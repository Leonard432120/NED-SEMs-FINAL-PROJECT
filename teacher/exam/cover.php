<?php if (empty($pdf_mode)): ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($exam['exam_name'] ?? '') ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/exam.css">

<style>
.exam-table td.no-border {
    border-left: none !important;
    border-bottom: none !important;
}
</style>
</head>
<body>
<?php else: ?>
<style>
.exam-table td.no-border {
    border-left: none !important;
    border-bottom: none !important;
}
</style>
<?php endif; ?>

<div class="paper">

<!-- ================= STUDENT LINE ================= -->
<div class="student-line">
NAME OF STUDENT: ________________________________ 
SECTION: _____________
</div>

<!-- ================= HEADER ================= -->
<div class="header">

    <div class="logo-wrap">
        <img src="<?= (!empty($pdf_mode) && !empty($pdf_img_path)) ? $pdf_img_path : BASE_URL . '/static/images/NED.jpg' ?>" class="logo">
    </div>

    <div class="header-text">
        <h2>NORTHERN EDUCATION DIVISION EXAMINATIONS</h2>

        <h3>
            <?= htmlspecialchars($exam['year'] ?? date("Y")) ?>
            MALAWI SCHOOL CERTIFICATE OF EDUCATION MOCK EXAMINATION
        </h3>

        <h2 class="subject">
            <?= htmlspecialchars($exam['subject_name'] ?? '') ?>
        </h2>
    </div>

</div>

<!-- ================= TITLE ================= -->
<h1 class="center">PAPER II</h1>

<h3 class="center">
    (<?= (int)($exam['total_marks'] ?? 0) ?> marks)
</h3>

<!-- ================= BODY ================= -->
<div class="two-columns">

    <!-- ================= INSTRUCTIONS ================= -->
    <div class="instructions">
        <h3>Instructions</h3>

        <p>This paper contains 8 pages. Please check.</p>
        <p>Answer all questions in all sections.</p>
        <p>Write your Name and Section on each page.</p>
        <p>Tick the questions you answer.</p>
        <p>Hand in when time is called.</p>
    </div>

    <!-- ================= TABLE AREA ================= -->
    <div class="table-area">

        <div class="top-right-info">
            <b>Subject:</b>
            <?= htmlspecialchars($exam['subject_code'] ?? 'M192/II') ?>
            <br>

            <b>Time:</b>
            <?= (int)($exam['duration_minutes'] ?? 120) ?> minutes
        </div>

        <table class="exam-table">

            <tr>
                <th>Question Number</th>
                <th>Tick</th>
                <th colspan="2">Do not write in these margins</th>
            </tr>

            <?php for ($i = 1; $i <= 9; $i++): ?>
            <tr>
                <td><?= $i ?></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <?php endfor; ?>

            <tr class="total-row">
                <td class="no-border"></td>
                <td><b>TOTAL</b></td>
                <td></td>
                <td></td>
            </tr>

        </table>

    </div>
</div>

<!-- ================= NAVIGATION ================= -->
<?php if (empty($pdf_mode)): ?>
<div class="nav no-print">
    <a href="<?= BASE_URL ?>/teacher/exam.php?id=<?= $exam_id ?>&page=page1">
        <button>Start Exam</button>
    </a>

    <?php if (!empty($download_ready)): ?>
        <a href="<?= BASE_URL ?>/teacher/exam.php?action=download&id=<?= $exam_id ?>">
            <button style="background:green;color:white;">Download Full Paper</button>
        </a>
    <?php else: ?>
        <div class="hint" style="margin-top:12px; color:#333; font-size:0.95rem;">
            View all question pages first to enable the full paper download.
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

</div>

<?php if (empty($pdf_mode)): ?>
</body>
</html>
<?php endif; ?>