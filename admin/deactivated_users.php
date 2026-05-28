<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

/* ================= ACTIVATE USER ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_user'])) {

    $user_id = (int)$_POST['user_id'];

    $stmt = $conn->prepare("
        UPDATE users 
        SET status = 'active',
            deleted_at = NULL,
            deleted_by = NULL
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    $log = $conn->prepare("
        INSERT INTO audit_logs (user_id, action, details)
        VALUES (?, 'activate_user', ?)
    ");
    $details = "Reactivated user ID $user_id";
    $log->bind_param("is", $_SESSION['user_id'], $details);
    $log->execute();
    $log->close();

    header("Location: manage_users.php?msg=activated");
    exit();
}

/* ================= USERS ================= */
$result = $conn->query("
    SELECT * FROM users
    WHERE status = 'inactive'
    ORDER BY user_id DESC
");

$users = $result->fetch_all(MYSQLI_ASSOC);
?>