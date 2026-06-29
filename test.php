<?php
require 'config/db.php';
$conn = get_db_connection();

$q = "SELECT e.exam_id, e.exam_name,
           (SELECT COUNT(subject_id) FROM exam_subjects es WHERE es.exam_id = e.exam_id) as subject_count
    FROM exams e 
    ORDER BY e.exam_name";

$res = $conn->query($q);
if (!$res) {
    echo "ERROR: " . $conn->error;
} else {
    echo "SUCCESS: Found " . $res->num_rows . " rows. \n";
    while($row = $res->fetch_assoc()) {
        print_r($row);
    }
}
