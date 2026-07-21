<?php
/*
 * common/get_student_details.php
 *
 * AJAX endpoint to fetch unified student details view.
 * Accessible to any authenticated user.
 */
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit("Unauthorized");
}

$conn = get_db_connection();
$student_id = (int)($_GET['student_id'] ?? 0);
$show_print = isset($_GET['show_print']) && $_GET['show_print'] === 'true';

// Include the unified reusable view
include __DIR__ . '/student_details_view.php';

$conn->close();
