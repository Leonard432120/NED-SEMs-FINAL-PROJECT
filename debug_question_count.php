<?php
require_once __DIR__ . '/config/db.php';

$conn = get_db_connection();
$result = $conn->query('SELECT exam_id, exam_name FROM exams LIMIT 1');
$exam = $result ? $result->fetch_assoc() : null;
if (!$exam) {
    echo "NO EXAM\n";
    exit(1);
}
$exam_id = (int)$exam['exam_id'];
$qstmt = $conn->prepare('SELECT COUNT(*) AS total FROM questions WHERE exam_id = ?');
$qstmt->bind_param('i', $exam_id);
$qstmt->execute();
$qres = $qstmt->get_result();
$row = $qres->fetch_assoc();
echo "EXAM_ID=".$exam_id." NAME=".$exam['exam_name'].' TOTAL_QUESTIONS='.(int)$row['total']."\n";
