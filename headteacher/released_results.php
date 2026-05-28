<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'headteacher') {
    header('Location: ../login.php');
    exit();
}

$school_id = $_SESSION['school_id'];
$conn = get_db_connection();

function shell_escape($value) {
    return '"' . str_replace('"', '\\"', $value) . '"';
}

function get_ai_summary(array $metrics) {
    $repoRoot = dirname(__DIR__);
    $cli = $repoRoot . '/ai_results_summary_cli.py';
    $pythonCandidates = ['python', 'py -3', 'python3'];
    $summary = 'AI summary unavailable at the moment.';
    $metrics_json = json_encode($metrics);

    foreach ($pythonCandidates as $python) {
        $command = 'cd /d ' . shell_escape($repoRoot) . ' && ' . $python . ' ' . shell_escape($cli);
        $command .= ' --context school';
        $command .= ' --metrics ' . shell_escape($metrics_json);
        $command .= ' 2>&1';
        $output = [];
        $exitCode = 1;
        exec($command, $output, $exitCode);

        if ($exitCode === 0) {
            $json = implode("\n", $output);
            $data = json_decode($json, true);
            if (is_array($data) && isset($data['summary'])) {
                return $data['summary'];
            }
        }
    }

    return $summary;
}

$results_stmt = $conn->prepare(
    "SELECT
        r.result_id,
        r.total_score,
        r.percentage,
        r.grade,
        r.remarks,
        r.position_in_class,
        r.status,
        e.exam_name,
        e.class,
        s.name AS student_name,
        s.exam_number,
        sub.subject_name
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    JOIN exams e ON r.exam_id = e.exam_id
    JOIN subjects sub ON e.subject_id = sub.subject_id
    WHERE s.school_id = ?
      AND r.status = 'head_approved'
    ORDER BY e.exam_name, r.percentage DESC"
);
$results_stmt->bind_param('i', $school_id);
$results_stmt->execute();
$results = $results_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$results_stmt->close();

$summary_stmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total_published,
        ROUND(AVG(percentage), 2) AS average_percentage,
        SUM(percentage >= 40) AS pass_count
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    WHERE s.school_id = ?
      AND r.status = 'head_approved'"
);
$summary_stmt->bind_param('i', $school_id);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();
$summary_stmt->close();

$ai_summary = get_ai_summary($summary);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Final Results</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/styles.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/form.css">
</head>
<body>
<div class="header">
    <div class="header-left">
        <span class="dashboard-title">Headteacher Portal</span>
    </div>
    <div class="header-right">
        <div class="profile">
            <span class="profile-name"><?= htmlspecialchars($_SESSION['name'] ?? 'Headteacher'); ?></span>
            <img src="<?= BASE_URL ?>/static/images/user.png" alt="Profile">
            <a href="<?= BASE_URL ?>/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</div>
<div class="dashboard">
    <div class="sidebar">
        <a href="dashboard.php">Dashboard</a>
        <a href="manage_students.php">Manage Students</a>
        <a href="add_student.php">Add Student</a>
        <a href="reports.php">Reports</a>
        <a href="performance.php">Performance</a>
        <a href="released_results.php">Final Results</a>
    </div>
    <div class="main-content">
        <h2>Final Results Published</h2>

        <div class="card card-accent-light">
            <h3>Executive Summary</h3>
            <p><?= nl2br(htmlspecialchars($ai_summary)); ?></p>
        </div>

        <div class="card card-accent-blue">
            <div class="analytics-grid">
                <div class="analytics-item">
                    <strong>Total Published</strong>
                    <span><?= htmlspecialchars($summary['total_published'] ?? 0); ?></span>
                </div>
                <div class="analytics-item">
                    <strong>Average Score</strong>
                    <span><?= htmlspecialchars($summary['average_percentage'] ?? '0'); ?>%</span>
                </div>
                <div class="analytics-item">
                    <strong>Pass Rate</strong>
                    <span><?php
                        $pass_rate = 0;
                        if (!empty($summary['total_published'])) {
                            $pass_rate = round($summary['pass_count'] / $summary['total_published'] * 100, 0);
                        }
                        echo htmlspecialchars($pass_rate) . '%';
                    ?></span>
                </div>
            </div>
        </div>

        <div class="card">
            <h3>Published Results</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Exam</th>
                            <th>Subject</th>
                            <th>Class</th>
                            <th>Exam No.</th>
                            <th>Score</th>
                            <th>%</th>
                            <th>Grade</th>
                            <th>Rank</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($results)): ?>
                            <tr><td colspan="9">No published results are available yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($results as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['student_name']); ?></td>
                                    <td><?= htmlspecialchars($row['exam_name']); ?></td>
                                    <td><?= htmlspecialchars($row['subject_name']); ?></td>
                                    <td><?= htmlspecialchars($row['class']); ?></td>
                                    <td><?= htmlspecialchars($row['exam_number']); ?></td>
                                    <td><?= htmlspecialchars($row['total_score']); ?></td>
                                    <td><?= htmlspecialchars($row['percentage']); ?>%</td>
                                    <td><?= htmlspecialchars($row['grade']); ?></td>
                                    <td><?= htmlspecialchars($row['position_in_class']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/static/js/main.js"></script>
</body>
</html>
