<?php
require_once __DIR__ . '/teacher_init.php';

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if ($exam_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

$conn = get_db_connection();

// Check access
$stmt = $conn->prepare("
    SELECT 1 FROM exam_assignments
    WHERE exam_id = ? AND teacher_id = ? AND role = 'item_writer'
");
$stmt->bind_param("ii", $exam_id, $user_id);
$stmt->execute();
if ($stmt->get_result()->num_rows == 0) {
    $conn->close();
    header("Location: dashboard.php");
    exit();
}
$stmt->close();

// Get exam
$stmt = $conn->prepare("
    SELECT exam_id, exam_name, class, total_marks
    FROM exams
    WHERE exam_id = ?
");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) {
    $conn->close();
    header("Location: dashboard.php");
    exit();
}

// Get results
$stmt = $conn->prepare("
    SELECT r.*, s.name, s.exam_number
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    WHERE r.exam_id = ?
    ORDER BY r.position_in_class ASC
");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Results - <?= htmlspecialchars($exam['exam_name']); ?></title>
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
            <h2>Results for <?= htmlspecialchars($exam['exam_name']); ?> (Class <?= htmlspecialchars($exam['class']); ?>)</h2>
        </div>
        <div class="card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Position</th>
                            <th>Student Name</th>
                            <th>Exam Number</th>
                            <th>Score</th>
                            <th>Percentage</th>
                            <th>Grade</th>
                            <th>Remarks</th>




                            am not saying make exam.php instead no i mean make the files in exam folder to be the .php files and allow them to work as they are now .
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $result): ?>
                        <tr>
                            <td><?= $result['position_in_class']; ?></td>
                            <td><?= htmlspecialchars($result['name']); ?></td>
                            <td><?= htmlspecialchars($result['exam_number']); ?></td>
                            <td><?= $result['total_score']; ?>/<?= $exam['total_marks']; ?></td>
                            <td><?= number_format($result['percentage'], 2); ?>%</td>
                            <td><?= htmlspecialchars($result['grade']); ?></td>
                            <td><?= htmlspecialchars($result['remarks']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($results)): ?>
                        <tr>
                            <td colspan="7">No results entered yet.</td>
                        </tr>
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