<?php
require_once __DIR__ . '/teacher_init.php';
require_once __DIR__ . '/../services/ai/compose_bridge.php';

header('Content-Type: application/json');

@set_time_limit(300);
@ini_set('max_execution_time', '300');

$conn = get_db_connection();
$user_id = $_SESSION['user_id'] ?? 0;

$exam_id = (int)($_POST['exam_id'] ?? 0);
$question_text = trim($_POST['question_text'] ?? '');
$marks = (int)($_POST['marks'] ?? 0);
$section_name = trim($_POST['section_name'] ?? 'Section A');
$order = (int)($_POST['order'] ?? 1);

if (!$exam_id || !$question_text || $marks <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid input. Exam, question text, and marks are required.']);
    $conn->close();
    exit;
}

/* ================= ACCESS CHECK ================= */
$stmt = $conn->prepare("
    SELECT 1
    FROM subject_assignments ea
    INNER JOIN exams e ON ea.subject_id = e.subject_id
    WHERE e.exam_id = ?
      AND ea.teacher_id = ?
      AND ea.role = 'item_writer'
");
$stmt->bind_param('ii', $exam_id, $user_id);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

/* ================= EXISTING QUESTIONS FOR DUPLICATE CHECK ================= */
$existing = [];
$stmt = $conn->prepare('SELECT question_text FROM questions WHERE exam_id = ?');
$stmt->bind_param('i', $exam_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $existing[] = $row['question_text'];
}
$stmt->close();
$conn->close();

/* ================= PYTHON AI — NO DATABASE INSERT ================= */
$ai = analyze_question_for_teacher($question_text, $marks, $existing);

echo json_encode([
    'success' => ($ai['status'] ?? '') !== 'failed',
    'ai' => $ai,
    'preview' => true,
    'message' => 'AI analysis complete. Review suggestions before saving.',
]);

?>
