<?php
/* ════════════════════════════════════════════════════════════════
   admin/reports/division_report.php
   EDM/Admin: Division-wide Strategic Overview
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../common/report_stats.php';
require_once __DIR__ . '/../../common/report_charts.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

$conn = get_db_connection();

// Get list of exams for filter dropdown
$exams_list = $conn->query("SELECT exam_id, exam_name, class, year FROM exams ORDER BY year DESC, start_date DESC")->fetch_all(MYSQLI_ASSOC);

$selected_exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
if ($selected_exam_id <= 0 && !empty($exams_list)) {
    $selected_exam_id = (int)$exams_list[0]['exam_id'];
}

// Fetch exam detail
$exam_info = null;
if ($selected_exam_id > 0) {
    $ex_stmt = $conn->prepare("SELECT * FROM exams WHERE exam_id = ?");
    $ex_stmt->bind_param("i", $selected_exam_id);
    $ex_stmt->execute();
    $exam_info = $ex_stmt->get_result()->fetch_assoc();
    $ex_stmt->close();
}

/* ─── 1. FETCH OVERALL DIVISION SUMMARY KPIs ─── */

$total_schools = (int)($conn->query("SELECT COUNT(*) as c FROM schools WHERE status='active'")->fetch_assoc()['c'] ?? 0);

$cand_stmt = $conn->prepare("SELECT COUNT(*) as c FROM students WHERE class = ? AND status='active'");
$cand_stmt->bind_param("s", $exam_info['class']);
$cand_stmt->execute();
$total_candidates = (int)($cand_stmt->get_result()->fetch_assoc()['c'] ?? 0);
$cand_stmt->close();

$sat_stmt = $conn->prepare("SELECT COUNT(DISTINCT student_id) as c FROM results WHERE exam_id = ? AND status='published'");
$sat_stmt->bind_param("i", $selected_exam_id);
$sat_stmt->execute();
$total_sat = (int)($sat_stmt->get_result()->fetch_assoc()['c'] ?? 0);
$sat_stmt->close();

$total_absent = max(0, $total_candidates - $total_sat);
$participation_rate = $total_candidates > 0 ? round(($total_sat / $total_candidates) * 100, 1) : 0.0;

$avg_stmt = $conn->prepare("SELECT AVG(average_score) as v FROM results WHERE exam_id = ? AND status='published'");
$avg_stmt->bind_param("i", $selected_exam_id);
$avg_stmt->execute();
$division_avg = (float)($avg_stmt->get_result()->fetch_assoc()['v'] ?? 0.0);
$division_avg = round($division_avg, 1);
$avg_stmt->close();

$pass_stmt = $conn->prepare("SELECT COUNT(*) as c FROM results WHERE exam_id = ? AND status='published' AND average_score >= 40");
$pass_stmt->bind_param("i", $selected_exam_id);
$pass_stmt->execute();
$passed = (int)($pass_stmt->get_result()->fetch_assoc()['c'] ?? 0);
$pass_stmt->close();

$failed = max(0, $total_sat - $passed);
$pass_rate = $total_sat > 0 ? round(($passed / $total_sat) * 100, 1) : 0.0;

/* ─── 2. GATHER DIVISION-LEVEL METRICS ─── */

// Best/Worst District for this exam
$district_perf = $conn->query("
    SELECT sc.district, AVG(r.average_score) as avg_score, COUNT(DISTINCT s.student_id) as candidates_count
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    JOIN schools sc ON s.school_id = sc.school_id
    WHERE r.exam_id = {$selected_exam_id} AND r.status='published'
    GROUP BY sc.district
    ORDER BY avg_score DESC
")->fetch_all(MYSQLI_ASSOC);

$best_district = $district_perf[0]['district'] ?? 'N/A';
$worst_district = (count($district_perf) > 1) ? $district_perf[count($district_perf) - 1]['district'] : ($district_perf[0]['district'] ?? 'N/A');

// Best/Worst School for this exam
$school_perf = $conn->query("
    SELECT sc.school_name, sc.district, AVG(r.average_score) as avg_score
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    JOIN schools sc ON s.school_id = sc.school_id
    WHERE r.exam_id = {$selected_exam_id} AND r.status='published'
    GROUP BY s.school_id
    ORDER BY avg_score DESC
")->fetch_all(MYSQLI_ASSOC);

$best_school = $school_perf[0]['school_name'] ?? 'N/A';
$worst_school = (count($school_perf) > 1) ? $school_perf[count($school_perf) - 1]['school_name'] : ($school_perf[0]['school_name'] ?? 'N/A');

// Historical Trend (Line Chart values)
$trend_res = $conn->query("
    SELECT e.exam_name, e.year, AVG(r.average_score) as avg_score
    FROM results r
    JOIN exams e ON r.exam_id = e.exam_id
    WHERE r.status='published'
    GROUP BY e.exam_id
    ORDER BY e.year ASC, e.start_date ASC
")->fetch_all(MYSQLI_ASSOC);

$trend_labels = array_map(function($t) {
  $name = $t['exam_name'];
  $year = (string)$t['year'];
  return (strpos($name, $year) !== false) ? $name : ($name . ' (' . $year . ')');
}, $trend_res);
$trend_values = array_map(fn($t) => (float)$t['avg_score'], $trend_res);

// Subject Standings
$subj_res = $conn->query("
    SELECT s.subject_name, s.subject_code, AVG(m.score) as avg_score
    FROM marks m
    JOIN subjects s ON m.subject_id = s.subject_id
    WHERE m.exam_id = {$selected_exam_id} AND m.status = 'approved'
    GROUP BY m.subject_id
    ORDER BY avg_score DESC
")->fetch_all(MYSQLI_ASSOC);

// Critical Risk Summary (Schools with average < 40%)
$risk_schools = $conn->query("
    SELECT sc.school_name, sc.district, AVG(r.average_score) as avg_score
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    JOIN schools sc ON s.school_id = sc.school_id
    WHERE r.exam_id = {$selected_exam_id} AND r.status='published'
    GROUP BY s.school_id
    HAVING avg_score < 40
    ORDER BY avg_score ASC
")->fetch_all(MYSQLI_ASSOC);

// Prediction summary: calculate expected overall average
$predicted_avg = $division_avg;
if (count($trend_values) >= 2) {
    $ma = stats_moving_average($trend_values, 3);
    $predicted_avg = round($ma[count($ma)-1] + (($trend_values[count($trend_values)-1] - $trend_values[count($trend_values)-2]) * 0.4), 1);
}

// Compute division-level trend direction
$trend_direction = 'flat';
if (count($trend_values) >= 2) {
    $last  = $trend_values[count($trend_values) - 1];
    $prev  = $trend_values[count($trend_values) - 2];
    $delta = $last - $prev;
    if ($delta <= -0.5)      $trend_direction = 'declining';
    elseif ($delta >= 0.5)   $trend_direction = 'improving';
}

// Fetch all headteacher findings and outcomes division-wide for the selected exam
$findings_stmt = $conn->prepare("
    SELECT f.finding_id, f.trend, f.cause_category, f.cause_detail, f.action_category, f.action_detail, f.lifecycle_status, f.pass_rate_pct,
           sc.school_name, sc.district,
           o.outcome, o.outcome_detail
    FROM ht_findings f
    JOIN schools sc ON f.school_id = sc.school_id
    LEFT JOIN ht_finding_outcomes o ON o.finding_id = f.finding_id
    WHERE f.exam_id = ?
    ORDER BY f.created_at DESC
");
$findings_stmt->bind_param("i", $selected_exam_id);
$findings_stmt->execute();
$division_findings = $findings_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$findings_stmt->close();

// Fetch division-level finding for the selected exam
$current_div_finding = null;
if ($selected_exam_id > 0) {
    $stmt = $conn->prepare("
        SELECT f.*, o.outcome, o.outcome_detail, o.created_at AS outcome_at
        FROM div_findings f
        LEFT JOIN div_finding_outcomes o ON o.finding_id = f.finding_id
        WHERE f.exam_id = ?
    ");
    $stmt->bind_param("i", $selected_exam_id);
    $stmt->execute();
    $current_div_finding = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch open division finding from the immediately preceding exam
$pending_div_outcome_finding = null;
if ($selected_exam_id > 0) {
    $stmt = $conn->prepare("
        SELECT f.finding_id, f.trend, f.cause_category, f.cause_detail,
               f.action_category, f.action_detail, f.avg_score_pct, f.created_at,
               e.exam_name, e.year
        FROM div_findings f
        JOIN exams e ON e.exam_id = f.exam_id
        WHERE f.lifecycle_status = 'open'
          AND (
                e.year < (SELECT year FROM exams WHERE exam_id = ?)
                OR (
                  e.year = (SELECT year FROM exams WHERE exam_id = ?)
                  AND e.exam_id < ?
                )
              )
        ORDER BY e.year DESC, e.exam_id DESC
        LIMIT 1
    ");
    $stmt->bind_param("iii", $selected_exam_id, $selected_exam_id, $selected_exam_id);
    $stmt->execute();
    $pending_div_outcome_finding = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch closed division suggestions matching this trend
$div_suggestions = [];
if ($selected_exam_id > 0 && in_array($trend_direction, ['declining', 'improving'], true)) {
    $stmt = $conn->prepare("
        SELECT f.cause_category, f.cause_detail,
               f.action_category, f.action_detail,
               f.avg_score_pct, f.created_at,
               o.outcome, o.outcome_detail,
               e.exam_name, e.year
        FROM div_findings f
        JOIN div_finding_outcomes o ON o.finding_id = f.finding_id
        JOIN exams e ON e.exam_id = f.exam_id
        WHERE f.trend = ? AND f.lifecycle_status = 'closed'
        ORDER BY FIELD(o.outcome, 'improved', 'no_change', 'worsened'), f.created_at DESC
        LIMIT 5
    ");
    $stmt->bind_param("s", $trend_direction);
    $stmt->execute();
    $div_suggestions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$conn->close();

function cause_label_div(string $slug): string {
    return [
        'teacher_shortage'             => 'Teacher Shortage (Division-wide)',
        'funding_delays'               => 'Funding Delays to Schools',
        'learning_material_deficiency' => 'Deficiency in Learning Materials',
        'curriculum_misalignment'      => 'Curriculum Misalignment / Rollout Issues',
        'teacher_compliance_low'       => 'Low Teacher Submission / Compliance',
        'extreme_weather'              => 'Extreme Weather / Disaster Disruption',
        'administrative_laxity'        => 'Administrative Laxity / Lack of Supervision',
        'positive_divisional_reform'   => 'Positive Divisional Policy Reforms',
        'other'                        => 'Other Strategic Factors',
    ][$slug] ?? ucfirst(str_replace('_', ' ', $slug));
}

function action_label_div(string $slug): string {
    return [
        'teacher_recruitment'       => 'Hiring / Deploying Teaching Staff',
        'budget_allocation'         => 'Emergency Funding Allocation',
        'textbook_distribution'     => 'Procuring & Distributing Textbooks/Materials',
        'inspection_blitz'          => 'Strategic School Inspection Campaigns',
        'teacher_capacity_building' => 'Syllabus CPD Capacity Building Seminars',
        'remedial_policy_mandate'   => 'Mandatory Remedial Class Requirements',
        'divisional_recognition'    => 'Strategic Center Performance Awards',
        'other'                     => 'Other Intervention Programs',
    ][$slug] ?? ucfirst(str_replace('_', ' ', $slug));
}

function outcome_label_div(string $slug): string {
    return ['improved' => 'Improved', 'no_change' => 'No Change', 'worsened' => 'Worsened'][$slug] ?? $slug;
}


$portal_title = 'NED-SEMS | Division Overview';
$module_css = 'admin';
include __DIR__ . '/../../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Division Strategic Report | NED-SEMS</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
</head>
<body>

<?php include __DIR__ . '/../../common/header.php'; ?>

<div class="dashboard">
    <?php include __DIR__ . '/../../common/sidebar.php'; ?>

    <div class="content">
        <!-- Print Header -->
        <div class="print-header">
            <h2 class="print-title">Division Educational Strategic Overview Report</h2>
            <div class="print-meta">Generated: <?= date('Y-m-d') ?> | Prepared By: Division EDM Manager</div>
        </div>

        <div class="page-header">
            <div>
                <h2 class="page-title">Division Overview Report</h2>
                <p class="page-subtitle">Strategic-level analysis of division averages, cross-district comparisons, subject health checks and recommendations</p>
            </div>
            <div class="header-actions">
                <button onclick="window.print()" class="btn btn-secondary">Print Division Report</button>
            </div>
        </div>

        <!-- Filter -->
        <div class="rpt-filter-panel">
            <form method="GET" class="rpt-filter-form">
                <div class="rpt-filter-group">
                    <label class="rpt-filter-label">Examination Year</label>
                    <select name="exam_id" class="rpt-filter-select" onchange="this.form.submit()">
                        <?php foreach ($exams_list as $ex): ?>
                            <option value="<?= $ex['exam_id'] ?>" <?= $selected_exam_id === (int)$ex['exam_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ex['exam_name']) ?> (<?= htmlspecialchars($ex['year']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="rpt-filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </form>
        </div>

        <!-- summary KPIs -->
        <div class="rpt-kpi-grid">
            <div class="rpt-kpi-card rpt-kpi--blue">
                <span class="rpt-kpi-label">Active Schools</span>
                <span class="rpt-kpi-value"><?= $total_schools ?></span>
                <span class="rpt-kpi-sub">Total Institution Centers</span>
            </div>
            <div class="rpt-kpi-card rpt-kpi--purple">
                <span class="rpt-kpi-label">Candidates Sat</span>
                <span class="rpt-kpi-value"><?= number_format($total_sat) ?></span>
                <span class="rpt-kpi-sub">Absent: <?= $total_absent ?> (Participation: <?= $participation_rate ?>%)</span>
            </div>
            <div class="rpt-kpi-card rpt-kpi--green">
                <span class="rpt-kpi-label">Division Average</span>
                <span class="rpt-kpi-value"><?= $division_avg ?>%</span>
                <span class="rpt-kpi-sub">Overall mean score</span>
            </div>
            <div class="rpt-kpi-card rpt-kpi--teal">
                <span class="rpt-kpi-label">Overall Pass Rate</span>
                <span class="rpt-kpi-value"><?= $pass_rate ?>%</span>
                <span class="rpt-kpi-sub">Failed: <?= 100 - $pass_rate ?>%</span>
            </div>
            <div class="rpt-kpi-card rpt-kpi--purple">
                <span class="rpt-kpi-label">Strategic Risk Index</span>
                <span class="rpt-kpi-value" style="font-size:1.6rem;"><?= count($risk_schools) ?> critical</span>
                <span class="rpt-kpi-sub">Schools average < 40%</span>
            </div>
        </div>

        <!-- Best/Worst Summary Panel -->
        <div class="card">
            <div class="section-header">
                <h3>Division Standings Summary</h3>
            </div>
            <div class="rpt-stats-grid">
                <div class="rpt-stat-item" style="border-left: 3px solid #16a34a;">
                    <span class="rpt-stat-label" style="color:#16a34a;">Best Performing District</span>
                    <span class="rpt-stat-value" style="font-size:1rem;"><?= htmlspecialchars($best_district) ?></span>
                </div>
                <div class="rpt-stat-item" style="border-left: 3px solid #dc2626;">
                    <span class="rpt-stat-label" style="color:#dc2626;">Lowest Performing District</span>
                    <span class="rpt-stat-value" style="font-size:1rem;"><?= htmlspecialchars($worst_district) ?></span>
                </div>
                <div class="rpt-stat-item" style="border-left: 3px solid #16a34a;">
                    <span class="rpt-stat-label" style="color:#16a34a;">Best Performing Institution</span>
                    <span class="rpt-stat-value" style="font-size:0.8rem; line-height:1.2; height:32px; overflow:hidden;" title="<?= htmlspecialchars($best_school) ?>"><?= htmlspecialchars($best_school) ?></span>
                </div>
                <div class="rpt-stat-item" style="border-left: 3px solid #dc2626;">
                    <span class="rpt-stat-label" style="color:#dc2626;">Lowest Performing Institution</span>
                    <span class="rpt-stat-value" style="font-size:0.8rem; line-height:1.2; height:32px; overflow:hidden;" title="<?= htmlspecialchars($worst_school) ?>"><?= htmlspecialchars($worst_school) ?></span>
                </div>
            </div>
        </div>

        <!-- District Comparisons & Historical Trends -->
        <div class="rpt-chart-grid">
            <div class="rpt-chart-box">
                <h3 class="rpt-chart-title">District Comparative Directory</h3>
                <p class="rpt-chart-sub">Average scores and candidate representation by districts</p>
                <div class="rpt-table-wrap">
                    <table class="rpt-table">
                        <thead>
                            <tr>
                                <th class="rank-col">Rank</th>
                                <th>District</th>
                                <th class="val-col">Candidates</th>
                                <th class="val-col">Average Score</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($district_perf)): ?>
                                <tr>
                                    <td colspan="5" class="empty-state">No compiled results data.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($district_perf as $idx => $dp): ?>
                                    <tr>
                                        <td class="rank-col"><?= $idx + 1 ?></td>
                                        <td style="font-weight:600;"><?= htmlspecialchars($dp['district']) ?></td>
                                        <td class="val-col"><?= $dp['candidates_count'] ?></td>
                                        <td class="val-col" style="color:var(--info-color);"><?= number_format($dp['avg_score'], 1) ?>%</td>
                                        <td><?= stats_badge(stats_performance_category($dp['avg_score'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rpt-chart-box">
                <h3 class="rpt-chart-title">Division History Line Trend</h3>
                <p class="rpt-chart-sub">Division performance mapping across exams history</p>
                <?php if (!empty($trend_values)): ?>
                    <?= chart_line($trend_labels, [['label' => 'Division Mean', 'values' => $trend_values, 'color' => '#2563eb']], ['height' => 220, 'rotate_labels' => true]) ?>
                <?php else: ?>
                    <p class="empty-state">No historical results data found.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Subject Health Check & Prediction/Risk Summary -->
        <div class="rpt-chart-grid">
            <div class="rpt-chart-box">
                <h3 class="rpt-chart-title">Syllabus Subject health check</h3>
                <p class="rpt-chart-sub">Subject performance sorted by mean average score</p>
                <div class="rpt-table-wrap">
                    <table class="rpt-table">
                        <thead>
                            <tr>
                                <th>Subject Code</th>
                                <th>Subject Syllabus</th>
                                <th class="val-col">Division Mean</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subj_res)): ?>
                                <tr>
                                    <td colspan="3" class="empty-state">No marks recorded.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($subj_res as $sr): ?>
                                    <tr>
                                        <td style="font-weight:700; color:var(--info-color);"><?= htmlspecialchars($sr['subject_code']) ?></td>
                                        <td><?= htmlspecialchars($sr['subject_name']) ?></td>
                                        <td class="val-col"><?= number_format($sr['avg_score'], 1) ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Risk analysis & ML Predictions -->
            <div class="rpt-chart-box">
                <h3 class="rpt-chart-title">Strategic Predictive & Risk Analysis</h3>
                <p class="rpt-chart-sub">Regression projections and warning indicators for division leadership</p>

                <div class="rpt-stats-grid" style="margin-top:20px;">
                    <div class="rpt-stat-item">
                        <span class="rpt-stat-label">Current Avg</span>
                        <span class="rpt-stat-value"><?= $division_avg ?>%</span>
                    </div>
                    <div class="rpt-stat-item">
                        <span class="rpt-stat-label">Projected Avg</span>
                        <span class="rpt-stat-value" style="color:var(--info-color);"><?= $predicted_avg ?>%</span>
                    </div>
                    <div class="rpt-stat-item">
                        <span class="rpt-stat-label">Growth Trend</span>
                        <span class="rpt-stat-value">
                            <?php if ($predicted_avg > $division_avg): ?>
                                <span style="color:#16a34a;">▲ Growth expected</span>
                            <?php elseif ($predicted_avg < $division_avg): ?>
                                <span style="color:#dc2626;">▼ Decline warning</span>
                            <?php else: ?>
                                <span style="color:#64748b;">– Stable stands</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <!-- Critical alert schools -->
                <?php if (!empty($risk_schools)): ?>
                    <div class="rpt-rec-card" style="margin-top:20px; background:#fef2f2; border-color:#fecaca; color:#991b1b; padding:15px;">
                        <h4 style="color:#991b1b; margin-top:0;">Critical Schools Risk Summary (<?= count($risk_schools) ?> Centers)</h4>
                        <ul class="rpt-rec-list">
                            <?php foreach (array_slice($risk_schools, 0, 3) as $rs): ?>
                                <li style="color:#991b1b; font-size:0.82rem; margin-bottom:4px;">
                                    <strong><?= htmlspecialchars($rs['school_name']) ?></strong> (<?= htmlspecialchars($rs['district']) ?>) — mean: <?= number_format($rs['avg_score'], 1) ?>%
                                </li>
                            <?php endforeach; ?>
                            <?php if (count($risk_schools) > 3): ?>
                                <li style="list-style:none; padding-left:0; color:#991b1b; font-size:0.8rem; font-style:italic;">...and <?= count($risk_schools) - 3 ?> other schools. Check School Report for full list.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Division-Wide Interventions & School Findings -->
        <div class="card" style="margin-top: 24px;">
            <div class="section-header">
                <h3>Division-Wide School Findings & Interventions</h3>
            </div>
            
            <?php if (empty($division_findings)): ?>
                <p class="empty-state">No school-level investigation findings have been logged for this examination cycle.</p>
            <?php else: ?>
                <div class="rpt-table-wrap">
                    <table class="rpt-table">
                        <thead>
                            <tr>
                                <th>School & District</th>
                                <th>Trend</th>
                                <th>Diagnosed Root Cause</th>
                                <th>Action Response Plan</th>
                                <th>Outcome Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($division_findings as $fd): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($fd['school_name']) ?></div>
                                        <div style="font-size: 0.78rem; color: var(--text-muted);"><?= htmlspecialchars($fd['district']) ?> District</div>
                                    </td>
                                    <td>
                                        <span class="htf-badge htf-badge--<?= htmlspecialchars($fd['trend']) ?>" style="font-size: 0.72rem;">
                                            <?= ucfirst(htmlspecialchars($fd['trend'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="htf-cause-tag" style="font-size: 0.78rem;"><?= htmlspecialchars(cause_label_div($fd['cause_category'])) ?></span>
                                        <?php if (!empty($fd['cause_detail'])): ?>
                                            <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px; max-width: 250px; line-height: 1.4;"><?= htmlspecialchars($fd['cause_detail']) ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="htf-action-tag" style="font-size: 0.78rem;"><?= htmlspecialchars(action_label_div($fd['action_category'])) ?></span>
                                        <?php if (!empty($fd['action_detail'])): ?>
                                            <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px; max-width: 250px; line-height: 1.4;"><?= htmlspecialchars($fd['action_detail']) ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($fd['lifecycle_status'] === 'open'): ?>
                                            <span class="htf-badge htf-badge--open">Open / Pending</span>
                                        <?php else: ?>
                                            <span class="htf-badge htf-badge--<?= htmlspecialchars($fd['outcome']) ?>" style="font-size: 0.72rem;">
                                                <?= ucfirst(htmlspecialchars($fd['outcome'])) ?>
                                            </span>
                                            <?php if (!empty($fd['outcome_detail'])): ?>
                                                <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px; max-width: 180px; line-height: 1.4;"><?= htmlspecialchars($fd['outcome_detail']) ?></p>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             DIVISION STRATEGIC FINDINGS & INVESTIGATION SECTION
             All output is htmlspecialchars()-escaped.
             Every query is scoped by exam_id only (no per-school data here).
        ═══════════════════════════════════════════════════════════════ -->

        <?php
        /* Flash messages */
        $div_finding_saved  = isset($_GET['finding_saved']);
        $div_outcome_saved  = isset($_GET['outcome_saved']);
        $div_finding_error  = isset($_GET['finding_error']) ? htmlspecialchars(urldecode($_GET['finding_error'])) : '';
        $div_outcome_error  = isset($_GET['outcome_error']) ? htmlspecialchars(urldecode($_GET['outcome_error'])) : '';
        ?>

        <?php if ($div_finding_saved): ?>
          <div class="htf-alert htf-alert--success" role="alert">Division strategic finding saved successfully.</div>
        <?php endif; ?>
        <?php if ($div_outcome_saved): ?>
          <div class="htf-alert htf-alert--success" role="alert">Outcome recorded — division finding is now closed.</div>
        <?php endif; ?>
        <?php if ($div_finding_error): ?>
          <div class="htf-alert htf-alert--error" role="alert">Error: <?= $div_finding_error ?></div>
        <?php endif; ?>
        <?php if ($div_outcome_error): ?>
          <div class="htf-alert htf-alert--error" role="alert">Error: <?= $div_outcome_error ?></div>
        <?php endif; ?>

        <!-- ─── DIVISION TREND BANNER ───────────────────────────────── -->
        <?php if (count($trend_values) >= 2): ?>
        <div class="htf-trend-banner htf-trend-banner--<?= $trend_direction ?>" role="region" aria-label="Division performance trend">
          <div class="htf-trend-banner-body">
            <strong class="htf-trend-banner-title">
              <?php if ($trend_direction === 'declining'): ?>
                Division Performance is Declining
              <?php elseif ($trend_direction === 'improving'): ?>
                Division Performance is Improving
              <?php else: ?>
                Division Performance is Stable
              <?php endif; ?>
            </strong>
            <span class="htf-trend-banner-sub">
              <?php
                $div_last_two = array_slice($trend_values, -2);
                $div_delta    = round($div_last_two[1] - $div_last_two[0], 1);
                $div_delta_str = ($div_delta > 0 ? '+' : '') . $div_delta . '% division average vs. previous exam';
              ?>
              <?= htmlspecialchars($div_delta_str) ?>
              <?php if ($trend_direction === 'declining'): ?>
                — record the strategic factors and the division intervention below.
              <?php elseif ($trend_direction === 'improving'): ?>
                — document what policy reforms drove improvement for future reference.
              <?php endif; ?>
            </span>
          </div>
        </div>
        <?php endif; ?>

        <!-- ─── PENDING DIVISION OUTCOME PROMPT ────────────────────── -->
        <?php if ($pending_div_outcome_finding): ?>
        <div class="htf-outcome-prompt" id="div-outcome-prompt">
          <div class="htf-outcome-prompt-header">
            <span class="htf-badge htf-badge--open">Division Action Awaiting Outcome</span>
            <strong>Did it work? Close out the <?= htmlspecialchars($pending_div_outcome_finding['exam_name']) ?> (<?= (int)$pending_div_outcome_finding['year'] ?>) division finding.</strong>
          </div>
          <p style="font-size:0.875rem; color: var(--text-muted); margin: 0 0 14px;">
            In that cycle the division identified <strong><?= htmlspecialchars(cause_label_div($pending_div_outcome_finding['cause_category'])) ?></strong>
            and responded with <strong><?= htmlspecialchars(action_label_div($pending_div_outcome_finding['action_category'])) ?></strong>.
            Now that new results are available, record whether the intervention made a difference division-wide.
          </p>
          <form method="POST" action="<?= BASE_URL ?>/admin/routes/save_div_outcome.php" class="htf-outcome-form">
            <input type="hidden" name="finding_id"      value="<?= (int)$pending_div_outcome_finding['finding_id'] ?>">
            <input type="hidden" name="current_exam_id" value="<?= $selected_exam_id ?>">
            <div class="htf-form-row">
              <div class="htf-form-group">
                <label class="htf-label" for="div-outcome-select">What happened division-wide? <span class="htf-required">*</span></label>
                <select name="outcome" id="div-outcome-select" class="rpt-filter-select" required>
                  <option value="">— Select outcome —</option>
                  <option value="improved">Improved — division average / pass rate went up</option>
                  <option value="no_change">No Change — roughly the same</option>
                  <option value="worsened">Worsened — division performance declined further</option>
                </select>
              </div>
              <div class="htf-form-group" style="flex:2;">
                <label class="htf-label" for="div-outcome-detail">Strategic notes (optional)</label>
                <input type="text" name="outcome_detail" id="div-outcome-detail"
                       class="rpt-filter-input"
                       placeholder="Any additional context about what changed division-wide…"
                       maxlength="500">
              </div>
            </div>
            <div style="margin-top:12px;">
              <button type="submit" class="btn btn-primary">Record Outcome &amp; Close Division Finding</button>
            </div>
          </form>
        </div>
        <?php endif; ?>

        <!-- ─── DIVISION SUGGESTION PANEL ──────────────────────────── -->
        <?php if (!empty($div_suggestions)): ?>
        <details class="htf-suggestion-panel" open>
          <summary class="htf-suggestion-summary">
            <span>
              <?= count($div_suggestions) ?> similar division-level situation<?= count($div_suggestions) !== 1 ? 's' : '' ?> in history
              — see what was tried and what the outcome was
            </span>
            <span class="htf-suggestion-chevron">▾</span>
          </summary>
          <div class="htf-suggestion-list">
            <?php foreach ($div_suggestions as $sg): ?>
            <div class="htf-suggestion-item htf-suggestion-item--<?= htmlspecialchars($sg['outcome']) ?>">
              <div class="htf-suggestion-meta">
                <span class="htf-badge <?= $sg['outcome'] === 'improved' ? 'htf-badge--improved' : ($sg['outcome'] === 'worsened' ? 'htf-badge--worsened' : 'htf-badge--nochange') ?>">
                  <?= $sg['outcome'] === 'improved' ? 'Worked' : ($sg['outcome'] === 'worsened' ? 'Worsened' : 'No Change') ?>
                </span>
                <span style="font-size:0.8rem; color:var(--text-muted);">
                  <?= htmlspecialchars($sg['exam_name']) ?> (<?= (int)$sg['year'] ?>) · Division avg was <?= number_format((float)$sg['avg_score_pct'], 1) ?>%
                </span>
              </div>
              <div class="htf-suggestion-content">
                <div class="htf-suggestion-col">
                  <span class="htf-suggestion-label">Strategic factor identified:</span>
                  <span class="htf-cause-tag"><?= htmlspecialchars(cause_label_div($sg['cause_category'])) ?></span>
                  <?php if (!empty($sg['cause_detail'])): ?>
                    <p class="htf-detail-text"><?= htmlspecialchars($sg['cause_detail']) ?></p>
                  <?php endif; ?>
                </div>
                <div class="htf-suggestion-col">
                  <span class="htf-suggestion-label">Division action taken:</span>
                  <span class="htf-action-tag"><?= htmlspecialchars(action_label_div($sg['action_category'])) ?></span>
                  <?php if (!empty($sg['action_detail'])): ?>
                    <p class="htf-detail-text"><?= htmlspecialchars($sg['action_detail']) ?></p>
                  <?php endif; ?>
                </div>
                <?php if (!empty($sg['outcome_detail'])): ?>
                <div class="htf-suggestion-col">
                  <span class="htf-suggestion-label">Division-wide observation:</span>
                  <p class="htf-detail-text"><?= htmlspecialchars($sg['outcome_detail']) ?></p>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </details>
        <?php endif; ?>

        <!-- ─── CURRENT DIVISION FINDING SUMMARY (already logged) ──── -->
        <?php if ($current_div_finding): ?>
        <div class="htf-current-finding">
          <div class="htf-current-finding-header">
            <span class="htf-section-label">Division Strategic Finding — This Exam Cycle</span>
            <span class="htf-badge <?= $current_div_finding['lifecycle_status'] === 'open' ? 'htf-badge--open' : 'htf-badge--closed' ?>">
              <?= $current_div_finding['lifecycle_status'] === 'open' ? 'Open' : 'Closed' ?>
            </span>
          </div>
          <div class="htf-finding-body">
            <div class="htf-finding-col">
              <div class="htf-finding-field-label">Strategic Factor</div>
              <div class="htf-finding-field-value">
                <span class="htf-cause-tag"><?= htmlspecialchars(cause_label_div($current_div_finding['cause_category'])) ?></span>
                <?php if (!empty($current_div_finding['cause_detail'])): ?>
                  <p class="htf-detail-text"><?= htmlspecialchars($current_div_finding['cause_detail']) ?></p>
                <?php endif; ?>
              </div>
            </div>
            <div class="htf-finding-col">
              <div class="htf-finding-field-label">Division Intervention</div>
              <div class="htf-finding-field-value">
                <span class="htf-action-tag"><?= htmlspecialchars(action_label_div($current_div_finding['action_category'])) ?></span>
                <?php if (!empty($current_div_finding['action_detail'])): ?>
                  <p class="htf-detail-text"><?= htmlspecialchars($current_div_finding['action_detail']) ?></p>
                <?php endif; ?>
              </div>
            </div>
            <?php if (!empty($current_div_finding['outcome'])): ?>
            <div class="htf-finding-col">
              <div class="htf-finding-field-label">Recorded Outcome</div>
              <div class="htf-finding-field-value">
                <span class="htf-badge <?= $current_div_finding['outcome'] === 'improved' ? 'htf-badge--improved' : ($current_div_finding['outcome'] === 'worsened' ? 'htf-badge--worsened' : 'htf-badge--nochange') ?>">
                  <?= htmlspecialchars(outcome_label_div($current_div_finding['outcome'])) ?>
                </span>
                <?php if (!empty($current_div_finding['outcome_detail'])): ?>
                  <p class="htf-detail-text"><?= htmlspecialchars($current_div_finding['outcome_detail']) ?></p>
                <?php endif; ?>
              </div>
            </div>
            <?php endif; ?>
          </div>
          <?php if ($current_div_finding['lifecycle_status'] === 'open'): ?>
          <!-- Allow editing an open division finding -->
          <details style="margin-top:14px;">
            <summary style="cursor:pointer; font-size:0.85rem; color:var(--info-color); font-weight:600;">Edit this division finding</summary>
            <div style="margin-top:12px;">
              <?php $prefill = $current_div_finding; ?>
              <?php include __DIR__ . '/partials/div_finding_form.php'; ?>
            </div>
          </details>
          <?php endif; ?>
        </div>
        <?php else: ?>

        <!-- ─── LOG NEW DIVISION FINDING FORM ──────────────────────── -->
        <div class="htf-log-form-card" id="log-div-finding">
          <div class="htf-section-header">
            <span class="htf-section-label">
              <?php if ($trend_direction === 'declining'): ?>Log Division Strategic Investigation — What Was Found?
              <?php elseif ($trend_direction === 'improving'): ?>Record What Division Reforms Worked
              <?php else: ?>Log a Division Strategic Finding
              <?php endif; ?>
            </span>
            <span style="font-size:0.8rem; color:var(--text-muted);">This finding will be searchable and surfaced as institutional evidence in future cycles.</span>
          </div>
          <?php $prefill = null; ?>
          <?php include __DIR__ . '/partials/div_finding_form.php'; ?>
        </div>

        <?php endif; /* $current_div_finding */ ?>

        <!-- Link to full division history -->
        <div style="text-align:right; margin-top: 12px; margin-bottom: 24px;">
          <a href="<?= BASE_URL ?>/admin/reports/div_findings_history.php<?= $selected_exam_id ? '?exam_id=' . $selected_exam_id : '' ?>" class="btn btn-secondary btn-small">
            View Full Division Findings History →
          </a>
        </div>

        <!-- Strategic Recommendations -->
        <div class="rpt-rec-card" style="margin-top: 24px;">
            <h4>Strategic Education Division Action Interventions</h4>
            <ul class="rpt-rec-list">
                <?php
                $recs = stats_recommendations($division_avg, $pass_rate, stats_trend($trend_values[count($trend_values)-2] ?? $division_avg, $division_avg), 'division');
                foreach ($recs as $rec):
                ?>
                    <li><?= htmlspecialchars($rec) ?></li>
                <?php endforeach; ?>
                <li>Initiate special inspection audits for the <?= count($risk_schools) ?> institutions flagged in the Critical Risk category.</li>
                <li>Conduct cross-district peer seminars led by instructors from <strong><?= htmlspecialchars($best_district) ?></strong> district.</li>
            </ul>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../common/footer.php'; ?>

</body>
</html>
