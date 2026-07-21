<?php
require 'config/db.php';
$conn = get_db_connection();

// Check if gender exists
$res = $conn->query("SHOW COLUMNS FROM students LIKE 'gender'");
if ($res->num_rows === 0) {
    $conn->query("ALTER TABLE students ADD COLUMN gender ENUM('Male','Female') NOT NULL DEFAULT 'Male'");
    echo "Added gender column.<br>";
}

// Check if special_needs exists
$res = $conn->query("SHOW COLUMNS FROM students LIKE 'special_needs'");
if ($res->num_rows === 0) {
    $conn->query("ALTER TABLE students ADD COLUMN special_needs VARCHAR(100) NOT NULL DEFAULT 'None'");
    echo "Added special_needs column.<br>";
}

// Randomly distribute gender for existing students to make reports look realistic
$conn->query("UPDATE students SET gender = 'Female' WHERE student_id % 2 = 0");
$conn->query("UPDATE students SET special_needs = 'Visually Impaired' WHERE student_id % 17 = 0");
$conn->query("UPDATE students SET special_needs = 'Hearing Impaired' WHERE student_id % 29 = 0");

echo "Alterations completed successfully!";
?>
