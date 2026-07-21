<?php
/**
 * headteacher/findings.php
 * ─────────────────────────────────────────────────────────────────
 * Searchable findings history for this school.
 * HTs can filter by: trend, cause category, outcome, exam year.
 * No cross-school data visible — every query is scoped to
 * (int)$_SESSION['school_id'].
 * ─────────────────────────────────────────────────────────────────
 */
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'headteacher') {
    header("Location: ../login.php");
    exit();
}

$conn      = get_db_connection();
$school_id = (int)$_SESSION['school_id'];

// ── School info ───────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT school_name, district FROM schools WHERE school_id = ?");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$school_info = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

// ── Filter inputs (sanitised) ────────────────────────────────────
$valid_trends   = ['', 'declining', 'improving', 'flat'];
$valid_outcomes = ['', 'improved', 'no_change', 'worsened'];
$valid_causes   = [
    '', 'teacher_absenteeism', 'resource_shortage', 'curriculum_gap',
    'student_discipline', 'assessment_irregularity', 'illness_outbreak',
    'staff_turnover', 'low_attendance', 'external_disruption',
    'positive_intervention', 'other',
];

$filter_trend   = in_array($_GET['trend'] ?? '', $valid_trends, true) ? ($_GET['trend'] ?? '') : '';
$filter_outcome = in_array($_GET['outcome'] ?? '', $valid_outcomes, true) ? ($_GET['outcome'] ?? '') : '';
$filter_cause   = in_array($_GET['cause'] ?? '', $valid_causes, true) ? ($_GET['cause'] ?? '') : '';
$filter_year    = isset($_GET['year']) ? (int)$_GET['year'] : 0;

// ── Build dynamic WHERE ───────────────────────────────────────────
// school_id is always bound; other filters are appended only when set.
$where_parts  = ["f.school_id = ?"];
$bind_types   = "i";
$bind_values  = [$school_id];

if ($filter_trend !== '') {
    $where_parts[] = "f.trend = ?";
    $bind_types   .= "s";
    $bind_values[] = $filter_trend;
}
if ($filter_cause !== '') {
    $where_parts[] = "f.cause_category = ?";
    $bind_types   .= "s";
    $bind_values[] = $filter_cause;
}
if ($filter_outcome !== '') {
    $where_parts[] = "o.outcome = ?";
    $bind_types   .= "s";
    $bind_values[] = $filter_outcome;
}
if ($filter_year > 0) {
    $where_parts[] = "e.year = ?";
    $bind_types   .= "i";
    $bind_values[] = $filter_year;
}

$where_sql = implode(' AND ', $where_parts);

$sql = "
    SELECT
        f.finding_id,
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
        e.exam_name,
        e.year   AS exam_year,
        o.outcome,
        o.outcome_detail,
        o.created_at AS outcome_at,
        adm_u.name AS edm_name
    FROM ht_findings f
    JOIN exams e ON e.exam_id = f.exam_id
    LEFT JOIN ht_finding_outcomes o ON o.finding_id = f.finding_id
    LEFT JOIN users adm_u ON adm_u.user_id = f.edm_responded_by
    WHERE {$where_sql}
    ORDER BY f.created_at DESC
";

$stmt = $conn->prepare($sql);
// Dynamically bind parameters
$stmt->bind_param($bind_types, ...$bind_values);
$stmt->execute();
$findings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Year list for filter dropdown ─────────────────────────────────
$stmt = $conn->prepare(
    "SELECT DISTINCT e.year FROM ht_findings f
     JOIN exams e ON e.exam_id = f.exam_id
     WHERE f.school_id = ?
     ORDER BY e.year DESC"
);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$year_list = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'year');
$stmt->close();

$conn->close();

// ── Label helpers ─────────────────────────────────────────────────
function cause_label(string $slug): string {
    return [
        'teacher_absenteeism'   => 'Teacher Absenteeism',
        'resource_shortage'     => 'Resource Shortage',
        'curriculum_gap'        => 'Curriculum Gap',
        'student_discipline'    => 'Student Discipline',
        'assessment_irregularity' => 'Assessment Irregularity',
        'illness_outbreak'      => 'Illness / Outbreak',
        'staff_turnover'        => 'Staff Turnover',
        'low_attendance'        => 'Low Attendance',
        'external_disruption'   => 'External Disruption',
        'positive_intervention' => 'Positive Intervention',
        'other'                 => 'Other',
    ][$slug] ?? ucfirst(str_replace('_', ' ', $slug));
}

function action_label(string $slug): string {
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

function outcome_label(string $slug): string {
    return ['improved' => 'Improved', 'no_change' => 'No Change', 'worsened' => 'Worsened'][$slug] ?? $slug;
}

function outcome_badge_class(string $outcome): string {
    return ['improved' => 'htf-badge--improved', 'no_change' => 'htf-badge--nochange', 'worsened' => 'htf-badge--worsened'][$outcome] ?? '';
}

function trend_badge_class(string $trend): string {
    return ['declining' => 'htf-badge--declining', 'improving' => 'htf-badge--improving', 'flat' => 'htf-badge--flat'][$trend] ?? '';
}

$portal_title = 'NED-SEMS | Findings History';
$module_css   = 'headteacher';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Findings History | Headteacher Portal | NED-SEMS</title>
  <meta name="description" content="Searchable history of headteacher investigation findings and outcomes for <?= htmlspecialchars($school_info['school_name'] ?? '') ?>">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
</head>
<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
  <?php include '../common/sidebar.php'; ?>

  <div class="content">

    <div class="page-header">
      <div>
        <h2 class="page-title">Findings History</h2>
        <p class="page-subtitle">
          <?= htmlspecialchars($school_info['school_name'] ?? '') ?> &nbsp;·&nbsp;
          Structured investigation log — all exam cycles
        </p>
      </div>
      <div class="header-actions">
        <a href="<?= BASE_URL ?>/headteacher/reports.php" class="btn btn-secondary">← Back to Reports</a>
      </div>
    </div>

    <?php if (isset($_GET['outcome_saved'])): ?>
      <div class="htf-alert htf-alert--success">Outcome recorded and finding closed successfully.</div>
    <?php endif; ?>

    <!-- ═══ FILTER PANEL ═══ -->
    <div class="rpt-filter-panel no-print">
      <form method="GET" class="rpt-filter-form" id="findings-filter-form">
        <div class="rpt-filter-group">
          <label class="rpt-filter-label" for="ff-trend">Trend Direction</label>
          <select name="trend" id="ff-trend" class="rpt-filter-select">
            <option value="">All Trends</option>
            <option value="declining"  <?= $filter_trend === 'declining'  ? 'selected' : '' ?>>Declining</option>
            <option value="improving"  <?= $filter_trend === 'improving'  ? 'selected' : '' ?>>Improving</option>
            <option value="flat"       <?= $filter_trend === 'flat'       ? 'selected' : '' ?>>Flat</option>
          </select>
        </div>
        <div class="rpt-filter-group">
          <label class="rpt-filter-label" for="ff-cause">Root Cause</label>
          <select name="cause" id="ff-cause" class="rpt-filter-select">
            <option value="">All Causes</option>
            <?php
            $causes = [
                'teacher_absenteeism','resource_shortage','curriculum_gap',
                'student_discipline','assessment_irregularity','illness_outbreak',
                'staff_turnover','low_attendance','external_disruption',
                'positive_intervention','other',
            ];
            foreach ($causes as $c):
            ?>
              <option value="<?= $c ?>" <?= $filter_cause === $c ? 'selected' : '' ?>><?= htmlspecialchars(cause_label($c)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="rpt-filter-group">
          <label class="rpt-filter-label" for="ff-outcome">Outcome</label>
          <select name="outcome" id="ff-outcome" class="rpt-filter-select">
            <option value="">All Outcomes</option>
            <option value="improved"   <?= $filter_outcome === 'improved'   ? 'selected' : '' ?>>Improved</option>
            <option value="no_change"  <?= $filter_outcome === 'no_change'  ? 'selected' : '' ?>>No Change</option>
            <option value="worsened"   <?= $filter_outcome === 'worsened'   ? 'selected' : '' ?>>Worsened</option>
          </select>
        </div>
        <div class="rpt-filter-group">
          <label class="rpt-filter-label" for="ff-year">Exam Year</label>
          <select name="year" id="ff-year" class="rpt-filter-select">
            <option value="0">All Years</option>
            <?php foreach ($year_list as $y): ?>
              <option value="<?= (int)$y ?>" <?= $filter_year === (int)$y ? 'selected' : '' ?>><?= (int)$y ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="rpt-filter-actions">
          <button type="submit" class="btn btn-primary">Filter</button>
          <a href="<?= BASE_URL ?>/headteacher/findings.php" class="btn btn-secondary">Reset</a>
        </div>
      </form>
    </div>

    <!-- ═══ FINDINGS TABLE ═══ -->
    <?php if (empty($findings)): ?>
      <div class="card" style="padding: 48px; text-align: center;">
        <p style="color: var(--text-muted); font-size: 1rem; margin: 0;">
          No findings recorded yet<?= ($filter_trend || $filter_cause || $filter_outcome || $filter_year) ? ' matching these filters' : '' ?>.
        </p>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 8px;">
          Findings are logged from the <a href="<?= BASE_URL ?>/headteacher/reports.php" style="color: var(--info-color);">School Reports</a> page when a trend is detected.
        </p>
      </div>
    <?php else: ?>

      <div style="margin-bottom: 12px; font-size: 0.85rem; color: var(--text-muted);">
        Showing <strong><?= count($findings) ?></strong> finding<?= count($findings) !== 1 ? 's' : '' ?>
      </div>

      <div class="htf-findings-list">
        <?php foreach ($findings as $f): ?>
          <?php
            $is_open    = $f['lifecycle_status'] === 'open';
            $has_outcome = !$is_open && !empty($f['outcome']);
          ?>
          <div class="htf-finding-card <?= $is_open ? 'htf-finding-card--open' : 'htf-finding-card--closed' ?>">

            <!-- Card Header -->
            <div class="htf-finding-header">
              <div class="htf-finding-meta">
                <span class="htf-badge <?= trend_badge_class($f['trend']) ?>">
                  <?= ucfirst(htmlspecialchars($f['trend'])) ?>
                </span>
                <strong style="font-size: 0.95rem; color: #0f172a;"><?= htmlspecialchars($f['exam_name']) ?> (<?= (int)$f['exam_year'] ?>)</strong>
                <span class="htf-pass-rate">Pass rate: <strong><?= number_format((float)$f['pass_rate_pct'], 1) ?>%</strong></span>
              </div>
              <div style="display: flex; align-items: center; gap: 10px;">
                <?php if ($is_open): ?>
                  <span class="htf-badge htf-badge--open">Open</span>
                <?php elseif ($has_outcome): ?>
                  <span class="htf-badge <?= outcome_badge_class($f['outcome']) ?>">
                    <?= htmlspecialchars(outcome_label($f['outcome'])) ?>
                  </span>
                <?php endif; ?>
                <span style="font-size: 0.78rem; color: var(--text-muted);"><?= date('d M Y', strtotime($f['created_at'])) ?></span>
              </div>
            </div>

            <!-- Card Body -->
            <div class="htf-finding-body">
              <div class="htf-finding-col">
                <div class="htf-finding-field-label">Root Cause</div>
                <div class="htf-finding-field-value">
                  <span class="htf-cause-tag"><?= htmlspecialchars(cause_label($f['cause_category'])) ?></span>
                  <?php if (!empty($f['cause_detail'])): ?>
                    <p class="htf-detail-text"><?= htmlspecialchars($f['cause_detail']) ?></p>
                  <?php endif; ?>
                </div>
              </div>
              <div class="htf-finding-col">
                <div class="htf-finding-field-label">Action Taken</div>
                <div class="htf-finding-field-value">
                  <span class="htf-action-tag"><?= htmlspecialchars(action_label($f['action_category'])) ?></span>
                  <?php if (!empty($f['action_detail'])): ?>
                    <p class="htf-detail-text"><?= htmlspecialchars($f['action_detail']) ?></p>
                  <?php endif; ?>
                </div>
              </div>
              <?php if ($has_outcome): ?>
              <div class="htf-finding-col">
                <div class="htf-finding-field-label">Outcome (recorded <?= date('d M Y', strtotime($f['outcome_at'])) ?>)</div>
                <div class="htf-finding-field-value">
                  <span class="htf-badge <?= outcome_badge_class($f['outcome']) ?>" style="font-size:0.85rem; padding: 4px 12px;">
                    <?= htmlspecialchars(outcome_label($f['outcome'])) ?>
                  </span>
                  <?php if (!empty($f['outcome_detail'])): ?>
                    <p class="htf-detail-text"><?= htmlspecialchars($f['outcome_detail']) ?></p>
                  <?php endif; ?>
                </div>
              </div>
              <?php elseif ($is_open): ?>
              <div class="htf-finding-col">
                <div class="htf-finding-field-label">Outcome</div>
                <div class="htf-finding-field-value" style="color: var(--text-muted); font-size: 0.85rem; font-style: italic;">
                  Pending — close out from the School Reports page after the next exam cycle.
                </div>
              </div>
              <?php endif; ?>
            </div><!-- /htf-finding-body -->

            <?php if (!empty($f['edm_feedback'])): ?>
              <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-left: 4px solid #0284c7; border-radius: 6px; padding: 14px 16px; margin: 0 16px 16px 16px;">
                <div style="font-size: 0.82rem; font-weight: 700; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
                   Official EDM Office Response &amp; Support Intervention
                </div>
                <div style="font-size: 0.9rem; color: #0c4a6e; line-height: 1.5;">
                  <?php if (!empty($f['edm_action'])): ?>
                    <p style="margin: 0 0 6px 0;"><strong>Pledged Support Intervention:</strong> <span style="background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 4px; font-weight: 600;"><?= htmlspecialchars($f['edm_action']) ?></span></p>
                  <?php endif; ?>
                  <p style="margin: 0; white-space: pre-wrap;"><?= htmlspecialchars($f['edm_feedback']) ?></p>
                  <div style="font-size: 0.76rem; color: #0284c7; margin-top: 8px; font-style: italic;">
                    Responded by <?= htmlspecialchars($f['edm_name'] ?: 'EDM Office') ?> on <?= date('d M Y, H:i', strtotime($f['edm_responded_at'])) ?>
                  </div>
                </div>
              </div>
            <?php endif; ?>

          </div><!-- /htf-finding-card -->
        <?php endforeach; ?>
      </div>

    <?php endif; ?>

  </div><!-- /content -->
</div><!-- /dashboard -->

<?php include '../common/footer.php'; ?>

</body>
</html>
