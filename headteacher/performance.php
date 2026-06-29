<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'headteacher') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();
$school_id = (int)$_SESSION['school_id'];
$school_users = "SELECT user_id FROM users WHERE school_id = $school_id";

$avg_score = round((float)($conn->query("SELECT AVG(r.average_score) AS v FROM results r JOIN students s ON r.student_id=s.student_id WHERE s.school_id=$school_id")->fetch_assoc()['v'] ?? 0), 1);
$total_results = (int)$conn->query("SELECT COUNT(*) AS t FROM results r JOIN students s ON r.student_id=s.student_id WHERE s.school_id=$school_id")->fetch_assoc()['t'];
$top_students = $conn->query("
    SELECT s.name, s.class, MAX(r.average_score) AS best_score
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    WHERE s.school_id = $school_id
    GROUP BY s.student_id
    ORDER BY best_score DESC
    LIMIT 8
");
$subject_perf = $conn->query("
    SELECT 
        sub.subject_name,
        ROUND(AVG(m.score),1) AS avg_score,
        COUNT(*) AS entries
    FROM marks m
    JOIN subjects sub ON m.subject_id = sub.subject_id
    JOIN students st ON m.student_id = st.student_id
    WHERE st.school_id = $school_id
    GROUP BY sub.subject_id
    ORDER BY avg_score DESC
");
$class_perf = $conn->query("
    SELECT s.class,
           ROUND(AVG(r.average_score),1) AS avg_score,
           COUNT(DISTINCT s.student_id) AS students
    FROM students s
    LEFT JOIN results r ON r.student_id=s.student_id
    WHERE s.school_id=$school_id AND s.class IS NOT NULL
    GROUP BY s.class
    ORDER BY avg_score DESC
");
$trend = $conn->query("
    SELECT DATE_FORMAT(r.compiled_at,'%b %Y') AS month_label,
           ROUND(AVG(r.average_score),1) AS avg_score
    FROM results r
    JOIN students s ON r.student_id=s.student_id
    WHERE s.school_id=$school_id
    GROUP BY DATE_FORMAT(r.compiled_at,'%Y-%m')
    ORDER BY MIN(r.compiled_at) DESC
    LIMIT 6
");
$trend_labels = []; $trend_data = [];
if ($trend) {
    $rows = [];
    while ($r = $trend->fetch_assoc()) { $rows[] = $r; }
    $rows = array_reverse($rows);
    foreach ($rows as $r) { $trend_labels[] = $r['month_label']; $trend_data[] = $r['avg_score']; }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Performance Analytics</title>
<?php $module_css = 'headteacher'; include __DIR__ . '/../common/head_assets.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php include '../common/header.php'; ?>
<div class="dashboard">
<?php include '../common/sidebar.php'; ?>
<div class="content">

<div class="page-header">
    <div>
        <h1 class="page-title">Performance Monitoring</h1>
        <p class="stats-info">Subject trends, class rankings, and top student performance.</p>
    </div>
    <a href="reports.php" class="btn btn-dark">Full School Report</a>
</div>

<div class="kpi-grid">
    <div class="kpi-card kpi-card--success">
        <div class="kpi-card__icon"></div>
        <div class="kpi-card__body">
            <span class="kpi-card__label">School Average</span>
            <span class="kpi-card__value"><?= $avg_score ?>%</span>
            <span class="kpi-card__hint">Across all recorded results</span>
        </div>
    </div>
    <div class="kpi-card kpi-card--info">
        <div class="kpi-card__icon"></div>
        <div class="kpi-card__body">
            <span class="kpi-card__label">Results Analysed</span>
            <span class="kpi-card__value"><?= $total_results ?></span>
            <span class="kpi-card__hint">Student exam entries</span>
        </div>
    </div>
</div>

<div class="panel-grid">
    <div class="section">
        <h3>Performance Trend</h3>
        <div class="chart-box"><canvas id="trendChart"></canvas></div>
    </div>
    <div class="section">
        <h3>Top Performing Students</h3>
        <div class="table-container">
            <table class="table-striped">
                <thead><tr><th>Student</th><th>Class</th><th>Best Score</th></tr></thead>
                <tbody>
                    <?php if ($top_students && $top_students->num_rows): while ($s = $top_students->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['name']) ?></td>
                            <td><?= htmlspecialchars($s['class'] ?? '—') ?></td>
                            <td><span class="badge badge-approved"><?= round($s['best_score'], 1) ?>%</span></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="3" class="text-center">No data yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="panel-grid">
    <div class="section">
        <h3>Subject Performance</h3>
        <div class="table-container">
            <table class="table-striped">
                <thead><tr><th>Subject</th><th>Average</th><th>Entries</th></tr></thead>
                <tbody>
                    <?php if ($subject_perf): while ($s = $subject_perf->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['subject_name']) ?></td>
                            <td><?= $s['avg_score'] ?>%</td>
                            <td><?= (int)$s['entries'] ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="section">
        <h3>Class Rankings</h3>
        <div class="table-container">
            <table class="table-striped">
                <thead><tr><th>Class</th><th>Students</th><th>Average</th></tr></thead>
                <tbody>
                    <?php if ($class_perf): while ($c = $class_perf->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['class']) ?></td>
                            <td><?= (int)$c['students'] ?></td>
                            <td><?= $c['avg_score'] ?? '—' ?>%</td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>
</div>
<?php include '../common/footer.php'; ?>
<script>
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($trend_labels) ?>,
        datasets: [{ label: 'Avg %', data: <?= json_encode($trend_data) ?>, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', fill: true, tension: 0.3 }]
    },
    options: { responsive: true, scales: { y: { beginAtZero: true, max: 100 } } }
});
</script>
</body>
</html>
