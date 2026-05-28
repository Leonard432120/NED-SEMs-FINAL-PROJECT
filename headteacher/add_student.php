<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'headteacher') {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

$school_id = $_SESSION['school_id'];
$conn = get_db_connection();
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $student_number = trim($_POST['student_number']);

    if ($name && $student_number) {
        $stmt = $conn->prepare("INSERT INTO students (name, student_number, school_id, status) VALUES (?, ?, ?, 'active')");
        $stmt->bind_param("ssi", $name, $student_number, $school_id);
        if ($stmt->execute()) {
            $message = 'Student added successfully.';
            $message_type = 'success';
        } else {
            $message = 'Failed to add student.';
            $message_type = 'error';
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Student</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/styles.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/form.css">
</head>
<body>
<div class="header">
    <div class="header-left">
        <span class="dashboard-title">Headteacher Portal</span>
    </div>
    <div class="header-right">
        <div class="profile">
            <span class="profile-name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Headteacher'); ?></span>
            <img src="<?= BASE_URL ?>/static/images/user.png" alt="Profile">
            <a href="<?= BASE_URL ?>/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</div>
<div class="dashboard">
    <div class="sidebar">
        <a href="dashboard.php">Dashboard</a>
        <a href="manage_students.php">Manage Students</a>
        <a href="add_student.php">Add Student</a>
        <a href="reports.php">Reports</a>
        <a href="performance.php">Performance</a>
        <a href="released_results.php">Final Results</a>
    </div>
    <div class="main-content">
        <h2>Add Student</h2>
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        <div class="card card-accent-blue">
            <div class="form-container">
                <form method="POST">
                    <div class="form-group">
                        <label>Student Name</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Student Number</label>
                        <input type="text" name="student_number" required>
                    </div>
                    <button type="submit" class="btn btn-dark">Add Student</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/static/js/main.js"></script>
</body>
</html>