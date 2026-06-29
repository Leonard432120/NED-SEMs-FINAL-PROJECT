<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();
$message = '';
$message_type = 'info';

$exam_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($exam_id <= 0) {
    header("Location: exams.php");
    exit();
}

/* ================= FETCH EXAM ================= */
$stmt = $conn->prepare("SELECT * FROM exams WHERE exam_id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) {
    header("Location: exams.php");
    exit();
}

/* ================= AUDIT LOG FUNCTION ================= */
function log_exam_activity($conn, $admin_id, $exam_id, $action, $details) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare("
        INSERT INTO audit_logs (user_id, target_user_id, action, details, ip_address)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisss", $admin_id, $exam_id, $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
}

/* ================= UPDATE EXAM ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $exam_name  = trim($_POST['exam_name'] ?? '');
    $exam_code  = trim($_POST['exam_code'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date   = $_POST['end_date'] ?? '';
    $status     = $_POST['status'] ?? '';
    $year       = trim($_POST['year'] ?? '');
    $class      = trim($_POST['class'] ?? '');

    $errors = [];
    if (empty($exam_name)) $errors[] = "Exam name is required.";
    if (empty($start_date)) $errors[] = "Start date is required.";
    if (empty($end_date)) $errors[] = "End date is required.";
    if ($start_date && $end_date && $start_date > $end_date) {
        $errors[] = "Start date cannot be after end date.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            UPDATE exams 
            SET exam_name = ?, 
                exam_code = ?, 
                start_date = ?, 
                end_date = ?, 
                status = ?, 
                year = ?, 
                class = ?
            WHERE exam_id = ?
        ");

        $stmt->bind_param("sssssssi", 
            $exam_name, $exam_code, $start_date, $end_date, 
            $status, $year, $class, $exam_id
        );

        if ($stmt->execute()) {
            $admin_id = $_SESSION['user_id'];
            $details = "Updated exam ID $exam_id: $exam_name ($exam_code)";

            log_exam_activity($conn, $admin_id, $exam_id, "update_exam", $details);

            $message = "Exam updated successfully.";
            $message_type = "success";

            // Refresh exam data - Use a NEW statement
            $stmt = $conn->prepare("SELECT * FROM exams WHERE exam_id = ?");
            $stmt->bind_param("i", $exam_id);
            $stmt->execute();
            $exam = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $message = "Failed to update exam. Please try again.";
            $message_type = "error";
        }
        // No need to close again here - already handled inside if/else
    } else {
        $message = implode("<br>", $errors);
        $message_type = "error";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Exam - <?= htmlspecialchars($exam['exam_name'] ?? '') ?></title>

    <?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>

    <style>
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .form-group label {
            font-weight: 600;
            color: #334155;
        }
    </style>
</head>
<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
    <?php include '../common/sidebar.php'; ?>

    <div class="content">

        <div class="page-header">
            <div>
                <h2 class="page-title">Edit Exam</h2>
                <p class="page-subtitle">Update examination details</p>
            </div>
            <div class="header-actions">
                <a href="exams.php" class="btn btn-secondary btn-small">← Back to Exams</a>
                <a href="view_exam.php?id=<?= $exam['exam_id'] ?? $exam_id ?>" class="btn btn-view btn-small">View Exam</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" class="form-grid">
                <div class="form-group">
                    <label>Exam Name <span style="color:red;">*</span></label>
                    <input type="text" name="exam_name" value="<?= htmlspecialchars($exam['exam_name'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Exam Code</label>
                    <input type="text" name="exam_code" value="<?= htmlspecialchars($exam['exam_code'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Start Date <span style="color:red;">*</span></label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($exam['start_date'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>End Date <span style="color:red;">*</span></label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($exam['end_date'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Year <span style="color:red;">*</span></label>
                    <input type="text" name="year" value="<?= htmlspecialchars($exam['year'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Class / Form <span style="color:red;">*</span></label>
                    <input type="text" name="class" value="<?= htmlspecialchars($exam['class'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Status <span style="color:red;">*</span></label>
                    <select name="status" required>
                        <option value="draft" <?= ($exam['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="assigned" <?= ($exam['status'] ?? '') === 'assigned' ? 'selected' : '' ?>>Assigned</option>
                        <option value="submitted" <?= ($exam['status'] ?? '') === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                        <option value="under_moderation" <?= ($exam['status'] ?? '') === 'under_moderation' ? 'selected' : '' ?>>Under Moderation</option>
                        <option value="approved" <?= ($exam['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
                    </select>
                </div>

                <div style="grid-column: 1 / -1; margin-top: 25px; text-align: right;">
                    <a href="exams.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-dark">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../common/footer.php'; ?>
</body>
</html>