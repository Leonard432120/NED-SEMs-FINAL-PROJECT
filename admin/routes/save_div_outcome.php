<?php
/**
 * admin/routes/save_div_outcome.php
 * ─────────────────────────────────────────────────────────────────
 * POST-only handler: close a division-level open finding with an
 * outcome and details.
 *
 * Security:
 *  - Role gate: admin only.
 *  - Prepared statements.
 *  - Verifies finding exists and is open.
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
$finding_id  = (int)($_POST['finding_id'] ?? 0);
$current_exam_id = (int)($_POST['current_exam_id'] ?? 0);
$outcome_detail  = trim($_POST['outcome_detail'] ?? '');

$valid_outcomes = ['improved', 'no_change', 'worsened'];
$outcome = $_POST['outcome'] ?? '';

$errors = [];
if ($finding_id <= 0)                           $errors[] = 'Invalid finding.';
if ($current_exam_id <= 0)                      $errors[] = 'Invalid exam.';
if (!in_array($outcome, $valid_outcomes, true)) $errors[] = 'Select an outcome.';

if (!empty($errors)) {
    $msg = urlencode(implode(' ', $errors));
    header("Location: ../../admin/reports/division_report.php?exam_id={$current_exam_id}&outcome_error={$msg}");
    exit();
}

$conn = get_db_connection();

// Fetch finding details to ensure it is open
$stmt = $conn->prepare("SELECT finding_id, lifecycle_status FROM div_findings WHERE finding_id = ?");
$stmt->bind_param("i", $finding_id);
$stmt->execute();
$finding = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$finding) {
    $conn->close();
    header("Location: ../../admin/reports/division_report.php?exam_id={$current_exam_id}&outcome_error=" . urlencode('Finding not found.'));
    exit();
}
if ($finding['lifecycle_status'] === 'closed') {
    $conn->close();
    header("Location: ../../admin/reports/division_report.php?exam_id={$current_exam_id}&outcome_error=" . urlencode('This finding is already closed.'));
    exit();
}

// Insert outcome
$stmt = $conn->prepare("
    INSERT INTO div_finding_outcomes
        (finding_id, exam_id, recorded_by, outcome, outcome_detail, created_at)
    VALUES
        (?, ?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
        recorded_by    = VALUES(recorded_by),
        exam_id        = VALUES(exam_id),
        outcome        = VALUES(outcome),
        outcome_detail = VALUES(outcome_detail)
");
$stmt->bind_param(
    "iiiss",
    $finding_id, $current_exam_id, $recorded_by, $outcome, $outcome_detail
);
$stmt->execute();
$stmt->close();

// Update status to closed
$stmt = $conn->prepare("UPDATE div_findings SET lifecycle_status = 'closed', updated_at = NOW() WHERE finding_id = ?");
$stmt->bind_param("i", $finding_id);
$stmt->execute();
$stmt->close();

log_audit_event('DIV_FINDING_OUTCOME_SAVED', [
    'finding_id' => $finding_id,
    'exam_id'    => $current_exam_id,
    'outcome'    => $outcome
], null, $conn);

$conn->close();

header("Location: ../../admin/reports/division_report.php?exam_id={$current_exam_id}&outcome_saved=1");
exit();
