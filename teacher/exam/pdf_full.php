<?php
// this file builds ONE long HTML exam paper for Dompdf

ob_start(); // capture all HTML output

$pdf_mode = true;

$css_file = __DIR__ . '/../../static/css/exam.css';
$global_css = file_exists($css_file) ? file_get_contents($css_file) : '';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Full Exam Paper</title>
<style>
<?= $global_css ?>
</style>
</head>
<body>

<!-- COVER -->
<?php include __DIR__ . '/cover.php'; ?>

<div style="page-break-after:always;"></div>

<!-- PAGE 1 -->
<?php include __DIR__ . '/page1.php'; ?>

<div style="page-break-after:always;"></div>

<!-- PAGE 2 -->
<?php include __DIR__ . '/page2.php'; ?>

<div style="page-break-after:always;"></div>

<!-- PAGE 3 -->
<?php include __DIR__ . '/page3.php'; ?>

<div style="page-break-after:always;"></div>

<!-- PAGE 4 -->
<?php include __DIR__ . '/page4.php'; ?>

<div style="page-break-after:always;"></div>

<!-- PAGE 5 -->
<?php include __DIR__ . '/page5.php'; ?>

<div style="page-break-after:always;"></div>

<!-- PAGE 6 -->
<?php include __DIR__ . '/page6.php'; ?>

</body>
</html>
<?php
// return the final HTML back to exam.php
$html = ob_get_clean();
echo $html;
?>