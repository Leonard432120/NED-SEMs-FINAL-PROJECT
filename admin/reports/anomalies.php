<?php
/* ════════════════════════════════════════════════════════════════
   admin/reports/anomalies.php
   EDM/Admin: Statistical & AI Anomaly Detection Dashboard
   ────────────────────────────────────────────────────────────────
   Monitors and flags academic & administrative anomalies:
   1. Score Collusion & Fabrication (Std Dev < 2.0)
   2. Grade Inflation (Pass rate 100% / Avg > 85%)
   3. Performance Deficit (Avg < 25%)
   4. Examiner Overload (> 4 active marking workloads)
   5. AI Quality Moderation Overrules (< 50% AI Quality score approved)
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

function get_standard_deviation(array $arr): float {
    $n = count($arr);
    if ($n === 0) return 0.0;
    $avg = array_sum($arr) / $n;
    $variance = 0.0;
    foreach ($arr as $v) $variance += pow($v - $avg, 2);
    return sqrt($variance / $n);
}

// ── Dropdown Filters Data ─────────────────────────────────────────
$exams_list = $conn->query("SELECT exam_id, exam_name, class, year FROM exams ORDER BY year DESC, start_date DESC")->fetch_all(MYSQLI_ASSOC);
$districts_list = $conn->query("SELECT DISTINCT district FROM schools WHERE status='active' ORDER BY district ASC")->fetch_all(MYSQLI_ASSOC);

$selected_exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$filter_district  = trim($_GET['district'] ?? '');
$filter_severity  = trim($_GET['severity'] ?? '');
$search_query     = trim($_GET['search'] ?? '');
$current_page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page         = 6;

$anomalies = [];

// ── 1. Grade Inflation, Deficits & Collusion Detection ────────────
$where_exam = $selected_exam_id > 0 ? "AND m.exam_id = {$selected_exam_id}" : "";

$stats_query = $conn->query("
    SELECT m.exam_id, m.subject_id, st.school_id, sc.school_name, sc.district, sub.subject_name, e.exam_name,
           COUNT(m.mark_id)   AS student_count,
           AVG(m.score)       AS avg_score,
           SUM(CASE WHEN m.score >= 40 THEN 1 ELSE 0 END) AS pass_count
    FROM marks m
    JOIN students st  ON m.student_id  = st.student_id
    JOIN schools  sc  ON st.school_id  = sc.school_id
    JOIN subjects sub ON m.subject_id  = sub.subject_id
    JOIN exams    e   ON m.exam_id     = e.exam_id
    WHERE m.status IN ('submitted', 'approved') {$where_exam}
    GROUP BY m.exam_id, m.subject_id, st.school_id
");

$groups = [];
if ($stats_query) {
    while ($row = $stats_query->fetch_assoc()) $groups[] = $row;
}

$raw_marks_query = $conn->query("
    SELECT m.exam_id, m.subject_id, st.school_id, m.score
    FROM marks m
    JOIN students st ON m.student_id = st.student_id
    WHERE m.status IN ('submitted', 'approved') {$where_exam}
");
$scores_by_group = [];
if ($raw_marks_query) {
    while ($row = $raw_marks_query->fetch_assoc()) {
        $key = $row['exam_id'] . '_' . $row['subject_id'] . '_' . $row['school_id'];
        $scores_by_group[$key][] = (float)$row['score'];
    }
}

foreach ($groups as $g) {
    $exam_id       = (int)$g['exam_id'];
    $subject_id    = (int)$g['subject_id'];
    $school_id     = (int)$g['school_id'];
    $district      = $g['district'];
    $student_count = (int)$g['student_count'];
    $avg_score     = (float)$g['avg_score'];
    $pass_count    = (int)$g['pass_count'];
    $pass_rate     = $student_count > 0 ? ($pass_count / $student_count) * 100 : 0;
    $context       = htmlspecialchars($g['subject_name']) . ' — ' . htmlspecialchars($g['exam_name']);

    // Grade Inflation
    if ($student_count >= 8 && ($avg_score > 85.0 || $pass_rate >= 100.0)) {
        $anomalies[] = [
            'category'    => 'Grade Inflation',
            'severity'    => 'High',
            'type'        => 'inflation',
            'district'    => $district,
            'source'      => htmlspecialchars($g['school_name']) . ' (' . htmlspecialchars($district) . ')',
            'context'     => $context,
            'metric'      => 'Pass Rate: ' . number_format($pass_rate, 1) . '%  |  Class Average: ' . number_format($avg_score, 1) . '%',
            'explanation' => 'This school recorded a pass rate or average score well above the national norm of 60–70%. This may indicate lenient marking, shared answers among candidates, or paper leakage prior to the exam.',
            'action'      => 'Request a random audit of at least 5 candidate scripts from this school. Cross-check against division averages for this subject and report findings to the Chief Examiner.',
        ];
    }

    // Performance Deficit
    if ($student_count >= 5 && $avg_score < 25.0) {
        $anomalies[] = [
            'category'    => 'Performance Deficit',
            'severity'    => 'Medium',
            'type'        => 'deficit',
            'district'    => $district,
            'source'      => htmlspecialchars($g['school_name']) . ' (' . htmlspecialchars($district) . ')',
            'context'     => $context,
            'metric'      => 'Class Average: ' . number_format($avg_score, 1) . '%  |  ' . $student_count . ' candidates sat',
            'explanation' => 'The average mark is critically low (below 25%). Candidates may have experienced severe syllabus gaps, teacher absenteeism, or marking errors that unfairly penalised work.',
            'action'      => 'Investigate teaching coverage for this subject at this school. Deploy a subject specialist or request diagnostic test results from the District Education Manager.',
        ];
    }

    // Score Collusion / Fabrication
    $key = $exam_id . '_' . $subject_id . '_' . $school_id;
    if (isset($scores_by_group[$key]) && count($scores_by_group[$key]) >= 8) {
        $std_dev = get_standard_deviation($scores_by_group[$key]);
        if ($std_dev < 2.0 && $std_dev > 0.0) {
            $anomalies[] = [
                'category'    => 'Score Collusion Suspected',
                'severity'    => 'Critical',
                'type'        => 'collusion',
                'district'    => $district,
                'source'      => htmlspecialchars($g['school_name']) . ' (' . htmlspecialchars($district) . ')',
                'context'     => $context,
                'metric'      => 'Std. Deviation: ' . number_format($std_dev, 2) . '  (Critical threshold: below 2.0)',
                'explanation' => 'All ' . $student_count . ' candidates at this school scored almost identically — a statistical near-impossibility under normal examination conditions. This strongly suggests scores were fabricated or copied.',
                'action'      => 'Reject the submitted marksheet immediately. Direct the Headteacher to produce original answer scripts for physical audit within 48 hours. Escalate to the Examinations Council.',
            ];
        }
    }
}

// ── 2. Examiner Workload Overload ─────────────────────────────────
$workload_query = $conn->query("
    SELECT u.name AS teacher_name, sc.school_name, sc.district, COUNT(*) AS scripts
    FROM marking_assignments ma
    JOIN users u ON ma.teacher_id = u.user_id
    LEFT JOIN schools sc ON u.school_id = sc.school_id
    WHERE ma.status IN ('assigned', 'submitted')
    GROUP BY ma.teacher_id
    HAVING scripts > 4
");
if ($workload_query) {
    while ($wl = $workload_query->fetch_assoc()) {
        $excess   = max(1, $wl['scripts'] - 4);
        $dist     = $wl['district'] ?: 'Division-wide';
        $anomalies[] = [
            'category'    => 'Examiner Overload',
            'severity'    => 'Medium',
            'type'        => 'overload',
            'district'    => $dist,
            'source'      => htmlspecialchars($wl['teacher_name']) . ' (' . htmlspecialchars($wl['school_name'] ?: 'External Examiner') . ')',
            'context'     => 'Marking Assignment Allocation',
            'metric'      => $wl['scripts'] . ' active marking assignments  |  Recommended maximum: 4',
            'explanation' => 'This examiner has been assigned ' . $wl['scripts'] . ' marking tasks simultaneously — exceeding the recommended maximum of 4. Examiner fatigue significantly increases the risk of grading errors.',
            'action'      => 'Reassign at least ' . $excess . ' marking task' . ($excess > 1 ? 's' : '') . ' to another qualified examiner immediately. Update the Assignment Manager and notify the examiner.',
        ];
    }
}

// ── 3. AI Moderation Overruled ─────────────────────────────────────
$where_ai_exam = $selected_exam_id > 0 ? "AND q.exam_id = {$selected_exam_id}" : "";
$moderation_overruled = $conn->query("
    SELECT q.question_id, q.exam_id, e.exam_name, q.question_text,
           ai.score AS ai_score, q.moderation_status, s.subject_name
    FROM questions q
    JOIN exams e ON q.exam_id = e.exam_id
    JOIN exam_subjects es ON q.exam_subject_id = es.id
    JOIN subjects s ON es.subject_id = s.subject_id
    JOIN ai_analysis ai ON q.question_id = ai.question_id
    WHERE q.moderation_status = 'approved' AND ai.score < 50.0 {$where_ai_exam}
    ORDER BY ai.score ASC
");
if ($moderation_overruled) {
    while ($mo = $moderation_overruled->fetch_assoc()) {
        $anomalies[] = [
            'category'    => 'AI Assessment Overruled',
            'severity'    => 'Low',
            'type'        => 'ai',
            'district'    => 'Division-wide',
            'source'      => 'Item Writing — Question Compilation',
            'context'     => htmlspecialchars($mo['exam_name'] . ' — ' . ($mo['subject_name'] ?? 'Subject Paper')),
            'metric'      => 'AI Quality Score: ' . number_format($mo['ai_score'], 1) . '%  |  Minimum recommended: 50%',
            'explanation' => 'Question #' . $mo['question_id'] . ' was approved by a human moderator even though the AI model rated its quality below 50%. The question may have ambiguity, incorrect difficulty, or syllabus misalignment.',
            'action'      => 'A senior subject expert should review this question before the paper goes to print. Request the moderator to provide written justification for the approval decision.',
        ];
    }
}

$conn->close();

// Global Severity Counters
$count_critical = count(array_filter($anomalies, fn($a) => $a['severity'] === 'Critical'));
$count_high     = count(array_filter($anomalies, fn($a) => $a['severity'] === 'High'));
$count_medium   = count(array_filter($anomalies, fn($a) => $a['severity'] === 'Medium'));
$count_low      = count(array_filter($anomalies, fn($a) => $a['severity'] === 'Low'));
$total_all      = count($anomalies);

// Filtering (District, Severity, Search Query)
$filtered_anomalies = array_filter($anomalies, function($anom) use ($filter_district, $filter_severity, $search_query) {
    if ($filter_severity !== '' && $anom['severity'] !== $filter_severity) {
        return false;
    }
    if ($filter_district !== '' && strtolower($anom['district']) !== strtolower($filter_district)) {
        return false;
    }
    if ($search_query !== '') {
        $haystack = strtolower($anom['source'] . ' ' . $anom['context'] . ' ' . $anom['category'] . ' ' . $anom['explanation']);
        if (str_contains($haystack, strtolower($search_query)) === false) {
            return false;
        }
    }
    return true;
});
$filtered_anomalies = array_values($filtered_anomalies);

$total_filtered      = count($filtered_anomalies);
$pagination          = paginate($total_filtered, $current_page, $per_page);
$offset              = ($pagination['page'] - 1) * $pagination['per_page'];
$anomalies_paginated = array_slice($filtered_anomalies, $offset, $per_page);

$portal_title = 'NED-SEMS | Anomaly Detection Report';
$module_css   = 'admin';
include __DIR__ . '/../../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anomaly Detection Report | NED-SEMS</title>
    <meta name="description" content="AI-powered statistical anomaly detection for examination marking, grade inflation, examiner overload, and quality outliers.">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
    <style>
    .kpi-strip {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 28px;
    }
    @media (max-width: 900px) { .kpi-strip { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 500px) { .kpi-strip { grid-template-columns: 1fr; } }

    .kpi-tile {
        background: var(--card-color, #ffffff);
        border: 1px solid var(--border-color, #e2e8f0);
        border-radius: 12px;
        padding: 20px;
        text-decoration: none;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        transition: transform .18s, box-shadow .18s;
        display: block;
    }
    .kpi-tile:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    }
    .kpi-tile.active {
        border-color: #2563eb;
        box-shadow: inset 0 0 0 1px #2563eb, 0 4px 12px rgba(0,0,0,0.06);
    }
    .kpi-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 8px; }
    .kpi-num { font-size: 2.2rem; font-weight: 800; line-height: 1; color: #0f172a; }
    .kpi-label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #475569; }
    .kpi-sub { font-size: 0.75rem; color: #94a3b8; margin-top: 2px; }

    .sev-badge-critical { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
    .sev-badge-high     { background: #fff7ed; color: #ea580c; border: 1px solid #ffedd5; }
    .sev-badge-medium   { background: #fefce8; color: #ca8a04; border: 1px solid #fef08a; }
    .sev-badge-low      { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }

    .guide-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 28px; }
    @media (max-width: 860px) { .guide-grid { grid-template-columns: 1fr; } }
    .guide-tile { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; }
    .guide-tile h4 { margin: 0 0 6px 0; font-size: 0.88rem; font-weight: 700; color: #0f172a; }
    .guide-tile p  { margin: 0; font-size: 0.8rem; color: #64748b; line-height: 1.5; }

    .anomaly-stack { display: flex; flex-direction: column; gap: 16px; }
    .a-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
    .a-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; background: #f8fafc; border-bottom: 1px solid #f1f5f9; }
    .a-category { font-size: 0.95rem; font-weight: 800; color: #0f172a; }
    .a-context { font-size: 0.78rem; color: #64748b; font-weight: 600; margin-top: 2px; }
    .a-body { padding: 16px 18px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (max-width: 640px) { .a-body { grid-template-columns: 1fr; } }
    .a-source-row { grid-column: 1 / -1; font-size: 0.84rem; color: #475569; padding-bottom: 4px; border-bottom: 1px dashed #e2e8f0; }
    .metric-bar { grid-column: 1 / -1; background: #0f172a; color: #e2e8f0; font-size: 0.78rem; font-weight: 700; font-family: monospace; padding: 8px 12px; border-radius: 6px; }
    .info-box { border-radius: 8px; padding: 12px; font-size: 0.82rem; line-height: 1.5; }
    .info-box .ib-label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
    .info-box.detected { background: #f8fafc; border: 1px solid #e2e8f0; color: #334155; }
    .info-box.detected .ib-label { color: #64748b; }
    .info-box.action { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
    .info-box.action .ib-label { color: #1d4ed8; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../common/header.php'; ?>

<div class="dashboard">
    <?php include __DIR__ . '/../../common/sidebar.php'; ?>

    <div class="content">

        <!-- Print Header -->
        <div class="print-header">
            <h2 class="print-title">Academic &amp; Administrative Anomaly Detection Report</h2>
            <div class="print-meta">Generated: <?= date('Y-m-d H:i') ?> | Northern Education Division | Administrator</div>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2 class="page-title">Anomaly Detection Report</h2>
                <p class="page-subtitle">Statistical &amp; AI checks for marking irregularities, grade inflation, examiner overload, and question quality outliers</p>
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
                    <label class="rpt-filter-label" for="af-exam">Examination Paper</label>
                    <select name="exam_id" id="af-exam" class="rpt-filter-select" onchange="this.form.submit()">
                        <option value="0">All Examinations</option>
                        <?php foreach ($exams_list as $ex): ?>
                            <option value="<?= $ex['exam_id'] ?>" <?= $selected_exam_id === (int)$ex['exam_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ex['exam_name']) ?> (<?= htmlspecialchars($ex['year']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- District Filter -->
                <div class="rpt-filter-group">
                    <label class="rpt-filter-label" for="af-district">District</label>
                    <select name="district" id="af-district" class="rpt-filter-select" onchange="this.form.submit()">
                        <option value="">All Districts</option>
                        <?php foreach ($districts_list as $d): ?>
                            <option value="<?= htmlspecialchars($d['district']) ?>" <?= $filter_district === $d['district'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['district']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Severity Filter -->
                <div class="rpt-filter-group">
                    <label class="rpt-filter-label" for="af-severity">Severity Level</label>
                    <select name="severity" id="af-severity" class="rpt-filter-select" onchange="this.form.submit()">
                        <option value="">All Severities</option>
                        <option value="Critical" <?= $filter_severity === 'Critical' ? 'selected' : '' ?>>Critical Only</option>
                        <option value="High"     <?= $filter_severity === 'High'     ? 'selected' : '' ?>>High Severity</option>
                        <option value="Medium"   <?= $filter_severity === 'Medium'   ? 'selected' : '' ?>>Medium Severity</option>
                        <option value="Low"      <?= $filter_severity === 'Low'      ? 'selected' : '' ?>>Low / Advisory</option>
                    </select>
                </div>

                <!-- Search Input -->
                <div class="rpt-filter-group">
                    <label class="rpt-filter-label" for="af-search">Search Keyword</label>
                    <input type="text" name="search" id="af-search" class="rpt-filter-input" placeholder="School, subject..." value="<?= htmlspecialchars($search_query) ?>">
                </div>

                <div class="rpt-filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="anomalies.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- ═══ KPI STRIP ═══ -->
        <div class="kpi-strip">
            <a href="anomalies.php?severity=Critical" class="kpi-tile <?= $filter_severity === 'Critical' ? 'active' : '' ?>">
                <div class="kpi-top">
                    <div class="kpi-num" style="color: #dc2626;"><?= $count_critical ?></div>
                </div>
                <div class="kpi-label">Critical Flags</div>
                <div class="kpi-sub">Collusion / fabrication</div>
            </a>

            <a href="anomalies.php?severity=High" class="kpi-tile <?= $filter_severity === 'High' ? 'active' : '' ?>">
                <div class="kpi-top">
                    <div class="kpi-num" style="color: #ea580c;"><?= $count_high ?></div>
                </div>
                <div class="kpi-label">High Severity</div>
                <div class="kpi-sub">Grade inflation detected</div>
            </a>

            <a href="anomalies.php?severity=Medium" class="kpi-tile <?= $filter_severity === 'Medium' ? 'active' : '' ?>">
                <div class="kpi-top">
                    <div class="kpi-num" style="color: #ca8a04;"><?= $count_medium ?></div>
                </div>
                <div class="kpi-label">Medium Severity</div>
                <div class="kpi-sub">Examiner overload / deficits</div>
            </a>

            <a href="anomalies.php?severity=Low" class="kpi-tile <?= $filter_severity === 'Low' ? 'active' : '' ?>">
                <div class="kpi-top">
                    <div class="kpi-num" style="color: #2563eb;"><?= $count_low ?></div>
                </div>
                <div class="kpi-label">Low / Advisory</div>
                <div class="kpi-sub">AI moderation overrides</div>
            </a>
        </div>

        <!-- ═══ DETECTION GUIDES ═══ -->
        <div class="guide-grid">
            <div class="guide-tile">
                <h4>Score Collusion &amp; Fabrication</h4>
                <p>Triggered when standard deviation across a class falls below 2.0. This indicates identical or copied scores across candidates.</p>
            </div>
            <div class="guide-tile">
                <h4>Grade Inflation &amp; Deficits</h4>
                <p>100% pass rate or class average &gt; 85% triggers an inflation flag. Average &lt; 25% flags severe teaching or marking deficits.</p>
            </div>
            <div class="guide-tile">
                <h4>AI Moderation Overrides</h4>
                <p>Triggered when a human moderator approves a question rated below 50% quality by the AI model prior to paper printing.</p>
            </div>
        </div>

        <!-- ═══ ANOMALIES LIST ═══ -->
        <div class="card">
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <div>
                    <h3 style="margin: 0;">Detected Anomaly Flags</h3>
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin: 4px 0 0 0;">
                        Displaying <strong><?= count($anomalies_paginated) ?></strong> of <strong><?= $total_filtered ?></strong> anomaly flags
                        <?= $filter_severity ? "filtered by <strong>" . htmlspecialchars($filter_severity) . "</strong> severity" : "" ?>
                    </p>
                </div>
                <?php if ($pagination && $pagination['total_pages'] > 1): ?>
                    <span style="font-size: 0.82rem; color: var(--text-muted);">Page <?= $pagination['page'] ?> of <?= $pagination['total_pages'] ?></span>
                <?php endif; ?>
            </div>

            <?php if (empty($anomalies_paginated)): ?>
                <div class="card empty-state" style="padding: 48px; text-align: center;">
                    <p style="color: var(--text-muted); font-size: 1rem; margin: 0;">
                        No anomaly flags detected matching your filter criteria.
                    </p>
                </div>
            <?php else: ?>
                <div class="anomaly-stack">
                    <?php foreach ($anomalies_paginated as $anom): ?>
                        <?php
                            $sev_cls = match($anom['severity']) {
                                'Critical' => 'sev-badge-critical',
                                'High'     => 'sev-badge-high',
                                'Medium'   => 'sev-badge-medium',
                                'Low'      => 'sev-badge-low',
                                default    => 'sev-badge-medium'
                            };
                        ?>
                        <div class="a-card">
                            <div class="a-head">
                                <div>
                                    <div class="a-category"><?= htmlspecialchars($anom['category']) ?></div>
                                    <div class="a-context"><?= $anom['context'] ?></div>
                                </div>
                                <span class="rpt-badge <?= $sev_cls ?>" style="font-size: 0.75rem; padding: 4px 10px; font-weight: 800;">
                                    <?= htmlspecialchars($anom['severity']) ?> Severity
                                </span>
                            </div>

                            <div class="a-body">
                                <div class="a-source-row">
                                    <strong>Source / Centre:</strong> <?= $anom['source'] ?>
                                </div>

                                <div class="metric-bar">
                                    <?= $anom['metric'] ?>
                                </div>

                                <div class="info-box detected">
                                    <div class="ib-label">What Was Detected</div>
                                    <?= htmlspecialchars($anom['explanation']) ?>
                                </div>

                                <div class="info-box action">
                                    <div class="ib-label">Recommended EDM Action</div>
                                    <?= htmlspecialchars($anom['action']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($pagination && $pagination['total_pages'] > 1): ?>
                    <div style="margin-top: 18px;">
                        <?= render_pagination($pagination, 'anomalies.php') ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../../common/footer.php'; ?>

</body>
</html>