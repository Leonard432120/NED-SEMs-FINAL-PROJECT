<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

$school_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($school_id > 0) {
    $conn = get_db_connection();
    $stmt = $conn->prepare("UPDATE schools SET status = 'deleted' WHERE school_id = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

header("Location: " . BASE_URL . "/admin/manage_schools.php");
exit();
