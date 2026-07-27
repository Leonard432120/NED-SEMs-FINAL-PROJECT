<?php
require_once __DIR__ . '/teacher_init.php';

/* ===============================
   1. CONNECT DATABASE
================================ */
$conn = get_db_connection();
if (!$conn) {
    die("Database connection failed");
}

/* ===============================
   2. VALIDATE EXAM ID
================================ */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid exam ID");
}

$exam_id = (int)$_GET['id'];
$page    = $_GET['page']   ?? 'cover';
$action  = $_GET['action'] ?? '';

/* ===============================
   3. FETCH EXAM DETAILS
================================ */
$subject_id_clause = "";
$params = [$exam_id];
$types = "i";
if (isset($_GET['subject_id']) && is_numeric($_GET['subject_id'])) {
    $subject_id_clause = " AND es.subject_id = ? ";
    $params[] = (int)$_GET['subject_id'];
    $types .= "i";
}

$stmt = $conn->prepare("
    SELECT e.*, s.subject_name, es.duration_minutes, es.total_marks
    FROM exams e
    LEFT JOIN exam_subjects es ON e.exam_id = es.exam_id
    LEFT JOIN subjects s ON es.subject_id = s.subject_id
    WHERE e.exam_id = ? {$subject_id_clause}
    LIMIT 1
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();

if (!$exam) {
    die("Exam not found");
}

/* safe defaults */
$exam['year']             = $exam['year'] ?? date("Y");
$exam['duration_minutes'] = $exam['duration_minutes'] ?? 120;
$exam['total_marks']      = $exam['total_marks'] ?? 100;
$exam['subject_code']     = strtoupper(substr($exam['subject_name'],0,3)) . "-MSCE";

/* ===============================
   4. FETCH QUESTIONS — scoped to subject if subject_id is given
================================ */
$subject_id_get = isset($_GET['subject_id']) && is_numeric($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

if ($subject_id_get > 0) {
    // Resolve exam_subject_id from subject_id
    $es_stmt = $conn->prepare("SELECT id FROM exam_subjects WHERE exam_id = ? AND subject_id = ? LIMIT 1");
    $es_stmt->bind_param("ii", $exam_id, $subject_id_get);
    $es_stmt->execute();
    $es_row = $es_stmt->get_result()->fetch_assoc();
    $es_stmt->close();
    $esi = $es_row ? (int)$es_row['id'] : 0;

    if ($esi > 0) {
        $qstmt = $conn->prepare("
            SELECT *
            FROM questions
            WHERE exam_subject_id = ?
            ORDER BY question_order ASC
        ");
        $qstmt->bind_param("i", $esi);
    } else {
        $qstmt = $conn->prepare("
            SELECT *
            FROM questions
            WHERE exam_id = ?
            ORDER BY question_order ASC
        ");
        $qstmt->bind_param("i", $exam_id);
    }
} else {
    $qstmt = $conn->prepare("
        SELECT *
        FROM questions
        WHERE exam_id = ?
        ORDER BY question_order ASC
    ");
    $qstmt->bind_param("i", $exam_id);
}
$qstmt->execute();
$qres = $qstmt->get_result();

$questions = [];
while ($row = $qres->fetch_assoc()) {
    $questions[] = $row;
}

/* ===============================
   5. SPLIT QUESTIONS BY SECTION_NAME
================================ */
$sectionA = [];
$sectionB = [];
$sectionC = [];

foreach ($questions as $q) {
    $sec = trim($q['section_name'] ?? '');
    if (stripos($sec, 'Section A') !== false) {
        $sectionA[] = $q;
    } elseif (stripos($sec, 'Section B') !== false) {
        $sectionB[] = $q;
    } elseif (stripos($sec, 'Section C') !== false) {
        $sectionC[] = $q;
    } else {
        // Default fallback based on question type
        if (($q['question_type'] ?? '') === 'mcq') {
            $sectionA[] = $q;
        } elseif (($q['question_type'] ?? '') === 'essay') {
            $sectionC[] = $q;
        } else {
            $sectionB[] = $q;
        }
    }
}

/* Split for pages */
$halfA = count($sectionA) > 0 ? (int)ceil(count($sectionA) / 2) : 0;
$page1_questions = array_slice($sectionA, 0, $halfA);
$page2_questions = array_slice($sectionA, $halfA);

$halfB = count($sectionB) > 0 ? (int)ceil(count($sectionB) / 2) : 0;
$page3_questions = array_slice($sectionB, 0, $halfB);
$page4_questions = array_slice($sectionB, $halfB);

$halfC = count($sectionC) > 0 ? (int)ceil(count($sectionC) / 2) : 0;
$page5_questions = array_slice($sectionC, 0, $halfC);
$page6_questions = array_slice($sectionC, $halfC);

/* page6 may contain section C continuation or extra writing space */

/* ===============================
   6. PROGRESS TRACKING
================================ */
$question_pages = ['page1', 'page2', 'page3', 'page4', 'page5', 'page6'];

if (!isset($_SESSION['exam_progress'])) {
    $_SESSION['exam_progress'] = [];
}

if (!isset($_SESSION['exam_progress'][$exam_id])) {
    $_SESSION['exam_progress'][$exam_id] = [];
}

if (in_array($page, $question_pages, true)) {
    if (!in_array($page, $_SESSION['exam_progress'][$exam_id], true)) {
        $_SESSION['exam_progress'][$exam_id][] = $page;
    }
}

$download_ready = count($questions) > 0;

/* ===============================
   7. DOWNLOAD FULL EXAM PDF
================================ */
if ($action === 'download') {
    if (count($questions) === 0) {
        die("Please add questions before downloading the exam paper.");
    }

    log_audit_event('EXAM_PAPER_DOWNLOADED', ['exam_id' => $exam_id, 'exam_name' => $exam['exam_name'] ?? ''], null, $conn);

    require_once __DIR__ . '/../vendor/autoload.php';
    $options = new Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $dompdf = new Dompdf\Dompdf($options);

    ob_start();
    include __DIR__ . "/exam/pdf_full.php";
    $html = ob_get_clean();

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream("Exam_{$exam_id}.pdf", ["Attachment" => true]);
    exit;
}

/* ===============================
   8. ALLOWED PAGES (NOW UP TO 6)
================================ */
$allowed_pages = [
    'cover',
    'page1',
    'page2',
    'page3',
    'page4',
    'page5',
    'page6'
];

if (!in_array($page, $allowed_pages, true)) {
    $page = 'cover';
}

/* ===============================
   9. LOAD PAGE TEMPLATE
================================ */
$page_file = __DIR__ . "/exam/$page.php";

if (!file_exists($page_file)) {
    die("Page file missing: $page");
}

/* ── FORENSIC WATERMARK & SECURITY SHIELD ──
   Inject watermark on every non-PDF screen view so any
   smartphone camera photo is permanently attributed to the user. */
$wm_user_id   = (int)($_SESSION['user_id'] ?? 0);
$wm_user_name = htmlspecialchars($_SESSION['name'] ?? 'User');
$wm_ip        = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if ($wm_ip === '::1') { $wm_ip = '127.0.0.1'; }
$wm_timestamp = date('Y-m-d H:i:s');
$wm_text      = "CONFIDENTIAL — {$wm_user_name} (ID: {$wm_user_id}) — IP: {$wm_ip} — {$wm_timestamp}";
$wm_base = defined('BASE_URL') ? BASE_URL : '';
echo '<!-- Forensic Watermark CSS -->' . "\n";
echo '<link rel="stylesheet" href="' . $wm_base . '/assets/css/watermark.css">' . "\n";
echo '<!-- Screen Unfocus Security Shield -->' . "\n";
echo '<div id="security-unfocus-shield"><h3>SECURITY SHIELD ACTIVE</h3><p>Content hidden while browser is out of focus. Return to this window to continue reviewing the exam paper.</p></div>' . "\n";
echo '<div class="forensic-watermark-overlay" id="forensicWatermarkOverlay">' . "\n";
for ($i = 0; $i < 18; $i++) {
    echo '<div class="forensic-watermark-unit">' . $wm_text . '</div>' . "\n";
}
echo '</div>' . "\n";

echo <<<'JSSEC'
<!-- Security Shielding JS -->
<script>
(function(){
    'use strict';
    document.body.classList.add('protected-exam-page');

    // Auto-blur on window unfocus (tab switch, Snipping Tool, etc.)
    window.addEventListener('blur', function(){ document.body.classList.add('screen-unfocused'); });
    window.addEventListener('focus', function(){ document.body.classList.remove('screen-unfocused'); });

    // Disable right-click
    document.addEventListener('contextmenu', function(e){ e.preventDefault(); return false; });

    // Block print, screenshot, devtools shortcuts
    document.addEventListener('keydown', function(e){
        if (e.key === 'PrintScreen' || e.keyCode === 44) {
            e.preventDefault();
            alert('Screen capture is restricted on examination drafting screens.');
            return false;
        }
        if ((e.ctrlKey||e.metaKey) && (e.key==='p'||e.key==='P')) {
            e.preventDefault();
            alert('Printing is disabled during exam paper authoring/review.');
            return false;
        }
        if ((e.ctrlKey||e.metaKey) && (e.key==='s'||e.key==='S')) { e.preventDefault(); return false; }
        if (e.key==='F12'||((e.ctrlKey||e.metaKey)&&e.shiftKey&&(e.key==='I'||e.key==='i'))) { e.preventDefault(); return false; }
    });
})();
</script>
JSSEC;

include $page_file;