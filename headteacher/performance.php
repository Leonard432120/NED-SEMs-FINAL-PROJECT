<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'headteacher') {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

$school_id = $_SESSION['school_id'];
$conn = get_db_connection();
$average_score = $conn->query("SELECT AVG(score) AS avg_score FROM results r JOIN students s ON r.student_id = s.student_id WHERE s.school_id = $school_id")->fetch_assoc()['avg_score'];
$total_results = $conn->query("SELECT COUNT(*) AS total FROM results r JOIN students s ON r.student_id = s.student_id WHERE s.school_id = $school_id")->fetch_assoc()['total'];
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Performance</title>
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
            <span class="profile-name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Headteacher'); ?></span>
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
        <h2>Performance</h2>
        <div class="card card-accent-blue">
            <div class="analytics-grid">
                <div class="analytics-item">
                    <strong>Average Score</strong>
                    <span><?= htmlspecialchars(number_format($average_score ?? 0, 2)); ?></span>
                </div>
                <div class="analytics-item">
                    <strong>Total Results</strong>
                    <span><?= htmlspecialchars($total_results); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/static/js/main.js"></script>
</body>
</html>