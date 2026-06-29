<?php
/* ════════════════════════════════════════════════════════════════
   teacher/chart_subjects.php
   Teacher: yields JSON subject averages in the school for charts
   ════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/teacher_init.php';

$school_id = (int)($_SESSION['school_id'] ?? 0);
$conn = get_db_connection();

// Calculate average score for each subject in the teacher's school
$subject_stmt = $conn->prepare("
    SELECT s.subject_name, AVG((m.score / es.total_marks) * 100) AS avg_score
    FROM marks m
    JOIN subjects s ON m.subject_id = s.subject_id
    JOIN exam_subjects es ON m.exam_id = es.exam_id AND m.subject_id = es.subject_id
    JOIN students st ON m.student_id = st.student_id
    WHERE st.school_id = ? AND m.status IN ('submitted', 'approved')
    GROUP BY s.subject_name
    ORDER BY avg_score DESC
");
$subject_stmt->bind_param("i", $school_id);
$subject_stmt->execute();
$data = $subject_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$subject_stmt->close();

$conn->close();

header('Content-Type: application/json');
echo json_encode($data);
?>