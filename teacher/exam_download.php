<?php
require_once __DIR__ . '/teacher_init.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$conn = get_db_connection();

/* ================= GET EXAM ================= */
$exam_id = (int)($_GET['id'] ?? $_GET['exam_id'] ?? 0);
$user_id = $_SESSION['user_id'] ?? 0;
$user_name = $_SESSION['name'] ?? 'User';
$user_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if ($user_ip === '::1') { $user_ip = '127.0.0.1'; }
$current_time = date('Y-m-d H:i:s');

$stmt = $conn->prepare("
    SELECT e.*, s.subject_name
    FROM exams e
    LEFT JOIN subjects s ON e.subject_id = s.subject_id
    WHERE e.exam_id = ?
");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) {
    die("Exam not found");
}

/* ── Audit log recording ── */
if (function_exists('log_audit_event')) {
    log_audit_event('EXAM_PAPER_DOWNLOADED', [
        'exam_id' => $exam_id,
        'exam_name' => $exam['exam_name'],
        'downloaded_by' => $user_id,
        'user_name' => $user_name,
        'ip' => $user_ip
    ], null, $conn);
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
    $sec = strtoupper(trim($q['section_name'] ?? ''));

    if ($sec === "SECTION A") {
        $sectionA[] = $q;
    } elseif ($sec === "SECTION B") {
        $sectionB[] = $q;
    } else {
        $sectionC[] = $q;
    }
}
$qstmt->close();
$conn->close();

/* ── Determine if Forensic Watermark should be applied ──
   Apply watermark during drafting/moderation/preparation phase.
   Remove watermark when official exam writing date arrives or when sitting exam. */
$exam_status = strtolower($exam['status'] ?? 'draft');
$start_date = $exam['start_date'] ?? null;
$today = date('Y-m-d');
$is_official_writing_date = ($start_date && $today >= $start_date && $exam_status === 'active');
$candidate_mode = isset($_GET['candidate']) && $_GET['candidate'] == '1';

$show_forensic_watermark = (!$is_official_writing_date && !$candidate_mode);

/* ================= BUILD HTML ================= */
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; color: #000; }
h1, h2, h3 { text-align: center; margin: 5px 0; }

.section { margin-bottom: 25px; }
.q { margin-bottom: 14px; }
.options { margin-left: 18px; margin-top: 4px; }
.page-break { page-break-after: always; }

<?php if ($show_forensic_watermark): ?>
/* Forensic Watermark overlay for preparation/draft PDF */
@page {
    margin: 40px;
}
.pdf-watermark-bg {
    position: fixed;
    top: 30%;
    left: 5%;
    width: 90%;
    text-align: center;
    transform: rotate(-35deg);
    font-size: 11pt;
    font-weight: bold;
    color: rgba(180, 83, 9, 0.22);
    text-transform: uppercase;
    font-family: monospace;
    z-index: -1000;
}
<?php endif; ?>
</style>
</head>
<body>

<?php if ($show_forensic_watermark): ?>
<div class="pdf-watermark-bg">
    CONFIDENTIAL PREPARATION DRAFT — <?= htmlspecialchars($user_name) ?> (ID: <?= $user_id ?>) — IP: <?= $user_ip ?> — <?= $current_time ?>
</div>
<?php endif; ?>

<!-- ================= COVER PAGE ================= -->
<h1>NORTHERN EDUCATION DIVISION</h1>
<h2><?= htmlspecialchars($exam['exam_name']) ?></h2>

<p style="text-align:center;"><b>Subject:</b> <?= htmlspecialchars($exam['subject_name'] ?? 'General') ?> &nbsp;|&nbsp; <b>Year:</b> <?= htmlspecialchars($exam['year'] ?? date('Y')) ?></p>
<p style="text-align:center;"><b>Duration:</b> <?= $exam['duration_minutes'] ?? 120 ?> minutes &nbsp;|&nbsp; <b>Total Marks:</b> <?= $exam['total_marks'] ?? 100 ?></p>

<div class="page-break"></div>

<!-- ================= SECTION A ================= -->
<?php if (!empty($sectionA)): ?>
<h2>SECTION A (Multiple Choice Questions)</h2>
<?php foreach ($sectionA as $q): ?>
<div class="q">
    <b><?= $q['question_order'] ?>. <?= htmlspecialchars($q['question_text']) ?></b>
    <div class="options">
        A. <?= htmlspecialchars($q['option_a'] ?? '') ?><br>
        B. <?= htmlspecialchars($q['option_b'] ?? '') ?><br>
        C. <?= htmlspecialchars($q['option_c'] ?? '') ?><br>
        D. <?= htmlspecialchars($q['option_d'] ?? '') ?><br>
    </div>
</div>
<?php endforeach; ?>
<div class="page-break"></div>
<?php endif; ?>

<!-- ================= SECTION B ================= -->
<?php if (!empty($sectionB)): ?>
<h2>SECTION B</h2>
<?php foreach ($sectionB as $q): ?>
<div class="q">
    <b><?= $q['question_order'] ?>. <?= htmlspecialchars($q['question_text']) ?> (<?= $q['marks'] ?> marks)</b>
    <div style="border:1px dashed #666; height:70px; margin-top:6px;"></div>
</div>
<?php endforeach; ?>
<div class="page-break"></div>
<?php endif; ?>

<!-- ================= SECTION C ================= -->
<?php if (!empty($sectionC)): ?>
<h2>SECTION C</h2>
<?php foreach ($sectionC as $q): ?>
<div class="q">
    <b><?= $q['question_order'] ?>. <?= htmlspecialchars($q['question_text']) ?> (<?= $q['marks'] ?> marks)</b>
    <div style="border:1px dashed #666; height:120px; margin-top:6px;"></div>
</div>
<?php endforeach; ?>
<?php endif; ?>

</body>
</html>
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
$filename = "Exam_Paper_" . $exam_id . ".pdf";
$dompdf->stream($filename, ["Attachment" => true]);