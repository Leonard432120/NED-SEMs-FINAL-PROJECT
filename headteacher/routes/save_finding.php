<?php
/**
 * headteacher/routes/save_finding.php
 * ─────────────────────────────────────────────────────────────────
 * POST-only handler: create or replace a structured finding for a
 * (school_id, exam_id) pair.
 *
 * Security:
 *  - school_id comes exclusively from $_SESSION, never from POST.
 *  - Role gate: headteacher only.
 *  - All DB ops use prepared statements.
 *  - UPSERT: if a finding already exists for this exam it is replaced
 *    (only while lifecycle_status = 'open'; closed findings are locked).
 * ─────────────────────────────────────────────────────────────────
 */
session_start();
require_once __DIR__ . '/../../config/db.php';

// ── Auth & role gate ─────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'headteacher') {
    http_response_code(403);
    exit('Forbidden');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$school_id   = (int)$_SESSION['school_id'];
$recorded_by = (int)$_SESSION['user_id'];

// ── Input validation ─────────────────────────────────────────────
$exam_id = (int)($_POST['exam_id'] ?? 0);

// Allowed ENUM values — validated server-side so no raw string goes to DB
$valid_causes = [
    'teacher_absenteeism', 'resource_shortage', 'curriculum_gap',
    'student_discipline', 'assessment_irregularity', 'illness_outbreak',
    'staff_turnover', 'low_attendance', 'external_disruption',
    'positive_intervention', 'other',
];
$valid_actions = [
    'remedial_classes', 'staff_redeployment', 'resource_procurement',
    'parent_engagement', 'curriculum_revision', 'attendance_campaign',
    'pastoral_support', 'peer_mentoring', 'teacher_cpd',
    'celebration_recognition', 'no_action_yet', 'other',
];
$valid_trends = ['declining', 'improving', 'flat'];

$trend           = $_POST['trend'] ?? '';
$cause_category  = $_POST['cause_category'] ?? '';
$cause_detail    = trim($_POST['cause_detail'] ?? '');
$action_category = $_POST['action_category'] ?? '';
$action_detail   = trim($_POST['action_detail'] ?? '');
$pass_rate_pct   = (float)($_POST['pass_rate_pct'] ?? 0);

$errors = [];
if ($exam_id <= 0)                              $errors[] = 'Invalid exam.';
if (!in_array($trend, $valid_trends, true))     $errors[] = 'Invalid trend value.';
if (!in_array($cause_category, $valid_causes, true))   $errors[] = 'Select a cause category.';
if (!in_array($action_category, $valid_actions, true)) $errors[] = 'Select an action category.';
if ($pass_rate_pct < 0 || $pass_rate_pct > 100) $errors[] = 'Invalid pass rate.';

if (!empty($errors)) {
    // Return to reports page with error message
    $msg = urlencode(implode(' ', $errors));
    header("Location: ../../headteacher/reports.php?exam_id={$exam_id}&finding_error={$msg}");
    exit();
}

$conn = get_db_connection();

// ── Verify exam exists (basic integrity check) ────────────────────
$stmt = $conn->prepare("SELECT exam_id FROM exams WHERE exam_id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam_exists = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$exam_exists) {
    $conn->close();
    header("Location: ../../headteacher/reports.php?exam_id={$exam_id}&finding_error=" . urlencode('Exam not found.'));
    exit();
}

// ── Check if a closed finding already exists (cannot overwrite) ────
$stmt = $conn->prepare(
    "SELECT finding_id, lifecycle_status FROM ht_findings
     WHERE school_id = ? AND exam_id = ?"
);
$stmt->bind_param("ii", $school_id, $exam_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing && $existing['lifecycle_status'] === 'closed') {
    $conn->close();
    header("Location: ../../headteacher/reports.php?exam_id={$exam_id}&finding_error=" . urlencode('This finding has been closed and cannot be edited.'));
    exit();
}

// ── Upsert: INSERT … ON DUPLICATE KEY UPDATE ──────────────────────
// The UNIQUE KEY uq_school_exam (school_id, exam_id) handles the conflict.
$stmt = $conn->prepare("
    INSERT INTO ht_findings
        (school_id, exam_id, recorded_by, trend, pass_rate_pct,
         cause_category, cause_detail, action_category, action_detail,
         lifecycle_status, created_at, updated_at)
    VALUES
        (?, ?, ?, ?, ?,
         ?, ?, ?, ?,
         'open', NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        recorded_by      = VALUES(recorded_by),
        trend            = VALUES(trend),
        pass_rate_pct    = VALUES(pass_rate_pct),
        cause_category   = VALUES(cause_category),
        cause_detail     = VALUES(cause_detail),
        action_category  = VALUES(action_category),
        action_detail    = VALUES(action_detail),
        updated_at       = NOW()
");
$stmt->bind_param(
    "iiisdssss",
    $school_id, $exam_id, $recorded_by, $trend, $pass_rate_pct,
    $cause_category, $cause_detail, $action_category, $action_detail
);
$stmt->execute();
$stmt->close();

// ── Audit log ────────────────────────────────────────────────────
log_audit_event('HT_FINDING_SAVED', [
    'exam_id'        => $exam_id,
    'trend'          => $trend,
    'cause_category' => $cause_category,
    'action_category'=> $action_category,
], null, $conn);

$conn->close();

header("Location: ../../headteacher/reports.php?exam_id={$exam_id}&finding_saved=1");
exit();
