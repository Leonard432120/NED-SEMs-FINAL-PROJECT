<?php
session_start();
include_once __DIR__ . '/../config/db.php';

/* ================= AUTH CHECK ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

/* ================= ROLE PERMISSION =================
   Allow both teacher AND admin
===================================================== */
$role = $_SESSION['role'] ?? '';

if (!in_array($role, ['teacher', 'admin'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

/* ================= USER ID ================= */
$user_id = $_SESSION['user_id'];

/* ================= SIDEBAR ACTIVE HELPER ================= */
function teacher_sidebar_active($page) {
    return basename($_SERVER['PHP_SELF']) === $page ? 'active' : '';
}
?>