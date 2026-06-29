<?php
/**
 * Role-aware sidebar router.
 * Override with $sidebar_role before including if needed.
 */
$sidebar_role = $sidebar_role ?? ($_SESSION['role'] ?? 'admin');

$sidebar_map = [
    'admin' => 'admin.php',
    'headteacher' => 'headteacher.php',
    'teacher' => 'teacher.php',
    'examination_officer' => 'examination_officer.php',
];

$sidebar_file = $sidebar_map[$sidebar_role] ?? 'admin.php';
$sidebar_path = __DIR__ . '/sidebars/' . $sidebar_file;

if (is_file($sidebar_path)) {
    include $sidebar_path;
}
