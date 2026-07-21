<?php if (empty($pdf_mode)): ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Section A - Page 1</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/exam.css">

<style>
.paper-flex { display:block; width:100%; }
.col { width:100%; }
.divider { width:100%; height:1px; background:black; margin:10px 0; }
.q { margin-bottom:18px; page-break-inside:avoid; }
.option { margin-left:15px; margin-top:3px; }
</style>
</head>
<body>
<?php else: ?>
<style>
.paper-flex { display:block; width:100%; }
.col { width:100%; }
.divider { width:100%; height:1px; background:black; margin:10px 0; }
.q { margin-bottom:18px; page-break-inside:avoid; }
.option { margin-left:15px; margin-top:3px; }
</style>
<?php endif; ?>

<div class="paper">

<div class="student-line">
NAME OF STUDENT: ________________________________ 
SECTION: _____________
</div>

<h2 class="center">SECTION A (MULTIPLE CHOICE)</h2>
<p><b>Answer all questions.</b></p>

<div class="paper-flex">

<div class="col">
<?php foreach ($page1_questions as $q): ?>
    <div class="q">
        <b><?= (int)$q['question_order'] ?>. <?= htmlspecialchars($q['question_text'] ?? '') ?></b>

        <div class="option">A. <?= htmlspecialchars($q['option_a'] ?? '') ?></div>
        <div class="option">B. <?= htmlspecialchars($q['option_b'] ?? '') ?></div>
        <div class="option">C. <?= htmlspecialchars($q['option_c'] ?? '') ?></div>
        <div class="option">D. <?= htmlspecialchars($q['option_d'] ?? '') ?></div>
    </div>
<?php endforeach; ?>
</div>

</div>

<br>

<?php if (empty($pdf_mode)): ?>
<div class="nav">
<a href="<?= BASE_URL ?>/teacher/exam.php?id=<?= $exam_id ?>&page=cover"><button>Back</button></a>
<a href="<?= BASE_URL ?>/teacher/exam.php?id=<?= $exam_id ?>&page=page2"><button>Next</button></a>
</div>
<?php endif; ?>

</div>

<?php if (empty($pdf_mode)): ?>
</body>
</html>
<?php endif; ?>