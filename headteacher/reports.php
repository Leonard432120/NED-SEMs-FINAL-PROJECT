<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'headteacher') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();
$school_id = (int)$_SESSION['school_id'];

// Get school info
$school_info = $conn->query("SELECT school_name, district, address FROM schools WHERE school_id = $school_id")->fetch_assoc();

// Performance statistics
$total_students = $conn->query("SELECT COUNT(*) as cnt FROM students WHERE school_id = $school_id")->fetch_assoc()['cnt'];
$total_teachers = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE school_id = $school_id AND role = 'teacher'")->fetch_assoc()['cnt'];
$total_exams = $conn->query("SELECT COUNT(*) as cnt FROM exams WHERE created_by IN (SELECT user_id FROM users WHERE school_id = $school_id)")->fetch_assoc()['cnt'];

// Results analysis
$avg_score_result = $conn->query("
    SELECT AVG(r.average_score) as avg_percentage
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    WHERE s.school_id = $school_id
    AND r.status = 'published'
");

$avg_data = $avg_score_result->fetch_assoc();
$avg_percentage = round($avg_data['avg_percentage'] ?? 0, 2);

// Pass rate
$total_results = $conn->query("
    SELECT COUNT(*) as cnt
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    WHERE s.school_id = $school_id
    AND r.status = 'published'
")->fetch_assoc()['cnt'];
$passing_results = $conn->query("
    SELECT COUNT(*) as cnt
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    WHERE s.school_id = $school_id
    AND r.average_score >= 40
    AND r.status = 'published'
")->fetch_assoc()['cnt'];
$pass_rate = $total_results > 0 ? round(($passing_results / $total_results) * 100, 2) : 0;

// Top performing subjects
$top_subjects = $conn->query("
    SELECT
        sub.subject_name,
        ROUND(AVG(m.score),2) AS avg_score,
        COUNT(*) AS student_count
    FROM marks m
    JOIN subjects sub
        ON m.subject_id = sub.subject_id
    JOIN students st
        ON m.student_id = st.student_id
    WHERE st.school_id = $school_id
    AND m.submission_status = 'received'
    GROUP BY sub.subject_id
    ORDER BY avg_score DESC
    LIMIT 5
");
// Exam status breakdown
$exam_status = $conn->query("
    SELECT status, COUNT(*) as count
    FROM exams
    WHERE created_by IN (SELECT user_id FROM users WHERE school_id = $school_id)
    GROUP BY status
");

// Class performance
$class_performance = $conn->query("
    SELECT
        s.class,
        COUNT(DISTINCT s.student_id) AS student_count,
        ROUND(AVG(r.average_score),2) AS avg_score
    FROM results r
    JOIN students s
        ON r.student_id = s.student_id
    WHERE s.school_id = $school_id
    AND r.status = 'published'
    GROUP BY s.class
    ORDER BY avg_score DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>School Reports</title>

<?php
$portal_title = 'NED-SEMS | Headteacher Portal';
$module_css = 'headteacher';
include __DIR__ . '/../common/head_assets.php';
?>
</head>

<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">

<?php include '../common/sidebar.php'; ?>

<div class="content">

<div class="page-header">
    <h1>School Reports</h1>
    <p><?= htmlspecialchars($school_info['school_name']) ?> Performance Analytics</p>
</div>

<!-- ================= KEY STATISTICS ================= -->
<div class="stats">

<div class="stat-box">
    <h4>Total Students</h4>
    <p><?= $total_students ?></p>
</div>

<div class="stat-box">
    <h4>Total Teachers</h4>
    <p><?= $total_teachers ?></p>
</div>

<div class="stat-box">
    <h4>Total Exams</h4>
    <p><?= $total_exams ?></p>
</div>

<div class="stat-box">
    <h4>Average Score</h4>
    <p><?= $avg_percentage ?>%</p>
</div>

<div class="stat-box">
    <h4>Pass Rate</h4>
    <p><?= $pass_rate ?>%</p>
</div>

<div class="stat-box">
    <h4>Results Recorded</h4>
    <p><?= $total_results ?></p>
</div>

</div>

<!-- ================= PERFORMANCE OVERVIEW ================= -->
<div class="section">

<h3>Overall Performance</h3>

<div class="two-column">

<div>
<h4>Pass Rate Analysis</h4>
<div class="progress-bar">
    <div class="progress-fill" style="width: <?= $pass_rate ?>%">
        <?= $pass_rate ?>%
    </div>
</div>
<p style="color: #666; font-size: 14px;">
    <?= $passing_results ?> out of <?= $total_results ?> students passed (≥40%)
</p>
</div>

<div>
<h4>Average Performance</h4>
<div class="progress-bar">
    <div class="progress-fill" style="width: <?= $avg_percentage ?>%">
        <?= $avg_percentage ?>%
    </div>
</div>
<p style="color: #666; font-size: 14px;">
    School average score across all subjects
</p>
</div>

</div>

</div>

<!-- ================= TOP PERFORMING SUBJECTS ================= -->
<div class="section">

<h3>Top Performing Subjects</h3>

<?php if ($top_subjects && $top_subjects->num_rows > 0): ?>

<table>

<thead>
<tr>
    <th>Subject</th>
    <th>Average Score</th>
    <th>Students Assessed</th>
    <th>Performance</th>
</tr>
</thead>

<tbody>

<?php while ($subject = $top_subjects->fetch_assoc()): ?>

<tr>
    <td><?= htmlspecialchars($subject['subject_name']) ?></td>
    <td><?= round($subject['avg_score'], 2) ?>%</td>
    <td><?= $subject['student_count'] ?></td>
    <td>
        <div class="progress-bar" style="width: 150px;">
            <div class="progress-fill" style="width: <?= $subject['avg_score'] ?>%"></div>
        </div>
    </td>
</tr>

<?php endwhile; ?>

</tbody>

</table>

<?php else: ?>

<div class="no-data">
    <p>No subject performance data available.</p>
</div>

<?php endif; ?>

</div>

<!-- ================= CLASS PERFORMANCE ================= -->
<div class="section">

<h3>Performance by Class</h3>

<?php if ($class_performance && $class_performance->num_rows > 0): ?>

<table>

<thead>
<tr>
    <th>Class</th>
    <th>Students</th>
    <th>Average Score</th>
    <th>Performance</th>
</tr>
</thead>

<tbody>

<?php while ($class = $class_performance->fetch_assoc()): ?>

<tr>
    <td><?= htmlspecialchars($class['class']) ?></td>
    <td><?= $class['student_count'] ?></td>
    <td><?= round($class['avg_score'], 2) ?>%</td>
    <td>
        <div class="progress-bar" style="width: 150px;">
            <div class="progress-fill" style="width: <?= $class['avg_score'] ?>%"></div>
        </div>
    </td>
</tr>

<?php endwhile; ?>

</tbody>

</table>

<?php else: ?>

<div class="no-data">
    <p>No class performance data available.</p>
</div>

<?php endif; ?>

</div>

<!-- ================= EXAM STATUS ================= -->
<div class="section">

<h3>Exam Status Distribution</h3>

<?php if ($exam_status && $exam_status->num_rows > 0): ?>

<table>

<thead>
<tr>
    <th>Status</th>
    <th>Count</th>
    <th>Percentage</th>
</tr>
</thead>

<tbody>

<?php 
$total_exams_count = 0;
$exams_by_status = [];
$exam_status->data_seek(0);
while ($row = $exam_status->fetch_assoc()) {
    $exams_by_status[] = $row;
    $total_exams_count += $row['count'];
}

foreach ($exams_by_status as $es): 
$percentage = $total_exams_count > 0 ? ($es['count'] / $total_exams_count * 100) : 0;
?>

<tr>
    <td><span class="badge badge-primary"><?= ucfirst(str_replace('_', ' ', $es['status'])) ?></span></td>
    <td><?= $es['count'] ?></td>
    <td><?= round($percentage, 2) ?>%</td>
</tr>

<?php endforeach; ?>

</tbody>

</table>

<?php else: ?>

<div class="no-data">
    <p>No exam data available.</p>
</div>

<?php endif; ?>

</div>

</div>

</div>

<?php
$conn->close();
?>
<?php include '../common/footer.php'; ?>