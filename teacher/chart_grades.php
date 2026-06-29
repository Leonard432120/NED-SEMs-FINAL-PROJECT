<?php
/* ════════════════════════════════════════════════════════════════
   teacher/chart_grades.php
   Teacher: yields JSON grade distribution for school-specific chart
   ════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/teacher_init.php';

if (!isset($_GET['exam_id']) || !is_numeric($_GET['exam_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid exam ID']);
    exit;
}

$exam_id    = (int)$_GET['exam_id'];
$teacher_id = (int)$_SESSION['user_id'];
$school_id  = (int)($_SESSION['school_id'] ?? 0);

$conn = get_db_connection();

// Check access: does teacher have assignments in this exam?
$check_stmt = $conn->prepare("
    SELECT 1 
    FROM subject_assignments sa
    JOIN exam_subjects es ON sa.subject_id = es.subject_id
    WHERE es.exam_id = ? AND sa.teacher_id = ?
");
$check_stmt->bind_param("ii", $exam_id, $teacher_id);
$check_stmt->execute();
if ($check_stmt->get_result()->num_rows == 0) {
    $check_stmt->close();
    $conn->close();
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}
$check_stmt->close();

// Grade distribution for this school and exam
$grade_stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN r.average_score >= 75 THEN 1 ELSE 0 END) A,
        SUM(CASE WHEN r.average_score >= 65 AND r.average_score < 75 THEN 1 ELSE 0 END) B,
        SUM(CASE WHEN r.average_score >= 50 AND r.average_score < 65 THEN 1 ELSE 0 END) C,
        SUM(CASE WHEN r.average_score >= 40 AND r.average_score < 50 THEN 1 ELSE 0 END) D,
        SUM(CASE WHEN r.average_score < 40 THEN 1 ELSE 0 END) F
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    WHERE r.exam_id = ? AND s.school_id = ?
");
$grade_stmt->bind_param("ii", $exam_id, $school_id);
$grade_stmt->execute();
$data = $grade_stmt->get_result()->fetch_assoc();
$grade_stmt->close();

$conn->close();

header('Content-Type: application/json');
echo json_encode($data ?: ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0]);
?>