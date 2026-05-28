<?php
require_once __DIR__ . '/teacher_init.php';

$question_id = isset($_GET['question_id']) ? (int)$_GET['question_id'] : 0;

if ($question_id <= 0) {
    header("Location: assigned_exams.php");
    exit();
}

$conn = get_db_connection();

$stmt = $conn->prepare("SELECT * FROM questions WHERE question_id=?");
$stmt->bind_param("i", $question_id);
$stmt->execute();
$question = $stmt->get_result()->fetch_assoc();

if (!$question) {
    $conn->close();
    header("Location: assigned_exams.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $order = isset($_POST['question_order']) ? (int)$_POST['question_order'] : 0;
    $text = trim($_POST['question_text']);
    $marks = isset($_POST['marks']) ? (int)$_POST['marks'] : 0;
    $section = trim($_POST['section_name'] ?? '');
    $option_a = trim($_POST['option_a'] ?? '');
    $option_b = trim($_POST['option_b'] ?? '');
    $option_c = trim($_POST['option_c'] ?? '');
    $option_d = trim($_POST['option_d'] ?? '');
    $correct_option = trim($_POST['correct_option'] ?? '');

    $stmt = $conn->prepare("UPDATE questions SET question_order=?, question_text=?, marks=?, section_name=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_option=? WHERE question_id=?");
    $stmt->bind_param("isissssssi", $order, $text, $marks, $section, $option_a, $option_b, $option_c, $option_d, $correct_option, $question_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    $conn->close();

    header("Location: moderate_exam.php?exam_id=" . $question['exam_id']);
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Question</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/styles.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/form.css">
<style>
.status-panel {
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border: 1px solid #bfdbfe;
    padding: 22px;
    border-radius: 18px;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
}
.status-panel h4 {
    margin-bottom: 12px;
    font-size: 18px;
    color: #0f172a;
}
.status-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
}
.status-pill {
    display: inline-flex;
    align-items: center;
    padding: 10px 16px;
    border-radius: 999px;
    background: #e0f2fe;
    color: #1d4ed8;
    font-weight: 700;
    letter-spacing: 0.02em;
}
.comment-box {
    background: #fff;
    border: 1px solid #e2e8f0;
    padding: 18px;
    border-radius: 16px;
    margin-top: 16px;
    line-height: 1.7;
    color: #334155;
}
.action-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 24px;
}
.btn-outline {
    background: transparent;
    border: 1px solid rgba(17, 24, 39, 0.12);
    color: #111827;
}
.btn-outline:hover {
    background: rgba(17, 24, 39, 0.04);
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
                <h2 class="page-title">Edit Question</h2>
                <p class="muted">Update question details and re-submit for moderation</p>
            </div>
            <div class="header-actions">
                <a href="moderate_exam.php?exam_id=<?= $question['exam_id']; ?>" class="btn-small btn-dark">
                    ← Back to Moderation
                </a>
            </div>
        </div>

        <div class="card">
            <h3>Question Details</h3>
            <form method="POST">
                <div class="dashboard-grid">
                    <div class="form-group">
                        <label>Question Order</label>
                        <input type="number" name="question_order" value="<?= htmlspecialchars($question['question_order'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Marks</label>
                        <input type="number" name="marks" value="<?= htmlspecialchars($question['marks'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Question Text</label>
                    <textarea name="question_text" rows="4" required><?= htmlspecialchars($question['question_text']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Section</label>
                    <select name="section_name" class="form-control">
                        <option value="Section A" <?= $question['section_name'] == 'Section A' ? 'selected' : ''; ?>>Section A</option>
                        <option value="Section B" <?= $question['section_name'] == 'Section B' ? 'selected' : ''; ?>>Section B</option>
                        <option value="Section C" <?= $question['section_name'] == 'Section C' ? 'selected' : ''; ?>>Section C</option>
                    </select>
                </div>

                <div class="card">
                    <h4>MCQ Options (if applicable)</h4>
                    <div class="form-group">
                        <label>Option A</label>
                        <input type="text" name="option_a" value="<?= htmlspecialchars($question['option_a'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Option B</label>
                        <input type="text" name="option_b" value="<?= htmlspecialchars($question['option_b'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Option C</label>
                        <input type="text" name="option_c" value="<?= htmlspecialchars($question['option_c'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Option D</label>
                        <input type="text" name="option_d" value="<?= htmlspecialchars($question['option_d'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Correct Answer</label>
                        <select name="correct_option">
                            <option value="">Select</option>
                            <option value="A" <?= $question['correct_option'] == 'A' ? 'selected' : ''; ?>>A</option>
                            <option value="B" <?= $question['correct_option'] == 'B' ? 'selected' : ''; ?>>B</option>
                            <option value="C" <?= $question['correct_option'] == 'C' ? 'selected' : ''; ?>>C</option>
                            <option value="D" <?= $question['correct_option'] == 'D' ? 'selected' : ''; ?>>D</option>
                        </select>
                    </div>
                </div>

                <div class="card status-panel">
                    <div class="status-row">
                        <div>
                            <h4>Moderation Status</h4>
                            <p><strong>Status:</strong>
                                <span class="status-pill">
                                    <?= htmlspecialchars($question['moderation_status'] ?? 'under_moderation'); ?>
                                </span>
                            </p>
                        </div>
                    </div>

                    <?php if (!empty($question['moderator_comment'])): ?>
                        <div class="comment-box">
                            <strong>Last Comment</strong>
                            <p><?= nl2br(htmlspecialchars($question['moderator_comment'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="action-bar">
                    <button type="submit" class="btn-small btn-teal">
                        ✔ Save Changes
                    </button>
                    <a href="moderate_exam.php?exam_id=<?= $question['exam_id']; ?>" class="btn-small btn-danger">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

    </div>
</div>
<script src="<?= BASE_URL ?>/static/js/main.js"></script>
</body>
</html>