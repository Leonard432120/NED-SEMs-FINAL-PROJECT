<?php
require_once __DIR__ . '/teacher_init.php';
require_once __DIR__ . '/../services/ai/compose_bridge.php';

header('Content-Type: application/json');

$conn = get_db_connection();
$user_id = $_SESSION['user_id'] ?? 0;

$ai_payload_raw = $_POST['ai_data'] ?? '';
$ai_data = json_decode($ai_payload_raw, true);
if (!is_array($ai_data)) {
    $ai_data = [];
}

$result = compose_save_question($conn, $user_id, $_POST, $ai_data);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
$conn->close();
