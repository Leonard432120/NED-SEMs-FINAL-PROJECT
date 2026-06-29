<?php
session_start();
require_once '../config/db.php';

// Ensure only examination officers can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'examination_officer') {
    header('Location: ../login.php');
    exit();
}

$conn = get_db_connection();
// $school_id removed - not used

$message = '';
$error   = '';
$edit_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

/* ------------------------------------------------------------
 *  POST – Update exam class and marks deadline
 * ------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_id        = (int)($_POST['exam_id'] ?? 0);
    $exam_class     = trim($_POST['class'] ?? '');
    $marks_deadline = trim($_POST['marks_deadline'] ?? '');

    if ($exam_id <= 0) {
        $error = 'Please select an exam.';
    } elseif ($exam_class === '') {
        $error = 'Please select a class.';
    } else {
        // Empty string becomes NULL so MySQL stores NULL
        $deadline_val = $marks_deadline !== '' ? $marks_deadline : null;
        $stmt = $conn->prepare('UPDATE exams SET class = ?, marks_deadline = ? WHERE exam_id = ?');
        $stmt->bind_param('ssi', $exam_class, $deadline_val, $exam_id);
        if ($stmt->execute()) {
            $message = 'Exam settings updated successfully.';
            $edit_id = $exam_id;
            // No marks_deadline column in exam_subjects; skip updating subjects
        } else {
            $error = 'Failed to update exam settings: ' . $conn->error;
        }
        $stmt->close();
    }
}

/* ------------------------------------------------------------
 *  Data for the page – exams list & schedule overview
 * ------------------------------------------------------------ */
// Exams for the dropdown (no school filter needed)
$exam_list = $conn->query('SELECT e.exam_id, e.exam_name,
        (SELECT COUNT(subject_id) FROM exam_subjects es WHERE es.exam_id = e.exam_id) AS subject_count
    FROM exams e ORDER BY e.exam_name');

// Load exam for edit mode
$edit_exam = null;
if ($edit_id > 0) {
    $edit_exam = $conn->query("SELECT * FROM exams WHERE exam_id = {$edit_id}")->fetch_assoc();
}

// Full schedule overview – no school filter needed
$schedule = $conn->query('SELECT e.exam_id, e.exam_name, e.class, e.status, e.marks_deadline,
        (SELECT COUNT(subject_id) FROM exam_subjects es WHERE es.exam_id = e.exam_id) AS subject_count
    FROM exams e ORDER BY e.exam_id DESC');

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Settings &amp; Deadlines</title>
    <?php $module_css = 'exam_officer'; include __DIR__ . '/../common/head_assets.php'; ?>

</head>
<body>
<?php include '../common/header.php'; ?>
<div class="dashboard">
    <?php include '../common/sidebar.php'; ?>
    <div class="content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Exam Settings &amp; Deadlines</h1>
                <p class="stats-info">Assign classes and set marks submission deadlines for examinations.</p>
            </div>
            <a href="exams.php" class="btn btn-secondary">← All Exams</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="panel-grid">
            <!-- Form: Create / Edit exam deadline -->
            <div class="section">
                <h3><?php echo $edit_exam ? 'Edit Exam Settings' : 'Set Exam Deadlines'; ?></h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="exam_id">Select Exam</label>
                        <select name="exam_id" id="exam_id" required>
                            <option value="">— Choose exam —</option>
                            <?php if ($exam_list): while ($ex = $exam_list->fetch_assoc()): ?>
                                <option value="<?php echo $ex['exam_id']; ?>" <?php echo $edit_id === (int)$ex['exam_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ex['exam_name']); ?> (<?php echo (int)$ex['subject_count']; ?> Subjects)
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="class">Class</label>
                        <select name="class" id="class" required>
                            <option value="Form 2" <?php echo ($edit_exam['class'] ?? '') === 'Form 2' ? 'selected' : ''; ?>>Form 2</option>
                            <option value="Form 4" <?php echo ($edit_exam['class'] ?? '') === 'Form 4' ? 'selected' : ''; ?>>Form 4</option>
                        </select>
                    </div>
                    <div class="form-group" style="background:#f8fafc;padding:15px;border-radius:8px;border:1px solid #e2e8f0;margin-top:20px;">
                        <label for="marks_deadline" style="color:#0f172a;font-weight:600;">Submissions Deadline (Marks)</label>
                        <p style="font-size:0.8rem;color:#64748b;margin-bottom:10px;">Set the final date when teachers will be automatically locked out of submitting their marks.</p>
                        <input type="date" name="marks_deadline" id="marks_deadline" value="<?php echo htmlspecialchars(isset($edit_exam['marks_deadline']) ? date('Y-m-d', strtotime($edit_exam['marks_deadline'])) : ''); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top:15px;">Save Settings</button>
                </form>
            </div>

            <!-- Overview table -->
            <div class="section">
                <h3>Exam Deadlines Overview</h3>
                <div class="table-container">
                    <table class="table-striped">
                        <thead>
                            <tr>
                                <th>Exam</th>
                                <th>Subjects</th>
                                <th>Class</th>
                                <th>Marks Deadline</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($schedule): while ($row = $schedule->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['exam_name']); ?></td>
                                    <td><?php echo (int)$row['subject_count']; ?></td>
                                    <td><?php echo htmlspecialchars($row['class'] ?? '—'); ?></td>
                                    <td>
                                        <?php if (!empty($row['marks_deadline'])): ?>
                                            <strong style="color:#dc2626;">
                                                <?php echo date('d M Y', strtotime($row['marks_deadline'])); ?>
                                            </strong>
                                        <?php else: ?>
                                            <span style="color:#94a3b8;font-style:italic;">No deadline</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge badge-<?php echo str_replace('_', '-', $row['status']); ?>"><?php echo $row['status']; ?></span></td>
                                    <td class="actions"><a href="schedule.php?exam_id=<?php echo $row['exam_id']; ?>" class="btn btn-small btn-edit">Edit</a></td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../common/footer.php'; ?>
</body>
</html>
