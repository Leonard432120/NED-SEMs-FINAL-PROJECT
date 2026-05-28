<?php
require_once __DIR__ . '/teacher_init.php';

$conn = get_db_connection();
$stmt = $conn->prepare("
    SELECT e.exam_id
    FROM exams e
    JOIN exam_assignments ea ON e.exam_id = ea.exam_id
    WHERE ea.teacher_id=?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$exam) {
    echo "No exams assigned";
    exit();
}

header("Location: enter_results.php?exam_id=" . $exam['exam_id']);
exit();
?>