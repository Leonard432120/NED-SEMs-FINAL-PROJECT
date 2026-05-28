<?php
session_start();
include '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

$conn = get_db_connection();
$total_exams = $conn->query("SELECT COUNT(*) AS total FROM exams")->fetch_assoc()['total'];
$approved_exams = $conn->query("SELECT COUNT(*) AS total FROM exams WHERE status = 'approved'")->fetch_assoc()['total'];
$avg_duration = $conn->query("SELECT AVG(duration_minutes) AS avg_duration FROM exams")->fetch_assoc()['avg_duration'];
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Performance Report</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/admin.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/form.css">
</head>
<body>
<div class="header">
    <div class="header-left">
        <span class="dashboard-title">NED-SEMS | EDM Control Center</span>
    </div>
    <div class="header-right">
        <div class="profile">
           <a href="<?= BASE_URL ?>/logout.php">Logout</a><img src="<?= BASE_URL ?>/static/images/user.png">
        </div>
    </div>
</div>
<div class="dashboard">
    <div class="sidebar">
        <a href="../dashboard.php">Dashboard</a>
        <a href="../manage_users.php">Users</a>
        <div class="sidebar-group">
            <span onclick="toggleMenu('schoolMenu')">Schools ▼</span>
            <div class="sidebar-sub" id="schoolMenu">
                <a href="../add_school.php">Add School</a>
                <a href="../manage_schools.php">Manage Schools</a>
            </div>
        </div>
        <a href="../manage_subject.php">Subjects</a>
        <a href="../exams.php">Exams</a>
        <div class="sidebar-group">
            <span onclick="toggleMenu('assignMenu')">Assignments ▼</span>
            <div class="sidebar-sub" id="assignMenu">
                <a href="../assign.php">Assign Teachers</a>
                <a href="../view_assignments.php">View Assignments</a>
            </div>
        </div>
        <div class="sidebar-group">
            <span onclick="toggleMenu('reportMenu')">Reports ▼</span>
            <div class="sidebar-sub" id="reportMenu">
                <a href="anomalies.php">Anomalies</a>
                <a href="compliance_report.php">Compliance</a>
                <a href="district_report.php">District</a>
                <a href="performance.php">Performance</a>
                <a href="ranking.php">Ranking</a>
            </div>
        </div>
    </div>
    <div class="main-content">
        <div class="page-header">
            <h2>Performance Report</h2>
        </div>
        <div class="card card-accent-blue">
            <p>Total exams: <?= htmlspecialchars($total_exams); ?></p>
            <p>Approved exams: <?= htmlspecialchars($approved_exams); ?></p>
            <p>Average duration: <?= htmlspecialchars(number_format($avg_duration ?? 0, 1)); ?> min</p>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/static/js/main.js"></script>
</body>
</html>