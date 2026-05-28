<?php
require_once __DIR__ . '/config/db.php';
$conn = get_db_connection();
$sql = 'SELECT q.exam_id, e.exam_name, COUNT(*) AS total FROM questions q JOIN exams e ON q.exam_id=e.exam_id GROUP BY q.exam_id HAVING total > 10 LIMIT 1';
$res = $conn->query($sql);
$row = $res ? $res->fetch_assoc() : null;
if (!$row) { echo "NOEXAM\n"; exit(1); }
echo "EXAM_ID=".$row['exam_id'].' NAME='.$row['exam_name'].' TOTAL='.$row['total'].'\n';
