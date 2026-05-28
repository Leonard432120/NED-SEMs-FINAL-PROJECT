<?php if (empty($pdf_mode)): ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Section B</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/exam.css">
</head>
<body>
<?php endif; ?>

<div class="paper">

<div class="student-line">
NAME OF STUDENT: ________________________________ 
SECTION: _____________
</div>

<h2 class="center">SECTION B</h2>

<p><b>Answer all questions.</b></p>

<?php foreach ($page3_questions as $q): ?>
    <h3><?= $q['question_order'] ?>. <?= htmlspecialchars($q['question_text']) ?> (<?= $q['marks'] ?> marks)</h3>
    <textarea class="lined" rows="8"></textarea>
<?php endforeach; ?>

<?php if (empty($pdf_mode)): ?>
<div class="nav">
<a href="<?= BASE_URL ?>/teacher/exam.php?id=<?= $exam_id ?>&page=page2"><button>Back</button></a>
<a href="<?= BASE_URL ?>/teacher/exam.php?id=<?= $exam_id ?>&page=page4"><button>Next</button></a>
</div>
<?php endif; ?>

</div>

<?php if (empty($pdf_mode)): ?>
</body>
</html>
<?php endif; ?>