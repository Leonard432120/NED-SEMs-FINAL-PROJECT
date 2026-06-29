<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

function log_exam_activity($conn, $admin_id, $exam_id, $action, $details) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, target_user_id, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $admin_id, $exam_id, $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
}

$message = '';
$message_type = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_ids = [];

    // Single delete
    if (!empty($_POST['exam_id'])) {
        $exam_ids[] = (int)$_POST['exam_id'];
    }

    // Bulk delete
    if (!empty($_POST['selected_exams']) && is_array($_POST['selected_exams'])) {
        $exam_ids = array_map('intval', $_POST['selected_exams']);
    }

    if (empty($exam_ids)) {
        $message = "No exam selected.";
    } else {
        $success = 0;
        $admin_id = $_SESSION['user_id'];

        foreach ($exam_ids as $id) {
            if ($id <= 0) continue;

            $stmt = $conn->prepare("SELECT exam_name FROM exams WHERE exam_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $exam = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($exam) {
                $stmt = $conn->prepare("DELETE FROM exams WHERE exam_id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $success++;
                    log_exam_activity($conn, $admin_id, $id, "delete_exam", "Deleted exam ID $id: {$exam['exam_name']}");
                }
                $stmt->close();
            }
        }

        if ($success > 0) {
            $message = $success > 1 ? "$success exams deleted successfully." : "Exam deleted successfully.";
            $message_type = "success";
        } else {
            $message = "Failed to delete exam(s).";
        }
    }
}

if (!empty($message)) {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $message_type;
}

$conn->close();
header("Location: exams.php");
exit();
?>