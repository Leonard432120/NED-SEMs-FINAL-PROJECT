<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

/* ================= SAMPLE KPI DATA ================= */
// You can replace these with real SQL later
$students = $conn->query("SELECT COUNT(*) as c FROM students")->fetch_assoc()['c'] ?? 0;
$teachers = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'teacher'")->fetch_assoc()['c'] ?? 0;
$classes = $conn->query("SELECT COUNT(DISTINCT class) as c FROM students WHERE class IS NOT NULL")->fetch_assoc()['c'] ?? 0;
$attendance = 94; // later calculate from attendance table

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Performance Dashboard</title>

<?php $module_css = 'admin'; include __DIR__ . '/../../common/head_assets.php'; ?>


<style>
.dashboard-grid{
    display:grid;
    grid-template-columns: repeat(4,1fr);
    gap:15px;
    margin-bottom:20px;
}

.card-kpi{
    background:#fff;
    padding:15px;
    border-radius:12px;
    box-shadow:0 2px 8px rgba(0,0,0,0.05);
}

.card-kpi h3{
    font-size:14px;
    color:#666;
}

.card-kpi h1{
    font-size:26px;
    margin-top:5px;
}

.chart-grid{
    display:grid;
    grid-template-columns: 2fr 1fr;
    gap:20px;
}

.chart-box{
    background:#fff;
    padding:15px;
    border-radius:12px;
    box-shadow:0 2px 8px rgba(0,0,0,0.05);
}
</style>

</head>

<body>

<?php include __DIR__ . '/../../common/header.php'; ?>

<div class="dashboard">
<?php include __DIR__ . '/../../common/sidebar.php'; ?>

<div class="content">

<!-- HEADER -->
<div class="page-header">
    <h2 class="page-title">Performance Dashboard</h2>
</div>

<!-- KPI CARDS -->
<div class="dashboard-grid">

    <div class="card-kpi">
        <h3>Students</h3>
        <h1><?= $students ?></h1>
    </div>

    <div class="card-kpi">
        <h3>Teachers</h3>
        <h1><?= $teachers ?></h1>
    </div>

    <div class="card-kpi">
        <h3>Classes</h3>
        <h1><?= $classes ?></h1>
    </div>

    <div class="card-kpi">
        <h3>Attendance Rate</h3>
        <h1><?= $attendance ?>%</h1>
    </div>

</div>

<!-- CHARTS -->
<div class="chart-grid">

    <!-- LINE CHART -->
    <div class="chart-box">
        <h3>Student Performance Trend</h3>
        <canvas id="lineChart"></canvas>
    </div>

    <!-- DONUT CHART -->
    <div class="chart-box">
        <h3>Attendance Overview</h3>
        <canvas id="donutChart"></canvas>
    </div>

</div>

</div>
</div>

<script>
/* ================= LINE CHART ================= */
new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: {
        labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul'],
        datasets: [{
            label: 'Performance',
            data: [65, 70, 68, 75, 80, 85, 90],
            borderColor: '#4e73df',
            fill: false,
            tension: 0.4
        }]
    }
});

/* ================= DONUT CHART ================= */
new Chart(document.getElementById('donutChart'), {
    type: 'doughnut',
    data: {
        labels: ['Present','Absent','Late'],
        datasets: [{
            data: [85,10,5],
            backgroundColor: ['#1cc88a','#e74a3b','#f6c23e']
        }]
    }
});
</script>

</body>
</html>