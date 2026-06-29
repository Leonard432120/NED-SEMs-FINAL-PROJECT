<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'headteacher') {
    header("Location: ../login.php");
    exit();
}

$school_id = (int)$_SESSION['school_id'];
$search = trim($_GET['search'] ?? '');
$class_filter = trim($_GET['class'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$conn = get_db_connection();
$sql = "SELECT student_id, name, exam_number, class, status FROM students WHERE school_id = $school_id";
if ($search !== '') {
    $safe = $conn->real_escape_string($search);
    $sql .= " AND (name LIKE '%$safe%' OR exam_number LIKE '%$safe%')";
}
if ($class_filter !== '') {
    $safe = $conn->real_escape_string($class_filter);
    $sql .= " AND class = '$safe'";
}
if ($status_filter !== '') {
    $safe = $conn->real_escape_string($status_filter);
    $sql .= " AND status = '$safe'";
}
$sql .= " ORDER BY name ASC";
$result = $conn->query($sql);
$students = [];
while ($row = $result->fetch_assoc()) { $students[] = $row; }
$total = count($students);
$active = count(array_filter($students, fn($s) => $s['status'] === 'active'));
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Students</title>
<?php $module_css = 'headteacher'; include __DIR__ . '/../common/head_assets.php'; ?>
</head>
<body>
<?php include '../common/header.php'; ?>
<div class="dashboard">
<?php include '../common/sidebar.php'; ?>
<div class="content">

<div class="page-header">
    <div>
        <h1 class="page-title">Student Management</h1>
        <p class="stats-info"><?= $total ?> students · <?= $active ?> active</p>
    </div>
    <a href="add_student.php" class="btn btn-dark">+ Add Student</a>
</div>

<form method="GET" class="search-form">
    <input type="text" name="search" placeholder="Search name or exam number..." value="<?= htmlspecialchars($search) ?>">
    <select name="class">
        <option value="">All Classes</option>
        <option value="Form 1" <?= $class_filter === 'Form 1' ? 'selected' : '' ?>>Form 1</option>
        <option value="Form 2" <?= $class_filter === 'Form 2' ? 'selected' : '' ?>>Form 2</option>
    </select>
    <select name="status">
        <option value="">All Status</option>
        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
    </select>
    <button type="submit">Filter</button>
</form>

<div class="section">
    <div class="table-container">
        <table class="table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Exam Number</th>
                    <th>Class</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($students): foreach ($students as $student): ?>
                    <tr>
                        <td>#<?= (int)$student['student_id'] ?></td>
                        <td><strong><?= htmlspecialchars($student['name']) ?></strong></td>
                        <td><?= htmlspecialchars($student['exam_number']) ?></td>
                        <td><?= htmlspecialchars($student['class'] ?? '—') ?></td>
                        <td><span class="badge badge-<?= $student['status'] === 'active' ? 'active' : 'inactive' ?>"><?= ucfirst($student['status']) ?></span></td>
                        <td class="actions">
                            <a href="released_results.php" class="btn btn-small btn-primary">Results</a>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6" class="text-center">No students found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
</div>
<?php include '../common/footer.php'; ?>
