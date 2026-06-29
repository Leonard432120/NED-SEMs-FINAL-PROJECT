<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'headteacher') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();
$school_id = (int)$_SESSION['school_id'];
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        s.class,
        COUNT(DISTINCT s.student_id) AS student_count,
        SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) AS active_count,
        ROUND(AVG(r.average_score), 1) AS avg_score,
        COUNT(r.result_id) AS result_count
    FROM students s
    LEFT JOIN results r ON r.student_id = s.student_id
    WHERE s.school_id = $school_id
    AND s.class IS NOT NULL
";
if ($search !== '') {
    $safe = $conn->real_escape_string($search);
    $sql .= " AND s.class LIKE '%$safe%'";
}
$sql .= " GROUP BY s.class ORDER BY s.class ASC";

$classes = $conn->query($sql);

$total_classes = $classes ? $classes->num_rows : 0;
$conn->close();

function perf_class($score) {
    if ($score === null || $score === '') return 'performance-indicator--low';
    if ($score >= 70) return 'performance-indicator--high';
    if ($score >= 40) return 'performance-indicator--mid';
    return 'performance-indicator--low';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Class Overview</title>
<?php $module_css = 'headteacher'; include __DIR__ . '/../common/head_assets.php'; ?>
</head>
<body>
<?php include '../common/header.php'; ?>
<div class="dashboard">
<?php include '../common/sidebar.php'; ?>
<div class="content">

<div class="page-header">
    <div>
        <h1 class="page-title">Class Overview</h1>
        <p class="stats-info">Student counts and performance summary per class.</p>
    </div>
    <a href="add_student.php" class="btn btn-dark">+ Add Student</a>
</div>

<div class="kpi-grid">
    <div class="kpi-card kpi-card--info">
        <div class="kpi-card__icon"></div>
        <div class="kpi-card__body">
            <span class="kpi-card__label">Active Classes</span>
            <span class="kpi-card__value"><?= $total_classes ?></span>
            <span class="kpi-card__hint">Form groups in your school</span>
        </div>
    </div>
</div>

<form method="GET" class="search-form">
    <input type="text" name="search" placeholder="Search class name..." value="<?= htmlspecialchars($search) ?>">
    <button type="submit">Filter</button>
</form>

<div class="section">
    <div class="table-container">
        <table class="table-striped">
            <thead>
                <tr>
                    <th>Class</th>
                    <th>Students</th>
                    <th>Active</th>
                    <th>Results</th>
                    <th>Avg Score</th>
                    <th>Performance</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($classes && $classes->num_rows > 0): ?>
                    <?php while ($row = $classes->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['class']) ?></strong></td>
                            <td><?= (int)$row['student_count'] ?></td>
                            <td><?= (int)$row['active_count'] ?></td>
                            <td><?= (int)$row['result_count'] ?></td>
                            <td><?= $row['avg_score'] !== null ? $row['avg_score'] . '%' : '—' ?></td>
                            <td>
                                <div class="progress-bar" style="max-width:120px;">
                                    <div class="progress-fill" style="width: <?= min(100, (float)($row['avg_score'] ?? 0)) ?>%"></div>
                                </div>
                            </td>
                            <td class="actions">
                                <a href="manage_students.php?class=<?= urlencode($row['class']) ?>" class="btn btn-small btn-primary">View Students</a>
                                <a href="reports.php" class="btn btn-small btn-secondary">Report</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center">No classes found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
</div>
<?php include '../common/footer.php'; ?>
