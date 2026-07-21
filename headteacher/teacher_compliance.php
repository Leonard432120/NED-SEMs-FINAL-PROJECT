<?php
/* ════════════════════════════════════════════════════════════════
  headteacher/teacher_compliance.php
  Headteacher: Teacher Submission & Compliance Report
  Auto-scoped to session school_id.
  Shows teachers' marking assignments, completion status, deadline progress,
  and flags late submissions to improve decision-making.
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

// Fetch school details
$stmt = $conn->prepare("SELECT school_name, district, school_type FROM schools WHERE school_id = ?");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$school_info = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

// Fetch exam list
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
  FETCH TEACHER WORKLOAD AND COMPLIANCE DETAILS
  ════════════════════════════════════════════════════════════════ */
$assignments = [];
if ($selected_exam_id > 0) {
  $stmt = $conn->prepare("
    SELECT 
      ma.assignment_id,
      ma.exam_id,
      ma.subject_id,
      ma.teacher_id,
      ma.deadline,
      ma.status,
      u.name AS teacher_name,
      u.employment_number,
      sub.subject_name,
      sub.subject_code,
      (SELECT MAX(m.submitted_at) 
       FROM marks m 
       JOIN students st ON m.student_id = st.student_id
       WHERE m.exam_id = ma.exam_id 
        AND m.subject_id = ma.subject_id 
        AND m.teacher_id = ma.teacher_id
        AND st.school_id = ma.school_id) AS max_submitted_at,
      (SELECT COUNT(*) 
       FROM marks m 
       JOIN students st ON m.student_id = st.student_id
       WHERE m.exam_id = ma.exam_id 
        AND m.subject_id = ma.subject_id 
        AND m.teacher_id = ma.teacher_id
        AND st.school_id = ma.school_id) AS marks_entered,
      (SELECT COUNT(*) 
       FROM student_subjects ss
       JOIN students st ON ss.student_id = st.student_id
       WHERE ss.subject_id = ma.subject_id 
        AND st.school_id = ma.school_id 
        AND st.status = 'active') AS total_students_to_mark,
      (SELECT AVG(m.score)
       FROM marks m
       JOIN students st ON m.student_id = st.student_id
       WHERE m.exam_id = ma.exam_id
        AND m.subject_id = ma.subject_id
        AND m.teacher_id = ma.teacher_id
        AND st.school_id = ma.school_id) AS avg_score_given
    FROM marking_assignments ma
    JOIN users u ON ma.teacher_id = u.user_id
    JOIN subjects sub ON ma.subject_id = sub.subject_id
    WHERE ma.school_id = ? AND ma.exam_id = ?
    ORDER BY u.name ASC
  ");
  $stmt->bind_param("ii", $school_id, $selected_exam_id);
  $stmt->execute();
  $assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

// Compute metrics
$total_assignments = count($assignments);
$compliant_count  = 0;
$late_count    = 0;
$overdue_count   = 0;
$pending_count   = 0;
$current_date   = date('Y-m-d');

$processed_assignments = [];
foreach ($assignments as $asg) {
  $deadline = $asg['deadline'];
  $status  = $asg['status'];
  $max_sub = $asg['max_submitted_at'];
  
  $asg_status = 'Pending';
  $is_late = false;
  $is_overdue = false;
  
  if (empty($deadline) || $deadline === '0000-00-00' || $deadline === '1970-01-01') {
    $deadline_label = 'None Set';
    if ($status === 'completed' || $status === 'submitted') {
      $asg_status = 'Submitted';
      $compliant_count++;
    } else {
      $asg_status = 'Pending';
      $pending_count++;
    }
  } else {
    $deadline_label = date('d M Y', strtotime($deadline));
    if ($status === 'completed' || $status === 'submitted') {
      if ($max_sub) {
        $sub_date = date('Y-m-d', strtotime($max_sub));
        if ($sub_date > $deadline) {
          $is_late = true;
          $asg_status = 'Submitted (Late)';
          $late_count++;
        } else {
          $asg_status = 'On Time';
          $compliant_count++;
        }
      } else {
        // Submitted but no marks records (edge case)
        $asg_status = 'On Time';
        $compliant_count++;
      }
    } else {
      if ($current_date > $deadline) {
        $is_overdue = true;
        $asg_status = 'Overdue';
        $overdue_count++;
      } else {
        $asg_status = 'Pending (On Track)';
        $pending_count++;
      }
    }
  }
  
  $asg['computed_status'] = $asg_status;
  $asg['is_late']     = $is_late;
  $asg['is_overdue']    = $is_overdue;
  $asg['deadline_label']  = $deadline_label;
  $processed_assignments[] = $asg;
}

$conn->close();

$portal_title = 'NED-SEMS | Teacher Compliance Report';
$module_css  = 'headteacher';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Teacher Submission & Compliance Report | NED-SEMS</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
</head>
<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
  <?php include '../common/sidebar.php'; ?>

  <div class="content">

    <!-- Print Header -->
    <div class="print-header">
      <h2 class="print-title">Teacher Grading Submission Compliance Report</h2>
      <div class="print-meta">Generated: <?= date('Y-m-d H:i:s') ?> | School: <?= htmlspecialchars($school_info['school_name'] ?? '') ?> | Role: Headteacher</div>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h2 class="page-title">Teacher Submission &amp; Compliance Report</h2>
        <p class="page-subtitle">
          School: <strong><?= htmlspecialchars($school_info['school_name'] ?? '') ?></strong> &nbsp;·&nbsp;
          District: <strong><?= htmlspecialchars($school_info['district'] ?? '') ?></strong> &nbsp;·&nbsp;
          Monitoring grading progress, deadlines, late submissions, and compliance stats.
        </p>
      </div>
      <div class="header-actions">
        <button onclick="window.print()" class="btn btn-primary">️ Print Report</button>
      </div>
    </div>

    <!-- Exam Filter Panel -->
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
          <button type="submit" class="btn btn-primary">Load Summary</button>
        </div>
      </form>
    </div>

    <?php if ($selected_exam_id > 0): ?>

    <!-- ═══ KPI SUMMARY CARD ═══ -->
    <div class="card" style="padding: 22px; margin-bottom: 28px;">
      <h3 style="margin-top:0; margin-bottom: 16px; font-size: 14px; font-weight: 700; color: #334155; text-transform: uppercase; letter-spacing: 0.5px;">
        Grading Duty Compliance KPI Summary
      </h3>
      <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 28px;">
        
        <!-- Total Duties -->
        <div style="border-right: 1px solid var(--border-color); padding-right: 14px;">
          <span style="font-size: 0.7rem; font-weight: 700; color:#64748b; text-transform:uppercase;">Total Tasks</span>
          <div style="font-size: 2.2rem; font-weight: 800; color:#0f172a; margin-top:4px;"><?= $total_assignments ?></div>
          <span style="font-size: 0.75rem; color:#64748b;">Active assignments</span>
        </div>

        <!-- On Time -->
        <div style="border-right: 1px solid var(--border-color); padding-right: 14px;">
          <span style="font-size: 0.7rem; font-weight: 700; color:#15803d; text-transform:uppercase;">On Time / Compliant</span>
          <div style="font-size: 2.2rem; font-weight: 800; color:#16a34a; margin-top:4px;"><?= $compliant_count ?></div>
          <span style="font-size: 0.75rem; color:#16a34a; font-weight: 600;">
            <?= $total_assignments > 0 ? round(($compliant_count / $total_assignments) * 100) : 0 ?>% compliance
          </span>
        </div>

        <!-- Late Submissions -->
        <div style="border-right: 1px solid var(--border-color); padding-right: 14px;">
          <span style="font-size: 0.7rem; font-weight: 700; color:#b45309; text-transform:uppercase;">Submitted Late</span>
          <div style="font-size: 2.2rem; font-weight: 800; color:#d97706; margin-top:4px;"><?= $late_count ?></div>
          <span style="font-size: 0.75rem; color:#d97706;">Submitted past deadline</span>
        </div>

        <!-- Overdue Tasks -->
        <div>
          <span style="font-size: 0.7rem; font-weight: 700; color:#b91c1c; text-transform:uppercase;">Overdue / Pending</span>
          <div style="font-size: 2.2rem; font-weight: 800; color:#dc2626; margin-top:4px;"><?= $overdue_count ?> <span style="font-size:1.2rem; font-weight:normal; color:#64748b;">/ <?= $pending_count ?></span></div>
          <span style="font-size: 0.75rem; color:<?= $overdue_count > 0 ? '#dc2626' : '#64748b' ?>; font-weight:<?= $overdue_count > 0 ? '600' : 'normal' ?>;">
            <?= $overdue_count ?> task(s) missed deadline
          </span>
        </div>

      </div>
    </div>

    <!-- ═══ DETAILED COMPLIANCE TABLE ═══ -->
    <div class="card">
      <div class="section-header">
        <h3>Teacher Workload &amp; Submission Standings</h3>
        <span style="font-size: 0.85rem; color: var(--text-muted);">Real-time monitoring of all school marking duties</span>
      </div>

      <div class="rpt-table-wrap">
        <table class="rpt-table">
          <thead>
            <tr>
              <th>Teacher Name</th>
              <th>Employment No.</th>
              <th>Subject Paper</th>
              <th style="text-align: center;">Progress (Marks entered)</th>
              <th>Deadline</th>
              <th>Submission Date</th>
              <th>Status</th>
              <th style="text-align: center;">Average Score</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($processed_assignments)): ?>
              <tr>
                <td colspan="8" class="empty-state">No marking assignments registered for this exam at your school.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($processed_assignments as $asg):
                $entered = (int)$asg['marks_entered'];
                $total  = (int)$asg['total_students_to_mark'];
                $progress = $total > 0 ? round(($entered / $total) * 100) : 0;
                
                // Color badges for status
                if ($asg['computed_status'] === 'On Time') {
                  $badge = '<span class="rpt-badge rpt-badge--excellent">Compliant</span>';
                } elseif ($asg['computed_status'] === 'Submitted (Late)') {
                  $badge = '<span class="rpt-badge rpt-badge--average">Submitted Late</span>';
                } elseif ($asg['computed_status'] === 'Overdue') {
                  $badge = '<span class="rpt-badge rpt-badge--poor">Overdue</span>';
                } elseif ($asg['computed_status'] === 'Pending (On Track)') {
                  $badge = '<span class="rpt-badge rpt-badge--good">Pending</span>';
                } else {
                  $badge = '<span class="rpt-badge rpt-badge--nodata">' . htmlspecialchars($asg['computed_status']) . '</span>';
                }
                
                // Format average score
                $avg_score = $asg['avg_score_given'];
                if ($avg_score !== null) {
                  $avg_score_val = round((float)$avg_score, 1) . '%';
                  $avg_color = $avg_score >= 60 ? '#16a34a' : ($avg_score >= 40 ? '#3b82f6' : '#ef4444');
                } else {
                  $avg_score_val = '—';
                  $avg_color = '#64748b';
                }
              ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($asg['teacher_name']) ?></strong>
                  </td>
                  <td><code><?= htmlspecialchars($asg['employment_number'] ?: 'N/A') ?></code></td>
                  <td>
                    <strong style="color: var(--primary-dark);"><?= htmlspecialchars($asg['subject_code']) ?></strong> - <?= htmlspecialchars($asg['subject_name']) ?>
                  </td>
                  <td>
                    <div style="display:flex; align-items:center; gap: 8px; justify-content: center;">
                      <div style="background:#f1f5f9; border-radius:99px; height:8px; width:100px; overflow:hidden;">
                        <div style="height:100%; background:<?= $progress === 100 ? '#16a34a' : 'var(--info-color)' ?>; width:<?= $progress ?>%;"></div>
                      </div>
                      <span style="font-size:0.8rem; font-weight:700; min-width: 65px; text-align: right;"><?= $entered ?> / <?= $total ?> (<?= $progress ?>%)</span>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($asg['deadline_label']) ?></td>
                  <td>
                    <?= $asg['max_submitted_at'] ? date('d M Y H:i', strtotime($asg['max_submitted_at'])) : '<span style="color:#94a3b8;">Not Submitted</span>' ?>
                  </td>
                  <td><?= $badge ?></td>
                  <td class="val-col" style="color: <?= $avg_color ?>;"><?= $avg_score_val ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ ACTIONABLE DECISION-MAKING & RECOMMENDATIONS ═══ -->
    <div class="rpt-rec-card" style="margin-top: 24px;">
      <h4>️ Actionable Compliance Interventions</h4>
      <ul class="rpt-rec-list">
        <?php if ($overdue_count > 0): ?>
          <li style="color:#b91c1c; font-weight:600;">
             Urgent Action Required: There are <?= $overdue_count ?> overdue grading duties. Please follow up with the assigned educators immediately to ensure results compilation is not delayed.
          </li>
        <?php endif; ?>
        
        <?php if ($late_count > 0): ?>
          <li>
            ️ Latency Review: <?= $late_count ?> marking duties were submitted late. Analyze whether high class enrollment sizes or lack of digital resources contributed to these delays.
          </li>
        <?php endif; ?>

        <li>
           Locked Workloads: If a teacher needs to update marks but the system has locked them out post-deadline, navigate to <strong>Marks Management</strong> in the sidebar and enable lock override.
        </li>
        <li>
           Deadline Management: Set realistic deadlines based on candidate script volumes (e.g. MSCE subjects with higher enrollment require larger marking cycles).
        </li>
      </ul>
    </div>

    <?php else: ?>
      <div class="card" style="padding: 40px; text-align: center;">
        <p style="color: var(--text-muted);">Select an examination above to load the teacher compliance summary.</p>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php include '../common/footer.php'; ?>

</body>
</html>
