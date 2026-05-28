<?php
session_start();

require_once '../config/db.php';
require_once '../common/email_service.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();
$message = '';
$message_type = '';

$admin_id = $_SESSION['user_id'];

/* ================= EXAMS (ONLY NON-APPROVED) ================= */
$exams = $conn->query("
    SELECT exam_id, exam_name 
    FROM exams 
    WHERE status != 'approved'
    ORDER BY exam_id DESC
");

$teachers = $conn->query("
    SELECT user_id, name, email 
    FROM users 
    WHERE role = 'teacher'
");

/* ================= HANDLE ASSIGN ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $teacher_id = $_POST['teacher_id'] ?? null;
    $role = $_POST['role'] ?? null;

    /* =====================================================
       BULK ASSIGN
    ===================================================== */
    if (!empty($_POST['exam_ids']) && $teacher_id && $role) {

        foreach ($_POST['exam_ids'] as $exam_id) {

            // prevent duplicates
            $check = $conn->prepare("
                SELECT COUNT(*) as total
                FROM exam_assignments
                WHERE exam_id = ?
                AND teacher_id = ?
                AND role = ?
                AND status = 'assigned'
            ");
            $check->bind_param("iis", $exam_id, $teacher_id, $role);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc()['total'] > 0;
            $check->close();

            if ($exists) continue;

            // insert assignment
            $stmt = $conn->prepare("
                INSERT INTO exam_assignments
                (exam_id, teacher_id, role, assigned_by, assigned_at, email_sent, status)
                VALUES (?, ?, ?, ?, NOW(), 0, 'assigned')
            ");
            $stmt->bind_param("iisi", $exam_id, $teacher_id, $role, $admin_id);
            $stmt->execute();
            $stmt->close();

            // get data for email
            $q = $conn->prepare("
                SELECT u.name, u.email, e.exam_name
                FROM users u
                JOIN exams e ON e.exam_id = ?
                WHERE u.user_id = ?
            ");
            $q->bind_param("ii", $exam_id, $teacher_id);
            $q->execute();
            $data = $q->get_result()->fetch_assoc();
            $q->close();

            if ($data) {
                send_email(
                    $data['email'],
                    "Exam Assignment Notification",
                    "Hello {$data['name']},

You have been assigned an exam:

Exam: {$data['exam_name']}
Role: {$role}

Please log in to your system to view details."
                );
            }

            // audit log
            $log = $conn->prepare("
                INSERT INTO audit_logs (user_id, action, details)
                VALUES (?, 'assign_exam', ?)
            ");

            $details = "Assigned exam ID $exam_id to teacher ID $teacher_id as $role";
            $log->bind_param("is", $admin_id, $details);
            $log->execute();
            $log->close();
        }

        $message = "Bulk assignment completed successfully.";
        $message_type = "success";
    }

    /* =====================================================
       SINGLE ASSIGN (FIXED: EMAIL + AUDIT + DUPLICATE CHECK)
    ===================================================== */
    if (!empty($_POST['exam_id']) && $teacher_id && $role) {

        $exam_id = $_POST['exam_id'];

        // duplicate check
        $check = $conn->prepare("
            SELECT COUNT(*) as total
            FROM exam_assignments
            WHERE exam_id = ?
            AND teacher_id = ?
            AND role = ?
            AND status = 'assigned'
        ");
        $check->bind_param("iis", $exam_id, $teacher_id, $role);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc()['total'] > 0;
        $check->close();

        if ($exists) {
            $message = "This assignment already exists.";
            $message_type = "error";
        } else {

            $stmt = $conn->prepare("
                INSERT INTO exam_assignments
                (exam_id, teacher_id, role, assigned_by, assigned_at, email_sent, status)
                VALUES (?, ?, ?, ?, NOW(), 0, 'assigned')
            ");
            $stmt->bind_param("iisi", $exam_id, $teacher_id, $role, $admin_id);
            $stmt->execute();
            $stmt->close();

            // get teacher + exam
            $q = $conn->prepare("
                SELECT u.name, u.email, e.exam_name
                FROM users u
                JOIN exams e ON e.exam_id = ?
                WHERE u.user_id = ?
            ");
            $q->bind_param("ii", $exam_id, $teacher_id);
            $q->execute();
            $data = $q->get_result()->fetch_assoc();
            $q->close();

            if ($data) {
                send_email(
                    $data['email'],
                    "Exam Assignment Notification",
                    "Hello {$data['name']},

You have been assigned an exam:

Exam: {$data['exam_name']}
Role: {$role}

Please log in to your dashboard."
                );
            }

            // audit log
            $log = $conn->prepare("
                INSERT INTO audit_logs (user_id, action, details)
                VALUES (?, 'assign_exam', ?)
            ");

            $details = "Single assignment: exam $exam_id assigned to teacher $teacher_id as $role";
            $log->bind_param("is", $admin_id, $details);
            $log->execute();
            $log->close();

            $message = "Teacher assigned successfully.";
            $message_type = "success";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Assign Exams</title>

<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/admin.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/form.css">

<style>
/* ================= GRID LIKE ADD USER ================= */
.main-content{
    width:100%;
    max-width:1100px;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:30px;
}

/* ================= CARD STYLE ================= */
.card{
    background:#fff;
    border-radius:16px;
    padding:20px;
    box-shadow:0 10px 25px rgba(0,0,0,0.05);
    display:flex;
    flex-direction:column;
}

.card h3{
    font-size:18px;
    margin-bottom:10px;
    color:#0f172a;
}

/* ================= EXAM CHECKBOX LIST ================= */
.exam-list{
    display:grid;
    grid-template-columns:1fr;
    gap:10px;
    max-height:300px;
    overflow:auto;
    padding:10px;
    border:1px solid #e2e8f0;
    border-radius:12px;
}

.exam-item{
    display:flex;
    align-items:center;
    gap:10px;
    padding:10px;
    border-radius:10px;
    background:#f8fafc;
    cursor:pointer;
}

.exam-item:hover{
    background:#eef2ff;
}

/* ================= BUTTON (LIKE ADD USER) ================= */
/* UPDATED BUTTON COLOR */
.btn-create{
    margin-top:15px;
    background:#334155;
    color:#fff;
    border:none;
    padding:12px;
    border-radius:12px;
    font-weight:600;
    cursor:pointer;
    transition:0.3s;
}

.btn-create:hover{
    background:#1e293b;
    transform:translateY(-2px);
}
</style>

</head>
<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
<?php include '../common/sidebar.php'; ?>

<div class="content">

    <!-- HEADER -->
    <div class="page-header">
        <div>
            <h2 class="page-title">Exam Assignment</h2>
            <p class="page-subtitle">Assign teachers to exams (single or bulk)</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- GRID -->
    <div class="main-content">

        <!-- LEFT: BULK ASSIGN -->
        <div class="card">

            <h3>Bulk Exam Assignment</h3>
            <p>Select multiple exams</p>

            <form method="POST">

                <div class="form-group">
                    <label>Teacher</label>
                    <select name="teacher_id" required>
                        <option value="">Select Teacher</option>
                        <?php while($t = $teachers->fetch_assoc()): ?>
                            <option value="<?= $t['user_id'] ?>">
                                <?= $t['name'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Role</label>
                    <select name="role" required>
                        <option value="item_writer">Item Writer</option>
                        <option value="moderator">Moderator</option>
                        <option value="marker">Marker</option>
                    </select>
                </div>

                <label>Available Exams</label>
                <div class="exam-list">

                    <?php while($e = $exams->fetch_assoc()): ?>
                        <label class="exam-item">
                            <input type="checkbox" name="exam_ids[]" value="<?= $e['exam_id'] ?>">
                            <?= $e['exam_name'] ?>
                        </label>
                    <?php endwhile; ?>

                </div>

                <button type="submit" class="btn-create">
                    Bulk Assign Exams
                </button>

            </form>
        </div>

        <!-- RIGHT: SINGLE ASSIGN -->
        <div class="card">

            <h3>Single Assignment</h3>

            <form method="POST">

                <div class="form-group">
                    <label>Exam</label>
                    <select name="exam_id" required>
                        <option value="">Select Exam</option>
                        <?php
                        $exams2 = $conn->query("SELECT exam_id, exam_name FROM exams WHERE status != 'approved'");
                        while($e = $exams2->fetch_assoc()):
                        ?>
                            <option value="<?= $e['exam_id'] ?>">
                                <?= $e['exam_name'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Teacher</label>
                    <select name="teacher_id" required>
                        <option value="">Select Teacher</option>
                        <?php
                        $teachers2 = $conn->query("SELECT user_id, name FROM users WHERE role='teacher'");
                        while($t = $teachers2->fetch_assoc()):
                        ?>
                            <option value="<?= $t['user_id'] ?>">
                                <?= $t['name'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Role</label>
                    <select name="role" required>
                        <option value="item_writer">Item Writer</option>
                        <option value="moderator">Moderator</option>
                        <option value="marker">Marker</option>
                    </select>
                </div>

                <button type="submit" class="btn-create">
                    Assign Teacher
                </button>

            </form>

        </div>

    </div>

</div>
</div>

<?php include '../common/footer.php'; ?>

</body>
</html>