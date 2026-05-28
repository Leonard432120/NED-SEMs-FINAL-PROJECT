<?php
require_once __DIR__ . '/teacher_init.php';

$conn = get_db_connection();

// Subject performance
$subject_stmt = $conn->prepare("
    SELECT s.subject_name, AVG(r.percentage) avg_score
    FROM results r
    JOIN exams e ON r.exam_id = e.exam_id
    JOIN subjects s ON e.subject_id = s.subject_id
    GROUP BY s.subject_name
");
$subject_stmt->execute();
$data = $subject_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$subject_stmt->close();

$conn->close();

header('Content-Type: application/json');
echo json_encode($data);
?>