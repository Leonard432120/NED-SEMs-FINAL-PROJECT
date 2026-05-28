<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/NED-SEMs FINAL YEAR PROJECT');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NED-SEMS | National Examination System</title>

    <link rel="stylesheet" href="<?= BASE_URL ?>/static/css/styles.css">
</head>
<body>



<!-- ================= MAIN ================= -->
<main class="content">

<!-- ================= HERO ================= -->
<section class="hero">
    <div class="hero-content">
        <h1>National Examination Management System</h1>
        <p>
            A centralized platform for managing examinations, schools,
            users, and results across the country.
        </p>

    </div>

</section>

<!-- ================= STATS ================= -->
<section class="stats">

    <div class="stat-box">
        <h4>Schools</h4>
        <p>50+</p>
    </div>

    <div class="stat-box">
        <h4>Users</h4>
        <p>1,000+</p>
    </div>

    <div class="stat-box">
        <h4>Exams</h4>
        <p>300+</p>
    </div>

    <div class="stat-box">
        <h4>Results</h4>
        <p>50,000+</p>
    </div>

</section>

<!-- ================= SYSTEM OVERVIEW ================= -->
<section class="card">
    <h3>System Overview</h3>
    <p>
        NED-SEMS provides a secure and efficient environment for examination management,
        including user control, school registration, exam handling, results processing,
        and moderation.
    </p>
</section>

<!-- ================= FEATURES ================= -->
<section class="dashboard-grid">

    <div class="card">
        <div class="icon">
            <img src="<?= BASE_URL ?>/static/icons/management.png" alt="Role Access">
        </div>
        <h3>User Management</h3>
        <p>Create and manage system users with role-based access.</p>
    </div>

    <div class="card">
        <div class="icon">
            <img src="<?= BASE_URL ?>/static/icons/exam.png" alt="Exam Management">
        </div>
        <h3>Exam Management</h3>
        <p>Create, schedule, and monitor examinations.</p>
    </div>

    <div class="card">
        <div class="icon">
            <img src="<?= BASE_URL ?>/static/icons/reports.png" alt="Reports">
        </div>
        <h3>Reports & Analytics</h3>
        <p>Generate performance reports and insights.</p>
    </div>

    <div class="card">
        <div class="icon">
            <img src="<?= BASE_URL ?>/static/icons/security.png" alt="Security">
        </div>
        <h3>System Security</h3>
        <p>Secure authentication and controlled user access.</p>
    </div>

</section>

<!-- ================= CTA ================= -->
<section class="card" style="text-align:center;">
    <h3>Access Your Dashboard</h3>
    <p>Login using credentials provided by your administrator.</p>
    <br>
    <a href="<?= BASE_URL ?>/login.php" class="quick-btn">Login Now</a>
</section>

</main>

<!-- ================= FOOTER ================= -->
<footer class="footer">

    <div class="footer-grid">

        <div>
            <h4>NED-SEMS</h4>
            <p>National Examination Management System.</p>
        </div>

        <div>
            <h4>Modules</h4>
            <ul>
                <li>Users</li>
                <li>Schools</li>
                <li>Exams</li>
                <li>Results</li>
            </ul>
        </div>

        <div>
            <h4>System</h4>
            <ul>
                <li>Version 1.0</li>
                <li>MySQL Database</li>
                <li>Status: Running</li>
            </ul>
        </div>

        <div>
            <h4>Support</h4>
            <ul>
                <li>Email: support@ned-sems.gov</li>
                <li>Phone: +265</li>
            </ul>
        </div>

    </div>

</footer>

</body>
</html>