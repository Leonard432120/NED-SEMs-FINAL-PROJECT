<?php
session_start();
include_once 'config/db.php';

session_destroy();
header("Location: " . BASE_URL . "/login.php");
exit();
?>