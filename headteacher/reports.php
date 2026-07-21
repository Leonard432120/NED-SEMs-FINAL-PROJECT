<?php
/* ════════════════════════════════════════════════════════════════
  headteacher/reports.php
  Headteacher: School-scoped analytics dashboard.
  Auto-scoped to session school_id — no school selector.
  Same layout as admin/reports/index.php: 3-col KPI summary card
  then 5-chart rpt-chart-grid. Plus teacher workflow KPIs in col 3.
  ════════════════════════════════════════════════════════════════ */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../common/report_stats.php';
require_once __DIR__ . '/../common/report_charts.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'headteacher') {
  header("Location: ../login.php");
  exit();
}

$conn   = get_db_connection();
$school_id = (int)$_SESSION['school_id'];

// School info (safe prepared query)
$stmt = $conn->prepare("SELECT school_name, district, school_type FROM schools WHERE school_id = ?");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$school_info = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

// Exam selector
$exams_list = $conn->query(
  "SELECT DISTINCT e.exam_id, e.exam_name, e.year
   FROM exams e
   ORDER BY e.year DESC, e.exam_name ASC"
)->fetch_all(MYSQLI_ASSOC);

$selected_exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
if ($selected_exam_id <= 0 && !empty($exams_list)) {
  $selected_exam_id = (int)$exams_list[0]['exam_id'];
}

/* ════════════════════════════════════════════════════════════════
  1. KPI STATISTICS — scoped to $school_id
  ════════════════════════════════════════════════════════════════ */

// Candidates sat (published results)
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

// Registered candidates (active students in this school)
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM students WHERE school_id = ? AND status = 'active'");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$total_candidates = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$total_absent    = max(0, $total_candidates - $total_sat);
$participation_rate = $total_candidates > 0 ? round(($total_sat / $total_candidates) * 100, 1) : 0.0;

// Pass / Fail (>=45 threshold)
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

// Best / Worst subject in school
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

$best_subject = !empty($subject_performance) ? $subject_performance[0]['subject_name'] : 'N/A';
$worst_subject = count($subject_performance) > 1
  ? $subject_performance[count($subject_performance) - 1]['subject_name']
  : ($subject_performance[0]['subject_name'] ?? 'N/A');
$best_subj_avg = !empty($subject_performance) ? round((float)$subject_performance[0]['avg_score'], 1) : 0;
$worst_subj_avg = count($subject_performance) > 1
  ? round((float)$subject_performance[count($subject_performance) - 1]['avg_score'], 1)
  : 0;

// Teacher / Workflow KPIs
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM users WHERE school_id = ? AND role = 'teacher' AND status = 'active'");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$total_teachers = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM marking_assignments WHERE school_id = ? AND exam_id = ? AND status != 'completed'");
$stmt->bind_param("ii", $school_id, $selected_exam_id);
$stmt->execute();
$pending_marking = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM marking_assignments WHERE school_id = ? AND exam_id = ? AND status = 'completed'");
$stmt->bind_param("ii", $school_id, $selected_exam_id);
$stmt->execute();
$completed_marking = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

// Students needing support (score < 40)
$stmt = $conn->prepare("
  SELECT COUNT(*) AS c
  FROM results r
  JOIN students s ON r.student_id = s.student_id
  WHERE s.school_id = ? AND r.exam_id = ? AND r.average_score < 40 AND r.status = 'published'
");
$stmt->bind_param("ii", $school_id, $selected_exam_id);
$stmt->execute();
$students_needing_support = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

/* ════════════════════════════════════════════════════════════════
  2. CHARTS — all scoped to $school_id
  ════════════════════════════════════════════════════════════════ */

// A. Pass vs Fail Donut
$pass_fail_donut = chart_donut([
  ['label' => 'Passed (>=45%)', 'value' => $total_passed, 'color' => '#22c55e'],
  ['label' => 'Failed (<45%)', 'value' => $total_failed, 'color' => '#ef4444'],
], ['size' => 180, 'center_text' => $pass_rate . '%', 'center_subtext' => 'Pass Rate']);

// B. Performance Trend Line (school history)
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
  ['label' => 'School Average', 'values' => $trend_values, 'color' => '#8b5cf6']
], ['height' => 200]);

// C. Subject Rankings hbar
$subj_hbar_labels  = array_column($subject_performance, 'subject_code');
$subj_hbar_values  = array_map(fn($s) => (float)$s['avg_score'], $subject_performance);
$subj_rankings_chart = chart_hbars($subj_hbar_labels, $subj_hbar_values, ['height' => 180]);

// D. Subject Performance Bar (top 8, vertical)
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
$stmt->bind_param("ii", $school_id, $selected_exam_id);
$stmt->execute();
$scores_res   = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$all_scores   = array_map(fn($t) => (float)$t['average_score'], $scores_res);
$grade_dist   = stats_grade_distribution($all_scores);
$grade_histogram = chart_grade_histogram($grade_dist, ['height' => 200]);

/* ════════════════════════════════════════════════════════════════
  3. TREND SIGNAL — computed from the school's full history
  ════════════════════════════════════════════════════════════════ */
$trend_direction = 'flat'; // default
if (count($trend_values) >= 2) {
  $last  = $trend_values[count($trend_values) - 1];
  $prev  = $trend_values[count($trend_values) - 2];
  $delta = $last - $prev;
  if ($delta <= -1.0)      $trend_direction = 'declining';
  elseif ($delta >= 1.0)   $trend_direction = 'improving';
}

/* ════════════════════════════════════════════════════════════════
  4. FINDINGS DATA — all scoped to $school_id
  ════════════════════════════════════════════════════════════════ */

// 4a. Current finding for the selected exam
$current_finding = null;
if ($selected_exam_id > 0) {
  $stmt = $conn->prepare("
    SELECT f.*, o.outcome, o.outcome_detail, o.created_at AS outcome_at, adm_u.name AS edm_name
    FROM ht_findings f
    LEFT JOIN ht_finding_outcomes o ON o.finding_id = f.finding_id
    LEFT JOIN users adm_u ON adm_u.user_id = f.edm_responded_by
    WHERE f.school_id = ? AND f.exam_id = ?
  ");
  $stmt->bind_param("ii", $school_id, $selected_exam_id);
  $stmt->execute();
  $current_finding = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

// 4b. Pending-outcome prompt:
//     Open finding from the IMMEDIATELY PRECEDING exam (by year/date) for this school.
$pending_outcome_finding = null;
if ($selected_exam_id > 0) {
  // Find the exam that comes just before the selected one (by year then start_date)
  $stmt = $conn->prepare("
    SELECT f.finding_id, f.trend, f.cause_category, f.cause_detail,
           f.action_category, f.action_detail, f.pass_rate_pct, f.created_at,
           e.exam_name, e.year AS exam_year, e.year
    FROM ht_findings f
    JOIN exams e ON e.exam_id = f.exam_id
    WHERE f.school_id = ?
      AND f.lifecycle_status = 'open'
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
  $stmt->bind_param("iiii", $school_id, $selected_exam_id, $selected_exam_id, $selected_exam_id);
  $stmt->execute();
  $pending_outcome_finding = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

// 4c. Suggestion lookup — same school, same trend direction, closed with an outcome.
$suggestions = [];
if ($selected_exam_id > 0 && in_array($trend_direction, ['declining', 'improving'], true)) {
  $stmt = $conn->prepare("
    SELECT f.cause_category, f.cause_detail,
           f.action_category, f.action_detail,
           f.pass_rate_pct,  f.created_at,
           o.outcome, o.outcome_detail,
           e.exam_name, e.year AS exam_year, e.year
    FROM ht_findings f
    JOIN ht_finding_outcomes o ON o.finding_id = f.finding_id
    JOIN exams e ON e.exam_id = f.exam_id
    WHERE f.school_id = ?
      AND f.trend = ?
      AND f.lifecycle_status = 'closed'
    ORDER BY
      FIELD(o.outcome, 'improved', 'no_change', 'worsened'),
      f.created_at DESC
    LIMIT 5
  ");
  $stmt->bind_param("is", $school_id, $trend_direction);
  $stmt->execute();
  $suggestions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

$conn->close();

// ── Label helpers (shared by reports.php and findings.php) ────────
function cause_label_rpt(string $slug): string {
  return [
    'teacher_absenteeism'     => 'Teacher Absenteeism',
    'resource_shortage'       => 'Resource Shortage',
    'curriculum_gap'          => 'Curriculum Gap',
    'student_discipline'      => 'Student Discipline',
    'assessment_irregularity' => 'Assessment Irregularity',
    'illness_outbreak'        => 'Illness / Outbreak',
    'staff_turnover'          => 'Staff Turnover',
    'low_attendance'          => 'Low Attendance',
    'external_disruption'     => 'External Disruption',
    'positive_intervention'   => 'Positive Intervention',
    'other'                   => 'Other',
  ][$slug] ?? ucfirst(str_replace('_', ' ', $slug));
}
function action_label_rpt(string $slug): string {
  return [
    'remedial_classes'        => 'Remedial Classes',
    'staff_redeployment'      => 'Staff Redeployment',
    'resource_procurement'    => 'Resource Procurement',
    'parent_engagement'       => 'Parent Engagement',
    'curriculum_revision'     => 'Curriculum Revision',
    'attendance_campaign'     => 'Attendance Campaign',
    'pastoral_support'        => 'Pastoral Support',
    'peer_mentoring'          => 'Peer Mentoring Programme',
    'teacher_cpd'             => 'Teacher CPD / Training',
    'celebration_recognition' => 'Celebration & Recognition',
    'no_action_yet'           => 'No Action Yet',
    'other'                   => 'Other',
  ][$slug] ?? ucfirst(str_replace('_', ' ', $slug));
}
function outcome_label_rpt(string $slug): string {
  return ['improved' => 'Improved', 'no_change' => 'No Change', 'worsened' => 'Worsened'][$slug] ?? $slug;
}

$portal_title = 'NED-SEMS | Headteacher Reports';
$module_css  = 'headteacher';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>School Reports | Headteacher Portal | NED-SEMS</title>
  <meta name="description" content="Headteacher school performance reports — pass rates, subject standings, grade distribution and teacher workflow for <?= htmlspecialchars($school_info['school_name'] ?? '') ?>">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
</head>
<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
  <?php include '../common/sidebar.php'; ?>

  <div class="content">

    <!-- Print Header -->
    <div class="print-header">
      <h2 class="print-title">School Performance Report: <?= htmlspecialchars($school_info['school_name'] ?? '') ?></h2>
      <div class="print-meta">Generated: <?= date('Y-m-d H:i:s') ?> | District: <?= htmlspecialchars($school_info['district'] ?? '') ?> | Role: Headteacher</div>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h2 class="page-title">School Reports &amp; Analytics</h2>
        <p class="page-subtitle">
          <?= htmlspecialchars($school_info['school_name'] ?? '') ?> &nbsp;·&nbsp;
          <?= htmlspecialchars($school_info['district'] ?? '') ?> District &nbsp;·&nbsp;
          <?= htmlspecialchars($school_info['school_type'] ?? 'Secondary') ?>
        </p>
      </div>
      <div class="header-actions">
        <button onclick="window.print()" class="btn btn-primary">Print School Report</button>
      </div>
    </div>

    <!-- Exam Selector -->
    <div class="rpt-filter-panel no-print">
      <form method="GET" class="rpt-filter-form">
        <div class="rpt-filter-group">
          <label class="rpt-filter-label">Select Examination</label>
          <select name="exam_id" class="rpt-filter-select" onchange="this.form.submit()">
            <?php if (empty($exams_list)): ?>
              <option value="">No examinations registered</option>
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
        <?= htmlspecialchars($school_info['school_name'] ?? '') ?> — School Metrics Summary
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
              <span>Students Needing Support:</span>
              <strong style="color:<?= $students_needing_support > 0 ? '#dc2626' : '#16a34a' ?>;"><?= $students_needing_support ?></strong>
            </div>
          </div>
        </div>

        <!-- Col 3: Administration & Workflow -->
        <div>
          <h4 style="font-size: 12.5px; font-weight: 600; color: #0f172a; margin-bottom: 12px; border-bottom: 2px solid var(--border-color); padding-bottom: 6px;">Administration &amp; Workflow</h4>
          <div style="display:flex; flex-direction:column; gap: 8px;">
            <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
              <span>Teaching Staff:</span>
              <strong style="color:#0f172a;"><?= $total_teachers ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
              <span>Registered Candidates:</span>
              <strong style="color:#0f172a;"><?= number_format($total_candidates) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
              <span>Marking (Pending / Done):</span>
              <strong style="color:#0f172a;"><?= $pending_marking ?> / <?= $completed_marking ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569;">
              <span>Compiled Results:</span>
              <strong style="color:#0f172a;"><?= number_format($total_sat) ?></strong>
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

    <!-- Actionable Decision Support & Interventions -->
    <div class="rpt-rec-card" style="margin-top: 24px;">
      <h4> Academic &amp; Operational Diagnostics</h4>
      <ul class="rpt-rec-list">
        <?php if ($pass_rate < 50): ?>
          <li style="color:#b91c1c; font-weight:600;">
             Remedial Attention: The overall pass rate is currently <?= $pass_rate ?>%. Immediate school-wide diagnostic assessments and revision seminars are recommended.
          </li>
        <?php endif; ?>

        <?php if ($students_needing_support > 0): ?>
          <li>
            ️ Candidate Support Warning: There are <strong><?= $students_needing_support ?></strong> candidates performing below the 40% threshold. Arrange dedicated intervention sessions and revision circles.
          </li>
        <?php endif; ?>

        <?php 
        $critical_subs = [];
        foreach ($subject_performance as $sp) {
          if ($sp['avg_score'] < 40) {
            $critical_subs[] = htmlspecialchars($sp['subject_name']) . " (" . round($sp['avg_score'], 1) . "%)";
          }
        }
        if (!empty($critical_subs)): ?>
          <li>
             Curriculum Gaps: Syllabus performance is critically low in: <?= implode(', ', $critical_subs) ?>. Instruct department heads to prepare a diagnostic recovery plan.
          </li>
        <?php endif; ?>

        <?php 
        $total_tasks = $pending_marking + $completed_marking;
        if ($pending_marking > 0): ?>
          <li>
             Workload Latency: There are <strong><?= $pending_marking ?></strong> marking assignments pending entry. Review the <strong>Teacher Submission Report</strong> in the sidebar to flag delays.
          </li>
        <?php endif; ?>
        
        <li>
           Leadership Guideline: Use these results to optimize candidate streaming, resource allocation, and targeted tutoring programs prior to national examinations.
        </li>
      </ul>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         FINDINGS & INVESTIGATION SECTION
         All output is htmlspecialchars()-escaped. No cross-school
         data can appear here — every query used $school_id from
         $_SESSION, never from user input.
    ═══════════════════════════════════════════════════════════════ -->

    <?php
    /* Flash messages */
    $finding_saved  = isset($_GET['finding_saved']);
    $outcome_saved  = isset($_GET['outcome_saved']);
    $finding_error  = isset($_GET['finding_error']) ? htmlspecialchars(urldecode($_GET['finding_error'])) : '';
    $outcome_error  = isset($_GET['outcome_error']) ? htmlspecialchars(urldecode($_GET['outcome_error'])) : '';
    ?>

    <?php if ($finding_saved): ?>
      <div class="htf-alert htf-alert--success" role="alert">Investigation finding saved successfully.</div>
    <?php endif; ?>
    <?php if ($outcome_saved): ?>
      <div class="htf-alert htf-alert--success" role="alert">Outcome recorded — finding is now closed.</div>
    <?php endif; ?>
    <?php if ($finding_error): ?>
      <div class="htf-alert htf-alert--error" role="alert">Error: <?= $finding_error ?></div>
    <?php endif; ?>
    <?php if ($outcome_error): ?>
      <div class="htf-alert htf-alert--error" role="alert">Error: <?= $outcome_error ?></div>
    <?php endif; ?>

    <!-- ─── TREND BANNER ─────────────────────────────────────────── -->
    <?php if (count($trend_values) >= 2): ?>
    <div class="htf-trend-banner htf-trend-banner--<?= $trend_direction ?>" role="region" aria-label="Performance trend">
      <div class="htf-trend-banner-body">
        <strong class="htf-trend-banner-title">
          <?php if ($trend_direction === 'declining'): ?>
            Performance is Declining
          <?php elseif ($trend_direction === 'improving'): ?>
            Performance is Improving
          <?php else: ?>
            Performance is Stable
          <?php endif; ?>
        </strong>
        <span class="htf-trend-banner-sub">
          <?php
            $last_two = array_slice($trend_values, -2);
            $delta_pct = round($last_two[1] - $last_two[0], 1);
            $delta_str = ($delta_pct > 0 ? '+' : '') . $delta_pct . '% average score vs. previous exam';
          ?>
          <?= htmlspecialchars($delta_str) ?>
          <?php if ($trend_direction === 'declining'): ?>
            — scroll down to log what you found and what you are doing about it.
          <?php elseif ($trend_direction === 'improving'): ?>
            — scroll down to record what worked so it can be surfaced as evidence next time.
          <?php endif; ?>
        </span>
      </div>
    </div>
    <?php endif; ?>

    <!-- ─── PENDING OUTCOME PROMPT ────────────────────────────────── -->
    <?php if ($pending_outcome_finding): ?>
    <div class="htf-outcome-prompt" id="outcome-prompt">
      <div class="htf-outcome-prompt-header">
        <span class="htf-badge htf-badge--open">Action Awaiting Outcome</span>
        <strong>Did it work? Close out your <?= htmlspecialchars($pending_outcome_finding['exam_name']) ?> (<?= (int)($pending_outcome_finding['exam_year'] ?? $pending_outcome_finding['year'] ?? 0) ?>) finding.</strong>
      </div>
      <p style="font-size:0.875rem; color: var(--text-muted); margin: 0 0 14px;">
        In that cycle you identified <strong><?= htmlspecialchars(cause_label_rpt($pending_outcome_finding['cause_category'])) ?></strong>
        and responded with <strong><?= htmlspecialchars(action_label_rpt($pending_outcome_finding['action_category'])) ?></strong>.
        Now that a new exam cycle has results, record whether it made a difference.
      </p>
      <form method="POST" action="<?= BASE_URL ?>/headteacher/routes/save_outcome.php" class="htf-outcome-form">
        <input type="hidden" name="finding_id"      value="<?= (int)$pending_outcome_finding['finding_id'] ?>">
        <input type="hidden" name="current_exam_id" value="<?= $selected_exam_id ?>">
        <div class="htf-form-row">
          <div class="htf-form-group">
            <label class="htf-label" for="outcome-select">What happened? <span class="htf-required">*</span></label>
            <select name="outcome" id="outcome-select" class="rpt-filter-select" required>
              <option value="">— Select outcome —</option>
              <option value="improved">Improved — the pass rate/average went up</option>
              <option value="no_change">No Change — roughly the same</option>
              <option value="worsened">Worsened — things got worse</option>
            </select>
          </div>
          <div class="htf-form-group" style="flex:2;">
            <label class="htf-label" for="outcome-detail">Notes (optional)</label>
            <input type="text" name="outcome_detail" id="outcome-detail"
                   class="rpt-filter-input"
                   placeholder="Any additional context about what changed…"
                   maxlength="500">
          </div>
        </div>
        <div style="margin-top:12px;">
          <button type="submit" class="btn btn-primary">Record Outcome &amp; Close Finding</button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <!-- ─── SUGGESTION PANEL ──────────────────────────────────────── -->
    <?php if (!empty($suggestions)): ?>
    <details class="htf-suggestion-panel" open>
      <summary class="htf-suggestion-summary">
        <span>
          <?= count($suggestions) ?> similar situation<?= count($suggestions) !== 1 ? 's' : '' ?> in your history
          — see what was tried and what worked
        </span>
        <span class="htf-suggestion-chevron">▾</span>
      </summary>
      <div class="htf-suggestion-list">
        <?php foreach ($suggestions as $sg): ?>
        <div class="htf-suggestion-item htf-suggestion-item--<?= htmlspecialchars($sg['outcome']) ?>">
          <div class="htf-suggestion-meta">
            <span class="htf-badge <?= $sg['outcome'] === 'improved' ? 'htf-badge--improved' : ($sg['outcome'] === 'worsened' ? 'htf-badge--worsened' : 'htf-badge--nochange') ?>">
              <?= $sg['outcome'] === 'improved' ? 'Worked' : ($sg['outcome'] === 'worsened' ? 'Worsened' : 'No Change') ?>
            </span>
            <span style="font-size:0.8rem; color:var(--text-muted);">
              <?= htmlspecialchars($sg['exam_name']) ?> (<?= (int)($sg['exam_year'] ?? $sg['year'] ?? 0) ?>) · Pass rate was <?= number_format((float)$sg['pass_rate_pct'], 1) ?>%
            </span>
          </div>
          <div class="htf-suggestion-content">
            <div class="htf-suggestion-col">
              <span class="htf-suggestion-label">Root cause identified:</span>
              <span class="htf-cause-tag"><?= htmlspecialchars(cause_label_rpt($sg['cause_category'])) ?></span>
              <?php if (!empty($sg['cause_detail'])): ?>
                <p class="htf-detail-text"><?= htmlspecialchars($sg['cause_detail']) ?></p>
              <?php endif; ?>
            </div>
            <div class="htf-suggestion-col">
              <span class="htf-suggestion-label">Action taken:</span>
              <span class="htf-action-tag"><?= htmlspecialchars(action_label_rpt($sg['action_category'])) ?></span>
              <?php if (!empty($sg['action_detail'])): ?>
                <p class="htf-detail-text"><?= htmlspecialchars($sg['action_detail']) ?></p>
              <?php endif; ?>
            </div>
            <?php if (!empty($sg['outcome_detail'])): ?>
            <div class="htf-suggestion-col">
              <span class="htf-suggestion-label">What they observed:</span>
              <p class="htf-detail-text"><?= htmlspecialchars($sg['outcome_detail']) ?></p>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </details>
    <?php endif; ?>

    <!-- ─── CURRENT FINDING SUMMARY (already logged) ─────────────── -->
    <?php if ($current_finding): ?>
    <div class="htf-current-finding">
      <div class="htf-current-finding-header">
        <span class="htf-section-label">Your Investigation Finding — this exam</span>
        <span class="htf-badge <?= $current_finding['lifecycle_status'] === 'open' ? 'htf-badge--open' : 'htf-badge--closed' ?>">
          <?= $current_finding['lifecycle_status'] === 'open' ? 'Open' : 'Closed' ?>
        </span>
      </div>
      <div class="htf-finding-body">
        <div class="htf-finding-col">
          <div class="htf-finding-field-label">Root Cause</div>
          <div class="htf-finding-field-value">
            <span class="htf-cause-tag"><?= htmlspecialchars(cause_label_rpt($current_finding['cause_category'])) ?></span>
            <?php if (!empty($current_finding['cause_detail'])): ?>
              <p class="htf-detail-text"><?= htmlspecialchars($current_finding['cause_detail']) ?></p>
            <?php endif; ?>
          </div>
        </div>
        <div class="htf-finding-col">
          <div class="htf-finding-field-label">Action Taken</div>
          <div class="htf-finding-field-value">
            <span class="htf-action-tag"><?= htmlspecialchars(action_label_rpt($current_finding['action_category'])) ?></span>
            <?php if (!empty($current_finding['action_detail'])): ?>
              <p class="htf-detail-text"><?= htmlspecialchars($current_finding['action_detail']) ?></p>
            <?php endif; ?>
          </div>
        </div>
        <?php if (!empty($current_finding['outcome'])): ?>
        <div class="htf-finding-col">
          <div class="htf-finding-field-label">Recorded Outcome</div>
          <div class="htf-finding-field-value">
            <span class="htf-badge <?= $current_finding['outcome'] === 'improved' ? 'htf-badge--improved' : ($current_finding['outcome'] === 'worsened' ? 'htf-badge--worsened' : 'htf-badge--nochange') ?>">
              <?= htmlspecialchars(outcome_label_rpt($current_finding['outcome'])) ?>
            </span>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <?php if (!empty($current_finding['edm_feedback'])): ?>
        <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-left: 4px solid #0284c7; border-radius: 6px; padding: 14px 16px; margin-top: 14px;">
          <div style="font-size: 0.82rem; font-weight: 700; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
             Official EDM Office Response &amp; Pledged Support Intervention
          </div>
          <div style="font-size: 0.9rem; color: #0c4a6e; line-height: 1.5;">
            <?php if (!empty($current_finding['edm_action'])): ?>
              <p style="margin: 0 0 6px 0;"><strong>Pledged Support Intervention:</strong> <span style="background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 4px; font-weight: 600;"><?= htmlspecialchars($current_finding['edm_action']) ?></span></p>
            <?php endif; ?>
            <p style="margin: 0; white-space: pre-wrap;"><?= htmlspecialchars($current_finding['edm_feedback']) ?></p>
            <div style="font-size: 0.76rem; color: #0284c7; margin-top: 8px; font-style: italic;">
              Responded by <?= htmlspecialchars($current_finding['edm_name'] ?: 'EDM Office') ?> on <?= date('d M Y, H:i', strtotime($current_finding['edm_responded_at'])) ?>
            </div>
          </div>
        </div>
      <?php endif; ?>
      <?php if ($current_finding['lifecycle_status'] === 'open'): ?>
      <!-- Allow editing an open finding -->
      <details style="margin-top:14px;">
        <summary style="cursor:pointer; font-size:0.85rem; color:var(--info-color); font-weight:600;">Edit this finding</summary>
        <div style="margin-top:12px;">
          <?php
            // Reuse the form below with pre-filled values
            $prefill = $current_finding;
          ?>
          <?php include __DIR__ . '/partials/finding_form.php'; ?>
        </div>
      </details>
      <?php endif; ?>
    </div>
    <?php else: ?>

    <!-- ─── LOG NEW FINDING FORM ──────────────────────────────────── -->
    <div class="htf-log-form-card" id="log-finding">
      <div class="htf-section-header">
        <span class="htf-section-label">
          <?php if ($trend_direction === 'declining'): ?>Log Your Investigation — What Did You Find?
          <?php elseif ($trend_direction === 'improving'): ?>Record What Worked
          <?php else: ?>Log an Investigation Finding
          <?php endif; ?>
        </span>
        <span style="font-size:0.8rem; color:var(--text-muted);">This finding will be searchable and surfaced as evidence in future cycles.</span>
      </div>

      <?php $prefill = null; ?>
      <?php include __DIR__ . '/partials/finding_form.php'; ?>
    </div>

    <?php endif; /* $current_finding */ ?>

    <!-- Link to full history -->
    <div style="text-align:right; margin-top: 12px; margin-bottom: 24px;">
      <a href="<?= BASE_URL ?>/headteacher/findings.php" class="btn btn-secondary btn-small">
        View Full Findings History →
      </a>
    </div>

    <?php else: ?>
      <div class="card" style="padding: 40px; text-align: center;">
        <p style="color: var(--text-muted);">Select an examination above to load the school performance report.</p>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php include '../common/footer.php'; ?>

</body>
</html>