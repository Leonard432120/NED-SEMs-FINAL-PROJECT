<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

$search        = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

function safe($v) { return htmlspecialchars($v ?? ''); }


/* PAGINATION */

$per_page = 15;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

/* ═══ TOTAL COUNT ═══ */
$count_sql    = "SELECT COUNT(*) AS cnt FROM exams WHERE 1=1";
$count_params = [];
$count_types  = '';
if ($search !== '') {
    $count_sql   .= " AND (exam_name LIKE ? OR exam_code LIKE ?)";
    $count_params[] = "%$search%";
    $count_params[] = "%$search%";
    $count_types   .= "ss";
}
if ($status_filter !== '') {
    $count_sql   .= " AND status = ?";
    $count_params[] = $status_filter;
    $count_types   .= "s";
}
$cs = $conn->prepare($count_sql);
if (!empty($count_params)) $cs->bind_param($count_types, ...$count_params);
$cs->execute();
$total_rows  = (int)$cs->get_result()->fetch_assoc()['cnt'];
$cs->close();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

/* ═══ PAGINATED QUERY ═══ */
$query  = "SELECT * FROM exams WHERE 1=1";
$params = [];
$types  = '';

if ($search !== '') {
    $query   .= " AND (exam_name LIKE ? OR exam_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types   .= "ss";
}
if ($status_filter !== '') {
    $query   .= " AND status = ?";
    $params[] = $status_filter;
    $types   .= "s";
}
$query   .= " ORDER BY exam_id DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types   .= "ii";

$stmt = $conn->prepare($query);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

function page_url(int $p, string $search, string $status): string {
    return 'exams.php?' . http_build_query(array_filter([
        'page'   => $p,
        'search' => $search,
        'status' => $status,
    ], fn($v) => $v !== '' && $v !== 0));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exams | NED-SEMS</title>
    <?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>
    <style>
        .modal {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.5); z-index: 9999;
        }
        .modal.show { display: block; }
        .modal-content {
            background: #fff; max-width: 420px;
            margin: 8% auto; padding: 24px;
            border-radius: var(--border-radius);
        }
    </style>
</head>
<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
    <?php include '../common/sidebar.php'; ?>

    <div class="main-content">

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div>
                <h2 class="page-title">Manage Exams</h2>
                <p class="page-subtitle">All examinations managed by the division</p>
            </div>
            <div class="header-actions">
                <a href="add_exam.php" class="btn btn-dark">+ Create Exam</a>
            </div>
        </div>

        <!-- FLASH MESSAGES -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?= $_SESSION['message_type'] ?? 'success' ?>">
                <?= safe($_SESSION['message']) ?>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>

        <!-- FILTERS -->
        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="Search exam name or code…"
                   value="<?= safe($search) ?>">
            <select name="status">
                <option value="">All Statuses</option>
                <option value="draft"     <?= $status_filter === 'draft'     ? 'selected' : '' ?>>Draft</option>
                <option value="active"    <?= $status_filter === 'active'    ? 'selected' : '' ?>>Active</option>
                <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
            </select>
            <button type="submit" class="btn btn-dark">Filter</button>
            <a href="exams.php" class="btn btn-secondary">Reset</a>
        </form>

        <!-- TABLE -->
        <div class="card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Exam Name</th>
                            <th>Code</th>
                            <th>Year</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($exams)): ?>
                            <?php $i = 1; foreach ($exams as $e): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= safe($e['exam_name']) ?></strong></td>
                                <td><?= safe($e['exam_code'] ?? '—') ?></td>
                                <td><?= safe($e['year'] ?? '—') ?></td>
                                <td>
                                    <span class="badge badge-<?= safe($e['status']) ?>">
                                        <?= ucfirst(safe($e['status'])) ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a href="view_exam.php?id=<?= $e['exam_id'] ?>"
                                       class="btn btn-dark btn-small">View</a>
                                    <a href="manage_candidates.php?exam_id=<?= $e['exam_id'] ?>"
                                       class="btn btn-secondary btn-small">Candidates</a>
                                    <a href="manage_exam_subjects.php?exam_id=<?= $e['exam_id'] ?>"
                                       class="btn btn-secondary btn-small">Subjects</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    No exams found.
                                    <?php if ($search || $status_filter): ?>
                                        <a href="exams.php">Reset filters</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-top:20px;">
                <span style="font-size:.8rem; color:var(--text-muted);">
                    Page <?= $page ?> of <?= $total_pages ?> &mdash; <?= $total_rows ?> exam(s)
                </span>
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    <a href="<?= page_url(1, $search, $status_filter) ?>"
                       style="display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border-radius:var(--border-radius);border:1px solid var(--border-color);background:var(--card-color);color:var(--text-color);font-size:.8rem;font-weight:600;text-decoration:none;<?= $page===1 ? 'opacity:.4;pointer-events:none;' : '' ?>">First</a>
                    <a href="<?= page_url(max(1,$page-1), $search, $status_filter) ?>"
                       style="display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border-radius:var(--border-radius);border:1px solid var(--border-color);background:var(--card-color);color:var(--text-color);font-size:.8rem;font-weight:600;text-decoration:none;<?= $page===1 ? 'opacity:.4;pointer-events:none;' : '' ?>">Prev</a>
                    <?php for ($p = max(1,$page-2); $p <= min($total_pages,$page+2); $p++): ?>
                    <a href="<?= page_url($p, $search, $status_filter) ?>"
                       style="display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border-radius:var(--border-radius);border:1px solid var(--border-color);font-size:.8rem;font-weight:600;text-decoration:none;<?= $p===$page ? 'background:var(--primary-dark);color:#fff;border-color:var(--primary-dark);' : 'background:var(--card-color);color:var(--text-color);' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <a href="<?= page_url(min($total_pages,$page+1), $search, $status_filter) ?>"
                       style="display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border-radius:var(--border-radius);border:1px solid var(--border-color);background:var(--card-color);color:var(--text-color);font-size:.8rem;font-weight:600;text-decoration:none;<?= $page===$total_pages ? 'opacity:.4;pointer-events:none;' : '' ?>">Next</a>
                    <a href="<?= page_url($total_pages, $search, $status_filter) ?>"
                       style="display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border-radius:var(--border-radius);border:1px solid var(--border-color);background:var(--card-color);color:var(--text-color);font-size:.8rem;font-weight:600;text-decoration:none;<?= $page===$total_pages ? 'opacity:.4;pointer-events:none;' : '' ?>">Last</a>
                </div>
            </div>
            <?php endif; ?>

        </div>

    </div>
</div>

<!-- HIDDEN DELETE FORM -->
<form id="hiddenDeleteForm" method="POST" action="delete_exam.php" style="display:none;">
    <input type="hidden" name="exam_id" id="hidden_exam_id">
</form>

<!-- DELETE MODAL -->
<script>
let currentDeleteId = null;

function openDeleteModal(id) {
    currentDeleteId = id;

    var modal = document.getElementById('globalDeleteModal');
    var confirmBtn = document.getElementById('globalDeleteConfirmBtn');
    if (!modal || !confirmBtn) return;

    confirmBtn.removeAttribute('href');
    confirmBtn.onclick = function(e) {
        e.preventDefault();
        document.getElementById('hidden_exam_id').value = currentDeleteId;
        document.getElementById('hiddenDeleteForm').submit();
        modal.classList.remove('show');
    };
    modal.classList.add('show');
}
</script>

<?php include '../common/footer.php'; ?>

</body>
</html>