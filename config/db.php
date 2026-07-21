<?php
// Database connection configuration

if (!defined('BASE_URL')) {
    define('BASE_URL', '/NED-SEMs FINAL YEAR PROJECT');
}

function get_db_connection() {
    $host = 'localhost';
    $user = 'root';
    $password = '';
    $database = 'ned_sems';

    $conn = new mysqli($host, $user, $password, $database);

    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');

    return $conn;
}

require_once __DIR__ . '/audit_helper.php';
?>