<?php
/* ════════════════════════════════════════════════════════════════
  examination_officer/reports.php
  Examination Officer: School-scoped analytics dashboard.
  Jurisdiction = session school_id (per-school, same as headteacher).
  Col 3 KPI = exam administration pipeline (marks received /
  pending / forwarded / compiled) — the EO's operational view.
  Same layout as admin/reports/index.php: 3-col KPI card + 5 charts.
  ════════════════════════════════════════════════════════════════ */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../common/report_stats.php';
require_once __DIR__ . '/../common/report_charts.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'examination_officer') {
  header("Location: ../login.php");
  exit();
}

$conn   = get_db_connection();
$school_id = (int)($_SESSION['school_id'] ?? 0);

// School info
$stmt = $conn->prepare("SELECT school_name, district, school_type FROM schools WHERE school_id = ?");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$school_info = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

// Exam selector
$exams_list = $conn->query(
  "SELECT exam_id, exam_name, year FROM exams ORDER BY year DESC, exam_name ASC"
)->fetch_all(MYSQLI_ASSOC);

$selected_exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
if ($selected_exam_id <= 0 && !empty($exams_list)) {
  $selected_exam_id = (int)$exams_list[0]['exam_id'];
}

/* ════════════════════════════════════════════════════════════════
  1. KPI STATISTICS — scoped to $school_id
  ════════════════════════════════════════════════════════════════ */

// Candidates sat
$stmt = $conn->prepare("
  SELECT COUNT(DISTINCT r.student_id) AS c
  FROM results r
  JOIN students s ON r.student_id = s.student_id
  WHERE s.school_id = ? AND r.exam_id = ? AND r.status = 'published'
");
$stmt->bind_param("ii", $school_id, $selected_exam_id);
$stmt->execute();
$total_sat = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

// Registered candidates
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM students WHERE school_id = ? AND status = 'active'");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$total_candidates = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
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
$stmt->bind_param("ii", $school_id, $selected_exam_id);
$stmt->execute();
$total_passed = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$total_failed = max(0, $total_sat - $total_passed);
$pass_rate  = $total_sat > 0 ? round(($total_passed / $total_sat) * 100, 1) : 0.0;
$fail_rate  = $total_sat > 0 ? round(($total_failed / $total_sat) * 100, 1) : 0.0;

// Best / Worst subject
$stmt = $conn->prepare("
  SELECT s.subject_name, s.subject_code, AVG(m.score) AS avg_score
  FROM marks m
  JOIN subjects s ON m.subject_id = s.subject_id
  JOIN students st ON m.student_id = st.student_id
  WHERE st.school_id = ? AND m.exam_id = ? AND m.status IN ('approved', 'submitted')
  GROUP BY m.subject_id
  ORDER BY avg_score DESC
");
$stmt->bind_param("ii", $school_id, $selected_exam_id);
$stmt->execute();
$subject_performance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$best_subject  = !empty($subject_performance) ? $subject_performance[0]['subject_name'] : 'N/A';
$worst_subject = count($subject_performance) > 1
  ? $subject_performance[count($subject_performance) - 1]['subject_name']
  : ($subject_performance[0]['subject_name'] ?? 'N/A');
$best_subj_avg = !empty($subject_performance) ? round((float)$subject_performance[0]['avg_score'], 1) : 0;
$worst_subj_avg = count($subject_performance) > 1
  ? round((float)$subject_performance[count($subject_performance) - 1]['avg_score'], 1)
  : 0;

// ── EO Workflow KPIs (marks pipeline) ──
$stmt = $conn->prepare("
  SELECT COUNT(*) AS c
  FROM marks m
  JOIN students s ON m.student_id = s.student_id
  WHERE s.school_id = ? AND m.exam_id = ? AND m.submission_status = 'received'
");
$stmt->bind_param("ii", $school_id, $selected_exam_id);
$stmt->execute();
$marks_received = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$stmt = $conn->prepare("
  SELECT COUNT(*) AS c
  FROM marks m
  JOIN students s ON m.student_id = s.student_id
  WHERE s.school_id = ? AND m.exam_id = ? AND m.submission_status = 'submitted'
");
$stmt->bind_param("ii", $school_id, $selected_exam_id);
$stmt->execute();
$marks_pending = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$stmt = $conn->prepare("
  SELECT COUNT(*) AS c
  FROM marks m
  JOIN students s ON m.student_id = s.student_id
  WHERE s.school_id = ? AND m.exam_id = ? AND m.submission_status = 'forwarded_to_edm'
");
$stmt->bind_param("ii", $school_id, $selected_exam_id);
$stmt->execute();
$marks_forwarded = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

// Total marks (approved status) — approved = final
$stmt = $conn->prepare("
  SELECT COUNT(*) AS c
  FROM marks m
  JOIN students s ON m.student_id = s.student_id
  WHERE s.school_id = ? AND m.exam_id = ? AND m.status = 'approved'
");
$stmt->bind_param("ii", $school_id, $selected_exam_id);
$stmt->execute();
$marks_approved = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

/* ════════════════════════════════════════════════════════════════
  2. CHARTS — scoped to $school_id
  ════════════════════════════════════════════════════════════════ */

// A. Pass vs Fail Donut
$pass_fail_donut = chart_donut([
  ['label' => 'Passed (>=45%)', 'value' => $total_passed, 'color' => '#22c55e'],
  ['label' => 'Failed (<45%)', 'value' => $total_failed, 'color' => '#ef4444'],
], ['size' => 180, 'center_text' => $pass_rate . '%', 'center_subtext' => 'Pass Rate']);

// B. Performance Trend Line
$stmt = $conn->prepare("
  SELECT e.exam_name, e.year, AVG(r.average_score) AS avg_score
  FROM results r
  JOIN exams e ON r.exam_id = e.exam_id
  JOIN students s ON r.student_id = s.student_id
  WHERE s.school_id = ? AND r.status = 'published'
  GROUP BY e.exam_id
  ORDER BY e.year ASC, e.start_date ASC
");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$trend_res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$trend_labels = array_map(fn($t) => $t['exam_name'] . ' (' . $t['year'] . ')', $trend_res);
$trend_values = array_map(fn($t) => (float)$t['avg_score'], $trend_res);
$trend_chart = chart_line($trend_labels, [
  ['label' => 'School Average', 'values' => $trend_values, 'color' => '#0d9488']
], ['height' => 200]);

// C. Subject Rankings hbar
$subj_hbar_labels  = array_column($subject_performance, 'subject_code');
$subj_hbar_values  = array_map(fn($s) => (float)$s['avg_score'], $subject_performance);
$subj_rankings_chart = chart_hbars($subj_hbar_labels, $subj_hbar_values, ['height' => 180]);

// D. Subject Performance Bar (top 8)
$subj_bar_labels = array_slice($subj_hbar_labels, 0, 8);
$subj_bar_values = array_slice($subj_hbar_values, 0, 8);
$subject_chart  = chart_bars($subj_bar_labels, $subj_bar_values, ['height' => 200, 'auto_color' => true]);

// E. Grade Distribution
$stmt = $conn->prepare("
  SELECT r.average_score
  FROM results r
  JOIN students s ON r.student_id = s.student_id
  WHERE s.school_id = ? AND r.exam_id = ? AND r.status = 'published'
");
$stmt->bind_param("ii", $school_id, $selected_exam_id);
$stmt->execute();
$scores_res   = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$all_scores   = array_map(fn($s) => (float)$s['average_score'], $scores_res);
$grade_dist   = stats_grade_distribution($all_scores);
$grade_histogram = chart_grade_histogram($grade_dist, ['height' => 200]);

$conn->close();

$portal_title = 'NED-SEMS | Examination Officer Reports';
$module_css  = 'exam_officer';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Examination Reports | Examination Officer Portal | NED-SEMS</title>
  <meta name="description" content="Examination officer performance reports for <?= htmlspecialchars($school_info['school_name'] ?? '') ?> — marks pipeline, subject standings, and grade distribution.">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
</head>
<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
  <?php include '../common/sidebar.php'; ?>

  <div class="content">

    <!-- Print Header -->
    <div class="print-header">
      <h2 class="print-title">Examination Performance Report: <?= htmlspecialchars($school_info['school_name'] ?? '') ?></h2>
      <div class="print-meta">Generated: <?= date('Y-m-d H:i:s') ?> | District: <?= htmlspecialchars($school_info['district'] ?? '') ?> | Role: Examination Officer</div>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h2 class="page-title">Examination Reports</h2>
        <p class="page-subtitle">
          <?= htmlspecialchars($school_info['school_name'] ?? '') ?> &nbsp;·&nbsp;
          <?= htmlspecialchars($school_info['district'] ?? '') ?> District &nbsp;|&nbsp;
          Marks pipeline, subject performance &amp; grade distribution
        </p>
      </div>
      <div class="header-actions">
        <button onclick="window.print()" class="btn btn-primary">️ Print Report</button>
      </div>
    </div>

    <!-- Exam Selector -->
    <div class="rpt-filter-panel no-print">
      <form method="GET" class="rpt-filter-form">
        <div class="rpt-filter-group">
          <label class="rpt-filter-label">Select Examination</label>
          <select name="exam_id" class="rpt-filter-select" onchange="this.form.submit()">
            <?php if (empty($exams_list)): ?>
              <option value="">No examinations found</option>
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

    <?php if ($selected_exam_id > 0): ?>

    <!-- ═══ KPI SUMMARY CARD ═══ -->
    <div class="card" style="padding: 22px; margin-bottom: 28px;">
      <h3 style="margin-top:0; margin-bottom: 16px; font-size: 14px; font-weight: 700; color: #334155; text-transform: uppercase; letter-spacing: 0.5px;">
        <?= htmlspecialchars($school_info['school_name'] ?? '') ?> — Examination Metrics Summary
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

        <!-- Col 2: Subject Standings -->
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
              <span>Pass Rate:</span>
              <strong style="color:#0f172a;"><?= $pass_rate ?>%</strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
              <span>Registered Candidates:</span>
              <strong style="color:#0f172a;"><?= number_format($total_candidates) ?></strong>
            </div>
          </div>
        </div>

        <!-- Col 3: Exam Administration & Marks Pipeline -->
        <div>
          <h4 style="font-size: 12.5px; font-weight: 600; color: #0f172a; margin-bottom: 12px; border-bottom: 2px solid var(--border-color); padding-bottom: 6px;">Exam Administration &amp; Workflow</h4>
          <div style="display:flex; flex-direction:column; gap: 8px;">
            <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
              <span>Marks Received:</span>
              <strong style="color:#0f172a;"><?= number_format($marks_received) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
              <span>Pending Submissions:</span>
              <strong style="color:<?= $marks_pending > 0 ? '#d97706' : '#16a34a' ?>;"><?= number_format($marks_pending) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
              <span>Forwarded to EDM:</span>
              <strong style="color:#0f172a;"><?= number_format($marks_forwarded) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
              <span>Marks Approved:</span>
              <strong style="color:#16a34a;"><?= number_format($marks_approved) ?></strong>
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
        <p class="rpt-chart-sub">Average score trends across exam history for <?= htmlspecialchars($school_info['school_name'] ?? '') ?></p>
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

      <!-- Chart 5: Grade Distribution Histogram (full width) -->
      <div class="rpt-chart-box" style="grid-column: span 2;">
        <h3 class="rpt-chart-title">Grade Distribution (Malawi MSCE Scale)</h3>
        <p class="rpt-chart-sub">Histogram of candidate results from Distinction (1) to Fail (9) — <?= htmlspecialchars($school_info['school_name'] ?? '') ?></p>
        <div class="rpt-chart-body">
          <?= $grade_histogram ?>
        </div>
      </div>

    </div>

    <?php else: ?>
      <div class="card" style="padding: 40px; text-align: center;">
        <p style="color: var(--text-muted);">Select an examination above to load the performance report.</p>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php include '../common/footer.php'; ?>

</body>
</html>
