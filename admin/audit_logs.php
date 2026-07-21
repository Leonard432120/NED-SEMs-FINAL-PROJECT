<?php
/* ════════════════════════════════════════════════════════════════
   admin/audit_logs.php
   EDM / Admin: Full-featured System Audit Log Viewer
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../common/pagination_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

/* ── FILTERS ── */
$search      = trim($_GET['search']      ?? '');
$action_filter = trim($_GET['action']    ?? '');
$user_filter = (int)($_GET['user_id']    ?? 0);
$date_from   = trim($_GET['date_from']   ?? '');
$date_to     = trim($_GET['date_to']     ?? '');
$tab         = in_array($_GET['tab'] ?? '', ['audit', 'exam_workflow', 'result_workflow']) ? $_GET['tab'] : 'audit';
$page        = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 5;

/* ── SUMMARY STATS (always from full audit_logs) ── */
$stats_total   = (int)$conn->query("SELECT COUNT(*) AS c FROM audit_logs")->fetch_assoc()['c'];
$stats_logins  = (int)$conn->query("SELECT COUNT(*) AS c FROM audit_logs WHERE action='USER_LOGIN_SUCCESS'")->fetch_assoc()['c'];
$stats_failed  = (int)$conn->query("SELECT COUNT(*) AS c FROM audit_logs WHERE action='USER_LOGIN_FAILED'")->fetch_assoc()['c'];
$stats_today   = (int)$conn->query("SELECT COUNT(*) AS c FROM audit_logs WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'];

/* ── DISTINCT ACTIONS (for filter dropdown) ── */
$actions_res = $conn->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC");
$all_actions = [];
while ($a = $actions_res->fetch_assoc()) {
    $all_actions[] = $a['action'];
}

/* ── ALL USERS (for user filter dropdown) ── */
$users_res = $conn->query("SELECT user_id, name, role FROM users ORDER BY name ASC");
$all_users = [];
while ($u = $users_res->fetch_assoc()) {
    $all_users[] = $u;
}

/* ════════════════════════════════════════════════════════════════
   TAB 1: MAIN AUDIT LOGS
   ════════════════════════════════════════════════════════════════ */
$logs = [];
$total_logs = 0;

if ($tab === 'audit') {
    /* ── BUILD WHERE CLAUSE ── */
    $where_parts = ["1"];
    $params  = [];
    $types   = '';

    if ($search !== '') {
        $where_parts[] = "(u.name LIKE ? OR al.action LIKE ? OR al.details LIKE ? OR al.ip_address LIKE ?)";
        $s = "%$search%";
        $params[]= $s; $params[]= $s; $params[]= $s; $params[]= $s;
        $types .= 'ssss';
    }
    if ($action_filter !== '') {
        $where_parts[] = "al.action = ?";
        $params[] = $action_filter;
        $types .= 's';
    }
    if ($user_filter > 0) {
        $where_parts[] = "al.user_id = ?";
        $params[] = $user_filter;
        $types .= 'i';
    }
    if ($date_from !== '') {
        $where_parts[] = "DATE(al.created_at) >= ?";
        $params[] = $date_from;
        $types .= 's';
    }
    if ($date_to !== '') {
        $where_parts[] = "DATE(al.created_at) <= ?";
        $params[] = $date_to;
        $types .= 's';
    }

    $where_sql = implode(' AND ', $where_parts);

    /* ── COUNT ── */
    $count_sql = "
        SELECT COUNT(*) AS cnt
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.user_id
        WHERE $where_sql
    ";
    $stmt = $conn->prepare($count_sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total_logs = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    /* ── FETCH ── */
    $pagination = paginate($total_logs, $page, $per_page);
    $offset = ($pagination['page'] - 1) * $per_page;

    $data_sql = "
        SELECT
            al.log_id, al.action, al.details, al.created_at, al.ip_address,
            u.user_id   AS actor_id,
            u.name      AS actor_name,
            u.role      AS actor_role,
            tu.name     AS target_name,
            tu.role     AS target_role
        FROM audit_logs al
        LEFT JOIN users u  ON al.user_id        = u.user_id
        LEFT JOIN users tu ON al.target_user_id = tu.user_id
        WHERE $where_sql
        ORDER BY al.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $all_params = array_merge($params, [$per_page, $offset]);
    $all_types  = $types . 'ii';
    $stmt = $conn->prepare($data_sql);
    $stmt->bind_param($all_types, ...$all_params);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

/* ════════════════════════════════════════════════════════════════
   TAB 2: EXAM WORKFLOW LOGS
   ════════════════════════════════════════════════════════════════ */
$exam_logs = [];
$total_exam_logs = 0;
$pagination_exam = null;

if ($tab === 'exam_workflow') {
    $total_exam_logs = (int)$conn->query("SELECT COUNT(*) AS c FROM exam_workflow_logs")->fetch_assoc()['c'];
    $pagination_exam = paginate($total_exam_logs, $page, $per_page);
    $offset_e = ($pagination_exam['page'] - 1) * $per_page;

    $stmt = $conn->prepare("
        SELECT ewl.log_id, ewl.exam_id, ewl.action, ewl.performed_by, ewl.role, ewl.timestamp, ewl.notes,
               e.exam_name, e.year,
               u.name AS performer_name
        FROM exam_workflow_logs ewl
        LEFT JOIN exams e ON ewl.exam_id = e.exam_id
        LEFT JOIN users u ON ewl.performed_by = u.user_id
        ORDER BY ewl.timestamp DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('ii', $per_page, $offset_e);
    $stmt->execute();
    $exam_logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

/* ════════════════════════════════════════════════════════════════
   TAB 3: RESULT WORKFLOW LOGS
   ════════════════════════════════════════════════════════════════ */
$result_logs = [];
$total_result_logs = 0;
$pagination_result = null;

if ($tab === 'result_workflow') {
    $total_result_logs = (int)$conn->query("SELECT COUNT(*) AS c FROM result_workflow_logs")->fetch_assoc()['c'];
    $pagination_result = paginate($total_result_logs, $page, $per_page);
    $offset_r = ($pagination_result['page'] - 1) * $per_page;

    $stmt = $conn->prepare("
        SELECT rwl.log_id, rwl.result_id, rwl.action, rwl.performed_by, rwl.role,
               rwl.from_status, rwl.to_status, rwl.notes, rwl.created_at,
               u.name AS performer_name,
               st.name AS student_name
        FROM result_workflow_logs rwl
        LEFT JOIN users u ON rwl.performed_by = u.user_id
        LEFT JOIN results r ON rwl.result_id = r.result_id
        LEFT JOIN students st ON r.student_id = st.student_id
        ORDER BY rwl.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('ii', $per_page, $offset_r);
    $stmt->execute();
    $result_logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$conn->close();

/* ── HELPER: classify actions into categories ── */
function action_category(string $action): string {
    $action_upper = strtoupper($action);
    if (str_contains($action_upper, 'LOGIN') || str_contains($action_upper, 'LOGOUT')) return 'auth';
    if (str_contains($action_upper, 'CREATE') || str_contains($action_upper, 'ADD'))   return 'create';
    if (str_contains($action_upper, 'DELETE') || str_contains($action_upper, 'REMOVE')) return 'delete';
    if (str_contains($action_upper, 'UPDATE') || str_contains($action_upper, 'EDIT') || str_contains($action_upper, 'CHANGE')) return 'update';
    if (str_contains($action_upper, 'MODERAT')) return 'moderation';
    if (str_contains($action_upper, 'DOWNLOAD') || str_contains($action_upper, 'EXPORT')) return 'export';
    if (str_contains($action_upper, 'PUBLISH') || str_contains($action_upper, 'COMPILE')) return 'system';
    if (str_contains($action_upper, 'LOCK') || str_contains($action_upper, 'FAIL'))  return 'security';
    return 'other';
}

function action_badge(string $category): string {
    $map = [
        'auth'       => ['#dbeafe', '#1d4ed8'],
        'create'     => ['#dcfce7', '#15803d'],
        'delete'     => ['#fee2e2', '#dc2626'],
        'update'     => ['#fef3c7', '#d97706'],
        'moderation' => ['#f3e8ff', '#7e22ce'],
        'export'     => ['#f0fdf4', '#166534'],
        'system'     => ['#e0f2fe', '#0369a1'],
        'security'   => ['#fee2e2', '#991b1b'],
        'other'      => ['#f1f5f9', '#475569'],
    ];
    [$bg, $color] = $map[$category] ?? $map['other'];
    return "background: $bg; color: $color;";
}

$module_css = 'admin';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="NED-SEMS System Audit Logs — Full user activity and system event tracking for the EDM">
    <title>System Audit Logs | NED-SEMS</title>
    <style>
        /* ── STATS ROW ── */
        .audit-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        @media (max-width: 900px) { .audit-stats { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 500px) { .audit-stats { grid-template-columns: 1fr; } }

        .audit-stat {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: var(--box-shadow);
        }
        .audit-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .audit-stat h5 {
            margin: 0;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }
        .audit-stat p {
            margin: 4px 0 0;
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary-dark);
            line-height: 1;
        }

        /* ── FILTER CARD ── */
        .filter-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 20px;
            box-shadow: var(--box-shadow);
        }
        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto;
            gap: 10px;
            align-items: end;
        }
        @media (max-width: 992px) { .filter-grid { grid-template-columns: 1fr 1fr; } }
        .filter-grid .form-group {
            margin: 0;
        }
        .filter-grid label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .filter-grid input,
        .filter-grid select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.875rem;
        }

        /* ── TABS ── */
        .tabs-header {
            display: flex;
            gap: 4px;
            margin-bottom: 20px;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 10px;
            width: fit-content;
        }
        .tab-link {
            padding: 9px 20px;
            border-radius: 8px;
            border: none;
            background: none;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .tab-link:hover { color: var(--primary-dark); background: #fff; }
        .tab-link.active { background: #fff; color: var(--info-color); box-shadow: 0 1px 4px rgba(0,0,0,0.08); }

        /* ── TABLE ENHANCEMENTS ── */
        .action-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }
        .details-cell {
            max-width: 280px;
            font-size: 0.8rem;
            color: #475569;
            word-break: break-word;
        }
        .details-cell code {
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.75rem;
            display: block;
            max-height: 56px;
            overflow: hidden;
            cursor: pointer;
            transition: max-height 0.3s ease;
        }
        .details-cell code.expanded { max-height: 400px; }
        .ip-pill {
            font-family: monospace;
            font-size: 0.75rem;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 20px;
            color: #64748b;
        }
        .user-link {
            font-weight: 600;
            color: var(--primary-dark);
            text-decoration: none;
        }
        .user-link:hover { text-decoration: underline; }
        .role-pill {
            font-size: 0.7rem;
            color: var(--text-muted);
            background: #f1f5f9;
            padding: 1px 7px;
            border-radius: 20px;
            margin-left: 4px;
        }
        .arrow-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.8rem;
        }
        .status-from { color: #64748b; background: #f1f5f9; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; }
        .status-to   { color: #0369a1; background: #e0f2fe; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; }

        /* ── EMPTY STATE ── */
        .empty-log {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        .empty-log .icon {
            display: inline-block;
            width: 56px;
            height: 56px;
            border: 2px solid var(--border-color);
            border-radius: 50%;
            line-height: 52px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 16px;
        }
        .empty-log h4 { margin: 0 0 8px; font-size: 1.1rem; color: var(--primary-dark); }

        /* ── CLEAR FILTER LINK ── */
        .clear-link {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-decoration: none;
            white-space: nowrap;
            padding: 8px 0;
        }
        .clear-link:hover { color: var(--danger-color); }

        @media print {
            .header, .sidebar, .filter-card, .tabs-header, .pagination, .btn, .page-header button { display: none !important; }
            .content { margin: 0 !important; padding: 0 !important; }
            .audit-stats { grid-template-columns: repeat(4, 1fr) !important; }
        }
    </style>
</head>
<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
    <?php include '../common/sidebar.php'; ?>

    <div class="content">

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div>
                <h1 class="page-title">System Audit Logs</h1>
                <p class="page-subtitle">Complete chronological record of all system events, user actions, and workflow state changes</p>
            </div>
            <button onclick="window.print()" class="btn btn-secondary">Print Log</button>
        </div>

        <!-- SUMMARY STATS -->
        <div class="audit-stats">
            <div class="audit-stat">
                <div class="audit-stat-icon" style="background:#e0f2fe; color:#0369a1;">ALL</div>
                <div>
                    <h5>Total Events</h5>
                    <p><?= number_format($stats_total) ?></p>
                </div>
            </div>
            <div class="audit-stat">
                <div class="audit-stat-icon" style="background:#dcfce7; color:#15803d;">LOG</div>
                <div>
                    <h5>Successful Logins</h5>
                    <p><?= number_format($stats_logins) ?></p>
                </div>
            </div>
            <div class="audit-stat">
                <div class="audit-stat-icon" style="background:#fee2e2; color:#991b1b;">FAIL</div>
                <div>
                    <h5>Failed Login Attempts</h5>
                    <p style="color: <?= $stats_failed > 5 ? '#dc2626' : 'inherit' ?>;"><?= number_format($stats_failed) ?></p>
                </div>
            </div>
            <div class="audit-stat">
                <div class="audit-stat-icon" style="background:#fef3c7; color:#d97706;">NOW</div>
                <div>
                    <h5>Events Today</h5>
                    <p><?= number_format($stats_today) ?></p>
                </div>
            </div>
        </div>

        <!-- TABS -->
        <div class="tabs-header">
            <a href="?tab=audit<?= $search ? '&search=' . urlencode($search) : '' ?>"
               class="tab-link <?= $tab === 'audit' ? 'active' : '' ?>">
                User Activity Log
            </a>
            <a href="?tab=exam_workflow"
               class="tab-link <?= $tab === 'exam_workflow' ? 'active' : '' ?>">
                Exam Workflow
            </a>
            <a href="?tab=result_workflow"
               class="tab-link <?= $tab === 'result_workflow' ? 'active' : '' ?>">
                Result Workflow
            </a>
        </div>

        <?php if ($tab === 'audit'): ?>
        <!-- ═══════════════════════════════════════════════
             TAB 1: MAIN AUDIT LOG + FILTERS
             ═══════════════════════════════════════════════ -->

        <!-- FILTERS -->
        <div class="filter-card">
            <form method="GET" id="filterForm">
                <input type="hidden" name="tab" value="audit">
                <div class="filter-grid">
                    <div class="form-group">
                        <label>Search</label>
                        <input type="text" name="search" id="searchInput"
                               placeholder="Name, action, IP, details..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="form-group">
                        <label>Action Type</label>
                        <select name="action">
                            <option value="">All Actions</option>
                            <?php foreach ($all_actions as $act): ?>
                                <option value="<?= htmlspecialchars($act) ?>"
                                    <?= $action_filter === $act ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($act) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>User</label>
                        <select name="user_id">
                            <option value="0">All Users</option>
                            <?php foreach ($all_users as $u): ?>
                                <option value="<?= $u['user_id'] ?>"
                                    <?= $user_filter === (int)$u['user_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['name']) ?> (<?= $u['role'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>From Date</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    <div class="form-group">
                        <label>To Date</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <button type="submit" class="btn btn-teal" style="margin-top: 20px;">Filter</button>
                        <?php if ($search || $action_filter || $user_filter || $date_from || $date_to): ?>
                            <a href="?tab=audit" class="clear-link" style="text-align:center;">Clear Filters</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- AUDIT LOG TABLE -->
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="margin:0;">
                    User Activity Events
                    <?php if ($total_logs !== $stats_total): ?>
                        <span style="font-size:0.8rem; color:var(--text-muted); font-weight:400; margin-left:8px;">
                            (<?= number_format($total_logs) ?> filtered of <?= number_format($stats_total) ?> total)
                        </span>
                    <?php else: ?>
                        <span style="font-size:0.8rem; color:var(--text-muted); font-weight:400; margin-left:8px;">
                            (<?= number_format($total_logs) ?> events)
                        </span>
                    <?php endif; ?>
                </h3>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width:50px;">#</th>
                            <th style="width:140px;">Timestamp</th>
                            <th>Action</th>
                            <th>Actor</th>
                            <th>Target User</th>
                            <th>Details</th>
                            <th style="width:100px;">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-log">
                                        <div class="icon">NIL</div>
                                        <h4>No Events Found</h4>
                                        <p>No audit log entries match your current filter criteria.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $idx => $log):
                                $cat   = action_category($log['action']);
                                $style = action_badge($cat);

                                // Parse JSON details gracefully
                                $raw_details = $log['details'] ?? '';
                                $decoded = @json_decode($raw_details, true);
                                if (is_array($decoded)) {
                                    $display_details = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                } else {
                                    $display_details = $raw_details;
                                }
                                $is_long = strlen($display_details) > 80;
                            ?>
                            <tr>
                                <td style="color:var(--text-muted); font-size:0.75rem; font-weight:700;"><?= $offset + $idx + 1 ?></td>
                                <td>
                                    <span style="font-size:0.8rem; color:var(--primary-dark); font-weight:600;">
                                        <?= date('d M Y', strtotime($log['created_at'])) ?>
                                    </span><br>
                                    <span style="font-size:0.75rem; color:var(--text-muted);">
                                        <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="action-chip" style="<?= $style ?>">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($log['actor_id']): ?>
                                        <a class="user-link" href="view_user.php?id=<?= $log['actor_id'] ?>">
                                            <?= htmlspecialchars($log['actor_name'] ?? '—') ?>
                                        </a>
                                        <span class="role-pill"><?= htmlspecialchars($log['actor_role'] ?? '') ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-style:italic; font-size:0.8rem;">System / Guest</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log['target_name']): ?>
                                        <?= htmlspecialchars($log['target_name']) ?>
                                        <?php if ($log['target_role']): ?>
                                            <span class="role-pill"><?= htmlspecialchars($log['target_role']) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:0.8rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="details-cell">
                                    <?php if ($display_details): ?>
                                        <code id="dc-<?= $log['log_id'] ?>"
                                              <?= $is_long ? "onclick=\"this.classList.toggle('expanded')\"" : '' ?>
                                              title="<?= $is_long ? 'Click to expand' : '' ?>">
                                            <?= htmlspecialchars($display_details) ?>
                                        </code>
                                        <?php if ($is_long): ?>
                                            <span style="font-size:0.7rem; color:var(--info-color); cursor:pointer;"
                                                  onclick="document.getElementById('dc-<?= $log['log_id'] ?>').classList.toggle('expanded')">
                                                [expand]
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:0.8rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="ip-pill"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
                <div style="margin-top: 16px; display: flex; justify-content: center;">
                    <?= render_pagination($pagination, 'audit_logs.php') ?>
                </div>
            <?php endif; ?>
        </div>

        <?php elseif ($tab === 'exam_workflow'): ?>
        <!-- ═══════════════════════════════════════════════
             TAB 2: EXAM WORKFLOW LOG
             ═══════════════════════════════════════════════ -->
        <div class="card">
            <div style="margin-bottom:16px;">
                <h3 style="margin:0;">Examination Workflow Log
                    <span style="font-size:0.8rem; color:var(--text-muted); font-weight:400; margin-left:8px;">
                        (<?= number_format($total_exam_logs) ?> events)
                    </span>
                </h3>
                <p style="font-size:0.85rem; color:var(--text-muted); margin:6px 0 0;">
                    Tracks every state change in the JCE/MSCE examination paper creation and approval pipeline.
                </p>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Timestamp</th>
                            <th>Exam</th>
                            <th>Action</th>
                            <th>Performed By</th>
                            <th>Role</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($exam_logs)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-log">
                                        <div class="icon">NIL</div>
                                        <h4>No Exam Workflow Events</h4>
                                        <p>No examination workflow actions have been recorded yet.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($exam_logs as $idx => $el): ?>
                            <tr>
                                <td style="color:var(--text-muted); font-size:0.75rem; font-weight:700;"><?= $offset_e + $idx + 1 ?></td>
                                <td>
                                    <span style="font-size:0.8rem; font-weight:600;">
                                        <?= date('d M Y', strtotime($el['timestamp'])) ?>
                                    </span><br>
                                    <span style="font-size:0.75rem; color:var(--text-muted);">
                                        <?= date('H:i:s', strtotime($el['timestamp'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight:600; color:var(--primary-dark);">
                                        <?= htmlspecialchars($el['exam_name'] ?? 'Exam #' . $el['exam_id']) ?>
                                    </span>
                                    <?php if ($el['year']): ?>
                                        <span class="role-pill"><?= $el['year'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php $cat = action_category($el['action']); ?>
                                    <span class="action-chip" style="<?= action_badge($cat) ?>">
                                        <?= htmlspecialchars($el['action']) ?>
                                    </span>
                                </td>
                                <td style="font-weight:600;"><?= htmlspecialchars($el['performer_name'] ?? '—') ?></td>
                                <td>
                                    <span class="role-pill" style="background:#f3e8ff; color:#6b21a8;">
                                        <?= htmlspecialchars($el['role'] ?? '—') ?>
                                    </span>
                                </td>
                                <td class="details-cell">
                                    <?= htmlspecialchars($el['notes'] ?? '—') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <?php if ($pagination_exam && $pagination_exam['total_pages'] > 1): ?>
                <div style="margin-top: 16px; display: flex; justify-content: center;">
                    <?= render_pagination($pagination_exam, 'audit_logs.php') ?>
                </div>
            <?php endif; ?>
        </div>

        <?php elseif ($tab === 'result_workflow'): ?>
        <!-- ═══════════════════════════════════════════════
             TAB 3: RESULT WORKFLOW LOG
             ═══════════════════════════════════════════════ -->
        <div class="card">
            <div style="margin-bottom:16px;">
                <h3 style="margin:0;">Results Approval Workflow Log
                    <span style="font-size:0.8rem; color:var(--text-muted); font-weight:400; margin-left:8px;">
                        (<?= number_format($total_result_logs) ?> events)
                    </span>
                </h3>
                <p style="font-size:0.85rem; color:var(--text-muted); margin:6px 0 0;">
                    Tracks every status transition in the results compilation, verification, and publication chain (EO → HT → EDM → Published).
                </p>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Timestamp</th>
                            <th>Student</th>
                            <th>Action</th>
                            <th>Status Change</th>
                            <th>Performed By</th>
                            <th>Role</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($result_logs)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-log">
                                        <div class="icon">NIL</div>
                                        <h4>No Result Workflow Events</h4>
                                        <p>No result approval or rejection events have been recorded yet.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($result_logs as $idx => $rl): ?>
                            <tr>
                                <td style="color:var(--text-muted); font-size:0.75rem; font-weight:700;"><?= $offset_r + $idx + 1 ?></td>
                                <td>
                                    <span style="font-size:0.8rem; font-weight:600;">
                                        <?= date('d M Y', strtotime($rl['created_at'])) ?>
                                    </span><br>
                                    <span style="font-size:0.75rem; color:var(--text-muted);">
                                        <?= date('H:i:s', strtotime($rl['created_at'])) ?>
                                    </span>
                                </td>
                                <td style="font-weight:600;">
                                    <?= htmlspecialchars($rl['student_name'] ?? 'Result #' . $rl['result_id']) ?>
                                </td>
                                <td>
                                    <?php $cat = action_category($rl['action']); ?>
                                    <span class="action-chip" style="<?= action_badge($cat) ?>">
                                        <?= htmlspecialchars($rl['action']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($rl['from_status'] || $rl['to_status']): ?>
                                        <div class="arrow-chip">
                                            <span class="status-from"><?= htmlspecialchars($rl['from_status'] ?? '—') ?></span>
                                            <span style="color:var(--text-muted);">→</span>
                                            <span class="status-to"><?= htmlspecialchars($rl['to_status'] ?? '—') ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight:600;"><?= htmlspecialchars($rl['performer_name'] ?? '—') ?></td>
                                <td>
                                    <span class="role-pill" style="background:#e0f2fe; color:#0369a1;">
                                        <?= htmlspecialchars($rl['role'] ?? '—') ?>
                                    </span>
                                </td>
                                <td class="details-cell">
                                    <?= htmlspecialchars($rl['notes'] ?? '—') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <?php if ($pagination_result && $pagination_result['total_pages'] > 1): ?>
                <div style="margin-top: 16px; display: flex; justify-content: center;">
                    <?= render_pagination($pagination_result, 'audit_logs.php') ?>
                </div>
            <?php endif; ?>
        </div>

        <?php endif; ?>

    </div><!-- /content -->
</div><!-- /dashboard -->

<?php include '../common/footer.php'; ?>

<script>
/* ── Live search debounce ── */
(function () {
    const input = document.getElementById('searchInput');
    if (!input) return;
    let timer;
    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            document.getElementById('filterForm').submit();
        }, 500);
    });
})();
</script>

</body>
</html>
