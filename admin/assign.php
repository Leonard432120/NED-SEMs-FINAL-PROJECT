<?php
session_start();
require_once '../config/db.php';
require_once '../common/email_service.php';

/* ── Global safety net: never show a raw fatal error to the user ── */
set_exception_handler(function(Throwable $e) {
    $code = ($e instanceof mysqli_sql_exception && $e->getCode() === 1062) ? 'duplicate' : 'db';
    $msg  = $code === 'duplicate'
        ? 'This assignment already exists. Use Manage Assignments to change it.'
        : 'An unexpected error occurred. Please try again.';
    // Redirect back with flash
    header('Location: assign.php?err=' . urlencode($msg));
    exit();
});

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();
$admin_id = $_SESSION['user_id'];

$message      = '';
$message_type = '';

/* Pick up flash from global exception handler redirect */
if (!empty($_GET['err'])) {
    $message      = htmlspecialchars($_GET['err']);
    $message_type = 'error';
}

/* ================= FETCH DATA ================= */
$teachers = $conn->query("SELECT user_id, name, email, COALESCE(teacher_category, '') AS teacher_category 
                         FROM users WHERE role='teacher' ORDER BY name");

$subjects = $conn->query("SELECT subject_id, subject_name, subject_code, category 
                         FROM subjects WHERE status='active' ORDER BY subject_name");

/* ================= HANDLE POST (unchanged) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $role       = trim($_POST['role'] ?? '');

    if ($teacher_id && $subject_id && $role) {
        $tq = $conn->prepare("SELECT name, email, COALESCE(teacher_category,'') AS teacher_category FROM users WHERE user_id=?");
        $tq->bind_param("i", $teacher_id);
        $tq->execute();
        $teacher = $tq->get_result()->fetch_assoc();
        $tq->close();

        $sq = $conn->prepare("SELECT subject_name, category FROM subjects WHERE subject_id=?");
        $sq->bind_param("i", $subject_id);
        $sq->execute();
        $subject = $sq->get_result()->fetch_assoc();
        $sq->close();

        $teacher_category = strtolower(trim($teacher['teacher_category'] ?? ''));
        $subject_category = strtolower(trim($subject['category'] ?? ''));

        if ($teacher_category && $subject_category && $teacher_category !== $subject_category) {
            $message = "Category Mismatch! Teacher is from <strong>" . ucfirst($teacher_category) . "</strong> but subject is <strong>" . ucfirst($subject_category) . "</strong>.";
            $message_type = "error";
        } else {
            // Unique key is (subject_id, role) — check if this role is already taken for this subject
            $check = $conn->prepare("SELECT assignment_id, u.name AS assigned_to
                FROM subject_assignments sa
                JOIN users u ON u.user_id = sa.teacher_id
                WHERE sa.subject_id = ? AND sa.role = ? AND sa.status = 'assigned'");
            $check->bind_param("is", $subject_id, $role);
            $check->execute();
            $taken = $check->get_result()->fetch_assoc();
            $check->close();

            if ($taken) {
                $message = "This role (<strong>" . ucwords(str_replace('_', ' ', $role)) . "</strong>) is already assigned to <strong>" . htmlspecialchars($taken['assigned_to']) . "</strong> for this subject. Reassign from Manage Assignments instead.";
                $message_type = "error";
            } else {
                try {
                    /* Temporarily allow errors as warnings so our catch works in PHP 8.1+ */
                    mysqli_report(MYSQLI_REPORT_OFF);

                    $stmt = $conn->prepare("INSERT INTO subject_assignments
                        (subject_id, teacher_id, teacher_category, role, assigned_by, assigned_at, email_sent, status)
                        VALUES (?, ?, ?, ?, ?, NOW(), 1, 'assigned')");
                    $stmt->bind_param("iissi", $subject_id, $teacher_id, $teacher['teacher_category'], $role, $admin_id);

                    if ($stmt->execute()) {
                        $stmt->close();
                        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

                        if ($teacher && $subject) {
                            send_email($teacher['email'], 'Subject Assignment Notification',
                                "Hello {$teacher['name']},\n\nYou have been assigned:\n\nSubject: {$subject['subject_name']}\nCategory: {$subject['category']}\nRole: {$role}");
                        }
                        $message      = 'Teacher assigned successfully.';
                        $message_type = 'success';
                    } else {
                        $stmt->close();
                        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                        // errno 1062 = duplicate entry
                        if ($conn->errno === 1062) {
                            $message = 'This role (<strong>' . ucwords(str_replace('_', ' ', $role)) . '</strong>) already has an assignment for this subject. Use Manage Assignments to reassign.';
                        } else {
                            $message = 'Database error (' . $conn->errno . '): ' . htmlspecialchars($conn->error);
                        }
                        $message_type = 'error';
                    }
                } catch (Throwable $e) {
                    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                    $message      = 'Could not save assignment: ' . htmlspecialchars($e->getMessage());
                    $message_type = 'error';
                }
            }
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Subject - Teacher</title>
    <?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>

    <style>
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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
        .warning-box {
            background: #fef3c7;
            border-left: 5px solid #f59e0b;
            padding: 15px;
            border-radius: 8px;
            color: #92400e;
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
                <h2 class="page-title">Subject Assignment</h2>
                <p class="page-subtitle">Smart filtering by category</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" class="form-grid" id="assignForm">

                <div class="form-group">
                    <label>Subject <span style="color:red;">*</span></label>
                    <select name="subject_id" id="subjectSelect" required onchange="filterTeachers()">
                        <option value="">Select Subject</option>
                        <?php $subjects->data_seek(0); while($s = $subjects->fetch_assoc()): ?>
                            <option value="<?= $s['subject_id'] ?>" data-category="<?= strtolower($s['category'] ?? '') ?>">
                                <?= htmlspecialchars($s['subject_name']) ?> (<?= htmlspecialchars($s['subject_code'] ?? '') ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Teacher <span style="color:red;">*</span></label>
                    <select name="teacher_id" id="teacherSelect" required onchange="filterSubjects()">
                        <option value="">Select Teacher</option>
                        <?php $teachers->data_seek(0); while($t = $teachers->fetch_assoc()): ?>
                            <option value="<?= $t['user_id'] ?>" data-category="<?= strtolower($t['teacher_category']) ?>">
                                <?= htmlspecialchars($t['name']) ?> 
                                <?= $t['teacher_category'] ? '(' . ucfirst($t['teacher_category']) . ')' : '' ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Role <span style="color:red;">*</span></label>
                    <select name="role" required>
                        <option value="item_writer">Item Writer</option>
                        <option value="moderator">Moderator</option>
                        <option value="marker">Marker</option>
                    </select>
                </div>

                <div id="validationWarning" class="warning-box" style="display: none; grid-column: 1 / -1;"></div>

                <div style="grid-column: 1 / -1; text-align: right; margin-top: 20px;">
                    <button type="submit" class="btn btn-dark" id="submitBtn">Assign Teacher</button>
                </div>

            </form>
        </div>

    </div>
</div>

<script>
// Store original options
let allTeachers = [];
let allSubjects = [];

document.addEventListener('DOMContentLoaded', () => {
    allTeachers = Array.from(document.getElementById('teacherSelect').options);
    allSubjects = Array.from(document.getElementById('subjectSelect').options);
});

function filterTeachers() {
    const subjectCat = document.getElementById('subjectSelect').value ? 
                       document.getElementById('subjectSelect').selectedOptions[0].dataset.category : '';
    const teacherSelect = document.getElementById('teacherSelect');
    const currentTeacher = teacherSelect.value;   // Preserve current selection

    teacherSelect.innerHTML = '<option value="">Select Teacher</option>';

    allTeachers.forEach(opt => {
        if (!opt.value) return;
        if (!subjectCat || opt.dataset.category === subjectCat) {
            const newOpt = opt.cloneNode(true);
            teacherSelect.appendChild(newOpt);
        }
    });

    // Restore previous teacher if still valid
    if (currentTeacher) {
        teacherSelect.value = currentTeacher;
    }
}

function filterSubjects() {
    const teacherCat = document.getElementById('teacherSelect').value ? 
                       document.getElementById('teacherSelect').selectedOptions[0].dataset.category : '';
    const subjectSelect = document.getElementById('subjectSelect');
    const currentSubject = subjectSelect.value;   // Preserve current selection

    subjectSelect.innerHTML = '<option value="">Select Subject</option>';

    allSubjects.forEach(opt => {
        if (!opt.value) return;
        if (!teacherCat || opt.dataset.category === teacherCat) {
            const newOpt = opt.cloneNode(true);
            subjectSelect.appendChild(newOpt);
        }
    });

    // Restore previous subject if still valid
    if (currentSubject) {
        subjectSelect.value = currentSubject;
    }
}

// Clear warning when selection changes
document.getElementById('subjectSelect').addEventListener('change', () => {
    document.getElementById('validationWarning').style.display = 'none';
});
document.getElementById('teacherSelect').addEventListener('change', () => {
    document.getElementById('validationWarning').style.display = 'none';
});
</script>

<?php include '../common/footer.php'; ?>

</body>
</html>