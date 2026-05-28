<?php
require_once __DIR__ . '/teacher_init.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$conn = get_db_connection();

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if ($exam_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

/* =========================================================
   CHECK ACCESS
========================================================= */
$stmt = $conn->prepare("
    SELECT 1
    FROM exam_assignments
    WHERE exam_id = ?
    AND teacher_id = ?
    AND role = 'item_writer'
");
$stmt->bind_param("ii", $exam_id, $user_id);
$stmt->execute();

if ($stmt->get_result()->num_rows == 0) {
    header("Location: dashboard.php");
    exit();
}
$stmt->close();

/* =========================================================
   GET EXAM
========================================================= */
$stmt = $conn->prepare("
    SELECT exam_id, exam_name, class, total_marks, year
    FROM exams
    WHERE exam_id = ?
");
$stmt->bind_param("i", $exam_id);
$stmt->execute();

$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) {
    header("Location: dashboard.php");
    exit();
}

$total_marks = $exam['total_marks'] ?: 100;

/* =========================================================
   GET TEACHER SCHOOL
========================================================= */
$stmt = $conn->prepare("
    SELECT school_id
    FROM users
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();

$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

$school_id = $teacher['school_id'] ?? 0;

/* =========================================================
   GET STUDENTS
========================================================= */
$stmt = $conn->prepare("
    SELECT student_id, name, exam_number
    FROM students
    WHERE school_id = ?
    AND class = ?
    ORDER BY name ASC
");
$stmt->bind_param("is", $school_id, $exam['class']);
$stmt->execute();

$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$message = '';
$message_type = '';

/* =========================================================
   GRADE FUNCTION
========================================================= */
function calculateGrade($percentage)
{
    if ($percentage >= 75) {
        return ['grade' => 'A', 'remarks' => 'Excellent'];
    }

    if ($percentage >= 65) {
        return ['grade' => 'B', 'remarks' => 'Very Good'];
    }

    if ($percentage >= 50) {
        return ['grade' => 'C', 'remarks' => 'Pass'];
    }

    if ($percentage >= 40) {
        return ['grade' => 'D', 'remarks' => 'Weak Pass'];
    }

    return ['grade' => 'F', 'remarks' => 'Fail'];
}

/* =========================================================
   SAVE RESULTS
========================================================= */
function saveResults($conn, $results, $exam_id, $user_id)
{
    usort($results, fn($a, $b) => $b['percentage'] <=> $a['percentage']);

    $position = 1;

    foreach ($results as $r) {

        $stmt = $conn->prepare("
            SELECT result_id, status
            FROM results
            WHERE student_id = ?
            AND exam_id = ?
        ");
        $stmt->bind_param("ii", $r['student_id'], $exam_id);
        $stmt->execute();

        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        /* LOCK AFTER ADMIN COMPILATION */
        if ($existing && $existing['status'] !== 'submitted') {
            continue;
        }

        if ($existing) {

            $stmt = $conn->prepare("
                UPDATE results
                SET
                    total_score = ?,
                    percentage = ?,
                    grade = ?,
                    remarks = ?,
                    position_in_class = ?,
                    teacher_id = ?,
                    recorded_by = ?,
                    status = 'submitted'
                WHERE result_id = ?
            ");

            $stmt->bind_param(
                "ddssiiii",
                $r['score'],
                $r['percentage'],
                $r['grade'],
                $r['remarks'],
                $position,
                $user_id,
                $user_id,
                $existing['result_id']
            );

        } else {

            $stmt = $conn->prepare("
                INSERT INTO results (
                    student_id,
                    exam_id,
                    total_score,
                    percentage,
                    grade,
                    remarks,
                    position_in_class,
                    teacher_id,
                    recorded_by,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted')
            ");

            $stmt->bind_param(
                "iiddssiii",
                $r['student_id'],
                $exam_id,
                $r['score'],
                $r['percentage'],
                $r['grade'],
                $r['remarks'],
                $position,
                $user_id,
                $user_id
            );
        }

        $stmt->execute();
        $stmt->close();

        $position++;
    }
}

/* =========================================================
   MANUAL SAVE
========================================================= */
if (isset($_POST['save_results'])) {

    $results = [];

    foreach ($students as $student) {

        $score = $_POST['score_' . $student['student_id']] ?? '';

        if ($score === '') {
            continue;
        }

        $score = max(0, min((float)$score, $total_marks));

        $percentage = ($score / $total_marks) * 100;

        $grading = calculateGrade($percentage);

        $results[] = [
            'student_id' => $student['student_id'],
            'score' => $score,
            'percentage' => $percentage,
            'grade' => $grading['grade'],
            'remarks' => $grading['remarks']
        ];
    }

    saveResults($conn, $results, $exam_id, $user_id);

    $message = "Results submitted successfully.";
    $message_type = "success";
}

/* =========================================================
   BULK IMPORT
========================================================= */
if (isset($_POST['import_results'])) {

    if (!empty($_FILES['xlsx_file']['tmp_name'])) {

        try {

            $spreadsheet = IOFactory::load($_FILES['xlsx_file']['tmp_name']);

            $sheet = $spreadsheet
                ->getActiveSheet()
                ->toArray();

            $results = [];

            foreach ($sheet as $i => $row) {

                if ($i === 0) {
                    continue;
                }

                $exam_number = trim($row[0] ?? '');
                $score       = trim($row[1] ?? '');

                if (!$exam_number || $score === '') {
                    continue;
                }

                foreach ($students as $student) {

                    if ($student['exam_number'] === $exam_number) {

                        $score = max(
                            0,
                            min((float)$score, $total_marks)
                        );

                        $percentage = ($score / $total_marks) * 100;

                        $grading = calculateGrade($percentage);

                        $results[] = [
                            'student_id' => $student['student_id'],
                            'score' => $score,
                            'percentage' => $percentage,
                            'grade' => $grading['grade'],
                            'remarks' => $grading['remarks']
                        ];
                    }
                }
            }

            saveResults($conn, $results, $exam_id, $user_id);

            $message = "Bulk results imported successfully.";
            $message_type = "success";

        } catch (Exception $e) {

            $message = "Import failed: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Enter Results</title>

<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/styles.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/form.css">

<style>

.page-header{
    margin-bottom:20px;
}

.page-title{
    font-size:28px;
    color:#0f172a;
    margin-bottom:10px;
}

.exam-card{
    margin-bottom:25px;
}

.exam-title{
    margin-bottom:10px;
    color:#111827;
}

.exam-meta{
    display:flex;
    gap:30px;
    color:#64748b;
    flex-wrap:wrap;
}

.results-layout{
    display:grid;
    grid-template-columns:340px 1fr;
    gap:25px;
    align-items:start;
}

.card{
    background:#fff;
    border-radius:16px;
    padding:24px;
    box-shadow:0 10px 30px rgba(15,23,42,.06);
}

.card h3{
    margin-bottom:10px;
    color:#0f172a;
}

.upload-box{
    border:2px dashed #cbd5e1;
    border-radius:14px;
    padding:30px 20px;
    text-align:center;
    cursor:pointer;
    transition:0.3s ease;
    margin-top:20px;
}

.upload-box:hover{
    border-color:#2563eb;
    background:#f8fafc;
}

.upload-box span{
    font-size:15px;
    font-weight:600;
    color:#334155;
}

.results-table{
    width:100%;
    border-collapse:collapse;
}

.results-table thead{
    background:#f8fafc;
}

.results-table th{
    text-align:left;
    padding:14px;
    font-size:14px;
    color:#334155;
}

.results-table td{
    padding:14px;
    border-bottom:1px solid #e2e8f0;
}

.results-table tbody tr:hover{
    background:#f9fafb;
}

.score-input{
    width:100%;
    min-width:100px;
}

.form-actions{
    margin-top:20px;
    display:flex;
    justify-content:flex-end;
}

.alert{
    padding:14px 18px;
    border-radius:10px;
    margin-bottom:20px;
}

.alert-success{
    background:#ecfdf5;
    color:#065f46;
}

.alert-error{
    background:#fef2f2;
    color:#991b1b;
}

.empty-row{
    text-align:center;
    color:#dc2626;
    padding:25px !important;
}

.import-info{
    margin-top:10px;
    color:#64748b;
    line-height:1.7;
}

.template-box{
    margin-top:20px;
    padding:15px;
    border-radius:12px;
    background:#f8fafc;
    color:#475569;
    font-size:14px;
}

@media(max-width:1000px){

    .results-layout{
        grid-template-columns:1fr;
    }

}

</style>

</head>

<body>

<!-- HEADER -->
<div class="header">

    <div class="header-left">
        <span class="dashboard-title">
            NED-SEMS | Teacher Portal
        </span>
    </div>

    <div class="header-right">

        <div class="profile">

            <span class="profile-name">
                <?= $_SESSION['name'] ?? 'Teacher'; ?>
            </span>

            <img src="<?= BASE_URL ?>/static/images/user.png">

            <a href="<?= BASE_URL ?>/logout.php"
               class="logout-btn">
               Logout
            </a>

        </div>

    </div>

</div>

<div class="dashboard">

    <?php include __DIR__ . '/teacher_sidebar.php'; ?>

    <div class="main-content">

        <!-- PAGE HEADER -->
        <div class="page-header">
            <h2 class="page-title">
                Enter Student Results
            </h2>
        </div>

        <!-- ALERT -->
        <?php if($message): ?>

            <div class="alert alert-<?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>

        <?php endif; ?>

        <!-- EXAM CARD -->
        <div class="card exam-card">

            <h3 class="exam-title">
                <?= htmlspecialchars($exam['exam_name']) ?>
            </h3>

            <div class="exam-meta">

                <div>
                    <b>Class:</b>
                    <?= htmlspecialchars($exam['class']) ?>
                </div>

                <div>
                    <b>Year:</b>
                    <?= htmlspecialchars($exam['year'] ?? date('Y')) ?>
                </div>

                <div>
                    <b>Total Marks:</b>
                    <?= $total_marks ?>
                </div>

            </div>

        </div>

        <!-- RESULTS LAYOUT -->
        <div class="results-layout">

            <!-- LEFT SIDE -->
            <div class="card">

                <h3>Bulk Import Results</h3>

                <p class="import-info">
                    Upload Excel file containing:
                    <br>
                    <b>Exam Number | Score</b>
                </p>

                <form method="POST"
                      enctype="multipart/form-data">

                    <input type="hidden"
                           name="import_results"
                           value="1">

                    <label>

                        <input type="file"
                               name="xlsx_file"
                               accept=".xlsx"
                               hidden
                               onchange="this.form.submit()">

                        <div class="upload-box">
                            <span>
                                📁 Choose Excel File
                            </span>
                        </div>

                    </label>

                </form>

                <div class="template-box">

                    <b>Excel Format Example</b>

                    <br><br>

                    1001 | 78
                    <br>
                    1002 | 65
                    <br>
                    1003 | 89

                </div>

            </div>

            <!-- RIGHT SIDE -->
            <div class="card">

                <h3>Manual Result Entry</h3>

                <form method="POST">

                    <input type="hidden"
                           name="save_results"
                           value="1">

                    <div class="table-container">

                        <table class="results-table">

                            <thead>

                                <tr>
                                    <th>#</th>
                                    <th>Student Name</th>
                                    <th>Exam Number</th>
                                    <th>Score</th>
                                </tr>

                            </thead>

                            <tbody>

                            <?php if(empty($students)): ?>

                                <tr>
                                    <td colspan="4"
                                        class="empty-row">
                                        No students found
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach($students as $index => $student): ?>

                                <tr>

                                    <td>
                                        <?= $index + 1 ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($student['name']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($student['exam_number']) ?>
                                    </td>

                                    <td>

                                        <input
                                            type="number"
                                            name="score_<?= $student['student_id'] ?>"
                                            class="score-input"
                                            min="0"
                                            max="<?= $total_marks ?>"
                                            step="0.01"
                                        >

                                    </td>

                                </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                    <div class="form-actions">

                        <button type="submit"
                                class="btn btn-dark">

                            Save Results

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>

</body>
</html>