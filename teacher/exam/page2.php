<?php if (empty($pdf_mode)): ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Section A - Page 2</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/exam.css">
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

    <div><input type="radio"> A</div>
    <div><input type="radio"> B</div>
    <div><input type="radio"> C</div>
    <div><input type="radio"> D</div>
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