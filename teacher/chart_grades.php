<?php
require_once __DIR__ . '/teacher_init.php';

if (!isset($_GET['exam_id']) || !is_numeric($_GET['exam_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid exam ID']);
    exit;
}

$exam_id = (int)$_GET['exam_id'];

$conn = get_db_connection();

// Check access
$check_stmt = $conn->prepare("
    SELECT 1 FROM exam_assignments
    WHERE exam_id = ? AND teacher_id = ? AND role IN ('item_writer', 'moderator')
");
$check_stmt->bind_param("ii", $exam_id, $user_id);
$check_stmt->execute();
if ($check_stmt->get_result()->num_rows == 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}
$check_stmt->close();

// Grade distribution
$grade_stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN percentage >= 75 THEN 1 ELSE 0 END) A,
        SUM(CASE WHEN percentage >= 65 AND percentage < 75 THEN 1 ELSE 0 END) B,
        SUM(CASE WHEN percentage >= 50 AND percentage < 65 THEN 1 ELSE 0 END) C,
        SUM(CASE WHEN percentage >= 40 AND percentage < 50 THEN 1 ELSE 0 END) D,
        SUM(CASE WHEN percentage < 40 THEN 1 ELSE 0 END) F
    FROM results
    WHERE exam_id = ?
");
$grade_stmt->bind_param("i", $exam_id);
$grade_stmt->execute();
$data = $grade_stmt->get_result()->fetch_assoc();
$grade_stmt->close();

$conn->close();

header('Content-Type: application/json');
echo json_encode($data ?: ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0]);
?>