<?php
session_start();
require_once '../config/db.php';
require_once '../common/pagination_helper.php';
require_once '../common/email_service.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

/* ================= FILTERS ================= */
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 5;

/* ================= COUNT ================= */
$count_sql = "SELECT COUNT(*) as total FROM users WHERE 1";
$params = [];
$types = '';

if ($search) {
    $count_sql .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

if ($role_filter) {
    $count_sql .= " AND role = ?";
    $params[] = $role_filter;
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

/* ================= USERS ================= */
/*
   users.school_id -> schools.school_id, schools.school_name.
   LEFT JOIN so users with school_id = NULL (most rows right now)
   still show up in the list with school_name = NULL -> "N/A" in the view.
*/
$sql = "
SELECT u.*,
s.school_name AS school_name,
(SELECT COUNT(*) FROM audit_logs a WHERE a.user_id = u.user_id) AS activity_score
FROM users u
LEFT JOIN schools s ON s.school_id = u.school_id
WHERE 1
";

$params = [];
$types = '';

if ($search) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

if ($role_filter) {
    $sql .= " AND u.role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

if ($status_filter) {
    $sql .= " AND u.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql .= " ORDER BY u.user_id DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ================= SINGLE ACTION ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $user_id = (int)$_POST['user_id'];
    $action = $_POST['action'];
    $admin_id = $_SESSION['user_id'];

    // GET USER FOR EMAIL
    $stmt = $conn->prepare("SELECT name, email FROM users WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($action === 'activate') {

        $stmt = $conn->prepare("UPDATE users SET status='active' WHERE user_id=?");

        $subject = "Account Activated";
        $message = "Hello {$user['name']},\n\nYour account has been ACTIVATED.\nYou can now log in.\n\nRegards,\nAdmin Team";
        send_email($user['email'], $subject, $message);

    } else {

        $stmt = $conn->prepare("UPDATE users SET status='inactive' WHERE user_id=?");

        $subject = "Account Deactivated";
        $message = "Hello {$user['name']},\n\nYour account has been DEACTIVATED.\nPlease contact admin.\n\nRegards,\nAdmin Team";
        send_email($user['email'], $subject, $message);
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    $log = $conn->prepare("
        INSERT INTO audit_logs (user_id, action, details)
        VALUES (?, ?, ?)
    ");

    $details = "Admin {$admin_id} performed {$action} on user {$user_id}";
    $log->bind_param("iss", $admin_id, $action, $details);
    $log->execute();
    $log->close();

    header("Location: manage_users.php?updated=1");
    exit();
}

/* ================= BULK ACTION ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {

    $bulk_action = $_POST['bulk_action'];
    $selected = $_POST['selected_users'] ?? [];
    $admin_id = $_SESSION['user_id'];

    if (!empty($selected)) {
        foreach ($selected as $uid) {

            $uid = (int)$uid;

            // GET USER FOR EMAIL
            $stmt = $conn->prepare("SELECT name, email FROM users WHERE user_id=?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($bulk_action === 'activate') {

                $stmt = $conn->prepare("UPDATE users SET status='active' WHERE user_id=?");

                send_email(
                    $user['email'],
                    "Account Activated",
                    "Hello {$user['name']},\n\nYour account has been ACTIVATED."
                );

            } else {

                $stmt = $conn->prepare("UPDATE users SET status='inactive' WHERE user_id=?");

                send_email(
                    $user['email'],
                    "Account Deactivated",
                    "Hello {$user['name']},\n\nYour account has been DEACTIVATED."
                );
            }

            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $stmt->close();

            $log = $conn->prepare("
                INSERT INTO audit_logs (user_id, action, details)
                VALUES (?, ?, ?)
            ");

            $details = "Bulk {$bulk_action} on user {$uid}";
            $log->bind_param("iss", $admin_id, $bulk_action, $details);
            $log->execute();
            $log->close();
        }
    }

    header("Location: manage_users.php?updated=1");
    exit();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Users</title>

<?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>
</head>

<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
<?php include '../common/sidebar.php'; ?>

<div class="content">

<!-- HEADER -->
<div class="page-header">
    <h2 class="page-title">Manage Users</h2>
    <div class="header-actions">
        <a href="add_user.php" class="btn btn-dark btn-small">+ Add User</a>
    </div>
</div>

<!-- FILTER -->
<form method="GET" class="search-form">

    <input type="text" name="search" placeholder="Search user..."
        value="<?= htmlspecialchars($search) ?>">

    <select name="role">
        <option value="">All Roles</option>
        <option value="admin">Admin</option>
        <option value="teacher">Teacher</option>
        <option value="headteacher">Headteacher</option>
        <option value="examination_officer">Exam Officer</option>
    </select>

    <select name="status">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
    </select>

    <button type="submit">Filter</button>
</form>

<!-- STATS -->
<div class="table-meta">
    Showing <?= (($page - 1) * $per_page) + 1 ?> –
    <?= min($page * $per_page, $total) ?> of <?= $total ?> users
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
    <th>Name</th>
    <th>School</th>
    <th>Role</th>
    <th>Status</th>
    <th>Activity</th>
    <th>Actions</th>
</tr>
</thead>

<tbody>

<?php foreach ($users as $u): ?>
<tr>

    <td>
        <input type="checkbox" name="selected_users[]" value="<?= $u['user_id'] ?>">
    </td>

    <td><?= htmlspecialchars($u['name']) ?></td>

    <td><?= htmlspecialchars($u['school_name'] ?? 'N/A') ?></td>

    <td><?= ucfirst($u['role']) ?></td>

    <td>
        <span class="badge badge-<?= $u['status'] ?>">
            <?= ucfirst($u['status']) ?>
        </span>
    </td>

    <td><?= $u['activity_score'] ?></td>

    <td class="actions">

        <!-- VIEW BUTTON -->
        <a href="view_user.php?id=<?= $u['user_id'] ?>" class="btn btn-view btn-small">
            View
        </a>

        <!-- EDIT -->
        <a href="edit_user.php?id=<?= $u['user_id'] ?>" class="btn btn-edit btn-small">
            Edit
        </a>

        <!-- ACTIVATE / DEACTIVATE -->
        <?php if ($u['status'] === 'active'): ?>
            <button type="button" class="btn btn-deactivate btn-small"
                onclick="openModal(<?= $u['user_id'] ?>,'deactivate')">
                Deactivate
            </button>
        <?php else: ?>
            <button type="button" class="btn btn-activate btn-small"
                onclick="openModal(<?= $u['user_id'] ?>,'activate')">
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
<?php echo render_pagination($pagination, 'manage_users.php'); ?>

</div>
</div>

<!-- MODAL -->
<div id="confirmModal" class="modal">
<div class="modal-content">

<h3 id="modalTitle"></h3>
<p id="modalText"></p>

<form method="POST">
<input type="hidden" name="user_id" id="modalUserId">
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
action==='activate' ? 'Activate User' : 'Deactivate User';

document.getElementById('modalText').innerText =
action==='activate'
? 'This user will regain access to the system and assigned resources. Confirm to activate this account.'
: 'This user will lose access until reactivated. Confirm to deactivate this account.';
}

function closeModal(){
    document.getElementById('confirmModal').classList.remove('show');
}

function toggleAll(source){
document.querySelectorAll('input[name="selected_users[]"]').forEach(cb=>{
cb.checked = source.checked;
});
}
</script>

<?php include '../common/footer.php'; ?>

</body>
</html>