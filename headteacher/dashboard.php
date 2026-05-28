<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'headteacher') {
    header("Location: ../login.php");
    exit();
}

$school_id = $_SESSION['school_id'];

$conn = get_db_connection();

$student_count = $conn->query("SELECT COUNT(*) AS total FROM students WHERE school_id = $school_id")->fetch_assoc()['total'];
$result_count = $conn->query("SELECT COUNT(*) AS total FROM results r JOIN students s ON r.student_id = s.student_id WHERE s.school_id = $school_id")->fetch_assoc()['total'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Headteacher Portal</title>

<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/styles.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/form.css">
</head>

<body>

<!-- ================= HEADER ================= -->
<div class="header">

    <div class="header-left">
        <span class="dashboard-title">Headteacher Portal</span>
    </div>

    <div class="header-right">

        <div class="profile">

            <!-- Profile Name -->
            <span class="profile-name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Headteacher'); ?></span>

            <!-- Profile Image -->
            <img src="<?= BASE_URL ?>/static/images/user.png" alt="Profile">

            <!-- Logout -->
            <a href="<?= BASE_URL ?>/logout.php" class="logout-btn">Logout</a>

        </div>

    </div>

</div>

<!-- ================= MAIN LAYOUT ================= -->
<div class="dashboard">

    <!-- ================= SIDEBAR ================= -->
    <div class="sidebar">

        <a href="dashboard.php">Dashboard</a>

        <!-- STUDENTS -->
        <a href="manage_students.php">Manage Students</a>
        <a href="add_student.php">Add Student</a>

        <!-- REPORTS -->
        <a href="reports.php">Reports</a>
        <a href="performance.php">Performance</a>
        <a href="released_results.php">Final Results</a>

    </div>

    <!-- ================= MAIN CONTENT ================= -->
    <div class="main-content">

<h2>Headteacher Dashboard</h2>

<!-- ================= STATS ================= -->
<div class="stats-grid">

    <div class="stat-card">
        <h4>Total Students</h4>
        <p><?php echo $student_count; ?></p>
    </div>

    <div class="stat-card">
        <h4>Total Results</h4>
        <p><?php echo $result_count; ?></p>
    </div>

</div>

<!-- ================= QUICK ACTIONS ================= -->
<div class="card">

    <h3 style="margin-bottom:15px;">Quick Actions</h3>

    <div class="quick-actions">

        <a href="add_student.php" class="quick-btn">
            Add Student
        </a>

        <a href="reports.php" class="quick-btn">
            View Reports
        </a>

        <a href="released_results.php" class="quick-btn">
            View Final Results
        </a>

        <a href="performance.php" class="quick-btn">
            Performance
        </a>

    </div>

</div>

    </div>

</div>

<script src="<?= BASE_URL ?>/static/js/main.js"></script>
</body>
</html>