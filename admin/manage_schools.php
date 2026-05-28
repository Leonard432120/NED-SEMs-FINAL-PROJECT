<?php
session_start();
require_once '../config/db.php';
require_once '../common/pagination_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

/* ================= FILTERS ================= */
$search = $_GET['search'] ?? '';
$district_filter = $_GET['district'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 5;

/* ================= SINGLE ACTION (ACTIVATE/DEACTIVATE) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $school_id = (int)$_POST['school_id'];
    $action = $_POST['action'];

    if ($action === 'activate') {
        $stmt = $conn->prepare("UPDATE schools SET status='active' WHERE school_id=?");
    } else {
        $stmt = $conn->prepare("UPDATE schools SET status='inactive' WHERE school_id=?");
    }

    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $stmt->close();

    header("Location: manage_schools.php?updated=1");
    exit();
}

/* ================= BULK ACTION ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {

    $bulk_action = $_POST['bulk_action'];
    $selected = $_POST['selected_schools'] ?? [];

    if (!empty($selected)) {

        foreach ($selected as $sid) {

            $sid = (int)$sid;

            if ($bulk_action === 'activate') {
                $stmt = $conn->prepare("UPDATE schools SET status='active' WHERE school_id=?");
            } else {
                $stmt = $conn->prepare("UPDATE schools SET status='inactive' WHERE school_id=?");
            }

            $stmt->bind_param("i", $sid);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: manage_schools.php?updated=1");
    exit();
}

/* ================= COUNT ================= */
$count_sql = "SELECT COUNT(*) as total FROM schools WHERE 1=1";
$params = [];
$types = '';

if ($search) {
    $count_sql .= " AND school_name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

if ($district_filter) {
    $count_sql .= " AND district = ?";
    $params[] = $district_filter;
    $types .= "s";
}

if ($status_filter) {
    $count_sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$stmt = $conn->prepare($count_sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

/* ================= PAGINATION ================= */
$pagination = paginate($total, $page, $per_page);
$offset = ($page - 1) * $per_page;

/* ================= DATA ================= */
$sql = "SELECT * FROM schools WHERE 1=1";

$params = [];
$types = '';

if ($search) {
    $sql .= " AND school_name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

if ($district_filter) {
    $sql .= " AND district = ?";
    $params[] = $district_filter;
    $types .= "s";
}

if ($status_filter) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql .= " ORDER BY school_id DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$schools = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ================= DISTRICTS ================= */
$districts = [];
$res = $conn->query("SELECT DISTINCT district FROM schools ORDER BY district");
while ($row = $res->fetch_assoc()) {
    $districts[] = $row['district'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Schools</title>

<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/admin.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/form.css">
</head>

<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
<?php include '../common/sidebar.php'; ?>

<div class="content">

<!-- HEADER -->
<div class="page-header">
    <h2 class="page-title">Manage Schools</h2>

    <div class="header-actions">
        <a href="add_school.php" class="btn btn-dark btn-small">+ Add School</a>
    </div>
</div>

<!-- FILTER -->
<form method="GET" class="search-form">

    <input type="text" name="search" placeholder="Search school..."
        value="<?= htmlspecialchars($search) ?>">

    <select name="district">
        <option value="">All Districts</option>
        <?php foreach ($districts as $d): ?>
            <option value="<?= htmlspecialchars($d) ?>"
                <?= $district_filter === $d ? 'selected' : '' ?>>
                <?= htmlspecialchars($d) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="status">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
    </select>

    <button type="submit">Filter</button>
</form>

<!-- STATS -->
<div style="margin:10px 0;font-weight:600;">
    Showing <?= (($page - 1) * $per_page) + 1 ?> –
    <?= min($page * $per_page, $total) ?> of <?= $total ?> schools
</div>

<!-- ================= SINGLE FORM FOR BULK + TABLE ================= -->
<form method="POST">

<!-- BULK ACTION BAR -->
<div class="bulk-action-bar">

    <div class="bulk-left">
        <span class="bulk-label">Bulk Actions</span>
    </div>

    <div class="bulk-right-actions">

        <select name="bulk_action" class="bulk-select" required>
            <option value="">Select action</option>
            <option value="activate">Activate Selected</option>
            <option value="deactivate">Deactivate Selected</option>
        </select>

        <button type="submit" class="bulk-btn">Apply</button>

    </div>

</div>

<!-- TABLE -->
<div class="card">
<div class="table-container">

<table>

<thead>
<tr>
    <th><input type="checkbox" onclick="toggleAll(this)"></th>
    <th>ID</th>
    <th>School Name</th>
    <th>District</th>
    <th>Address</th>
    <th>Status</th>
    <th>Actions</th>
</tr>
</thead>

<tbody>

<?php foreach ($schools as $s): ?>
<tr>

<td>
    <input type="checkbox" name="selected_schools[]" value="<?= $s['school_id'] ?>">
</td>

<td><?= $s['school_id'] ?></td>
<td><?= htmlspecialchars($s['school_name']) ?></td>
<td><?= htmlspecialchars($s['district']) ?></td>
<td><?= htmlspecialchars($s['address']) ?></td>

<td>
<span class="badge badge-<?= $s['status'] ?>">
<?= $s['status'] ?>
</span>
</td>

<td class="actions">

<a href="edit_school.php?id=<?= $s['school_id'] ?>" class="btn btn-edit btn-small">
Edit
</a>

<?php if ($s['status'] === 'active'): ?>
<button type="button" class="btn btn-deactivate btn-small"
onclick="openModal(<?= $s['school_id'] ?>,'deactivate')">
Deactivate
</button>
<?php else: ?>
<button type="button" class="btn btn-activate btn-small"
onclick="openModal(<?= $s['school_id'] ?>,'activate')">
Activate
</button>
<?php endif; ?>

</td>

</tr>
<?php endforeach; ?>

</tbody>

</table>

</div>
</div>

</form>

<!-- PAGINATION -->
<?php echo render_pagination($pagination, 'manage_schools.php'); ?>

</div>
</div>

<!-- ================= MODAL ================= -->
<div id="confirmModal" class="modal">
<div class="modal-content">

<h3 id="modalTitle"></h3>
<p id="modalText"></p>

<form method="POST">
<input type="hidden" name="school_id" id="modalUserId">
<input type="hidden" name="action" id="modalAction">

<div class="modal-actions">
<button type="button" onclick="closeModal()">Cancel</button>
<button type="submit" class="btn btn-confirm">Confirm</button>
</div>

</form>

</div>
</div>

<script>
function openModal(id, action){
    const modal = document.getElementById('confirmModal');
    modal.classList.add('show');
    document.getElementById('modalUserId').value=id;
    document.getElementById('modalAction').value=action;

document.getElementById('modalTitle').innerText =
action==='activate' ? 'Activate School' : 'Deactivate School';

document.getElementById('modalText').innerText =
action==='activate'
? 'School will be activated.'
: 'School will be deactivated.';
}

function closeModal(){
    document.getElementById('confirmModal').classList.remove('show');
}

function toggleAll(source){
document.querySelectorAll('input[name="selected_schools[]"]').forEach(cb=>{
cb.checked = source.checked;
});
}
</script>

<?php include '../common/footer.php'; ?>

<style>
/* KEEP EXACT SAME BULK CSS FROM USERS */
.bulk-action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;

    background: #ffffff;
    border: 1px solid #e2e8f0;
    padding: 12px 14px;
    border-radius: 10px;

    margin-bottom: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.bulk-left .bulk-label {
    font-weight: 600;
    font-size: 13px;
    color: #334155;
}

.bulk-right-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-left: auto;
}

.bulk-select {
    padding: 9px 12px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    font-size: 13px;
    outline: none;
    min-width: 220px;
    background: #fff;
}

.bulk-select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
}

.bulk-btn {
    padding: 7px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    background: #111827;
    color: #fff;
    border: none;
    cursor: pointer;
    min-width: 90px;
}

.bulk-btn:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

@media (max-width: 768px) {
    .bulk-action-bar {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .bulk-right-actions {
        width: 100%;
        flex-direction: column;
    }

    .bulk-select,
    .bulk-btn {
        width: 100%;
    }
}
</style>

</body>
</html>