<?php if (empty($pdf_mode)): ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Section A - Page 2</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/exam.css">
<style>
.q { margin-bottom:18px; }
.option { margin-left:15px; margin-top:3px; }
</style>
</head>
<body>
<?php endif; ?>

<div class="paper">

<div class="student-line">
NAME OF STUDENT: ________________________________ 
SECTION: _____________
</div>

<h2 class="center">SECTION A (CONTINUED)</h2>

<?php foreach ($page2_questions as $q): ?>
<div class="q">
    <b><?= $q['question_order'] ?>. <?= htmlspecialchars($q['question_text']) ?></b>

    <div class="option">A. <?= htmlspecialchars($q['option_a'] ?? '') ?></div>
    <div class="option">B. <?= htmlspecialchars($q['option_b'] ?? '') ?></div>
    <div class="option">C. <?= htmlspecialchars($q['option_c'] ?? '') ?></div>
    <div class="option">D. <?= htmlspecialchars($q['option_d'] ?? '') ?></div>
</div>
<?php endforeach; ?>

<?php if (empty($pdf_mode)): ?>
<div class="nav">
<a href="<?= BASE_URL ?>/teacher/exam.php?id=<?= $exam_id ?>&page=page1"><button>Previous</button></a>
<a href="<?= BASE_URL ?>/teacher/exam.php?id=<?= $exam_id ?>&page=page3"><button>Next Section</button></a>
</div>
<?php endif; ?>

</div>

<?php if (empty($pdf_mode)): ?>
</body>
</html>
<?php endif; ?>