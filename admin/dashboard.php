<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

/* ================= STATS ================= */
$schools = $conn->query("SELECT COUNT(*) AS total FROM schools")->fetch_assoc()['total'];
$users = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
$exams = $conn->query("SELECT COUNT(*) AS total FROM exams")->fetch_assoc()['total'];
$results = $conn->query("SELECT COUNT(*) AS total FROM results")->fetch_assoc()['total'];

$pending = $conn->query("SELECT COUNT(*) AS total FROM exams WHERE status='under_moderation'")->fetch_assoc()['total'];
$active_exams = $conn->query("SELECT COUNT(*) AS total FROM exams WHERE status IN ('submitted','approved')")->fetch_assoc()['total'];

/* ================= AI ALERTS ================= */
$low = $conn->query("SELECT COUNT(*) AS t FROM ai_alerts WHERE severity='low'")->fetch_assoc()['t'];
$medium = $conn->query("SELECT COUNT(*) AS t FROM ai_alerts WHERE severity='medium'")->fetch_assoc()['t'];
$high = $conn->query("SELECT COUNT(*) AS t FROM ai_alerts WHERE severity='high'")->fetch_assoc()['t'];

/* ================= ROLE DISTRIBUTION ================= */
$roles = $conn->query("SELECT role, COUNT(*) as total FROM users GROUP BY role");

/* ================= RECENT USERS ================= */
$recent_users = $conn->query("SELECT name, role FROM users ORDER BY user_id DESC LIMIT 5");

/* ================= LIVE ACTIVITY (FIXED COLUMN) ================= */
$logs = $conn->query("
    SELECT a.*, u.name
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.user_id
    ORDER BY a.created_at DESC
    LIMIT 5
");

/* ================= CHART DATA ================= */
$draft = $exams;
$approved = $conn->query("SELECT COUNT(*) AS total FROM exams WHERE status='approved'")->fetch_assoc()['total'];
$rejected = $conn->query("SELECT COUNT(*) AS total FROM exams WHERE status='rejected'")->fetch_assoc()['total'];

$chart_labels = ['Draft', 'Approved', 'Rejected'];
$chart_data = [$draft, $approved, $rejected];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>

<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/admin.css">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* ===== MODERN GOVERNMENT STYLE UI ===== */

body {
    background: #f4f6f9;
    font-family: Arial, sans-serif;
}

.dashboard {
    display: flex;
}

/* MAIN CONTENT */
.content {
    flex: 1;
    padding: 20px;
}

/* STATS */
.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.stat-box {
    background: white;
    padding: 15px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.stat-box h4 {
    margin: 0;
    font-size: 14px;
    color: #555;
}

.stat-box p {
    font-size: 20px;
    font-weight: bold;
    margin-top: 8px;
}

/* SECTION */
.section {
    margin-top: 20px;
    background: white;
    padding: 15px;
    border-radius: 10px;
}

/* QUICK LINKS */
.quick-links a {
    display: inline-block;
    margin: 5px;
    padding: 8px 12px;
    background: #2d6cdf;
    color: white;
    border-radius: 5px;
    text-decoration: none;
}

/* ALERTS */
.alert-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}

.alert {
    padding: 12px;
    border-radius: 8px;
    color: white;
    font-size: 14px;
}

.low { background: #2ecc71; }
.medium { background: #f39c12; }
.high { background: #e74c3c; }

/* FEED */
.feed-item {
    padding: 10px;
    border-bottom: 1px solid #eee;
    font-size: 13px;
}

/* CHART */
.chart-box {
    height: 300px;
}
</style>

</head>

<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">

<?php include '../common/sidebar.php'; ?>

<!-- ================= MAIN CONTENT ================= -->
<div class="content">

<h2>EDM Dashboard Overview</h2>

<!-- ================= STATS ================= -->
<div class="stats">

<div class="stat-box"><h4>Schools</h4><p><?= $schools ?></p></div>
<div class="stat-box"><h4>Users</h4><p><?= $users ?></p></div>
<div class="stat-box"><h4>Exams</h4><p><?= $exams ?></p></div>
<div class="stat-box"><h4>Results</h4><p><?= $results ?></p></div>
<div class="stat-box"><h4>Pending Moderation</h4><p><?= $pending ?></p></div>
<div class="stat-box"><h4>Active Exams</h4><p><?= $active_exams ?></p></div>

</div>

<!-- ================= QUICK ACTIONS ================= -->
<div class="section">
<h3>Quick Actions</h3>

<div class="quick-links">
    <a href="add_user.php">+ Add User</a>
    <a href="add_school.php">+ Add School</a>
    <a href="create_exam.php">+ Create Exam</a>
    <a href="assign.php">+ Assign Teachers</a>
</div>
</div>

<!-- ================= ALERT CARDS ================= -->
<div class="section">
<h3>AI Alert Severity</h3>

<div class="alert-grid">
    <div class="alert low">Low Alerts: <?= $low ?></div>
    <div class="alert medium">Medium Alerts: <?= $medium ?></div>
    <div class="alert high">High Alerts: <?= $high ?></div>
</div>

</div>

<!-- ================= CHART ================= -->
<div class="section">
    <h3>📊 Exam Status Overview</h3>
    <div class="chart-box">
        <canvas id="examChart"></canvas>
    </div>
</div>

<!-- ================= LIVE ACTIVITY ================= -->
<div class="section">
<h3>Live Activity Feed</h3>

<?php while($log = $logs->fetch_assoc()): ?>
    <div class="feed-item">
        <b><?= htmlspecialchars($log['name'] ?? 'System') ?></b>
        - <?= htmlspecialchars($log['action'] ?? 'Activity') ?>
        <small>(<?= $log['created_at'] ?>)</small>
    </div>
<?php endwhile; ?>

</div>

<!-- ================= RECENT USERS ================= -->
<div class="section">
<h3>Recent Users</h3>

<table border="1" width="100%" cellpadding="8">
<tr>
<th>Name</th>
<th>Role</th>
</tr>

<?php while($u = $recent_users->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($u['name']) ?></td>
<td><?= htmlspecialchars($u['role']) ?></td>
</tr>
<?php endwhile; ?>

</table>

</div>

</div>
</div>

<?php include '../common/footer.php'; ?>

<!-- ================= CHART SCRIPT ================= -->
<script>
const ctx = document.getElementById('examChart');

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
            label: 'Exams',
            data: <?= json_encode($chart_data) ?>,
            borderWidth: 1
        }]
    },
    options: {
        responsive: true
    }
});
</script>

</body>
</html>


