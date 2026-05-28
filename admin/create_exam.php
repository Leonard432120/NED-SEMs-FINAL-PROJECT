<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

$message = '';
$message_type = '';

$conn = get_db_connection();
$subjects = [];
$result = $conn->query("SELECT subject_id, subject_name FROM subjects WHERE status = 'active' ORDER BY subject_name");
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_name = $_POST['exam_name'];
    $subject_id = (int)$_POST['subject_id'];
    $exam_date = $_POST['exam_date'];
    $duration_minutes = (int)$_POST['duration_minutes'];
    $total_marks = (int)$_POST['total_marks'];
    $status = 'draft';
    $year = $_POST['year'];
    $class = $_POST['class'];

    $stmt = $conn->prepare("INSERT INTO exams(exam_name, subject_id, created_by, exam_date, duration_minutes, total_marks, status, year, class) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sissiiiss", $exam_name, $subject_id, $_SESSION['user_id'], $exam_date, $duration_minutes, $total_marks, $status, $year, $class);

    if ($stmt->execute()) {
        $message = "Exam created successfully.";
        $message_type = "success";
        header("Location: " . BASE_URL . "/admin/exams.php");
        exit();
    } else {
        $message = "Failed to create exam.";
        $message_type = "error";
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Create Exam</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/admin.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/form.css">
</head>
<body>
<div class="header">
    <div class="header-left">
        <span class="dashboard-title">NED-SEMS | EDM Control Center</span>
    </div>
    <div class="header-right">
        <div class="profile">
           <a href="<?= BASE_URL ?>/logout.php">Logout</a><img src="<?= BASE_URL ?>/static/images/user.png">
        </div>
    </div>
</div>
<div class="dashboard">
    <div class="sidebar">
        <a href="dashboard.php">Dashboard</a>
        <a href="manage_users.php">Users</a>
        <div class="sidebar-group">
            <span onclick="toggleMenu('schoolMenu')">Schools ▼</span>
            <div class="sidebar-sub" id="schoolMenu">
                <a href="add_school.php">Add School</a>
                <a href="manage_schools.php">Manage Schools</a>
            </div>
        </div>
        <a href="manage_subject.php">Subjects</a>
        <a href="exams.php">Exams</a>
        <div class="sidebar-group">
            <span onclick="toggleMenu('assignMenu')">Assignments ▼</span>
            <div class="sidebar-sub" id="assignMenu">
                <a href="assign.php">Assign Teachers</a>
                <a href="view_assignments.php">View Assignments</a>
            </div>
        </div>
    </div>
    <div class="main-content">
        <div class="page-header">
            <h2>Create Exam</h2>
        </div>
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        <div class="card card-accent-blue">
            <div class="form-container">
                <form method="POST">
                    <div class="form-group">
                        <label>Exam Name</label>
                        <input type="text" name="exam_name" required>
                    </div>
                    <div class="form-group">
                        <label>Subject</label>
                        <select name="subject_id" required>
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['subject_id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Exam Date</label>
                        <input type="date" name="exam_date" required>
                    </div>
                    <div class="form-group">
                        <label>Duration (minutes)</label>
                        <input type="number" name="duration_minutes" required>
                    </div>
                    <div class="form-group">
                        <label>Total Marks</label>
                        <input type="number" name="total_marks" required>
                    </div>
                    <div class="form-group">
                        <label>Year</label>
                        <input type="text" name="year" required>
                    </div>
                    <div class="form-group">
                        <label>Class</label>
                        <input type="text" name="class" required>
                    </div>
                    <button type="submit" class="btn btn-dark">Save Exam</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/static/js/main.js"></script>
</body>
</html>