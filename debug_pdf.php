<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/teacher/teacher_init.php';

$conn = get_db_connection();
$result = $conn->query('SELECT exam_id FROM exams LIMIT 1');
$exam = $result ? $result->fetch_assoc() : null;
if (!$exam) {
    echo "NO EXAM\n";
    exit(1);
}
$exam_id = (int)$exam['exam_id'];

$_GET['id'] = $exam_id;
$_GET['page'] = 'cover';
$_GET['action'] = '';

require_once __DIR__ . '/teacher/exam.php';
