<?php
if (!function_exists('log_audit_event')) {
    function log_audit_event($action, $details = null, $target_user_id = null, $custom_conn = null) {
        $conn = $custom_conn ?? (function_exists('get_db_connection') ? get_db_connection() : null);
        if (!$conn) {
            return false;
        }

        $user_id = $_SESSION['user_id'] ?? null;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        if (is_array($details) || is_object($details)) {
            $details_str = json_encode($details, JSON_UNESCAPED_UNICODE);
        } else {
            $details_str = (string)$details;
        }

        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, target_user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("iisss", $user_id, $target_user_id, $action, $details_str, $ip_address);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
    }
}
?>
