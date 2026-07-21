<?php
/* ════════════════════════════════════════════════════════════════
   admin/reports/compliance_report.php
   EDM/Admin: Examination Readiness & Compliance Intelligence Dashboard
   ────────────────────────────────────────────────────────────────
   Monitors and tracks:
   1. Question paper compilation & moderation completion
   2. Teacher marking assignment submission progress
   3. JCE & MSCE printing readiness & compliance checklists
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../common/report_stats.php';
require_once __DIR__ . '/../../common/report_charts.php';
require_once __DIR__ . '/../../common/pagination_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

$conn = get_db_connection();

/* ════════════════════════════════════════════════════════════════
   COMPLIANCE CORE METRICS
   ════════════════════════════════════════════════════════════════ */
$total_exams  = (int)($conn->query("SELECT COUNT(*) as c FROM exams")->fetch_assoc()['c'] ?? 0);
$active_exams = (int)($conn->query("SELECT COUNT(*) as c FROM exams WHERE status='active'")->fetch_assoc()['c'] ?? 0);
$draft_exams  = (int)($conn->query("SELECT COUNT(*) as c FROM exams WHERE status='draft'")->fetch_assoc()['c'] ?? 0);

$total_assignments     = (int)($conn->query("SELECT COUNT(*) as c FROM marking_assignments")->fetch_assoc()['c'] ?? 0);
$submitted_assignments = (int)($conn->query("SELECT COUNT(*) as c FROM marking_assignments WHERE status IN ('submitted', 'completed')")->fetch_assoc()['c'] ?? 0);
$marking_compliance_pct = $total_assignments > 0 ? round(($submitted_assignments / $total_assignments) * 100, 1) : 0;

$total_questions        = (int)($conn->query("SELECT COUNT(*) as c FROM questions")->fetch_assoc()['c'] ?? 0);
$approved_questions     = (int)($conn->query("SELECT COUNT(*) as c FROM questions WHERE moderation_status='approved'")->fetch_assoc()['c'] ?? 0);
$question_compliance_pct = $total_questions > 0 ? round(($approved_questions / $total_questions) * 100, 1) : 0;

$overall_readiness_pct = round(($marking_compliance_pct * 0.5) + ($question_compliance_pct * 0.5), 1);

// Dropdowns for filtering
$exams_list = $conn->query("SELECT exam_id, exam_name, class, year FROM exams ORDER BY year DESC, start_date DESC")->fetch_all(MYSQLI_ASSOC);

$selected_exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$filter_status    = trim($_GET['status'] ?? '');
$search_query     = trim($_GET['search'] ?? '');
$current_page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page         = 10;

/* ════════════════════════════════════════════════════════════════
   EXAM SUBJECT PAPERS QUERY & COMPLIANCE FILTERING
   ════════════════════════════════════════════════════════════════ */
$where_parts = ["es.status = 'active'"];
$bind_types  = "";
$bind_values = [];

if ($selected_exam_id > 0) {
    $where_parts[] = "es.exam_id = ?";
    $bind_types   .= "i";
    $bind_values[] = $selected_exam_id;
}
if ($search_query !== '') {
    $where_parts[] = "(s.subject_name LIKE ? OR s.subject_code LIKE ? OR e.exam_name LIKE ?)";
    $bind_types   .= "sss";
    $bind_values[] = "%{$search_query}%";
    $bind_values[] = "%{$search_query}%";
    $bind_values[] = "%{$search_query}%";
}

$where_sql = implode(" AND ", $where_parts);

$all_papers_sql = "
    SELECT
        es.id            AS exam_subject_id,
        es.exam_id,
        e.exam_name,
        e.year,
        e.status         AS exam_status,
        s.subject_name,
        s.subject_code,
        (SELECT COUNT(*) FROM questions q WHERE q.exam_subject_id = es.id) AS total_q,
        (SELECT COUNT(*) FROM questions q WHERE q.exam_subject_id = es.id AND q.moderation_status = 'approved') AS approved_q,
        (SELECT COUNT(*) FROM questions q WHERE q.exam_subject_id = es.id AND q.moderation_status = 'revise') AS revise_q,
        (SELECT COUNT(*) FROM questions q WHERE q.exam_subject_id = es.id AND q.moderation_status = 'rejected') AS rejected_q,
        (SELECT COUNT(*) FROM questions q WHERE q.exam_subject_id = es.id AND q.moderation_status = 'pending') AS pending_q
    FROM exam_subjects es
    JOIN exams e ON es.exam_id = e.exam_id
    JOIN subjects s ON es.subject_id = s.subject_id
    WHERE {$where_sql}
    ORDER BY e.year DESC, e.exam_name ASC, s.subject_name ASC
";

if (!empty($bind_types)) {
    $stmt = $conn->prepare($all_papers_sql);
    $stmt->bind_param($bind_types, ...$bind_values);
    $stmt->execute();
    $all_papers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $all_papers = $conn->query($all_papers_sql)->fetch_all(MYSQLI_ASSOC);
}

// Calculate percentages and status
foreach ($all_papers as &$p) {
    $total    = (int)$p['total_q'];
    $approved = (int)$p['approved_q'];
    $p['pct'] = $total > 0 ? round(($approved / $total) * 100) : 0;
    
    if ($total === 0) {
        $p['status_slug'] = 'not_started';
        $p['status_text'] = 'Not Started';
    } elseif ($p['pct'] === 100) {
        $p['status_slug'] = 'compliant';
        $p['status_text'] = 'Compliant';
    } else {
        $p['status_slug'] = 'in_progress';
        $p['status_text'] = 'In Progress';
    }
}
unset($p);

// Filter by Status slug
if ($filter_status !== '') {
    $all_papers = array_values(array_filter($all_papers, fn($p) => $p['status_slug'] === $filter_status));
}

$total_records = count($all_papers);
$pagination    = paginate($total_records, $current_page, $per_page);
$offset        = ($pagination['page'] - 1) * $per_page;
$papers_list   = array_slice($all_papers, $offset, $per_page);

$conn->close();

$portal_title = 'NED-SEMS | Readiness & Compliance Report';
$module_css   = 'admin';
include __DIR__ . '/../../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Examination Readiness &amp; Compliance Report | NED-SEMS</title>
    <meta name="description" content="Monitors item writing moderation completion, paper draft progress, and teacher marking assignment compliance">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
    <style>
      .compliance-layout {
        display: grid;
        grid-template-columns: 2.2fr 1fr;
        gap: 24px;
      }
      @media (max-width: 1024px) {
        .compliance-layout { grid-template-columns: 1fr; }
      }
      .action-card {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 10px;
        padding: 20px;
        color: #1e40af;
      }
      .action-card h4 {
        color: #1e3a8a;
        margin: 0 0 12px 0;
        font-size: 0.95rem;
        font-weight: 700;
      }
      .action-card ul {
        padding-left: 18px;
        margin: 0;
      }
      .action-card li {
        margin-bottom: 10px;
        font-size: 0.82rem;
        line-height: 1.5;
      }
      .progress-indicator {
        display: flex;
        align-items: center;
        gap: 10px;
      }
      .progress-bar-small {
        flex: 1;
        height: 8px;
        background: #f1f5f9;
        border-radius: 4px;
        overflow: hidden;
      }
      .progress-bar-fill {
        height: 100%;
        background: #2563eb;
      }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../common/header.php'; ?>

<div class="dashboard">
    <?php include __DIR__ . '/../../common/sidebar.php'; ?>

    <div class="content">

        <!-- Print Header -->
        <div class="print-header">
            <h2 class="print-title">Examination Readiness &amp; Compliance Report</h2>
            <div class="print-meta">Generated: <?= date('Y-m-d H:i') ?> | Northern Education Division | Administrator</div>
        </div>

        <!-- Header -->
        <div class="page-header">
            <div>
                <h2 class="page-title">Examination Readiness &amp; Compliance</h2>
                <p class="page-subtitle">Monitors question paper item writing, moderation completion, and teacher marking assignment logs</p>
            </div>
            <div class="header-actions">
                <button onclick="window.print()" class="btn btn-secondary">Print Report</button>
            </div>
        </div>

        <!-- ═══ FILTER PANEL ═══ -->
        <div class="rpt-filter-panel no-print">
            <form method="GET" class="rpt-filter-form">
                <!-- Exam Filter -->
                <div class="rpt-filter-group">
                    <label class="rpt-filter-label" for="cf-exam">Target Examination</label>
                    <select name="exam_id" id="cf-exam" class="rpt-filter-select" onchange="this.form.submit()">
                        <option value="0">All Examinations</option>
                        <?php foreach ($exams_list as $ex): ?>
                            <option value="<?= $ex['exam_id'] ?>" <?= $selected_exam_id === (int)$ex['exam_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ex['exam_name']) ?> (<?= htmlspecialchars($ex['year']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Compliance Status Filter -->
                <div class="rpt-filter-group">
                    <label class="rpt-filter-label" for="cf-status">Paper Status</label>
                    <select name="status" id="cf-status" class="rpt-filter-select" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="compliant"   <?= $filter_status === 'compliant'   ? 'selected' : '' ?>>Compliant (100% Moderated)</option>
                        <option value="in_progress" <?= $filter_status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="not_started" <?= $filter_status === 'not_started' ? 'selected' : '' ?>>Not Started</option>
                    </select>
                </div>

                <!-- Search Input -->
                <div class="rpt-filter-group">
                    <label class="rpt-filter-label" for="cf-search">Search Paper</label>
                    <input type="text" name="search" id="cf-search" class="rpt-filter-input" placeholder="Subject name or code..." value="<?= htmlspecialchars($search_query) ?>">
                </div>

                <div class="rpt-filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="compliance_report.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- ═══ KPI SUMMARY CARDS ═══ -->
        <div class="rpt-kpi-grid">
            <div class="rpt-kpi-card rpt-kpi--blue">
                <span class="rpt-kpi-label">Active Examination Papers</span>
                <span class="rpt-kpi-value"><?= $active_exams ?> <span style="font-size:1rem; color:#64748b;">/ <?= $total_exams ?></span></span>
                <span class="rpt-kpi-sub"><?= $draft_exams ?> papers in draft state</span>
            </div>
            <div class="rpt-kpi-card rpt-kpi--teal">
                <span class="rpt-kpi-label">Moderation Compliance</span>
                <span class="rpt-kpi-value" style="color: #2563eb;"><?= $question_compliance_pct ?>%</span>
                <span class="rpt-kpi-sub"><?= number_format($approved_questions) ?> of <?= number_format($total_questions) ?> items approved</span>
            </div>
            <div class="rpt-kpi-card rpt-kpi--green">
                <span class="rpt-kpi-label">Marking Compliance</span>
                <span class="rpt-kpi-value" style="color: #16a34a;"><?= $marking_compliance_pct ?>%</span>
                <span class="rpt-kpi-sub"><?= number_format($submitted_assignments) ?> of <?= number_format($total_assignments) ?> marksheets submitted</span>
            </div>
            <div class="rpt-kpi-card rpt-kpi--purple">
                <span class="rpt-kpi-label">Overall Readiness Index</span>
                <span class="rpt-kpi-value" style="color: #7c3aed;"><?= $overall_readiness_pct ?>%</span>
                <span class="rpt-kpi-sub">Composite readiness score</span>
            </div>
        </div>

        <!-- ═══ COMPLIANCE LAYOUT (TABLE + CHECKLIST) ═══ -->
        <div class="compliance-layout">

            <!-- PAPER COMPILATION STANDINGS -->
            <div class="card" style="margin: 0;">
                <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <div>
                        <h3 style="margin: 0;">Paper Compilation &amp; Moderation Standings</h3>
                        <p style="font-size: 0.82rem; color: var(--text-muted); margin: 4px 0 0 0;">
                            Displaying <strong><?= count($papers_list) ?></strong> of <strong><?= $total_records ?></strong> registered subject papers
                        </p>
                    </div>
                    <?php if ($pagination && $pagination['total_pages'] > 1): ?>
                        <span style="font-size: 0.82rem; color: var(--text-muted);">Page <?= $pagination['page'] ?> of <?= $pagination['total_pages'] ?></span>
                    <?php endif; ?>
                </div>

                <div class="rpt-table-wrap">
                    <table class="rpt-table">
                        <thead>
                            <tr>
                                <th>Exam (Year)</th>
                                <th>Subject Paper</th>
                                <th class="val-col">Total Q</th>
                                <th class="val-col">Approved</th>
                                <th class="val-col">Pending</th>
                                <th class="val-col">Revise</th>
                                <th style="min-width: 140px;">Completion Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($papers_list)): ?>
                                <tr>
                                    <td colspan="7" class="empty-state">No examination subject papers found matching your filter criteria.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($papers_list as $paper): ?>
                                    <?php
                                        $pct      = $paper['pct'];
                                        $badge_bg = $pct === 100 ? '#f0fdf4' : ($pct > 0 ? '#fefce8' : '#f8fafc');
                                        $badge_fg = $pct === 100 ? '#16a34a' : ($pct > 0 ? '#ca8a04' : '#64748b');
                                        $badge_bd = $pct === 100 ? '#bbf7d0' : ($pct > 0 ? '#fef08a' : '#e2e8f0');
                                    ?>
                                    <tr>
                                        <td style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($paper['exam_name']) ?> (<?= htmlspecialchars($paper['year']) ?>)</td>
                                        <td style="font-weight: 700; color: var(--info-color);"><?= htmlspecialchars($paper['subject_name']) ?> (<?= htmlspecialchars($paper['subject_code']) ?>)</td>
                                        <td class="val-col"><?= $paper['total_q'] ?></td>
                                        <td class="val-col" style="font-weight: 800; color: #16a34a;"><?= $paper['approved_q'] ?></td>
                                        <td class="val-col" style="font-weight: 700; color: #6366f1;"><?= $paper['pending_q'] ?></td>
                                        <td class="val-col" style="font-weight: 700; color: #dc2626;"><?= $paper['revise_q'] ?></td>
                                        <td>
                                            <div class="progress-indicator">
                                                <div class="progress-bar-small">
                                                    <div class="progress-bar-fill" style="width: <?= $pct ?>%; background: <?= $pct === 100 ? '#16a34a' : '#2563eb' ?>;"></div>
                                                </div>
                                                <span style="font-size: 0.8rem; font-weight: 800; min-width: 32px; text-align: right;"><?= $pct ?>%</span>
                                            </div>
                                            <div style="margin-top: 4px;">
                                                <span style="font-size: 0.72rem; padding: 2px 8px; border-radius: 4px; font-weight: 700; background: <?= $badge_bg ?>; color: <?= $badge_fg ?>; border: 1px solid <?= $badge_bd ?>;">
                                                    <?= htmlspecialchars($paper['status_text']) ?>
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination controls -->
                <?php if ($pagination && $pagination['total_pages'] > 1): ?>
                    <div style="margin-top: 16px;">
                        <?= render_pagination($pagination, 'compliance_report.php') ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- COMPLIANCE ACTION GUIDELINES -->
            <div class="action-card">
                <h4>Divisional Compliance Guidelines</h4>
                <ul>
                    <li><strong>Deadline Monitoring:</strong> Verify printing schedules for JCE and MSCE papers. All question items must reach 100% moderation approval at least 4 weeks prior to exam start.</li>
                    <li><strong>Revision Requests:</strong> Contact Item Writers immediately for subject papers showing pending revision requests.</li>
                    <li><strong>Examiner Lock Toggling:</strong> Locked examiners cannot submit marksheets. For late entry requests, navigate to the Assignment Manager to adjust access privileges.</li>
                    <li><strong>Printing Authorization:</strong> Papers are certified ready for secure printing only when moderation status reaches 100% Compliant.</li>
                </ul>
            </div>

        </div><!-- /compliance-layout -->

    </div>
</div>

<?php include __DIR__ . '/../../common/footer.php'; ?>

</body>
</html>