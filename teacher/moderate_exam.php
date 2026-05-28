<?php
require_once __DIR__ . '/teacher_init.php';

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if ($exam_id <= 0) {
    header("Location: moderation_exams.php");
    exit();
}

$conn = get_db_connection();

// Check access
$stmt = $conn->prepare("
    SELECT 1 FROM exam_assignments
    WHERE exam_id = ? AND teacher_id = ? AND role = 'moderator'
");
$stmt->bind_param("ii", $exam_id, $user_id);
$stmt->execute();
if ($stmt->get_result()->num_rows == 0) {
    $conn->close();
    header("Location: moderation_exams.php");
    exit();
}
$stmt->close();

// Get exam
$stmt = $conn->prepare("
    SELECT 
        e.*,
        u.name AS teacher_name,
        s.subject_name
    FROM exams e
    JOIN users u ON e.created_by = u.user_id
    JOIN subjects s ON e.subject_id = s.subject_id
    WHERE e.exam_id = ?
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
    $question_id = $_POST['question_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $comment = $_POST['comment'] ?? '';

    if ($question_id && $status) {
        $stmt = $conn->prepare("UPDATE questions SET moderation_status=?, moderator_comment=? WHERE question_id=?");
        $stmt->bind_param("ssi", $status, $comment, $question_id);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
    }
    header("Location: moderate_exam.php?exam_id=$exam_id");
    exit();
}

// AI moderation map
$ai_map = [];
foreach ($questions as $q) {
    $ai_result = run_ai_moderation($q["question_text"], $q["marks"]);
    $ai_map[$q["question_id"]] = $ai_result;
}

// Previous moderation
$stmt = $conn->prepare("
    SELECT *
    FROM moderation
    WHERE exam_id = ?
    ORDER BY review_date DESC
    LIMIT 1
");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$previous = $stmt->get_result()->fetch_assoc();
$stmt->close();

$conn->close();

// Analytics
$total_marks = array_sum(array_column($questions, 'marks'));
$easy = 0;
$medium = 0;
$hard = 0;

foreach ($questions as $q) {
    $ai = $ai_map[$q["question_id"]] ?? [];
    $bloom = $ai["bloom_level"] ?? "";
    if (in_array($bloom, ["Remember", "Understand"])) {
        $easy++;
    } elseif (in_array($bloom, ["Apply", "Analyze"])) {
        $medium++;
    } elseif (in_array($bloom, ["Evaluate", "Create"])) {
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

function shell_escape($value) {
    return '"' . str_replace('"', '\\"', $value) . '"';
}

function run_ai_moderation($question_text, $marks = 0) {
    $repoRoot = dirname(__DIR__);
    $cli = $repoRoot . '/ai_moderation_cli.py';
    $question_arg = shell_escape($question_text);
    $marks_arg = shell_escape((string)$marks);
    $pythonCandidates = ['python', 'py -3', 'python3'];

    foreach ($pythonCandidates as $python) {
        $output = [];
        $command = 'cd /d ' . shell_escape($repoRoot) . ' && ' . $python . ' ' . shell_escape($cli) . ' --question ' . $question_arg . ' --marks ' . $marks_arg . ' 2>&1';
        exec($command, $output, $exitCode);

        if ($exitCode === 0) {
            $result = json_decode(implode("\n", $output), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $result;
            }
        }
    }

    return [
        'bloom_level' => 'Unknown',
        'bloom_confidence' => 0,
        'complexity_score' => 0,
        'quality_score' => 0,
        'suggested_marks' => $marks,
        'current_marks' => $marks,
        'ai_status' => 'AI Failed',
        'feedback' => ['AI analysis unavailable.']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Moderate Exam - <?= htmlspecialchars($exam['exam_name']); ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/styles.css">
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

.moderation-grid{
    display:flex;
    gap:12px;
    margin-top:15px;
}

.choice{
    flex:1;
    background:white;
    border:2px solid #cbd5e1;
    padding:15px;
    border-radius:12px;
    text-align:center;
    cursor:pointer;
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
<div class="header">
    <div class="header-left">
        <span class="dashboard-title">NED-SEMS | Teacher Portal</span>
    </div>
    <div class="header-right">
        <div class="profile">
            <a href="<?= BASE_URL ?>/logout.php">Logout</a><img src="<?= BASE_URL ?>/static/images/user.png" alt="User">
        </div>
    </div>
</div>
<div class="dashboard">
    <?php include __DIR__ . '/teacher_sidebar.php'; ?>
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
                <?php $exp = $ai['explanation'] ?? []; ?>
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
                    <div class="ai-panel">
                        <h2>AI Moderation Analysis</h2>
                        <div class="ai-grid">
                            <div class="ai-card">
                                <p>Bloom Level</p>
                                <h2><?= htmlspecialchars($ai['bloom_level'] ?? 'Unknown'); ?></h2>
                                <small><?= htmlspecialchars($exp['bloom_explained'] ?? ''); ?></small>
                            </div>
                            <div class="ai-card">
                                <p>AI Confidence</p>
                                <h2><?= htmlspecialchars($ai['bloom_confidence'] ?? 0); ?>%</h2>
                                <small><?= htmlspecialchars($exp['confidence_explained'] ?? ''); ?></small>
                            </div>
                            <div class="ai-card">
                                <p>Complexity</p>
                                <h2><?= htmlspecialchars($ai['complexity_score'] ?? 0); ?></h2>
                                <small><?= htmlspecialchars($exp['complexity_explained'] ?? ''); ?></small>
                            </div>
                            <div class="ai-card">
                                <p>Quality Score</p>
                                <h2><?= htmlspecialchars($ai['quality_score'] ?? 0); ?>%</h2>
                                <small><?= htmlspecialchars($exp['quality_explained'] ?? ''); ?></small>
                            </div>
                        </div>

                        <div style="margin-top:25px;background:#111827;padding:20px;border-radius:16px;">
                            <h3>AI Marks Recommendation</h3>
                            <div style="display:flex;gap:30px;margin-top:15px;">
                                <div>
                                    <p style="color:#94a3b8">Current Marks</p>
                                    <h1><?= htmlspecialchars($ai['current_marks'] ?? $q['marks']); ?></h1>
                                </div>
                                <div>
                                    <p style="color:#94a3b8">Suggested Marks</p>
                                    <h1 style="color:#38bdf8"><?= htmlspecialchars($ai['suggested_marks'] ?? $q['marks']); ?></h1>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top:25px">
                            <h3>AI Feedback</h3>
                            <?php foreach ($ai['feedback'] ?? [] as $item): ?>
                            <div class="feedback-box">
                                <?= htmlspecialchars($item); ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="moderate_exam.php?exam_id=<?= $exam_id; ?>">
                        <input type="hidden" name="question_id" value="<?= $q['question_id']; ?>">
                        <div class="moderation-section">
                            <h2>Moderation Decision</h2>
                            <div class="moderation-grid">
                                <label class="choice">
                                    <input type="radio" name="status" value="approved"> Approve
                                </label>
                                <label class="choice">
                                    <input type="radio" name="status" value="revise"> Revise
                                </label>
                                <label class="choice">
                                    <input type="radio" name="status" value="rejected"> Reject
                                </label>
                            </div>
                            <textarea name="comment" placeholder="Write moderation feedback..."></textarea>
                            <button type="submit" class="save-btn">Save Moderation</button>
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
<script src="<?= BASE_URL ?>/static/js/main.js"></script>
</body>
</html>