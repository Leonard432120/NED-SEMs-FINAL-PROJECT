<?php
require_once __DIR__ . '/config/db.php';

$conn = get_db_connection();
$exam_id = 18;

$stmt = $conn->prepare("SELECT e.*, s.subject_name FROM exams e JOIN subjects s ON e.subject_id = s.subject_id WHERE e.exam_id = ?");
$stmt->bind_param('i', $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();

$exam['year'] = $exam['year'] ?? date('Y');
$exam['duration_minutes'] = $exam['duration_minutes'] ?? 120;
$exam['total_marks'] = $exam['total_marks'] ?? 100;
$exam['subject_code'] = strtoupper(substr($exam['subject_name'],0,3)) . '-MSCE';

$qstmt = $conn->prepare('SELECT * FROM questions WHERE exam_id = ? ORDER BY question_order ASC');
$qstmt->bind_param('i', $exam_id);
$qstmt->execute();
$qres = $qstmt->get_result();
$questions = [];
while ($row = $qres->fetch_assoc()) {
    $questions[] = $row;
}
$sectionA = array_slice($questions, 0, 10);
$sectionB = array_slice($questions, 10, 5);
$sectionC = array_slice($questions, 15);

$page1_questions = array_slice($sectionA, 0, 5);
$page2_questions = array_slice($sectionA, 5, 5);
$page3_questions = array_slice($sectionB, 0, ceil(count($sectionB) / 2));
$page4_questions = array_slice($sectionB, ceil(count($sectionB) / 2));
$page5_questions = array_slice($sectionC, 0, ceil(count($sectionC) / 2));
$page6_questions = array_slice($sectionC, ceil(count($sectionC) / 2));

$pdf_mode = true;
include __DIR__ . '/teacher/exam/pdf_full.php';
if (!isset($html)) {
    echo "MISSING_HTML\n";
    exit(1);
}
file_put_contents(__DIR__ . '/debug_pdf_output.html', $html);
echo "WROTE debug_pdf_output.html length=" . strlen($html) . "\n";
