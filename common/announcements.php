<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$conn      = get_db_connection();
$user_id   = (int)$_SESSION['user_id'];
$role      = $_SESSION['role'];
$school_id = isset($_SESSION['school_id']) ? (int)$_SESSION['school_id'] : 0;

$message      = '';
$message_type = '';

// Can publish? (EDM/admin, headteacher, examination officer)
$can_publish = in_array($role, ['admin', 'headteacher', 'examination_officer']);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'publish' && $can_publish) {
        $title   = trim($_POST['title']   ?? '');
        $content = trim($_POST['content'] ?? '');

        if (empty($title) || empty($content)) {
            $message      = "All fields are required.";
            $message_type = "error";
        } else {
            // Admin posts globally (NULL school_id), others post to their school
            $target_school_id = ($role === 'admin') ? null : $school_id;

            $stmt = $conn->prepare("
                INSERT INTO announcements (title, content, published_by, school_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("ssii", $title, $content, $user_id, $target_school_id);
            if ($stmt->execute()) {
                $message      = "Announcement published successfully.";
                $message_type = "success";
            } else {
                $message      = "Failed to publish announcement: " . $conn->error;
                $message_type = "error";
            }
            $stmt->close();
        }
    } elseif ($action === 'delete') {
        $announcement_id = (int)$_POST['announcement_id'];

        if ($role === 'admin') {
            // Admin can delete any announcement
            $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
            $stmt->bind_param("i", $announcement_id);
        } else {
            // Others can only delete their own
            $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ? AND published_by = ?");
            $stmt->bind_param("ii", $announcement_id, $user_id);
        }

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message      = "Announcement deleted successfully.";
            $message_type = "success";
        } else {
            $message      = "Failed to delete announcement or unauthorized.";
            $message_type = "error";
        }
        $stmt->close();
    }
}

/* ═══════════════════════════════════════
   KPI STATS
═══════════════════════════════════════ */
if ($role === 'admin') {
    $kpi_total  = $conn->query("SELECT COUNT(*) c FROM announcements")->fetch_assoc()['c'] ?? 0;
    $kpi_global = $conn->query("SELECT COUNT(*) c FROM announcements WHERE school_id IS NULL")->fetch_assoc()['c'] ?? 0;
    $kpi_school = $conn->query("SELECT COUNT(*) c FROM announcements WHERE school_id IS NOT NULL")->fetch_assoc()['c'] ?? 0;
    $kpi_mine   = $conn->query("SELECT COUNT(*) c FROM announcements WHERE published_by = $user_id")->fetch_assoc()['c'] ?? 0;
} else {
    $kpi_total  = $conn->query("SELECT COUNT(*) c FROM announcements WHERE school_id IS NULL OR school_id = $school_id")->fetch_assoc()['c'] ?? 0;
    $kpi_global = $conn->query("SELECT COUNT(*) c FROM announcements WHERE school_id IS NULL")->fetch_assoc()['c'] ?? 0;
    $kpi_school = $conn->query("SELECT COUNT(*) c FROM announcements WHERE school_id = $school_id")->fetch_assoc()['c'] ?? 0;
    $kpi_mine   = $conn->query("SELECT COUNT(*) c FROM announcements WHERE published_by = $user_id")->fetch_assoc()['c'] ?? 0;
}

/* ═══════════════════════════════════════
   FILTERS & PAGINATION (5 per page)
═══════════════════════════════════════ */
$filter_type = trim($_GET['type']   ?? '');
$search      = trim($_GET['search'] ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 5;

$where_clauses = [];
$bind_types    = "";
$bind_params   = [];

if ($role === 'admin') {
    $where_clauses[] = "1=1";
} else {
    $where_clauses[] = "(a.school_id IS NULL OR a.school_id = ?)";
    $bind_types   .= "i";
    $bind_params[] = $school_id;
}

if ($filter_type === 'global') {
    $where_clauses[] = "a.school_id IS NULL";
} elseif ($filter_type === 'school') {
    $where_clauses[] = "a.school_id IS NOT NULL";
} elseif ($filter_type === 'mine') {
    $where_clauses[] = "a.published_by = ?";
    $bind_types   .= "i";
    $bind_params[] = $user_id;
}

if ($search !== '') {
    $where_clauses[] = "(a.title LIKE ? OR a.content LIKE ?)";
    $bind_types   .= "ss";
    $bind_params[] = "%$search%";
    $bind_params[] = "%$search%";
}

$where_sql = implode(" AND ", $where_clauses);

// Count filtered
$count_sql  = "SELECT COUNT(*) c FROM announcements a WHERE $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($bind_params) {
    $count_stmt->bind_param($bind_types, ...$bind_params);
}
$count_stmt->execute();
$total_filtered = (int)$count_stmt->get_result()->fetch_assoc()['c'];
$count_stmt->close();

$total_pages = max(1, (int)ceil($total_filtered / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

// Fetch paged records
$bind_params_paged   = $bind_params;
$bind_types_paged    = $bind_types . "ii";
$bind_params_paged[] = $per_page;
$bind_params_paged[] = $offset;

$sql = "
    SELECT a.*, u.name AS author_name, u.role AS author_role, s.school_name
    FROM announcements a
    LEFT JOIN users u ON a.published_by = u.user_id
    LEFT JOIN schools s ON a.school_id = s.school_id
    WHERE $where_sql
    ORDER BY a.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param($bind_types_paged, ...$bind_params_paged);
$stmt->execute();
$announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

function pg_ann_url(int $p, array $extra = []): string {
    $extra['page'] = $p;
    return 'announcements.php?' . http_build_query(array_filter($extra, fn($v) => $v !== '' && $v !== 0));
}
$carry_params = ['type' => $filter_type, 'search' => $search];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Announcements &amp; Bulletins | NED-SEMS</title>
<?php include __DIR__ . '/head_assets.php'; ?>
<style>
/* ── KPI Strip ── */
.kpi-strip {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
@media (max-width: 900px) { .kpi-strip { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 500px) { .kpi-strip { grid-template-columns: 1fr; } }

.kpi-card {
    background: var(--card-color);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 18px 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    transition: transform .15s, box-shadow .15s;
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.06);
}

.kpi-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-muted);
    margin-bottom: 6px;
}
.kpi-value {
    font-size: 1.9rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1;
}
.kpi-sub {
    font-size: 0.72rem;
    color: var(--text-muted);
    margin-top: 4px;
}

/* ── Filter / Compose Bar ── */
.controls-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    flex: 1;
}
.filter-group input[type="text"] {
    padding: 9px 14px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 0.85rem;
    background: var(--card-color);
    min-width: 220px;
    flex: 1;
}
.filter-tabs {
    display: flex;
    gap: 4px;
    background: #e2e8f0;
    padding: 3px;
    border-radius: 8px;
}
.filter-tab {
    padding: 6px 14px;
    font-size: 0.78rem;
    font-weight: 600;
    color: #475569;
    text-decoration: none;
    border-radius: 6px;
    transition: background 0.15s, color 0.15s;
}
.filter-tab.active {
    background: #ffffff;
    color: #0f172a;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

/* ── Compose Box ── */
.compose-card {
    background: var(--card-color);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 22px 24px;
    margin-bottom: 24px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
    display: none;
}
.compose-card.show {
    display: block;
    animation: slideDown 0.2s ease-out;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.compose-card h3 {
    margin: 0 0 16px 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
}

/* ── Announcement Cards ── */
.announcement-card {
    background: var(--card-color);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 22px 24px;
    margin-bottom: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    position: relative;
    transition: transform 0.15s, box-shadow 0.15s;
}
.announcement-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
}

.ann-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 12px;
}
.author-info {
    display: flex;
    align-items: center;
    gap: 10px;
}
.author-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #3b82f6;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.95rem;
    text-transform: uppercase;
}
.author-avatar.school-avatar {
    background: #0d9488;
}
.author-meta {
    line-height: 1.25;
}
.author-name {
    font-size: 0.88rem;
    font-weight: 700;
    color: #0f172a;
}
.author-role {
    font-size: 0.74rem;
    color: var(--text-muted);
}

.ann-tags {
    display: flex;
    align-items: center;
    gap: 8px;
}
.ann-tag {
    padding: 3px 10px;
    border-radius: 99px;
    font-size: 0.72rem;
    font-weight: 700;
}
.tag-global { background: #dbeafe; color: #1d4ed8; }
.tag-school { background: #ccfbf1; color: #0f766e; }

.ann-date {
    font-size: 0.75rem;
    color: #64748b;
}

.ann-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 10px 0;
    line-height: 1.35;
}
.ann-body {
    font-size: 0.9rem;
    color: #334155;
    line-height: 1.65;
    white-space: pre-wrap;
    margin: 0;
}

.ann-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 16px;
    padding-top: 12px;
    border-top: 1px solid #f1f5f9;
}

/* ── Pagination ── */
.pagination-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 24px;
    padding: 14px 20px;
    background: var(--card-color);
    border: 1px solid var(--border-color);
    border-radius: 12px;
}
.pag-info { font-size: 0.78rem; color: var(--text-muted); }
.pag-links { display: flex; gap: 5px; flex-wrap: wrap; }
.pag-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    height: 34px;
    padding: 0 10px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: var(--card-color);
    color: var(--text-color);
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.15s, border-color 0.15s;
}
.pag-btn:hover { background: #f1f5f9; border-color: #94a3b8; }
.pag-btn.active { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }
.pag-btn.disabled { opacity: 0.4; pointer-events: none; }

.empty-feed {
    text-align: center;
    padding: 50px 20px;
    background: var(--card-color);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    color: var(--text-muted);
}
.empty-feed h4 { font-size: 1rem; color: #0f172a; margin-bottom: 4px; }
</style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="dashboard">
    <?php include 'sidebar.php'; ?>

    <div class="content">

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div>
                <h2 class="page-title">Announcements &amp; Bulletins</h2>
                <p class="page-subtitle">Official division broadcasts and school notices</p>
            </div>
            <?php if ($can_publish): ?>
                <div class="header-actions">
                    <button type="button" class="btn btn-dark" id="toggleComposeBtn">
                        + New Announcement
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- ALERTS -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- KPI STRIP -->
        <div class="kpi-strip">
            <div class="kpi-card blue">
                <div class="kpi-label">Total Bulletins</div>
                <div class="kpi-value"><?= number_format($kpi_total) ?></div>
                <div class="kpi-sub">Available to view</div>
            </div>
            <div class="kpi-card purple">
                <div class="kpi-label">Global Division</div>
                <div class="kpi-value"><?= number_format($kpi_global) ?></div>
                <div class="kpi-sub">Division-wide notices</div>
            </div>
            <div class="kpi-card teal">
                <div class="kpi-label">School Notices</div>
                <div class="kpi-value"><?= number_format($kpi_school) ?></div>
                <div class="kpi-sub">Specific to your school</div>
            </div>
            <div class="kpi-card amber">
                <div class="kpi-label">My Bulletins</div>
                <div class="kpi-value"><?= number_format($kpi_mine) ?></div>
                <div class="kpi-sub">Published by you</div>
            </div>
        </div>

        <!-- COMPOSE CARD (EXPANDABLE) -->
        <?php if ($can_publish): ?>
            <div class="compose-card" id="composeCard">
                <h3>Publish New Announcement</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="publish">

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="annTitle">Title <span style="color:red;">*</span></label>
                        <input type="text" id="annTitle" name="title" required placeholder="e.g. MSCE 2026 Examination Schedule Update">
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="annContent">Message Details <span style="color:red;">*</span></label>
                        <textarea id="annContent" name="content" rows="4" required placeholder="Write full details of the announcement here..."></textarea>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" class="btn btn-secondary" onclick="toggleCompose(false)">Cancel</button>
                        <button type="submit" class="btn btn-dark">Publish Bulletin</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- CONTROLS & FILTERS -->
        <div class="controls-bar">
            <form method="GET" class="filter-group">
                <?php if ($filter_type): ?>
                    <input type="hidden" name="type" value="<?= htmlspecialchars($filter_type) ?>">
                <?php endif; ?>
                <input type="text" name="search" placeholder="Search announcements..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-dark" style="padding: 9px 16px;">Filter</button>
                <a href="announcements.php" class="btn btn-secondary" style="padding: 9px 14px;">Reset</a>
            </form>

            <div class="filter-tabs">
                <a href="announcements.php<?= $search ? '?search='.urlencode($search) : '' ?>"
                   class="filter-tab <?= $filter_type === '' ? 'active' : '' ?>">All</a>
                <a href="announcements.php?type=global<?= $search ? '&search='.urlencode($search) : '' ?>"
                   class="filter-tab <?= $filter_type === 'global' ? 'active' : '' ?>">Global</a>
                <a href="announcements.php?type=school<?= $search ? '&search='.urlencode($search) : '' ?>"
                   class="filter-tab <?= $filter_type === 'school' ? 'active' : '' ?>">School</a>
                <a href="announcements.php?type=mine<?= $search ? '&search='.urlencode($search) : '' ?>"
                   class="filter-tab <?= $filter_type === 'mine' ? 'active' : '' ?>">Mine</a>
            </div>
        </div>

        <!-- ANNOUNCEMENTS FEED -->
        <?php if (empty($announcements)): ?>
            <div class="empty-feed">
                <h4>No announcements found</h4>
                <p>Try adjusting your search query or switching filter tabs.</p>
            </div>
        <?php else: ?>
            <?php foreach ($announcements as $ann):
                $is_global = ($ann['school_id'] === null);
                $initial   = strtoupper(substr($ann['author_name'] ?? 'S', 0, 1));
            ?>
                <div class="announcement-card <?= $is_global ? 'global-card' : 'school-card' ?>">
                    <div class="ann-card-header">
                        <div class="author-info">
                            <div class="author-avatar <?= $is_global ? '' : 'school-avatar' ?>">
                                <?= $initial ?>
                            </div>
                            <div class="author-meta">
                                <div class="author-name"><?= htmlspecialchars($ann['author_name'] ?? 'System Administrator') ?></div>
                                <div class="author-role"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $ann['author_role'] ?? 'Division'))) ?></div>
                            </div>
                        </div>

                        <div class="ann-tags">
                            <?php if ($is_global): ?>
                                <span class="ann-tag tag-global">Global Division Bulletin</span>
                            <?php else: ?>
                                <span class="ann-tag tag-school"><?= htmlspecialchars($ann['school_name'] ?? 'School Notice') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h3 class="ann-title"><?= htmlspecialchars($ann['title']) ?></h3>
                    <p class="ann-body"><?= htmlspecialchars($ann['content']) ?></p>

                    <div class="ann-footer">
                        <span class="ann-date">
                            Published on <?= date('d M Y \a\t H:i', strtotime($ann['created_at'])) ?>
                        </span>

                        <?php if ($role === 'admin' || (int)$ann['published_by'] === $user_id): ?>
                            <form id="deleteForm<?= $ann['id'] ?>" method="POST" style="margin:0;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="announcement_id" value="<?= $ann['id'] ?>">
                            </form>
                            <button type="button" class="btn btn-delete btn-small"
                                    onclick="openAnnDeleteModal('deleteForm<?= $ann['id'] ?>')"
                                    style="padding: 4px 12px; font-size: 0.78rem;">
                                Delete
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1 || $total_filtered > 0): ?>
            <div class="pagination-wrap">
                <span class="pag-info">
                    Page <?= $page ?> of <?= $total_pages ?> &nbsp;&middot;&nbsp; <?= number_format($total_filtered) ?> bulletins total
                </span>
                <div class="pag-links">
                    <a href="<?= pg_ann_url(1, $carry_params) ?>" class="pag-btn <?= $page <= 1 ? 'disabled' : '' ?>">&laquo;</a>
                    <a href="<?= pg_ann_url($page - 1, $carry_params) ?>" class="pag-btn <?= $page <= 1 ? 'disabled' : '' ?>">&lsaquo; Prev</a>

                    <?php
                    $range = 2;
                    for ($p = 1; $p <= $total_pages; $p++):
                        if ($p === 1 || $p === $total_pages || ($p >= $page - $range && $p <= $page + $range)):
                    ?>
                        <a href="<?= pg_ann_url($p, $carry_params) ?>" class="pag-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                    <?php
                        elseif ($p === $page - $range - 1 || $p === $page + $range + 1):
                    ?>
                        <span class="pag-btn disabled" style="border:none;background:none;">...</span>
                    <?php
                        endif;
                    endfor;
                    ?>

                    <a href="<?= pg_ann_url($page + 1, $carry_params) ?>" class="pag-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">Next &rsaquo;</a>
                    <a href="<?= pg_ann_url($total_pages, $carry_params) ?>" class="pag-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">&raquo;</a>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include 'footer.php'; ?>

<script>
function toggleCompose(show) {
    var card = document.getElementById('composeCard');
    if (!card) return;
    if (show === undefined) {
        card.classList.toggle('show');
    } else if (show) {
        card.classList.add('show');
    } else {
        card.classList.remove('show');
    }
}

// Announcement-specific delete modal wiring
var _pendingDeleteFormId = null;

function openAnnDeleteModal(formId) {
    _pendingDeleteFormId = formId;
    // Reuse the global delete modal but wire confirm to form submit
    var modal = document.getElementById('globalDeleteModal');
    var confirmBtn = document.getElementById('globalDeleteConfirmBtn');
    if (!modal || !confirmBtn) return;

    // Replace href behaviour with form submission
    confirmBtn.removeAttribute('href');
    confirmBtn.onclick = function(e) {
        e.preventDefault();
        if (_pendingDeleteFormId) {
            var f = document.getElementById(_pendingDeleteFormId);
            if (f) f.submit();
        }
    };

    modal.classList.add('show');
}

document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('toggleComposeBtn');
    if (btn) {
        btn.addEventListener('click', function() {
            toggleCompose();
        });
    }
});
</script>

</body>
</html>
