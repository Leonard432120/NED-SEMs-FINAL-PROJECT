<?php
/* ════════════════════════════════════════════════════════════════
  headteacher/performance.php
  HT: Performance & Statistical Reporting Dashboard
  Auto-scoped to session school_id.
  Integrates data from Teacher Module (Item Writing & Moderation) 
  and Examination Officer Module (Marks Pipeline) into a cohesive 
  school-wide statistical report for the Headteacher.
  ════════════════════════════════════════════════════════════════ */
session_start();
require_once '../config/db.php';
require_once '../common/report_stats.php';
require_once '../common/report_charts.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'headteacher') {
  header("Location: ../login.php");
  exit();
}

$conn = get_db_connection();
$school_id = (int)$_SESSION['school_id'];

// Fetch school details
$stmt = $conn->prepare("SELECT school_name, district, school_type FROM schools WHERE school_id = ?");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$school_info = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

/* ════════════════════════════════════════════════════════════════
  1. CORE ACADEMIC STATISTICS (from compiled results)
  ════════════════════════════════════════════════════════════════ */
$avg_score = round((float)($conn->query("
  SELECT AVG(r.average_score) AS v 
  FROM results r 
  JOIN students s ON r.student_id = s.student_id 
  WHERE s.school_id = $school_id AND r.status = 'published'
")->fetch_assoc()['v'] ?? 0), 1);

$total_results = (int)$conn->query("
  SELECT COUNT(*) AS t 
  FROM results r 
  JOIN students s ON r.student_id = s.student_id 
  WHERE s.school_id = $school_id AND r.status = 'published'
")->fetch_assoc()['t'];

$exams_participated = (int)$conn->query("
  SELECT COUNT(DISTINCT r.exam_id) AS c
  FROM results r
  JOIN students s ON r.student_id = s.student_id
  WHERE s.school_id = $school_id AND r.status = 'published'
")->fetch_assoc()['c'];

/* ════════════════════════════════════════════════════════════════
  2. MARKS PIPELINE STATS (from Teacher & Exam Officer marks)
  ════════════════════════════════════════════════════════════════ */
// Total student-subject slots registered in the school
$total_registrations = (int)$conn->query("
  SELECT COUNT(*) AS c
  FROM student_subjects ss
  JOIN students s ON ss.student_id = s.student_id
  WHERE s.school_id = $school_id AND s.status = 'active'
")->fetch_assoc()['c'];

$marks_stats = $conn->query("
  SELECT 
    SUM(CASE WHEN m.status = 'draft' THEN 1 ELSE 0 END) AS draft_marks,
    SUM(CASE WHEN m.status = 'submitted' THEN 1 ELSE 0 END) AS submitted_marks,
    SUM(CASE WHEN m.status = 'approved' THEN 1 ELSE 0 END) AS approved_marks,
    COUNT(m.mark_id) AS total_entered
  FROM marks m
  JOIN students s ON m.student_id = s.student_id
  WHERE s.school_id = $school_id
")->fetch_assoc();

$draft_marks   = (int)($marks_stats['draft_marks'] ?? 0);
$submitted_marks = (int)($marks_stats['submitted_marks'] ?? 0);
$approved_marks  = (int)($marks_stats['approved_marks'] ?? 0);
$total_entered  = (int)($marks_stats['total_entered'] ?? 0);

$marks_submission_pct = $total_registrations > 0 ? round(($total_entered / $total_registrations) * 100, 1) : 0;
$marks_finalization_pct = $total_entered > 0 ? round(($approved_marks / $total_entered) * 100, 1) : 0;

/* ════════════════════════════════════════════════════════════════
  3. QUESTION BANK & ITEM WRITING STATS (from Teacher moderation)
  ════════════════════════════════════════════════════════════════ */
$question_stats = $conn->query("
  SELECT 
    COUNT(q.question_id) AS total_questions,
    SUM(CASE WHEN q.moderation_status = 'approved' THEN 1 ELSE 0 END) AS approved_questions,
    SUM(CASE WHEN q.moderation_status = 'pending' THEN 1 ELSE 0 END) AS pending_questions,
    SUM(CASE WHEN q.moderation_status = 'revise' THEN 1 ELSE 0 END) AS revise_questions,
    SUM(CASE WHEN q.moderation_status = 'rejected' THEN 1 ELSE 0 END) AS rejected_questions
  FROM questions q
  JOIN users u ON q.created_by = u.user_id
  WHERE u.school_id = $school_id
")->fetch_assoc();

$total_questions  = (int)($question_stats['total_questions'] ?? 0);
$approved_questions = (int)($question_stats['approved_questions'] ?? 0);
$pending_questions = (int)($question_stats['pending_questions'] ?? 0);
$revise_questions  = (int)($question_stats['revise_questions'] ?? 0);
$rejected_questions = (int)($question_stats['rejected_questions'] ?? 0);

$moderation_compliance_pct = $total_questions > 0 ? round(($approved_questions / $total_questions) * 100, 1) : 0;

/* ════════════════════════════════════════════════════════════════
  4. DETAILED TABLES (Top Candidates, Classes, Subjects)
  ════════════════════════════════════════════════════════════════ */
// Top students
$top_students_query = $conn->query("
  SELECT s.name, s.class, MAX(r.average_score) AS best_score
  FROM results r
  JOIN students s ON r.student_id = s.student_id
  WHERE s.school_id = $school_id AND r.status = 'published'
  GROUP BY s.student_id
  ORDER BY best_score DESC
  LIMIT 6
");
$top_students = [];
if ($top_students_query) {
  while ($row = $top_students_query->fetch_assoc()) {
    $top_students[] = $row;
  }
}

// Subject average scores
$subject_perf_query = $conn->query("
  SELECT 
    sub.subject_name,
    sub.subject_code,
    ROUND(AVG(m.score), 1) AS avg_score,
    COUNT(*) AS entries
  FROM marks m
  JOIN subjects sub ON m.subject_id = sub.subject_id
  JOIN students st ON m.student_id = st.student_id
  WHERE st.school_id = $school_id AND m.status = 'approved'
  GROUP BY sub.subject_id
  ORDER BY avg_score DESC
  LIMIT 6
");
$subjects = [];
if ($subject_perf_query) {
  while ($row = $subject_perf_query->fetch_assoc()) {
    $subjects[] = $row;
  }
}

// Class stream averages
$class_perf_query = $conn->query("
  SELECT s.class,
      ROUND(AVG(r.average_score), 1) AS avg_score,
      COUNT(DISTINCT s.student_id) AS students
  FROM students s
  LEFT JOIN results r ON r.student_id = s.student_id AND r.status = 'published'
  WHERE s.school_id = $school_id AND s.class IS NOT NULL
  GROUP BY s.class
  ORDER BY avg_score DESC
");
$classes = [];
if ($class_perf_query) {
  while ($row = $class_perf_query->fetch_assoc()) {
    $classes[] = $row;
  }
}

// Teacher engagement audit
$teacher_audit_query = $conn->prepare("
  SELECT 
    u.user_id,
    u.name AS teacher_name,
    u.employment_number,
    (SELECT COUNT(*) FROM questions WHERE created_by = u.user_id) AS questions_drafted,
    (SELECT COUNT(*) FROM questions WHERE created_by = u.user_id AND moderation_status = 'approved') AS questions_approved,
    (SELECT COUNT(*) FROM questions WHERE created_by = u.user_id AND moderation_status = 'revise') AS questions_revise,
    (SELECT COUNT(*) FROM marking_assignments WHERE teacher_id = u.user_id AND school_id = ?) AS assigned_marksheets,
    (SELECT COUNT(*) FROM marking_assignments WHERE teacher_id = u.user_id AND school_id = ? AND status = 'completed') AS completed_marksheets
  FROM users u
  WHERE u.school_id = ? AND u.role = 'teacher' AND u.status = 'active'
  ORDER BY u.name ASC
");
$teacher_audit_query->bind_param("iii", $school_id, $school_id, $school_id);
$teacher_audit_query->execute();
$teachers = $teacher_audit_query->get_result()->fetch_all(MYSQLI_ASSOC);
$teacher_audit_query->close();

// Historical sittings trend
$trend_query = $conn->query("
  SELECT DATE_FORMAT(r.compiled_at, '%b %Y') AS month_label,
      ROUND(AVG(r.average_score), 1) AS avg_score
  FROM results r
  JOIN students s ON r.student_id = s.student_id
  WHERE s.school_id = $school_id AND r.status = 'published'
  GROUP BY DATE_FORMAT(r.compiled_at, '%Y-%m')
  ORDER BY MIN(r.compiled_at) DESC
  LIMIT 6
");
$trend_labels = [];
$trend_data  = [];
if ($trend_query) {
  $trend_rows = [];
  while ($r = $trend_query->fetch_assoc()) {
    $trend_rows[] = $r;
  }
  $trend_rows = array_reverse($trend_rows);
  foreach ($trend_rows as $r) {
    $trend_labels[] = $r['month_label'];
    $trend_data[]  = (float)$r['avg_score'];
  }
}

$conn->close();

$perf_category = stats_performance_category($avg_score);
$portal_title = 'NED-SEMS | Performance Analytics';
$module_css  = 'headteacher';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Performance &amp; Operational Analytics | NED-SEMS</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
  <style>
    .split-card-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 24px;
      margin-bottom: 24px;
    }
    @media (max-width: 992px) {
      .split-card-grid {
        grid-template-columns: 1fr;
      }
    }
    .stat-bar-group {
      margin-bottom: 16px;
    }
    .stat-bar-group:last-child {
      margin-bottom: 0;
    }
    .stat-bar-label {
      display: flex;
      justify-content: space-between;
      font-size: 0.82rem;
      color: #475569;
      margin-bottom: 4px;
      font-weight: 600;
    }
    .stat-bar-outer {
      height: 10px;
      background: #f1f5f9;
      border-radius: 99px;
      overflow: hidden;
    }
    .stat-bar-inner {
      height: 100%;
      border-radius: 99px;
    }
  </style>
</head>
<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
  <?php include '../common/sidebar.php'; ?>

  <div class="content">

    <!-- Print Header -->
    <div class="print-header">
      <h2 class="print-title">Academic Standing &amp; Operational Status Report</h2>
      <div class="print-meta">Generated: <?= date('Y-m-d H:i:s') ?> | School: <?= htmlspecialchars($school_info['school_name'] ?? '') ?> | Role: Headteacher</div>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Performance Analytics Dashboard</h1>
        <p class="page-subtitle">Syllabus compliance, draft moderation status, marks pipeline metrics, and school standings for **<?= htmlspecialchars($school_info['school_name'] ?? '') ?>**</p>
      </div>
      <div class="header-actions">
        <button onclick="window.print()" class="btn btn-primary">️ Print Executive Report</button>
      </div>
    </div>

    <!-- ═══ PRIMARY KPI SUMMARY CARD GRID ═══ -->
    <div class="rpt-kpi-grid">
      <div class="rpt-kpi-card rpt-kpi--green">
        <span class="rpt-kpi-label">School Average</span>
        <span class="rpt-kpi-value"><?= $avg_score ?>%</span>
        <span class="rpt-kpi-sub">Standing: <strong><?= $perf_category ?></strong></span>
      </div>
      <div class="rpt-kpi-card rpt-kpi--blue">
        <span class="rpt-kpi-label">Results Compiled</span>
        <span class="rpt-kpi-value"><?= number_format($total_results) ?></span>
        <span class="rpt-kpi-sub">Across <?= $exams_participated ?> sittings</span>
      </div>
      <div class="rpt-kpi-card rpt-kpi--teal">
        <span class="rpt-kpi-label">Marks Intake Progress</span>
        <span class="rpt-kpi-value"><?= $marks_submission_pct ?>%</span>
        <span class="rpt-kpi-sub"><?= number_format($total_entered) ?> of <?= number_format($total_registrations) ?> slots entered</span>
      </div>
      <div class="rpt-kpi-card rpt-kpi--purple">
        <span class="rpt-kpi-label">Item Bank Compliance</span>
        <span class="rpt-kpi-value"><?= $moderation_compliance_pct ?>%</span>
        <span class="rpt-kpi-sub"><?= $approved_questions ?> of <?= $total_questions ?> questions approved</span>
      </div>
    </div>

    <!-- ═══ DETAILED PIPELINE AND SYSTEM STATISTICS ═══ -->
    <div class="split-card-grid">
      
      <!-- Card 1: Marks Intake Status Pipeline -->
      <div class="card" style="padding: 24px; margin: 0;">
        <h3 style="margin-top:0; margin-bottom: 18px; font-size: 0.95rem; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px;">
           Marks Intake &amp; Workflow Status
        </h3>
        <div style="display:flex; flex-direction:column; gap:16px; margin-top:20px;">
          
          <!-- Draft Marks -->
          <div class="stat-bar-group">
            <div class="stat-bar-label">
              <span>Draft (Unsubmitted Marks)</span>
              <strong><?= number_format($draft_marks) ?> slots</strong>
            </div>
            <div class="stat-bar-outer">
              <div class="stat-bar-inner" style="width: <?= $total_entered > 0 ? round(($draft_marks / $total_entered) * 100) : 0 ?>%; background:#64748b;"></div>
            </div>
          </div>

          <!-- Submitted Marks -->
          <div class="stat-bar-group">
            <div class="stat-bar-label">
              <span>Submitted (Awaiting Approval)</span>
              <strong><?= number_format($submitted_marks) ?> slots</strong>
            </div>
            <div class="stat-bar-outer">
              <div class="stat-bar-inner" style="width: <?= $total_entered > 0 ? round(($submitted_marks / $total_entered) * 100) : 0 ?>%; background:#eab308;"></div>
            </div>
          </div>

          <!-- Approved Marks -->
          <div class="stat-bar-group">
            <div class="stat-bar-label">
              <span>Approved (Finalized Marks)</span>
              <strong><?= number_format($approved_marks) ?> slots</strong>
            </div>
            <div class="stat-bar-outer">
              <div class="stat-bar-inner" style="width: <?= $total_entered > 0 ? round(($approved_marks / $total_entered) * 100) : 0 ?>%; background:#10b981;"></div>
            </div>
          </div>

          <!-- Finalization Rate -->
          <div style="border-top:1px solid #f1f5f9; padding-top:12px; margin-top:8px; display:flex; justify-content:space-between; align-items:center;">
            <span style="font-size:0.82rem; color:#64748b; font-weight:600;">Verification Finalization Rate:</span>
            <span class="rpt-badge rpt-badge--good" style="font-size:0.8rem; font-weight:bold;"><?= $marks_finalization_pct ?>%</span>
          </div>

        </div>
      </div>

      <!-- Card 2: Question Moderation Metrics -->
      <div class="card" style="padding: 24px; margin: 0;">
        <h3 style="margin-top:0; margin-bottom: 18px; font-size: 0.95rem; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px;">
           Exam Question Moderation Pipeline
        </h3>
        <div style="display:flex; flex-direction:column; gap:16px; margin-top:20px;">
          
          <!-- Approved -->
          <div class="stat-bar-group">
            <div class="stat-bar-label">
              <span>Approved Questions</span>
              <strong><?= $approved_questions ?> (<?= $moderation_compliance_pct ?>%)</strong>
            </div>
            <div class="stat-bar-outer">
              <div class="stat-bar-inner" style="width: <?= $moderation_compliance_pct ?>%; background:#10b981;"></div>
            </div>
          </div>

          <!-- Pending -->
          <div class="stat-bar-group">
            <div class="stat-bar-label">
              <span>Pending Moderation</span>
              <strong><?= $pending_questions ?></strong>
            </div>
            <div class="stat-bar-outer">
              <div class="stat-bar-inner" style="width: <?= $total_questions > 0 ? round(($pending_questions / $total_questions) * 100) : 0 ?>%; background:#3b82f6;"></div>
            </div>
          </div>

          <!-- Revise -->
          <div class="stat-bar-group">
            <div class="stat-bar-label">
              <span>Requires Revision (Revise Status)</span>
              <strong><?= $revise_questions ?></strong>
            </div>
            <div class="stat-bar-outer">
              <div class="stat-bar-inner" style="width: <?= $total_questions > 0 ? round(($revise_questions / $total_questions) * 100) : 0 ?>%; background:#f59e0b;"></div>
            </div>
          </div>

          <!-- Rejected -->
          <div class="stat-bar-group">
            <div class="stat-bar-label">
              <span>Rejected Items</span>
              <strong><?= $rejected_questions ?></strong>
            </div>
            <div class="stat-bar-outer">
              <div class="stat-bar-inner" style="width: <?= $total_questions > 0 ? round(($rejected_questions / $total_questions) * 100) : 0 ?>%; background:#ef4444;"></div>
            </div>
          </div>

        </div>
      </div>

    </div>

    <!-- ═══ GRID: PERFORMANCE TREND & TOP STUDENTS ═══ -->
    <div class="rpt-chart-grid">
      
      <!-- Trend Chart -->
      <div class="rpt-chart-box">
        <h3 class="rpt-chart-title">Academic Performance Trend</h3>
        <p class="rpt-chart-sub">School average score trends across historical sittings</p>
        <div class="rpt-chart-body">
          <?php if (!empty($trend_data)): ?>
            <?= chart_line($trend_labels, [['label' => 'School Avg', 'values' => $trend_data, 'color' => '#3b82f6']], ['height' => 180]) ?>
          <?php else: ?>
            <p style="color:var(--text-muted);text-align:center;padding:40px 0;">No trend data available.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Top Students -->
      <div class="rpt-chart-box">
        <h3 class="rpt-chart-title"> Top Performing Candidates</h3>
        <p class="rpt-chart-sub">Honour list of top students by average score</p>
        <div class="rpt-table-wrap">
          <table class="rpt-table">
            <thead>
              <tr>
                <th class="rank-col">Rank</th>
                <th>Student Name</th>
                <th>Class</th>
                <th class="val-col">Average</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($top_students)): ?>
                <tr><td colspan="4" class="empty-state">No student results found.</td></tr>
              <?php else: ?>
                <?php foreach ($top_students as $idx => $s): ?>
                  <tr>
                    <td class="rank-col"><?= $idx + 1 ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($s['name']) ?></td>
                    <td><?= htmlspecialchars($s['class'] ?? '—') ?></td>
                    <td class="val-col" style="color:#16a34a;"><?= number_format($s['best_score'], 1) ?>%</td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- ═══ GRID: SUBJECT PERFORMANCE & CLASS STANDINGS ═══ -->
    <div class="rpt-chart-grid">

      <!-- Subject Rankings -->
      <div class="rpt-chart-box">
        <h3 class="rpt-chart-title">Curriculum Standings</h3>
        <p class="rpt-chart-sub">Average score per subject syllabus</p>
        <div class="rpt-table-wrap">
          <table class="rpt-table">
            <thead>
              <tr>
                <th>Code</th>
                <th>Subject Name</th>
                <th class="val-col">Average</th>
                <th>Assessment Load</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($subjects)): ?>
                <tr><td colspan="4" class="empty-state">No subjects evaluated yet.</td></tr>
              <?php else: ?>
                <?php foreach ($subjects as $sub): ?>
                  <tr>
                    <td style="font-weight:700; color:var(--info-color);"><?= htmlspecialchars($sub['subject_code']) ?></td>
                    <td><?= htmlspecialchars($sub['subject_name']) ?></td>
                    <td class="val-col"><?= number_format($sub['avg_score'], 1) ?>%</td>
                    <td><span style="font-size:0.75rem; background:#f1f5f9; padding:2px 8px; border-radius:4px; font-weight:700;"><?= $sub['entries'] ?> entries</span></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Class Rankings -->
      <div class="rpt-chart-box">
        <h3 class="rpt-chart-title">Classroom Stream Standings</h3>
        <p class="rpt-chart-sub">Average score compared across forms and streams</p>
        <div class="rpt-table-wrap">
          <table class="rpt-table">
            <thead>
              <tr>
                <th class="rank-col">Rank</th>
                <th>Class Stream</th>
                <th class="val-col">Student Count</th>
                <th class="val-col">Class Average</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($classes)): ?>
                <tr><td colspan="4" class="empty-state">No class averages available.</td></tr>
              <?php else: ?>
                <?php foreach ($classes as $idx => $cls): ?>
                  <tr>
                    <td class="rank-col"><?= $idx + 1 ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($cls['class']) ?></td>
                    <td class="val-col"><?= $cls['students'] ?></td>
                    <td class="val-col" style="color:var(--info-color);"><?= $cls['avg_score'] ?? '—' ?>%</td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- ═══ TEACHER OPERATIONAL ENGAGEMENT TABLE ═══ -->
    <div class="card" style="margin-bottom: 24px;">
      <div class="section-header">
        <h3>Teacher Operational Audit</h3>
        <span style="font-size: 0.85rem; color: var(--text-muted);">Workload contributions: Item drafting, approved moderation, and marks entries.</span>
      </div>
      
      <div class="rpt-table-wrap">
        <table class="rpt-table">
          <thead>
            <tr>
              <th>Teacher Name</th>
              <th>Employment No.</th>
              <th style="text-align: center;">Questions Drafted</th>
              <th style="text-align: center;">Questions Approved</th>
              <th style="text-align: center;">Requires Revision</th>
              <th style="text-align: center;">Assigned Marksheets</th>
              <th style="text-align: center;">Completed Marksheets</th>
              <th>Grading Progress</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($teachers)): ?>
              <tr><td colspan="8" class="empty-state">No teachers registered in this school.</td></tr>
            <?php else: ?>
              <?php foreach ($teachers as $t):
                $assigned = (int)$t['assigned_marksheets'];
                $completed = (int)$t['completed_marksheets'];
                $progress_pct = $assigned > 0 ? round(($completed / $assigned) * 100) : 0;
              ?>
                <tr>
                  <td><strong><?= htmlspecialchars($t['teacher_name']) ?></strong></td>
                  <td><code><?= htmlspecialchars($t['employment_number'] ?: 'N/A') ?></code></td>
                  <td style="text-align: center; font-weight: bold;"><?= $t['questions_drafted'] ?></td>
                  <td style="text-align: center; font-weight: bold; color: #16a34a;"><?= $t['questions_approved'] ?></td>
                  <td style="text-align: center; font-weight: bold; color: #f59e0b;"><?= $t['questions_revise'] ?></td>
                  <td style="text-align: center; font-weight: bold;"><?= $assigned ?></td>
                  <td style="text-align: center; font-weight: bold; color: #16a34a;"><?= $completed ?></td>
                  <td>
                    <div style="display:flex; align-items:center; gap: 8px;">
                      <div style="background:#f1f5f9; border-radius:99px; height:8px; width:70px; overflow:hidden;">
                        <div style="height:100%; background:<?= $progress_pct === 100 ? '#10b981' : 'var(--info-color)' ?>; width:<?= $progress_pct ?>%;"></div>
                      </div>
                      <span style="font-size:0.75rem; font-weight:700;"><?= $progress_pct ?>%</span>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ STRATEGIC ACTION RECOMMENDATIONS ═══ -->
    <div class="rpt-rec-card">
      <h4>️ Headteacher Strategic Recommendations</h4>
      <ul class="rpt-rec-list">
        <?php if ($avg_score < 45): ?>
          <li style="color:#b91c1c; font-weight:600;">
             Remedial Intervention Program: With a school average below the 45% threshold, deploy school-wide student revision circles and increase teacher supervision parameters.
          </li>
        <?php endif; ?>

        <?php if ($revise_questions > 0): ?>
          <li>
            ️ Item Writing Revision: There are <?= $revise_questions ?> questions currently flagged as "Revise". Direct the corresponding item writers to finalize adjustments to avoid moderation delays.
          </li>
        <?php endif; ?>

        <?php if ($submitted_marks > 0): ?>
          <li>
             Marks Validation: There are <?= number_format($submitted_marks) ?> marks entries awaiting moderation. Direct the Examination Officer to verify and approve submissions.
          </li>
        <?php endif; ?>

        <?php if (!empty($classes) && count($classes) > 1):
          $best_class = $classes[0];
          $worst_class = end($classes);
          $gap = round((float)($best_class['avg_score'] ?? 0) - (float)($worst_class['avg_score'] ?? 0), 1);
          if ($gap > 8): ?>
            <li>
               Stream Balancing: A significant performance variance of <?= $gap ?>% exists between <?= htmlspecialchars($best_class['class']) ?> and <?= htmlspecialchars($worst_class['class']) ?>. Investigate streaming criteria, teacher allocation dynamics, and support resources for the lagging stream.
            </li>
          <?php endif;
        endif;
        ?>
        
        <li>
           Data-Driven Planning: Share these performance insights during the upcoming staff meeting to encourage collaborative curriculum alignments.
        </li>
      </ul>
    </div>

  </div>
</div>

<?php include '../common/footer.php'; ?>

</body>
</html>
