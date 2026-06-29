<?php
/**
 * Standard stylesheet bundle for all authenticated dashboard pages.
 * Set $module_css before including: admin | teacher | headteacher | exam_officer
 * Set $portal_title before including header.php.
 */
if (!isset($module_css)) {
    $module_map = [
        'admin' => 'admin',
        'teacher' => 'teacher',
        'headteacher' => 'headteacher',
        'examination_officer' => 'exam_officer',
    ];
    $module_css = $module_map[$_SESSION['role'] ?? 'admin'] ?? 'admin';
}
$allowed_modules = ['admin', 'teacher', 'headteacher', 'exam_officer'];
if (!in_array($module_css, $allowed_modules, true)) {
    $module_css = 'admin';
}
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/base.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/layout.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/components.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/<?= htmlspecialchars($module_css) ?>.css">
