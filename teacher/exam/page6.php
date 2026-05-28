<?php if (empty($pdf_mode)): ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Section C - Page 6</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/exam.css">
</head>
<body>
<?php endif; ?>

<div class="paper">

<div class="student-line">
NAME OF STUDENT: ________________________________ 
SECTION: _____________
</div>

<h2 class="center">SECTION C (CONTINUED)</h2>

<?php foreach ($page6_questions as $q): ?>
    <h3>
        <?= $q['question_order'] ?>. <?= htmlspecialchars($q['question_text']) ?>
        (<?= $q['marks'] ?> marks)
    </h3>
    <textarea class="lined" rows="12"></textarea>
<?php endforeach; ?>

<?php if (empty($pdf_mode)): ?>
<div class="nav no-print">
<a href="<?= BASE_URL ?>/teacher/exam.php?id=<?= $exam_id ?>&page=page5">
    <button>Previous</button>
</a>

<?php if (!empty($download_ready)): ?>
    <a href="<?= BASE_URL ?>/teacher/exam.php?action=download&id=<?= $exam_id ?>">
        <button style="background:green;color:white;">Download Full Paper</button>
    </a>
<?php else: ?>
    <div class="hint" style="margin-top:12px; color:#333; font-size:0.95rem;">
        View all pages first to unlock the full paper download.
    </div>
<?php endif; ?>
</div>
<?php endif; ?>

</div>

<?php if (empty($pdf_mode)): ?>
</body>
</html>
<?php endif; ?>