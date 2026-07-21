<?php
/**
 * admin/reports/div_findings_history.php
 * ─────────────────────────────────────────────────────────────────
 * Searchable findings history & EDM Support Feedback Manager.
 * Allows EDM / Admin to:
 * 1. Filter by Scope (School Headteacher Findings vs Division Findings).
 * 2. Select District (e.g. Chitipa, Karonga, Likoma, Mzimba, Nkhata Bay, Rumphi).
 * 3. Select School & Exam.
 * 4. Review school findings and submit EDM Feedback & Support Interventions.
 * ─────────────────────────────────────────────────────────────────
 */
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../common/pagination_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

$conn     = get_db_connection();
$admin_id = (int)$_SESSION['user_id'];
$message  = '';
$msg_type = '';

// ── Handle EDM Feedback POST Submission ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_edm_feedback'])) {
    $finding_id   = (int)($_POST['finding_id'] ?? 0);
    $edm_action   = trim($_POST['edm_action'] ?? '');
    $edm_feedback = trim($_POST['edm_feedback'] ?? '');

    if ($finding_id > 0 && !empty($edm_feedback)) {
        $stmt = $conn->prepare("
            UPDATE ht_findings 
            SET edm_feedback = ?, edm_action = ?, edm_responded_by = ?, edm_responded_at = NOW() 
            WHERE finding_id = ?
        ");
        $stmt->bind_param("ssii", $edm_feedback, $edm_action, $admin_id, $finding_id);
        if ($stmt->execute()) {
            $stmt->close();
            // Preserve existing GET parameters
            $params = $_GET;
            $params['feedback_saved'] = 1;
            header("Location: div_findings_history.php?" . http_build_query($params));
            exit();
        } else {
            $message  = "Failed to save feedback: " . $conn->error;
            $msg_type = "error";
            $stmt->close();
        }
    } else {
        $message  = "Please enter official EDM feedback before submitting.";
        $msg_type = "error";
    }
}

// ── Filter inputs (sanitised) ────────────────────────────────────
$filter_scope    = in_array($_GET['scope'] ?? '', ['school', 'division'], true) 
                    ? $_GET['scope'] 
                    : (!empty($_GET['school_id']) || !empty($_GET['district']) ? 'school' : 'school');

$filter_district = trim($_GET['district'] ?? '');
$filter_school_id= isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;
$filter_exam_id  = isset($_GET['exam_id'])   ? (int)$_GET['exam_id']   : 0;

$valid_trends    = ['', 'declining', 'improving', 'flat'];
$valid_outcomes  = ['', 'improved', 'no_change', 'worsened'];

$filter_trend    = in_array($_GET['trend']   ?? '', $valid_trends,   true) ? ($_GET['trend']   ?? '') : '';
$filter_outcome  = in_array($_GET['outcome'] ?? '', $valid_outcomes, true) ? ($_GET['outcome'] ?? '') : '';
$filter_cause    = trim($_GET['cause'] ?? '');
$filter_year     = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$show_responded  = isset($_GET['show_responded']) && $_GET['show_responded'] == '1';
$current_page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page        = 5;

// ── Dropdown Data ────────────────────────────────────────────────
$districts_list = $conn->query("SELECT DISTINCT district FROM schools WHERE status='active' ORDER BY district ASC")->fetch_all(MYSQLI_ASSOC);

$schools_query_sql = "SELECT school_id, school_name, district FROM schools WHERE status='active' ";
if ($filter_district !== '') {
    $esc_dist = $conn->real_escape_string($filter_district);
    $schools_query_sql .= "AND district = '$esc_dist' ";
}
$schools_query_sql .= "ORDER BY school_name ASC";
$schools_list = $conn->query($schools_query_sql)->fetch_all(MYSQLI_ASSOC);

$exams_list = $conn->query("SELECT exam_id, exam_name, class, year FROM exams ORDER BY year DESC, start_date DESC")->fetch_all(MYSQLI_ASSOC);

// ── Build Query based on Scope ───────────────────────────────────
if ($filter_scope === 'school') {

    // SCHOOL HEADTEACHER FINDINGS QUERY
    $where_parts = ['1=1'];
    $bind_types  = '';
    $bind_values = [];

    if ($filter_district !== '') {
        $where_parts[] = 'sc.district = ?';
        $bind_types   .= 's';
        $bind_values[] = $filter_district;
    }
    if ($filter_school_id > 0) {
        $where_parts[] = 'f.school_id = ?';
        $bind_types   .= 'i';
        $bind_values[] = $filter_school_id;
    }
    if ($filter_exam_id > 0) {
        $where_parts[] = 'f.exam_id = ?';
        $bind_types   .= 'i';
        $bind_values[] = $filter_exam_id;
    }
    if ($filter_trend !== '') {
        $where_parts[] = 'f.trend = ?';
        $bind_types   .= 's';
        $bind_values[] = $filter_trend;
    }
    if ($filter_cause !== '') {
        $where_parts[] = 'f.cause_category = ?';
        $bind_types   .= 's';
        $bind_values[] = $filter_cause;
    }
    if ($filter_outcome !== '') {
        $where_parts[] = 'o.outcome = ?';
        $bind_types   .= 's';
        $bind_values[] = $filter_outcome;
    }
    if ($filter_year > 0) {
        $where_parts[] = 'e.year = ?';
        $bind_types   .= 'i';
        $bind_values[] = $filter_year;
    }

    // By default, hide findings that already have EDM feedback (to give space for unresponded schools)
    if (!$show_responded) {
        $where_parts[] = 'f.edm_feedback IS NULL';
    }

    $where_sql = implode(' AND ', $where_parts);

    // Count total for pagination
    $count_sql = "SELECT COUNT(*) AS total FROM ht_findings f
        JOIN schools sc ON sc.school_id = f.school_id
        JOIN exams e ON e.exam_id = f.exam_id
        LEFT JOIN ht_finding_outcomes o ON o.finding_id = f.finding_id
        WHERE {$where_sql}";

    $sql = "
        SELECT
            f.finding_id,
            f.school_id,
            f.exam_id,
            f.trend,
            f.pass_rate_pct,
            f.cause_category,
            f.cause_detail,
            f.action_category,
            f.action_detail,
            f.lifecycle_status,
            f.edm_feedback,
            f.edm_action,
            f.edm_responded_at,
            f.created_at,
            sc.school_name,
            sc.district,
            e.exam_name,
            e.year AS exam_year,
            o.outcome,
            o.outcome_detail,
            o.created_at AS outcome_at,
            ht_u.name AS ht_name,
            adm_u.name AS edm_name
        FROM ht_findings f
        JOIN schools sc ON sc.school_id = f.school_id
        JOIN exams e ON e.exam_id = f.exam_id
        LEFT JOIN ht_finding_outcomes o ON o.finding_id = f.finding_id
        LEFT JOIN users ht_u ON ht_u.user_id = f.recorded_by
        LEFT JOIN users adm_u ON adm_u.user_id = f.edm_responded_by
        WHERE {$where_sql}
        ORDER BY f.created_at DESC
    ";

} else {

    // DIVISION-LEVEL FINDINGS QUERY
    $where_parts = ['1=1'];
    $bind_types  = '';
    $bind_values = [];

    if ($filter_exam_id > 0) {
        $where_parts[] = 'f.exam_id = ?';
        $bind_types   .= 'i';
        $bind_values[] = $filter_exam_id;
    }
    if ($filter_trend !== '') {
        $where_parts[] = 'f.trend = ?';
        $bind_types   .= 's';
        $bind_values[] = $filter_trend;
    }
    if ($filter_cause !== '') {
        $where_parts[] = 'f.cause_category = ?';
        $bind_types   .= 's';
        $bind_values[] = $filter_cause;
    }
    if ($filter_outcome !== '') {
        $where_parts[] = 'o.outcome = ?';
        $bind_types   .= 's';
        $bind_values[] = $filter_outcome;
    }
    if ($filter_year > 0) {
        $where_parts[] = 'e.year = ?';
        $bind_types   .= 'i';
        $bind_values[] = $filter_year;
    }

    $where_sql = implode(' AND ', $where_parts);

    $count_sql = "SELECT COUNT(*) AS total FROM div_findings f
        JOIN exams e ON e.exam_id = f.exam_id
        LEFT JOIN div_finding_outcomes o ON o.finding_id = f.finding_id
        WHERE {$where_sql}";

    $sql = "
        SELECT
            f.finding_id,
            f.exam_id,
            f.trend,
            f.avg_score_pct,
            f.cause_category,
            f.cause_detail,
            f.action_category,
            f.action_detail,
            f.lifecycle_status,
            f.created_at,
            e.exam_name,
            e.year AS exam_year,
            o.outcome,
            o.outcome_detail,
            o.created_at AS outcome_at,
            u.name AS recorded_by_name
        FROM div_findings f
        JOIN exams e ON e.exam_id = f.exam_id
        LEFT JOIN div_finding_outcomes o ON o.finding_id = f.finding_id
        LEFT JOIN users u ON u.user_id = f.recorded_by
        WHERE {$where_sql}
        ORDER BY f.created_at DESC
    ";
}

// ── Count total results for pagination ────────────────────────────
$total_findings = 0;
if (!empty($bind_types)) {
    $cnt_stmt = $conn->prepare($count_sql);
    $cnt_stmt->bind_param($bind_types, ...$bind_values);
    $cnt_stmt->execute();
    $total_findings = (int)($cnt_stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $cnt_stmt->close();
} else {
    $total_findings = (int)($conn->query($count_sql)->fetch_assoc()['total'] ?? 0);
}

$pagination = paginate($total_findings, $current_page, $per_page);
$offset = ($pagination['page'] - 1) * $per_page;
$sql .= " LIMIT {$per_page} OFFSET {$offset}";

// ── Also count total responded (for info badge) ──────────────────
$total_responded = 0;
if ($filter_scope === 'school') {
    $resp_sql = "SELECT COUNT(*) AS c FROM ht_findings f
        JOIN schools sc ON sc.school_id = f.school_id
        JOIN exams e ON e.exam_id = f.exam_id
        LEFT JOIN ht_finding_outcomes o ON o.finding_id = f.finding_id
        WHERE " . str_replace('f.edm_feedback IS NULL', '1=1', $where_sql) . " AND f.edm_feedback IS NOT NULL";
    if (!empty($bind_types)) {
        $resp_stmt = $conn->prepare($resp_sql);
        $resp_stmt->bind_param($bind_types, ...$bind_values);
        $resp_stmt->execute();
        $total_responded = (int)($resp_stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $resp_stmt->close();
    } else {
        $total_responded = (int)($conn->query($resp_sql)->fetch_assoc()['c'] ?? 0);
    }
}

if (!empty($bind_types)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($bind_types, ...$bind_values);
    $stmt->execute();
    $findings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $findings = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

// ── Year list for filter dropdown ────────────────────────────────
$year_list = $conn->query(
    "SELECT DISTINCT e.year FROM exams e ORDER BY e.year DESC"
)->fetch_all(MYSQLI_ASSOC);
$year_list = array_column($year_list, 'year');

$conn->close();

// ── Label helpers ─────────────────────────────────────────────────
function dh_cause_label(string $slug): string {
    return [
        'teacher_shortage'             => 'Teacher Shortage',
        'resource_shortage'            => 'Resource Shortage',
        'funding_delays'               => 'Funding Delays',
        'learning_material_deficiency' => 'Deficiency in Learning Materials',
        'curriculum_gap'               => 'Curriculum Gap',
        'curriculum_misalignment'      => 'Curriculum Misalignment',
        'teacher_compliance_low'       => 'Low Teacher Submission',
        'teacher_absenteeism'          => 'Teacher Absenteeism',
        'student_discipline'           => 'Student Discipline',
        'assessment_irregularity'      => 'Assessment Irregularity',
        'illness_outbreak'             => 'Illness / Outbreak',
        'staff_turnover'               => 'Staff Turnover',
        'low_attendance'               => 'Low Student Attendance',
        'external_disruption'          => 'External Disruption',
        'extreme_weather'              => 'Extreme Weather',
        'administrative_laxity'        => 'Administrative Laxity',
        'positive_intervention'        => 'Positive School Intervention',
        'positive_divisional_reform'   => 'Positive Divisional Reforms',
        'other'                        => 'Other Factors',
    ][$slug] ?? ucfirst(str_replace('_', ' ', $slug));
}

function dh_action_label(string $slug): string {
    return [
        'remedial_classes'          => 'Remedial Classes',
        'staff_redeployment'        => 'Staff Redeployment',
        'teacher_recruitment'       => 'Hiring / Deploying Staff',
        'resource_procurement'      => 'Resource Procurement',
        'textbook_distribution'     => 'Procuring Textbooks/Materials',
        'budget_allocation'         => 'Emergency Funding Allocation',
        'parent_engagement'         => 'Parent Engagement',
        'curriculum_revision'       => 'Curriculum Revision',
        'attendance_campaign'       => 'Attendance Campaign',
        'inspection_blitz'          => 'School Inspection Campaign',
        'teacher_capacity_building' => 'Teacher CPD Seminars',
        'pastoral_support'          => 'Pastoral Support',
        'peer_mentoring'            => 'Peer Mentoring',
        'celebration_recognition'   => 'Celebration & Recognition',
        'no_action_yet'             => 'No Action Yet',
        'other'                     => 'Other Intervention',
    ][$slug] ?? ucfirst(str_replace('_', ' ', $slug));
}

function dh_outcome_label(string $slug): string {
    return ['improved' => 'Improved', 'no_change' => 'No Change', 'worsened' => 'Worsened'][$slug] ?? $slug;
}

function dh_outcome_badge(string $outcome): string {
    return ['improved' => 'htf-badge--improved', 'no_change' => 'htf-badge--nochange', 'worsened' => 'htf-badge--worsened'][$outcome] ?? '';
}

function dh_trend_badge(string $trend): string {
    return ['declining' => 'htf-badge--declining', 'improving' => 'htf-badge--improving', 'flat' => 'htf-badge--flat'][$trend] ?? '';
}

$portal_title = 'NED-SEMS | Division & School Findings Log';
$module_css   = 'admin';
include __DIR__ . '/../../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Findings & EDM Support Manager | NED-SEMS Admin</title>
  <meta name="description" content="Searchable history of school and division findings with EDM Office support feedback and intervention tools">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
  <style>
    .edm-response-box {
      background: #f0f9ff;
      border: 1px solid #bae6fd;
      border-left: 4px solid #0284c7;
      border-radius: 6px;
      padding: 14px 16px;
      margin-top: 14px;
    }
    .edm-response-title {
      font-size: 0.85rem;
      font-weight: 700;
      color: #0369a1;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 6px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .edm-response-body {
      font-size: 0.9rem;
      color: #0c4a6e;
      line-height: 1.5;
    }
    .edm-form-box {
      background: #f8fafc;
      border: 1px dashed #cbd5e1;
      border-radius: 6px;
      padding: 16px;
      margin-top: 14px;
    }
    .edm-form-title {
      font-size: 0.88rem;
      font-weight: 700;
      color: #334155;
      margin-bottom: 10px;
    }
    .scope-tab-bar {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
      border-bottom: 2px solid var(--border-color);
      padding-bottom: 8px;
    }
    .scope-tab {
      padding: 8px 18px;
      font-size: 0.9rem;
      font-weight: 600;
      border-radius: 6px;
      text-decoration: none;
      color: #64748b;
      background: #f1f5f9;
      transition: all 0.2s ease;
    }
    .scope-tab.active {
      background: var(--info-color, #2563eb);
      color: #ffffff;
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../../common/header.php'; ?>

<div class="dashboard">
  <?php include __DIR__ . '/../../common/sidebar.php'; ?>

  <div class="content">

    <div class="page-header">
      <div>
        <h2 class="page-title">Findings Log &amp; EDM Support Center</h2>
        <p class="page-subtitle">Inspect Headteacher findings by District &amp; School, provide official EDM Office feedback, and pledge support interventions</p>
      </div>
      <div class="header-actions">
        <a href="<?= BASE_URL ?>/admin/reports/division_report.php" class="btn btn-secondary">← Back to Division Report</a>
      </div>
    </div>

    <?php if (isset($_GET['feedback_saved'])): ?>
      <div class="htf-alert htf-alert--success">EDM Feedback and support intervention saved successfully for the school!</div>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
      <div class="htf-alert htf-alert--<?= $msg_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Scope Tabs -->
    <div class="scope-tab-bar">
      <?php
        $school_tab_url   = "div_findings_history.php?" . http_build_query(array_merge($_GET, ['scope' => 'school']));
        $division_tab_url = "div_findings_history.php?" . http_build_query(array_merge($_GET, ['scope' => 'division']));
      ?>
      <a href="<?= htmlspecialchars($school_tab_url) ?>" class="scope-tab <?= $filter_scope === 'school' ? 'active' : '' ?>">
         School Headteacher Findings &amp; EDM Response
      </a>
      <a href="<?= htmlspecialchars($division_tab_url) ?>" class="scope-tab <?= $filter_scope === 'division' ? 'active' : '' ?>">
         Division Strategic Findings
      </a>
    </div>

    <!-- ═══ FILTER PANEL ═══ -->
    <div class="rpt-filter-panel no-print">
      <form method="GET" class="rpt-filter-form" id="div-findings-filter-form">
        <input type="hidden" name="scope" value="<?= htmlspecialchars($filter_scope) ?>">

        <?php if ($filter_scope === 'school'): ?>
          <!-- District Filter -->
          <div class="rpt-filter-group">
            <label class="rpt-filter-label" for="df-district">District</label>
            <select name="district" id="df-district" class="rpt-filter-select" onchange="this.form.submit()">
              <option value="">All Districts</option>
              <?php foreach ($districts_list as $d): ?>
                <option value="<?= htmlspecialchars($d['district']) ?>" <?= $filter_district === $d['district'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($d['district']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- School Filter -->
          <div class="rpt-filter-group">
            <label class="rpt-filter-label" for="df-school">School Center</label>
            <select name="school_id" id="df-school" class="rpt-filter-select">
              <option value="0">All Schools</option>
              <?php foreach ($schools_list as $sc): ?>
                <option value="<?= $sc['school_id'] ?>" <?= $filter_school_id === (int)$sc['school_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($sc['school_name']) ?> (<?= htmlspecialchars($sc['district']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <!-- Exam Filter -->
        <div class="rpt-filter-group">
          <label class="rpt-filter-label" for="df-exam">Examination</label>
          <select name="exam_id" id="df-exam" class="rpt-filter-select">
            <option value="0">All Examinations</option>
            <?php foreach ($exams_list as $ex): ?>
              <option value="<?= $ex['exam_id'] ?>" <?= $filter_exam_id === (int)$ex['exam_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($ex['exam_name']) ?> (<?= htmlspecialchars($ex['year']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Trend Filter -->
        <div class="rpt-filter-group">
          <label class="rpt-filter-label" for="df-trend">Trend Direction</label>
          <select name="trend" id="df-trend" class="rpt-filter-select">
            <option value="">All Trends</option>
            <option value="declining" <?= $filter_trend === 'declining' ? 'selected' : '' ?>>Declining</option>
            <option value="improving" <?= $filter_trend === 'improving' ? 'selected' : '' ?>>Improving</option>
            <option value="flat"      <?= $filter_trend === 'flat'      ? 'selected' : '' ?>>Flat</option>
          </select>
        </div>

        <!-- Exam Year Filter -->
        <div class="rpt-filter-group">
          <label class="rpt-filter-label" for="df-year">Exam Year</label>
          <select name="year" id="df-year" class="rpt-filter-select">
            <option value="0">All Years</option>
            <?php foreach ($year_list as $y): ?>
              <option value="<?= (int)$y ?>" <?= $filter_year === (int)$y ? 'selected' : '' ?>><?= (int)$y ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if ($filter_scope === 'school'): ?>
          <div class="rpt-filter-group">
            <label class="rpt-filter-label">&nbsp;</label>
            <label style="display: flex; align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 600; color: #475569; cursor: pointer; padding-top: 4px;">
              <input type="checkbox" name="show_responded" value="1" <?= $show_responded ? 'checked' : '' ?> onchange="this.form.submit()" style="width: 16px; height: 16px; accent-color: #2563eb;">
              Show Responded (<?= $total_responded ?>)
            </label>
          </div>
        <?php endif; ?>

        <div class="rpt-filter-actions">
          <button type="submit" class="btn btn-primary">Filter</button>
          <a href="<?= BASE_URL ?>/admin/reports/div_findings_history.php" class="btn btn-secondary">Reset</a>
        </div>
      </form>
    </div>

    <!-- ═══ FINDINGS LIST ═══ -->
    <?php if (empty($findings)): ?>
      <div class="card" style="padding: 48px; text-align: center;">
        <p style="color: var(--text-muted); font-size: 1rem; margin: 0;">
          <?php if ($filter_scope === 'school' && !$show_responded && $total_responded > 0): ?>
            All school findings have been responded to! There are <strong><?= $total_responded ?></strong> responded finding<?= $total_responded !== 1 ? 's' : '' ?>.
          <?php else: ?>
            No findings recorded yet matching the selected filters.
          <?php endif; ?>
        </p>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 8px;">
          <?php if ($filter_scope === 'school' && !$show_responded && $total_responded > 0): ?>
            <a href="<?= htmlspecialchars('div_findings_history.php?' . http_build_query(array_merge($_GET, ['show_responded' => '1']))) ?>" style="color: var(--info-color); font-weight: 600;">Show Responded Findings →</a>
          <?php else: ?>
            Try selecting a different District, School, or Exam from the filters above.
          <?php endif; ?>
        </p>
      </div>
    <?php else: ?>

      <div style="margin-bottom: 14px; font-size: 0.88rem; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center;">
        <span>
          Showing <strong><?= count($findings) ?></strong> of <strong><?= $total_findings ?></strong>
          <?= $filter_scope === 'school' ? ($show_responded ? '' : 'awaiting EDM response') : 'division' ?>
          finding<?= $total_findings !== 1 ? 's' : '' ?>
          <?php if ($filter_scope === 'school' && !$show_responded && $total_responded > 0): ?>
            &nbsp;·&nbsp; <span style="color: #16a34a; font-weight: 600;"><?= $total_responded ?> already responded</span>
          <?php endif; ?>
        </span>
        <span style="font-size: 0.82rem;">Page <?= $pagination['page'] ?> of <?= $pagination['total_pages'] ?></span>
      </div>

      <div class="htf-findings-list">
        <?php foreach ($findings as $f): ?>
          <?php
            $is_open     = $f['lifecycle_status'] === 'open';
            $has_outcome = !$is_open && !empty($f['outcome']);
            $has_edm_fb  = !empty($f['edm_feedback']);
          ?>
          <div class="htf-finding-card <?= $is_open ? 'htf-finding-card--open' : 'htf-finding-card--closed' ?>">

            <!-- Card Header -->
            <div class="htf-finding-header">
              <div class="htf-finding-meta">
                <span class="htf-badge <?= dh_trend_badge($f['trend']) ?>">
                  <?= ucfirst(htmlspecialchars($f['trend'])) ?>
                </span>
                <?php if ($filter_scope === 'school'): ?>
                  <strong style="font-size: 1rem; color: #0f172a;"><?= htmlspecialchars($f['school_name']) ?></strong>
                  <span style="font-size: 0.8rem; background: #e2e8f0; padding: 2px 8px; border-radius: 4px; font-weight: 700; color: #334155;">
                    <?= htmlspecialchars($f['district']) ?>
                  </span>
                <?php endif; ?>
                <span style="font-size: 0.9rem; color: #475569; font-weight: 600;">
                  <?= htmlspecialchars($f['exam_name']) ?> (<?= (int)$f['exam_year'] ?>)
                </span>
                <span class="htf-pass-rate">
                  <?= $filter_scope === 'school' ? 'School Pass Rate:' : 'Division Avg:' ?> 
                  <strong><?= number_format((float)($f['pass_rate_pct'] ?? $f['avg_score_pct'] ?? 0), 1) ?>%</strong>
                </span>
              </div>

              <div style="display: flex; align-items: center; gap: 10px;">
                <?php if ($filter_scope === 'school' && $has_edm_fb): ?>
                  <span style="font-size: 0.78rem; background: #dbeafe; color: #1e40af; padding: 3px 10px; border-radius: 12px; font-weight: 700;">
                     EDM Feedback Responded
                  </span>
                <?php endif; ?>

                <?php if ($is_open): ?>
                  <span class="htf-badge htf-badge--open">Open</span>
                <?php elseif ($has_outcome): ?>
                  <span class="htf-badge <?= dh_outcome_badge($f['outcome']) ?>">
                    <?= htmlspecialchars(dh_outcome_label($f['outcome'])) ?>
                  </span>
                <?php endif; ?>
                <span style="font-size: 0.78rem; color: var(--text-muted);"><?= date('d M Y', strtotime($f['created_at'])) ?></span>
              </div>
            </div>

            <!-- Card Body -->
            <div class="htf-finding-body">
              <div class="htf-finding-col">
                <div class="htf-finding-field-label"><?= $filter_scope === 'school' ? 'Root Cause (Headteacher)' : 'Strategic Factor' ?></div>
                <div class="htf-finding-field-value">
                  <span class="htf-cause-tag"><?= htmlspecialchars(dh_cause_label($f['cause_category'])) ?></span>
                  <?php if (!empty($f['cause_detail'])): ?>
                    <p class="htf-detail-text"><?= htmlspecialchars($f['cause_detail']) ?></p>
                  <?php endif; ?>
                </div>
              </div>
              <div class="htf-finding-col">
                <div class="htf-finding-field-label"><?= $filter_scope === 'school' ? 'Action Taken (Headteacher)' : 'Division Intervention' ?></div>
                <div class="htf-finding-field-value">
                  <span class="htf-action-tag"><?= htmlspecialchars(dh_action_label($f['action_category'])) ?></span>
                  <?php if (!empty($f['action_detail'])): ?>
                    <p class="htf-detail-text"><?= htmlspecialchars($f['action_detail']) ?></p>
                  <?php endif; ?>
                </div>
              </div>
              <?php if ($has_outcome): ?>
              <div class="htf-finding-col">
                <div class="htf-finding-field-label">Outcome (recorded <?= date('d M Y', strtotime($f['outcome_at'])) ?>)</div>
                <div class="htf-finding-field-value">
                  <span class="htf-badge <?= dh_outcome_badge($f['outcome']) ?>" style="font-size:0.85rem; padding: 4px 12px;">
                    <?= htmlspecialchars(dh_outcome_label($f['outcome'])) ?>
                  </span>
                  <?php if (!empty($f['outcome_detail'])): ?>
                    <p class="htf-detail-text"><?= htmlspecialchars($f['outcome_detail']) ?></p>
                  <?php endif; ?>
                </div>
              </div>
              <?php endif; ?>
            </div><!-- /htf-finding-body -->

            <!-- ═══ EDM FEEDBACK & INTERVENTION SUPPORT SECTION (FOR SCHOOL FINDINGS) ═══ -->
            <?php if ($filter_scope === 'school'): ?>
              <?php if ($has_edm_fb): ?>
                <!-- Response Display Box -->
                <div class="edm-response-box">
                  <div class="edm-response-title">
                    <span> Official EDM Office Feedback &amp; Pledged Support</span>
                    <button type="button" onclick="document.getElementById('edm-edit-form-<?= $f['finding_id'] ?>').style.display='block'; this.style.display='none';" class="btn btn-secondary" style="font-size:0.75rem; padding:3px 8px;">
                      ✎ Edit Response
                    </button>
                  </div>
                  <div class="edm-response-body">
                    <?php if (!empty($f['edm_action'])): ?>
                      <p style="margin: 0 0 6px 0;"><strong>Pledged Support Intervention:</strong> <span style="background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 4px; font-weight: 600;"><?= htmlspecialchars($f['edm_action']) ?></span></p>
                    <?php endif; ?>
                    <p style="margin: 0; white-space: pre-wrap;"><?= htmlspecialchars($f['edm_feedback']) ?></p>
                    <div style="font-size: 0.76rem; color: #0284c7; margin-top: 8px; font-style: italic;">
                      Responded by <?= htmlspecialchars($f['edm_name'] ?: 'EDM Office') ?> on <?= date('d M Y, H:i', strtotime($f['edm_responded_at'])) ?>
                    </div>
                  </div>
                </div>

                <!-- Hidden Edit Form -->
                <div class="edm-form-box" id="edm-edit-form-<?= $f['finding_id'] ?>" style="display: none;">
                  <div class="edm-form-title">Update Official EDM Office Response for <?= htmlspecialchars($f['school_name']) ?></div>
                  <form method="POST">
                    <input type="hidden" name="finding_id" value="<?= $f['finding_id'] ?>">
                    <div style="margin-bottom: 10px;">
                      <label style="font-size: 0.82rem; font-weight: 600; display: block; margin-bottom: 4px;">Pledged EDM Intervention Action</label>
                      <input type="text" name="edm_action" class="rpt-filter-input" style="width: 100%; box-sizing: border-box;" value="<?= htmlspecialchars($f['edm_action'] ?? '') ?>" placeholder="e.g. Deploying 2 Relief Teachers, Emergency Textbook Procurement...">
                    </div>
                    <div style="margin-bottom: 12px;">
                      <label style="font-size: 0.82rem; font-weight: 600; display: block; margin-bottom: 4px;">EDM Guidance &amp; Official Feedback</label>
                      <textarea name="edm_feedback" rows="3" class="rpt-filter-input" style="width: 100%; box-sizing: border-box; resize: vertical;" required><?= htmlspecialchars($f['edm_feedback']) ?></textarea>
                    </div>
                    <div style="display: flex; gap: 8px;">
                      <button type="submit" name="save_edm_feedback" class="btn btn-primary" style="font-size: 0.82rem; padding: 6px 14px;">Save Updated Response</button>
                      <button type="button" onclick="document.getElementById('edm-edit-form-<?= $f['finding_id'] ?>').style.display='none';" class="btn btn-secondary" style="font-size: 0.82rem; padding: 6px 14px;">Cancel</button>
                    </div>
                  </form>
                </div>
              <?php else: ?>
                <!-- New Response Form -->
                <div class="edm-form-box">
                  <div class="edm-form-title"> Provide EDM Office Feedback &amp; Support Action for <?= htmlspecialchars($f['school_name']) ?></div>
                  <form method="POST">
                    <input type="hidden" name="finding_id" value="<?= $f['finding_id'] ?>">
                    <div style="margin-bottom: 10px;">
                      <label style="font-size: 0.82rem; font-weight: 600; display: block; margin-bottom: 4px;">Pledged Support Action / Intervention</label>
                      <select name="edm_action" class="rpt-filter-select" style="width: 100%; box-sizing: border-box;">
                        <option value="Staff Redeployment / Teacher Allocation">Staff Redeployment / Teacher Allocation</option>
                        <option value="Emergency Textbook & Material Procurement">Emergency Textbook &amp; Material Procurement</option>
                        <option value="Strategic Supervisory & Inspection Visit">Strategic Supervisory &amp; Inspection Visit</option>
                        <option value="Remedial Program Grant & Funding">Remedial Program Grant &amp; Funding</option>
                        <option value="Headteacher & Teacher CPD Training">Headteacher &amp; Teacher CPD Training</option>
                        <option value="Other Divisional Support Support">Other Divisional Support Intervention</option>
                      </select>
                    </div>
                    <div style="margin-bottom: 12px;">
                      <label style="font-size: 0.82rem; font-weight: 600; display: block; margin-bottom: 4px;">Official EDM Guidance &amp; Feedback</label>
                      <textarea name="edm_feedback" rows="3" class="rpt-filter-input" style="width: 100%; box-sizing: border-box; resize: vertical;" placeholder="Enter official guidance and intervention commitments from the EDM office for <?= htmlspecialchars($f['school_name']) ?>..." required></textarea>
                    </div>
                    <button type="submit" name="save_edm_feedback" class="btn btn-primary" style="font-size: 0.85rem; padding: 6px 16px;">
                      Submit EDM Response &amp; Help School
                    </button>
                  </form>
                </div>
              <?php endif; ?>
            <?php endif; ?>

          </div><!-- /htf-finding-card -->
        <?php endforeach; ?>
      </div>

      <!-- ═══ PAGINATION ═══ -->
      <?php if ($pagination['total_pages'] > 1): ?>
        <?= render_pagination($pagination, 'div_findings_history.php') ?>
      <?php endif; ?>

    <?php endif; ?>

  </div><!-- /content -->
</div><!-- /dashboard -->

<?php include __DIR__ . '/../../common/footer.php'; ?>

</body>
</html>
