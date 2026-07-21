<?php
require_once __DIR__ . '/config/db.php';
$conn = get_db_connection();

echo "=== EXAM 27 ===\n";
$r = $conn->query("SELECT exam_id, exam_name, status FROM exams WHERE exam_id = 27");
if ($row = $r->fetch_assoc()) {
    print_r($row);
}

echo "\n=== QUESTIONS FOR EXAM 27 ===\n";
$r = $conn->query("SELECT question_id, moderation_status, moderator_comment FROM questions WHERE exam_id = 27");
while ($row = $r->fetch_assoc()) {
    print_r($row);
}

echo "\n=== MODERATION TASKS QUERY SIMULATION ===\n";
$user_id = 2; // Let's check what user ID we are simulating, or we can fetch for all moderators.
$query = "
    SELECT DISTINCT
        e.exam_id,
        e.exam_name,
        e.status AS exam_status,
        COUNT(q.question_id) AS total_q,
        SUM(q.moderation_status = 'approved') AS approved_q,
        SUM(q.moderation_status = 'revise') AS revise_q,
        SUM(q.moderation_status = 'rejected') AS rejected_q,
        SUM(q.moderation_status = 'pending' OR q.moderation_status IS NULL) AS pending_q
    FROM subject_assignments ea
    INNER JOIN exam_subjects es ON ea.subject_id = es.subject_id
    INNER JOIN exams e ON es.exam_id = e.exam_id
    LEFT JOIN questions q ON q.exam_id = e.exam_id
    WHERE e.exam_id = 27
    GROUP BY e.exam_id
";
$r = $conn->query($query);
if ($row = $r->fetch_assoc()) {
    print_r($row);
}
$conn->close();
