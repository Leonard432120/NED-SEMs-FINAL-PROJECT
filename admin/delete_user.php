<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

$user_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($user_id > 0) {

    /* ================= SOFT DELETE ================= */
    $stmt = $conn->prepare("
        UPDATE users 
        SET status = 'deleted',
            deleted_at = NOW(),
            deleted_by = ?
        WHERE user_id = ?
    ");

    $stmt->bind_param("ii", $_SESSION['user_id'], $user_id);
    $stmt->execute();
    $stmt->close();

    /* ================= AUDIT LOG ================= */
    $log = $conn->prepare("
        INSERT INTO audit_logs (user_id, action, details)
        VALUES (?, 'delete_user', ?)
    ");

    $details = "Deleted user ID {$user_id}";
    $log->bind_param("is", $_SESSION['user_id'], $details);
    $log->execute();
    $log->close();
}

$conn->close();

header("Location: manage_users.php?deleted=1");
exit();
?>