<?php
session_start();
include_once 'config/db.php';

log_audit_event('USER_LOGOUT');
session_destroy();
header("Location: " . BASE_URL . "/login.php");
exit();
?>