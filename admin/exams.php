<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$conn = get_db_connection();

function safe($value) {
    return htmlspecialchars($value ?? '');
}

/* ================= QUERY ================= */
$query = "
SELECT e.*, s.subject_name
FROM exams e
JOIN subjects s ON e.subject_id = s.subject_id
WHERE 1=1
";

$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND e.exam_name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

if (!empty($status_filter)) {
    $query .= " AND e.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$query .= " ORDER BY e.exam_id DESC";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$exams = [];
while ($row = $result->fetch_assoc()) {
    $exams[] = $row;
}

$stmt->close();

$statuses = [
    'draft','assigned','submitted',
    'under_moderation','needs_revision',
    'approved','rejected'
];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Exams</title>

<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/admin.css">

<style>
/* ================= HEADER ================= */
.page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}

.page-title{
    font-size:24px;
    font-weight:700;
    color:#0f172a;
}

/* ================= SEARCH ================= */
.search-form{
    display:flex;
    gap:12px;
    margin-bottom:18px;
    flex-wrap:wrap;
}

.search-form input,
.search-form select{
    height:44px;
    padding:0 14px;
    border:1px solid #cbd5e1;
    border-radius:10px;
    outline:none;
    background:#fff;
}

.search-form input:focus,
.search-form select:focus{
    border-color:#2563eb;
    box-shadow:0 0 0 3px rgba(37,99,235,0.12);
}

.search-form button{
    height:44px;
    padding:0 18px;
    border:none;
    border-radius:10px;
    background:#111827;
    color:#fff;
    cursor:pointer;
    font-weight:600;
}

.search-form button:hover{
    background:#2563eb;
}

/* ================= CARD ================= */
.card{
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:14px;
    box-shadow:0 2px 8px rgba(0,0,0,0.04);
}

/* ================= TABLE ================= */
.table-container table{
    width:100%;
    border-collapse:collapse;
}

.table-container th{
    background:#f8fafc;
    padding:14px;
    text-align:left;
    font-size:13px;
    font-weight:700;
    border-bottom:1px solid #e2e8f0;
}

.table-container td{
    padding:14px;
    border-bottom:1px solid #f1f5f9;
}

/* ================= ACTIONS ================= */
.actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}

.btn-small{
    padding:8px 12px;
    font-size:12px;
    border-radius:8px;
    font-weight:700;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    justify-content:center;
}

/* reusable admin colors assumed */
.btn-dark{ background:#111827; color:#fff; }
.btn-teal{ background:#0f766e; color:#fff; }
.btn-delete{ background:#dc2626; color:#fff; }

.btn-dark:hover{ background:#2563eb; }
.btn-teal:hover{ background:#115e59; }
.btn-delete:hover{ background:#991b1b; }

/* ================= BADGES ================= */
.badge{
    padding:6px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
    text-transform:capitalize;
}

.badge-draft{ background:#e5e7eb; color:#374151; }
.badge-assigned{ background:#dbeafe; color:#1e3a8a; }
.badge-submitted{ background:#fef9c3; color:#854d0e; }
.badge-under_moderation{ background:#fde68a; color:#78350f; }
.badge-needs_revision{ background:#ffe4e6; color:#9f1239; }
.badge-approved{ background:#dcfce7; color:#166534; }
.badge-rejected{ background:#fee2e2; color:#991b1b; }

/* ================= MODAL (REUSABLE SYSTEM) ================= */
.modal{
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.5);
    justify-content:center;
    align-items:center;
    z-index:9999;
}

.modal.show{
    display:flex;
}

.modal-content{
    background:#fff;
    width:360px;
    padding:20px;
    border-radius:12px;
}

.modal-actions{
    display:flex;
    justify-content:flex-end;
    gap:10px;
    margin-top:15px;
}
</style>
</head>

<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
<?php include '../common/sidebar.php'; ?>

<div class="main-content">

<!-- HEADER -->
<div class="page-header">
    <h2 class="page-title">Manage Exams</h2>

    <a href="create_exam.php" class="btn btn-dark btn-small">
        + Create Exam
    </a>
</div>

<!-- SEARCH -->
<form method="GET" class="search-form">

    <input type="text" name="search" placeholder="Search exam..."
           value="<?= safe($search) ?>">

    <select name="status">
        <option value="">All Status</option>
        <?php foreach ($statuses as $s): ?>
            <option value="<?= $s ?>" <?= $status_filter == $s ? 'selected' : '' ?>>
                <?= ucfirst($s) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Filter</button>
</form>

<!-- TABLE -->
<div class="card">
<div class="table-container">

<table>
<thead>
<tr>
    <th>ID</th>
    <th>Title</th>
    <th>Subject</th>
    <th>Date</th>
    <th>Duration</th>
    <th>Marks</th>
    <th>Status</th>
    <th>Actions</th>
</tr>
</thead>

<tbody>

<?php if (!empty($exams)): ?>
    <?php foreach ($exams as $e): ?>
<tr>

    <td><?= $e['exam_id'] ?></td>
    <td><?= safe($e['exam_name']) ?></td>
    <td><?= safe($e['subject_name']) ?></td>
    <td><?= $e['exam_date'] ?: '-' ?></td>
    <td><?= $e['duration_minutes'] ? $e['duration_minutes'].' min' : '-' ?></td>
    <td><?= $e['total_marks'] ?: '-' ?></td>

    <td>
        <span class="badge badge-<?= $e['status'] ?>">
            <?= safe($e['status']) ?>
        </span>
    </td>

    <td class="actions">

        <a href="assign.php?exam_id=<?= $e['exam_id'] ?>"
           class="btn btn-teal btn-small">
            Assign
        </a>

        <!-- VIEW (ADMIN SAFE) -->
        <a href="../teacher/exam.php?id=<?= urlencode($e['exam_id']) ?>"
           class="btn btn-dark btn-small">
            View
        </a>

        <!-- DELETE MODAL TRIGGER -->
        <button type="button"
                class="btn btn-delete btn-small"
                onclick="openDeleteModal(<?= $e['exam_id'] ?>)">
            Delete
        </button>

    </td>

</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
    <td colspan="8" style="text-align:center;padding:20px;">
        No exams found
    </td>
</tr>
<?php endif; ?>

</tbody>
</table>

</div>
</div>

</div>
</div>

<!-- ================= REUSABLE DELETE MODAL ================= -->
<div id="deleteModal" class="modal">
    <div class="modal-content">

        <h3>Delete Exam</h3>
        <p>Are you sure you want to delete this exam?</p>

        <form method="POST" action="delete_exam.php">
            <input type="hidden" name="exam_id" id="delete_exam_id">

            <div class="modal-actions">
                <button type="button" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="btn btn-delete">Delete</button>
            </div>
        </form>

    </div>
</div>

<script>
function openDeleteModal(id){
    document.getElementById('delete_exam_id').value = id;
    document.getElementById('deleteModal').classList.add('show');
}

function closeDeleteModal(){
    document.getElementById('deleteModal').classList.remove('show');
}
</script>

<?php include '../common/footer.php'; ?>

</body>
</html>