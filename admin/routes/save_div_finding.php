<?php
/**
 * admin/routes/save_div_finding.php
 * ─────────────────────────────────────────────────────────────────
 * POST-only handler: create or replace a strategic finding at the
 * division level for a given exam_id.
 *
 * Security:
 *  - Role gate: admin only.
 *  - Prepared statements.
 *  - Restricts editing if finding is closed.
 * ─────────────────────────────────────────────────────────────────
 */
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$recorded_by = (int)$_SESSION['user_id'];
$exam_id     = (int)($_POST['exam_id'] ?? 0);

$valid_causes = [
    'teacher_shortage', 'funding_delays', 'learning_material_deficiency',
    'curriculum_misalignment', 'teacher_compliance_low', 'extreme_weather',
    'administrative_laxity', 'positive_divisional_reform', 'other'
];
$valid_actions = [
    'teacher_recruitment', 'budget_allocation', 'textbook_distribution',
    'inspection_blitz', 'teacher_capacity_building', 'remedial_policy_mandate',
    'divisional_recognition', 'other'
];
$valid_trends = ['declining', 'improving', 'flat'];

$trend           = $_POST['trend'] ?? '';
$cause_category  = $_POST['cause_category'] ?? '';
$cause_detail    = trim($_POST['cause_detail'] ?? '');
$action_category = $_POST['action_category'] ?? '';
$action_detail   = trim($_POST['action_detail'] ?? '');
$avg_score_pct   = (float)($_POST['avg_score_pct'] ?? 0);

$errors = [];
if ($exam_id <= 0)                                     $errors[] = 'Invalid exam.';
if (!in_array($trend, $valid_trends, true))            $errors[] = 'Invalid trend.';
if (!in_array($cause_category, $valid_causes, true))   $errors[] = 'Select a cause category.';
if (!in_array($action_category, $valid_actions, true)) $errors[] = 'Select an action category.';

if (!empty($errors)) {
    $msg = urlencode(implode(' ', $errors));
    header("Location: ../../admin/reports/division_report.php?exam_id={$exam_id}&finding_error={$msg}");
    exit();
}

$conn = get_db_connection();

// Verify exam
$stmt = $conn->prepare("SELECT exam_id FROM exams WHERE exam_id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam_exists = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$exam_exists) {
    $conn->close();
    header("Location: ../../admin/reports/division_report.php?exam_id={$exam_id}&finding_error=" . urlencode('Exam not found.'));
    exit();
}

// Check if closed finding exists
$stmt = $conn->prepare("SELECT finding_id, lifecycle_status FROM div_findings WHERE exam_id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing && $existing['lifecycle_status'] === 'closed') {
    $conn->close();
    header("Location: ../../admin/reports/division_report.php?exam_id={$exam_id}&finding_error=" . urlencode('This division finding is closed and cannot be modified.'));
    exit();
}

// Upsert
$stmt = $conn->prepare("
    INSERT INTO div_findings
        (exam_id, recorded_by, trend, avg_score_pct,
         cause_category, cause_detail, action_category, action_detail,
         lifecycle_status, created_at, updated_at)
    VALUES
        (?, ?, ?, ?,
         ?, ?, ?, ?,
         'open', NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        recorded_by      = VALUES(recorded_by),
        trend            = VALUES(trend),
        avg_score_pct    = VALUES(avg_score_pct),
        cause_category   = VALUES(cause_category),
        cause_detail     = VALUES(cause_detail),
        action_category  = VALUES(action_category),
        action_detail    = VALUES(action_detail),
        updated_at       = NOW()
");
$stmt->bind_param(
    "iisdssss",
    $exam_id, $recorded_by, $trend, $avg_score_pct,
    $cause_category, $cause_detail, $action_category, $action_detail
);
$stmt->execute();
$stmt->close();

log_audit_event('DIV_FINDING_SAVED', [
    'exam_id' => $exam_id,
    'trend'   => $trend,
    'cause'   => $cause_category,
    'action'  => $action_category
], null, $conn);

$conn->close();

header("Location: ../../admin/reports/division_report.php?exam_id={$exam_id}&finding_saved=1");
exit();
