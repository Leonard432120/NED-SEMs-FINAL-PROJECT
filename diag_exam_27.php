<?php
require_once 'config/db.php';
$conn = get_db_connection();
$exam_id = 27;

// Find exam details
$exam = $conn->query("SELECT * FROM exams WHERE exam_id = $exam_id")->fetch_assoc();
echo "<h2>Exam 27</h2><pre>"; print_r($exam); echo "</pre>";

if ($exam) {
    // Find assignments for this exam's subject
    $subject_id = $exam['subject_id'] ?? 0;
    // Wait, let's get subject_id from exam_subjects too
    $es = $conn->query("SELECT * FROM exam_subjects WHERE exam_id = $exam_id")->fetch_all(MYSQLI_ASSOC);
    echo "<h2>Exam Subjects</h2><pre>"; print_r($es); echo "</pre>";

    if (!empty($es)) {
        $sub_ids = array_column($es, 'subject_id');
        $sub_list = implode(',', $sub_ids);
        $assigns = $conn->query("
            SELECT sa.*, u.name AS teacher_name, u.role AS user_role
            FROM subject_assignments sa
            JOIN users u ON sa.teacher_id = u.user_id
            WHERE sa.subject_id IN ($sub_list)
        ")->fetch_all(MYSQLI_ASSOC);
        echo "<h2>Subject Assignments</h2><pre>"; print_r($assigns); echo "</pre>";
    }
}
$conn->close();
