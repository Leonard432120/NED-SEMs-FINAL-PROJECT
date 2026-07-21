<?php
/* ════════════════════════════════════════════════════════════════
   admin/reports/index.php
   EDM/Admin: Main Reports & Analytics Command Center Dashboard
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

/* ─── 1. FETCH EXECUTIVE KPI STATISTICS ─── */

// Schools, Districts, Candidates
$total_schools = (int)($conn->query("SELECT COUNT(*) as c FROM schools WHERE status='active'")->fetch_assoc()['c'] ?? 0);
$total_districts = (int)($conn->query("SELECT COUNT(DISTINCT district) as c FROM schools WHERE status='active'")->fetch_assoc()['c'] ?? 0);
$total_candidates = (int)($conn->query("SELECT COUNT(*) as c FROM students WHERE status='active'")->fetch_assoc()['c'] ?? 0);

// Exam participation
$total_sat = (int)($conn->query("SELECT COUNT(DISTINCT student_id) as c FROM results WHERE status='published'")->fetch_assoc()['c'] ?? 0);
$total_absent = max(0, $total_candidates - $total_sat);
$participation_rate = $total_candidates > 0 ? round(($total_sat / $total_candidates) * 100, 1) : 0.0;

// Pass / Fail (Using Credit 40% as standard benchmark)
$total_passed = (int)($conn->query("
    SELECT COUNT(DISTINCT student_id) as c
    FROM results
    WHERE status='published'
    AND average_score >= 45
")->fetch_assoc()['c'] ?? 0);
$total_failed = max(0, $total_sat - $total_passed);
$pass_rate = $total_sat > 0 ? round(($total_passed / $total_sat) * 100, 1) : 0.0;
$fail_rate = $total_sat > 0 ? round(($total_failed / $total_sat) * 100, 1) : 0.0;

// Average score
$avg_score = (float)($conn->query("SELECT AVG(average_score) as v FROM results WHERE status='published'")->fetch_assoc()['v'] ?? 0.0);
$avg_score = round($avg_score, 1);

// Exams and Subjects
$total_exams = (int)($conn->query("SELECT COUNT(*) as c FROM exams")->fetch_assoc()['c'] ?? 0);
$total_subjects = (int)($conn->query("SELECT COUNT(*) as c FROM subjects WHERE status='active'")->fetch_assoc()['c'] ?? 0);

// District rankings (Best / Worst)
$district_perf = $conn->query("
    SELECT sc.district, AVG(r.average_score) as avg_score
    FROM results r
    JOIN students st ON r.student_id = st.student_id
    JOIN schools sc ON st.school_id = sc.school_id
    WHERE r.status='published'
    GROUP BY sc.district
    ORDER BY avg_score DESC
")->fetch_all(MYSQLI_ASSOC);

$best_district = $district_perf[0]['district'] ?? 'N/A';
$worst_district = (count($district_perf) > 1) ? $district_perf[count($district_perf) - 1]['district'] : ($district_perf[0]['district'] ?? 'N/A');

// School rankings (Best / Worst)
$school_perf = $conn->query("
    SELECT sc.school_name, AVG(r.average_score) as avg_score
    FROM results r
    JOIN students st ON r.student_id = st.student_id
    JOIN schools sc ON st.school_id = sc.school_id
    WHERE r.status='published'
    GROUP BY sc.school_id
    ORDER BY avg_score DESC
")->fetch_all(MYSQLI_ASSOC);

$best_school = $school_perf[0]['school_name'] ?? 'N/A';
$worst_school = (count($school_perf) > 1) ? $school_perf[count($school_perf) - 1]['school_name'] : ($school_perf[0]['school_name'] ?? 'N/A');

// Pending marks and approved results
$pending_marks = (int)($conn->query("SELECT COUNT(*) as c FROM marking_assignments WHERE status != 'completed'")->fetch_assoc()['c'] ?? 0);
$approved_results = (int)($conn->query("SELECT COUNT(*) as c FROM results WHERE status = 'published'")->fetch_assoc()['c'] ?? 0);

/* ─── 2. CHARTS DATA PREPARATION ─── */
// A. Pass vs Fail Donut
$pass_fail_donut = chart_donut([
    ['label' => 'Passed (>=45%)', 'value' => $total_passed, 'color' => '#22c55e'],
    ['label' => 'Failed (<45%)', 'value' => $total_failed, 'color' => '#ef4444']
], ['size' => 180, 'center_text' => $pass_rate . '%', 'center_subtext' => 'Pass Rate']);

// B. Performance Trend Line
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
$trend_chart = chart_line($trend_labels, [
    ['label' => 'Average Score', 'values' => $trend_values, 'color' => '#3b82f6']
], ['height' => 220, 'rotate_labels' => true]);

// C. District Performance Rankings
$dist_labels = array_column($district_perf, 'district');
$dist_values = array_map(fn($d) => (float)$d['avg_score'], $district_perf);
$district_chart = chart_hbars($dist_labels, $dist_values, ['height' => 180]);

// D. Subject Performance Bar
$subj_res = $conn->query("
    SELECT s.subject_code, AVG(m.score) as avg_score
    FROM marks m
    JOIN subjects s ON m.subject_id = s.subject_id
    WHERE m.status = 'approved'
    GROUP BY m.subject_id
    ORDER BY avg_score DESC
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

$subj_labels = array_column($subj_res, 'subject_code');
$subj_values = array_map(fn($s) => (float)$s['avg_score'], $subj_res);
$subject_chart = chart_bars($subj_labels, $subj_values, ['height' => 200, 'auto_color' => true]);

// E. Grade Distribution Histogram
$scores_res = $conn->query("SELECT average_score FROM results WHERE status='published'")->fetch_all(MYSQLI_ASSOC);
$all_scores = array_map(fn($s) => (float)$s['average_score'], $scores_res);
$grade_dist = stats_grade_distribution($all_scores);
$grade_histogram = chart_grade_histogram($grade_dist, ['height' => 200]);

$conn->close();

$portal_title = 'NED-SEMS | Reports Dashboard';
$module_css = 'admin';
include __DIR__ . '/../../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports & Analytics Dashboard | EDM Control Center</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
</head>
<body>

<?php include __DIR__ . '/../../common/header.php'; ?>

<div class="dashboard">
    <?php include __DIR__ . '/../../common/sidebar.php'; ?>

    <div class="content">
        <!-- Print Header -->
        <div class="print-header">
            <h2 class="print-title">NED-SEMS National Education Data Summary</h2>
            <div class="print-meta">Generated on: <?= date('Y-m-d H:i:s') ?> | Role: Administrator</div>
        </div>

        <div class="page-header">
            <div>
                <h2 class="page-title">Reports & Analytics Command Center</h2>
                <p class="page-subtitle">National education data, school standings, statistics, and machine learning insights</p>
            </div>
            <div class="header-actions">
                <button onclick="window.print()" class="btn btn-primary">Print Executive Report</button>
            </div>
        </div>       

        <!-- LOGICALLY GROUPED DETAIL STATISTICS SHEET -->
        <div class="card" style="padding: 22px; margin-bottom: 28px;">
            <h3 style="margin-top:0; margin-bottom: 16px; font-size: 14px; font-weight: 700; color: #334155; text-transform: uppercase; letter-spacing: 0.5px;">National Education Metrics Summary</h3>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px;">
                <!-- Column 1: Candidate Participation -->
                <div style="border-right: 1px solid var(--border-color); padding-right: 14px;">
                    <h4 style="font-size: 12.5px; font-weight: 600; color: #0f172a; margin-bottom: 12px; border-bottom: 2px solid var(--border-color); padding-bottom: 6px;">Candidacy &amp; Participation</h4>
                    <div style="display:flex; flex-direction:column; gap: 8px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
                            <span>Total Candidates Sat:</span>
                            <strong style="color:#0f172a;"><?= number_format($total_sat) ?></strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
                            <span>Absent Candidates:</span>
                            <strong style="color:#0f172a;"><?= number_format($total_absent) ?></strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
                            <span>Participation Rate:</span>
                            <strong style="color:#0f172a;"><?= $participation_rate ?>%</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
                            <span>Fail Rate:</span>
                            <strong style="color:#0f172a;"><?= $fail_rate ?>%</strong>
                        </div>
                    </div>
                </div>

                <!-- Column 2: Rankings Overview -->
                <div style="border-right: 1px solid var(--border-color); padding-right: 14px;">
                    <h4 style="font-size: 12.5px; font-weight: 600; color: #0f172a; margin-bottom: 12px; border-bottom: 2px solid var(--border-color); padding-bottom: 6px;">School &amp; District Standings</h4>
                    <div style="display:flex; flex-direction:column; gap: 8px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
                            <span>Best Performing District:</span>
                            <strong style="color:#16a34a;" title="<?= htmlspecialchars($best_district) ?>"><?= htmlspecialchars($best_district) ?></strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
                            <span>Lowest Performing District:</span>
                            <strong style="color:#dc2626;" title="<?= htmlspecialchars($worst_district) ?>"><?= htmlspecialchars($worst_district) ?></strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
                            <span>Best Performing School:</span>
                            <strong style="color:#16a34a;" title="<?= htmlspecialchars($best_school) ?>"><?= htmlspecialchars(mb_strimwidth($best_school, 0, 18, '...')) ?></strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
                            <span>Lowest Performing School:</span>
                            <strong style="color:#dc2626;" title="<?= htmlspecialchars($worst_school) ?>"><?= htmlspecialchars(mb_strimwidth($worst_school, 0, 18, '...')) ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Column 3: Syllabus and Moderation -->
                <div>
                    <h4 style="font-size: 12.5px; font-weight: 600; color: #0f172a; margin-bottom: 12px; border-bottom: 2px solid var(--border-color); padding-bottom: 6px;">Administration &amp; Workflow</h4>
                    <div style="display:flex; flex-direction:column; gap: 8px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
                            <span>Total Exam Papers:</span>
                            <strong style="color:#0f172a;"><?= $total_exams ?></strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
                            <span>Subjects Examined:</span>
                            <strong style="color:#0f172a;"><?= $total_subjects ?></strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
                            <span>Total Examining Districts:</span>
                            <strong style="color:#0f172a;"><?= $total_districts ?></strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
                            <span>Workflow (Pending / Approved):</span>
                            <strong style="color:#0f172a;"><?= $pending_marks ?> / <?= $approved_results ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CHARTS SECTION -->
        <div class="rpt-chart-grid">
            <div class="rpt-chart-box">
                <h3 class="rpt-chart-title">Pass vs Failure Ratio</h3>
                <p class="rpt-chart-sub">Doughnut representation of overall pass / fail distribution</p>
                <div class="rpt-chart-body" style="text-align: center;">
                    <?= $pass_fail_donut ?>
                </div>
            </div>

            <div class="rpt-chart-box">
                <h3 class="rpt-chart-title">Division Performance Trends</h3>
                <p class="rpt-chart-sub">Academic average score trends across national exam history</p>
                <div class="rpt-chart-body">
                    <?= $trend_chart ?>
                </div>
            </div>

            <div class="rpt-chart-box">
                <h3 class="rpt-chart-title">District Performance Rankings</h3>
                <p class="rpt-chart-sub">Horizontal comparative list of district mean scores</p>
                <div class="rpt-chart-body">
                    <?= $district_chart ?>
                </div>
            </div>

            <div class="rpt-chart-box">
                <h3 class="rpt-chart-title">Subject Performance Analysis</h3>
                <p class="rpt-chart-sub">Comparison of top 8 subjects by average approved marks</p>
                <div class="rpt-chart-body">
                    <?= $subject_chart ?>
                </div>
            </div>

            <div class="rpt-chart-box" style="grid-column: span 2;">
                <h3 class="rpt-chart-title">Grade Distribution (Malawi MSCE scale)</h3>
                <p class="rpt-chart-sub">Histogram analysis of candidate results from Distinction (1) to Fail (9)</p>
                <div class="rpt-chart-body">
                    <?= $grade_histogram ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../common/footer.php'; ?>

</body>
</html>
