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
    $exam_number = trim($_POST['exam_number']);

    if ($name && $exam_number) {
        $stmt = $conn->prepare("INSERT INTO students (name, exam_number, school_id, status) VALUES (?, ?, ?, 'active')");
        $stmt->bind_param("ssi", $name, $exam_number, $school_id);
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Student</title>
<?php
$portal_title = 'NED-SEMS | Headteacher Portal';
$module_css = 'headteacher';
include __DIR__ . '/../common/head_assets.php';
?>
</head>
<body>
<?php include '../common/header.php'; ?>

<div class="dashboard">

<?php include '../common/sidebar.php'; ?>

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
                        <label>Exam Number</label>
                        <input type="text" name="exam_number" required>
                    </div>
                    <button type="submit" class="btn btn-dark">Add Student</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include '../common/footer.php'; ?>
