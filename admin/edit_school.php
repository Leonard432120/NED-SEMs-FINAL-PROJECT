<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

$school_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($school_id <= 0) {
    header("Location: " . BASE_URL . "/admin/manage_schools.php");
    exit();
}

$conn = get_db_connection();
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_name = trim($_POST['school_name']);
    $school_code = trim($_POST['school_code']);
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE schools SET school_name = ?, school_code = ?, status = ? WHERE school_id = ?");
    $stmt->bind_param("sssi", $school_name, $school_code, $status, $school_id);
    if ($stmt->execute()) {
        $message = "School updated successfully.";
        $message_type = "success";
    } else {
        $message = "Failed to update school.";
        $message_type = "error";
    }
    $stmt->close();
}

$stmt = $conn->prepare("SELECT * FROM schools WHERE school_id = ?");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$school = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$school) {
    header("Location: " . BASE_URL . "/admin/manage_schools.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit School</title>
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
            <h2>Edit School</h2>
        </div>
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        <div class="card card-accent-blue">
            <div class="form-container">
                <form method="POST">
                    <div class="form-group">
                        <label>School Name</label>
                        <input type="text" name="school_name" value="<?= htmlspecialchars($school['school_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>School Code</label>
                        <input type="text" name="school_code" value="<?= htmlspecialchars($school['school_code']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="active" <?= $school['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?= $school['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-dark">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/static/js/main.js"></script>
</body>
</html>