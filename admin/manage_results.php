<?php
session_start();
require_once '../config/db.php';
require_once '../common/pagination_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

/* ═══════════════════════════════════════
   FILTERS
═══════════════════════════════════════ */
$exam_id     = isset($_GET['exam_id'])  ? (int)$_GET['exam_id']             : 0;
$filter_class = trim($_GET['class']    ?? '');
$status_f    = trim($_GET['status']    ?? '');
$search      = trim($_GET['search']    ?? '');

/* ═══════════════════════════════════════
   PAGINATION — 5 per page
═══════════════════════════════════════ */
$per_page = 5;
$page     = max(1, (int)($_GET['page'] ?? 1));

/* ═══════════════════════════════════════
   KPI STATS
═══════════════════════════════════════ */
$kpi = $conn->query("
    SELECT
        COUNT(*)                                                          AS total_results,
        SUM(status = 'draft')                                             AS draft_count,
        SUM(status = 'published')                                         AS published_count,
        SUM(status IN ('approved','eo_approved','head_approved','edm_approved')) AS approved_count,
        ROUND(AVG(average_score), 1)                                      AS avg_score,
        SUM(locked = 1)                                                   AS locked_count
    FROM results
")->fetch_assoc();

/* ═══════════════════════════════════════
   EXAM & CLASS DROPDOWNS
═══════════════════════════════════════ */
$exams = $conn->query("SELECT exam_id, exam_name FROM exams ORDER BY exam_id DESC");

$classes_res = $conn->query("
    SELECT DISTINCT s.class
    FROM results r
    JOIN students s ON s.student_id = r.student_id
    ORDER BY s.class
");

/* ═══════════════════════════════════════
   WHERE CLAUSE
═══════════════════════════════════════ */
$where_parts = ["1=1"];
$bind_params = [];
$bind_types  = "";

if ($exam_id > 0) {
    $where_parts[] = "r.exam_id = ?";
    $bind_params[] = $exam_id;
    $bind_types   .= "i";
}
if ($filter_class !== '') {
    $where_parts[] = "s.class = ?";
    $bind_params[] = $filter_class;
    $bind_types   .= "s";
}
if ($status_f !== '') {
    $where_parts[] = "r.status = ?";
    $bind_params[] = $status_f;
    $bind_types   .= "s";
}
if ($search !== '') {
    $where_parts[] = "s.name LIKE ?";
    $bind_params[] = "%$search%";
    $bind_types   .= "s";
}

$where_sql = implode(" AND ", $where_parts);

/* ═══════════════════════════════════════
   COUNT
═══════════════════════════════════════ */
$count_stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM results r
    JOIN students s ON s.student_id = r.student_id
    JOIN exams e    ON e.exam_id    = r.exam_id
    WHERE $where_sql
");
if ($bind_params) $count_stmt->bind_param($bind_types, ...$bind_params);
$count_stmt->execute();
$total_filtered = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = max(1, (int)ceil($total_filtered / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

/* ═══════════════════════════════════════
   RESULTS QUERY
═══════════════════════════════════════ */
$bind_params_paged = $bind_params;
$bind_types_paged  = $bind_types . "ii";
$bind_params_paged[] = $per_page;
$bind_params_paged[] = $offset;

$stmt = $conn->prepare("
    SELECT
        r.result_id,
        r.total_score,
        r.average_score,
        r.grade,
        r.position_in_class,
        r.total_subjects,
        r.status,
        r.locked,
        r.compiled_at,
        r.year,
        r.term,
        s.name  AS student_name,
        s.class AS student_class,
        e.exam_name
    FROM results r
    JOIN students s ON s.student_id = r.student_id
    JOIN exams e    ON e.exam_id    = r.exam_id
    WHERE $where_sql
    ORDER BY r.position_in_class ASC, r.total_score DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param($bind_types_paged, ...$bind_params_paged);
$stmt->execute();
$results_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

/* ═══════════════════════════════════════
   HELPERS
═══════════════════════════════════════ */
function grade_colour(string $g): string {
    return match(true) {
        in_array($g, ['A','A1'])              => '#16a34a',
        in_array($g, ['B','B2','B3'])         => '#2563eb',
        in_array($g, ['C','C4','C5','C6'])    => '#7c3aed',
        in_array($g, ['D','D7'])              => '#d97706',
        in_array($g, ['E','E8'])              => '#ea580c',
        in_array($g, ['F','F9','P','P7','P8'])=> '#dc2626',
        default                               => '#6b7280',
    };
}

function status_pill(string $s): string {
    return match($s) {
        'published'    => '<span class="spill spill-pub">Published</span>',
        'approved',
        'eo_approved',
        'head_approved',
        'edm_approved' => '<span class="spill spill-app">Approved</span>',
        'submitted'    => '<span class="spill spill-sub">Submitted</span>',
        'draft'        => '<span class="spill spill-dft">Draft</span>',
        default        => '<span class="spill spill-dft">' . htmlspecialchars(ucfirst($s)) . '</span>',
    };
}

function pg_url(int $p, array $params): string {
    $params['page'] = $p;
    return 'manage_results.php?' . http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== 0));
}

$filter_carry = [
    'exam_id' => $exam_id ?: '',
    'class'   => $filter_class,
    'status'  => $status_f,
    'search'  => $search,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Results | NED-SEMS</title>
<?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>
<style>
/* ── KPI bar ── */
.kpi-strip {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 14px;
    margin-bottom: 24px;
}
@media (max-width: 1200px) { .kpi-strip { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 600px)  { .kpi-strip { grid-template-columns: repeat(2, 1fr); } }

.kpi-card {
    background: var(--card-color);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 16px 18px;
    box-shadow: var(--box-shadow);
    border-top: 3px solid transparent;
    transition: transform .15s;
}
.kpi-card:hover { transform: translateY(-2px); }
.kpi-card.blue   { border-top-color: #3b82f6; }
.kpi-card.green  { border-top-color: #22c55e; }
.kpi-card.violet { border-top-color: #8b5cf6; }
.kpi-card.amber  { border-top-color: #f59e0b; }
.kpi-card.teal   { border-top-color: #14b8a6; }
.kpi-card.red    { border-top-color: #ef4444; }

.kpi-label {
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
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
    font-size: .72rem;
    color: var(--text-muted);
    margin-top: 4px;
}

/* ── Search / filter bar ── */
.filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    padding: 16px 20px;
    background: var(--card-color);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    margin-bottom: 18px;
    box-shadow: var(--box-shadow);
}
.filter-bar input[type="text"] {
    flex: 1;
    min-width: 180px;
}
.filter-bar select, .filter-bar input[type="text"] {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: .85rem;
    color: var(--text-color);
    background: var(--background-color);
}
.filter-bar select:focus, .filter-bar input[type="text"]:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(59,130,246,.15);
}

/* ── Table ── */
.results-table-wrap {
    background: var(--card-color);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: var(--box-shadow);
}
.table-top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px;
    border-bottom: 1px solid var(--border-color);
    background: #f8fafc;
}
.table-top-bar h3 {
    font-size: .9rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}
.showing-meta {
    font-size: .78rem;
    color: var(--text-muted);
}

.res-table {
    width: 100%;
    border-collapse: collapse;
}
.res-table th {
    padding: 10px 16px;
    text-align: left;
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--text-muted);
    background: #f8fafc;
    border-bottom: 1px solid var(--border-color);
    white-space: nowrap;
}
.res-table td {
    padding: 12px 16px;
    font-size: .86rem;
    color: var(--text-color);
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.res-table tr:last-child td { border-bottom: none; }
.res-table tr:hover td { background: #f8faff; }

.rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    font-size: .72rem;
    font-weight: 700;
}
.rank-1 { background: #fef9c3; color: #854d0e; }
.rank-2 { background: #f1f5f9; color: #475569; }
.rank-3 { background: #fff7ed; color: #9a3412; }
.rank-n { background: #f1f5f9; color: #64748b; }

.student-name {
    font-weight: 600;
    color: #0f172a;
}
.student-class {
    font-size: .72rem;
    color: var(--text-muted);
    margin-top: 2px;
}

.score-bar-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 130px;
}
.score-bar-bg {
    flex: 1;
    height: 6px;
    background: #e2e8f0;
    border-radius: 99px;
    overflow: hidden;
}
.score-bar-fill {
    height: 100%;
    border-radius: 99px;
    transition: width .4s;
}
.score-text {
    font-size: .78rem;
    font-weight: 700;
    white-space: nowrap;
    min-width: 38px;
    text-align: right;
}

.grade-chip {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: .75rem;
    font-weight: 800;
    letter-spacing: .04em;
    color: #fff;
}

.exam-pill {
    display: inline-block;
    padding: 2px 9px;
    border-radius: 99px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: .72rem;
    font-weight: 600;
}

/* Status pills */
.spill {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 99px;
    font-size: .72rem;
    font-weight: 700;
}
.spill-pub { background: #dcfce7; color: #15803d; }
.spill-app { background: #dbeafe; color: #1d4ed8; }
.spill-sub { background: #f3e8ff; color: #7e22ce; }
.spill-dft { background: #f1f5f9; color: #64748b; }

.lock-icon { font-size: .8rem; color: #dc2626; }

/* ── Pagination ── */
.pagination-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    padding: 14px 20px;
    border-top: 1px solid var(--border-color);
}
.pag-info { font-size: .78rem; color: var(--text-muted); }
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
    font-size: .8rem;
    font-weight: 600;
    text-decoration: none;
    transition: background .15s, border-color .15s, color .15s;
}
.pag-btn:hover   { background: #f1f5f9; border-color: #94a3b8; }
.pag-btn.active  { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }
.pag-btn.disabled{ opacity: .4; pointer-events: none; }

/* ── Workflow card ── */
.workflow-card {
    background: var(--card-color);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 22px 24px;
    margin-top: 20px;
    box-shadow: var(--box-shadow);
}
.workflow-steps {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 14px;
}
.wf-step {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 8px;
    font-size: .8rem;
    font-weight: 600;
}
.wf-step.draft    { background: #f1f5f9; color: #475569; }
.wf-step.sub      { background: #f3e8ff; color: #7e22ce; }
.wf-step.approved { background: #dbeafe; color: #1d4ed8; }
.wf-step.pub      { background: #dcfce7; color: #15803d; }
.wf-arrow { color: #cbd5e1; font-size: 1rem; }

/* ── Empty state ── */
.empty-state-row td {
    text-align: center;
    padding: 50px 20px;
    color: var(--text-muted);
}
.empty-icon { font-size: 2.5rem; margin-bottom: 10px; }
.empty-state-row h4 { font-size: .95rem; color: #0f172a; margin-bottom: 4px; }
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
                <h2 class="page-title">Results Dashboard</h2>
                <p class="page-subtitle">Centralized EDM results management and publication centre</p>
            </div>
            <div class="header-actions">
                <a href="compile_results.php" class="btn btn-secondary">Compile Results</a>
                <a href="publish_results.php" class="btn btn-dark">Publish Results</a>
            </div>
        </div>

        <!-- KPI STRIP -->
        <div class="kpi-strip">
            <div class="kpi-card blue">
                <div class="kpi-label">Total Results</div>
                <div class="kpi-value"><?= number_format($kpi['total_results']) ?></div>
                <div class="kpi-sub">All records</div>
            </div>
            <div class="kpi-card amber">
                <div class="kpi-label">Draft</div>
                <div class="kpi-value"><?= number_format($kpi['draft_count']) ?></div>
                <div class="kpi-sub">Awaiting compilation</div>
            </div>
            <div class="kpi-card violet">
                <div class="kpi-label">Approved</div>
                <div class="kpi-value"><?= number_format($kpi['approved_count']) ?></div>
                <div class="kpi-sub">Cleared for release</div>
            </div>
            <div class="kpi-card green">
                <div class="kpi-label">Published</div>
                <div class="kpi-value"><?= number_format($kpi['published_count']) ?></div>
                <div class="kpi-sub">Officially released</div>
            </div>
            <div class="kpi-card teal">
                <div class="kpi-label">Avg Score</div>
                <div class="kpi-value"><?= $kpi['avg_score'] ?? '—' ?>%</div>
                <div class="kpi-sub">Across all results</div>
            </div>
            <div class="kpi-card red">
                <div class="kpi-label">Locked</div>
                <div class="kpi-value"><?= number_format($kpi['locked_count']) ?></div>
                <div class="kpi-sub">Read-only records</div>
            </div>
        </div>

        <!-- FILTER BAR -->
        <form method="GET" class="filter-bar">
            <input type="text" name="search"
                   placeholder="Search student name…"
                   value="<?= htmlspecialchars($search) ?>">

            <select name="exam_id">
                <option value="">All Exams</option>
                <?php while ($ex = $exams->fetch_assoc()): ?>
                    <option value="<?= $ex['exam_id'] ?>"
                            <?= $exam_id === (int)$ex['exam_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ex['exam_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <select name="class">
                <option value="">All Classes</option>
                <?php while ($cl = $classes_res->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($cl['class']) ?>"
                            <?= $filter_class === $cl['class'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cl['class']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <select name="status">
                <option value="">All Statuses</option>
                <?php foreach ([
                    'draft'        => 'Draft',
                    'submitted'    => 'Submitted',
                    'approved'     => 'Approved',
                    'eo_approved'  => 'EO Approved',
                    'head_approved'=> 'Head Approved',
                    'edm_approved' => 'EDM Approved',
                    'published'    => 'Published',
                ] as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $status_f === $val ? 'selected' : '' ?>>
                        <?= $label ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-dark">Filter</button>
            <a href="manage_results.php" class="btn btn-secondary">Reset</a>
        </form>

        <!-- RESULTS TABLE -->
        <div class="results-table-wrap">

            <div class="table-top-bar">
                <h3>Student Results
                    <?php if ($total_filtered > 0): ?>
                        <span style="font-weight:400;color:#64748b;font-size:.8rem;margin-left:6px;">
                            — <?= number_format($total_filtered) ?> record<?= $total_filtered !== 1 ? 's' : '' ?> found
                        </span>
                    <?php endif; ?>
                </h3>
                <?php if ($total_filtered > 0): ?>
                    <span class="showing-meta">
                        Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_filtered) ?>
                        of <?= number_format($total_filtered) ?>
                    </span>
                <?php endif; ?>
            </div>

            <div style="overflow-x:auto;">
                <table class="res-table">
                    <thead>
                        <tr>
                            <th style="width:50px">#</th>
                            <th>Student</th>
                            <th>Examination</th>
                            <th>Score / Avg</th>
                            <th>Performance</th>
                            <th style="text-align:center">Grade</th>
                            <th style="text-align:center">Rank</th>
                            <th style="text-align:center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($results_data)): ?>
                        <tr class="empty-state-row">
                            <td colspan="8">
                                <h4>No results found</h4>
                                <p>Try adjusting your filters or reset to see all records.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($results_data as $i => $row):
                            $pct  = (float)($row['average_score'] ?? 0);
                            $bar_color = $pct >= 70 ? '#22c55e' : ($pct >= 50 ? '#3b82f6' : ($pct >= 40 ? '#f59e0b' : '#ef4444'));
                            $grade = $row['grade'] ?: '—';
                            $pos   = (int)($row['position_in_class'] ?? 0);
                            $rank_class = match($pos) { 1 => 'rank-1', 2 => 'rank-2', 3 => 'rank-3', default => 'rank-n' };
                        ?>
                        <tr>
                            <td style="color:#94a3b8;font-size:.78rem;font-weight:600;">
                                <?= $offset + $i + 1 ?>
                            </td>
                            <td>
                                <div class="student-name"><?= htmlspecialchars($row['student_name']) ?></div>
                                <div class="student-class"><?= htmlspecialchars($row['student_class']) ?></div>
                            </td>
                            <td>
                                <span class="exam-pill"><?= htmlspecialchars($row['exam_name']) ?></span>
                                <?php if ($row['year']): ?>
                                    <div style="font-size:.7rem;color:#94a3b8;margin-top:3px;">
                                        <?= htmlspecialchars($row['term'] ?? '') ?>
                                        <?= htmlspecialchars($row['year']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size:.9rem;font-weight:700;color:#0f172a;">
                                    <?= number_format($row['total_score'], 1) ?>
                                </div>
                                <div style="font-size:.72rem;color:#64748b;">
                                    avg <?= number_format($pct, 1) ?>%
                                    <?php if ($row['total_subjects']): ?>
                                        · <?= $row['total_subjects'] ?> subj<?= $row['total_subjects'] > 1 ? 's' : '' ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="score-bar-wrap">
                                    <div class="score-bar-bg">
                                        <div class="score-bar-fill"
                                             style="width:<?= min(100, $pct) ?>%;background:<?= $bar_color ?>;"></div>
                                    </div>
                                    <div class="score-text" style="color:<?= $bar_color ?>">
                                        <?= number_format($pct, 1) ?>%
                                    </div>
                                </div>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($grade !== '—'): ?>
                                    <span class="grade-chip"
                                          style="background:<?= grade_colour($grade) ?>;">
                                        <?= htmlspecialchars($grade) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#94a3b8">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($pos > 0): ?>
                                    <span class="rank-badge <?= $rank_class ?>"><?= $pos ?></span>
                                <?php else: ?>
                                    <span style="color:#94a3b8">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <?= status_pill($row['status']) ?>
                                <?php if ($row['locked']): ?>
                                    <span class="lock-icon" title="Locked">[Locked]</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION INSIDE TABLE CARD -->
            <?php if ($total_pages > 1 || $total_filtered > 0): ?>
            <div class="pagination-wrap">
                <span class="pag-info">
                    Page <?= $page ?> of <?= $total_pages ?>
                    &nbsp;·&nbsp; <?= number_format($total_filtered) ?> total
                </span>
                <div class="pag-links">
                    <!-- First & Prev -->
                    <a href="<?= pg_url(1, $filter_carry) ?>"
                       class="pag-btn <?= $page <= 1 ? 'disabled' : '' ?>">«</a>
                    <a href="<?= pg_url($page - 1, $filter_carry) ?>"
                       class="pag-btn <?= $page <= 1 ? 'disabled' : '' ?>">‹ Prev</a>

                    <!-- Numbered pages -->
                    <?php
                    $range = 2;
                    for ($p = 1; $p <= $total_pages; $p++):
                        if ($p === 1 || $p === $total_pages || ($p >= $page - $range && $p <= $page + $range)):
                    ?>
                        <a href="<?= pg_url($p, $filter_carry) ?>"
                           class="pag-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                    <?php
                        elseif ($p === $page - $range - 1 || $p === $page + $range + 1):
                    ?>
                        <span class="pag-btn disabled" style="border:none;background:none;">…</span>
                    <?php
                        endif;
                    endfor;
                    ?>

                    <!-- Next & Last -->
                    <a href="<?= pg_url($page + 1, $filter_carry) ?>"
                       class="pag-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">Next ›</a>
                    <a href="<?= pg_url($total_pages, $filter_carry) ?>"
                       class="pag-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">»</a>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /results-table-wrap -->

        <!-- WORKFLOW CARD -->
        <div class="workflow-card">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                <div>
                    <h3 style="font-size:.9rem;font-weight:700;color:#0f172a;margin:0;">
                        Results Management Workflow
                    </h3>
                    <p style="font-size:.78rem;color:#64748b;margin:3px 0 0;">
                        Each result passes through the following stages before official release.
                    </p>
                </div>
            </div>
            <div class="workflow-steps">
                <div class="wf-step draft">Draft<br><small>Teachers submit marks</small></div>
                <span class="wf-arrow">&rarr;</span>
                <div class="wf-step sub">Under Review<br><small>EDM checks marks</small></div>
                <span class="wf-arrow">&rarr;</span>
                <div class="wf-step approved">Approved<br><small>Grades &amp; rankings set</small></div>
                <span class="wf-arrow">&rarr;</span>
                <div class="wf-step pub">Published<br><small>Results officially released</small></div>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /dashboard -->

<?php include '../common/footer.php'; ?>
</body>
</html>