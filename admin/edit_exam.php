<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

$exam_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($exam_id <= 0) {
    header("Location: " . BASE_URL . "/admin/exams.php");
    exit();
}

$conn = get_db_connection();
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_name = $_POST['exam_name'];
    $subject_id = (int)$_POST['subject_id'];
    $exam_date = $_POST['exam_date'];
    $duration_minutes = (int)$_POST['duration_minutes'];
    $total_marks = (int)$_POST['total_marks'];
    $status = $_POST['status'];
    $year = $_POST['year'];
    $class = $_POST['class'];

    $stmt = $conn->prepare("UPDATE exams SET exam_name = ?, subject_id = ?, exam_date = ?, duration_minutes = ?, total_marks = ?, status = ?, year = ?, class = ? WHERE exam_id = ?");
    $stmt->bind_param("sisiisssi", $exam_name, $subject_id, $exam_date, $duration_minutes, $total_marks, $status, $year, $class, $exam_id);
    if ($stmt->execute()) {
        $message = "Exam updated successfully.";
        $message_type = "success";
    } else {
        $message = "Failed to update exam.";
        $message_type = "error";
    }
    $stmt->close();
}

$subject_result = $conn->query("SELECT subject_id, subject_name FROM subjects WHERE status = 'active' ORDER BY subject_name");
$subjects = [];
while ($row = $subject_result->fetch_assoc()) {
    $subjects[] = $row;
}

$stmt = $conn->prepare("SELECT * FROM exams WHERE exam_id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) {
    $conn->close();
    header("Location: " . BASE_URL . "/admin/exams.php");
    exit();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Exam</title>
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
            <h2>Edit Exam</h2>
        </div>
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        <div class="card card-accent-blue">
            <div class="form-container">
                <form method="POST">
                    <div class="form-group">
                        <label>Exam Name</label>
                        <input type="text" name="exam_name" value="<?= htmlspecialchars($exam['exam_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Subject</label>
                        <select name="subject_id" required>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= $subject['subject_id']; ?>" <?= (isset($exam['subject_id']) && $exam['subject_id'] == $subject['subject_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($subject['subject_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Exam Date</label>
                        <input type="date" name="exam_date" value="<?= htmlspecialchars($exam['exam_date'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Duration (minutes)</label>
                        <input type="number" name="duration_minutes" value="<?= htmlspecialchars($exam['duration_minutes'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Total Marks</label>
                        <input type="number" name="total_marks" value="<?= htmlspecialchars($exam['total_marks'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Year</label>
                        <input type="text" name="year" value="<?= htmlspecialchars($exam['year'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Class</label>
                        <input type="text" name="class" value="<?= htmlspecialchars($exam['class'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <?php foreach (['draft','assigned','submitted','under_moderation','needs_revision','approved','rejected'] as $status): ?>
                                <option value="<?= $status; ?>" <?= (isset($exam['status']) && $exam['status'] == $status) ? 'selected' : ''; ?>><?= ucfirst($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-dark">Update Exam</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/static/js/main.js"></script>
</body>
</html>