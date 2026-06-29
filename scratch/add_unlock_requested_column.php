<?php
require_once '../config/db.php';
$conn = get_db_connection();

$query = "ALTER TABLE marking_assignments ADD COLUMN unlock_requested TINYINT(1) NOT NULL DEFAULT 0 AFTER override_lock";
if ($conn->query($query)) {
    echo "Column 'unlock_requested' added successfully to 'marking_assignments' table.\n";
} else {
    echo "Error adding column: " . $conn->error . "\n";
}

$conn->close();
?>
