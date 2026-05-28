<?php
require_once __DIR__ . '/teacher_init.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$conn = get_db_connection();

/* ================= GET EXAM ================= */
$exam_id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT e.*, s.subject_name
    FROM exams e
    JOIN subjects s ON e.subject_id = s.subject_id
    WHERE e.exam_id = ?
");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();

if (!$exam) {
    die("Exam not found");
}

/* ================= GET QUESTIONS ================= */
$qstmt = $conn->prepare("
    SELECT *
    FROM questions
    WHERE exam_id = ?
    ORDER BY question_order ASC
");
$qstmt->bind_param("i", $exam_id);
$qstmt->execute();
$res = $qstmt->get_result();

$sectionA = [];
$sectionB = [];
$sectionC = [];

while ($q = $res->fetch_assoc()) {
    $sec = strtoupper(trim($q['section_name']));

    if ($sec === "SECTION A") {
        $sectionA[] = $q;
    } elseif ($sec === "SECTION B") {
        $sectionB[] = $q;
    } else {
        $sectionC[] = $q;
    }
}

/* ================= BUILD HTML ================= */
ob_start();
?>

<style>
body { font-family: Arial; font-size: 12px; }
h1, h2, h3 { text-align: center; }

.section { margin-bottom: 25px; }

.q {
    margin-bottom: 12px;
}

.options {
    margin-left: 15px;
}

.page-break {
    page-break-after: always;
}
</style>

<!-- ================= COVER PAGE ================= -->
<h1>NORTHERN EDUCATION DIVISION</h1>
<h2><?= htmlspecialchars($exam['exam_name']) ?></h2>

<p><b>Subject:</b> <?= htmlspecialchars($exam['subject_name']) ?></p>
<p><b>Year:</b> <?= htmlspecialchars($exam['year']) ?></p>
<p><b>Duration:</b> <?= $exam['duration_minutes'] ?? 120 ?> minutes</p>
<p><b>Total Marks:</b> <?= $exam['total_marks'] ?? 100 ?></p>

<div class="page-break"></div>

<!-- ================= SECTION A ================= -->
<h2>SECTION A (MCQ)</h2>

<?php foreach ($sectionA as $q): ?>
<div class="q">
    <b><?= $q['question_order'] ?>. <?= $q['question_text'] ?></b>

    <div class="options">
        A. <?= $q['option_a'] ?><br>
        B. <?= $q['option_b'] ?><br>
        C. <?= $q['option_c'] ?><br>
        D. <?= $q['option_d'] ?><br>
    </div>
</div>
<?php endforeach; ?>

<div class="page-break"></div>

<!-- ================= SECTION B ================= -->
<h2>SECTION B</h2>

<?php foreach ($sectionB as $q): ?>
<div class="q">
    <b><?= $q['question_order'] ?>. <?= $q['question_text'] ?> (<?= $q['marks'] ?> marks)</b>
    <div style="border:1px solid #000; height:80px;"></div>
</div>
<?php endforeach; ?>

<div class="page-break"></div>

<!-- ================= SECTION C ================= -->
<h2>SECTION C</h2>

<?php foreach ($sectionC as $q): ?>
<div class="q">
    <b><?= $q['question_order'] ?>. <?= $q['question_text'] ?> (<?= $q['marks'] ?> marks)</b>
    <div style="border:1px solid #000; height:140px;"></div>
</div>
<?php endforeach; ?>

<?php
$html = ob_get_clean();

/* ================= GENERATE PDF ================= */
$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

/* ================= DOWNLOAD ================= */
$dompdf->stream("Exam_Paper_{$exam_id}.pdf", ["Attachment" => true]);