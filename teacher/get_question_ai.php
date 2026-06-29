<?php
require_once __DIR__ . '/../services/ai/compose_bridge.php';
require_once __DIR__ . '/teacher_init.php';

$conn = get_db_connection();

$question_id = (int)($_GET['question_id'] ?? 0);

if ($question_id <= 0) {
    echo json_encode(['error' => 'Invalid question']);
    exit;
}

// fetch question
$stmt = $conn->prepare("SELECT * FROM questions WHERE question_id=?");
$stmt->bind_param("i", $question_id);
$stmt->execute();
$q = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$q) {
    echo json_encode(['error' => 'Not found']);
    exit;
}

// optional: fetch other questions for duplicate detection
$stmt = $conn->prepare("SELECT question_text FROM questions WHERE exam_id=?");
$stmt->bind_param("i", $q['exam_id']);
$stmt->execute();
$all = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$others = array_column($all, 'question_text');

// run AI
$ai = analyze_question_for_teacher(
    $q['question_text'],
    $q['marks'],
    $others
);

echo json_encode($ai);