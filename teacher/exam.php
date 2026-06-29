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

/* safe defaults */
$exam['year']             = $exam['year'] ?? date("Y");
$exam['duration_minutes'] = $exam['duration_minutes'] ?? 120;
$exam['total_marks']      = $exam['total_marks'] ?? 100;
$exam['subject_code']     = strtoupper(substr($exam['subject_name'],0,3)) . "-MSCE";

/* ===============================
   4. FETCH QUESTIONS
================================ */
$qstmt = $conn->prepare("
    SELECT *
    FROM questions
    WHERE exam_id = ?
    ORDER BY question_order ASC
");
$qstmt->bind_param("i", $exam_id);
$qstmt->execute();
$qres = $qstmt->get_result();

$questions = [];
while ($row = $qres->fetch_assoc()) {
    $questions[] = $row;
}

/* ===============================
   5. SPLIT QUESTIONS INTO PAGES
================================ */

/* Section A → 10 questions */
$sectionA = array_slice($questions, 0, 10);

/* Section B → next 5 */
$sectionB = array_slice($questions, 10, 5);

/* Section C → remaining */
$sectionC = array_slice($questions, 15);

/* Split for pages */
$page1_questions = array_slice($sectionA, 0, 5);
$page2_questions = array_slice($sectionA, 5, 5);

$page3_questions = array_slice($sectionB, 0, ceil(count($sectionB) / 2));
$page4_questions = array_slice($sectionB, ceil(count($sectionB) / 2));

$page5_questions = array_slice($sectionC, 0, ceil(count($sectionC) / 2));
$page6_questions = array_slice($sectionC, ceil(count($sectionC) / 2));

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

    require_once __DIR__ . '/../vendor/autoload.php';
    $dompdf = new Dompdf\Dompdf();

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

include $page_file;