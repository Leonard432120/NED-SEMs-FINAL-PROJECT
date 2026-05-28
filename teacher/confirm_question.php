<?php
require_once __DIR__ . '/teacher_init.php';

if (!isset($_POST['exam_id']) || !is_numeric($_POST['exam_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid exam ID']);
    exit;
}

$exam_id = (int)$_POST['exam_id'];

$conn = get_db_connection();

// Check access
$stmt = $conn->prepare("
    SELECT 1 FROM exam_assignments
    WHERE exam_id = ? AND teacher_id = ? AND role = 'item_writer'
");
$stmt->bind_param("ii", $exam_id, $user_id);
$stmt->execute();
if ($stmt->get_result()->num_rows == 0) {
    $conn->close();
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}
$stmt->close();

$question_text = trim($_POST['question_text'] ?? '');
$marks = (int)($_POST['marks'] ?? 0);
$section_name = $_POST['section_name'] ?? 'Section A';
$order = (int)($_POST['order'] ?? 1);
$option_a = $_POST['option_a'] ?? '';
$option_b = $_POST['option_b'] ?? '';
$option_c = $_POST['option_c'] ?? '';
$option_d = $_POST['option_d'] ?? '';
$correct_option = $_POST['correct_option'] ?? '';

if (empty($question_text) || $marks <= 0) {
    $conn->close();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid question data']);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO questions (
        exam_id, question_order, question_text, marks,
        created_by, section_name, question_type,
        option_a, option_b, option_c, option_d, correct_option,
        moderation_status
    )
    VALUES (?, ?, ?, ?, ?, ?, 'structured', ?, ?, ?, ?, ?, 'pending')
");
$stmt->bind_param("iisiissssss", $exam_id, $order, $question_text, $marks, $user_id, $section_name, $option_a, $option_b, $option_c, $option_d, $correct_option);
$stmt->execute();
$stmt->close();

$conn->close();

echo json_encode(['success' => true]);
?>