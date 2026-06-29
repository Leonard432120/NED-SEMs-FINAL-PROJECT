<?php
require_once __DIR__ . '/teacher_init.php';
require_once __DIR__ . '/../services/ai/compose_bridge.php';

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if ($exam_id <= 0) {
    header("Location: moderation_exams.php");
    exit();
}

$conn = get_db_connection();

// Check access
// Check moderator access
$stmt = $conn->prepare("
    SELECT
        e.*,
        s.subject_name
    FROM subject_assignments sa
    INNER JOIN exam_subjects es ON sa.subject_id = es.subject_id
    INNER JOIN exams e ON es.exam_id = e.exam_id
    INNER JOIN subjects s ON es.subject_id = s.subject_id
    WHERE e.exam_id = ?
      AND sa.teacher_id = ?
      AND sa.role = 'moderator'
");

$stmt->bind_param("ii", $exam_id, $user_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) {
    $conn->close();
    header("Location: moderation_exams.php");
    exit();
}

// Get exam
$stmt = $conn->prepare("
    SELECT 
        e.*,
        u.name AS teacher_name,
        s.subject_name
    FROM exams e
    JOIN users u ON e.created_by = u.user_id
    JOIN exam_subjects es ON e.exam_id = es.exam_id
    JOIN subjects s ON es.subject_id = s.subject_id
    WHERE e.exam_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) {
    $conn->close();
    header("Location: moderation_exams.php");
    exit();
}

// Get questions
$stmt = $conn->prepare("
    SELECT *
    FROM questions
    WHERE exam_id = ?
    ORDER BY question_order ASC
");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle POST for saving moderation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $question_id = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;
    $status = $_POST['status'] ?? '';
    $comment = trim($_POST['comment'] ?? '');

    $allowed = ['approved', 'revise', 'rejected'];

    if ($question_id > 0 && in_array($status, $allowed)) {

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
    }

    header("Location: moderate_exam.php?exam_id=$exam_id");
    exit();
}
// Fresh AI moderation report per question (independent from teacher compose analysis)
$ai_map = compose_build_ai_map($conn, $questions, $exam_id);
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
/* ================= GLOBAL ================= */
body{
    background:#f1f5f9;
    font-family: Arial, sans-serif;
    color:#0f172a;
}

/* ================= HEADER ================= */
.page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
}

.page-title{
    font-size:28px;
    font-weight:700;
}

.exam-meta{
    color:#64748b;
    font-size:14px;
    margin-top:5px;
}

/* ================= LAYOUT ================= */
.moderation-layout{
    display:grid;
    grid-template-columns:320px 1fr;
    gap:20px;
}

/* ================= LEFT PANEL ================= */
.question-list{
    background:rgb(223, 220, 220);
    border:2px solid rgb(199, 186, 186);
    border-radius:16px;
    padding:15px;
    height:82vh;
    overflow:auto;
}

.question-item{
    background:#f8fafc;
    padding:12px;
    border-radius:10px;
    margin-bottom:10px;
    cursor:pointer;
    border-left:4px solid transparent;
    transition:0.2s;
}

.question-item:hover{
    background:#e2e8f0;
}

.question-item.active{
    border-left:4px solid #3b82f6;
    background:#dbeafe;
}

/* ================= RIGHT PANEL ================= */
.question-panel{
    background:rgb(223, 220, 220);
    border:2px solid rgb(199, 186, 186);
    border-radius:16px;
    padding:25px;
}

.question-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}

.edit-btn{
    background:#f59e0b;
    color:white;
    border:none;
    padding:10px 16px;
    border-radius:10px;
    cursor:pointer;
    font-weight:600;
}

.question-text{
    font-size:20px;
    line-height:1.7;
}

.options{
    margin-top:20px;
}

.opt{
    background:white;
    border:1px solid #cbd5e1;
    padding:14px;
    border-radius:12px;
    margin-bottom:10px;
}

/* ================= MODERATION ================= */
.moderation-section{
    margin-top:30px;
}

.moderation-grid {
    display: flex;
    gap: 12px;
    margin-top: 15px;
}

.choice {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 10px;
    background: #ffffff;
    border: 2px solid #cbd5e1;
    padding: 14px;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 600;
    transition: 0.2s;
}

.choice:hover {
    background: #f1f5f9;
}

.choice input[type="radio"] {
    transform: scale(1.3);
    cursor: pointer;
}

.choice span {
    font-size: 15px;
}

/* highlight selected option */
.choice input[type="radio"]:checked + span {
    color: #2563eb;
}

textarea{
    width:100%;
    padding:14px;
    border-radius:12px;
    border:1px solid #cbd5e1;
    margin-top:18px;
    min-height:120px;
    resize:none;
}

.save-btn{
    margin-top:18px;
    background:#2563eb;
    color:white;
    border:none;
    padding:14px 22px;
    border-radius:12px;
    cursor:pointer;
    font-weight:600;
}

/* ================= AI PANEL ================= */
.ai-panel{
    background:#0f172a;
    color:white;
    border-radius:18px;
    padding:25px;
    margin-top:25px;
}

.ai-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:15px;
    margin-top:20px;
}

.ai-card{
    background:#1e293b;
    padding:18px;
    border-radius:14px;
}

.ai-card p{
    color:#94a3b8;
    font-size:13px;
    margin-bottom:8px;
}

.feedback-box{
    background:#1e293b;
    padding:14px;
    border-radius:12px;
    margin-bottom:12px;
    border-left:4px solid #38bdf8;
}
</style>
</head>
<body>
<?php include __DIR__ . '/../common/header.php'; ?>
<div class="dashboard">
    <?php include __DIR__ . '/../common/sidebar.php'; ?>
    <div class="content">
        <div class="page-header">
            <div>
                <div class="page-title">Moderate Exam</div>
                <div class="exam-meta"><?= htmlspecialchars($exam['subject_name'] . ' • ' . $exam['teacher_name']); ?></div>
            </div>
        </div>

        <div class="moderation-layout">
            <div class="question-list">
                <?php foreach ($questions as $index => $q): ?>
                <div onclick="selectQuestion(<?= $q['question_id']; ?>)"
                     id="q-item-<?= $q['question_id']; ?>"
                     class="question-item <?= $index == 0 ? 'active' : ''; ?>">
                    <strong>Q<?= $index + 1; ?></strong>
                    <div style="margin-top:8px;font-size:14px">
                        <?= htmlspecialchars(mb_strimwidth($q['question_text'], 0, 90, '...')); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div>
                <?php foreach ($questions as $index => $q): ?>
                <?php $ai = $ai_map[$q['question_id']] ?? []; ?>
                <div id="question-detail-<?= $q['question_id']; ?>"
                     class="question-panel question-detail"
                     style="display: <?= $index == 0 ? 'block' : 'none'; ?>">

                    <div class="question-header">
                        <div>
                            <h2>Question <?= $index + 1; ?></h2>
                            <p><?= htmlspecialchars($q['marks']); ?> Marks</p>
                        </div>
                        <a href="edit_question.php?question_id=<?= $q['question_id']; ?>">
                            <button type="button" class="edit-btn">✏ Edit Question</button>
                        </a>
                    </div>

                    <div class="question-text">
                        <?= nl2br(htmlspecialchars($q['question_text'])); ?>
                    </div>

                    <?php if ($q['option_a']): ?>
                    <div class="options">
                        <div class="opt">A. <?= htmlspecialchars($q['option_a']); ?></div>
                        <div class="opt">B. <?= htmlspecialchars($q['option_b']); ?></div>
                        <div class="opt">C. <?= htmlspecialchars($q['option_c']); ?></div>
                        <div class="opt">D. <?= htmlspecialchars($q['option_d']); ?></div>
                    </div>
                    <p style="margin-top:15px">
                        <strong>Correct Answer:</strong> <?= htmlspecialchars($q['correct_option']); ?>
                    </p>
                    <?php endif; ?>

                    <?php if (!empty($ai)): ?>
                    <?php
                        $modRec = $ai['moderation_recommendation'] ?? [];
                        $dup = $ai['duplicate_detection'] ?? [];
                    ?>
                    <div class="ai-panel">
                        <h2>AI Moderation Report (Fresh Analysis)</h2>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">
                            <?= htmlspecialchars($modRec['note'] ?? 'AI assists only — you make the final decision.'); ?>
                        </p>

                        <div class="ai-grid">
                            <div class="ai-card">
                                <p>Quality Score</p>
                                <h2><?= htmlspecialchars($ai['quality_score'] ?? 0); ?>%</h2>
                            </div>
                            <div class="ai-card">
                                <p>Difficulty</p>
                                <h2><?= htmlspecialchars($ai['difficulty_level'] ?? 'Unknown'); ?></h2>
                            </div>
                            <div class="ai-card">
                                <p>Bloom's Taxonomy</p>
                                <h2><?= htmlspecialchars($ai['cognitive_level'] ?? $ai['bloom_level'] ?? 'Unknown'); ?></h2>
                            </div>
                            <div class="ai-card">
                                <p>AI Recommendation</p>
                                <h2 style="color:#38bdf8;"><?= htmlspecialchars($modRec['label'] ?? 'Review'); ?></h2>
                            </div>
                        </div>

                        <?php if (!empty($modRec['reason'])): ?>
                        <div style="margin-top:18px;background:#111827;padding:16px;border-radius:12px;">
                            <strong>Recommendation Reason</strong>
                            <p style="margin-top:8px;"><?= htmlspecialchars($modRec['reason']); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($ai['grammar_corrections'])): ?>
                        <div style="margin-top:20px">
                            <h3>Grammar Issues</h3>
                            <?php foreach ($ai['grammar_corrections'] as $item): ?>
                            <div class="feedback-box"><?= htmlspecialchars($item); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($ai['ambiguities'])): ?>
                        <div style="margin-top:20px">
                            <h3>Ambiguity Warnings</h3>
                            <?php foreach ($ai['ambiguities'] as $item): ?>
                            <div class="feedback-box" style="border-left-color:#f59e0b;">Ambiguous term: <?= htmlspecialchars($item); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($dup['is_duplicate']) || ($dup['similarity_score'] ?? 0) >= 70): ?>
                        <div style="margin-top:20px">
                            <h3>Duplicate Detection</h3>
                            <div class="feedback-box" style="border-left-color:#ef4444;">
                                Similarity: <?= htmlspecialchars($dup['similarity_score'] ?? 0); ?>%
                                <?php if (!empty($dup['matched_question'])): ?>
                                — matched: "<?= htmlspecialchars($dup['matched_question']); ?>"
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($ai['suggested_question']) && $ai['suggested_question'] !== $q['question_text']): ?>
                        <div style="margin-top:20px">
                            <h3>Suggested Correction</h3>
                            <div class="feedback-box"><?= nl2br(htmlspecialchars($ai['suggested_question'])); ?></div>
                        </div>
                        <?php endif; ?>

                        <div style="margin-top:20px">
                            <h3>AI Feedback & Recommendations</h3>
                            <?php foreach ($ai['recommendations'] ?? [] as $item): ?>
                            <div class="feedback-box"><?= htmlspecialchars($item); ?></div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (!empty($ai['warnings'])): ?>
                        <div style="margin-top:20px">
                            <h3>Warnings</h3>
                            <?php foreach ($ai['warnings'] as $item): ?>
                            <div class="feedback-box" style="border-left-color:#ef4444;"><?= htmlspecialchars($item); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="moderate_exam.php?exam_id=<?= $exam_id; ?>">

                        <input type="hidden" name="question_id" value="<?= $q['question_id']; ?>">

                        <div class="moderation-section">
                            <h2>Moderation Decision</h2>

                            <div class="moderation-grid">

                                <!-- APPROVE -->
                                <label class="choice">
                                    <input type="radio" name="status" value="approved"
                                        <?= ($q['moderation_status'] === 'approved') ? 'checked' : '' ?>>
                                    <span>Approve</span>
                                </label>

                                <!-- REVISE -->
                                <label class="choice">
                                    <input type="radio" name="status" value="revise"
                                        <?= ($q['moderation_status'] === 'revise') ? 'checked' : '' ?>>
                                    <span>Revise</span>
                                </label>

                                <!-- REJECT -->
                                <label class="choice">
                                    <input type="radio" name="status" value="rejected"
                                        <?= ($q['moderation_status'] === 'rejected') ? 'checked' : '' ?>>
                                    <span>Reject</span>
                                </label>

                            </div>

                            <!-- COMMENT BOX -->
                            <textarea 
                                name="comment" 
                                placeholder="Write moderation feedback..."
                            ><?= htmlspecialchars($q['moderator_comment'] ?? '') ?></textarea>

                            <!-- SUBMIT BUTTON -->
                            <button type="submit" class="save-btn">
                                Save Moderation
                            </button>

                        </div>

                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<script>
function selectQuestion(id) {
    document.querySelectorAll('.question-detail').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.question-item').forEach(el => el.classList.remove('active'));
    document.getElementById('question-detail-' + id).style.display = 'block';
    document.getElementById('q-item-' + id).classList.add('active');
}
window.onload = function() {
    const first = <?= !empty($questions) ? $questions[0]['question_id'] : 0; ?>;
    if (first) {
        selectQuestion(first);
    }
}
</script>
<?php include __DIR__ . '/../common/footer.php'; ?>