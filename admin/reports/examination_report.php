<?php
/* ════════════════════════════════════════════════════════════════
   admin/reports/examination_report.php
   EDM/Admin: Comprehensive Examination Intelligence Dashboard
   ────────────────────────────────────────────────────────────────
   Redesigned reporting approach:
   • District-level comparison heatmap
   • Subject-by-subject breakdown with pass rates
   • Gender gap analysis
   • Top/Bottom performing schools ranking
   • Score distribution with multiple thresholds
   • Cross-exam trend comparison
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

// ── Exam dropdown ──────────────────────────────────────────────────
$exams_list = $conn->query("
    SELECT DISTINCT e.exam_id, e.exam_name, e.year, e.class 
    FROM results r
    JOIN exams e ON r.exam_id = e.exam_id
    ORDER BY e.year DESC, e.exam_name ASC
")->fetch_all(MYSQLI_ASSOC);

$selected_exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
if ($selected_exam_id <= 0 && !empty($exams_list)) {
    $selected_exam_id = (int)$exams_list[0]['exam_id'];
}

$exam_info = null;
if ($selected_exam_id > 0) {
    $ex_stmt = $conn->prepare("SELECT * FROM exams WHERE exam_id = ?");
    $ex_stmt->bind_param("i", $selected_exam_id);
    $ex_stmt->execute();
    $exam_info = $ex_stmt->get_result()->fetch_assoc();
    $ex_stmt->close();
}

/* ════════════════════════════════════════════════════════════════
   DATA COLLECTION (all scoped to selected exam)
   ════════════════════════════════════════════════════════════════ */
$scores = [];
$stats = null;
$total_sat = 0;
$total_registered = 0;
$total_absent = 0;
$participation_rate = 0;
$pass_fail = null;
$grade_dist = [];
$district_data = [];
$school_rankings = [];
$subject_analysis = [];
$gender_analysis = [];
$score_bands = [];
$outliers = [];
$prev_exam_stats = null;

if ($selected_exam_id > 0 && $exam_info) {

    // ── 1. CANDIDACY STATS ────────────────────────────────────────
    $cand_stmt = $conn->prepare("SELECT COUNT(*) as c FROM students WHERE class = ? AND status='active'");
    $cand_stmt->bind_param("s", $exam_info['class']);
    $cand_stmt->execute();
    $total_registered = (int)($cand_stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $cand_stmt->close();

    // ── 2. DETAILED CANDIDATE SCORES ──────────────────────────────
    $scores_stmt = $conn->prepare("
        SELECT r.average_score, s.name, s.exam_number, s.gender, s.student_id,
               sch.school_name, sch.district, sch.school_id
        FROM results r
        JOIN students s ON r.student_id = s.student_id
        JOIN schools sch ON s.school_id = sch.school_id
        WHERE r.exam_id = ? AND r.status = 'published'
    ");
    $scores_stmt->bind_param("i", $selected_exam_id);
    $scores_stmt->execute();
    $candidates = $scores_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $scores_stmt->close();

    $scores = array_map(fn($s) => (float)$s['average_score'], $candidates);
    $total_sat = count($scores);
    $total_absent = max(0, $total_registered - $total_sat);
    $participation_rate = $total_registered > 0 ? round(($total_sat / $total_registered) * 100, 1) : 0.0;

    if ($total_sat > 0) {
        $stats = stats_summary($scores);
        $pass_fail = stats_pass_rate($scores, 40.0);
        $grade_dist = stats_grade_distribution($scores);

        // ── 3. DISTRICT COMPARISON ────────────────────────────────
        $by_district = [];
        foreach ($candidates as $c) {
            $d = $c['district'];
            if (!isset($by_district[$d])) $by_district[$d] = ['scores' => [], 'passed' => 0, 'total' => 0];
            $by_district[$d]['scores'][] = (float)$c['average_score'];
            $by_district[$d]['total']++;
            if ((float)$c['average_score'] >= 40) $by_district[$d]['passed']++;
        }
        foreach ($by_district as $dist => $data) {
            $avg = round(array_sum($data['scores']) / count($data['scores']), 1);
            $pr = round(($data['passed'] / $data['total']) * 100, 1);
            $district_data[] = [
                'district' => $dist,
                'candidates' => $data['total'],
                'avg_score' => $avg,
                'pass_rate' => $pr,
                'passed' => $data['passed'],
                'failed' => $data['total'] - $data['passed'],
                'highest' => round(max($data['scores']), 1),
                'lowest' => round(min($data['scores']), 1),
                'category' => stats_performance_category($avg),
            ];
        }
        usort($district_data, fn($a, $b) => $b['avg_score'] <=> $a['avg_score']);

        // ── 4. SCHOOL RANKINGS (Top & Bottom) ─────────────────────
        $by_school = [];
        foreach ($candidates as $c) {
            $sid = $c['school_id'];
            if (!isset($by_school[$sid])) $by_school[$sid] = ['name' => $c['school_name'], 'district' => $c['district'], 'scores' => [], 'passed' => 0];
            $by_school[$sid]['scores'][] = (float)$c['average_score'];
            if ((float)$c['average_score'] >= 40) $by_school[$sid]['passed']++;
        }
        foreach ($by_school as $sid => $data) {
            $cnt = count($data['scores']);
            $avg = round(array_sum($data['scores']) / $cnt, 1);
            $pr = round(($data['passed'] / $cnt) * 100, 1);
            $school_rankings[] = [
                'school_name' => $data['name'],
                'district' => $data['district'],
                'candidates' => $cnt,
                'avg_score' => $avg,
                'pass_rate' => $pr,
                'category' => stats_performance_category($avg),
            ];
        }
        usort($school_rankings, fn($a, $b) => $b['avg_score'] <=> $a['avg_score']);

        // ── 5. SUBJECT-BY-SUBJECT ANALYSIS ────────────────────────
        $subj_stmt = $conn->prepare("
            SELECT sub.subject_id, sub.subject_name, sub.subject_code,
                   AVG(m.score) AS avg_score,
                   COUNT(m.mark_id) AS entries,
                   SUM(CASE WHEN m.score >= 40 THEN 1 ELSE 0 END) AS passed,
                   MAX(m.score) AS highest,
                   MIN(m.score) AS lowest
            FROM marks m
            JOIN subjects sub ON m.subject_id = sub.subject_id
            JOIN students st ON m.student_id = st.student_id
            WHERE m.exam_id = ? AND m.status IN ('approved','submitted')
            GROUP BY sub.subject_id
            ORDER BY avg_score DESC
        ");
        $subj_stmt->bind_param("i", $selected_exam_id);
        $subj_stmt->execute();
        $subject_analysis = $subj_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $subj_stmt->close();

        foreach ($subject_analysis as &$sa) {
            $sa['avg_score'] = round((float)$sa['avg_score'], 1);
            $sa['pass_rate'] = $sa['entries'] > 0 ? round(($sa['passed'] / $sa['entries']) * 100, 1) : 0;
            $sa['highest'] = round((float)$sa['highest'], 1);
            $sa['lowest'] = round((float)$sa['lowest'], 1);
            $sa['category'] = stats_performance_category($sa['avg_score']);
        }
        unset($sa);

        // ── 6. GENDER GAP ANALYSIS ────────────────────────────────
        $gender_groups = ['Male' => [], 'Female' => []];
        foreach ($candidates as $c) {
            $g = ucfirst(strtolower($c['gender'] ?? 'unknown'));
            if ($g === 'Male' || $g === 'M') $gender_groups['Male'][] = (float)$c['average_score'];
            elseif ($g === 'Female' || $g === 'F') $gender_groups['Female'][] = (float)$c['average_score'];
        }
        foreach ($gender_groups as $g => $g_scores) {
            if (empty($g_scores)) continue;
            $cnt = count($g_scores);
            $avg = round(array_sum($g_scores) / $cnt, 1);
            $passed = count(array_filter($g_scores, fn($s) => $s >= 40));
            $gender_analysis[$g] = [
                'count' => $cnt,
                'avg_score' => $avg,
                'pass_rate' => round(($passed / $cnt) * 100, 1),
                'passed' => $passed,
                'failed' => $cnt - $passed,
            ];
        }

        // ── 7. SCORE BAND DISTRIBUTION ────────────────────────────
        $bands = ['0–19' => 0, '20–39' => 0, '40–49' => 0, '50–59' => 0, '60–69' => 0, '70–79' => 0, '80–100' => 0];
        foreach ($scores as $sc) {
            if ($sc < 20) $bands['0–19']++;
            elseif ($sc < 40) $bands['20–39']++;
            elseif ($sc < 50) $bands['40–49']++;
            elseif ($sc < 60) $bands['50–59']++;
            elseif ($sc < 70) $bands['60–69']++;
            elseif ($sc < 80) $bands['70–79']++;
            else $bands['80–100']++;
        }
        $score_bands = $bands;

        // ── 8. OUTLIER DETECTION ──────────────────────────────────
        $mean = $stats['mean'];
        $sd = $stats['std_dev'];
        foreach ($candidates as $cs) {
            $score = (float)$cs['average_score'];
            $z = $sd > 0 ? ($score - $mean) / $sd : 0.0;
            if (abs($z) >= 2.0) {
                $outliers[] = [
                    'name' => $cs['name'],
                    'exam_number' => $cs['exam_number'],
                    'school_name' => $cs['school_name'],
                    'district' => $cs['district'],
                    'score' => $score,
                    'z_score' => round($z, 2),
                    'type' => $z > 0 ? 'High Performer' : 'At Risk'
                ];
            }
        }
        usort($outliers, fn($a, $b) => $b['z_score'] <=> $a['z_score']);

        // ── 9. PREVIOUS EXAM COMPARISON ───────────────────────────
        $prev_stmt = $conn->prepare("
            SELECT e.exam_id, e.exam_name, e.year, AVG(r.average_score) AS avg_score,
                   COUNT(DISTINCT r.student_id) AS sat
            FROM results r
            JOIN exams e ON r.exam_id = e.exam_id
            WHERE e.class = ? AND e.exam_id != ? AND r.status = 'published'
            GROUP BY e.exam_id
            ORDER BY e.year DESC, e.start_date DESC
            LIMIT 1
        ");
        $prev_stmt->bind_param("si", $exam_info['class'], $selected_exam_id);
        $prev_stmt->execute();
        $prev_exam_stats = $prev_stmt->get_result()->fetch_assoc();
        $prev_stmt->close();
    }
}

$conn->close();

// ── Chart Builders ────────────────────────────────────────────────
$grade_histogram = '';
$pass_fail_donut = '';
$district_bar_chart = '';
$subject_bar_chart = '';
$score_band_chart = '';
$gender_donut = '';

if ($stats && $total_sat > 0) {
    $grade_histogram = chart_grade_histogram($grade_dist, ['height' => 220]);

    $pass_fail_donut = chart_donut([
        ['label' => 'Passed (≥40%)', 'value' => $pass_fail['pass_count'], 'color' => '#22c55e'],
        ['label' => 'Failed (<40%)', 'value' => $pass_fail['fail_count'], 'color' => '#ef4444'],
    ], ['size' => 170, 'center_text' => $pass_fail['pass_rate'] . '%', 'center_subtext' => 'Pass Rate']);

    // District comparison bar chart
    if (!empty($district_data)) {
        $d_labels = array_column($district_data, 'district');
        $d_values = array_map(fn($d) => $d['avg_score'], $district_data);
        $district_bar_chart = chart_bars($d_labels, $d_values, ['height' => 200, 'auto_color' => true]);
    }

    // Subject performance horizontal bar chart (top 10)
    if (!empty($subject_analysis)) {
        $s_labels = array_map(fn($s) => $s['subject_code'], array_slice($subject_analysis, 0, 10));
        $s_values = array_map(fn($s) => $s['avg_score'], array_slice($subject_analysis, 0, 10));
        $subject_bar_chart = chart_hbars($s_labels, $s_values, ['height' => 220]);
    }

    // Score band distribution bar
    if (!empty($score_bands)) {
        $score_band_chart = chart_bars(array_keys($score_bands), array_values($score_bands), ['height' => 200, 'auto_color' => true]);
    }

    // Gender donut
    if (!empty($gender_analysis)) {
        $g_segments = [];
        $g_colors = ['Male' => '#3b82f6', 'Female' => '#ec4899'];
        foreach ($gender_analysis as $g => $gd) {
            $g_segments[] = ['label' => $g . ' (' . $gd['count'] . ')', 'value' => $gd['count'], 'color' => $g_colors[$g] ?? '#94a3b8'];
        }
        $gender_donut = chart_donut($g_segments, ['size' => 160, 'center_text' => count($candidates), 'center_subtext' => 'Total Sat']);
    }
}

// ── Performance category helpers ──────────────────────────────────
function perf_color(string $cat): string {
    return match($cat) {
        'Excellent' => '#16a34a', 'Good' => '#22c55e', 'Satisfactory' => '#eab308',
        'Needs Improvement' => '#f97316', 'Critical' => '#dc2626', default => '#64748b'
    };
}
function perf_bg(string $cat): string {
    return match($cat) {
        'Excellent' => '#f0fdf4', 'Good' => '#f0fdf4', 'Satisfactory' => '#fefce8',
        'Needs Improvement' => '#fff7ed', 'Critical' => '#fef2f2', default => '#f8fafc'
    };
}

$portal_title = 'NED-SEMS | Examination Intelligence Report';
$module_css = 'admin';
include __DIR__ . '/../../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Examination Intelligence Report | NED-SEMS</title>
    <meta name="description" content="Comprehensive examination performance analysis with district comparison, subject breakdown, gender analytics and school rankings">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
    <style>
      .exam-hero { display: grid; grid-template-columns: 1fr 1fr; gap: 0; border-radius: 10px; overflow: hidden; margin-bottom: 28px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
      .exam-hero-left { background: linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%); color: #fff; padding: 28px 32px; }
      .exam-hero-right { background: #ffffff; padding: 28px 32px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
      .hero-exam-name { font-size: 1.6rem; font-weight: 800; margin: 0 0 4px 0; letter-spacing: -0.5px; }
      .hero-exam-sub { font-size: 0.88rem; color: #94a3b8; margin: 0 0 18px 0; }
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
      .gender-comparison { display: grid; grid-template-columns: 1fr auto 1fr; gap: 0; align-items: center; }
      .gender-card { padding: 20px; border-radius: 8px; text-align: center; }
      .gender-card-male { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 1px solid #bfdbfe; }
      .gender-card-female { background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%); border: 1px solid #fbcfe8; }
      .gender-vs { font-size: 1.2rem; font-weight: 900; color: #94a3b8; padding: 0 16px; }
      .gender-stat-big { font-size: 2rem; font-weight: 900; }
      .gender-stat-label { font-size: 0.78rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
      @media (max-width: 900px) {
        .exam-hero { grid-template-columns: 1fr; }
        .gender-comparison { grid-template-columns: 1fr; gap: 12px; }
        .gender-vs { display: none; }
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
            <h2 class="print-title">Examination Intelligence Report</h2>
            <div class="print-meta">Generated: <?= date('Y-m-d H:i') ?> | Northern Education Division | Administrator</div>
        </div>

        <div class="page-header">
            <div>
                <h2 class="page-title">Examination Intelligence Report</h2>
                <p class="page-subtitle">Multi-dimensional performance analysis — district comparisons, subject standings, gender equity and school rankings</p>
            </div>
            <div class="header-actions">
                <button onclick="window.print()" class="btn btn-secondary">Print Report</button>
            </div>
        </div>

        <!-- Exam Selector -->
        <div class="rpt-filter-panel no-print">
            <form method="GET" class="rpt-filter-form">
                <div class="rpt-filter-group">
                    <label class="rpt-filter-label">Select Examination Paper</label>
                    <select name="exam_id" class="rpt-filter-select" onchange="this.form.submit()">
                        <?php if (empty($exams_list)): ?>
                            <option value="0">No compiled examination papers available</option>
                        <?php else: ?>
                            <?php foreach ($exams_list as $ex): ?>
                                <option value="<?= $ex['exam_id'] ?>" <?= $selected_exam_id === (int)$ex['exam_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ex['exam_name']) ?> (<?= htmlspecialchars($ex['year']) ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="rpt-filter-actions">
                    <button type="submit" class="btn btn-primary">Load Report</button>
                </div>
            </form>
        </div>

        <?php if ($selected_exam_id > 0 && $stats): ?>

        <!-- ═══ HERO EXECUTIVE SUMMARY ═══ -->
        <div class="exam-hero">
            <div class="exam-hero-left">
                <h1 class="hero-exam-name"><?= htmlspecialchars($exam_info['exam_name'] ?? '') ?></h1>
                <p class="hero-exam-sub"><?= htmlspecialchars($exam_info['class'] ?? '') ?> · Academic Year <?= htmlspecialchars($exam_info['year'] ?? '') ?></p>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="hero-stat">
                        <span class="hero-stat-label">Overall Pass Rate</span>
                        <span class="hero-stat-value" style="color: <?= $pass_fail['pass_rate'] >= 50 ? '#4ade80' : '#f87171' ?>;"><?= $pass_fail['pass_rate'] ?>%</span>
                        <?php if ($prev_exam_stats): ?>
                            <?php
                                $prev_avg = round((float)$prev_exam_stats['avg_score'], 1);
                                $delta = round($stats['mean'] - $prev_avg, 1);
                                $delta_sign = $delta >= 0 ? '+' : '';
                                $delta_color = $delta >= 0 ? '#4ade80' : '#f87171';
                            ?>
                            <span class="hero-stat-delta" style="color: <?= $delta_color ?>;"><?= $delta_sign . $delta ?>% vs <?= htmlspecialchars($prev_exam_stats['exam_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="hero-stat">
                        <span class="hero-stat-label">Division Mean Score</span>
                        <span class="hero-stat-value"><?= $stats['mean'] ?>%</span>
                        <span class="hero-stat-delta">Median: <?= $stats['median'] ?>% · SD: <?= $stats['std_dev'] ?></span>
                    </div>
                </div>
            </div>
            <div class="exam-hero-right">
                <div class="hero-kpi">
                    <span class="hero-kpi-label">Candidates Sat</span>
                    <span class="hero-kpi-value"><?= number_format($total_sat) ?></span>
                    <span class="hero-kpi-sub">of <?= number_format($total_registered) ?> registered (<?= $participation_rate ?>%)</span>
                </div>
                <div class="hero-kpi">
                    <span class="hero-kpi-label">Passed (≥40%)</span>
                    <span class="hero-kpi-value" style="color: #16a34a;"><?= number_format($pass_fail['pass_count']) ?></span>
                    <span class="hero-kpi-sub">Failed: <?= number_format($pass_fail['fail_count']) ?> (<?= $pass_fail['fail_rate'] ?>%)</span>
                </div>
                <div class="hero-kpi">
                    <span class="hero-kpi-label">Score Range</span>
                    <span class="hero-kpi-value"><?= $stats['min'] ?>–<?= $stats['max'] ?>%</span>
                    <span class="hero-kpi-sub">IQR: <?= $stats['q1'] ?>–<?= $stats['q3'] ?>%</span>
                </div>
                <div class="hero-kpi">
                    <span class="hero-kpi-label">Schools Represented</span>
                    <span class="hero-kpi-value"><?= count($school_rankings) ?></span>
                    <span class="hero-kpi-sub">Across <?= count($district_data) ?> districts</span>
                </div>
            </div>
        </div>

        <!-- ═══ SECTION 1: PASS/FAIL & GRADE DISTRIBUTION ═══ -->
        <div class="section-divider">
            <h3>Score Distribution & Grade Spread</h3>
            <span class="section-divider-badge"><?= $total_sat ?> candidates</span>
        </div>

        <div class="rpt-chart-grid">
            <div class="rpt-chart-box">
                <h3 class="rpt-chart-title">Pass vs Failure Ratio</h3>
                <p class="rpt-chart-sub">Pass threshold set at 40% average score</p>
                <div class="rpt-chart-body" style="text-align: center;">
                    <?= $pass_fail_donut ?>
                </div>
            </div>
            <div class="rpt-chart-box">
                <h3 class="rpt-chart-title">Score Band Distribution</h3>
                <p class="rpt-chart-sub">Candidate count by score range brackets</p>
                <div class="rpt-chart-body">
                    <?= $score_band_chart ?>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 24px;">
            <div class="section-header"><h3>Grade Distribution (Malawi MSCE Scale)</h3></div>
            <div style="max-width: 850px; margin: auto;">
                <?= $grade_histogram ?>
            </div>
        </div>

        <!-- ═══ SECTION 2: DISTRICT PERFORMANCE COMPARISON ═══ -->
        <?php if (!empty($district_data)): ?>
        <div class="section-divider">
            <h3>District Performance Heatmap</h3>
            <span class="section-divider-badge"><?= count($district_data) ?> districts</span>
        </div>

        <div class="rpt-chart-grid">
            <div class="rpt-chart-box" style="grid-column: span 2;">
                <h3 class="rpt-chart-title">District Average Score Comparison</h3>
                <p class="rpt-chart-sub">Visual comparison of mean scores across all participating districts</p>
                <div class="rpt-chart-body"><?= $district_bar_chart ?></div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 24px; overflow-x: auto;">
            <table class="district-heatmap">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>District</th>
                        <th style="text-align: center;">Sat</th>
                        <th style="text-align: center;">Passed</th>
                        <th style="text-align: center;">Failed</th>
                        <th style="text-align: center;">Avg Score</th>
                        <th style="text-align: center;">Pass Rate</th>
                        <th style="text-align: center;">Highest</th>
                        <th style="text-align: center;">Lowest</th>
                        <th style="text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($district_data as $i => $d): ?>
                    <tr>
                        <td>
                            <?php $rc = $i === 0 ? 'rank-gold' : ($i === 1 ? 'rank-silver' : ($i === 2 ? 'rank-bronze' : 'rank-default')); ?>
                            <span class="rank-badge <?= $rc ?>"><?= $i + 1 ?></span>
                        </td>
                        <td style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($d['district']) ?></td>
                        <td style="text-align: center;"><?= $d['candidates'] ?></td>
                        <td style="text-align: center; color: #16a34a; font-weight: 600;"><?= $d['passed'] ?></td>
                        <td style="text-align: center; color: #dc2626; font-weight: 600;"><?= $d['failed'] ?></td>
                        <td style="text-align: center;">
                            <span class="heatcell" style="background: <?= perf_bg($d['category']) ?>; color: <?= perf_color($d['category']) ?>;">
                                <?= $d['avg_score'] ?>%
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <span class="heatcell" style="background: <?= $d['pass_rate'] >= 50 ? '#f0fdf4' : '#fef2f2' ?>; color: <?= $d['pass_rate'] >= 50 ? '#16a34a' : '#dc2626' ?>;">
                                <?= $d['pass_rate'] ?>%
                            </span>
                        </td>
                        <td style="text-align: center; color: #16a34a;"><?= $d['highest'] ?>%</td>
                        <td style="text-align: center; color: #dc2626;"><?= $d['lowest'] ?>%</td>
                        <td style="text-align: center;"><?= stats_badge($d['category']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- ═══ SECTION 3: SUBJECT ANALYSIS ═══ -->
        <?php if (!empty($subject_analysis)): ?>
        <div class="section-divider">
            <h3>Subject Performance Breakdown</h3>
            <span class="section-divider-badge"><?= count($subject_analysis) ?> subjects</span>
        </div>

        <div class="rpt-chart-grid">
            <div class="rpt-chart-box" style="grid-column: span 2;">
                <h3 class="rpt-chart-title">Subject Performance Rankings (Top 10)</h3>
                <p class="rpt-chart-sub">Horizontal ranking of subjects by average score across all schools</p>
                <div class="rpt-chart-body"><?= $subject_bar_chart ?></div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 24px; overflow-x: auto;">
            <table class="district-heatmap">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Subject</th>
                        <th>Code</th>
                        <th style="text-align: center;">Entries</th>
                        <th style="text-align: center;">Avg Score</th>
                        <th style="text-align: center;">Pass Rate</th>
                        <th style="text-align: center;">Highest</th>
                        <th style="text-align: center;">Lowest</th>
                        <th style="text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subject_analysis as $i => $sa): ?>
                    <tr>
                        <td>
                            <?php $rc = $i === 0 ? 'rank-gold' : ($i === 1 ? 'rank-silver' : ($i === 2 ? 'rank-bronze' : 'rank-default')); ?>
                            <span class="rank-badge <?= $rc ?>"><?= $i + 1 ?></span>
                        </td>
                        <td style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($sa['subject_name']) ?></td>
                        <td style="color: #64748b;"><?= htmlspecialchars($sa['subject_code']) ?></td>
                        <td style="text-align: center;"><?= $sa['entries'] ?></td>
                        <td style="text-align: center;">
                            <span class="heatcell" style="background: <?= perf_bg($sa['category']) ?>; color: <?= perf_color($sa['category']) ?>;">
                                <?= $sa['avg_score'] ?>%
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <span class="heatcell" style="background: <?= $sa['pass_rate'] >= 50 ? '#f0fdf4' : '#fef2f2' ?>; color: <?= $sa['pass_rate'] >= 50 ? '#16a34a' : '#dc2626' ?>;">
                                <?= $sa['pass_rate'] ?>%
                            </span>
                        </td>
                        <td style="text-align: center; color: #16a34a;"><?= $sa['highest'] ?>%</td>
                        <td style="text-align: center; color: #dc2626;"><?= $sa['lowest'] ?>%</td>
                        <td style="text-align: center;"><?= stats_badge($sa['category']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- ═══ SECTION 4: GENDER EQUITY ANALYSIS ═══ -->
        <?php if (count($gender_analysis) === 2): ?>
        <div class="section-divider">
            <h3>Gender Performance Equity</h3>
            <span class="section-divider-badge">Gender gap analysis</span>
        </div>

        <div class="rpt-chart-grid">
            <div class="rpt-chart-box">
                <h3 class="rpt-chart-title">Gender Enrollment Distribution</h3>
                <p class="rpt-chart-sub">Candidate count split by declared gender</p>
                <div class="rpt-chart-body" style="text-align: center;">
                    <?= $gender_donut ?>
                </div>
            </div>
            <div class="rpt-chart-box">
                <h3 class="rpt-chart-title">Head-to-Head Performance Comparison</h3>
                <p class="rpt-chart-sub">Average score and pass rate comparison between genders</p>
                <div class="rpt-chart-body">
                    <?php
                        $m = $gender_analysis['Male'] ?? null;
                        $f = $gender_analysis['Female'] ?? null;
                        $gap = $m && $f ? round(abs($m['avg_score'] - $f['avg_score']), 1) : 0;
                        $gap_leader = ($m['avg_score'] ?? 0) >= ($f['avg_score'] ?? 0) ? 'Male' : 'Female';
                    ?>
                    <div class="gender-comparison">
                        <div class="gender-card gender-card-male">
                            <div class="gender-stat-label">Male Candidates</div>
                            <div class="gender-stat-big" style="color: #2563eb;"><?= $m['avg_score'] ?? 0 ?>%</div>
                            <div style="font-size: 0.82rem; color: #64748b; margin-top: 4px;">
                                <?= $m['count'] ?? 0 ?> sat · <?= $m['pass_rate'] ?? 0 ?>% pass rate
                            </div>
                        </div>
                        <div class="gender-vs">VS</div>
                        <div class="gender-card gender-card-female">
                            <div class="gender-stat-label">Female Candidates</div>
                            <div class="gender-stat-big" style="color: #db2777;"><?= $f['avg_score'] ?? 0 ?>%</div>
                            <div style="font-size: 0.82rem; color: #64748b; margin-top: 4px;">
                                <?= $f['count'] ?? 0 ?> sat · <?= $f['pass_rate'] ?? 0 ?>% pass rate
                            </div>
                        </div>
                    </div>
                    <div style="text-align: center; margin-top: 14px; font-size: 0.85rem; color: #475569;">
                        <strong>Gender Gap:</strong>
                        <span style="font-weight: 800; color: <?= $gap <= 3 ? '#16a34a' : ($gap <= 8 ? '#eab308' : '#dc2626') ?>;"><?= $gap ?>%</span>
                        in favour of <strong><?= $gap_leader ?></strong>
                        <?php if ($gap <= 3): ?>
                            — <span style="color: #16a34a;">Equitable</span>
                        <?php elseif ($gap <= 8): ?>
                            — <span style="color: #eab308;">Moderate Gap</span>
                        <?php else: ?>
                            — <span style="color: #dc2626;">Significant Gap</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ═══ SECTION 5: SCHOOL RANKINGS ═══ -->
        <?php if (!empty($school_rankings)): ?>
        <div class="section-divider">
            <h3>School Performance Rankings</h3>
            <span class="section-divider-badge"><?= count($school_rankings) ?> centers</span>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
            <!-- Top 5 -->
            <div class="card" style="padding: 0; overflow: hidden;">
                <div style="background: linear-gradient(135deg, #065f46, #047857); color: #fff; padding: 14px 18px;">
                    <h4 style="margin: 0; font-size: 0.9rem; font-weight: 700;">Top 5 Performing Schools</h4>
                </div>
                <div style="padding: 0;">
                    <?php foreach (array_slice($school_rankings, 0, 5) as $i => $sc): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 18px; border-bottom: 1px solid #f1f5f9;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <?php $rc = $i === 0 ? 'rank-gold' : ($i === 1 ? 'rank-silver' : ($i === 2 ? 'rank-bronze' : 'rank-default')); ?>
                            <span class="rank-badge <?= $rc ?>"><?= $i + 1 ?></span>
                            <div>
                                <div style="font-weight: 700; font-size: 0.88rem; color: #0f172a;"><?= htmlspecialchars($sc['school_name']) ?></div>
                                <div style="font-size: 0.75rem; color: #64748b;"><?= htmlspecialchars($sc['district']) ?> · <?= $sc['candidates'] ?> candidates</div>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 800; font-size: 1rem; color: #16a34a;"><?= $sc['avg_score'] ?>%</div>
                            <div style="font-size: 0.72rem; color: #64748b;">Pass: <?= $sc['pass_rate'] ?>%</div>
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
                    <?php
                        $bottom5 = array_slice($school_rankings, -5);
                        $bottom5 = array_reverse($bottom5);
                        $total_schools = count($school_rankings);
                    ?>
                    <?php foreach ($bottom5 as $i => $sc): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 18px; border-bottom: 1px solid #f1f5f9;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span class="rank-badge rank-default"><?= $total_schools - $i ?></span>
                            <div>
                                <div style="font-weight: 700; font-size: 0.88rem; color: #0f172a;"><?= htmlspecialchars($sc['school_name']) ?></div>
                                <div style="font-size: 0.75rem; color: #64748b;"><?= htmlspecialchars($sc['district']) ?> · <?= $sc['candidates'] ?> candidates</div>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 800; font-size: 1rem; color: #dc2626;"><?= $sc['avg_score'] ?>%</div>
                            <div style="font-size: 0.72rem; color: #64748b;">Pass: <?= $sc['pass_rate'] ?>%</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>



        <?php else: ?>
            <div class="card empty-state" style="padding:48px; text-align: center;">
                <p style="color: var(--text-muted); font-size: 1rem;">Select an examination paper above to generate the intelligence report.</p>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/../../common/footer.php'; ?>

</body>
</html>
