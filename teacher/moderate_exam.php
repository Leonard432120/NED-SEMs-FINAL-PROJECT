<?php
require_once __DIR__ . '/teacher_init.php';
require_once __DIR__ . '/../services/ai/compose_bridge.php';

$exam_id    = isset($_GET['exam_id'])    ? (int)$_GET['exam_id']    : 0;
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

if ($exam_id <= 0) {
    header("Location: moderation_exams.php");
    exit();
}

$conn = get_db_connection();

// Fetch Exam Details — scoped to subject if subject_id given
if ($subject_id > 0) {
    $stmt = $conn->prepare("
        SELECT
            e.*,
            u.name AS teacher_name,
            s.subject_name,
            es.id  AS exam_subject_id,
            es.subject_id
        FROM exams e
        LEFT JOIN users u ON e.created_by = u.user_id
        INNER JOIN exam_subjects es ON e.exam_id = es.exam_id AND es.subject_id = ?
        INNER JOIN subjects s ON es.subject_id = s.subject_id
        WHERE e.exam_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $subject_id, $exam_id);
} else {
    $stmt = $conn->prepare("
        SELECT
            e.*,
            u.name AS teacher_name,
            s.subject_name,
            es.id  AS exam_subject_id,
            es.subject_id
        FROM exams e
        LEFT JOIN users u ON e.created_by = u.user_id
        LEFT JOIN exam_subjects es ON e.exam_id = es.exam_id
        LEFT JOIN subjects s ON es.subject_id = s.subject_id
        WHERE e.exam_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $exam_id);
}
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Derive exam_subject_id for question filtering
$exam_subject_id = (int)($exam['exam_subject_id'] ?? 0);
if ($subject_id <= 0) {
    $subject_id = (int)($exam['subject_id'] ?? 0);
}

if (!$exam) {
    $conn->close();
    header("Location: moderation_exams.php");
    exit();
}

// Check access permission: Admin, Creator, or Moderator scoped to this subject
$is_admin = ($_SESSION['role'] ?? '') === 'admin';
$is_creator = ($exam['created_by'] ?? 0) == $user_id;
$is_moderator = false;

if (!$is_admin && !$is_creator) {
    if ($subject_id > 0) {
        $m_stmt = $conn->prepare("
            SELECT 1
            FROM subject_assignments sa
            INNER JOIN exam_subjects es ON sa.subject_id = es.subject_id
            WHERE es.exam_id = ? AND es.subject_id = ? AND sa.teacher_id = ? AND sa.role = 'moderator'
            LIMIT 1
        ");
        $m_stmt->bind_param("iii", $exam_id, $subject_id, $user_id);
    } else {
        $m_stmt = $conn->prepare("
            SELECT 1
            FROM subject_assignments sa
            INNER JOIN exam_subjects es ON sa.subject_id = es.subject_id
            WHERE es.exam_id = ? AND sa.teacher_id = ? AND sa.role = 'moderator'
            LIMIT 1
        ");
        $m_stmt->bind_param("ii", $exam_id, $user_id);
    }
    $m_stmt->execute();
    $is_moderator = (bool)$m_stmt->get_result()->fetch_assoc();
    $m_stmt->close();
}

if (!$is_admin && !$is_creator && !$is_moderator) {
    $conn->close();
    header("Location: moderation_exams.php");
    exit();
}

// Get questions — filtered to this subject
if ($exam_subject_id > 0) {
    $stmt = $conn->prepare("
        SELECT *
        FROM questions
        WHERE exam_subject_id = ?
        ORDER BY question_order ASC
    ");
    $stmt->bind_param("i", $exam_subject_id);
} else {
    $stmt = $conn->prepare("
        SELECT *
        FROM questions
        WHERE exam_id = ?
        ORDER BY question_order ASC
    ");
    $stmt->bind_param("i", $exam_id);
}
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle POST for saving moderation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Check if the exam itself is already approved
    $exam_status_stmt = $conn->prepare("SELECT status FROM exams WHERE exam_id = ? LIMIT 1");
    $exam_status_stmt->bind_param("i", $exam_id);
    $exam_status_stmt->execute();
    $exam_status_row = $exam_status_stmt->get_result()->fetch_assoc();
    $exam_status_stmt->close();

    if (($exam_status_row['status'] ?? '') === 'approved') {
        header("Location: moderate_exam.php?exam_id=$exam_id&locked=1");
        exit();
    }

    $question_id = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;
    $status = $_POST['status'] ?? '';
    $comment = trim($_POST['comment'] ?? '');

    $allowed = ['approved', 'revise', 'rejected'];

    if ($question_id > 0 && in_array($status, $allowed)) {

        // Block re-moderation of already-approved questions
        $lock_chk = $conn->prepare('SELECT moderation_status FROM questions WHERE question_id = ? AND exam_id = ?');
        $lock_chk->bind_param('ii', $question_id, $exam_id);
        $lock_chk->execute();
        $lock_row = $lock_chk->get_result()->fetch_assoc();
        $lock_chk->close();

        if (($lock_row['moderation_status'] ?? '') === 'approved') {
            // Already approved — do not allow changes
            header("Location: moderate_exam.php?exam_id=$exam_id&locked=1");
            exit();
        }

        // 1. Update question main table
        $stmt = $conn->prepare("
            UPDATE questions 
            SET moderation_status = ?, moderator_comment = ?
            WHERE question_id = ?
        ");
        $stmt->bind_param("ssi", $status, $comment, $question_id);
        $stmt->execute();
        $stmt->close();

        // 2. Insert into moderation history table
        $stmt = $conn->prepare("
            INSERT INTO question_moderation 
            (question_id, moderator_id, status, comment)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("iiss", $question_id, $user_id, $status, $comment);
        $stmt->execute();
        $stmt->close();

        // 3. Log Audit Event
        log_audit_event('QUESTION_MODERATED', [
            'exam_id' => $exam_id,
            'question_id' => $question_id,
            'status' => $status,
            'comment' => $comment
        ], null, $conn);

        // 3. Log workflow (optional but powerful)
        $stmt = $conn->prepare("
            INSERT INTO exam_workflow_logs 
            (exam_id, action, performed_by, role, notes)
            VALUES (?, 'question_moderation', ?, 'moderator', ?)
        ");
        $note = "Moderated question ID $question_id as $status";
        $stmt->bind_param("iis", $exam_id, $user_id, $note);
        $stmt->execute();
        $stmt->close();

        // 4. Automatically recalculate and update the overall exam status
        $chk_stmt = $conn->prepare("SELECT moderation_status FROM questions WHERE exam_id = ?");
        $chk_stmt->bind_param("i", $exam_id);
        $chk_stmt->execute();
        $q_res = $chk_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $chk_stmt->close();

        $total_q = count($q_res);
        $approved_q = 0;
        $revise_q = 0;
        $rejected_q = 0;
        $pending_q = 0;

        foreach ($q_res as $qr) {
            $st = $qr['moderation_status'] ?? 'pending';
            if ($st === 'approved') {
                $approved_q++;
            } elseif ($st === 'revise') {
                $revise_q++;
            } elseif ($st === 'rejected') {
                $rejected_q++;
            } else {
                $pending_q++;
            }
        }

        if ($total_q > 0) {
            if ($approved_q === $total_q) {
                $new_exam_status = 'approved';
            } elseif ($pending_q > 0) {
                $new_exam_status = 'under_moderation';
            } else {
                $new_exam_status = 'needs_revision';
            }

            $up_stmt = $conn->prepare("UPDATE exams SET status = ? WHERE exam_id = ?");
            $up_stmt->bind_param("si", $new_exam_status, $exam_id);
            $up_stmt->execute();
            $up_stmt->close();
        }
    }

    header("Location: moderate_exam.php?exam_id=$exam_id&subject_id=$subject_id");
    exit();
}
// Fresh AI moderation report per question (independent from teacher compose analysis)
$ai_map = compose_build_ai_map($conn, $questions, $exam_id, $exam_subject_id);
$conn->close();

// Analytics
$total_marks = array_sum(array_column($questions, 'marks'));
$easy = 0;
$medium = 0;
$hard = 0;

foreach ($questions as $q) {
    $ai = $ai_map[$q['question_id']] ?? [];
    $bloom = $ai['cognitive_level'] ?? $ai['bloom_level'] ?? '';
    if (in_array($bloom, ['Remembering', 'Understanding', 'Remember', 'Understand'])) {
        $easy++;
    } elseif (in_array($bloom, ['Applying', 'Analysing', 'Apply', 'Analyze'])) {
        $medium++;
    } elseif (in_array($bloom, ['Evaluating', 'Creating', 'Evaluate', 'Create'])) {
        $hard++;
    }
}

// Risk score
$risk_score = 0;
foreach ($ai_map as $ai) {
    $quality = $ai["quality_score"] ?? 100;
    if ($quality < 50) {
        $risk_score += 15;
    } elseif ($quality < 70) {
        $risk_score += 8;
    }
}
$risk_score = min($risk_score, 100);

$risk_level = $risk_score >= 70 ? "High" : ($risk_score >= 40 ? "Medium" : "Low");

// Findings
$findings = [];
if ($hard == 0) {
    $findings[] = "Exam lacks higher-order thinking questions.";
}
if ($easy > ($medium + $hard)) {
    $findings[] = "Exam is dominated by easy questions.";
}
if ($total_marks < 50) {
    $findings[] = "Total exam marks appear too low.";
}
if (empty($findings)) {
    $findings[] = "Exam structure looks balanced.";
}

$ai_report = [
    "risk_score" => $risk_score,
    "level" => $risk_level,
    "stats" => [
        "total_marks" => $total_marks,
        "easy" => $easy,
        "medium" => $medium,
        "hard" => $hard
    ],
    "findings" => $findings
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Moderate Exam - <?= htmlspecialchars($exam['exam_name']); ?></title>
<?php
$portal_title = 'NED-SEMs | Teacher Portal';
$module_css = 'teacher';
include __DIR__ . '/../common/head_assets.php';
?>
<style>
/* ══════════════════════════════════════════════════════════
   MODERATE EXAM — Premium UI (matches compose_exam)
══════════════════════════════════════════════════════════ */
:root {
    --bg: var(--background-color, #f4f6f8);
    --surface: var(--card-color, #ffffff);
    --surface2: #f1f5f9;
    --border: var(--border-color, #e2e8f0);
    --accent: #2563eb;
    --success: var(--success-color, #22c55e);
    --warning: var(--warning-color, #facc15);
    --danger: var(--danger-color, #ef4444);
    --text: var(--text-color, #1e293b);
    --muted: var(--text-muted, #64748b);
    --card-shadow: var(--box-shadow, 0 8px 20px rgba(0,0,0,0.06));
}

/* ── Page Header ── */
.compose-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 24px;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    gap: 16px;
    flex-wrap: wrap;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}

.compose-title-block h1 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text);
    line-height: 1.2;
    margin: 0;
}

.compose-title-block .meta {
    font-size: 12px;
    color: var(--muted);
    margin-top: 2px;
}

.header-stats { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }

.stat-pill {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 4px 12px;
    font-size: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
    color: var(--muted);
}
.stat-pill.success { border-color: var(--success); color: #15803d; }
.stat-pill.warning { border-color: #f59e0b;        color: #b45309; }
.stat-pill.danger  { border-color: var(--danger);  color: #b91c1c; }
.stat-pill.info    { border-color: var(--accent);  color: var(--accent); }

/* ── Layout ── */
.moderate-layout {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 0;
    height: calc(100vh - 57px);
    overflow: hidden;
}

/* ── Left Panel ── */
.q-panel {
    background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.q-panel-header {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
    font-weight: 700;
    color: var(--muted);
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.q-list {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
}

.q-list::-webkit-scrollbar { width: 4px; }
.q-list::-webkit-scrollbar-track { background: transparent; }
.q-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

.q-item {
    padding: 10px 12px;
    border-radius: 10px;
    cursor: pointer;
    margin-bottom: 6px;
    border: 1px solid transparent;
    transition: all 0.15s ease;
    position: relative;
}

.q-item:hover { background: var(--surface2); border-color: var(--border); }
.q-item.active {
    background: rgba(37, 99, 235, 0.08);
    border-color: var(--accent);
}

.q-item-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.q-num { font-size: 13px; font-weight: 700; color: var(--text); }

.q-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--border);
    flex-shrink: 0;
}
.q-status-dot.approved { background: var(--success); }
.q-status-dot.revise   { background: #f59e0b; }
.q-status-dot.rejected { background: var(--danger); }

.q-preview {
    font-size: 11px;
    color: var(--muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 220px;
}

/* ── Right Panel ── */
.editor-panel {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: var(--bg);
}

.editor-inner {
    flex: 1;
    overflow-y: auto;
    padding: 24px;
}

.editor-inner::-webkit-scrollbar { width: 4px; }
.editor-inner::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

/* ── Question Card ── */
.editor-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 28px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.editor-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border);
}

.editor-card-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text);
    margin: 0 0 4px;
}

.marks-badge {
    background: rgba(37, 99, 235, 0.1);
    color: var(--accent);
    border: 1px solid rgba(37, 99, 235, 0.25);
    border-radius: 20px;
    padding: 3px 12px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

/* ── Question body ── */
.question-body {
    font-size: 15px;
    line-height: 1.75;
    color: var(--text);
    margin-bottom: 20px;
}

/* ── MCQ Options ── */
.mcq-options { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }

.mcq-opt {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 14px;
    color: var(--text);
}

.mcq-opt .opt-label {
    background: var(--accent);
    color: #fff;
    width: 26px;
    height: 26px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    flex-shrink: 0;
}

.correct-answer-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(34, 197, 94, 0.08);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #15803d;
    border-radius: 8px;
    padding: 4px 12px;
    font-size: 12px;
    font-weight: 600;
    margin-top: 4px;
}

/* ── AI Report Card ── */
.ai-report-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.ai-report-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--border);
}

.ai-report-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--text);
    margin: 0;
}

.ai-tag {
    background: rgba(37, 99, 235, 0.08);
    color: var(--accent);
    border: 1px solid rgba(37, 99, 235, 0.2);
    border-radius: 20px;
    padding: 2px 10px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.ai-metrics-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 16px;
}

.ai-metric {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px;
    text-align: center;
}

.ai-metric .lbl {
    font-size: 10px;
    color: var(--muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 6px;
}

.ai-metric .val {
    font-size: 16px;
    font-weight: 800;
    color: var(--text);
}

.ai-metric .val.accent { color: var(--accent); }
.ai-metric .val.success { color: #15803d; }
.ai-metric .val.warning { color: #b45309; }
.ai-metric .val.danger  { color: #b91c1c; }

.ai-section {
    margin-bottom: 14px;
}

.ai-section-title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--muted);
    margin: 0 0 8px;
}

.feedback-item {
    background: var(--surface2);
    border-left: 3px solid var(--accent);
    border-radius: 0 8px 8px 0;
    padding: 10px 14px;
    font-size: 13px;
    color: var(--text);
    margin-bottom: 6px;
    line-height: 1.5;
}

.feedback-item.grammar  { border-left-color: var(--danger); }
.feedback-item.ambiguity{ border-left-color: #f59e0b; }
.feedback-item.duplicate{ border-left-color: #b91c1c; background: rgba(239,68,68,0.04); }
.feedback-item.suggest  { border-left-color: var(--success); }
.feedback-item.warning  { border-left-color: var(--danger); background: rgba(239,68,68,0.04); }

.recommendation-box {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 14px 16px;
    font-size: 13px;
    color: var(--text);
    line-height: 1.6;
}

/* ── Moderation Decision Card ── */
.moderation-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.moderation-card-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--text);
    margin: 0 0 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border);
}

.choice-grid {
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
}

.choice {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 10px;
    background: var(--surface2);
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 14px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
    transition: all 0.15s ease;
}

.choice:hover { background: #e2e8f0; }

.choice input[type="radio"] {
    transform: scale(1.2);
    accent-color: var(--accent);
    cursor: pointer;
    flex-shrink: 0;
}

.choice.approve:has(input:checked) { border-color: var(--success); background: rgba(34,197,94,0.08); color: #15803d; }
.choice.revise:has(input:checked)  { border-color: #f59e0b;        background: rgba(245,158,11,0.08); color: #b45309; }
.choice.reject:has(input:checked)  { border-color: var(--danger);  background: rgba(239,68,68,0.08); color: #b91c1c; }

.comment-field {
    width: 100%;
    background: var(--surface2);
    border: 1px solid var(--border);
    color: var(--text);
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 14px;
    font-family: inherit;
    line-height: 1.6;
    resize: vertical;
    min-height: 100px;
    outline: none;
    transition: border-color 0.15s;
    box-sizing: border-box;
    margin-bottom: 14px;
}

.comment-field:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

/* ── Buttons ── */
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s;
    font-family: inherit;
    text-decoration: none;
}

.btn-primary { background: var(--accent); color: #fff; }
.btn-primary:hover { background: #1d4ed8; transform: translateY(-1px); }
.btn-warning { background: #f59e0b; color: #fff; }
.btn-warning:hover { background: #d97706; transform: translateY(-1px); }
.btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border); }
.btn-ghost:hover { background: var(--surface2); color: var(--text); }

.action-bar {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
/* ── Locked Banner ── */
.locked-banner {
    display: flex;
    align-items: center;
    gap: 14px;
    background: rgba(34, 197, 94, 0.08);
    border: 1px solid rgba(34, 197, 94, 0.35);
    border-left: 4px solid #22c55e;
    border-radius: 12px;
    padding: 18px 22px;
    margin-bottom: 20px;
}

.locked-banner .lock-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(34, 197, 94, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 18px;
}

.locked-banner .lock-title {
    font-size: 15px;
    font-weight: 700;
    color: #15803d;
    margin-bottom: 3px;
}

.locked-banner .lock-sub {
    font-size: 12px;
    color: var(--muted);
    line-height: 1.5;
}

.locked-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 28px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    opacity: 0.85;
}

/* Toast for lock message */
.toast-locked {
    position: fixed;
    top: 72px;
    right: 24px;
    background: #15803d;
    color: #fff;
    border-radius: 10px;
    padding: 12px 20px;
    font-size: 13px;
    font-weight: 600;
    z-index: 9999;
    box-shadow: 0 4px 16px rgba(0,0,0,0.2);
    display: none;
}
</style>
</head>
<body>
<?php include __DIR__ . '/../common/header.php'; ?>
<div class="dashboard">
    <?php include __DIR__ . '/../common/sidebar.php'; ?>
    <div class="content" style="padding:0;">

        <!-- Sticky Header Bar -->
        <div class="compose-header">
            <div class="compose-title-block">
                <h1>Moderate Exam</h1>
                <div class="meta"><?= htmlspecialchars(($exam['subject_name'] ?? 'N/A') . ' &bull; ' . ($exam['teacher_name'] ?? 'N/A')); ?></div>
            </div>
            <div class="header-stats">
                <span class="stat-pill info"><?= count($questions); ?> Questions</span>
                <span class="stat-pill"><?= $total_marks; ?> Total Marks</span>
                <span class="stat-pill success"><?= $easy; ?> Easy</span>
                <span class="stat-pill warning"><?= $medium; ?> Medium</span>
                <span class="stat-pill danger"><?= $hard; ?> Hard</span>
                <span class="stat-pill <?= $risk_level === 'High' ? 'danger' : ($risk_level === 'Medium' ? 'warning' : 'success'); ?>">
                    Risk: <?= $risk_level; ?>
                </span>
            </div>
            <div class="action-bar">
                <a href="moderation_exams.php" class="btn btn-ghost">Back to Exams</a>
            </div>
        </div>

        <!-- Main Two-Column Layout -->
        <div class="moderate-layout">
            <!-- LEFT: Question List -->
            <div class="q-panel">
                <div class="q-panel-header">Questions (<?= count($questions); ?>)</div>
                <div class="q-list">
                    <?php foreach ($questions as $index => $q): ?>
                    <div onclick="selectQuestion(<?= $q['question_id']; ?>)"
                         id="q-item-<?= $q['question_id']; ?>"
                         class="q-item <?= $index == 0 ? 'active' : ''; ?>">
                        <div class="q-item-top">
                            <span class="q-num">Q<?= $index + 1; ?></span>
                            <span class="q-status-dot <?= htmlspecialchars($q['moderation_status'] ?? ''); ?>"></span>
                        </div>
                        <div class="q-preview"><?= htmlspecialchars(mb_strimwidth($q['question_text'], 0, 90, '...')); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- RIGHT: Editor Panel -->
            <div class="editor-panel">
                <div class="editor-inner">

                <?php foreach ($questions as $index => $q): ?>
                <?php $ai = $ai_map[$q['question_id']] ?? []; ?>
                <div id="question-detail-<?= $q['question_id']; ?>"
                     class="question-detail"
                     style="display: <?= $index == 0 ? 'block' : 'none'; ?>">

                    <!-- Question Card -->
                    <div class="editor-card">
                        <div class="editor-card-header">
                            <div>
                                <h2 class="editor-card-title">Question <?= $index + 1; ?></h2>
                                <div style="font-size:12px;color:var(--muted);margin-top:2px;"><?= htmlspecialchars($q['section_name'] ?? 'General'); ?></div>
                            </div>
                            <div class="action-bar">
                                <span class="marks-badge"><?= htmlspecialchars($q['marks']); ?> Marks</span>
                                <a href="edit_question.php?question_id=<?= $q['question_id']; ?>" class="btn btn-warning">Edit Question</a>
                            </div>
                        </div>

                        <div class="question-body">
                            <?= nl2br(htmlspecialchars($q['question_text'])); ?>
                        </div>

                        <?php if ($q['option_a']): ?>
                        <div class="mcq-options">
                            <?php
                            $opts = ['A' => $q['option_a'], 'B' => $q['option_b'], 'C' => $q['option_c'], 'D' => $q['option_d']];
                            foreach ($opts as $lbl => $val): if (!$val) continue; ?>
                            <div class="mcq-opt">
                                <span class="opt-label"><?= $lbl; ?></span>
                                <?= htmlspecialchars($val); ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div>
                            <span class="correct-answer-tag">Correct: <?= htmlspecialchars($q['correct_option']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div><!-- /.editor-card -->

                    <?php if (!empty($ai)): ?>
                    <?php
                        $modRec = $ai['moderation_recommendation'] ?? [];
                        $dup = $ai['duplicate_detection'] ?? [];
                    ?>
                    <!-- AI Report Card -->
                    <div class="ai-report-card">
                        <div class="ai-report-header">
                            <span class="ai-tag">AI Analysis</span>
                            <h3 class="ai-report-title">Moderation Report</h3>
                        </div>

                        <div class="ai-metrics-grid">
                            <div class="ai-metric">
                                <div class="lbl">Quality Score</div>
                                <div class="val <?= ($ai['quality_score'] ?? 100) >= 70 ? 'success' : (($ai['quality_score'] ?? 100) >= 50 ? 'warning' : 'danger'); ?>"><?= htmlspecialchars($ai['quality_score'] ?? 0); ?>%</div>
                            </div>
                            <div class="ai-metric">
                                <div class="lbl">Difficulty</div>
                                <div class="val"><?= htmlspecialchars($ai['difficulty_level'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="ai-metric">
                                <div class="lbl">Bloom's Level</div>
                                <div class="val accent"><?= htmlspecialchars($ai['cognitive_level'] ?? $ai['bloom_level'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="ai-metric">
                                <div class="lbl">Recommendation</div>
                                <div class="val <?= strtolower($modRec['label'] ?? '') === 'approve' ? 'success' : 'warning'; ?>"><?= htmlspecialchars($modRec['label'] ?? 'Review'); ?></div>
                            </div>
                        </div>

                        <?php if (!empty($modRec['reason'])): ?>
                        <div class="ai-section">
                            <p class="ai-section-title">Recommendation Reason</p>
                            <div class="recommendation-box"><?= htmlspecialchars($modRec['reason']); ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($ai['grammar_corrections'])): ?>
                        <div class="ai-section">
                            <p class="ai-section-title">Grammar Issues</p>
                            <?php foreach ($ai['grammar_corrections'] as $item): ?>
                            <div class="feedback-item grammar"><?= htmlspecialchars($item); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($ai['ambiguities'])): ?>
                        <div class="ai-section">
                            <p class="ai-section-title">Ambiguity Warnings</p>
                            <?php foreach ($ai['ambiguities'] as $item): ?>
                            <div class="feedback-item ambiguity">Ambiguous term: <?= htmlspecialchars($item); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($dup['is_duplicate']) || ($dup['similarity_score'] ?? 0) >= 70): ?>
                        <div class="ai-section">
                            <p class="ai-section-title">Duplicate Detection</p>
                            <div class="feedback-item duplicate">
                                Similarity: <?= htmlspecialchars($dup['similarity_score'] ?? 0); ?>%
                                <?php if (!empty($dup['matched_question'])): ?>
                                &mdash; matched: "<?= htmlspecialchars($dup['matched_question']); ?>"
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($ai['suggested_question']) && $ai['suggested_question'] !== $q['question_text']): ?>
                        <div class="ai-section">
                            <p class="ai-section-title">Suggested Correction</p>
                            <div class="feedback-item suggest"><?= nl2br(htmlspecialchars($ai['suggested_question'])); ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($ai['recommendations'])): ?>
                        <div class="ai-section">
                            <p class="ai-section-title">AI Recommendations</p>
                            <?php foreach ($ai['recommendations'] as $item): ?>
                            <div class="feedback-item"><?= htmlspecialchars($item); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($ai['warnings'])): ?>
                        <div class="ai-section">
                            <p class="ai-section-title">Warnings</p>
                            <?php foreach ($ai['warnings'] as $item): ?>
                            <div class="feedback-item warning"><?= htmlspecialchars($item); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Moderation Decision Card -->
                    <?php if (($q['moderation_status'] ?? '') === 'approved'): ?>
                    <!-- LOCKED: Question already approved -->
                    <div class="locked-banner">
                        <div class="lock-icon">&#10003;</div>
                        <div>
                            <div class="lock-title">Moderation Complete &mdash; Approved</div>
                            <div class="lock-sub">
                                This question has passed moderation and is ready for distribution.
                                <?php if (!empty($q['moderator_comment'])): ?>
                                Moderator note: <?= htmlspecialchars($q['moderator_comment']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <form method="POST" action="moderate_exam.php?exam_id=<?= $exam_id; ?>">
                        <input type="hidden" name="question_id" value="<?= $q['question_id']; ?>">

                        <div class="moderation-card">
                            <p class="moderation-card-title">Moderation Decision</p>

                            <div class="choice-grid">
                                <label class="choice approve">
                                    <input type="radio" name="status" value="approved"
                                        <?= ($q['moderation_status'] === 'approved') ? 'checked' : '' ?> >
                                    <span>Approve</span>
                                </label>
                                <label class="choice revise">
                                    <input type="radio" name="status" value="revise"
                                        <?= ($q['moderation_status'] === 'revise') ? 'checked' : '' ?> >
                                    <span>Revise</span>
                                </label>
                                <label class="choice reject">
                                    <input type="radio" name="status" value="rejected"
                                        <?= ($q['moderation_status'] === 'rejected') ? 'checked' : '' ?> >
                                    <span>Reject</span>
                                </label>
                            </div>

                            <textarea
                                class="comment-field"
                                name="comment"
                                placeholder="Write moderation feedback or notes..."
                            ><?= htmlspecialchars($q['moderator_comment'] ?? '') ?></textarea>

                            <button type="submit" class="btn btn-primary">Save Moderation Decision</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div><!-- /.question-detail -->
                <?php endforeach; ?>

                </div><!-- /.editor-inner -->
            </div><!-- /.editor-panel -->
        </div><!-- /.moderate-layout -->

    </div><!-- /.content -->
</div><!-- /.dashboard -->
<div id="toastLocked" class="toast-locked">This question is already approved and locked.</div>
<script>
function selectQuestion(id) {
    document.querySelectorAll('.question-detail').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.q-item').forEach(el => el.classList.remove('active'));
    document.getElementById('question-detail-' + id).style.display = 'block';
    document.getElementById('q-item-' + id).classList.add('active');
}
window.onload = function() {
    const first = <?= !empty($questions) ? $questions[0]['question_id'] : 0; ?>;
    if (first) {
        selectQuestion(first);
    }
    // Show toast if redirected after attempted re-moderation of locked question
    <?php if (!empty($_GET['locked'])): ?>
    const toast = document.getElementById('toastLocked');
    toast.style.display = 'block';
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.5s'; setTimeout(() => toast.style.display = 'none', 500); }, 3000);
    <?php endif; ?>
}
</script>
<?php include __DIR__ . '/../common/footer.php'; ?>