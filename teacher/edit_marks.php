<?php
require_once __DIR__ . '/teacher_init.php';
$conn = get_db_connection();

$user_id = $_SESSION['user_id'] ?? 0;

/* ================= GET EXAM ================= */
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if ($exam_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

/* ================= CHECK EDIT WINDOW ================= */
$stmt = $conn->prepare("
    SELECT editable_until 
    FROM marks 
    WHERE exam_id=? AND teacher_id=? 
    LIMIT 1
");
$stmt->bind_param("ii", $exam_id, $user_id);
$stmt->execute();
$time_check = $stmt->get_result()->fetch_assoc();

$can_edit = true;

if ($time_check && strtotime($time_check['editable_until']) < time()) {
    $can_edit = false;
}

/* ================= FETCH EXAM ================= */
$stmt = $conn->prepare("SELECT * FROM exams WHERE exam_id=?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();

if (!$exam) {
    die("Exam not found.");
}

/* ================= FETCH STUDENTS ================= */
$students = $conn->query("
    SELECT s.student_id, s.name, s.exam_number,
           sc.school_name, s.class
    FROM students s
    LEFT JOIN schools sc ON s.school_id = sc.school_id
");

/* ================= HANDLE SAVE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$can_edit) {
        die("Editing time has expired.");
    }

    foreach ($_POST['marks'] as $student_id => $score) {

        $score = ($score === '') ? null : (int)$score;

        /* check existing */
        $check = $conn->prepare("
            SELECT mark_id FROM marks 
            WHERE exam_id=? AND student_id=? AND teacher_id=?
        ");
        $check->bind_param("iii", $exam_id, $student_id, $user_id);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();

        if ($existing) {

            $update = $conn->prepare("
                UPDATE marks 
                SET score=?, created_at=NOW()
                WHERE mark_id=?
            ");
            $update->bind_param("ii", $score, $existing['mark_id']);
            $update->execute();

        } else {

            $insert = $conn->prepare("
                INSERT INTO marks (
                    student_id, exam_id, teacher_id, score,
                    created_at, editable_until
                )
                VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE))
            ");
            $insert->bind_param("iiii", $student_id, $exam_id, $user_id, $score);
            $insert->execute();
        }
    }

    $success = "Marks saved successfully!";
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Edit Marks</title>
<?php
$portal_title = 'NED-SEMS | Teacher Portal';
$module_css = 'teacher';
include __DIR__ . '/../common/head_assets.php';
?>

<style>
.table input{
    width: 90px;
    padding: 6px;
    border: 1px solid #ddd;
    border-radius: 6px;
}

.locked{
    background:#fee2e2;
    color:#991b1b;
    padding:10px;
    border-radius:8px;
}
</style>
</head>

<body>

<?php include __DIR__ . '/../common/header.php'; ?>

<div class="dashboard">

<?php include __DIR__ . '/../common/sidebar.php'; ?>

<div class="content">

<div class="card">
    <h2><?= htmlspecialchars($exam['exam_name']) ?></h2>

    <p><b>Class:</b> <?= htmlspecialchars($exam['class']) ?></p>
    <p><b>Total Marks:</b> <?= htmlspecialchars($exam['total_marks']) ?></p>
    <p><b>Date:</b> <?= htmlspecialchars($exam['exam_date']) ?></p>

    <?php if(!$can_edit): ?>
        <div class="locked">
            Editing time has expired. You can no longer update marks.
        </div>
    <?php endif; ?>
</div>

<?php if(isset($success)): ?>
    <div class="success"><?= $success ?></div>
<?php endif; ?>

<form method="POST">

<div class="card">

<table class="table">
    <thead>
        <tr>
            <th>Student</th>
            <th>Exam No</th>
            <th>School</th>
            <th>Class</th>
            <th>Marks</th>
        </tr>
    </thead>

    <tbody>

    <?php
    $marks = $conn = get_db_connection();

    $result = $conn->prepare("
        SELECT student_id, score 
        FROM marks 
        WHERE exam_id=? AND teacher_id=?
    ");
    $result->bind_param("ii", $exam_id, $user_id);
    $result->execute();
    $res = $result->get_result();

    $existing = [];
    while($row = $res->fetch_assoc()){
        $existing[$row['student_id']] = $row['score'];
    }

    while($student = $students->fetch_assoc()):
    ?>

        <tr>
            <td><?= htmlspecialchars($student['name']) ?></td>
            <td><?= htmlspecialchars($student['exam_number']) ?></td>
            <td><?= htmlspecialchars($student['school_name']) ?></td>
            <td><?= htmlspecialchars($student['class']) ?></td>

            <td>
                <input type="number"
                    name="marks[<?= $student['student_id'] ?>]"
                    value="<?= $existing[$student['student_id']] ?? '' ?>"
                    min="0"
                    max="100"
                    <?= !$can_edit ? 'disabled' : '' ?>
                >
            </td>
        </tr>

    <?php endwhile; ?>

    </tbody>
</table>

</div>

<br>

<?php if($can_edit): ?>
    <button type="submit" class="btn-small btn-teal">
        Save Changes
    </button>
<?php endif; ?>

</form>

</div>
</div>

<?php include __DIR__ . '/../common/footer.php'; ?>