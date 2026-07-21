<?php
/* ════════════════════════════════════════════════════════════════
  admin/reports/school_report.php
  EDM/Admin: School Performance — two modes:
   MODE 1 (school_id > 0): Single-school deep-dive, mirrors
    index.php layout exactly, scoped to one school.
   MODE 2 (no school_id): Division-wide comparative list.
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

// Selector data
$exams_list   = $conn->query("SELECT exam_id, exam_name, class, year FROM exams ORDER BY year DESC, start_date DESC")->fetch_all(MYSQLI_ASSOC);
$districts_list = $conn->query("SELECT DISTINCT district FROM schools WHERE status='active' ORDER BY district ASC")->fetch_all(MYSQLI_ASSOC);

// Filters
$selected_school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;
$selected_exam_id  = isset($_GET['exam_id'])  ? (int)$_GET['exam_id']  : 0;
$selected_district = isset($_GET['district']) ? trim($_GET['district']) : '';
$search       = isset($_GET['search'])  ? trim($_GET['search'])  : '';

if ($selected_exam_id <= 0 && !empty($exams_list)) {
  $pub_stmt = $conn->query("
    SELECT e.exam_id 
    FROM exams e 
    JOIN results r ON e.exam_id = r.exam_id 
    WHERE r.status = 'published' 
    ORDER BY e.year DESC, e.start_date DESC 
    LIMIT 1
  ");
  $published_exam = $pub_stmt ? $pub_stmt->fetch_assoc() : null;
  if ($published_exam) {
    $selected_exam_id = (int)$published_exam['exam_id'];
  } else {
    $selected_exam_id = (int)$exams_list[0]['exam_id'];
  }
}

/* ────────────────────────────────────────────────────────────────
  MODE 1: SINGLE SCHOOL DEEP DIVE — mirrors index.php layout
  ──────────────────────────────────────────────────────────────── */
if ($selected_school_id > 0) {

  // School info
  $stmt = $conn->prepare("SELECT * FROM schools WHERE school_id = ?");
  $stmt->bind_param("i", $selected_school_id);
  $stmt->execute();
  $school_info = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$school_info) { die("School not found."); }

  // Exam info
  $exam_info = [];
  if ($selected_exam_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM exams WHERE exam_id = ?");
    $stmt->bind_param("i", $selected_exam_id);
    $stmt->execute();
    $exam_info = $stmt->get_result()->fetch_assoc() ?? [];
    $stmt->close();
  }

  /* ── KPI: Candidacy & Participation ── */
  $exam_class = $exam_info['class'] ?? '';
  if ($exam_class !== '') {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM students WHERE school_id = ? AND class = ? AND status='active'");
    $stmt->bind_param("is", $selected_school_id, $exam_class);
    $stmt->execute();
    $total_candidates = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
  } else {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM students WHERE school_id = ? AND status='active'");
    $stmt->bind_param("i", $selected_school_id);
    $stmt->execute();
    $total_candidates = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
  }

  $stmt = $conn->prepare("
    SELECT COUNT(DISTINCT r.student_id) AS c
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    WHERE s.school_id = ? AND r.exam_id = ? AND r.status = 'published'
  ");
  $stmt->bind_param("ii", $selected_school_id, $selected_exam_id);
  $stmt->execute();
  $total_sat = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
  $stmt->close();

  $total_absent    = max(0, $total_candidates - $total_sat);
  $participation_rate = $total_candidates > 0 ? round(($total_sat / $total_candidates) * 100, 1) : 0.0;

  // Pass / Fail (>=45)
  $stmt = $conn->prepare("
    SELECT COUNT(DISTINCT r.student_id) AS c
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    WHERE s.school_id = ? AND r.exam_id = ? AND r.status = 'published' AND r.average_score >= 45
  ");
  $stmt->bind_param("ii", $selected_school_id, $selected_exam_id);
  $stmt->execute();
  $total_passed = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
  $stmt->close();

  $total_failed = max(0, $total_sat - $total_passed);
  $pass_rate  = $total_sat > 0 ? round(($total_passed / $total_sat) * 100, 1) : 0.0;
  $fail_rate  = $total_sat > 0 ? round(($total_failed / $total_sat) * 100, 1) : 0.0;

  /* ── KPI: Best / Worst Subject within school ── */
  $stmt = $conn->prepare("
    SELECT s.subject_name, s.subject_code, AVG(m.score) AS avg_score
    FROM marks m
    JOIN subjects s ON m.subject_id = s.subject_id
    JOIN students st ON m.student_id = st.student_id
    WHERE st.school_id = ? AND m.exam_id = ? AND m.status = 'approved'
    GROUP BY m.subject_id
    ORDER BY avg_score DESC
  ");
  $stmt->bind_param("ii", $selected_school_id, $selected_exam_id);
  $stmt->execute();
  $subject_performance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  $best_subject = !empty($subject_performance) ? $subject_performance[0]['subject_name'] : 'N/A';
  $worst_subject = count($subject_performance) > 1
    ? $subject_performance[count($subject_performance) - 1]['subject_name']
    : ($subject_performance[0]['subject_name'] ?? 'N/A');
  $best_subj_avg = !empty($subject_performance) ? round((float)$subject_performance[0]['avg_score'], 1) : 0;
  $worst_subj_avg = count($subject_performance) > 1
    ? round((float)$subject_performance[count($subject_performance) - 1]['avg_score'], 1)
    : 0;

  /* ── KPI: School context (ranks) ── */
  // District average for comparison
  $stmt = $conn->prepare("
    SELECT AVG(r.average_score) AS avg_score
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    JOIN schools sc ON s.school_id = sc.school_id
    WHERE r.exam_id = ? AND r.status = 'published' AND sc.district = ?
  ");
  $stmt->bind_param("is", $selected_exam_id, $school_info['district']);
  $stmt->execute();
  $district_avg = round((float)($stmt->get_result()->fetch_assoc()['avg_score'] ?? 0), 1);
  $stmt->close();

  // School average
  $stmt = $conn->prepare("
    SELECT AVG(r.average_score) AS v
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    WHERE s.school_id = ? AND r.exam_id = ? AND r.status = 'published'
  ");
  $stmt->bind_param("ii", $selected_school_id, $selected_exam_id);
  $stmt->execute();
  $school_avg = round((float)($stmt->get_result()->fetch_assoc()['v'] ?? 0), 1);
  $stmt->close();

  // Division rank (national)
  $stmt = $conn->prepare("
    SELECT s.school_id, AVG(r.average_score) AS avg_score
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    WHERE r.exam_id = ? AND r.status = 'published'
    GROUP BY s.school_id
    ORDER BY avg_score DESC
  ");
  $stmt->bind_param("i", $selected_exam_id);
  $stmt->execute();
  $div_ranks    = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $total_schools_div = count($div_ranks);
  $div_rank     = 1;
  foreach ($div_ranks as $idx => $dr) {
    if ((int)$dr['school_id'] === $selected_school_id) { $div_rank = $idx + 1; break; }
  }
  $stmt->close();

  // District rank
  $stmt = $conn->prepare("
    SELECT s.school_id, AVG(r.average_score) AS avg_score
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    JOIN schools sc ON s.school_id = sc.school_id
    WHERE r.exam_id = ? AND r.status = 'published' AND sc.district = ?
    GROUP BY s.school_id
    ORDER BY avg_score DESC
  ");
  $stmt->bind_param("is", $selected_exam_id, $school_info['district']);
  $stmt->execute();
  $dist_ranks    = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $total_schools_dist = count($dist_ranks);
  $dist_rank     = 1;
  foreach ($dist_ranks as $idx => $dr) {
    if ((int)$dr['school_id'] === $selected_school_id) { $dist_rank = $idx + 1; break; }
  }
  $stmt->close();

  // Pending marks for school
  $stmt = $conn->prepare("
    SELECT COUNT(*) AS c FROM marking_assignments WHERE school_id = ? AND exam_id = ? AND status != 'completed'
  ");
  $stmt->bind_param("ii", $selected_school_id, $selected_exam_id);
  $stmt->execute();
  $school_pending = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
  $stmt->close();

  /* ── CHARTS ── */

  // A. Pass vs Fail Donut
  $pass_fail_donut = chart_donut([
    ['label' => 'Passed (>=45%)', 'value' => $total_passed, 'color' => '#22c55e'],
    ['label' => 'Failed (<45%)', 'value' => $total_failed, 'color' => '#ef4444'],
  ], ['size' => 180, 'center_text' => $pass_rate . '%', 'center_subtext' => 'Pass Rate']);

  // B. Performance Trend Line (school history, all exams)
  $stmt = $conn->prepare("
    SELECT e.exam_name, e.year, AVG(r.average_score) AS avg_score
    FROM results r
    JOIN exams e ON r.exam_id = e.exam_id
    JOIN students s ON r.student_id = s.student_id
    WHERE s.school_id = ? AND r.status = 'published'
    GROUP BY e.exam_id
    ORDER BY e.year ASC, e.start_date ASC
  ");
  $stmt->bind_param("i", $selected_school_id);
  $stmt->execute();
  $hist = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  $hist_labels = array_map(fn($h) => $h['exam_name'] . ' (' . $h['year'] . ')', $hist);
  $hist_values = array_map(fn($h) => (float)$h['avg_score'], $hist);
  $trend_chart = chart_line($hist_labels, [
    ['label' => 'School Average', 'values' => $hist_values, 'color' => '#8b5cf6']
  ], ['height' => 200]);

  // C. Subject Rankings hbar (subjects within school)
  $subj_hbar_labels = array_column($subject_performance, 'subject_code');
  $subj_hbar_values = array_map(fn($s) => (float)$s['avg_score'], $subject_performance);
  $subj_rankings_chart = chart_hbars($subj_hbar_labels, $subj_hbar_values, ['height' => 180]);

  // D. Subject Performance Bar (same data, vertical)
  $subj_bar_labels = array_slice($subj_hbar_labels, 0, 8);
  $subj_bar_values = array_slice($subj_hbar_values, 0, 8);
  $subject_chart  = chart_bars($subj_bar_labels, $subj_bar_values, ['height' => 200, 'auto_color' => true]);

  // E. Grade Distribution Histogram
  $stmt = $conn->prepare("
    SELECT r.average_score
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    WHERE s.school_id = ? AND r.exam_id = ? AND r.status = 'published'
  ");
  $stmt->bind_param("ii", $selected_school_id, $selected_exam_id);
  $stmt->execute();
  $scores_res  = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  $school_scores  = array_map(fn($s) => (float)$s['average_score'], $scores_res);
  $grade_dist   = stats_grade_distribution($school_scores);
  $grade_histogram = chart_grade_histogram($grade_dist, ['height' => 200]);

} else {
  /* ────────────────────────────────────────────────────────────────
    MODE 2: DIVISION COMPARATIVE LIST (unchanged from original)
    ──────────────────────────────────────────────────────────────── */

  $where_clauses = ["sc.status = 'active'"];
  $params = [];
  $types = "";

  if ($selected_district !== '') {
    $where_clauses[] = "sc.district = ?";
    $params[]    = $selected_district;
    $types     .= "s";
  }
  if ($search !== '') {
    $where_clauses[]  = "sc.school_name LIKE ?";
    $params[]     = "%{$search}%";
    $types      .= "s";
  }

  $where_sql = implode(" AND ", $where_clauses);

  $count_q = "SELECT COUNT(*) AS c FROM schools sc WHERE {$where_sql}";
  $stmt = $conn->prepare($count_q);
  if (!empty($params)) { $stmt->bind_param($types, ...$params); }
  $stmt->execute();
  $total_schools = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
  $stmt->close();

  $page    = isset($_GET['page']) ? (int)$_GET['page'] : 1;
  $per_page  = 5;
  $pagination = paginate($total_schools, $page, $per_page);
  $offset   = ($pagination['page'] - 1) * $pagination['per_page'];

  $list_q = "
    SELECT sc.school_id, sc.school_name, sc.district, sc.school_type,
        AVG(r.average_score) AS avg_score,
        COUNT(DISTINCT s.student_id) AS candidates_count,
        SUM(r.average_score >= 45) AS passed_count,
        COUNT(r.result_id) AS sat_count
    FROM schools sc
    LEFT JOIN students s ON sc.school_id = s.school_id AND s.status='active'
    LEFT JOIN results r ON s.student_id = r.student_id AND r.exam_id = ? AND r.status='published'
    WHERE {$where_sql}
    GROUP BY sc.school_id
    ORDER BY avg_score DESC
    LIMIT ? OFFSET ?
  ";
  $stmt    = $conn->prepare($list_q);
  $list_params = array_merge([$selected_exam_id], $params, [$per_page, $offset]);
  $list_types = "i" . $types . "ii";
  $stmt->bind_param($list_types, ...$list_params);
  $stmt->execute();
  $schools_comparative = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  // Critical schools (mean < 40)
  $stmt = $conn->prepare("
    SELECT sc.school_name, sc.district, AVG(r.average_score) AS avg_score
    FROM schools sc
    JOIN students s ON sc.school_id = s.school_id AND s.status='active'
    JOIN results r ON s.student_id = r.student_id AND r.exam_id = ? AND r.status='published'
    WHERE sc.status='active'
    GROUP BY sc.school_id
    HAVING avg_score < 40
    ORDER BY avg_score ASC
  ");
  $stmt->bind_param("i", $selected_exam_id);
  $stmt->execute();
  $critical_schools = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  // Schools list for selector (filtered by district if selected)
  if ($selected_district !== '') {
    $stmt = $conn->prepare("SELECT school_id, school_name FROM schools WHERE status='active' AND district = ? ORDER BY school_name ASC");
    $stmt->bind_param("s", $selected_district);
    $stmt->execute();
    $schools_for_selector = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
  } else {
    $schools_for_selector = $conn->query("SELECT school_id, school_name FROM schools WHERE status='active' ORDER BY school_name ASC")->fetch_all(MYSQLI_ASSOC);
  }
}

$conn->close();

$portal_title = 'NED-SEMS | School Performance Reports';
$module_css  = 'admin';
include __DIR__ . '/../../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>School Performance Reports | NED-SEMS</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
</head>
<body>

<?php include __DIR__ . '/../../common/header.php'; ?>

<div class="dashboard">
  <?php include __DIR__ . '/../../common/sidebar.php'; ?>

  <div class="content">

    <!-- ════════════════════════════════════════════════════════════
       MODE 1 — SINGLE SCHOOL DETAILED REPORT (mirrors index.php)
       ════════════════════════════════════════════════════════════ -->
    <?php if ($selected_school_id > 0): ?>

      <!-- Print Header -->
      <div class="print-header">
        <h2 class="print-title">School Performance Report: <?= htmlspecialchars($school_info['school_name']) ?></h2>
        <div class="print-meta">Generated: <?= date('Y-m-d H:i:s') ?> | District: <?= htmlspecialchars($school_info['district']) ?> | Role: Administrator</div>
      </div>

      <!-- Page Header -->
      <div class="page-header">
        <div>
          <h2 class="page-title"><?= htmlspecialchars($school_info['school_name']) ?></h2>
          <p class="page-subtitle">
            District: <strong><?= htmlspecialchars($school_info['district']) ?></strong> &nbsp;|&nbsp;
            Type: <strong><?= htmlspecialchars($school_info['school_type'] ?: 'Day Secondary') ?></strong> &nbsp;|&nbsp;
            Performance: <strong><?= stats_performance_category($school_avg) ?></strong>
          </p>
        </div>
        <div class="header-actions">
          <a href="school_report.php?exam_id=<?= $selected_exam_id ?>" class="btn btn-secondary">← Schools Overview</a>
          <button onclick="window.print()" class="btn btn-primary">Print School Report</button>
        </div>
      </div>

      <!-- Exam Selector -->
      <div class="rpt-filter-panel no-print">
        <form method="GET" class="rpt-filter-form">
          <input type="hidden" name="school_id" value="<?= $selected_school_id ?>">
          <div class="rpt-filter-group">
            <label class="rpt-filter-label">Select Examination</label>
            <select name="exam_id" class="rpt-filter-select" onchange="this.form.submit()">
              <?php foreach ($exams_list as $ex): ?>
                <option value="<?= $ex['exam_id'] ?>" <?= $selected_exam_id === (int)$ex['exam_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($ex['exam_name']) ?> (<?= htmlspecialchars($ex['year']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="rpt-filter-actions">
            <button type="submit" class="btn btn-primary">Load</button>
          </div>
        </form>
      </div>

      <!-- ═══ KPI SUMMARY CARD ═══ -->
      <div class="card" style="padding: 22px; margin-bottom: 28px;">
        <h3 style="margin-top:0; margin-bottom: 16px; font-size: 14px; font-weight: 700; color: #334155; text-transform: uppercase; letter-spacing: 0.5px;">
          <?= htmlspecialchars($school_info['school_name']) ?> — Performance Metrics Summary
        </h3>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px;">

          <!-- Col 1: Candidacy & Participation -->
          <div style="border-right: 1px solid var(--border-color); padding-right: 14px;">
            <h4 style="font-size: 12.5px; font-weight: 600; color: #0f172a; margin-bottom: 12px; border-bottom: 2px solid var(--border-color); padding-bottom: 6px;">Candidacy &amp; Participation</h4>
            <div style="display:flex; flex-direction:column; gap: 8px;">
              <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
                <span>Candidates Sat:</span>
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

          <!-- Col 2: Best / Worst Subject within school -->
          <div style="border-right: 1px solid var(--border-color); padding-right: 14px;">
            <h4 style="font-size: 12.5px; font-weight: 600; color: #0f172a; margin-bottom: 12px; border-bottom: 2px solid var(--border-color); padding-bottom: 6px;">Subject Standings</h4>
            <div style="display:flex; flex-direction:column; gap: 8px;">
              <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
                <span>Best Performing Subject:</span>
                <strong style="color:#16a34a;" title="<?= htmlspecialchars($best_subject) ?>"><?= htmlspecialchars(mb_strimwidth($best_subject, 0, 20, '…')) ?> (<?= $best_subj_avg ?>%)</strong>
              </div>
              <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
                <span>Weakest Subject:</span>
                <strong style="color:#dc2626;" title="<?= htmlspecialchars($worst_subject) ?>"><?= htmlspecialchars(mb_strimwidth($worst_subject, 0, 20, '…')) ?> (<?= $worst_subj_avg ?>%)</strong>
              </div>
              <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
                <span>School Average:</span>
                <strong style="color:#0f172a;"><?= $school_avg ?>%</strong>
              </div>
              <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
                <span>District Average:</span>
                <strong style="color:#0f172a;"><?= $district_avg ?>%</strong>
              </div>
            </div>
          </div>

          <!-- Col 3: School Context (ranks + workflow) -->
          <div>
            <h4 style="font-size: 12.5px; font-weight: 600; color: #0f172a; margin-bottom: 12px; border-bottom: 2px solid var(--border-color); padding-bottom: 6px;">Rankings &amp; Workflow</h4>
            <div style="display:flex; flex-direction:column; gap: 8px;">
              <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
                <span>District Rank:</span>
                <strong style="color:#0f172a;"><?= $dist_rank ?> of <?= $total_schools_dist ?></strong>
              </div>
              <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
                <span>Division Rank (National):</span>
                <strong style="color:#0f172a;"><?= $div_rank ?> of <?= $total_schools_div ?></strong>
              </div>
              <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
                <span>Pass Rate:</span>
                <strong style="color:#0f172a;"><?= $pass_rate ?>%</strong>
              </div>
              <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
                <span>Pending Marking Tasks:</span>
                <strong style="color:#0f172a;"><?= $school_pending ?></strong>
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
          <p class="rpt-chart-sub">Doughnut representation of school pass / fail distribution</p>
          <div class="rpt-chart-body" style="text-align: center;">
            <?= $pass_fail_donut ?>
          </div>
        </div>

        <!-- Chart 2: Performance Trend Line -->
        <div class="rpt-chart-box">
          <h3 class="rpt-chart-title">School Performance Trends</h3>
          <p class="rpt-chart-sub">Average score trends across exam history for this school</p>
          <div class="rpt-chart-body">
            <?= $trend_chart ?>
          </div>
        </div>

        <!-- Chart 3: Subject Rankings hbar -->
        <div class="rpt-chart-box">
          <h3 class="rpt-chart-title">Subject Performance Rankings</h3>
          <p class="rpt-chart-sub">Horizontal ranking of subjects by average score within this school</p>
          <div class="rpt-chart-body">
            <?= $subj_rankings_chart ?>
          </div>
        </div>

        <!-- Chart 4: Subject Performance Bar -->
        <div class="rpt-chart-box">
          <h3 class="rpt-chart-title">Subject Performance Analysis</h3>
          <p class="rpt-chart-sub">Top 8 subjects by average approved marks in this school</p>
          <div class="rpt-chart-body">
            <?= $subject_chart ?>
          </div>
        </div>

        <!-- Chart 5: Grade Distribution Histogram -->
        <div class="rpt-chart-box" style="grid-column: span 2;">
          <h3 class="rpt-chart-title">Grade Distribution (Malawi MSCE Scale)</h3>
          <p class="rpt-chart-sub">Histogram of candidate results from Distinction (1) to Fail (9) — <?= htmlspecialchars($school_info['school_name']) ?></p>
          <div class="rpt-chart-body">
            <?= $grade_histogram ?>
          </div>
        </div>

      </div>

    <?php else: ?>
    <!-- ════════════════════════════════════════════════════════════
       MODE 2 — DIVISION COMPARATIVE LIST
       ════════════════════════════════════════════════════════════ -->

      <!-- Print Header -->
      <div class="print-header">
        <h2 class="print-title">School Performance Standings &amp; Statistics</h2>
        <div class="print-meta">Generated: <?= date('Y-m-d') ?> | Division EDM Office</div>
      </div>

      <div class="page-header">
        <div>
          <h2 class="page-title">School Performance Reports</h2>
          <p class="page-subtitle">National schools overview, rankings and subject statistics</p>
        </div>
        <div class="header-actions">
          <button onclick="window.print()" class="btn btn-secondary">️ Print Report</button>
        </div>
      </div>

      <!-- Filters -->
      <div class="rpt-filter-panel">
        <form method="GET" class="rpt-filter-form">
          <div class="rpt-filter-group">
            <label class="rpt-filter-label">Examination</label>
            <select name="exam_id" class="rpt-filter-select" onchange="this.form.submit()">
              <?php foreach ($exams_list as $ex): ?>
                <option value="<?= $ex['exam_id'] ?>" <?= $selected_exam_id === (int)$ex['exam_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($ex['exam_name']) ?> (<?= htmlspecialchars($ex['year']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="rpt-filter-group">
            <label class="rpt-filter-label">District</label>
            <select name="district" class="rpt-filter-select" onchange="this.form.submit()">
              <option value="">All Districts</option>
              <?php foreach ($districts_list as $d): ?>
                <option value="<?= htmlspecialchars($d['district']) ?>" <?= $selected_district === $d['district'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($d['district']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="rpt-filter-group">
            <label class="rpt-filter-label">Jump to School</label>
            <select name="school_id" class="rpt-filter-select" onchange="this.form.submit()">
              <option value="">Select a school...</option>
              <?php foreach ($schools_for_selector as $sc): ?>
                <option value="<?= $sc['school_id'] ?>">
                  <?= htmlspecialchars($sc['school_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="rpt-filter-group">
            <label class="rpt-filter-label">Search School</label>
            <input type="text" name="search" class="rpt-filter-input" placeholder="School name..." value="<?= htmlspecialchars($search) ?>">
          </div>

          <div class="rpt-filter-actions">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="school_report.php" class="btn btn-secondary">Reset</a>
          </div>
        </form>
      </div>

      <!-- Critical schools alert -->
      <?php if (!empty($critical_schools)): ?>
        <div class="rpt-rec-card" style="background:#fef2f2; border-color:#fecaca; color:#991b1b; margin-bottom:24px;">
          <h4> Schools Requiring Attention (Mean Score &lt; 40%)</h4>
          <ul class="rpt-rec-list">
            <?php foreach ($critical_schools as $cs): ?>
              <li style="color:#991b1b;"><strong><?= htmlspecialchars($cs['school_name']) ?></strong> (<?= htmlspecialchars($cs['district']) ?>) — avg: <strong><?= number_format($cs['avg_score'], 1) ?>%</strong></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- Comparative Table -->
      <div class="card">
        <div class="section-header">
          <h3>School Comparative Directory</h3>
          <span style="font-size:0.875rem; color:var(--text-muted);">Showing <?= count($schools_comparative) ?> of <?= $total_schools ?> institutions</span>
        </div>
        <div class="rpt-table-wrap">
          <table class="rpt-table">
            <thead>
              <tr>
                <th class="rank-col">Rank</th>
                <th>School Center</th>
                <th>District</th>
                <th>Type</th>
                <th class="val-col">Candidates</th>
                <th class="val-col">Mean Score</th>
                <th class="val-col">Pass Rate (&ge;45)</th>
                <th>Status</th>
                <th class="no-print">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($schools_comparative)): ?>
                <tr><td colspan="9" class="empty-state">No compiled school performance data found.</td></tr>
              <?php else: ?>
                <?php foreach ($schools_comparative as $idx => $sch):
                  $rank    = $offset + $idx + 1;
                  $avg    = $sch['avg_score'] !== null ? (float)$sch['avg_score'] : null;
                  $pass_ratio = $sch['sat_count'] > 0 ? round(($sch['passed_count'] / $sch['sat_count']) * 100, 1) : 0.0;
                  $status   = $avg !== null ? stats_performance_category($avg) : 'N/A';
                ?>
                  <tr>
                    <td class="rank-col"><?= $rank ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($sch['school_name']) ?></td>
                    <td><?= htmlspecialchars($sch['district']) ?></td>
                    <td><span style="font-size:0.75rem; background:#f1f5f9; padding:2px 8px; border-radius:4px; font-weight:700;"><?= htmlspecialchars($sch['school_type'] ?: 'CDSS') ?></span></td>
                    <td class="val-col"><?= $sch['candidates_count'] ?></td>
                    <td class="val-col" style="color:var(--info-color);"><?= $avg !== null ? number_format($avg, 1) . '%' : '—' ?></td>
                    <td class="val-col"><?= $sch['sat_count'] > 0 ? $pass_ratio . '%' : '—' ?></td>
                    <td><?= stats_badge($status) ?></td>
                    <td class="no-print">
                      <a href="school_report.php?school_id=<?= $sch['school_id'] ?>&exam_id=<?= $selected_exam_id ?>" class="btn btn-secondary" style="font-size:0.75rem; padding:4px 8px;">Explore →</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if ($pagination['total_pages'] > 1): ?>
          <div style="margin-top:20px; display:flex; justify-content:center;">
            <?= render_pagination($pagination, 'school_report.php') ?>
          </div>
        <?php endif; ?>
      </div>

    <?php endif; ?>

  </div>
</div>

<?php include __DIR__ . '/../../common/footer.php'; ?>

</body>
</html>
