<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

$message = '';
$message_type = '';

function createSubject($conn, $subject_name)
{
    $subject_name = trim($subject_name);

    if (!$subject_name) {
        return ['status' => 'error', 'message' => 'Subject name is required.'];
    }

    $check = $conn->prepare("SELECT subject_id FROM subjects WHERE LOWER(subject_name) = LOWER(?)");
    $check->bind_param("s", $subject_name);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        return ['status' => 'error', 'message' => 'Subject already exists.'];
    }

    $stmt = $conn->prepare("INSERT INTO subjects (subject_name, status) VALUES (?, 'active')");
    $stmt->bind_param("s", $subject_name);

    if ($stmt->execute()) {
        return ['status' => 'success', 'message' => 'Subject created successfully.'];
    }

    return ['status' => 'error', 'message' => 'Failed to create subject.'];
}

if (isset($_POST['create_subject'])) {
    $response = createSubject($conn, $_POST['subject_name'] ?? '');
    $message = $response['message'];
    $message_type = $response['status'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Subject</title>

<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/admin.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/form.css">
</head>
<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">

<?php include '../common/sidebar.php'; ?>

<div class="content">

    <div class="page-header">
        <div>
            <h2 class="page-title">Add New Subject</h2>
            <p class="page-subtitle">Create a subject for exams and question banks.</p>
        </div>
        <a href="manage_subject.php" class="btn btn-dark btn-small">← Back to Subjects</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="main-content single-column">
        <div class="card">
            <h3>Add Subject</h3>
            <form method="POST" class="form-container">
                <input type="hidden" name="create_subject" value="1">
                <div class="form-group">
                    <label>Subject Name</label>
                    <input type="text" name="subject_name" placeholder="e.g. Mathematics" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-create">Create Subject</button>
                </div>
            </form>
        </div>
    </div>

</div>
</div>

<?php include '../common/footer.php'; ?>

</body>
</html>
