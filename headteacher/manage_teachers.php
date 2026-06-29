<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'headteacher') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();
$school_id = (int)$_SESSION['school_id'];

// Get all teachers at the school
$teachers_query = $conn->query("
    SELECT u.user_id, u.name, u.email, u.phone, u.status, u.login_count, u.last_login,
           u.major_subject, u.minor_subject, u.teacher_category
    FROM users u
    WHERE u.school_id = $school_id
    AND u.role = 'teacher'
    ORDER BY u.name ASC
");

// Get all subject assignments (item writer/moderator)
$teacher_subjects = [];
$result = $conn->query("
    SELECT sa.teacher_id AS user_id, GROUP_CONCAT(DISTINCT CONCAT(s.subject_name, ' (', REPLACE(sa.role, '_', ' '), ')') SEPARATOR ', ') as subjects
    FROM subject_assignments sa
    JOIN subjects s ON sa.subject_id = s.subject_id
    GROUP BY sa.teacher_id
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $teacher_subjects[$row['user_id']] = $row['subjects'];
    }
}

// Merge in marking assignments
$result_marking = $conn->query("
    SELECT ma.teacher_id AS user_id, GROUP_CONCAT(DISTINCT CONCAT(s.subject_name, ' (Marker)') SEPARATOR ', ') as subjects
    FROM marking_assignments ma
    JOIN subjects s ON ma.subject_id = s.subject_id
    WHERE ma.school_id = $school_id
    GROUP BY ma.teacher_id
");
if ($result_marking) {
    while ($row = $result_marking->fetch_assoc()) {
        $existing = isset($teacher_subjects[$row['user_id']]) ? $teacher_subjects[$row['user_id']] : '';
        if ($existing !== '') {
            $teacher_subjects[$row['user_id']] = $existing . ', ' . $row['subjects'];
        } else {
            $teacher_subjects[$row['user_id']] = $row['subjects'];
        }
    }
}

// Get exams assigned to each teacher (item writer, moderator, or marker)
$teacher_exams = [];
$result_exams = $conn->query("
    SELECT teacher_id, COUNT(DISTINCT exam_id) AS exam_count
    FROM (
        SELECT sa.teacher_id, es.exam_id
        FROM subject_assignments sa
        JOIN exam_subjects es ON sa.subject_id = es.subject_id
        UNION
        SELECT ma.teacher_id, ma.exam_id
        FROM marking_assignments ma
        WHERE ma.school_id = $school_id
    ) AS combined
    GROUP BY teacher_id
");
if ($result_exams) {
    while ($row = $result_exams->fetch_assoc()) {
        $teacher_exams[$row['teacher_id']] = $row['exam_count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Teachers</title>

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
    <h1>Manage Teachers</h1>
    <p>View and manage all teachers at your school</p>
</div>

<div class="section">

<h3>Teacher Directory</h3>

<?php if ($teachers_query && $teachers_query->num_rows > 0): ?>

<table>

<thead>
<tr>
    <th>Name</th>
    <th>Email Address</th>
    <th>Account Status</th>
    <th>Actions</th>
</tr>
</thead>

<tbody>

<?php while ($teacher = $teachers_query->fetch_assoc()): ?>

<tr>
    <td>
        <strong><?= htmlspecialchars($teacher['name']) ?></strong>
        <?php if (!empty($teacher['major_subject']) || !empty($teacher['minor_subject'])): ?>
            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">
                Major: <?= htmlspecialchars($teacher['major_subject'] ?: '—') ?> | Minor: <?= htmlspecialchars($teacher['minor_subject'] ?: '—') ?>
            </div>
        <?php endif; ?>
    </td>
    <td><?= htmlspecialchars($teacher['email']) ?></td>
    <td>
        <span class="badge badge-<?= strtolower($teacher['status']) ?>">
            <?= ucfirst($teacher['status']) ?>
        </span>
    </td>
    <td>
        <div class="actions">
            <a href="view_teacher.php?id=<?= $teacher['user_id'] ?>" class="btn btn-sm-primary">View</a>
            <a href="teacher_performance.php?id=<?= $teacher['user_id'] ?>" class="btn btn-sm-secondary">Performance</a>
        </div>
    </td>
</tr>

<?php endwhile; ?>

</tbody>

</table>

<?php else: ?>

<div class="no-data">
    <p>No teachers found at your school.</p>
</div>

<?php endif; ?>

</div>

</div>

</div>

<?php
$conn->close();
?>
<?php include '../common/footer.php'; ?>
