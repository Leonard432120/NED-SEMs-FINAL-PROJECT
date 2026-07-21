<?php
/* ════════════════════════════════════════════════════════════════
  admin/reports/district_report.php
  EDM/Admin: District Analysis — mirrors index.php layout exactly,
  scoped to the selected district via prepared statements.
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

/* ─── DISTRICT SELECTOR ─── */
$districts_list = $conn->query(
  "SELECT DISTINCT district FROM schools WHERE status='active' ORDER BY district ASC"
)->fetch_all(MYSQLI_ASSOC);

$selected_district = isset($_GET['district']) ? trim($_GET['district']) : '';
if ($selected_district === '' && !empty($districts_list)) {
  $selected_district = $districts_list[0]['district'];
}

/* ════════════════════════════════════════════════════════════════
  1. KPI STATISTICS — scoped to $selected_district
  ════════════════════════════════════════════════════════════════ */

// Candidates sat (published results in this district)
$stmt = $conn->prepare("
  SELECT COUNT(DISTINCT r.student_id) AS c
  FROM results r
  JOIN students st ON r.student_id = st.student_id
  JOIN schools sc ON st.school_id = sc.school_id
  WHERE sc.district = ? AND r.status = 'published'
");
$stmt->bind_param("s", $selected_district);
$stmt->execute();
$total_sat = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

// Total registered candidates in this district
$stmt = $conn->prepare("
  SELECT COUNT(*) AS c
  FROM students st
  JOIN schools sc ON st.school_id = sc.school_id
  WHERE sc.district = ? AND st.status = 'active'
");
$stmt->bind_param("s", $selected_district);
$stmt->execute();
$total_candidates = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$total_absent    = max(0, $total_candidates - $total_sat);
$participation_rate = $total_candidates > 0 ? round(($total_sat / $total_candidates) * 100, 1) : 0.0;

// Pass / Fail (>=45 benchmark, same as index.php)
$stmt = $conn->prepare("
  SELECT COUNT(DISTINCT r.student_id) AS c
  FROM results r
  JOIN students st ON r.student_id = st.student_id
  JOIN schools sc ON st.school_id = sc.school_id
  WHERE sc.district = ? AND r.status = 'published' AND r.average_score >= 45
");
$stmt->bind_param("s", $selected_district);
$stmt->execute();
$total_passed = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$total_failed = max(0, $total_sat - $total_passed);
$pass_rate  = $total_sat > 0 ? round(($total_passed / $total_sat) * 100, 1) : 0.0;
$fail_rate  = $total_sat > 0 ? round(($total_failed / $total_sat) * 100, 1) : 0.0;

// School performance within district (best / worst)
$stmt = $conn->prepare("
  SELECT sc.school_name, AVG(r.average_score) AS avg_score
  FROM results r
  JOIN students st ON r.student_id = st.student_id
  JOIN schools sc ON st.school_id = sc.school_id
  WHERE sc.district = ? AND r.status = 'published'
  GROUP BY sc.school_id
  ORDER BY avg_score DESC
");
$stmt->bind_param("s", $selected_district);
$stmt->execute();
$school_perf = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$best_school = $school_perf[0]['school_name'] ?? 'N/A';
$worst_school = count($school_perf) > 1
  ? $school_perf[count($school_perf) - 1]['school_name']
  : ($school_perf[0]['school_name'] ?? 'N/A');

// Number of schools in district
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM schools WHERE district = ? AND status = 'active'");
$stmt->bind_param("s", $selected_district);
$stmt->execute();
$total_schools_in_district = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

// Subjects examined in district
$stmt = $conn->prepare("
  SELECT COUNT(DISTINCT m.subject_id) AS c
  FROM marks m
  JOIN students st ON m.student_id = st.student_id
  JOIN schools sc ON st.school_id = sc.school_id
  WHERE sc.district = ? AND m.status = 'approved'
");
$stmt->bind_param("s", $selected_district);
$stmt->execute();
$subjects_examined = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

// District rank among all districts
$all_dist_perf = $conn->query("
  SELECT sc.district, AVG(r.average_score) AS avg_score
  FROM results r
  JOIN students st ON r.student_id = st.student_id
  JOIN schools sc ON st.school_id = sc.school_id
  WHERE r.status = 'published'
  GROUP BY sc.district
  ORDER BY avg_score DESC
")->fetch_all(MYSQLI_ASSOC);

$district_rank    = 1;
$total_districts_all = count($all_dist_perf);
foreach ($all_dist_perf as $idx => $d) {
  if ($d['district'] === $selected_district) {
    $district_rank = $idx + 1;
    break;
  }
}

// Pending marks / approved results in district
$stmt = $conn->prepare("
  SELECT COUNT(*) AS c
  FROM marking_assignments ma
  JOIN schools sc ON ma.school_id = sc.school_id
  WHERE sc.district = ? AND ma.status != 'completed'
");
$stmt->bind_param("s", $selected_district);
$stmt->execute();
$pending_marks = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$stmt = $conn->prepare("
  SELECT COUNT(*) AS c
  FROM results r
  JOIN students st ON r.student_id = st.student_id
  JOIN schools sc ON st.school_id = sc.school_id
  WHERE sc.district = ? AND r.status = 'published'
");
$stmt->bind_param("s", $selected_district);
$stmt->execute();
$approved_results = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

/* ════════════════════════════════════════════════════════════════
  2. CHARTS DATA — all scoped to $selected_district
  ════════════════════════════════════════════════════════════════ */

// A. Pass vs Fail Donut
$pass_fail_donut = chart_donut([
  ['label' => 'Passed (>=45%)', 'value' => $total_passed, 'color' => '#22c55e'],
  ['label' => 'Failed (<45%)', 'value' => $total_failed, 'color' => '#ef4444'],
], ['size' => 180, 'center_text' => $pass_rate . '%', 'center_subtext' => 'Pass Rate']);

// B. Performance Trend Line (district history across all exams)
$stmt = $conn->prepare("
  SELECT e.exam_name, e.year, AVG(r.average_score) AS avg_score
  FROM results r
  JOIN exams e ON r.exam_id = e.exam_id
  JOIN students st ON r.student_id = st.student_id
  JOIN schools sc ON st.school_id = sc.school_id
  WHERE sc.district = ? AND r.status = 'published'
  GROUP BY e.exam_id
  ORDER BY e.year ASC, e.start_date ASC
");
$stmt->bind_param("s", $selected_district);
$stmt->execute();
$trend_res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$trend_labels = array_map(function($t) {
  $name = $t['exam_name'];
  $year = (string)$t['year'];
  return (strpos($name, $year) !== false) ? $name : ($name . ' (' . $year . ')');
}, $trend_res);
$trend_values = array_map(fn($t) => (float)$t['avg_score'], $trend_res);
$trend_chart = chart_line($trend_labels, [
  ['label' => 'District Average', 'values' => $trend_values, 'color' => '#3b82f6']
], ['height' => 220, 'rotate_labels' => true]);

// C. School Performance Rankings hbar (schools within district)
$sch_labels = array_column($school_perf, 'school_name');
$sch_values = array_map(fn($s) => (float)$s['avg_score'], $school_perf);
$school_rankings_chart = chart_hbars($sch_labels, $sch_values, ['height' => 180]);

// D. Subject Performance Bar (top 8 subjects in district)
$stmt = $conn->prepare("
  SELECT s.subject_code, AVG(m.score) AS avg_score
  FROM marks m
  JOIN subjects s ON m.subject_id = s.subject_id
  JOIN students st ON m.student_id = st.student_id
  JOIN schools sc ON st.school_id = sc.school_id
  WHERE sc.district = ? AND m.status = 'approved'
  GROUP BY m.subject_id
  ORDER BY avg_score DESC
  LIMIT 8
");
$stmt->bind_param("s", $selected_district);
$stmt->execute();
$subj_res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$subj_labels  = array_column($subj_res, 'subject_code');
$subj_values  = array_map(fn($s) => (float)$s['avg_score'], $subj_res);
$subject_chart = chart_bars($subj_labels, $subj_values, ['height' => 200, 'auto_color' => true]);

// E. Grade Distribution Histogram
$stmt = $conn->prepare("
  SELECT r.average_score
  FROM results r
  JOIN students st ON r.student_id = st.student_id
  JOIN schools sc ON st.school_id = sc.school_id
  WHERE sc.district = ? AND r.status = 'published'
");
$stmt->bind_param("s", $selected_district);
$stmt->execute();
$scores_res  = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$all_scores   = array_map(fn($s) => (float)$s['average_score'], $scores_res);
$grade_dist   = stats_grade_distribution($all_scores);
$grade_histogram = chart_grade_histogram($grade_dist, ['height' => 200]);

$conn->close();

$portal_title = 'NED-SEMS | District Analysis Report';
$module_css  = 'admin';
include __DIR__ . '/../../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>District Analysis Report | NED-SEMS</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
</head>
<body>

<?php include __DIR__ . '/../../common/header.php'; ?>

<div class="dashboard">
  <?php include __DIR__ . '/../../common/sidebar.php'; ?>

  <div class="content">

    <!-- Print Header -->
    <div class="print-header">
      <h2 class="print-title">NED-SEMS District Analysis Report: <?= htmlspecialchars($selected_district) ?></h2>
      <div class="print-meta">Generated on: <?= date('Y-m-d H:i:s') ?> | Role: Administrator</div>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h2 class="page-title">District Analysis: <?= htmlspecialchars($selected_district) ?></h2>
        <p class="page-subtitle">Performance data, school standings, subject breakdowns and grade distribution scoped to the selected district</p>
      </div>
      <div class="header-actions">
        <button onclick="window.print()" class="btn btn-primary">Print District Report</button>
      </div>
    </div>

    <!-- District Selector -->
    <div class="rpt-filter-panel no-print">
      <form method="GET" class="rpt-filter-form">
        <div class="rpt-filter-group">
          <label class="rpt-filter-label">Select District</label>
          <select name="district" class="rpt-filter-select" onchange="this.form.submit()">
            <?php foreach ($districts_list as $d): ?>
              <option value="<?= htmlspecialchars($d['district']) ?>"
                <?= $selected_district === $d['district'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($d['district']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="rpt-filter-actions">
          <button type="submit" class="btn btn-primary">Load District</button>
        </div>
      </form>
    </div>

    <!-- ═══ KPI SUMMARY CARD ═══ -->
    <div class="card" style="padding: 22px; margin-bottom: 28px;">
      <h3 style="margin-top:0; margin-bottom: 16px; font-size: 14px; font-weight: 700; color: #334155; text-transform: uppercase; letter-spacing: 0.5px;">
        <?= htmlspecialchars($selected_district) ?> District Metrics Summary
      </h3>
      <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px;">

        <!-- Column 1: Candidacy & Participation -->
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

        <!-- Column 2: School Standings within District -->
        <div style="border-right: 1px solid var(--border-color); padding-right: 14px;">
          <h4 style="font-size: 12.5px; font-weight: 600; color: #0f172a; margin-bottom: 12px; border-bottom: 2px solid var(--border-color); padding-bottom: 6px;">School Standings in District</h4>
          <div style="display:flex; flex-direction:column; gap: 8px;">
            <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
              <span>Best Performing School:</span>
              <strong style="color:#16a34a;" title="<?= htmlspecialchars($best_school) ?>"><?= htmlspecialchars(mb_strimwidth($best_school, 0, 20, '…')) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
              <span>Lowest Performing School:</span>
              <strong style="color:#dc2626;" title="<?= htmlspecialchars($worst_school) ?>"><?= htmlspecialchars(mb_strimwidth($worst_school, 0, 20, '…')) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
              <span>District Rank (National):</span>
              <strong style="color:#0f172a;"><?= $district_rank ?> of <?= $total_districts_all ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
              <span>Schools in District:</span>
              <strong style="color:#0f172a;"><?= $total_schools_in_district ?></strong>
            </div>
          </div>
        </div>

        <!-- Column 3: Administration & Workflow -->
        <div>
          <h4 style="font-size: 12.5px; font-weight: 600; color: #0f172a; margin-bottom: 12px; border-bottom: 2px solid var(--border-color); padding-bottom: 6px;">Administration &amp; Workflow</h4>
          <div style="display:flex; flex-direction:column; gap: 8px;">
            <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
              <span>Subjects Examined:</span>
              <strong style="color:#0f172a;"><?= $subjects_examined ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
              <span>Pass Rate:</span>
              <strong style="color:#0f172a;"><?= $pass_rate ?>%</strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
              <span>Compiled Results:</span>
              <strong style="color:#0f172a;"><?= number_format($approved_results) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
              <span>Workflow (Pending / Approved):</span>
              <strong style="color:#0f172a;"><?= $pending_marks ?> / <?= number_format($approved_results) ?></strong>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- ═══ CHARTS SECTION ═══ -->
    <div class="rpt-chart-grid">

      <!-- Chart 1: Pass vs Fail Donut -->
      <div class="rpt-chart-box">
        <h3 class="rpt-chart-title">Pass vs Failure Ratio</h3>
        <p class="rpt-chart-sub">Doughnut representation of district pass / fail distribution</p>
        <div class="rpt-chart-body" style="text-align: center;">
          <?= $pass_fail_donut ?>
        </div>
      </div>

      <!-- Chart 2: Performance Trend Line -->
      <div class="rpt-chart-box">
        <h3 class="rpt-chart-title">District Performance Trends</h3>
        <p class="rpt-chart-sub">Average score trends across exam history for <?= htmlspecialchars($selected_district) ?> district</p>
        <div class="rpt-chart-body">
          <?= $trend_chart ?>
        </div>
      </div>

      <!-- Chart 3: School Performance Rankings hbar -->
      <div class="rpt-chart-box">
        <h3 class="rpt-chart-title">School Performance Rankings</h3>
        <p class="rpt-chart-sub">Horizontal comparative rankings of schools within <?= htmlspecialchars($selected_district) ?> district</p>
        <div class="rpt-chart-body">
          <?= $school_rankings_chart ?>
        </div>
      </div>

      <!-- Chart 4: Subject Performance Bar -->
      <div class="rpt-chart-box">
        <h3 class="rpt-chart-title">Subject Performance Analysis</h3>
        <p class="rpt-chart-sub">Top 8 subjects by average approved marks within the district</p>
        <div class="rpt-chart-body">
          <?= $subject_chart ?>
        </div>
      </div>

      <!-- Chart 5: Grade Distribution Histogram (full width) -->
      <div class="rpt-chart-box" style="grid-column: span 2;">
        <h3 class="rpt-chart-title">Grade Distribution (Malawi MSCE Scale)</h3>
        <p class="rpt-chart-sub">Histogram of candidate results from Distinction (1) to Fail (9) — <?= htmlspecialchars($selected_district) ?> district</p>
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