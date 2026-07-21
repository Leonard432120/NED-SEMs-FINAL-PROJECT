<?php
require_once __DIR__ . '/../config/db.php';
$conn = get_db_connection();

function dump_table($conn, $table) {
    echo "=== Table: $table ===\n";
    $res = $conn->query("SHOW CREATE TABLE `$table`");
    if ($res) {
        $row = $res->fetch_row();
        echo $row[1] . "\n\n";
    } else {
        echo "Error: " . $conn->error . "\n\n";
    }
}

dump_table($conn, 'exams');
dump_table($conn, 'exam_subjects');
dump_table($conn, 'questions');
dump_table($conn, 'question_moderation');

$conn->close();
