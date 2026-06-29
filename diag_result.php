<?php
// Quick diagnostic — visit this page then delete it
require_once 'config/db.php';
$conn = get_db_connection();

$result_id = 117;

// Get result info
$r = $conn->query("SELECT * FROM results WHERE result_id = $result_id")->fetch_assoc();
echo "<h2>Result #$result_id</h2><pre>"; print_r($r); echo "</pre>";

if ($r) {
    // Check marks for this student+exam with ALL statuses
    $marks = $conn->query("
        SELECT m.mark_id, s.subject_name, m.score, m.status, m.grade
        FROM marks m
        JOIN subjects s ON s.subject_id = m.subject_id
        WHERE m.student_id = {$r['student_id']} AND m.exam_id = {$r['exam_id']}
    ")->fetch_all(MYSQLI_ASSOC);
    echo "<h2>Marks (all statuses)</h2><pre>"; print_r($marks); echo "</pre>";

    // Check exam_subjects
    $es = $conn->query("SELECT * FROM exam_subjects WHERE exam_id = {$r['exam_id']}")->fetch_all(MYSQLI_ASSOC);
    echo "<h2>Exam Subjects</h2><pre>"; print_r($es); echo "</pre>";
}
$conn->close();
