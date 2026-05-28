<?php
session_start();
include '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

$conn = get_db_connection();
$schools = $conn->query("SELECT school_id, school_name FROM schools WHERE status = 'active' ORDER BY school_name");
$school_counts = [];
while ($row = $schools->fetch_assoc()) {
    $school_counts[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>District Report</title>
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
            <h2>District Report</h2>
        </div>
        <div class="card card-accent-blue">
            <p>Active schools in district: <?= htmlspecialchars(count($school_counts)); ?></p>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/static/js/main.js"></script>
</body>
</html>