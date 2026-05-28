<?php
require_once __DIR__ . '/teacher_init.php';

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$allowedStatuses = ['submitted', 'under_moderation', 'needs_revision', 'approved', 'rejected'];
if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$conn = get_db_connection();
$query = "SELECT 
            e.exam_id,
            e.exam_name,
            e.status,
            s.subject_name,
            t.name,
            e.year,
            e.class
        FROM exam_assignments ea
        JOIN exams e ON ea.exam_id = e.exam_id
        JOIN users t ON ea.teacher_id = t.user_id
        JOIN subjects s ON e.subject_id = s.subject_id
        WHERE ea.teacher_id = ?
          AND ea.role = 'moderator'";
$params = [$user_id];
$types = 'i';

if ($search !== '') {
    $query .= " AND e.exam_name LIKE ?";
    $params[] = '%' . $search . '%';
    $types .= 's';
}

if ($status !== '') {
    $query .= " AND e.status = ?";
    $params[] = $status;
    $types .= 's';
}

$query .= " ORDER BY e.exam_id DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$exams = [];
while ($row = $result->fetch_assoc()) {
    $exams[] = $row;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Moderation Tasks</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/styles.css">
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
                <h2 class="page-title">Moderation Tasks</h2>
            </div>
        </div>

        <form method="GET" class="search-filter-bar">
            <input type="text" name="search" placeholder="Search exam title..." value="<?= htmlspecialchars($search); ?>">
            <select name="status">
                <option value="">All Status</option>
                <option value="submitted" <?= $status === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                <option value="under_moderation" <?= $status === 'under_moderation' ? 'selected' : ''; ?>>Under Moderation</option>
                <option value="needs_revision" <?= $status === 'needs_revision' ? 'selected' : ''; ?>>Needs Revision</option>
                <option value="approved" <?= $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="rejected" <?= $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
            <button type="submit">Filter</button>
        </form>

        <div class="card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Exam Title</th>
                            <th>Subject</th>
                            <th>Teacher</th>
                            <th>Year</th>
                            <th>Class</th>
                            <th>Status</th>
                            <th style="width:200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($exams)): ?>
                            <tr>
                                <td colspan="7">No exams assigned for moderation.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($exams as $exam): ?>
                                <tr>
                                    <td><?= htmlspecialchars($exam['exam_name']); ?></td>
                                    <td><?= htmlspecialchars($exam['subject_name']); ?></td>
                                    <td><?= htmlspecialchars($exam['name']); ?></td>
                                    <td><?= htmlspecialchars($exam['year']); ?></td>
                                    <td><?= htmlspecialchars($exam['class']); ?></td>
                                    <td>
                                        <span class="badge badge-<?= htmlspecialchars($exam['status']); ?>">
                                            <?= htmlspecialchars($exam['status']); ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <a href="moderate_exam.php?exam_id=<?= $exam['exam_id']; ?>" class="btn btn-teal btn-small">Moderate</a>
                                        <a href="<?= BASE_URL ?>/teacher/exam.php?id=<?= $exam['exam_id']; ?>&page=cover" class="btn btn-dark btn-small">View Paper</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/static/js/main.js"></script>
</body>
</html>
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
    color:#0f172a;
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
.choice:hover{
    background:#f8fafc;
}
.choice input{
    display:none;
}
.choice.selected{
    border-color:#2563eb;
    background:#dbeafe;
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
.ai-card h2{
    margin:0;
}
.feedback-box{
    background:#1e293b;
    padding:14px;
    border-radius:12px;
    margin-bottom:12px;
    border-left:4px solid #38bdf8;
}
.alert{
    padding:10px;
    border-radius:8px;
    margin-bottom:15px;
}
.alert.success{
    background:#dcfce7;
    color:#166534;
}
.alert.error{
    background:#fee2e2;
    color:#991b1b;
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
           <a href="<?= BASE_URL ?>/logout.php">Logout</a><img src="<?= BASE_URL ?>/static/images/user.png">
        </div>
    </div>
</div>
<div class="dashboard">
    <?php include __DIR__ . '/teacher_sidebar.php'; ?>
    <div class="main-content">

        <div class="page-header">
            <div>
                <div class="page-title"><?php echo $exam ? 'Moderate Exam' : 'Moderation Exams'; ?></div>
                <?php if ($exam): ?>
                    <div class="exam-meta"><?= htmlspecialchars($exam['subject_name'] . ' • ' . ($exam['teacher_name'] ?? 'Teacher')); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="alert error"><?= htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($status_message): ?>
            <div class="alert success"><?= htmlspecialchars($status_message); ?></div>
        <?php endif; ?>

        <?php if ($exam): ?>

            <form method="POST">
                <div class="moderation-layout">

                    <div class="question-list">
                        <?php foreach ($questions as $index => $question): ?>
                            <div onclick="selectQuestion(<?= $question['question_id']; ?>)"
                                 id="q-item-<?= $question['question_id']; ?>"
                                 class="question-item<?php echo $index === 0 ? ' active' : ''; ?>">
                                <strong>Q<?= $index + 1; ?></strong>
                                <div style="margin-top:8px;font-size:14px">
                                    <?= htmlspecialchars(substr($question['question_text'], 0, 90)); ?>...
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div>
                        <?php foreach ($questions as $index => $question): ?>
                            <?php $ai = $ai_map[$question['question_id']] ?? []; ?>
                            <div id="question-detail-<?= $question['question_id']; ?>"
                                 class="question-panel question-detail"
                                 style="display:<?= $index === 0 ? 'block' : 'none'; ?>;">

                                <div class="question-header">
                                    <div>
                                        <h2>Question <?= $index + 1; ?></h2>
                                        <p><?= intval($question['marks']); ?> Marks</p>
                                    </div>
                                    <a href="edit_question.php?id=<?= $question['question_id']; ?>">
                                        <button type="button" class="edit-btn">✏ Edit Question</button>
                                    </a>
                                </div>

                                <div class="question-text">
                                    <?= nl2br(htmlspecialchars($question['question_text'])); ?>
                                </div>

                                <?php if ($question['option_a'] || $question['option_b'] || $question['option_c'] || $question['option_d']): ?>
                                    <div class="options">
                                        <?php if ($question['option_a']): ?><div class="opt">A. <?= htmlspecialchars($question['option_a']); ?></div><?php endif; ?>
                                        <?php if ($question['option_b']): ?><div class="opt">B. <?= htmlspecialchars($question['option_b']); ?></div><?php endif; ?>
                                        <?php if ($question['option_c']): ?><div class="opt">C. <?= htmlspecialchars($question['option_c']); ?></div><?php endif; ?>
                                        <?php if ($question['option_d']): ?><div class="opt">D. <?= htmlspecialchars($question['option_d']); ?></div><?php endif; ?>
                                    </div>
                                    <p style="margin-top:15px"><strong>Correct Answer:</strong> <?= htmlspecialchars($question['correct_option']); ?></p>
                                <?php endif; ?>

                                <?php if ($ai): ?>
                                    <div class="ai-panel">
                                        <h2>AI Moderation Analysis</h2>
                                        <div class="ai-grid">
                                            <div class="ai-card">
                                                <p>Bloom Level</p>
                                                <h2><?= htmlspecialchars($ai['bloom_level'] ?? 'N/A'); ?></h2>
                                                <small><?= htmlspecialchars($ai['explanation']['explanations']['bloom_explained'] ?? ''); ?></small>
                                            </div>
                                            <div class="ai-card">
                                                <p>AI Confidence</p>
                                                <h2><?= htmlspecialchars($ai['bloom_confidence'] ?? 0); ?>%</h2>
                                                <small><?= htmlspecialchars($ai['explanation']['explanations']['confidence_explained'] ?? ''); ?></small>
                                            </div>
                                            <div class="ai-card">
                                                <p>Complexity</p>
                                                <h2><?= htmlspecialchars($ai['complexity_score'] ?? 0); ?></h2>
                                                <small><?= htmlspecialchars($ai['explanation']['explanations']['complexity_explained'] ?? ''); ?></small>
                                            </div>
                                            <div class="ai-card">
                                                <p>Quality Score</p>
                                                <h2><?= htmlspecialchars($ai['quality_score'] ?? 0); ?>%</h2>
                                                <small><?= htmlspecialchars($ai['explanation']['explanations']['quality_explained'] ?? ''); ?></small>
                                            </div>
                                        </div>

                                        <div style="margin-top:25px;background:#111827;padding:20px;border-radius:16px;">
                                            <h3>AI Marks Recommendation</h3>
                                            <div style="display:flex;gap:30px;margin-top:15px;flex-wrap:wrap">
                                                <div>
                                                    <p style="color:#94a3b8">Current Marks</p>
                                                    <h1><?= htmlspecialchars($ai['current_marks'] ?? $question['marks']); ?></h1>
                                                </div>
                                                <div>
                                                    <p style="color:#94a3b8">Suggested Marks</p>
                                                    <h1 style="color:#38bdf8"><?= htmlspecialchars($ai['suggested_marks'] ?? 0); ?></h1>
                                                </div>
                                            </div>
                                        </div>

                                        <div style="margin-top:25px">
                                            <h3>AI Feedback</h3>
                                            <div style="margin-top:15px">
                                                <?php foreach ($ai['feedback'] ?? [] as $feedbackItem): ?>
                                                    <div class="feedback-box"><?= htmlspecialchars($feedbackItem); ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="moderation-section">
                                    <h2>Moderation Decision</h2>
                                    <div class="moderation-grid">
                                        <?php $status = $question['moderation_status'] ?? 'pending'; ?>
                                        <label class="choice<?php echo $status === 'approved' ? ' selected' : ''; ?>">
                                            <input type="radio" name="status_<?= $question['question_id']; ?>" value="approved" <?= $status === 'approved' ? 'checked' : ''; ?>>
                                            Approve
                                        </label>
                                        <label class="choice<?php echo $status === 'revise' ? ' selected' : ''; ?>">
                                            <input type="radio" name="status_<?= $question['question_id']; ?>" value="revise" <?= $status === 'revise' ? 'checked' : ''; ?>>
                                            Revise
                                        </label>
                                        <label class="choice<?php echo $status === 'rejected' ? ' selected' : ''; ?>">
                                            <input type="radio" name="status_<?= $question['question_id']; ?>" value="rejected" <?= $status === 'rejected' ? 'checked' : ''; ?>>
                                            Reject
                                        </label>
                                    </div>

                                    <textarea name="comment_<?= $question['question_id']; ?>" placeholder="Write moderation feedback..."><?= htmlspecialchars($question['moderator_comment'] ?? ''); ?></textarea>
                                    <button type="submit" class="save-btn">Save Moderation</button>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>

        <?php else: ?>
            <div class="card">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Exam</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exams as $exam): ?>
                                <tr>
                                    <td><?= $exam['exam_id']; ?></td>
                                    <td><?= htmlspecialchars($exam['exam_name']); ?></td>
                                    <td><?= htmlspecialchars($exam['status']); ?></td>
                                    <td><?= htmlspecialchars($exam['exam_date']); ?></td>
                                    <td><a href="moderation_exams.php?exam_id=<?= $exam['exam_id']; ?>" class="btn btn-dark">Moderate</a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($exams)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center;padding:20px;">No exams available for moderation.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>
<script>
function selectQuestion(id) {
    document.querySelectorAll('.question-detail').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.question-item').forEach(el => el.classList.remove('active'));
    const detail = document.getElementById('question-detail-' + id);
    const item = document.getElementById('q-item-' + id);
    if (detail) detail.style.display = 'block';
    if (item) item.classList.add('active');
}

window.addEventListener('load', function() {
    const first = <?= !empty($questions) ? (int)$questions[0]['question_id'] : 0; ?>;
    if (first) {
        selectQuestion(first);
    }
});

const choices = document.querySelectorAll('.choice');
choices.forEach(choice => {
    choice.addEventListener('click', function() {
        const parent = this.parentElement;
        parent.querySelectorAll('.choice').forEach(c => c.classList.remove('selected'));
        this.classList.add('selected');
        const radio = this.querySelector('input[type=radio]');
        if (radio) radio.checked = true;
    });
});
</script>
</body>
</html>