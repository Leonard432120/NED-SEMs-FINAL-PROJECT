<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'headteacher') {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

$school_id = $_SESSION['school_id'];
$conn = get_db_connection();
$result = $conn->query("SELECT student_id, name, student_number, status FROM students WHERE school_id = $school_id ORDER BY name");
$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Students</title>
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
        <h2>Manage Students</h2>
        <div class="card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Student Number</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($students): ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?= $student['student_id']; ?></td>
                                    <td><?= htmlspecialchars($student['name']); ?></td>
                                    <td><?= htmlspecialchars($student['student_number']); ?></td>
                                    <td><?= htmlspecialchars($student['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align:center;">No students found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/static/js/main.js"></script>
</body>
</html>