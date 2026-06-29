<?php
session_start();
require_once '../config/db.php';
require_once '../common/email_service.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn     = get_db_connection();
$admin_id = $_SESSION['user_id'];

/* ══════════════════════════════════════════
   DELETE ASSIGNMENT
══════════════════════════════════════════ */
if (isset($_GET['delete'])) {
    $id   = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM subject_assignments WHERE assignment_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_assignments.php?deleted=1");
    exit();
}

/* ══════════════════════════════════════════
   RESEND EMAIL
══════════════════════════════════════════ */
if (isset($_GET['resend'])) {
    $id   = (int)$_GET['resend'];
    $stmt = $conn->prepare("
        SELECT sa.*, u.name, u.email, s.subject_name
        FROM subject_assignments sa
        JOIN users u    ON u.user_id    = sa.teacher_id
        JOIN subjects s ON s.subject_id = sa.subject_id
        WHERE sa.assignment_id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($data) {
        send_email(
            $data['email'],
            "Subject Assignment Reminder",
            "Hello {$data['name']},\n\nReminder of your assignment:\n\nSubject: {$data['subject_name']}\nRole: {$data['role']}\n\nPlease log in to your dashboard."
        );
        $upd = $conn->prepare("UPDATE subject_assignments SET email_sent = 1 WHERE assignment_id = ?");
        $upd->bind_param("i", $id);
        $upd->execute();
        $upd->close();
    }
    header("Location: manage_assignments.php?resent=1");
    exit();
}

/* ══════════════════════════════════════════
   REASSIGN TEACHER
══════════════════════════════════════════ */
if (isset($_POST['reassign'])) {
    $assignment_id = (int)$_POST['assignment_id'];
    $new_teacher   = (int)$_POST['new_teacher_id'];

    $stmt = $conn->prepare("
        SELECT sa.role, s.subject_name, u.name AS new_name, u.email AS new_email
        FROM subject_assignments sa
        JOIN subjects s ON s.subject_id = sa.subject_id
        JOIN users u    ON u.user_id    = ?
        WHERE sa.assignment_id = ?
    ");
    $stmt->bind_param("ii", $new_teacher, $assignment_id);
    $stmt->execute();
    $info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $upd = $conn->prepare("UPDATE subject_assignments SET teacher_id = ?, email_sent = 1 WHERE assignment_id = ?");
    $upd->bind_param("ii", $new_teacher, $assignment_id);
    $upd->execute();
    $upd->close();

    if ($info) {
        send_email(
            $info['new_email'],
            "Subject Assignment Notification",
            "Hello {$info['new_name']},\n\nYou have been assigned to:\nSubject: {$info['subject_name']}\nRole: {$info['role']}\n\nPlease log in to your dashboard."
        );
    }
    header("Location: manage_assignments.php?reassigned=1");
    exit();
}

/* ══════════════════════════════════════════
   FILTERS
══════════════════════════════════════════ */
$search   = trim($_GET['search']   ?? '');
$category = trim($_GET['category'] ?? '');
$coverage = trim($_GET['coverage'] ?? '');

/* ══════════════════════════════════════════
   PAGINATION
══════════════════════════════════════════ */
$per_page = 10;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

/* Total subjects count for pagination */
$csql  = "SELECT COUNT(*) AS total FROM subjects s WHERE s.status = 'active'";
$cp    = [];
$ct    = "";
if ($search)   { $csql .= " AND s.subject_name LIKE ?"; $cp[] = "%$search%"; $ct .= "s"; }
if ($category) { $csql .= " AND s.category = ?";        $cp[] = $category;   $ct .= "s"; }
$cst = $conn->prepare($csql);
if ($cp) $cst->bind_param($ct, ...$cp);
$cst->execute();
$total_subjects = (int)$cst->get_result()->fetch_assoc()['total'];
$cst->close();
$total_pages = max(1, (int)ceil($total_subjects / $per_page));

/* ══════════════════════════════════════════
   MAIN QUERY
   Uses correlated subqueries per role so we
   always get exactly ONE row per subject.
   Aliases avoid MySQL reserved words:
     iw  = item_writer assignment row
     mdr = moderator assignment row   (NOT 'mod' — reserved in MySQL)
══════════════════════════════════════════ */
$sql = "
    SELECT
        s.subject_id,
        s.subject_name,
        s.subject_code,
        s.category,

        iw.assignment_id  AS iw_id,
        iw.teacher_id     AS iw_teacher_id,
        iw_u.name         AS iw_teacher_name,
        iw.assigned_at    AS iw_assigned_at,
        iw.email_sent     AS iw_email_sent,
        iw.status         AS iw_status,

        mdr.assignment_id AS mdr_id,
        mdr.teacher_id    AS mdr_teacher_id,
        mdr_u.name        AS mdr_teacher_name,
        mdr.assigned_at   AS mdr_assigned_at,
        mdr.email_sent    AS mdr_email_sent,
        mdr.status        AS mdr_status

    FROM subjects s

    LEFT JOIN subject_assignments iw
           ON iw.assignment_id = (
               SELECT sa_iw.assignment_id
               FROM   subject_assignments sa_iw
               WHERE  sa_iw.subject_id = s.subject_id
                 AND  sa_iw.role       = 'item_writer'
               ORDER  BY sa_iw.assigned_at DESC
               LIMIT  1
           )
    LEFT JOIN users iw_u ON iw_u.user_id = iw.teacher_id

    LEFT JOIN subject_assignments mdr
           ON mdr.assignment_id = (
               SELECT sa_mdr.assignment_id
               FROM   subject_assignments sa_mdr
               WHERE  sa_mdr.subject_id = s.subject_id
                 AND  sa_mdr.role       = 'moderator'
               ORDER  BY sa_mdr.assigned_at DESC
               LIMIT  1
           )
    LEFT JOIN users mdr_u ON mdr_u.user_id = mdr.teacher_id

    WHERE s.status = 'active'
";

$params = [];
$types  = "";
if ($search)   { $sql .= " AND s.subject_name LIKE ?"; $params[] = "%$search%"; $types .= "s"; }
if ($category) { $sql .= " AND s.category = ?";        $params[] = $category;   $types .= "s"; }
$sql .= " ORDER BY s.subject_name LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types   .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

/* ══════════════════════════════════════════
   BUILD SUBJECTS ARRAY
   One row per subject — no grouping needed.
══════════════════════════════════════════ */
$subjects_list = [];
while ($r = $result->fetch_assoc()) {
    $subjects_list[] = [
        'subject_id'   => $r['subject_id'],
        'subject_name' => $r['subject_name'],
        'subject_code' => $r['subject_code'],
        'category'     => $r['category'],
        'roles'        => [
            'item_writer' => $r['iw_id'] ? [
                'assignment_id' => $r['iw_id'],
                'teacher_name'  => $r['iw_teacher_name'],
                'assigned_at'   => $r['iw_assigned_at'],
                'email_sent'    => $r['iw_email_sent'],
                'assign_status' => $r['iw_status'],
            ] : null,
            'moderator'   => $r['mdr_id'] ? [
                'assignment_id' => $r['mdr_id'],
                'teacher_name'  => $r['mdr_teacher_name'],
                'assigned_at'   => $r['mdr_assigned_at'],
                'email_sent'    => $r['mdr_email_sent'],
                'assign_status' => $r['mdr_status'],
            ] : null,
        ],
    ];
}

/* Coverage filter in PHP after fetch */
if ($coverage) {
    $subjects_list = array_values(array_filter($subjects_list, function ($s) use ($coverage) {
        $f = (int)!is_null($s['roles']['item_writer']) + (int)!is_null($s['roles']['moderator']);
        if ($coverage === 'all')     return $f === 2;
        if ($coverage === 'partial') return $f === 1;
        if ($coverage === 'none')    return $f === 0;
        return true;
    }));
}

/* ══════════════════════════════════════════
   KPI — full dataset, accurate across pages
══════════════════════════════════════════ */
$kpi_sql = "
    SELECT
        COUNT(DISTINCT s.subject_id) AS total,
        SUM(iw.assignment_id IS NOT NULL AND mdr.assignment_id IS NOT NULL)         AS full_count,
        SUM((iw.assignment_id IS NOT NULL) != (mdr.assignment_id IS NOT NULL))      AS partial_count,
        SUM(iw.assignment_id IS NULL AND mdr.assignment_id IS NULL)                 AS none_count
    FROM subjects s
    LEFT JOIN subject_assignments iw
           ON iw.assignment_id = (
               SELECT sa_iw.assignment_id FROM subject_assignments sa_iw
               WHERE  sa_iw.subject_id = s.subject_id AND sa_iw.role = 'item_writer'
               ORDER  BY sa_iw.assigned_at DESC LIMIT 1
           )
    LEFT JOIN subject_assignments mdr
           ON mdr.assignment_id = (
               SELECT sa_mdr.assignment_id FROM subject_assignments sa_mdr
               WHERE  sa_mdr.subject_id = s.subject_id AND sa_mdr.role = 'moderator'
               ORDER  BY sa_mdr.assigned_at DESC LIMIT 1
           )
    WHERE s.status = 'active'
";
$kpi = $conn->query($kpi_sql)->fetch_assoc();

/* ══════════════════════════════════════════
   TEACHERS FOR REASSIGN MODAL
══════════════════════════════════════════ */
$teachers_list = [];
$tr = $conn->query("
    SELECT user_id, name, COALESCE(teacher_category, '') AS teacher_category
    FROM   users
    WHERE  role = 'teacher'
    ORDER  BY name
");
while ($t = $tr->fetch_assoc()) {
    $teachers_list[] = $t;
}

/* ══════════════════════════════════════════
   CATEGORIES FOR FILTER DROPDOWN
══════════════════════════════════════════ */
$categories = [];
$cr = $conn->query("
    SELECT DISTINCT category
    FROM   subjects
    WHERE  status = 'active' AND category IS NOT NULL
    ORDER  BY category
");
while ($c = $cr->fetch_assoc()) {
    $categories[] = $c['category'];
}

$conn->close();

/* ══════════════════════════════════════════
   HELPER FUNCTIONS
══════════════════════════════════════════ */
function coverage_badge(int $filled): string {
    if ($filled === 2) return '<span class="badge badge-success">All roles filled</span>';
    if ($filled === 1) return '<span class="badge badge-warning">1 role missing</span>';
    return '<span class="badge badge-danger">No roles assigned</span>';
}

function status_badge(?string $status): string {
    return match ($status) {
        'submitted' => '<span class="badge badge-info">Submitted</span>',
        'moderated' => '<span class="badge badge-success">Moderated</span>',
        'assigned'  => '<span class="badge badge-assigned">Assigned</span>',
        default     => '<span class="badge">—</span>',
    };
}

function role_label(string $role): string {
    return $role === 'item_writer' ? 'Item writer' : 'Moderator';
}

function page_url(int $p, string $search, string $category, string $coverage): string {
    return 'manage_assignments.php?' . http_build_query(array_filter([
        'page'     => $p,
        'search'   => $search,
        'category' => $category,
        'coverage' => $coverage,
    ]));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Assignments | NED-SEMS</title>
    <?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>
    <style>
        /* ── Subject card ── */
        .subject-card {
            background: var(--card-color);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            margin-bottom: 16px;
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }

        .subject-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 13px 18px;
            border-bottom: 1px solid var(--border-color);
            background: #f8fafc;
            flex-wrap: wrap;
            gap: 8px;
        }

        .subject-name {
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 3px;
        }

        .subject-cat-pill {
            display: inline-flex;
            align-items: center;
            padding: 2px 9px;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 600;
            background: #dbeafe;
            color: #1d4ed8;
            margin-top: 4px;
        }

        /* ── Role table inside card ── */
        .role-table {
            width: 100%;
            border-collapse: collapse;
        }

        .role-table th {
            background: #f8fafc;
            text-align: left;
            padding: 9px 18px;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border-color);
        }

        .role-table td {
            padding: 11px 18px;
            font-size: 0.875rem;
            color: var(--text-color);
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .role-table tr:last-child td { border-bottom: none; }
        .role-table tr:hover td      { background: #f8fafc; }

        .role-table tr.empty-role td {
            color: var(--text-muted);
            font-style: italic;
        }

        .role-table tr.marker-row td {
            background: #fafafa;
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        /* ── Inline action links ── */
        .link-action {
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            color: var(--primary-color);
            cursor: pointer;
            background: none;
            border: none;
            font-family: inherit;
            padding: 0;
            transition: color 0.15s;
        }
        .link-action:hover        { color: var(--info-color); }
        .link-action.danger       { color: var(--danger-color); }
        .link-action.danger:hover { color: #dc2626; }
        .link-action.assign       { color: var(--info-color); font-style: normal; }
        .link-sep { color: #cbd5e1; margin: 0 6px; user-select: none; }

        /* ── KPI bar ── */
        .kpi-bar {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 22px;
        }

        .kpi-tile {
            background: var(--card-color);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            padding: 15px 17px;
            box-shadow: var(--box-shadow);
        }

        .kpi-tile .kpi-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            margin-bottom: 5px;
        }

        .kpi-tile .kpi-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
        }

        .kpi-tile .kpi-hint {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .kpi-tile.kpi-blue   { border-left: 4px solid var(--info-color); }
        .kpi-tile.kpi-green  { border-left: 4px solid var(--success-color); }
        .kpi-tile.kpi-yellow { border-left: 4px solid var(--warning-color); }
        .kpi-tile.kpi-red    { border-left: 4px solid var(--danger-color); }

        /* ── Pagination ── */
        .pagination-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        .pagination-info { font-size: 0.8rem; color: var(--text-muted); }

        .pagination-links { display: flex; gap: 6px; flex-wrap: wrap; }

        .page-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            background: var(--card-color);
            color: var(--text-color);
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.15s, border-color 0.15s;
        }
        .page-btn:hover   { background: #f1f5f9; border-color: #cbd5e1; }
        .page-btn.active  { background: var(--primary-dark); color: #fff; border-color: var(--primary-dark); }
        .page-btn.disabled{ opacity: 0.4; pointer-events: none; }

        /* ── Modal extras ── */
        .modal-subject-info {
            background: var(--background-color);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 10px 14px;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 16px;
            line-height: 1.6;
        }
        .modal-subject-info strong { color: #0f172a; }

        /* ── Marker note ── */
        .marker-note {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.75rem;
            color: var(--text-muted);
            background: #f1f5f9;
            border-radius: 999px;
            padding: 3px 10px;
        }

        /* ── Empty state ── */
        .empty-subjects { text-align: center; padding: 50px 20px; color: var(--text-muted); }
        .empty-subjects h3 { font-size: 1rem; color: #0f172a; margin-bottom: 6px; }

        @media (max-width: 768px) {
            .kpi-bar { grid-template-columns: 1fr 1fr; }
            .role-table th:nth-child(3),
            .role-table td:nth-child(3) { display: none; }
        }
        @media (max-width: 480px) {
            .kpi-bar { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
    <?php include '../common/sidebar.php'; ?>

    <div class="content">

        <!-- ══════════ PAGE HEADER ══════════ -->
        <div class="page-header">
            <div>
                <h2 class="page-title">Manage assignments</h2>
                <p class="page-subtitle">
                    Item writer &amp; moderator assignments per subject —
                    markers are assigned by headteachers
                </p>
            </div>
            <div class="header-actions">
                <a href="assign_teacher.php" class="btn btn-dark">+ New assignment</a>
            </div>
        </div>

        <!-- ══════════ FLASH MESSAGES ══════════ -->
        <?php if (isset($_GET['deleted'])):    ?>
            <div class="alert alert-error">Assignment removed successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET['resent'])):     ?>
            <div class="alert alert-success">Reminder email resent successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET['reassigned'])): ?>
            <div class="alert alert-success">Teacher reassigned and notified by email.</div>
        <?php endif; ?>

        <!-- ══════════ KPI BAR ══════════ -->
        <div class="kpi-bar">
            <div class="kpi-tile kpi-blue">
                <div class="kpi-label">Total subjects</div>
                <div class="kpi-value"><?= (int)$kpi['total'] ?></div>
                <div class="kpi-hint">Active subjects</div>
            </div>
            <div class="kpi-tile kpi-green">
                <div class="kpi-label">Fully covered</div>
                <div class="kpi-value"><?= (int)$kpi['full_count'] ?></div>
                <div class="kpi-hint">Both roles assigned</div>
            </div>
            <div class="kpi-tile kpi-yellow">
                <div class="kpi-label">Partially assigned</div>
                <div class="kpi-value"><?= (int)$kpi['partial_count'] ?></div>
                <div class="kpi-hint">1 role missing</div>
            </div>
            <div class="kpi-tile kpi-red">
                <div class="kpi-label">Not started</div>
                <div class="kpi-value"><?= (int)$kpi['none_count'] ?></div>
                <div class="kpi-hint">No roles assigned yet</div>
            </div>
        </div>

        <!-- ══════════ FILTERS ══════════ -->
        <form method="GET" class="search-form">
            <input type="text"
                   name="search"
                   placeholder="Search subject…"
                   value="<?= htmlspecialchars($search) ?>">

            <select name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>"
                        <?= $category === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars(ucfirst($cat)) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="coverage">
                <option value="">All coverage</option>
                <option value="all"     <?= $coverage === 'all'     ? 'selected' : '' ?>>Fully covered</option>
                <option value="partial" <?= $coverage === 'partial' ? 'selected' : '' ?>>Partially assigned</option>
                <option value="none"    <?= $coverage === 'none'    ? 'selected' : '' ?>>Not started</option>
            </select>

            <button type="submit" class="btn btn-dark">Filter</button>
            <a href="manage_assignments.php" class="btn btn-secondary">Reset</a>
        </form>

        <!-- ══════════ PAGINATION META ══════════ -->
        <?php if ($total_subjects > 0): ?>
            <p class="table-meta">
                Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_subjects) ?>
                of <?= $total_subjects ?> subjects
            </p>
        <?php endif; ?>

        <!-- ══════════ SUBJECT CARDS ══════════ -->
        <?php if (empty($subjects_list)): ?>
            <div class="empty-subjects">
                <h3>No subjects found</h3>
                <p>Try adjusting your filters or
                   <a href="manage_assignments.php" class="link-action assign">reset</a>.
                </p>
            </div>

        <?php else: ?>

            <?php foreach ($subjects_list as $subj):
                $roles  = $subj['roles'];
                $filled = (int)!is_null($roles['item_writer']) + (int)!is_null($roles['moderator']);
            ?>
            <div class="subject-card">

                <div class="subject-card-header">
                    <div>
                        <div class="subject-name">
                            <?= htmlspecialchars($subj['subject_name']) ?>
                            <?php if ($subj['subject_code']): ?>
                                <span class="muted-text" style="font-weight:400">
                                    &nbsp;(<?= htmlspecialchars($subj['subject_code']) ?>)
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($subj['category']): ?>
                            <span class="subject-cat-pill">
                                <?= htmlspecialchars(ucfirst($subj['category'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?= coverage_badge($filled) ?>
                </div>

                <table class="role-table">
                    <thead>
                        <tr>
                            <th style="width:15%">Role</th>
                            <th style="width:24%">Assigned to</th>
                            <th style="width:17%">Assigned on</th>
                            <th style="width:16%">Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php foreach (['item_writer', 'moderator'] as $role):
                        $a = $roles[$role];
                    ?>

                        <?php if ($a): ?>
                        <!-- ROLE IS FILLED -->
                        <tr>
                            <td>
                                <span class="badge <?= $role === 'item_writer' ? 'badge-info' : 'badge-assigned' ?>">
                                    <?= role_label($role) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($a['teacher_name']) ?></td>
                            <td class="muted-text">
                                <?= date('d M Y', strtotime($a['assigned_at'])) ?>
                            </td>
                            <td><?= status_badge($a['assign_status']) ?></td>
                            <td>
                                <button class="link-action"
                                        onclick="openReassign(
                                            <?= $a['assignment_id'] ?>,
                                            '<?= htmlspecialchars(addslashes($subj['subject_name'])) ?>',
                                            '<?= role_label($role) ?>',
                                            '<?= htmlspecialchars(addslashes($a['teacher_name'])) ?>',
                                            '<?= strtolower($subj['category'] ?? '') ?>'
                                        )">
                                    Reassign
                                </button>
                                <span class="link-sep">·</span>
                                <a href="?resend=<?= $a['assignment_id'] ?>"
                                   class="link-action"
                                   onclick="return confirm('Resend email to <?= htmlspecialchars(addslashes($a['teacher_name'])) ?>?')">
                                    Resend
                                </a>
                                <span class="link-sep">·</span>
                                <a href="#"
                                   class="link-action danger"
                                   onclick="openDeleteModal('manage_assignments.php?delete=<?= $a['assignment_id'] ?>')">
                                    Remove
                                </a>
                            </td>
                        </tr>

                        <?php else: ?>
                        <!-- ROLE IS EMPTY -->
                        <tr class="empty-role">
                            <td>
                                <span class="badge <?= $role === 'item_writer' ? 'badge-info' : 'badge-assigned' ?>"
                                      style="opacity:0.4">
                                    <?= role_label($role) ?>
                                </span>
                            </td>
                            <td>Not assigned</td>
                            <td>—</td>
                            <td><span class="badge badge-warning">Pending</span></td>
                            <td>
                                <a href="assign_teacher.php?subject_id=<?= $subj['subject_id'] ?>&role=<?= $role ?>"
                                   class="link-action assign">
                                    + Assign now
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>

                    <?php endforeach; ?>

                    <!-- MARKER ROW — read-only, headteacher responsibility -->
                    <tr class="marker-row">
                        <td>
                            <span class="badge" style="background:#e2e8f0;color:#475569">Marker</span>
                        </td>
                        <td colspan="3">
                            <span class="marker-note">&#128274; To be assigned by headteacher</span>
                        </td>
                        <td></td>
                    </tr>

                    </tbody>
                </table>

            </div>
            <?php endforeach; ?>

            <!-- ══════════ PAGINATION ══════════ -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-bar">
                <span class="pagination-info">
                    Page <?= $page ?> of <?= $total_pages ?>
                </span>
                <div class="pagination-links">
                    <a href="<?= page_url($page - 1, $search, $category, $coverage) ?>"
                       class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                        &lsaquo; Prev
                    </a>

                    <?php
                    $range = 2;
                    for ($p = 1; $p <= $total_pages; $p++):
                        if ($p === 1 || $p === $total_pages || ($p >= $page - $range && $p <= $page + $range)):
                    ?>
                        <a href="<?= page_url($p, $search, $category, $coverage) ?>"
                           class="page-btn <?= $p === $page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php
                        elseif ($p === $page - $range - 1 || $p === $page + $range + 1):
                    ?>
                        <span class="page-btn disabled" style="border:none;background:none">…</span>
                    <?php
                        endif;
                    endfor;
                    ?>

                    <a href="<?= page_url($page + 1, $search, $category, $coverage) ?>"
                       class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        Next &rsaquo;
                    </a>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>

    </div><!-- /content -->
</div><!-- /dashboard -->

<!-- ══════════════════════════════════════════
     REASSIGN MODAL
══════════════════════════════════════════ -->
<div id="reassignModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="reassign-title">
    <div class="modal-content" style="max-width:440px">

        <h3 id="reassign-title">Reassign teacher</h3>

        <div class="modal-subject-info" id="reassignInfo"></div>

        <form method="POST">
            <input type="hidden" name="reassign"      value="1">
            <input type="hidden" name="assignment_id" id="reassignAssignmentId">

            <div class="form-group">
                <label for="newTeacherSelect">Select replacement teacher</label>
                <select name="new_teacher_id" id="newTeacherSelect" required>
                    <option value="">— Choose teacher —</option>
                    <?php foreach ($teachers_list as $t): ?>
                        <option value="<?= $t['user_id'] ?>"
                                data-category="<?= strtolower(htmlspecialchars($t['teacher_category'])) ?>">
                            <?= htmlspecialchars($t['name']) ?>
                            <?= $t['teacher_category'] ? ' (' . ucfirst($t['teacher_category']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeReassignModal()">
                    Cancel
                </button>
                <button type="submit" class="btn btn-edit">
                    Confirm reassignment
                </button>
            </div>
        </form>

    </div>
</div>

<?php include '../common/footer.php'; ?>

<script>
function openReassign(assignmentId, subjectName, roleName, currentTeacher, subjectCategory) {
    document.getElementById('reassignAssignmentId').value = assignmentId;

    document.getElementById('reassignInfo').innerHTML =
        '<strong>' + subjectName + '</strong> &nbsp;&middot;&nbsp; ' + roleName +
        '<br><span style="font-size:.8rem;margin-top:3px;display:block">' +
        'Currently: <strong>' + currentTeacher + '</strong></span>';

    /* Filter teacher dropdown by subject category if set */
    const sel = document.getElementById('newTeacherSelect');
    sel.value = '';
    Array.from(sel.options).forEach(function (opt) {
        if (!opt.value) return;
        opt.hidden = subjectCategory
            ? (opt.dataset.category !== '' && opt.dataset.category !== subjectCategory)
            : false;
    });

    document.getElementById('reassignModal').classList.add('show');
}

function closeReassignModal() {
    document.getElementById('reassignModal').classList.remove('show');
}

document.getElementById('reassignModal').addEventListener('click', function (e) {
    if (e.target === this) closeReassignModal();
});
</script>

</body>
</html>