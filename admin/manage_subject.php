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
$status_filter = $_GET['status'] ?? '';

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 5;

$message = '';
$message_type = '';

function subjectHasExams($conn, $subject_id)
{
    $check = $conn->prepare("SELECT COUNT(*) AS total FROM exams WHERE subject_id = ?");
    $check->bind_param("i", $subject_id);
    $check->execute();
    $count = $check->get_result()->fetch_assoc()['total'] ?? 0;
    $check->close();
    return $count > 0;
}

/* =========================================================
   SINGLE DELETE
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $subject_id = (int)$_POST['subject_id'];
    $action = $_POST['action'];

    if ($action === 'delete') {

        if (subjectHasExams($conn, $subject_id)) {
            $_SESSION['message'] = "Cannot delete subject because it is used in one or more exams. Remove or reassign those exams first.";
            $_SESSION['message_type'] = "error";
        } else {
            $stmt = $conn->prepare(" 
                DELETE FROM subjects
                WHERE subject_id = ?
            ");

            $stmt->bind_param("i", $subject_id);

            if ($stmt->execute()) {
                $_SESSION['message'] = "Subject deleted successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Failed to delete subject.";
                $_SESSION['message_type'] = "error";
            }

            $stmt->close();
        }
    }

    header("Location: manage_subject.php");
    exit();
}

/* =========================================================
   BULK DELETE
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {

    $bulk_action = $_POST['bulk_action'];
    $selected = $_POST['selected_subjects'] ?? [];

    if ($bulk_action === 'delete' && !empty($selected)) {

        $deletedCount = 0;
        $skippedCount = 0;

        foreach ($selected as $sid) {
            $sid = (int)$sid;

            if (subjectHasExams($conn, $sid)) {
                $skippedCount++;
                continue;
            }

            $stmt = $conn->prepare(" 
                DELETE FROM subjects
                WHERE subject_id = ?
            ");

            $stmt->bind_param("i", $sid);
            if ($stmt->execute()) {
                $deletedCount++;
            }
            $stmt->close();
        }

        if ($deletedCount > 0 && $skippedCount === 0) {
            $_SESSION['message'] = "Selected subjects deleted successfully.";
            $_SESSION['message_type'] = "success";
        } elseif ($deletedCount > 0) {
            $_SESSION['message'] = "Deleted {$deletedCount} subjects. Skipped {$skippedCount} subjects because they are used in exams.";
            $_SESSION['message_type'] = "success";
        } elseif ($skippedCount > 0) {
            $_SESSION['message'] = "No subjects were deleted because selected subjects are used in exams.";
            $_SESSION['message_type'] = "error";
        }
    }

    header("Location: manage_subject.php");
    exit();
}

/* =========================================================
   SESSION ALERTS
========================================================= */
if (isset($_SESSION['message'])) {

    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];

    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

/* =========================================================
   COUNT
========================================================= */
$count_sql = "
    SELECT COUNT(*) as total
    FROM subjects
    WHERE 1=1
";

$params = [];
$types = '';

if ($search) {
    $count_sql .= " AND subject_name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

if ($status_filter) {
    $count_sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$stmt = $conn->prepare($count_sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$total = $stmt->get_result()->fetch_assoc()['total'];

$stmt->close();

/* =========================================================
   PAGINATION
========================================================= */
$pagination = paginate($total, $page, $per_page);
$offset = ($page - 1) * $per_page;

/* =========================================================
   SUBJECTS
========================================================= */
$sql = "
    SELECT *
    FROM subjects
    WHERE 1=1
";

$params = [];
$types = '';

if ($search) {
    $sql .= " AND subject_name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

if ($status_filter) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql .= " ORDER BY subject_id DESC LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;

$types .= "ii";

$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Subjects</title>

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

    <div>
        <h2 class="page-title">Manage Subjects</h2>
        <p class="page-subtitle">
            Review and manage all available subjects.
        </p>
    </div>

    <div class="header-actions">
        <a href="add_subject.php" class="btn btn-dark btn-small">
            + Add Subject
        </a>
    </div>

</div>

<!-- ALERT -->
<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- FILTER -->
<form method="GET" class="search-form">

    <input type="text"
           name="search"
           placeholder="Search subject..."
           value="<?= htmlspecialchars($search) ?>">

    <select name="status">

        <option value="">All Status</option>

        <option value="active"
            <?= $status_filter === 'active' ? 'selected' : '' ?>>
            Active
        </option>

        <option value="inactive"
            <?= $status_filter === 'inactive' ? 'selected' : '' ?>>
            Inactive
        </option>

    </select>

    <button type="submit">Filter</button>

</form>

<!-- STATS -->
<div class="table-meta">

    Showing <?= (($page - 1) * $per_page) + 1 ?> –
    <?= min($page * $per_page, $total) ?>
    of <?= $total ?> subjects

</div>

<!-- BULK + TABLE -->
<form method="POST">

<!-- BULK ACTION BAR -->
<div class="bulk-action-bar">

    <div class="bulk-left">
        <span class="bulk-label">Bulk Actions</span>
    </div>

    <div class="bulk-right-actions">

        <select name="bulk_action" class="bulk-select" required>
            <option value="">Select action</option>
            <option value="delete">Delete Selected</option>
        </select>

        <button type="submit" class="bulk-btn">
            Apply
        </button>

    </div>

</div>

<!-- TABLE -->
<div class="card">

<div class="table-container">

<table>

<thead>
<tr>

    <th>
        <input type="checkbox" onclick="toggleAll(this)">
    </th>

    <th>ID</th>
    <th>Subject Name</th>
    <th>Status</th>
    <th>Actions</th>

</tr>
</thead>

<tbody>

<?php if (empty($subjects)): ?>

<tr>
    <td colspan="5" class="empty-state">
        No subjects found.
    </td>
</tr>

<?php else: ?>

<?php foreach ($subjects as $subject): ?>

<tr>

<td>
    <input type="checkbox"
           name="selected_subjects[]"
           value="<?= $subject['subject_id'] ?>">
</td>

<td><?= $subject['subject_id'] ?></td>

<td>
    <?= htmlspecialchars($subject['subject_name']) ?>
</td>

<td>

<span class="badge badge-<?= $subject['status'] ?>">
    <?= ucfirst(htmlspecialchars($subject['status'])) ?>
</span>

</td>

<td class="actions">

<a href="edit_subject.php?edit_id=<?= $subject['subject_id'] ?>"
   class="btn btn-edit btn-small">
    Edit
</a>

<button type="button"
        class="btn btn-delete btn-small"
        onclick="openModal(<?= $subject['subject_id'] ?>,'delete')">
    Delete
</button>

</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</tbody>

</table>

</div>
</div>

</form>

<!-- PAGINATION -->
<?php echo render_pagination($pagination, 'manage_subject.php'); ?>

</div>
</div>

<!-- ================= MODAL ================= -->
<div id="confirmModal" class="modal">

<div class="modal-content">

<h3 id="modalTitle"></h3>

<p id="modalText"></p>

<form method="POST">

<input type="hidden" name="subject_id" id="modalSubjectId">

<input type="hidden" name="action" id="modalAction">

<div class="modal-actions">

<button type="button" onclick="closeModal()">
    Cancel
</button>

<button type="submit" class="btn btn-confirm">
    Confirm
</button>

</div>

</form>

</div>
</div>

<script>
function openModal(id, action){

    const modal = document.getElementById('confirmModal');

    modal.classList.add('show');

    document.getElementById('modalSubjectId').value = id;
    document.getElementById('modalAction').value = action;

    document.getElementById('modalTitle').innerText =
        'Delete Subject';

    document.getElementById('modalText').innerText =
        'This subject will be deleted permanently.';
}

function closeModal(){
    document.getElementById('confirmModal').classList.remove('show');
}

function toggleAll(source){

    document.querySelectorAll(
        'input[name="selected_subjects[]"]'
    ).forEach(cb => {

        cb.checked = source.checked;
    });
}
</script>

<?php include '../common/footer.php'; ?>

</body>
</html>