<?php
/* ════════════════════════════════════════════════════════════════
   admin/reports/subject_report.php
   EDM/Admin: Subject Syllabus Intelligence Dashboard
   ────────────────────────────────────────────────────────────────
   Handles large volume of schools via:
   • Hero Executive Summary
   • Pass/Fail Ratio & Grade Distribution
   • Historical Subject Performance Trend & Predictive Insights
   • Regional & District Performance Standings
   • Institutional Standings (Top & Bottom Summaries + Full Paginated Table)
   • District Filtering (Chitipa, Karonga, Likoma, Mzimba, Nkhata Bay, Rumphi)
   • School Name Search
   • 10-per-page Pagination
   • Subject Diagnostic Action Recommendations
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

// ── Dropdown Data ────────────────────────────────────────────────
$subjects_list = $conn->query("SELECT subject_id, subject_name, subject_code, category FROM subjects WHERE status='active' ORDER BY subject_name ASC")->fetch_all(MYSQLI_ASSOC);

$exams_list = $conn->query("
    SELECT DISTINCT e.exam_id, e.exam_name, e.year 
    FROM results r
    JOIN exams e ON r.exam_id = e.exam_id
    ORDER BY e.year DESC, e.exam_name ASC
")->fetch_all(MYSQLI_ASSOC);

$districts_list = $conn->query("SELECT DISTINCT district FROM schools WHERE status='active' ORDER BY district ASC")->fetch_all(MYSQLI_ASSOC);

// Filters
$selected_subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$selected_exam_id    = isset($_GET['exam_id'])    ? (int)$_GET['exam_id']    : 0;
$filter_dist         = trim($_GET['district'] ?? '');
$search_query        = trim($_GET['search'] ?? '');
$current_page        = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page            = 10;

if ($selected_subject_id <= 0 && !empty($subjects_list)) {
    $selected_subject_id = (int)$subjects_list[0]['subject_id'];
}
if ($selected_exam_id <= 0 && !empty($exams_list)) {
    $selected_exam_id = (int)$exams_list[0]['exam_id'];
}

// Fetch subject detail
$subject_info = null;
foreach ($subjects_list as $sj) {
    if ((int)$sj['subject_id'] === $selected_subject_id) {
        $subject_info = $sj;
        break;
    }
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

/* ════════════════════════════════════════════════════════════════
   DATA COLLECTION (scoped to subject and exam)
   ════════════════════════════════════════════════════════════════ */
$scores = [];
$stats = null;
$pass_fail = null;
$total_candidates = 0;
$grade_dist = [];
$district_comparisons = [];
$top_schools = [];
$weak_schools = [];
$all_school_standings = [];
$paginated_schools = [];
$total_school_records = 0;
$pagination = null;
$history = [];
$hist_labels = [];
$hist_values = [];
$predicted_avg = 0.0;
$prev_exam_subject_avg = null;

if ($selected_subject_id > 0 && $selected_exam_id > 0) {
    // ── 1. Fetch individual approved mark scores ─────────────────
    $marks_stmt = $conn->prepare("
        SELECT m.score, s.school_id, sch.school_name, sch.district
        FROM marks m
        JOIN students s ON m.student_id = s.student_id
        JOIN schools sch ON s.school_id = sch.school_id
        WHERE m.subject_id = ? AND m.exam_id = ? AND m.status = 'approved'
    ");
    $marks_stmt->bind_param("ii", $selected_subject_id, $selected_exam_id);
    $marks_stmt->execute();
    $marks_data = $marks_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $marks_stmt->close();

    $scores = array_map(fn($m) => (float)$m['score'], $marks_data);
    $total_candidates = count($scores);

    if ($total_candidates > 0) {
        $stats     = stats_summary($scores);
        $pass_fail = stats_pass_rate($scores, 40.0);
        $grade_dist = stats_grade_distribution($scores);

        // ── 2. District comparisons ─────────────────────────────────
        $dist_stmt = $conn->prepare("
            SELECT sch.district, 
                   AVG(m.score) as avg_score, 
                   COUNT(*) as count,
                   SUM(CASE WHEN m.score >= 40 THEN 1 ELSE 0 END) AS passed
            FROM marks m
            JOIN students s ON m.student_id = s.student_id
            JOIN schools sch ON s.school_id = sch.school_id
            WHERE m.subject_id = ? AND m.exam_id = ? AND m.status = 'approved'
            GROUP BY sch.district
            ORDER BY avg_score DESC
        ");
        $dist_stmt->bind_param("ii", $selected_subject_id, $selected_exam_id);
        $dist_stmt->execute();
        $district_comparisons = $dist_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $dist_stmt->close();

        foreach ($district_comparisons as &$dc) {
            $dc['avg_score'] = round((float)$dc['avg_score'], 1);
            $dc['pass_rate'] = $dc['count'] > 0 ? round(($dc['passed'] / $dc['count']) * 100, 1) : 0;
            $dc['category']  = stats_performance_category($dc['avg_score']);
        }
        unset($dc);

        // ── 3. Full School Standings for Subject (With Ranking, Search & District Filter) ─
        $all_sch_stmt = $conn->prepare("
            SELECT sch.school_id, sch.school_name, sch.district,
                   AVG(m.score) as avg_score, 
                   COUNT(*) as candidates,
                   SUM(CASE WHEN m.score >= 40 THEN 1 ELSE 0 END) AS passed
            FROM marks m
            JOIN students s ON m.student_id = s.student_id
            JOIN schools sch ON s.school_id = sch.school_id
            WHERE m.subject_id = ? AND m.exam_id = ? AND m.status = 'approved'
            GROUP BY sch.school_id
            ORDER BY avg_score DESC
        ");
        $all_sch_stmt->bind_param("ii", $selected_subject_id, $selected_exam_id);
        $all_sch_stmt->execute();
        $all_school_standings = $all_sch_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $all_sch_stmt->close();

        // Assign global subject ranks
        foreach ($all_school_standings as $idx => &$sch) {
            $sch['global_rank'] = $idx + 1;
            $sch['avg_score']   = round((float)$sch['avg_score'], 1);
            $sch['pass_rate']   = $sch['candidates'] > 0 ? round(($sch['passed'] / $sch['candidates']) * 100, 1) : 0;
            $sch['category']    = stats_performance_category($sch['avg_score']);
        }
        unset($sch);

        // Top 5 & Weak 5 summaries for highlights
        $top_schools  = array_slice($all_school_standings, 0, 5);
        $weak_schools = array_slice($all_school_standings, -5);
        $weak_schools = array_reverse($weak_schools);

        // Apply District & Search filtering to full standings table
        $filtered_schools = array_filter($all_school_standings, function($sch) use ($filter_dist, $search_query) {
            if ($filter_dist !== '' && strtolower($sch['district']) !== strtolower($filter_dist)) {
                return false;
            }
            if ($search_query !== '' && stripos($sch['school_name'], $search_query) === false) {
                return false;
            }
            return true;
        });
        $filtered_schools = array_values($filtered_schools);

        $total_school_records = count($filtered_schools);
        $pagination = paginate($total_school_records, $current_page, $per_page);
        $offset     = ($pagination['page'] - 1) * $per_page;

        $paginated_schools = array_slice($filtered_schools, $offset, $per_page);

        // ── 5. Subject trend across exam history ─────────────────────
        $hist_stmt = $conn->prepare("
            SELECT e.exam_name, e.year, AVG(m.score) as avg_score
            FROM marks m
            JOIN exams e ON m.exam_id = e.exam_id
            WHERE m.subject_id = ? AND m.status = 'approved'
            GROUP BY e.exam_id
            ORDER BY e.year ASC, e.start_date ASC
        ");
        $hist_stmt->bind_param("i", $selected_subject_id);
        $hist_stmt->execute();
        $history = $hist_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $hist_stmt->close();

        $hist_labels = array_map(fn($h) => $h['exam_name'] . ' (' . $h['year'] . ')', $history);
        $hist_values = array_map(fn($h) => (float)$h['avg_score'], $history);

        // Previous exam comparison
        if (count($hist_values) >= 2) {
            $prev_exam_subject_avg = round($hist_values[count($hist_values) - 2], 1);
        }

        // Predictive average calculation
        $predicted_avg = $stats['mean'];
        if (count($hist_values) >= 2) {
            $ma = stats_moving_average($hist_values, 3);
            $predicted_avg = round($ma[count($ma)-1] + (($hist_values[count($hist_values)-1] - $hist_values[count($hist_values)-2]) * 0.4), 1);
        }
        $predicted_avg = max(0, min(100, $predicted_avg));
    }
}

$conn->close();

// ── Chart Builders ────────────────────────────────────────────────
$grade_histogram   = '';
$pass_fail_donut   = '';
$district_bar_chart= '';
$history_line_chart= '';

if ($stats && $total_candidates > 0) {
    $grade_histogram = chart_grade_histogram($grade_dist, ['height' => 220]);

    $pass_fail_donut = chart_donut([
        ['label' => 'Passed (≥40%)', 'value' => $pass_fail['pass_count'], 'color' => '#22c55e'],
        ['label' => 'Failed (<40%)', 'value' => $pass_fail['fail_count'], 'color' => '#ef4444'],
    ], ['size' => 170, 'center_text' => $pass_fail['pass_rate'] . '%', 'center_subtext' => 'Pass Rate']);

    if (!empty($district_comparisons)) {
        $d_labels = array_column($district_comparisons, 'district');
        $d_values = array_column($district_comparisons, 'avg_score');
        $district_bar_chart = chart_bars($d_labels, $d_values, ['height' => 200, 'auto_color' => true]);
    }

    if (!empty($hist_values)) {
        $history_line_chart = chart_line($hist_labels, [
            ['label' => $subject_info['subject_name'] . ' Mean', 'values' => $hist_values, 'color' => '#f59e0b']
        ], ['height' => 200]);
    }
}

function perf_color_sub(string $cat): string {
    return match($cat) {
        'Excellent' => '#16a34a', 'Good' => '#22c55e', 'Satisfactory' => '#eab308',
        'Needs Improvement' => '#f97316', 'Critical' => '#dc2626', default => '#64748b'
    };
}
function perf_bg_sub(string $cat): string {
    return match($cat) {
        'Excellent' => '#f0fdf4', 'Good' => '#f0fdf4', 'Satisfactory' => '#fefce8',
        'Needs Improvement' => '#fff7ed', 'Critical' => '#fef2f2', default => '#f8fafc'
    };
}

$portal_title = 'NED-SEMS | Subject Intelligence Report';
$module_css   = 'admin';
include __DIR__ . '/../../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subject Syllabus Intelligence Report | NED-SEMS</title>
    <meta name="description" content="Subject syllabus performance report with district rankings, historical trend tracking, institutional standings and diagnostic recommendations">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
    <style>
      .subj-hero { display: grid; grid-template-columns: 1fr 1fr; gap: 0; border-radius: 10px; overflow: hidden; margin-bottom: 28px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
      .subj-hero-left { background: linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%); color: #fff; padding: 28px 32px; }
      .subj-hero-right { background: #ffffff; padding: 28px 32px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
      .hero-subj-name { font-size: 1.6rem; font-weight: 800; margin: 0 0 4px 0; letter-spacing: -0.5px; }
      .hero-subj-sub { font-size: 0.88rem; color: #94a3b8; margin: 0 0 18px 0; }
      .hero-stat { display: flex; flex-direction: column; }
      .hero-stat-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; font-weight: 600; margin-bottom: 2px; }
      .hero-stat-value { font-size: 1.8rem; font-weight: 800; letter-spacing: -1px; }
      .hero-stat-delta { font-size: 0.78rem; margin-top: 2px; }
      .hero-kpi { display: flex; flex-direction: column; padding: 12px 14px; border-radius: 8px; background: #f8fafc; border: 1px solid #e2e8f0; }
      .hero-kpi-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; font-weight: 700; margin-bottom: 4px; }
      .hero-kpi-value { font-size: 1.35rem; font-weight: 800; color: #0f172a; }
      .hero-kpi-sub { font-size: 0.75rem; color: #94a3b8; margin-top: 2px; }
      .section-divider { display: flex; align-items: center; gap: 12px; margin: 32px 0 20px 0; }
      .section-divider h3 { font-size: 1rem; font-weight: 700; color: #0f172a; margin: 0; white-space: nowrap; }
      .section-divider::after { content: ''; flex: 1; height: 2px; background: linear-gradient(90deg, #e2e8f0 0%, transparent 100%); }
      .section-divider-badge { font-size: 0.7rem; background: #e0f2fe; color: #0369a1; padding: 2px 10px; border-radius: 12px; font-weight: 700; white-space: nowrap; }
      .district-heatmap { width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
      .district-heatmap th { background: #0f172a; color: #fff; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.7px; padding: 10px 12px; font-weight: 700; }
      .district-heatmap td { padding: 10px 12px; font-size: 0.85rem; border-bottom: 1px solid #f1f5f9; }
      .district-heatmap tr:last-child td { border-bottom: none; }
      .district-heatmap tr:hover td { background: #f0f9ff; }
      .heatcell { display: inline-block; padding: 3px 10px; border-radius: 6px; font-weight: 700; font-size: 0.82rem; min-width: 48px; text-align: center; }
      .rank-badge { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; font-size: 0.75rem; font-weight: 800; }
      .rank-gold { background: #fef3c7; color: #92400e; }
      .rank-silver { background: #e2e8f0; color: #334155; }
      .rank-bronze { background: #fed7aa; color: #9a3412; }
      .rank-default { background: #f1f5f9; color: #64748b; }
      @media (max-width: 900px) {
        .subj-hero { grid-template-columns: 1fr; }
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
            <h2 class="print-title">Subject Intelligence Report: <?= htmlspecialchars($subject_info['subject_name'] ?? '') ?></h2>
            <div class="print-meta">Generated: <?= date('Y-m-d H:i') ?> | Northern Education Division | Administrator</div>
        </div>

        <div class="page-header">
            <div>
                <h2 class="page-title">Subject Reports &amp; Analytics</h2>
                <p class="page-subtitle">Syllabus performance analysis — district comparisons, historical trends, predictive insights and institutional rankings</p>
            </div>
            <div class="header-actions">
                <button onclick="window.print()" class="btn btn-secondary">Print Report</button>
            </div>
        </div>

        <!-- Filter panel -->
        <div class="rpt-filter-panel no-print">
            <form method="GET" class="rpt-filter-form">
                <div class="rpt-filter-group">
                    <label class="rpt-filter-label" for="sf-subject">Syllabus Subject</label>
                    <select name="subject_id" id="sf-subject" class="rpt-filter-select" onchange="this.form.submit()">
                        <?php foreach ($subjects_list as $sj): ?>
                            <option value="<?= $sj['subject_id'] ?>" <?= $selected_subject_id === (int)$sj['subject_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sj['subject_name']) ?> (<?= htmlspecialchars($sj['subject_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="rpt-filter-group">
                    <label class="rpt-filter-label" for="sf-exam">Examination Paper</label>
                    <select name="exam_id" id="sf-exam" class="rpt-filter-select" onchange="this.form.submit()">
                        <?php foreach ($exams_list as $ex): ?>
                            <option value="<?= $ex['exam_id'] ?>" <?= $selected_exam_id === (int)$ex['exam_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ex['exam_name']) ?> (<?= htmlspecialchars($ex['year']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="rpt-filter-group">
                    <label class="rpt-filter-label" for="sf-district">District Filter</label>
                    <select name="district" id="sf-district" class="rpt-filter-select" onchange="this.form.submit()">
                        <option value="">All Districts</option>
                        <?php foreach ($districts_list as $d): ?>
                            <option value="<?= htmlspecialchars($d['district']) ?>" <?= $filter_dist === $d['district'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['district']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="rpt-filter-group">
                    <label class="rpt-filter-label" for="sf-search">Search School</label>
                    <input type="text" name="search" id="sf-search" class="rpt-filter-input" placeholder="School name..." value="<?= htmlspecialchars($search_query) ?>">
                </div>

                <div class="rpt-filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="subject_report.php?subject_id=<?= $selected_subject_id ?>&exam_id=<?= $selected_exam_id ?>" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <?php if ($selected_subject_id > 0 && $stats): ?>

        <!-- ═══ HERO EXECUTIVE SUMMARY ═══ -->
        <div class="subj-hero">
            <div class="subj-hero-left">
                <h1 class="hero-subj-name"><?= htmlspecialchars($subject_info['subject_name'] ?? '') ?></h1>
                <p class="hero-subj-sub">Code: <?= htmlspecialchars($subject_info['subject_code'] ?? '') ?> · Category: <?= htmlspecialchars(ucfirst($subject_info['category'] ?? 'Theory')) ?> · <?= htmlspecialchars($exam_info['exam_name'] ?? '') ?> (<?= htmlspecialchars($exam_info['year'] ?? '') ?>)</p>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="hero-stat">
                        <span class="hero-stat-label">Subject Average Score</span>
                        <span class="hero-stat-value" style="color: <?= $stats['mean'] >= 50 ? '#4ade80' : '#f87171' ?>;"><?= $stats['mean'] ?>%</span>
                        <?php if ($prev_exam_subject_avg !== null): ?>
                            <?php
                                $delta = round($stats['mean'] - $prev_exam_subject_avg, 1);
                                $delta_sign = $delta >= 0 ? '+' : '';
                                $delta_color = $delta >= 0 ? '#4ade80' : '#f87171';
                            ?>
                            <span class="hero-stat-delta" style="color: <?= $delta_color ?>;"><?= $delta_sign . $delta ?>% vs previous exam cycle</span>
                        <?php endif; ?>
                    </div>
                    <div class="hero-stat">
                        <span class="hero-stat-label">Pass Rate (≥40%)</span>
                        <span class="hero-stat-value"><?= $pass_fail['pass_rate'] ?>%</span>
                        <span class="hero-stat-delta">Median: <?= $stats['median'] ?>% · Std Dev: <?= $stats['std_dev'] ?></span>
                    </div>
                </div>
            </div>
            <div class="subj-hero-right">
                <div class="hero-kpi">
                    <span class="hero-kpi-label">Candidates Assessed</span>
                    <span class="hero-kpi-value"><?= number_format($total_candidates) ?></span>
                    <span class="hero-kpi-sub">Approved marks entries</span>
                </div>
                <div class="hero-kpi">
                    <span class="hero-kpi-label">Passed / Failed</span>
                    <span class="hero-kpi-value" style="color: #16a34a;"><?= number_format($pass_fail['pass_count']) ?> <span style="font-size:0.9rem; color:#dc2626;">/ <?= number_format($pass_fail['fail_count']) ?></span></span>
                    <span class="hero-kpi-sub">Fail rate: <?= $pass_fail['fail_rate'] ?>%</span>
                </div>
                <div class="hero-kpi">
                    <span class="hero-kpi-label">Highest / Lowest Mark</span>
                    <span class="hero-kpi-value" style="color:#0f172a;"><?= $stats['max'] ?>% <span style="font-size:0.9rem; color:#64748b;">/ <?= $stats['min'] ?>%</span></span>
                    <span class="hero-kpi-sub">Score range: <?= $stats['range'] ?>%</span>
                </div>
                <div class="hero-kpi">
                    <span class="hero-kpi-label">Predicted Next Cycle</span>
                    <span class="hero-kpi-value" style="color: #2563eb;"><?= $predicted_avg ?>%</span>
                    <span class="hero-kpi-sub">Based on moving average</span>
                </div>
            </div>
        </div>

        <!-- ═══ SECTION 1: PASS/FAIL & GRADE DISTRIBUTION ═══ -->
        <div class="section-divider">
            <h3>Pass/Fail Ratio &amp; Grade Distribution</h3>
            <span class="section-divider-badge"><?= $total_candidates ?> candidates</span>
        </div>

        <div class="rpt-chart-grid">
            <div class="rpt-chart-box">
                <h3 class="rpt-chart-title">Pass vs Failure Ratio</h3>
                <p class="rpt-chart-sub">Doughnut representation of subject pass / fail ratio (40% threshold)</p>
                <div class="rpt-chart-body" style="text-align: center;">
                    <?= $pass_fail_donut ?>
                </div>
            </div>
            <div class="rpt-chart-box">
                <h3 class="rpt-chart-title">Grade Distribution (Malawi MSCE Scale)</h3>
                <p class="rpt-chart-sub">Grade distribution histogram from Distinction (1) to Fail (9)</p>
                <div class="rpt-chart-body">
                    <?= $grade_histogram ?>
                </div>
            </div>
        </div>

        <!-- ═══ SECTION 2: HISTORICAL TREND & PREDICTIVE INSIGHTS ═══ -->
        <div class="section-divider">
            <h3>Historical Performance Trend &amp; Predictive Insights</h3>
            <span class="section-divider-badge">Exam history</span>
        </div>

        <div class="rpt-chart-grid">
            <div class="rpt-chart-box" style="grid-column: span 2;">
                <h3 class="rpt-chart-title">Subject Performance Across Examinations</h3>
                <p class="rpt-chart-sub">Historical trend of average marks in <?= htmlspecialchars($subject_info['subject_name']) ?></p>
                <div class="rpt-chart-body">
                    <?php if (!empty($history_line_chart)): ?>
                        <?= $history_line_chart ?>
                    <?php else: ?>
                        <p style="text-align: center; color: var(--text-muted); padding: 20px;">No historical examination data available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 24px; padding: 20px;">
            <h4 style="margin: 0 0 12px 0; font-size: 0.95rem; font-weight: 700; color: #0f172a;">Syllabus Predictive Analysis</h4>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 14px; border-radius: 8px;">
                    <div style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 4px;">Current Mean Score</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a;"><?= $stats['mean'] ?>%</div>
                </div>
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; padding: 14px; border-radius: 8px;">
                    <div style="font-size: 0.75rem; text-transform: uppercase; color: #1e40af; font-weight: 700; margin-bottom: 4px;">Predicted Next Mean</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #2563eb;"><?= $predicted_avg ?>%</div>
                </div>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 14px; border-radius: 8px;">
                    <div style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 4px;">Projected Trajectory</div>
                    <div style="font-size: 1.1rem; font-weight: 800; margin-top: 4px;">
                        <?php if ($predicted_avg > $stats['mean']): ?>
                            <span style="color: #16a34a;">Positive Trajectory (+<?= round($predicted_avg - $stats['mean'], 1) ?>%)</span>
                        <?php elseif ($predicted_avg < $stats['mean']): ?>
                            <span style="color: #dc2626;">Declining Trajectory (<?= round($predicted_avg - $stats['mean'], 1) ?>%)</span>
                        <?php else: ?>
                            <span style="color: #64748b;">Stable Trajectory</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ SECTION 3: DISTRICT PERFORMANCE COMPARISON ═══ -->
        <?php if (!empty($district_comparisons)): ?>
        <div class="section-divider">
            <h3>District Performance Standings</h3>
            <span class="section-divider-badge"><?= count($district_comparisons) ?> districts</span>
        </div>

        <div class="rpt-chart-grid">
            <div class="rpt-chart-box" style="grid-column: span 2;">
                <h3 class="rpt-chart-title">District Average Score Comparison</h3>
                <p class="rpt-chart-sub">Comparison of subject average scores across districts</p>
                <div class="rpt-chart-body"><?= $district_bar_chart ?></div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 24px; overflow-x: auto;">
            <table class="district-heatmap">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>District</th>
                        <th style="text-align: center;">Candidates Sat</th>
                        <th style="text-align: center;">Passed (≥40%)</th>
                        <th style="text-align: center;">Subject Average</th>
                        <th style="text-align: center;">Pass Rate</th>
                        <th style="text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($district_comparisons as $i => $dc): ?>
                    <tr>
                        <td>
                            <?php $rc = $i === 0 ? 'rank-gold' : ($i === 1 ? 'rank-silver' : ($i === 2 ? 'rank-bronze' : 'rank-default')); ?>
                            <span class="rank-badge <?= $rc ?>"><?= $i + 1 ?></span>
                        </td>
                        <td style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($dc['district']) ?></td>
                        <td style="text-align: center;"><?= $dc['count'] ?></td>
                        <td style="text-align: center; color: #16a34a; font-weight: 600;"><?= $dc['passed'] ?></td>
                        <td style="text-align: center;">
                            <span class="heatcell" style="background: <?= perf_bg_sub($dc['category']) ?>; color: <?= perf_color_sub($dc['category']) ?>;">
                                <?= $dc['avg_score'] ?>%
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <span class="heatcell" style="background: <?= $dc['pass_rate'] >= 50 ? '#f0fdf4' : '#fef2f2' ?>; color: <?= $dc['pass_rate'] >= 50 ? '#16a34a' : '#dc2626' ?>;">
                                <?= $dc['pass_rate'] ?>%
                            </span>
                        </td>
                        <td style="text-align: center;"><?= stats_badge($dc['category']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- ═══ SECTION 4: INSTITUTIONAL STANDINGS (TOP/BOTTOM + FULL PAGINATED TABLE) ═══ -->
        <?php if (!empty($all_school_standings)): ?>
        <div class="section-divider">
            <h3>Institutional Performance Rankings</h3>
            <span class="section-divider-badge"><?= count($all_school_standings) ?> schools offering <?= htmlspecialchars($subject_info['subject_name']) ?></span>
        </div>

        <!-- Highlights (Top 5 vs Bottom 5) -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
            <!-- Top 5 -->
            <div class="card" style="padding: 0; overflow: hidden;">
                <div style="background: linear-gradient(135deg, #065f46, #047857); color: #fff; padding: 14px 18px;">
                    <h4 style="margin: 0; font-size: 0.9rem; font-weight: 700;">Top 5 Performing Schools in <?= htmlspecialchars($subject_info['subject_name']) ?></h4>
                </div>
                <div style="padding: 0;">
                    <?php foreach ($top_schools as $i => $ts): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 18px; border-bottom: 1px solid #f1f5f9;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <?php $rc = $i === 0 ? 'rank-gold' : ($i === 1 ? 'rank-silver' : ($i === 2 ? 'rank-bronze' : 'rank-default')); ?>
                            <span class="rank-badge <?= $rc ?>"><?= $i + 1 ?></span>
                            <div>
                                <div style="font-weight: 700; font-size: 0.88rem; color: #0f172a;"><?= htmlspecialchars($ts['school_name']) ?></div>
                                <div style="font-size: 0.75rem; color: #64748b;"><?= htmlspecialchars($ts['district']) ?> · <?= $ts['candidates'] ?> candidates</div>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 800; font-size: 1rem; color: #16a34a;"><?= number_format($ts['avg_score'], 1) ?>%</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Bottom 5 -->
            <div class="card" style="padding: 0; overflow: hidden;">
                <div style="background: linear-gradient(135deg, #991b1b, #dc2626); color: #fff; padding: 14px 18px;">
                    <h4 style="margin: 0; font-size: 0.9rem; font-weight: 700;">Bottom 5 Schools (Need Support)</h4>
                </div>
                <div style="padding: 0;">
                    <?php foreach ($weak_schools as $i => $ws): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 18px; border-bottom: 1px solid #f1f5f9;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span class="rank-badge rank-default"><?= $ws['global_rank'] ?></span>
                            <div>
                                <div style="font-weight: 700; font-size: 0.88rem; color: #0f172a;"><?= htmlspecialchars($ws['school_name']) ?></div>
                                <div style="font-size: 0.75rem; color: #64748b;"><?= htmlspecialchars($ws['district']) ?> · <?= $ws['candidates'] ?> candidates</div>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 800; font-size: 1rem; color: #dc2626;"><?= number_format($ws['avg_score'], 1) ?>%</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Full Searchable & Paginated School Table for Subject -->
        <div class="card">
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <div>
                    <h3 style="margin: 0;">Complete School Standings for <?= htmlspecialchars($subject_info['subject_name']) ?></h3>
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin: 4px 0 0 0;">
                        Showing <strong><?= count($paginated_schools) ?></strong> of <strong><?= $total_school_records ?></strong> schools
                        <?= $filter_dist ? "in <strong>" . htmlspecialchars($filter_dist) . "</strong>" : "" ?>
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
                            <th class="rank-col">Rank</th>
                            <th>School Name</th>
                            <th>District</th>
                            <th class="val-col">Candidates Sat</th>
                            <th class="val-col">Passed (≥40%)</th>
                            <th class="val-col">Subject Mean</th>
                            <th class="val-col">Pass Rate</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($paginated_schools)): ?>
                            <tr>
                                <td colspan="8" class="empty-state">No school standings found matching your filter criteria for this subject.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($paginated_schools as $sch): ?>
                                <?php $rc = $sch['global_rank'] === 1 ? 'rank-gold' : ($sch['global_rank'] === 2 ? 'rank-silver' : ($sch['global_rank'] === 3 ? 'rank-bronze' : 'rank-default')); ?>
                                <tr>
                                    <td class="rank-col">
                                        <span class="rank-badge <?= $rc ?>"><?= $sch['global_rank'] ?></span>
                                    </td>
                                    <td style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($sch['school_name']) ?></td>
                                    <td><?= htmlspecialchars($sch['district']) ?></td>
                                    <td class="val-col"><?= $sch['candidates'] ?></td>
                                    <td class="val-col" style="color: #16a34a; font-weight: 600;"><?= $sch['passed'] ?></td>
                                    <td class="val-col" style="font-weight: 800; color: <?= $sch['avg_score'] >= 50 ? '#16a34a' : '#dc2626' ?>;">
                                        <?= number_format($sch['avg_score'], 1) ?>%
                                    </td>
                                    <td class="val-col" style="font-weight: 700;">
                                        <?= number_format($sch['pass_rate'], 1) ?>%
                                    </td>
                                    <td><?= stats_badge($sch['category']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination controls -->
            <?php if ($pagination && $pagination['total_pages'] > 1): ?>
                <div style="margin-top: 16px;">
                    <?= render_pagination($pagination, 'subject_report.php') ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ═══ SECTION 5: ACTION RECOMMENDATIONS ═══ -->
        <div class="rpt-rec-card">
            <h4>Subject Diagnostic &amp; Curriculum Action Recommendations</h4>
            <ul class="rpt-rec-list">
                <?php
                $recs = stats_recommendations($stats['mean'], $pass_fail['pass_rate'], stats_trend($hist_values[count($hist_values)-2] ?? $stats['mean'], $stats['mean']), 'subject');
                foreach ($recs as $rec):
                ?>
                    <li><?= htmlspecialchars($rec) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php else: ?>
            <div class="card empty-state" style="padding:48px; text-align: center;">
                <p style="color: var(--text-muted); font-size: 1rem;">Select a subject and examination paper above to generate the intelligence report.</p>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/../../common/footer.php'; ?>

</body>
</html>
