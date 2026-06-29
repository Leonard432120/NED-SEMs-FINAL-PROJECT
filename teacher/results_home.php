<?php
require_once __DIR__ . '/teacher_init.php';

$conn = get_db_connection();
$stmt = $conn->prepare("
    SELECT DISTINCT es.exam_id
    FROM exam_subjects es
    JOIN subject_assignments sa ON es.subject_id = sa.subject_id
    WHERE sa.teacher_id = ?
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

header("Location: view_results.php?exam_id=" . $exam['exam_id']);
exit();
?>