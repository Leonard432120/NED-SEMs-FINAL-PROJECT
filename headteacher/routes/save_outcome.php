<?php
/**
 * headteacher/routes/save_outcome.php
 * ─────────────────────────────────────────────────────────────────
 * POST-only handler: record the outcome of a prior open finding.
 * Closes the finding lifecycle and inserts a ht_finding_outcomes row.
 *
 * Security:
 *  - school_id comes exclusively from $_SESSION.
 *  - Verifies finding belongs to this school before updating.
 *  - Verifies finding is still open (cannot close twice).
 *  - All DB ops use prepared statements.
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
$finding_id    = (int)($_POST['finding_id'] ?? 0);
$current_exam_id = (int)($_POST['current_exam_id'] ?? 0);  // the exam the HT is currently viewing
$outcome_detail  = trim($_POST['outcome_detail'] ?? '');

$valid_outcomes = ['improved', 'no_change', 'worsened'];
$outcome = $_POST['outcome'] ?? '';

$errors = [];
if ($finding_id <= 0)                                   $errors[] = 'Invalid finding.';
if ($current_exam_id <= 0)                              $errors[] = 'Invalid exam context.';
if (!in_array($outcome, $valid_outcomes, true))         $errors[] = 'Select a valid outcome.';

if (!empty($errors)) {
    $msg = urlencode(implode(' ', $errors));
    header("Location: ../../headteacher/reports.php?exam_id={$current_exam_id}&outcome_error={$msg}");
    exit();
}

$conn = get_db_connection();

// ── Fetch and verify finding ownership + open status ─────────────
// The WHERE school_id = ? is the cross-school leakage guard.
$stmt = $conn->prepare(
    "SELECT finding_id, lifecycle_status
     FROM ht_findings
     WHERE finding_id = ? AND school_id = ?"
);
$stmt->bind_param("ii", $finding_id, $school_id);
$stmt->execute();
$finding = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$finding) {
    $conn->close();
    header("Location: ../../headteacher/reports.php?exam_id={$current_exam_id}&outcome_error=" . urlencode('Finding not found.'));
    exit();
}
if ($finding['lifecycle_status'] === 'closed') {
    $conn->close();
    header("Location: ../../headteacher/reports.php?exam_id={$current_exam_id}&outcome_error=" . urlencode('This finding has already been closed.'));
    exit();
}

// ── Insert outcome ────────────────────────────────────────────────
$stmt = $conn->prepare("
    INSERT INTO ht_finding_outcomes
        (finding_id, school_id, exam_id, recorded_by, outcome, outcome_detail, created_at)
    VALUES
        (?, ?, ?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
        recorded_by    = VALUES(recorded_by),
        exam_id        = VALUES(exam_id),
        outcome        = VALUES(outcome),
        outcome_detail = VALUES(outcome_detail)
");
$stmt->bind_param(
    "iiiiss",
    $finding_id, $school_id, $current_exam_id, $recorded_by, $outcome, $outcome_detail
);
$stmt->execute();
$stmt->close();

// ── Close the finding lifecycle ───────────────────────────────────
$stmt = $conn->prepare(
    "UPDATE ht_findings
     SET lifecycle_status = 'closed', updated_at = NOW()
     WHERE finding_id = ? AND school_id = ?"
);
$stmt->bind_param("ii", $finding_id, $school_id);
$stmt->execute();
$stmt->close();

// ── Audit log ────────────────────────────────────────────────────
log_audit_event('HT_FINDING_OUTCOME_SAVED', [
    'finding_id' => $finding_id,
    'exam_id'    => $current_exam_id,
    'outcome'    => $outcome,
], null, $conn);

$conn->close();

header("Location: ../../headteacher/reports.php?exam_id={$current_exam_id}&outcome_saved=1");
exit();
