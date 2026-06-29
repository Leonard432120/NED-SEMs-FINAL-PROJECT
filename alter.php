<?php
require 'config/db.php';
$conn = get_db_connection();
$conn->query("ALTER TABLE exams ADD COLUMN marks_deadline DATETIME NULL");
echo "Done";
