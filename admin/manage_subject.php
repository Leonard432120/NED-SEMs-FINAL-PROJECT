<?php
session_start();
require_once '../config/db.php';
require_once '../common/pagination_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

/* ================= HELPER FUNCTIONS ================= */

function getSubjectUsage($conn, $subject_id) {
    $usages = [];
    
    // Check subject_assignments
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM subject_assignments WHERE subject_id = ?");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();
    if ($count > 0) {
        $usages[] = "Teacher Assignments ($count)";
    }
    
    // Check exam_subjects
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM exam_subjects WHERE subject_id = ?");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();
    if ($count > 0) {
        $usages[] = "Exam Associations ($count)";
    }
    
    // Check marks
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM marks WHERE subject_id = ?");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();
    if ($count > 0) {
        $usages[] = "Student Marks ($count)";
    }
    
    return $usages;
}

/* ================= FILTERS ================= */
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;

$message = '';
$message_type = '';

/* =========================================================
   SINGLE DELETE
 ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $subject_id = (int)$_POST['subject_id'];
    $action = $_POST['action'];

    if ($action === 'delete') {
        $usages = getSubjectUsage($conn, $subject_id);
        if (!empty($usages)){
            $_SESSION['message'] = "Cannot delete subject because it is referenced in: " . implode(', ', $usages) . ".";
            $_SESSION['message_type'] = "error";
        } else {
            $stmt = $conn->prepare("DELETE FROM subjects WHERE subject_id = ?");
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

            $usages = getSubjectUsage($conn, $sid);
            if (!empty($usages)) {
                $skippedCount++;
                continue;
            }

            $stmt = $conn->prepare("DELETE FROM subjects WHERE subject_id = ?");
            $stmt->bind_param("i", $sid);
            if ($stmt->execute()) {
                $deletedCount++;
            }
            $stmt->close();
        }

        if ($deletedCount > 0) {
            $_SESSION['message'] = "Deleted {$deletedCount} subject(s).";
            if ($skippedCount > 0) {
                $_SESSION['message'] .= " Skipped {$skippedCount} referenced subject(s).";
            }
            $_SESSION['message_type'] = "success";
        } else {
            if ($skippedCount > 0) {
                $_SESSION['message'] = "No subjects were deleted. Skipped {$skippedCount} subject(s) because they are referenced in other tables.";
            } else {
                $_SESSION['message'] = "No subjects were selected/deleted.";
            }
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
    unset($_SESSION['message'], $_SESSION['message_type']);
}

/* =========================================================
   COUNT & FETCH SUBJECTS
 ========================================================= */
$count_sql = "SELECT COUNT(*) as total FROM subjects WHERE 1=1";
$params = [];
$types = '';

if ($search) {
    $count_sql .= " AND (subject_name LIKE ? OR subject_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
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

$pagination = paginate($total, $page, $per_page);
$offset = ($page - 1) * $per_page;

$sql = "SELECT * FROM subjects WHERE 1=1";
$params = [];
$types = '';

if ($search) {
    $sql .= " AND (subject_name LIKE ? OR subject_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
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
if ($params) $stmt->bind_param($types, ...$params);
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
    <?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>
    
    <style>
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 10px; max-width: 420px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
    </style>
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
                <p class="page-subtitle">Review and manage all available master subjects.</p>
            </div>
            <div class="header-actions">
                <a href="add_subject.php" class="btn btn-dark btn-small">+ Add Subject</a>
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
            <input type="text" name="search" placeholder="Search name or code..." value="<?= htmlspecialchars($search) ?>">
            <select name="status">
                <option value="">All Status</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <button type="submit">Filter</button>
        </form>

        <!-- STATS -->
        <div class="table-meta">
            Showing <?= $total > 0 ? (($page - 1) * $per_page) + 1 : 0 ?> – <?= min($page * $per_page, $total) ?> of <?= $total ?> subjects
        </div>

        <!-- BULK + TABLE -->
        <form method="POST">
            <div class="bulk-action-bar">
                <div class="bulk-left">
                    <span class="bulk-label">Bulk Actions</span>
                </div>
                <div class="bulk-right-actions">
                    <select name="bulk_action" class="bulk-select" required>
                        <option value="">Select action</option>
                        <option value="delete">Delete Selected</option>
                    </select>
                    <button type="submit" class="bulk-btn">Apply</button>
                </div>
            </div>

            <div class="card">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" onclick="toggleAll(this)"></th>
                                <th>ID</th>
                                <th>Subject Name</th>
                                <th>Code</th>
                                <th>Category</th>
                                <th>Paper Type</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subjects)): ?>
                                <tr>
                                    <td colspan="8" class="empty-state">No subjects found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($subjects as $subject): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_subjects[]" value="<?= $subject['subject_id'] ?>"></td>
                                    <td><?= $subject['subject_id'] ?></td>
                                    <td><?= htmlspecialchars($subject['subject_name']) ?></td>
                                    <td><?= htmlspecialchars($subject['subject_code']) ?></td>
                                    <td><?= ucfirst(htmlspecialchars($subject['category'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars($subject['paper_type'] ?? '') ?></td>
                                    <td>
                                        <span class="badge badge-<?= htmlspecialchars($subject['status']) ?>">
                                            <?= ucfirst(htmlspecialchars($subject['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <a href="edit_subject.php?edit_id=<?= $subject['subject_id'] ?>" class="btn btn-edit btn-small">Edit</a>
                                        <button type="button" class="btn btn-delete btn-small" 
                                                onclick="openModal(<?= $subject['subject_id'] ?>, 'delete')">
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

<!-- Hidden form submitted when global delete modal is confirmed -->
<form method="POST" id="subjectDeleteForm" style="display:none;">
    <input type="hidden" name="subject_id" id="modalSubjectId">
    <input type="hidden" name="action" value="delete">
</form>

<script>
function openModal(id, action) {
    document.getElementById('modalSubjectId').value = id;

    var modal = document.getElementById('globalDeleteModal');
    var confirmBtn = document.getElementById('globalDeleteConfirmBtn');
    if (!modal || !confirmBtn) return;

    confirmBtn.removeAttribute('href');
    confirmBtn.onclick = function(e) {
        e.preventDefault();
        document.getElementById('subjectDeleteForm').submit();
    };
    modal.classList.add('show');
}

function toggleAll(source) {
    document.querySelectorAll('input[name="selected_subjects[]"]').forEach(cb => {
        cb.checked = source.checked;
    });
}
</script>

<?php include '../common/footer.php'; ?>

</body>
</html>l>