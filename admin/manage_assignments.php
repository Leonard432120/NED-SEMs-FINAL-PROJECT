<?php
session_start();

require_once '../config/db.php';
require_once '../common/email_service.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();
$admin_id = $_SESSION['user_id'];

$message = '';
$message_type = '';

/* ================= REMOVE ASSIGNMENT ================= */
if (isset($_GET['delete'])) {

    $id = (int)$_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM exam_assignments WHERE assignment_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: manage_assignments.php?deleted=1");
    exit();
}

/* ================= RESEND EMAIL ================= */
if (isset($_GET['resend'])) {

    $id = (int)$_GET['resend'];

    $stmt = $conn->prepare("
        SELECT ea.*, u.name, u.email, e.exam_name
        FROM exam_assignments ea
        JOIN users u ON u.user_id = ea.teacher_id
        JOIN exams e ON e.exam_id = ea.exam_id
        WHERE ea.assignment_id = ?
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($data) {
        send_email(
            $data['email'],
            "Exam Assignment Reminder",
            "Hello {$data['name']},

This is a reminder of your assignment:

Exam: {$data['exam_name']}
Role: {$data['role']}

Please log in to your dashboard."
        );
    }

    $conn->query("UPDATE exam_assignments SET email_sent = 1 WHERE assignment_id = $id");

    header("Location: manage_assignments.php?resent=1");
    exit();
}

/* ================= REASSIGN TEACHER ================= */
if (isset($_POST['reassign'])) {

    $assignment_id = (int)$_POST['assignment_id'];
    $new_teacher = (int)$_POST['teacher_id'];

    $stmt = $conn->prepare("
        UPDATE exam_assignments
        SET teacher_id = ?
        WHERE assignment_id = ?
    ");

    $stmt->bind_param("ii", $new_teacher, $assignment_id);
    $stmt->execute();
    $stmt->close();

    $message = "Teacher reassigned successfully.";
    $message_type = "success";
}

/* ================= FILTERS ================= */
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';
$teacher_filter = $_GET['teacher'] ?? '';

$where = "WHERE 1=1";

if ($search) {
    $where .= " AND (e.exam_name LIKE '%$search%' OR u.name LIKE '%$search%')";
}

if ($role) {
    $where .= " AND ea.role = '$role'";
}

if ($teacher_filter) {
    $where .= " AND ea.teacher_id = " . (int)$teacher_filter;
}

/* ================= DATA ================= */
$assignments = $conn->query("
    SELECT 
        ea.assignment_id,
        ea.role,
        ea.assigned_at,
        ea.email_sent,
        u.name AS teacher_name,
        u.user_id,
        e.exam_name
    FROM exam_assignments ea
    JOIN users u ON u.user_id = ea.teacher_id
    JOIN exams e ON e.exam_id = ea.exam_id
    $where
    ORDER BY ea.assignment_id DESC
");

$teachers = $conn->query("SELECT user_id, name FROM users WHERE role='teacher'");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Assignments</title>

<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/admin.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/form.css">

<style>
/* PAGE LAYOUT */
.page-wrapper {
    padding: 20px;
}

/* TABLE */
.card {
    background: #fff;
    padding: 15px;
    border-radius: 14px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
}

.table-container table {
    width: 100%;
    border-collapse: collapse;
}

.table-container th,
.table-container td {
    padding: 12px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}

/* BADGES */
.badge {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.badge.sent { background:#dcfce7; color:#166534; }
.badge.pending { background:#fee2e2; color:#991b1b; }

/* BUTTONS */
.btn {
    padding: 6px 10px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
}

.btn-dark { background:#334155; color:#fff; }
.btn-red { background:#ef4444; color:#fff; }
.btn-blue { background:#2563eb; color:#fff; }
.btn-gray { background:#e2e8f0; color:#0f172a; }

/* FILTER */
.search-form {
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
    margin:15px 0;
}

.search-form input,
.search-form select {
    padding:10px;
    border-radius:8px;
    border:1px solid #e2e8f0;
}

.search-right {
    margin-left:auto;
    display:flex;
    gap:10px;
}
</style>
</head>

<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
<?php include '../common/sidebar.php'; ?>

<div class="content page-wrapper">

<!-- HEADER -->
<div class="page-header">
    <h2 class="page-title">Manage Assignments</h2>
    <p class="page-subtitle">Reassign, remove or manage teacher assignments</p>
</div>

<!-- FILTER -->
<form method="GET" class="search-form">

    <input type="text" name="search" placeholder="Search exam or teacher..."
        value="<?= htmlspecialchars($search) ?>">

    <select name="role">
        <option value="">All Roles</option>
        <option value="item_writer">Item Writer</option>
        <option value="moderator">Moderator</option>
        <option value="marker">Marker</option>
    </select>

    <select name="teacher">
        <option value="">All Teachers</option>
        <?php while($t = $teachers->fetch_assoc()): ?>
            <option value="<?= $t['user_id'] ?>"><?= $t['name'] ?></option>
        <?php endwhile; ?>
    </select>

    <div class="search-right">
        <button class="btn btn-dark" type="submit">Filter</button>
        <a class="btn btn-gray" href="manage_assignments.php">Reset</a>
    </div>

</form>

<!-- TABLE -->
<div class="card">
<div class="table-container">

<table>
<thead>
<tr>
    <th>Exam</th>
    <th>Teacher</th>
    <th>Role</th>
    <th>Date</th>
    <th>Email</th>
    <th>Actions</th>
</tr>
</thead>

<tbody>

<?php while($a = $assignments->fetch_assoc()): ?>
<tr>

<td><?= $a['exam_name'] ?></td>
<td><?= $a['teacher_name'] ?></td>
<td><?= $a['role'] ?></td>
<td><?= $a['assigned_at'] ?></td>

<td>
<?php if ($a['email_sent']): ?>
    <span class="badge sent">Sent</span>
<?php else: ?>
    <span class="badge pending">Pending</span>
<?php endif; ?>
</td>

<td style="display:flex; gap:6px;">

<!-- RESEND -->
<a class="btn btn-dark" href="?resend=<?= $a['assignment_id'] ?>">Resend</a>

<!-- DELETE -->
<a class="btn btn-red" href="?delete=<?= $a['assignment_id'] ?>"
onclick="return confirm('Remove assignment?')">
Remove
</a>

<!-- REASSIGN -->
<form method="POST" style="display:flex; gap:5px;">
    <input type="hidden" name="assignment_id" value="<?= $a['assignment_id'] ?>">
    <select name="teacher_id" required>
        <?php
        $t2 = $conn->query("SELECT user_id, name FROM users WHERE role='teacher'");
        while($t = $t2->fetch_assoc()):
        ?>
            <option value="<?= $t['user_id'] ?>"><?= $t['name'] ?></option>
        <?php endwhile; ?>
    </select>

    <button class="btn btn-blue" name="reassign">Reassign</button>
</form>

</td>

</tr>
<?php endwhile; ?>

</tbody>
</table>

</div>
</div>

</div>
</div>

<?php include '../common/footer.php'; ?>

</body>
</html>