<?php
require_once __DIR__ . '/teacher_init.php';

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if ($exam_id <= 0) {
    header("Location: moderation_exams.php");
    exit();
}

$comment = trim($_POST['comment'] ?? '');
$decision = $_POST['decision'] ?? '';

if ($decision == "approve") {
    $status = "approved";
} else {
    $status = "rejected";
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

// Update exam status
$stmt = $conn->prepare("
    UPDATE exams
    SET status = ?
    WHERE exam_id = ?
");
$stmt->bind_param("si", $status, $exam_id);
$stmt->execute();
$stmt->close();

// Save final moderation record
$stmt = $conn->prepare("
    INSERT INTO moderation
    (exam_id, reviewer_id, comments, status, review_date)
    VALUES (?, ?, ?, ?, NOW())
");
$stmt->bind_param("iiss", $exam_id, $user_id, $comment, $status);
$stmt->execute();
$stmt->close();

$conn->close();

header("Location: moderation_exams.php");
?>