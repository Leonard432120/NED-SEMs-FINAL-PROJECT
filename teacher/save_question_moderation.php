<?php
require_once __DIR__ . '/teacher_init.php';

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if ($exam_id <= 0 || !isset($_POST['question_id'])) {
    header("Location: moderation_exams.php");
    exit();
}

$question_id = (int)$_POST['question_id'];
$status = $_POST['status'] ?? '';
$comment = trim($_POST['comment'] ?? '');

if (!in_array($status, ['approved', 'revise', 'rejected'])) {
    header("Location: moderate_exam.php?exam_id=$exam_id");
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

// Save moderation per question
$stmt = $conn->prepare("
    INSERT INTO question_moderation
    (question_id, moderator_id, status, comment)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        status = VALUES(status),
        comment = VALUES(comment)
");
$stmt->bind_param("iiss", $question_id, $user_id, $status, $comment);
$stmt->execute();
$stmt->close();

// Update question status
$stmt = $conn->prepare("
    UPDATE questions
    SET moderation_status = ?
    WHERE question_id = ?
");
$stmt->bind_param("si", $status, $question_id);
$stmt->execute();
$stmt->close();

$conn->close();

header("Location: moderate_exam.php?exam_id=$exam_id");
?>