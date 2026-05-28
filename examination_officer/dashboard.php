<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'examination_officer') {
    header("Location: ../login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Examination Officer Portal</title>

<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/styles.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/form.css">
</head>

<body>

<!-- ================= HEADER ================= -->
<div class="header">

    <div class="header-left">
        <span class="dashboard-title">Examination Officer Portal</span>
    </div>

    <div class="header-right">

        <div class="profile">

            <!-- Profile Name -->
            <span class="profile-name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Exam Officer'); ?></span>

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

        <!-- Add more links as needed -->

    </div>

    <!-- ================= MAIN CONTENT ================= -->
    <div class="main-content">

<h2>Examination Officer Dashboard</h2>

<div class="card">
    <p>Welcome to the Examination Officer Portal.</p>
</div>

    </div>

</div>

<script src="<?= BASE_URL ?>/static/js/main.js"></script>
</body>
</html>