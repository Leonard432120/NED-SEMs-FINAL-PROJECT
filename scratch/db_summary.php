<?php
require_once __DIR__ . '/../config/db.php';
$conn = get_db_connection();

echo "=== SCHOOLS ===\n";
$r = $conn->query("SELECT school_id, school_name, district, school_type, status FROM schools");
while ($row = $r->fetch_assoc()) {
    echo "ID: {$row['school_id']} | Name: {$row['school_name']} | District: {$row['district']} | Type: {$row['school_type']} | Status: {$row['status']}\n";
}

echo "\n=== EXAMS ===\n";
$r = $conn->query("SELECT exam_id, exam_name, exam_code, status, class, year FROM exams");
while ($row = $r->fetch_assoc()) {
    echo "ID: {$row['exam_id']} | Name: {$row['exam_name']} | Code: {$row['exam_code']} | Status: {$row['status']} | Class: {$row['class']} | Year: {$row['year']}\n";
}

echo "\n=== RESULTS STATUS COUNTS ===\n";
$r = $conn->query("SELECT exam_id, status, COUNT(*) as cnt FROM results GROUP BY exam_id, status");
while ($row = $r->fetch_assoc()) {
    echo "Exam ID: {$row['exam_id']} | Status: {$row['status']} | Count: {$row['cnt']}\n";
}

echo "\n=== MARKS STATUS COUNTS ===\n";
$r = $conn->query("SELECT exam_id, status, submission_status, COUNT(*) as cnt FROM marks GROUP BY exam_id, status, submission_status");
while ($row = $r->fetch_assoc()) {
    echo "Exam ID: {$row['exam_id']} | Status: {$row['status']} | Submission Status: {$row['submission_status']} | Count: {$row['cnt']}\n";
}

echo "\n=== USER ROLES ===\n";
$r = $conn->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role");
while ($row = $r->fetch_assoc()) {
    echo "Role: {$row['role']} | Count: {$row['cnt']}\n";
}

$conn->close();
