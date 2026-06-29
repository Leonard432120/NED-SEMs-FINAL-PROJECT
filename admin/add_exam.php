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

// Fetch active subjects
$subjects = [];
$result = $conn->query("SELECT subject_id, subject_name FROM subjects WHERE status = 'active' ORDER BY subject_name ASC");
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
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

/* ================= CREATE EXAM ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $exam_name   = trim($_POST['exam_name']);
    $exam_code   = trim($_POST['exam_code']);
    $subject_id  = (int)$_POST['subject_id'];
    $start_date  = $_POST['start_date'];
    $end_date    = $_POST['end_date'];
    $year        = trim($_POST['year']);
    $class       = trim($_POST['class']);
    $status      = 'draft';

    if (empty($exam_code)) {
        $exam_code = strtoupper(substr($exam_name, 0, 4)) . date('Ymd');
    }

    $stmt = $conn->prepare("
        INSERT INTO exams (
            exam_name, exam_code, created_by, start_date, end_date, 
            status, year, class
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("ssisssss", 
        $exam_name, $exam_code, $_SESSION['user_id'], 
        $start_date, $end_date, $status, $year, $class
    );

    if ($stmt->execute()) {
        $new_exam_id = $stmt->insert_id;

        // ===================== AUDIT LOG =====================
        $admin_id = $_SESSION['user_id'];
        $details = "Created new exam: '$exam_name' ($exam_code) for $class ($year)";

        log_exam_activity($conn, $admin_id, $new_exam_id, "create_exam", $details);
        // ====================================================

        $message = "Exam created successfully!";
        $message_type = "success";

        header("Location: exams.php?success=1");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Exam</title>

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
                <h2 class="page-title">Create New Exam</h2>
                <p class="page-subtitle">Set up a new examination</p>
            </div>
            <a href="exams.php" class="btn btn-secondary btn-small">← Back to Exams</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">

            <form method="POST" class="form-grid">

                <div class="form-group">
                    <label>Exam Name <span style="color:red;">*</span></label>
                    <input type="text" name="exam_name" required placeholder="e.g. Mid-Term Examination 2026">
                </div>

                <div class="form-group">
                    <label>Exam Code</label>
                    <input type="text" name="exam_code" placeholder="Auto-generated if left blank">
                </div>

                <div class="form-group">
                    <label>Subject <span style="color:red;">*</span></label>
                    <select name="subject_id" required>
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $sub): ?>
                            <option value="<?= $sub['subject_id'] ?>">
                                <?= htmlspecialchars($sub['subject_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Start Date <span style="color:red;">*</span></label>
                    <input type="date" name="start_date" required>
                </div>

                <div class="form-group">
                    <label>End Date <span style="color:red;">*</span></label>
                    <input type="date" name="end_date" required>
                </div>

                <div class="form-group">
                    <label>Year <span style="color:red;">*</span></label>
                    <input type="text" name="year" value="<?= date('Y') ?>" required>
                </div>

                <div class="form-group">
                    <label>Class / Form <span style="color:red;">*</span></label>
                    <input type="text" name="class" placeholder="e.g. Form 4" required>
                </div>

                <div style="grid-column: 1 / -1; margin-top: 20px; text-align: right;">
                    <button type="submit" class="btn btn-dark">Create Exam</button>
                </div>

            </form>

        </div>

    </div>
</div>

<?php include '../common/footer.php'; ?>

</body>
</html>