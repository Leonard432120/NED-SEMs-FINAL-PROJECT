<?php
require_once __DIR__ . '/teacher_init.php';

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if ($exam_id <= 0) {
    header("Location: assigned_exams.php");
    exit();
}

$conn = get_db_connection();

// Check access
$stmt = $conn->prepare("
    SELECT e.*, s.subject_name
    FROM exams e
    JOIN subjects s ON e.subject_id = s.subject_id
    JOIN exam_assignments ea ON e.exam_id = ea.exam_id
    WHERE e.exam_id = ? AND ea.teacher_id = ? AND ea.role = 'item_writer'
");
$stmt->bind_param("ii", $exam_id, $user_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) {
    $conn->close();
    header("Location: assigned_exams.php");
    exit();
}

// Get questions
$stmt = $conn->prepare("
    SELECT * FROM questions
    WHERE exam_id = ?
    ORDER BY question_id ASC
");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

// AI map
$ai_map = [];
foreach ($questions as $q) {
    $ai_map[$q['question_id']] = run_ai_moderation($q['question_text'], $q['marks']);
}

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
<title>Compose Exam - <?= htmlspecialchars($exam['exam_name']); ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/styles.css">
<style>
body{
    background:#f1f5f9;
    font-family: Arial, sans-serif;
    color:#0f172a;
}

.page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}

.page-title{
    font-size:28px;
    font-weight:700;
}

.exam-meta{
    color:#64748b;
    font-size:14px;
}

.moderation-layout{
    display:grid;
    grid-template-columns:320px 1fr;
    gap:20px;
}

.question-list{
    background:#dfdcdc;
    border:2px solid #c7baba;
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
}

.question-item:hover{
    background:#e2e8f0;
}

.question-item.active{
    border-left:4px solid #3b82f6;
    background:#dbeafe;
}

.question-panel{
    background:#dfdcdc;
    border:2px solid #c7baba;
    border-radius:16px;
    padding:25px;
    min-height:82vh;
}

.card{
    background:#fff;
    border-radius:14px;
    padding:15px;
    margin-bottom:15px;
}

label{
    font-weight:600;
    font-size:14px;
    display:block;
    margin-bottom:5px;
}

input, textarea, select{
    width:100%;
    padding:10px;
    border-radius:8px;
    border:1px solid #cbd5e1;
}

.dashboard-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:15px;
}

.btn{
    padding:12px 18px;
    border:none;
    border-radius:10px;
    cursor:pointer;
    font-weight:600;
}

.btn-dark{background:#111827;color:#fff;}
.btn-teal{background:#0f766e;color:#fff;}

#mcqBox{
    display:none;
    background:#f8fafc;
    padding:15px;
    border-radius:12px;
    border:1px solid #e2e8f0;
    margin-top:10px;
}

.modal{
    display:none;
    position:fixed;
    top:0;left:0;
    width:100%;height:100%;
    background:rgba(0,0,0,0.6);
    z-index:999;
}

.modal-content{
    width:700px;
    margin:80px auto;
    background:#fff;
    border-radius:14px;
    overflow:hidden;
}

.modal-header{
    display:flex;
    justify-content:space-between;
    padding:15px;
    background:#111827;
    color:#fff;
}

.modal-body{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:15px;
    padding:20px;
}

.ai-box{
    background:#f1f5f9;
    padding:10px;
    border-radius:10px;
    margin-bottom:10px;
}

.ai-warning{
    color:#dc2626;
    font-weight:600;
}

.modal-footer{
    display:flex;
    justify-content:flex-end;
    gap:10px;
    padding:15px;
    border-top:1px solid #eee;
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
    <div class="main-content">
        <div class="page-header">
            <div>
                <div class="page-title"><?= htmlspecialchars($exam['exam_name']); ?></div>
                <div class="exam-meta">Year: <?= htmlspecialchars($exam['year'] ?? ''); ?> | Class: <?= htmlspecialchars($exam['exam_class'] ?? ''); ?></div>
            </div>
        </div>

        <div class="moderation-layout">

            <div class="question-list">
                <div class="question-item active" onclick="loadNew()">
                    ➕ Add New Question
                </div>

                <?php foreach ($questions as $q): ?>
                <div class="question-item" onclick="loadQuestion(<?= $q['question_id']; ?>)">
                    <b>Q<?= $q['question_order'] ?: $q['question_id']; ?></b><br>
                    <?= htmlspecialchars(mb_substr($q['question_text'], 0, 60)); ?>...
                </div>
                <?php endforeach; ?>
            </div>

            <div class="question-panel">

                <div class="card">
                    <h3 id="formTitle">Add Question</h3>

                    <form id="questionForm">
                        <label>Section</label>
                        <select id="section" name="section_name" onchange="toggleMCQ()">
                            <option value="">Select</option>
                            <option value="Section A">Section A</option>
                            <option value="Section B">Section B</option>
                            <option value="Section C">Section C</option>
                        </select>

                        <div class="dashboard-grid">
                            <div>
                                <label>Order</label>
                                <input type="number" id="order" name="order">
                            </div>

                            <div>
                                <label>Marks</label>
                                <input type="number" id="marks" name="marks">
                            </div>
                        </div>

                        <label>Question</label>
                        <textarea id="question_text" name="question_text"></textarea>

                        <div id="mcqBox">
                            <label>Option A</label>
                            <input id="option_a" name="option_a">

                            <label>Option B</label>
                            <input id="option_b" name="option_b">

                            <label>Option C</label>
                            <input id="option_c" name="option_c">

                            <label>Option D</label>
                            <input id="option_d" name="option_d">

                            <label>Correct</label>
                            <select id="correct_option" name="correct_option">
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                            </select>
                        </div>

                        <button type="button" class="btn btn-dark" onclick="openAIModal()">
                            🚀 Run AI Analysis
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<div class="modal" id="aiModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>AI Review</h3>
            <span onclick="closeModal()" style="cursor:pointer">&times;</span>
        </div>
        <div class="modal-body">
            <div>
                <div class="ai-box"><b>Quality</b><h3 id="ai_quality"></h3></div>
                <div class="ai-box"><b>Bloom</b><h3 id="ai_bloom"></h3></div>
                <div class="ai-box"><b>Suggested</b><h3 id="ai_suggested"></h3></div>
                <div class="ai-warning" id="ai_warning"></div>
            </div>
            <div>
                <label>Edit Marks</label>
                <input type="number" id="final_marks_input">
                <label>Summary</label>
                <textarea id="ai_summary" readonly></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-teal" onclick="confirmSave()">Save</button>
            <button class="btn btn-dark" onclick="closeModal()">Cancel</button>
        </div>
    </div>
</div>

<script>
const questions = <?= json_encode($questions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const aiMap = <?= json_encode($ai_map, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

function toggleMCQ(){
    document.getElementById('mcqBox').style.display =
        document.getElementById('section').value === 'Section A' ? 'block' : 'none';
}

function loadNew(){
    document.getElementById('questionForm').reset();
    document.getElementById('formTitle').innerText = 'Add Question';
    document.getElementById('mcqBox').style.display = 'none';
}

function loadQuestion(id){
    const q = questions.find(x => x.question_id === id);
    if(!q) return;

    document.getElementById('formTitle').innerText = 'Edit Question';
    document.getElementById('section').value = q.section_name || '';
    document.getElementById('order').value = q.question_order || '';
    document.getElementById('marks').value = q.marks || '';
    document.getElementById('question_text').value = q.question_text || '';
    document.getElementById('option_a').value = q.option_a || '';
    document.getElementById('option_b').value = q.option_b || '';
    document.getElementById('option_c').value = q.option_c || '';
    document.getElementById('option_d').value = q.option_d || '';
    document.getElementById('correct_option').value = q.correct_option || 'A';
    toggleMCQ();
}

function openAIModal(){
    const form = document.getElementById('questionForm');
    const data = new FormData(form);
    fetch('ai_moderation.php', {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(data => {
        const lastAI = data;
        document.getElementById('ai_quality').innerText = Math.floor(lastAI.quality_score/10) + '/10';
        document.getElementById('ai_bloom').innerText = lastAI.bloom_level;
        document.getElementById('ai_suggested').innerText = lastAI.suggested_marks;
        document.getElementById('final_marks_input').value = document.getElementById('marks').value;
        document.getElementById('ai_warning').innerText = lastAI.suggested_marks != document.getElementById('marks').value ? '⚠ AI suggests different marks' : '';
        document.getElementById('ai_summary').value = lastAI.explanations?.summary || '';
        document.getElementById('aiModal').style.display = 'block';
    });
}

function confirmSave(){
    const form = document.getElementById('questionForm');
    const data = new FormData(form);
    data.append('exam_id', <?= $exam_id; ?>);
    fetch('confirm_question.php', {
        method: 'POST',
        body: data
    }).then(() => location.reload());
}

function closeModal(){
    document.getElementById('aiModal').style.display = 'none';
}
</script>

<script src="<?= BASE_URL ?>/static/js/main.js"></script>
</body>
</html>