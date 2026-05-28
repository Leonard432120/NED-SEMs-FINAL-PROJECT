<?php
require_once __DIR__ . '/teacher_init.php';

if (!isset($_GET['exam_id']) || !is_numeric($_GET['exam_id'])) {
    die("Invalid exam ID");
}

$exam_id = (int)$_GET['exam_id'];

$conn = get_db_connection();
$cursor = $conn->prepare("
    SELECT e.*, s.subject_name
    FROM exams e
    JOIN subjects s ON e.subject_id = s.subject_id
    WHERE e.exam_id = ?
");
$cursor->bind_param("i", $exam_id);
$cursor->execute();
$exam = $cursor->get_result()->fetch_assoc();
$cursor->close();

if (!$exam) {
    die("Exam not found");
}

// Check if teacher is assigned to this exam
$check_stmt = $conn->prepare("
    SELECT 1 FROM exam_assignments
    WHERE exam_id = ? AND teacher_id = ? AND role IN ('item_writer', 'moderator')
");
$check_stmt->bind_param("ii", $exam_id, $user_id);
$check_stmt->execute();
if ($check_stmt->get_result()->num_rows == 0) {
    die("Access denied");
}
$check_stmt->close();

// Get results
$results_stmt = $conn->prepare("
    SELECT r.*, s.name
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    WHERE r.exam_id = ?
");
$results_stmt->bind_param("i", $exam_id);
$results_stmt->execute();
$results = $results_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$results_stmt->close();

// Stats
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) AS total_students,
        AVG(percentage) AS avg_score,
        SUM(CASE WHEN percentage >= 50 THEN 1 ELSE 0 END) AS passed
    FROM results
    WHERE exam_id = ?
");
$stats_stmt->bind_param("i", $exam_id);
$stats_stmt->execute();
$stats_row = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

$stats = $stats_row ?: [
    "total_students" => 0,
    "avg_score" => 0,
    "passed" => 0
];

$pass_rate = $stats["total_students"] > 0 ? round(($stats["passed"] / $stats["total_students"]) * 100, 1) : 0;

// Grade distribution
$grade_stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN percentage >= 75 THEN 1 ELSE 0 END) A,
        SUM(CASE WHEN percentage >= 65 AND percentage < 75 THEN 1 ELSE 0 END) B,
        SUM(CASE WHEN percentage >= 50 AND percentage < 65 THEN 1 ELSE 0 END) C,
        SUM(CASE WHEN percentage >= 40 AND percentage < 50 THEN 1 ELSE 0 END) D,
        SUM(CASE WHEN percentage < 40 THEN 1 ELSE 0 END) F
    FROM results
    WHERE exam_id = ?
");
$grade_stmt->bind_param("i", $exam_id);
$grade_stmt->execute();
$grade_dist = $grade_stmt->get_result()->fetch_assoc();
$grade_stmt->close();

// Historical
$historical_stmt = $conn->prepare("
    SELECT e.year, AVG(r.percentage) AS avg_score
    FROM results r
    JOIN exams e ON r.exam_id = e.exam_id
    WHERE e.subject_id = ?
    GROUP BY e.year
    ORDER BY e.year
");
$historical_stmt->bind_param("i", $exam["subject_id"]);
$historical_stmt->execute();
$historical = $historical_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$historical_stmt->close();

// Subject performance
$subject_stmt = $conn->prepare("
    SELECT s.subject_name, AVG(r.percentage) AS avg_score
    FROM results r
    JOIN exams e ON r.exam_id = e.exam_id
    JOIN subjects s ON e.subject_id = s.subject_id
    GROUP BY s.subject_name
");
$subject_stmt->execute();
$subject_performance = $subject_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$subject_stmt->close();

$conn->close();

// AI insights
$ai_insights = [];
$predicted_next = null;

$values = array_column($historical, 'avg_score');

if (count($values) >= 2) {
    $growth = [];
    for ($i = 1; $i < count($values); $i++) {
        $growth[] = $values[$i] - $values[$i-1];
    }
    $avg_growth = array_sum($growth) / count($growth);

    $predicted_next = round($values[count($values)-1] + $avg_growth, 1);

    if ($avg_growth > 0) {
        $ai_insights[] = "Performance improving 📈";
    } elseif ($avg_growth < 0) {
        $ai_insights[] = "Performance declining ⚠️";
    } else {
        $ai_insights[] = "Performance stable";
    }

    $ai_insights[] = "Predicted next: {$predicted_next}%";
} else {
    $ai_insights[] = "Not enough data for prediction";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Analytics - <?= htmlspecialchars($exam['exam_name']); ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/admin.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/form.css">
</head>
<body>
<div class="header">
    <div class="header-left">
        <span class="dashboard-title">NED-SEMS | Teacher Portal</span>
    </div>
    <div class="header-right">
        <div class="profile">
           <a href="<?= BASE_URL ?>/logout.php">Logout</a><img src="<?= BASE_URL ?>/static/images/user.png">
        </div>
    </div>
</div>
<div class="dashboard">
    <?php include __DIR__ . '/teacher_sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h2>Analytics for <?= htmlspecialchars($exam['exam_name']); ?> (<?= htmlspecialchars($exam['subject_name']); ?>)</h2>
        </div>
        <div class="card">
            <h3>Exam Statistics</h3>
            <p>Total Students: <?= $stats['total_students']; ?></p>
            <p>Average Score: <?= round($stats['avg_score'], 1); ?>%</p>
            <p>Pass Rate: <?= $pass_rate; ?>%</p>
        </div>
        <div class="card">
            <h3>Grade Distribution</h3>
            <ul>
                <li>A: <?= $grade_dist['A'] ?? 0; ?></li>
                <li>B: <?= $grade_dist['B'] ?? 0; ?></li>
                <li>C: <?= $grade_dist['C'] ?? 0; ?></li>
                <li>D: <?= $grade_dist['D'] ?? 0; ?></li>
                <li>F: <?= $grade_dist['F'] ?? 0; ?></li>
            </ul>
        </div>
        <div class="card">
            <h3>Historical Performance</h3>
            <table>
                <tr><th>Year</th><th>Avg Score</th></tr>
                <?php foreach ($historical as $h): ?>
                <tr><td><?= $h['year']; ?></td><td><?= round($h['avg_score'], 1); ?>%</td></tr>
                <?php endforeach; ?>
            </table>
        </div>
        <div class="card">
            <h3>Subject Performance</h3>
            <table>
                <tr><th>Subject</th><th>Avg Score</th></tr>
                <?php foreach ($subject_performance as $sp): ?>
                <tr><td><?= htmlspecialchars($sp['subject_name']); ?></td><td><?= round($sp['avg_score'], 1); ?>%</td></tr>
                <?php endforeach; ?>
            </table>
        </div>
        <div class="card">
            <h3>AI Insights</h3>
            <ul>
                <?php foreach ($ai_insights as $insight): ?>
                <li><?= htmlspecialchars($insight); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/static/js/main.js"></script>
</body>
</html>