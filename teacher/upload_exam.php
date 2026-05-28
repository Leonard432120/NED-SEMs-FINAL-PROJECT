<?php
require_once __DIR__ . '/teacher_init.php';

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

$message = '';
$message_type = '';

if ($exam_id <= 0) {
    header("Location: assigned_exams.php");
    exit();
}

$conn = get_db_connection();

// Check if assigned
$stmt = $conn->prepare("SELECT e.*, s.subject_name FROM exams e JOIN subjects s ON e.subject_id = s.subject_id JOIN exam_assignments ea ON e.exam_id = ea.exam_id WHERE e.exam_id = ? AND ea.teacher_id = ? AND ea.role = 'item_writer'");
$stmt->bind_param("ii", $exam_id, $user_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) {
    $conn->close();
    header("Location: assigned_exams.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $file = $_FILES['exam_file'] ?? null;

    if (!$file || $file['error'] != 0 || empty($file['name'])) {
        $message = "Select file";
        $message_type = "error";
    } else {
        $filename = basename($file['name']);
        $upload_dir = "../static/uploads/exams/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_path = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // Get next version
            $stmt = $conn->prepare("SELECT MAX(version_number) as v FROM exam_documents WHERE exam_id = ?");
            $stmt->bind_param("i", $exam_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $next_v = $row['v'] ? $row['v'] + 1 : 1;
            $stmt->close();

            // Set previous to not current
            $stmt = $conn->prepare("UPDATE exam_documents SET is_current = 0 WHERE exam_id = ?");
            $stmt->bind_param("i", $exam_id);
            $stmt->execute();
            $stmt->close();

            // Insert new
            $stmt = $conn->prepare("INSERT INTO exam_documents (exam_id, uploaded_by, file_path, version_number, is_current) VALUES (?, ?, ?, ?, 1)");
            $stmt->bind_param("iisii", $exam_id, $user_id, $file_path, $next_v);
            $stmt->execute();
            $stmt->close();

            // Update exam status
            $stmt = $conn->prepare("UPDATE exams SET status = 'submitted' WHERE exam_id = ?");
            $stmt->bind_param("i", $exam_id);
            $stmt->execute();
            $stmt->close();

            $message = "Uploaded successfully for moderation";
            $message_type = "success";
            header("Location: my_submissions.php");
            exit();
        } else {
            $message = "Upload failed";
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
<title>Upload Exam</title>

<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/styles.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/form.css">
</head>

<body>

<!-- ================= HEADER ================= -->
<div class="header">

    <div class="header-left">
        <span class="dashboard-title">EDM Staff Portal</span>
    </div>

    <div class="header-right">

        <div class="profile">

            <!-- Profile Name -->
            <span class="profile-name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Teacher'); ?></span>

            <!-- Profile Image -->
            <img src="<?= BASE_URL ?>/static/images/user.png" alt="Profile">

            <!-- Logout -->
            <a href="<?= BASE_URL ?>/logout.php" class="logout-btn">Logout</a>

        </div>

    </div>

</div>

<!-- ================= MAIN LAYOUT ================= -->
<div class="dashboard">

    <!-- ================= SIDEBAR ================= -->
    <?php include __DIR__ . '/teacher_sidebar.php'; ?>

    <!-- ================= MAIN CONTENT ================= -->
    <div class="main-content">

<h2>Upload Exam: <?php echo htmlspecialchars($exam['exam_name']); ?> (<?php echo htmlspecialchars($exam['subject_name']); ?>)</h2>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="card">

<div class="form-container">

<form method="POST" enctype="multipart/form-data">

    <div class="form-group">
        <label>Exam File</label>
        <input type="file" name="exam_file" required>
    </div>

    <button type="submit" class="btn btn-primary">
        Upload Exam
    </button>

</form>

</div>

</div>

    </div>

</div>

<script src="<?= BASE_URL ?>/static/js/main.js"></script>
</body>
</html>