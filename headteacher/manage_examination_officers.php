<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'headteacher') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();
$school_id = (int)$_SESSION['school_id'];

// Get all examination officers at the school
$officers_query = $conn->query("
    SELECT u.user_id, u.name, u.email, u.phone, u.status, u.login_count, u.last_login
    FROM users u
    WHERE u.school_id = $school_id
    AND u.role = 'examination_officer'
    ORDER BY u.name ASC
");

// Get exams handled by each officer
$officer_exams = [];
$result = $conn->query("
    SELECT created_by, COUNT(exam_id) as exam_count
    FROM exams
    WHERE created_by IN (SELECT user_id FROM users WHERE school_id = $school_id AND role = 'examination_officer')
    GROUP BY created_by
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $officer_exams[$row['created_by']] = $row['exam_count'];
    }
}

// Get results processed by each officer
$officer_results = [];
$result = $conn->query("
    SELECT 
    u.user_id,
    COUNT(r.result_id) AS result_count
    FROM users u
    LEFT JOIN results r ON u.user_id = r.compiled_by
    WHERE u.school_id = 32
    AND u.role = 'examination_officer'
    GROUP BY u.user_id;
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $officer_results[$row['user_id']] = $row['result_count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Examination Officers</title>

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
    <h1>Manage Examination Officers</h1>
    <p>View and manage all examination officers at your school</p>
</div>

<div class="stat-cards">
    <div class="stat-card">
        <h4>Total Officers</h4>
        <p><?= $officers_query->num_rows ?></p>
    </div>
</div>

<div class="section">

<h3>Examination Officers Directory</h3>

<?php $officers_query->data_seek(0); if ($officers_query && $officers_query->num_rows > 0): ?>

<table>

<thead>
<tr>
    <th>Name</th>
    <th>Email</th>
    <th>Phone</th>
    <th>Exams Created</th>
    <th>Results Approved</th>
    <th>Status</th>
    <th>Last Login</th>
    <th>Actions</th>
</tr>
</thead>

<tbody>

<?php while ($officer = $officers_query->fetch_assoc()): ?>

<tr>
    <td><strong><?= htmlspecialchars($officer['name']) ?></strong></td>
    <td><?= htmlspecialchars($officer['email']) ?></td>
    <td><?= htmlspecialchars($officer['phone'] ?? 'N/A') ?></td>
    <td>
        <small><?= $officer_exams[$officer['user_id']] ?? 0 ?></small>
    </td>
    <td>
        <small><?= $officer_results[$officer['user_id']] ?? 0 ?></small>
    </td>
    <td>
        <span class="badge badge-<?= strtolower($officer['status']) ?>">
            <?= ucfirst($officer['status']) ?>
        </span>
    </td>
    <td>
        <small><?= $officer['last_login'] ? date('d M Y H:i', strtotime($officer['last_login'])) : 'Never' ?></small>
    </td>
    <td>
        <div class="actions">
            <a href="view_officer.php?id=<?= $officer['user_id'] ?>" class="btn btn-sm-primary">View</a>
            <a href="officer_analytics.php?id=<?= $officer['user_id'] ?>" class="btn btn-sm-secondary">Analytics</a>
        </div>
    </td>
</tr>

<?php endwhile; ?>

</tbody>

</table>

<?php else: ?>

<div class="no-data">
    <p>No examination officers found at your school.</p>
</div>

<?php endif; ?>

</div>

</div>

</div>

<?php
$conn->close();
?>
<?php include '../common/footer.php'; ?>
