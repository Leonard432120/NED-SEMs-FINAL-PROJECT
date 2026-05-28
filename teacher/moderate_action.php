<?php
require_once __DIR__ . '/teacher_init.php';

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if ($exam_id <= 0) {
    header("Location: moderation_exams.php");
    exit();
}

$decision = $_POST['decision'] ?? '';
$comment = $_POST['comment'] ?? '';

if ($decision == 'approve') {
    $new_status = 'approved';
} elseif ($decision == 'reject') {
    $new_status = 'rejected';
} else {
    $new_status = 'under_moderation';
}

$conn = get_db_connection();
$stmt = $conn->prepare("UPDATE exams SET status=? WHERE exam_id=?");
$stmt->bind_param("si", $new_status, $exam_id);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("INSERT INTO moderation (exam_id, reviewer_id, comments, status, review_date) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("iiss", $exam_id, $user_id, $comment, $new_status);
$stmt->execute();
$stmt->close();

$conn->commit();
$conn->close();

header("Location: moderation_exams.php");
exit();
?>