<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'examination_officer') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();
$school_id = (int)($_SESSION['school_id'] ?? 0);
$class_filter = trim($_GET['class'] ?? 'Form 2');
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

$scope = $school_id > 0 ? "st.school_id = $school_id" : "1=1";

$sql = "
    SELECT 
    st.class, 
    e.exam_name, 
    sub.subject_name,
    COUNT(r.result_id) AS students,

    ROUND(AVG(r.average_score), 1) AS avg_score,
    ROUND(MAX(r.average_score), 1) AS top_score,

    SUM(CASE WHEN r.average_score >= 40 THEN 1 ELSE 0 END) AS passed

    FROM results r
    JOIN students st ON r.student_id = st.student_id
    JOIN exams e ON r.exam_id = e.exam_id
    JOIN subjects sub ON e.subject_id = sub.subject_id
    WHERE st.school_id = $school_id
";
if ($class_filter !== '') $sql .= " AND st.class = '" . $conn->real_escape_string($class_filter) . "'";
if ($exam_id > 0) $sql .= " AND r.exam_id = $exam_id";
$sql .= " GROUP BY st.class, e.exam_id ORDER BY avg_score DESC";

$reports = $conn->query($sql);
$exams = $conn->query("SELECT exam_id, exam_name FROM exams ORDER BY exam_name");
$school = $school_id > 0 ? $conn->query("SELECT school_name FROM schools WHERE school_id=$school_id")->fetch_assoc() : null;

// Subject registration summary
$sub_reg_sql = "
    SELECT sub.subject_name, sub.subject_code, sub.category, COUNT(ss.student_id) as student_count
    FROM subjects sub
    JOIN student_subjects ss ON sub.subject_id = ss.subject_id
    JOIN students st ON ss.student_id = st.student_id
    WHERE st.school_id = $school_id
";
if ($class_filter !== '') $sub_reg_sql .= " AND st.class = '" . $conn->real_escape_string($class_filter) . "'";
$sub_reg_sql .= " GROUP BY sub.subject_id ORDER BY student_count DESC";

$subject_registrations = $conn->query($sub_reg_sql);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Exam Reports</title>
<?php $module_css = 'exam_officer'; include __DIR__ . '/../common/head_assets.php'; ?>
</head>
<body>
<?php include '../common/header.php'; ?>
<div class="dashboard">
<?php include '../common/sidebar.php'; ?>
<div class="content">

<div class="page-header">
    <div>
        <h1 class="page-title">Report Generation</h1>
        <p class="stats-info">Class and exam performance summaries — print-ready layout.</p>
    </div>
    <div class="header-actions">
        <button type="button" class="btn btn-dark" onclick="window.print()">🖨️ Print Report</button>
    </div>
</div>

<form method="GET" class="search-form">
    <select name="class">
        <option value="">All Classes</option>
        <option value="Form 1" <?= $class_filter === 'Form 1' ? 'selected' : '' ?>>Form 1</option>
        <option value="Form 2" <?= $class_filter === 'Form 2' ? 'selected' : '' ?>>Form 2</option>
    </select>
    <select name="exam_id">
        <option value="0">All Exams</option>
        <?php if ($exams): while ($ex = $exams->fetch_assoc()): ?>
            <option value="<?= $ex['exam_id'] ?>" <?= $exam_id === (int)$ex['exam_id'] ? 'selected' : '' ?>><?= htmlspecialchars($ex['exam_name']) ?></option>
        <?php endwhile; endif; ?>
    </select>
    <button type="submit">Generate</button>
</form>

<div class="card" style="margin-bottom:24px;">
    <h3><?= htmlspecialchars($school['school_name'] ?? 'School') ?> — Examination Performance Report</h3>
    <p class="muted-text">Generated on <?= date('d F Y') ?> · Class filter: <?= $class_filter ?: 'All' ?></p>
</div>

<div class="section">
    <div class="table-container">
        <table class="table-striped">
            <thead>
                <tr>
                    <th>Class</th>
                    <th>Exam</th>
                    <th>Subject</th>
                    <th>Students</th>
                    <th>Average %</th>
                    <th>Top Score</th>
                    <th>Pass Count</th>
                    <th>Pass Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reports && $reports->num_rows): while ($row = $reports->fetch_assoc()):
                    $pass_rate = $row['students'] > 0 ? round(($row['passed'] / $row['students']) * 100, 1) : 0;
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['class'] ?? '—') ?></strong></td>
                        <td><?= htmlspecialchars($row['exam_name']) ?></td>
                        <td><?= htmlspecialchars($row['subject_name']) ?></td>
                        <td><?= (int)$row['students'] ?></td>
                        <td><?= $row['avg_score'] ?>%</td>
                        <td><?= $row['top_score'] ?>%</td>
                        <td><?= (int)$row['passed'] ?></td>
                        <td><span class="badge <?= $pass_rate >= 50 ? 'badge-approved' : 'badge-warning' ?>"><?= $pass_rate ?>%</span></td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="8" class="text-center">No report data available for selected filters.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="margin-top:40px; margin-bottom:24px;">
    <h3>Subject Registration Summary</h3>
    <p class="muted-text">Shows how many students are registered to write each subject at this school.</p>
</div>

<div class="section">
    <div class="table-container">
        <table class="table-striped">
            <thead>
                <tr>
                    <th>Subject Code</th>
                    <th>Subject Name</th>
                    <th>Category</th>
                    <th>Registered Students</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($subject_registrations && $subject_registrations->num_rows): while ($row = $subject_registrations->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['subject_code']) ?></strong></td>
                        <td><?= htmlspecialchars($row['subject_name']) ?></td>
                        <td><?= htmlspecialchars(ucfirst($row['category'] ?? '—')) ?></td>
                        <td><span class="badge badge-active" style="font-size:0.9rem; padding:4px 8px;"><?= (int)$row['student_count'] ?></span></td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="4" class="text-center">No subject registrations found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
</div>
<?php include '../common/footer.php'; ?>
