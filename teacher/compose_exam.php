<?php
require_once __DIR__ . '/teacher_init.php';
require_once __DIR__ . '/../services/ai/compose_bridge.php';

@set_time_limit(600);

$exam_id         = isset($_GET['exam_id'])         ? (int)$_GET['exam_id']         : (int)($_POST['exam_id']         ?? 0);
$subject_id      = isset($_GET['subject_id'])      ? (int)$_GET['subject_id']      : (int)($_POST['subject_id']      ?? 0);
$exam_subject_id = isset($_GET['exam_subject_id']) ? (int)$_GET['exam_subject_id'] : (int)($_POST['exam_subject_id'] ?? 0);

if ($exam_id <= 0) {
    header('Location: assigned_exams.php');
    exit();
}

$conn = get_db_connection();

// ── POST handler ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = trim($_POST['action'] ?? '');

    // Check if the exam is locked for composing
    $lock_stmt = $conn->prepare("SELECT status FROM exams WHERE exam_id = ? LIMIT 1");
    $lock_stmt->bind_param('i', $exam_id);
    $lock_stmt->execute();
    $lock_exam = $lock_stmt->get_result()->fetch_assoc();
    $lock_stmt->close();
    $exam_status = $lock_exam['status'] ?? 'draft';
    $is_exam_locked = in_array($exam_status, ['submitted', 'under_moderation', 'approved']);

    if ($is_exam_locked && in_array($action, ['save', 'delete'])) {
        echo json_encode(['success' => false, 'error' => 'This exam is currently locked (submitted or approved) and cannot be modified.']);
        $conn->close(); exit;
    }

    if ($action === 'submit_exam') {
        $stmt = $conn->prepare("UPDATE exams SET status = 'submitted' WHERE exam_id = ?");
        $stmt->bind_param('i', $exam_id);
        $stmt->execute();
        $success = $stmt->affected_rows > 0;
        $stmt->close();
        
        if ($success) {
            log_audit_event('EXAM_SUBMITTED_FOR_MODERATION', ['exam_id' => $exam_id], null, $conn);
        }
        echo json_encode(['success' => $success]);
        $conn->close(); exit;
    }

    if ($action === 'preview') {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $question_text   = trim($_POST['question_text'] ?? '');
        $marks           = (int)($_POST['marks'] ?? 0);
        $esi_preview     = (int)($_POST['exam_subject_id'] ?? $exam_subject_id);

        if (!$question_text || $marks <= 0) {
            echo json_encode(['success' => false, 'error' => 'Question text and marks are required.']);
            $conn->close(); exit;
        }

        $existing = [];
        if ($esi_preview > 0) {
            $stmt = $conn->prepare('SELECT question_text FROM questions WHERE exam_subject_id = ?');
            $stmt->bind_param('i', $esi_preview);
        } else {
            $stmt = $conn->prepare('SELECT question_text FROM questions WHERE exam_id = ?');
            $stmt->bind_param('i', $exam_id);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $existing[] = $row['question_text'];
        $stmt->close();

        $ai = analyze_question_for_teacher($question_text, $marks, $existing);
        echo json_encode(['success' => ($ai['status'] ?? '') !== 'failed', 'ai' => $ai]);
        $conn->close(); exit;
    }

    if ($action === 'save') {
        $ai_data = json_decode($_POST['ai_data'] ?? '{}', true);
        if (!is_array($ai_data)) $ai_data = [];
        $result = compose_save_question($conn, $user_id, $_POST, $ai_data);
        if ($result['success'] ?? false) {
            log_audit_event('QUESTION_SAVED', ['exam_id' => $exam_id, 'question_id' => $result['question_id'] ?? 0], null, $conn);
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        $conn->close(); exit;
    }

    if ($action === 'delete') {
        $qid = (int)($_POST['question_id'] ?? 0);
        $esi_del = (int)($_POST['exam_subject_id'] ?? $exam_subject_id);
        if ($qid > 0 && compose_teacher_can_write_exam($conn, $exam_id, $user_id, $subject_id)) {
            if ($esi_del > 0) {
                $stmt = $conn->prepare('DELETE FROM questions WHERE question_id = ? AND exam_subject_id = ?');
                $stmt->bind_param('ii', $qid, $esi_del);
            } else {
                $stmt = $conn->prepare('DELETE FROM questions WHERE question_id = ? AND exam_id = ?');
                $stmt->bind_param('ii', $qid, $exam_id);
            }
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                log_audit_event('QUESTION_DELETED', ['exam_id' => $exam_id, 'subject_id' => $subject_id, 'question_id' => $qid], null, $conn);
            }
            echo json_encode(['success' => $stmt->affected_rows > 0]);
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
        }
        $conn->close(); exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    $conn->close(); exit;
}

// ── Page load ────────────────────────────────────────────────
// Allow admin/headteacher to view any exam; teachers need item_writer role
$is_admin = false;
$user_row_stmt = $conn->prepare('SELECT role FROM users WHERE user_id = ? LIMIT 1');
$user_row_stmt->bind_param('i', $user_id);
$user_row_stmt->execute();
$user_row = $user_row_stmt->get_result()->fetch_assoc();
$user_row_stmt->close();
if ($user_row && in_array($user_row['role'], ['admin', 'headteacher', 'examination_officer'])) {
    $is_admin = true;
}

// Load exam details — scope to subject if subject_id is given
if ($is_admin) {
    if ($subject_id > 0) {
        $stmt = $conn->prepare("
            SELECT e.*, es.id AS exam_subject_id, es.subject_id, s.subject_name
            FROM exams e
            INNER JOIN exam_subjects es ON e.exam_id = es.exam_id AND es.subject_id = ?
            INNER JOIN subjects s ON es.subject_id = s.subject_id
            WHERE e.exam_id = ? LIMIT 1
        ");
        $stmt->bind_param('ii', $subject_id, $exam_id);
    } else {
        $stmt = $conn->prepare("SELECT e.*, es.id AS exam_subject_id, es.subject_id, s.subject_name FROM exams e LEFT JOIN exam_subjects es ON e.exam_id = es.exam_id LEFT JOIN subjects s ON es.subject_id = s.subject_id WHERE e.exam_id = ? LIMIT 1");
        $stmt->bind_param('i', $exam_id);
    }
} else {
    $stmt = $conn->prepare("
        SELECT e.*, es.id AS exam_subject_id, es.subject_id, s.subject_name
        FROM subject_assignments sa
        INNER JOIN exam_subjects es ON sa.subject_id = es.subject_id
        INNER JOIN exams e ON es.exam_id = e.exam_id
        INNER JOIN subjects s ON es.subject_id = s.subject_id
        WHERE e.exam_id = ? AND sa.teacher_id = ? AND sa.role = 'item_writer'
          AND (? = 0 OR es.subject_id = ?)
        LIMIT 1
    ");
    $stmt->bind_param('iiii', $exam_id, $user_id, $subject_id, $subject_id);
}
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) {
    $conn->close();
    // Show friendly error instead of silent redirect
    $portal_title = 'NED-SEMS | Teacher Portal';
    include __DIR__ . '/../common/head_assets.php';
    echo '<div style="display:flex;align-items:center;justify-content:center;min-height:80vh;font-family:sans-serif;">
        <div style="text-align:center;padding:40px;">
            <h2>Access Denied</h2>
            <p style="color:#64748b;">You are not assigned as an item writer for this exam,<br>or the exam does not exist.</p>
            <a href="assigned_exams.php" style="background:#2563eb;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;">View My Exams</a>
        </div>
    </div>';
    exit();
}

// Resolve exam_subject_id from the loaded exam if still 0
if ($exam_subject_id <= 0 && isset($exam['exam_subject_id'])) {
    $exam_subject_id = (int)$exam['exam_subject_id'];
}
if ($subject_id <= 0 && isset($exam['subject_id'])) {
    $subject_id = (int)$exam['subject_id'];
}

// Load questions — scope to exam_subject_id for subject-based filtering
if ($exam_subject_id > 0) {
    $stmt = $conn->prepare('SELECT * FROM questions WHERE exam_subject_id = ? ORDER BY section_name ASC, question_order ASC, question_id ASC');
    $stmt->bind_param('i', $exam_subject_id);
} else {
    $stmt = $conn->prepare('SELECT * FROM questions WHERE exam_id = ? ORDER BY section_name ASC, question_order ASC, question_id ASC');
    $stmt->bind_param('i', $exam_id);
}
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$ai_map = compose_build_ai_map($conn, $questions, $exam_id, $exam_subject_id);
$conn->close();

$exam_status = $exam['status'] ?? 'draft';
$is_exam_locked = in_array($exam_status, ['submitted', 'under_moderation', 'approved']);

// Group questions by section
$sections = [];
foreach ($questions as $q) {
    $sec = $q['section_name'] ?: 'Unsectioned';
    $sections[$sec][] = $q;
}

$total_marks = array_sum(array_column($questions, 'marks'));
$approved_count = count(array_filter($questions, fn($q) => ($q['moderation_status'] ?? '') === 'approved'));
$revise_count = count(array_filter($questions, fn($q) => ($q['moderation_status'] ?? '') === 'revise'));
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Compose Exam — <?= htmlspecialchars($exam['exam_name']); ?></title>
<meta name="description" content="Compose and moderate exam questions with AI assistance.">
<?php
$portal_title = 'NED-SEMS | Compose Exam';
$module_css = 'teacher';
include __DIR__ . '/../common/head_assets.php';
?>

<style>
/* ══════════════════════════════════════════════════════════
   COMPOSE EXAM — Premium UI
══════════════════════════════════════════════════════════ */
:root {
    --bg: var(--background-color, #f4f6f8);
    --surface: var(--card-color, #ffffff);
    --surface2: #f1f5f9;
    --border: var(--border-color, #e2e8f0);
    --accent: #2563eb;
    --accent2: #4f46e5;
    --success: var(--success-color, #22c55e);
    --warning: var(--warning-color, #facc15);
    --danger: var(--danger-color, #ef4444);
    --text: var(--text-color, #1e293b);
    --muted: var(--text-muted, #64748b);
    --card-shadow: var(--box-shadow, 0 8px 20px rgba(0, 0, 0, 0.06));
}

/* ── Header Bar ── */
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
    backdrop-filter: blur(12px);
}

.compose-title-block h1 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text);
    line-height: 1.2;
}

.compose-title-block .meta {
    font-size: 12px;
    color: var(--muted);
    margin-top: 2px;
}

.header-stats {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.stat-pill {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 4px 12px;
    font-size: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
}

.stat-pill.success { border-color: var(--success); color: var(--success); }
.stat-pill.warning { border-color: var(--warning); color: #b45309; }
.stat-pill.danger  { border-color: var(--danger);  color: var(--danger);  }

.header-actions { display: flex; gap: 8px; }

/* ── Layout ── */
.compose-layout {
    display: grid;
    grid-template-columns: 280px 1fr 320px;
    gap: 0;
    height: calc(100vh - 57px);
    overflow: hidden;
}

/* ── Left: Question List Panel ── */
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
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.q-list {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
}

.q-list::-webkit-scrollbar { width: 4px; }
.q-list::-webkit-scrollbar-track { background: transparent; }
.q-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

.q-section-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--muted);
    padding: 10px 8px 6px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 4px;
    margin-top: 8px;
}

.q-item {
    padding: 10px 12px;
    border-radius: 10px;
    cursor: pointer;
    margin-bottom: 4px;
    border: 1px solid transparent;
    transition: all 0.15s ease;
    position: relative;
}

.q-item:hover { background: var(--surface2); }
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

.q-num {
    font-size: 13px;
    font-weight: 700;
    color: var(--text);
}

.q-preview {
    font-size: 11px;
    color: var(--muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 180px;
}

.add-new-btn {
    margin: 10px;
    padding: 10px;
    background: rgba(37, 99, 235, 0.06);
    border: 1px dashed var(--accent);
    border-radius: 10px;
    text-align: center;
    font-size: 13px;
    font-weight: 600;
    color: var(--accent);
    cursor: pointer;
    transition: all 0.15s;
}
.add-new-btn:hover { background: rgba(37, 99, 235, 0.12); }
.add-new-btn.active { background: rgba(37, 99, 235, 0.18); border-style: solid; }

/* ── Center: Editor Panel ── */
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

.editor-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    max-width: 720px;
    margin: 0 auto;
}

.editor-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border);
}

.editor-card-title {
    font-size: 18px;
    font-weight: 700;
}

/* Form fields */
.field-group {
    margin-bottom: 18px;
}

.field-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--muted);
    margin-bottom: 6px;
}

.field-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 12px;
}

.compose-layout input[type="text"],
.compose-layout input[type="number"],
.compose-layout textarea,
.compose-layout select {
    width: 100%;
    background: var(--surface2);
    border: 1px solid var(--border);
    color: var(--text);
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 14px;
    font-family: inherit;
    transition: border-color 0.15s;
    outline: none;
}

.compose-layout input:focus, 
.compose-layout textarea:focus, 
.compose-layout select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

.compose-layout textarea { resize: vertical; min-height: 110px; line-height: 1.6; }

.compose-layout select option { background: var(--surface); }

/* MCQ Box */
.mcq-box {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    margin-top: 12px;
    display: none;
}

.mcq-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; }
.mcq-option { display: flex; align-items: center; gap: 8px; }
.mcq-option .opt-label {
    background: var(--accent);
    color: #fff;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    flex-shrink: 0;
}

/* Moderator Alert */
.mod-alert {
    display: none;
    padding: 12px 16px;
    border-radius: 10px;
    margin-bottom: 16px;
    font-size: 13px;
    line-height: 1.5;
    border-left: 4px solid;
}

.mod-alert.revise   { background: rgba(245,158,11,0.08);  border-color: var(--warning); color: #b45309; }
.mod-alert.rejected { background: rgba(239,68,68,0.08);   border-color: var(--danger);  color: #b91c1c; }
.mod-alert.approved { background: rgba(16,185,129,0.08);  border-color: var(--success); color: #047857; }

/* Action buttons */
.action-bar {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
    flex-wrap: wrap;
    align-items: center;
}

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

.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary { background: var(--accent); color: #fff; }
.btn-primary:hover:not(:disabled) { background: #1d4ed8; transform: translateY(-1px); }
.btn-success { background: var(--success); color: #fff; }
.btn-success:hover:not(:disabled) { background: #15803d; transform: translateY(-1px); }
.btn-danger  { background: rgba(239,68,68,0.1); color: var(--danger); border: 1px solid var(--danger); }
.btn-danger:hover:not(:disabled)  { background: rgba(239,68,68,0.2); }
.btn-ghost   { background: transparent; color: var(--muted); border: 1px solid var(--border); }
.btn-ghost:hover   { background: var(--surface2); color: var(--text); }
.btn-outline { background: transparent; color: var(--text); border: 1px solid var(--border); }
.btn-outline:hover { background: var(--surface2); }

/* ── Right: AI Panel ── */
.ai-panel {
    background: var(--surface);
    border-left: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.ai-panel-header {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.ai-panel-body {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
}

.ai-panel-body::-webkit-scrollbar { width: 4px; }
.ai-panel-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

/* AI Score ring */
.score-ring-wrap {
    text-align: center;
    margin-bottom: 16px;
    padding: 16px;
    background: var(--surface2);
    border-radius: 12px;
    border: 1px solid var(--border);
}

.score-ring {
    width: 90px;
    height: 90px;
    margin: 0 auto 10px;
    position: relative;
}

.score-ring svg { transform: rotate(-90deg); }
.score-ring-bg   { fill: none; stroke: var(--border); stroke-width: 8; }
.score-ring-fill { fill: none; stroke-width: 8; stroke-linecap: round; transition: stroke-dashoffset 0.6s ease; }
.score-ring-text {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 800;
}

.score-label { font-size: 11px; color: var(--muted); font-weight: 600; }

/* AI Metric pills */
.ai-metrics { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 16px; }
.ai-metric {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px;
}
.ai-metric .lbl { font-size: 10px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
.ai-metric .val { font-size: 13px; font-weight: 700; }

/* AI recommendation list */
.ai-section { margin-bottom: 16px; }
.ai-section h4 { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); margin-bottom: 8px; }

.ai-list { list-style: none; padding: 0; }
.ai-list li {
    font-size: 12px;
    color: var(--text);
    padding: 6px 0 6px 16px;
    border-bottom: 1px solid var(--border);
    position: relative;
    line-height: 1.4;
}
.ai-list li:last-child { border-bottom: none; }
.ai-list li::before {
    content: '›';
    position: absolute;
    left: 0;
    color: var(--accent);
    font-weight: 700;
}

.ai-warning-tag {
    background: rgba(239,68,68,0.06);
    border: 1px solid rgba(239,68,68,0.2);
    color: #b91c1c;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 11px;
    margin-bottom: 8px;
    line-height: 1.4;
}

.ai-suggestion-box {
    background: rgba(37,99,235,0.05);
    border: 1px solid rgba(37,99,235,0.2);
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 12px;
    display: none;
}

.ai-suggestion-box .sug-label { font-size: 10px; color: var(--accent); font-weight: 700; margin-bottom: 6px; text-transform: uppercase; }
.ai-suggestion-box p { font-size: 12px; font-style: italic; line-height: 1.5; color: var(--text); margin-bottom: 8px; }

.ai-empty {
    text-align: center;
    padding: 40px 20px;
}
.ai-empty .ai-icon {
    width: 48px;
    height: 48px;
    margin: 0 auto 16px;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%233b82f6'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M13 10V3L4 14h7v7l9-11h-7z'/%3E%3C/svg%3E") no-repeat center;
    background-size: contain;
}

.ai-empty p {
    font-size: 13px;
    color: var(--muted);
    line-height: 1.5;
}

@media (max-width: 1024px) {
    .compose-layout { grid-template-columns: 1fr; grid-template-rows: auto 1fr; }
    .q-panel { max-height: 200px; border-right: none; border-bottom: 1px solid var(--border); }
}

/* ── Approved Lock Banner ── */
.approved-lock-banner {
    display: none;
    align-items: center;
    gap: 14px;
    background: rgba(34, 197, 94, 0.08);
    border: 1px solid rgba(34, 197, 94, 0.35);
    border-left: 4px solid #22c55e;
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 18px;
}
.approved-lock-banner.visible { display: flex; }
.approved-lock-banner .lock-badge {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: rgba(34, 197, 94, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    font-weight: 800;
    color: #15803d;
    flex-shrink: 0;
}
.approved-lock-banner .lock-title {
    font-size: 14px;
    font-weight: 700;
    color: #15803d;
}
.approved-lock-banner .lock-sub {
    font-size: 12px;
    color: var(--muted);
    margin-top: 2px;
}
</style>
</head>
<body>

<?php include __DIR__ . '/../common/header.php'; ?>
<div class="dashboard">
<?php include __DIR__ . '/../common/sidebar.php'; ?>

<div class="main-content" style="padding:0; display:flex; flex-direction:column; height:calc(100vh - 60px); overflow:hidden;">

<!-- Header Bar -->
<div class="compose-header">
    <div class="compose-title-block">
        <h1><?= htmlspecialchars($exam['exam_name']); ?></h1>
        <div class="meta">
            <?= htmlspecialchars($exam['subject_name'] ?? 'N/A'); ?> &nbsp;·&nbsp;
            <?= htmlspecialchars($exam['year'] ?? ''); ?> &nbsp;·&nbsp;
            Form <?= htmlspecialchars($exam['class'] ?? ''); ?> &nbsp;·&nbsp;
            <?= count($questions); ?> question<?= count($questions) !== 1 ? 's' : ''; ?> &nbsp;·&nbsp;
            <?= $total_marks; ?> total marks
        </div>
    </div>

    <div class="header-stats">
        <?php if ($approved_count > 0): ?>
        <span class="stat-pill success"><?= $approved_count; ?> Approved</span>
        <?php endif; ?>
       
    </div>

    <div class="header-actions">
        <a href="exam.php?id=<?= $exam_id ?>&subject_id=<?= $subject_id ?>&page=cover" target="_blank" class="btn btn-outline" style="font-size:12px; padding:8px 14px;">
            Preview
        </a>
        <?php if (count($questions) > 0): ?>
            <a href="exam.php?action=download&id=<?= $exam_id ?>&subject_id=<?= $subject_id ?>" class="btn btn-success" style="font-size:12px; padding:8px 14px;">
                Download PDF
            </a>
            <?php if (in_array($exam['status'], ['draft', 'assigned'])): ?>
                <button type="button" class="btn btn-primary" onclick="submitExamForModeration()" style="font-size:12px; padding:8px 14px;">
                    Submit for Moderation
                </button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Main Layout -->
<div class="compose-layout">

    <!-- LEFT: Question List -->
    <div class="q-panel">
        <div class="q-panel-header">
            <span>QUESTIONS</span>
            <span style="color: var(--accent);"><?= count($questions); ?></span>
        </div>

        <div class="add-new-btn active" id="addNewBtn" onclick="loadNew()">
            + Add New Question
        </div>

        <div class="q-list" id="qList">
            <?php if (empty($questions)): ?>
                <div style="text-align:center; padding:30px 10px; color:var(--muted); font-size:12px;">
                    No questions yet.<br>Start composing!
                </div>
            <?php else: ?>
                <?php
                $secLabels = [];
                foreach ($questions as $q):
                    $sec = $q['section_name'] ?: 'Unsectioned';
                    $statusCls = match($q['moderation_status'] ?? '') {
                        'approved' => 'badge-approved',
                        'revise'   => 'badge-revise',
                        'rejected' => 'badge-rejected',
                        default    => 'badge-pending',
                    };
                    if (!in_array($sec, $secLabels)):
                        $secLabels[] = $sec;
                ?>
                <div class="q-section-label"><?= htmlspecialchars($sec); ?></div>
                <?php endif; ?>
                <div class="q-item" data-id="<?= (int)$q['question_id']; ?>" onclick="loadQuestion(<?= (int)$q['question_id']; ?>)">
                    <div class="q-item-top">
                        <span class="q-num">Q<?= (int)($q['question_order'] ?: $q['question_id']); ?> <small style="color:var(--muted); font-weight:400;">(<?= (int)$q['marks']; ?>mk)</small></span>
                        <span class="badge <?= $statusCls; ?>"><?= ucfirst($q['moderation_status'] ?? 'pending'); ?></span>
                    </div>
                    <div class="q-preview"><?= htmlspecialchars(mb_substr($q['question_text'], 0, 50)); ?>…</div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- CENTER: Editor -->
    <div class="editor-panel">
        <div class="editor-inner">
            <div class="editor-card">
                <div class="editor-card-header">
                    <span class="editor-card-title" id="editorTitle">Add Question</span>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <span id="editorBadge" class="badge" style="display:none;"></span>
                        <button type="button" class="btn btn-danger" id="btnDelete" onclick="deleteQuestion()" style="display:none; padding:6px 12px; font-size:12px;">
                            Delete
                        </button>
                    </div>
                </div>

                <!-- Approved Lock Banner -->
                <div class="approved-lock-banner" id="approvedLockBanner">
                    <div class="lock-badge">&#10003;</div>
                    <div>
                        <div class="lock-title">Approved &amp; Locked</div>
                        <div class="lock-sub">This question has been approved by the moderator and cannot be edited.</div>
                    </div>
                </div>

                <!-- Moderator Alert -->
                <div class="mod-alert" id="modAlert">
                    <strong id="modAlertTitle">Moderator Comment</strong>
                    <div id="modAlertBody" style="margin-top:4px;"></div>
                </div>

                <form id="questionForm">
                    <input type="hidden" name="exam_id" id="exam_id" value="<?= $exam_id; ?>">
                    <input type="hidden" name="subject_id" id="subject_id" value="<?= $subject_id; ?>">
                    <input type="hidden" name="exam_subject_id" id="exam_subject_id" value="<?= $exam_subject_id; ?>">
                    <input type="hidden" name="question_id" id="question_id" value="">

                    <div class="field-grid">
                        <div class="field-group">
                            <label>Section</label>
                            <select name="section_name" id="section_name" onchange="toggleMCQ()">
                                <option value="">-- Select --</option>
                                <option value="Section A">Section A (MCQ)</option>
                                <option value="Section B">Section B (Structured)</option>
                                <option value="Section C">Section C (Essay)</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>Question #</label>
                            <input type="number" name="order" id="order" min="1" value="<?= count($questions) + 1; ?>">
                        </div>
                        <div class="field-group">
                            <label>Marks</label>
                            <input type="number" name="marks" id="marks" min="1" required placeholder="e.g. 5">
                        </div>
                    </div>

                    <div class="field-group">
                        <label>Question Text</label>
                        <textarea name="question_text" id="question_text" required placeholder="Type your exam question here...&#10;&#10;Tip: Start with an action verb like 'Explain', 'Calculate', 'Compare'..."></textarea>
                    </div>

                    <!-- MCQ Options (shown for Section A) -->
                    <div class="mcq-box" id="mcqBox">
                        <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--muted); margin-bottom:12px;">MCQ Options</div>
                        <div class="mcq-grid">
                            <div class="mcq-option">
                                <div class="opt-label">A</div>
                                <input type="text" name="option_a" id="option_a" placeholder="Option A">
                            </div>
                            <div class="mcq-option">
                                <div class="opt-label">B</div>
                                <input type="text" name="option_b" id="option_b" placeholder="Option B">
                            </div>
                            <div class="mcq-option">
                                <div class="opt-label">C</div>
                                <input type="text" name="option_c" id="option_c" placeholder="Option C">
                            </div>
                            <div class="mcq-option">
                                <div class="opt-label">D</div>
                                <input type="text" name="option_d" id="option_d" placeholder="Option D">
                            </div>
                        </div>
                        <div class="field-group" style="margin:0;">
                            <label>Correct Answer</label>
                            <select name="correct_option" id="correct_option">
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                            </select>
                        </div>
                    </div>

                    <div class="action-bar">
                        <button type="button" class="btn btn-primary" id="btnAnalyze" onclick="runAIAnalysis()">
                            <span id="aiSpinner" class="spinner"></span>
                            <span id="btnAnalyzeText">Analyze with AI</span>
                        </button>
                        <button type="button" class="btn btn-success" id="btnSave" onclick="directSave()">
                            <span id="saveSpinner" class="spinner"></span>
                            <span id="btnSaveText">Save Question</span>
                        </button>
                        <button type="button" class="btn btn-ghost" onclick="loadNew()" style="margin-left:auto;">
                            + New
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- RIGHT: AI Panel -->
    <div class="ai-panel">
        <div class="ai-panel-header">
            <span></span>
            <span>AI Analysis</span>
            <span id="aiStatusDot" style="margin-left:auto; width:8px; height:8px; border-radius:50%; background:var(--border);"></span>
        </div>

        <div class="ai-panel-body">

            <!-- Empty State -->
            <div class="ai-empty" id="aiEmpty">
                <div class="ai-icon"></div>
                <p>Click <strong>Analyze with AI</strong> to get instant feedback on your question's quality, Bloom level, and recommendations.</p>
                <div style="background:rgba(79,110,247,0.08); border:1px solid rgba(79,110,247,0.2); border-radius:8px; padding:10px; margin-top:16px; font-size:11px; color:var(--muted); line-height:1.6;">
                    <strong style="color:var(--accent);">Pro Tip:</strong> You can also save directly — AI analysis runs automatically in the background.
                </div>
            </div>

            <!-- Analysis Content -->
            <div id="aiContent" style="display:none;">

                <!-- Score Ring -->
                <div class="score-ring-wrap">
                    <div class="score-ring">
                        <svg width="90" height="90" viewBox="0 0 90 90">
                            <circle class="score-ring-bg" cx="45" cy="45" r="38"/>
                            <circle class="score-ring-fill" id="scoreCircle" cx="45" cy="45" r="38"
                                    stroke-dasharray="239" stroke-dashoffset="239" stroke="var(--accent)"/>
                        </svg>
                        <div class="score-ring-text">
                            <span id="scoreNum" style="color:var(--accent);">0%</span>
                        </div>
                    </div>
                    <div class="score-label">Quality Score</div>
                    <div id="aiStatusLabel" style="font-size:11px; color:var(--muted); margin-top:4px;"></div>
                </div>

                <!-- Metrics Grid -->
                <div class="ai-metrics">
                    <div class="ai-metric">
                        <div class="lbl" style="color: black" >Bloom Level</div>
                        <div class="val" id="aiBloom" style="color: black">—</div>
                    </div>
                    <div class="ai-metric">
                        <div class="lbl" style="color: black">Difficulty</div>
                        <div class="val" id="aiDifficulty" style="color: black">—</div>
                    </div>
                    <div class="ai-metric">
                        <div class="lbl" style="color: black">Cognitive</div>
                        <div class="val" id="aiCognitive" style="color: black">—</div>
                    </div>
                    <div class="ai-metric">
                        <div class="lbl" style="color: black">Topic</div>
                        <div class="val" id="aiTopic" style="font-size:11px; overflow:hidden;color: black; text-overflow:ellipsis; white-space:nowrap;">—</div>
                    </div>
                </div>

                <!-- Suggested Version -->
                <div class="ai-suggestion-box" id="aiSuggestionBox">
                    <div class="sug-label">AI Suggested Version</div>
                    <p id="aiSuggestionText"></p>
                    <button class="btn btn-ghost" style="font-size:11px; padding:5px 10px;" onclick="applySuggestion()">Apply Suggestion</button>
                </div>

                <!-- Warnings -->
                <div id="aiWarningsWrap"></div>

                <!-- Recommendations -->
                <div class="ai-section" id="aiRecsSection">
                    <h4>Recommendations</h4>
                    <ul class="ai-list" id="aiRecs"></ul>
                </div>

                <!-- Grammar -->
                <div class="ai-section" id="aiGrammarSection">
                    <h4>Grammar / Style</h4>
                    <ul class="ai-list" id="aiGrammar"></ul>
                </div>

                <!-- Moderation Decision -->
                <div id="aiModDecision" style="margin-top:16px; padding:12px; border-radius:10px; font-size:12px; line-height:1.5; display:none;"></div>

            </div>
        </div>
    </div>

</div><!-- /compose-layout -->
</div><!-- /main-content -->
</div><!-- /dashboard -->

<!-- Toast container -->
<div id="toast"></div>

<script>
// ── Data from PHP ──────────────────────────────────────────
const examQuestions = <?= json_encode($questions, JSON_UNESCAPED_UNICODE); ?>;
const aiMap = <?= json_encode($ai_map, JSON_UNESCAPED_UNICODE); ?>;
const composeUrl = 'compose_exam.php?exam_id=<?= $exam_id; ?>&subject_id=<?= $subject_id; ?>&exam_subject_id=<?= $exam_subject_id; ?>';
const isExamLocked = <?= $is_exam_locked ? 'true' : 'false'; ?>;

let lastAI = null;
let analyzing = false;
let saving = false;
let activeQId = 0;

// ── Toast ──────────────────────────────────────────────────
function toast(msg, type = 'info', duration = 4000) {
    const el = document.createElement('div');
    el.className = 'toast-msg ' + type;
    el.innerHTML = msg;
    document.getElementById('toast').appendChild(el);
    setTimeout(() => el.remove(), duration);
}

// ── Toggle MCQ ─────────────────────────────────────────────
function toggleMCQ() {
    const sec = document.getElementById('section_name').value;
    document.getElementById('mcqBox').style.display = sec === 'Section A' ? 'block' : 'none';
}

// ── Submit Exam for Moderation ─────────────────────────────
function submitExamForModeration() {
    if (!confirm("Are you sure you want to submit this exam for moderation? Once submitted, you will not be able to edit it until it is reviewed.")) {
        return;
    }
    postCompose('submit_exam')
        .then(res => {
            if (res.success) {
                alert('Exam submitted successfully for moderation!');
                window.location.href = 'assigned_exams.php';
            } else {
                alert(res.error || 'Submission failed.');
            }
        })
        .catch(err => alert('Submission failed: ' + err.message));
}

// ── Load New ───────────────────────────────────────────────
function loadNew() {
    activeQId = 0;
    lastAI = null;

    document.getElementById('questionForm').reset();
    document.getElementById('exam_id').value = <?= $exam_id; ?>;
    document.getElementById('subject_id').value = <?= $subject_id; ?>;
    document.getElementById('exam_subject_id').value = <?= $exam_subject_id; ?>;
    document.getElementById('order').value = examQuestions.length + 1;
    document.getElementById('question_id').value = '';
    document.getElementById('editorTitle').textContent = 'Add Question';
    document.getElementById('editorBadge').style.display = 'none';
    document.getElementById('btnDelete').style.display = 'none';
    document.getElementById('modAlert').style.display = 'none';
    document.getElementById('mcqBox').style.display = 'none';

    // Reset lock state
    const lockBanner = document.getElementById('approvedLockBanner');
    const formEl = document.getElementById('questionForm');
    const allInputs = formEl.querySelectorAll('input, textarea, select');

    if (isExamLocked) {
        lockBanner.classList.add('visible');
        document.getElementById('lockBannerTitle').textContent = 'Exam Locked';
        document.getElementById('lockBannerSub').textContent = 'This exam has been submitted or approved and cannot be edited.';
        allInputs.forEach(el => { el.disabled = true; });
        document.getElementById('btnSave').style.display    = 'none';
        document.getElementById('btnAnalyze').style.display = 'none';
    } else {
        lockBanner.classList.remove('visible');
        allInputs.forEach(el => { el.disabled = false; });
        document.getElementById('btnSave').style.display    = 'inline-flex';
        document.getElementById('btnAnalyze').style.display = 'inline-flex';
    }

    // AI Panel
    document.getElementById('aiEmpty').style.display = 'block';
    document.getElementById('aiContent').style.display = 'none';
    document.getElementById('aiStatusDot').style.background = 'var(--border)';

    // Active state
    document.querySelectorAll('.q-item').forEach(el => el.classList.remove('active'));
    if (document.getElementById('addNewBtn')) {
        document.getElementById('addNewBtn').classList.add('active');
    }
}

// ── Load Existing Question ─────────────────────────────────
function loadQuestion(id) {
    const q = examQuestions.find(x => parseInt(x.question_id) === id);
    if (!q) return;

    activeQId = id;
    lastAI = aiMap[id] || null;

    const isApproved = (q.moderation_status || '') === 'approved';
    const shouldLock = isApproved || isExamLocked;

    document.getElementById('editorTitle').textContent = shouldLock ? 'Question (Locked)' : 'Edit Question';
    document.getElementById('question_id').value = q.question_id;
    document.getElementById('section_name').value = q.section_name || '';
    document.getElementById('order').value = q.question_order || '';
    document.getElementById('marks').value = q.marks || '';
    document.getElementById('question_text').value = q.question_text || '';
    document.getElementById('option_a').value = q.option_a || '';
    document.getElementById('option_b').value = q.option_b || '';
    document.getElementById('option_c').value = q.option_c || '';
    document.getElementById('option_d').value = q.option_d || '';
    document.getElementById('correct_option').value = q.correct_option || 'A';

    toggleMCQ();

    // Lock or unlock form fields
    const formEl = document.getElementById('questionForm');
    const allInputs = formEl.querySelectorAll('input, textarea, select');
    allInputs.forEach(el => { el.disabled = shouldLock; });

    // Lock banner
    const lockBanner = document.getElementById('approvedLockBanner');
    lockBanner.classList.toggle('visible', shouldLock);

    if (isExamLocked) {
        document.getElementById('lockBannerTitle').textContent = 'Exam Locked';
        document.getElementById('lockBannerSub').textContent = 'This exam has been submitted or approved and cannot be edited.';
    } else {
        document.getElementById('lockBannerTitle').textContent = 'Approved & Locked';
        document.getElementById('lockBannerSub').textContent = 'This question has been approved by the moderator and cannot be edited.';
    }

    // Hide action buttons when locked
    document.getElementById('btnSave').style.display    = shouldLock ? 'none' : 'inline-flex';
    document.getElementById('btnAnalyze').style.display = shouldLock ? 'none' : 'inline-flex';
    document.getElementById('btnDelete').style.display  = shouldLock ? 'none' : 'inline-flex';

    // Status badge
    const badge = document.getElementById('editorBadge');
    if (q.moderation_status) {
        badge.textContent = q.moderation_status.toUpperCase();
        badge.className = 'badge badge-' + (q.moderation_status || 'pending');
        badge.style.display = 'inline-block';
    } else {
        badge.style.display = 'none';
    }

    // Moderator alert
    const alert = document.getElementById('modAlert');
    if (q.moderator_comment && q.moderator_comment.trim()) {
        alert.className = 'mod-alert ' + (q.moderation_status || 'pending');
        document.getElementById('modAlertTitle').textContent =
            q.moderation_status === 'approved' ? 'AI Approved' :
            q.moderation_status === 'revise'   ? 'Revision Needed' :
            q.moderation_status === 'rejected' ? 'Rejected' : 'Comment';
        document.getElementById('modAlertBody').textContent = q.moderator_comment;
        alert.style.display = 'block';
    } else {
        alert.style.display = 'none';
    }

    // AI Pane
    if (lastAI) {
        populateAIPane(lastAI);
    } else {
        document.getElementById('aiEmpty').style.display = 'block';
        document.getElementById('aiContent').style.display = 'none';
    }

    // Sidebar active
    document.querySelectorAll('.q-item').forEach(el => el.classList.remove('active'));
    document.getElementById('addNewBtn').classList.remove('active');
    const item = document.querySelector(`.q-item[data-id="${id}"]`);
    if (item) {
        item.classList.add('active');
        item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

// ── AI Pane Population ─────────────────────────────────────
function populateAIPane(ai) {
    if (!ai) return;

    document.getElementById('aiEmpty').style.display = 'none';
    document.getElementById('aiContent').style.display = 'block';

    // Score ring
    const score = parseInt(ai.quality_score ?? 0);
    const color = score >= 75 ? '#10b981' : score >= 55 ? '#f59e0b' : '#ef4444';
    const circumference = 239;
    const offset = circumference - (score / 100) * circumference;
    document.getElementById('scoreNum').textContent = score + '%';
    document.getElementById('scoreNum').style.color = color;
    const circle = document.getElementById('scoreCircle');
    circle.style.stroke = color;
    circle.style.strokeDashoffset = offset;

    document.getElementById('aiStatusLabel').textContent = ai.ai_status || '';
    document.getElementById('aiStatusDot').style.background = color;

    // Metrics
    document.getElementById('aiBloom').textContent = ai.bloom_level || '—';
    document.getElementById('aiDifficulty').textContent = ai.difficulty_level || '—';
    document.getElementById('aiCognitive').textContent = ai.cognitive_level || '—';
    document.getElementById('aiTopic').textContent = ai.topic || '—';

    // Suggestion
    const sugBox = document.getElementById('aiSuggestionBox');
    const sugText = document.getElementById('aiSuggestionText');
    if (ai.suggested_question && ai.suggested_question !== ai.original_question) {
        sugText.textContent = ai.suggested_question;
        sugBox.style.display = 'block';
    } else {
        sugBox.style.display = 'none';
    }

    // Warnings
    const warningsWrap = document.getElementById('aiWarningsWrap');
    warningsWrap.innerHTML = '';
    (ai.warnings || []).forEach(w => {
        const d = document.createElement('div');
        d.className = 'ai-warning-tag';
        d.textContent = w;
        warningsWrap.appendChild(d);
    });

    // Recommendations
    fillList('aiRecs', ai.recommendations);
    fillList('aiGrammar', ai.grammar_corrections);

    // Moderation decision
    const modDiv = document.getElementById('aiModDecision');
    if (ai.moderation_recommendation) {
        const isApproved = ai.moderation_recommendation === 'approved';
        modDiv.style.display = 'block';
        modDiv.style.background = isApproved ? 'rgba(34,197,94,0.08)' : 'rgba(245,158,11,0.08)';
        modDiv.style.border = '1px solid ' + (isApproved ? 'rgba(34,197,94,0.2)' : 'rgba(245,158,11,0.2)');
        modDiv.style.color = isApproved ? '#15803d' : '#a16207';
        modDiv.innerHTML = `<strong>${isApproved ? 'Auto-Approval Eligible' : 'Revision Recommended'}</strong><br><span style="opacity:0.8;">${ai.moderation_reason || ''}</span>`;
    } else {
        modDiv.style.display = 'none';
    }
}

function fillList(elId, items) {
    const el = document.getElementById(elId);
    if (!el) return;
    el.innerHTML = '';
    const list = (items || []).filter(Boolean);
    if (list.length === 0) {
        el.innerHTML = '<li style="color:var(--muted);">None detected.</li>';
        return;
    }
    list.forEach(item => {
        const li = document.createElement('li');
        li.textContent = item;
        el.appendChild(li);
    });
}

function applySuggestion() {
    const text = document.getElementById('aiSuggestionText').textContent;
    if (text) {
        document.getElementById('question_text').value = text;
        toast('Suggestion applied!', 'success');
    }
}

// ── API Call ───────────────────────────────────────────────
async function postCompose(action, extra = {}) {
    const data = new FormData(document.getElementById('questionForm'));
    data.set('action', action);
    for (const [k, v] of Object.entries(extra)) data.set(k, v);

    const res = await fetch(composeUrl, { method: 'POST', body: data });
    const raw = await res.text();
    try {
        return JSON.parse(raw);
    } catch {
        throw new Error(raw.substring(0, 300) || 'Invalid server response');
    }
}

// ── Run AI Analysis ────────────────────────────────────────
function runAIAnalysis() {
    if (analyzing) return;

    const text  = document.getElementById('question_text').value.trim();
    const marks = parseInt(document.getElementById('marks').value) || 0;
    const sec   = document.getElementById('section_name').value;

    if (!sec)              return toast('Please select a section first.', 'error');
    if (!text)             return toast('Please enter question text.', 'error');
    if (!marks || marks < 1) return toast('Please enter marks.', 'error');

    analyzing = true;
    document.getElementById('aiSpinner').style.display = 'block';
    document.getElementById('btnAnalyzeText').textContent = 'Analyzing…';
    document.getElementById('btnAnalyze').disabled = true;
    document.getElementById('aiStatusDot').style.background = '#f59e0b';

    postCompose('preview')
        .then(res => {
            analyzing = false;
            document.getElementById('aiSpinner').style.display = 'none';
            document.getElementById('btnAnalyzeText').textContent = 'Analyze with AI';
            document.getElementById('btnAnalyze').disabled = false;

            if (!res || !res.ai) {
                toast(res.error || 'AI analysis failed', 'error');
                return;
            }
            if (res.ai.status === 'failed') {
                toast(res.ai.error || 'AI analysis failed', 'error');
                populateAIPane(res.ai);
                return;
            }

            lastAI = res.ai;
            populateAIPane(lastAI);
            toast('AI analysis complete!', 'success');
        })
        .catch(err => {
            analyzing = false;
            document.getElementById('aiSpinner').style.display = 'none';
            document.getElementById('btnAnalyzeText').textContent = 'Analyze with AI';
            document.getElementById('btnAnalyze').disabled = false;
            toast('AI request failed: ' + (err.message || 'Unknown error'), 'error');
        });
}

// ── Save Question ──────────────────────────────────────────
function directSave() {
    if (saving) return;

    const text  = document.getElementById('question_text').value.trim();
    const marks = parseInt(document.getElementById('marks').value) || 0;
    const sec   = document.getElementById('section_name').value;

    if (!sec)              return toast('Please select a section.', 'error');
    if (!text)             return toast('Question text is required.', 'error');
    if (!marks || marks < 1) return toast('Marks must be at least 1.', 'error');

    saving = true;
    document.getElementById('saveSpinner').style.display = 'block';
    document.getElementById('btnSaveText').textContent = 'Saving…';
    document.getElementById('btnSave').disabled = true;

    const aiData = lastAI ? JSON.stringify(lastAI) : '{}';

    postCompose('save', { ai_data: aiData })
        .then(res => {
            saving = false;
            document.getElementById('saveSpinner').style.display = 'none';
            document.getElementById('btnSaveText').textContent = 'Save Question';
            document.getElementById('btnSave').disabled = false;

            if (res.success) {
                toast('Question saved! (AI: ' + (res.mod_status || 'pending') + ')', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                toast(res.error || 'Save failed', 'error');
            }
        })
        .catch(err => {
            saving = false;
            document.getElementById('saveSpinner').style.display = 'none';
            document.getElementById('btnSaveText').textContent = 'Save Question';
            document.getElementById('btnSave').disabled = false;
            toast('Save failed: ' + (err.message || 'Unknown error'), 'error');
        });
}

// ── Delete Question ────────────────────────────────────────
function deleteQuestion() {
    if (!activeQId) return;
    if (!confirm('Delete this question? This cannot be undone.')) return;

    postCompose('delete', { question_id: activeQId })
        .then(res => {
            if (res.success) {
                toast('Question deleted.', 'info');
                setTimeout(() => location.reload(), 800);
            } else {
                toast(res.error || 'Delete failed', 'error');
            }
        })
        .catch(err => toast('Delete failed: ' + (err.message || ''), 'error'));
}

// ── Init ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadNew();
});
</script>

</body>
</html>
