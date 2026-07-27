<?php
session_start();
require_once '../config/db.php';
require_once '../common/email_service.php';

/* ── Global error handler ── */
set_exception_handler(function(Throwable $e) {
    $code = ($e instanceof mysqli_sql_exception && $e->getCode() === 1062) ? 'duplicate' : 'db';
    $msg  = $code === 'duplicate'
        ? 'This assignment already exists. Use Manage Assignments to change it.'
        : 'An unexpected error occurred. Please try again.';
    header('Location: assign.php?err=' . urlencode($msg));
    exit();
});

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn     = get_db_connection();
$admin_id = $_SESSION['user_id'];

$message      = '';
$message_type = '';

if (!empty($_GET['err'])) {
    $message      = htmlspecialchars($_GET['err']);
    $message_type = 'error';
}

/* ── Pre-fill from Manage Assignments link ── */
$allowed_roles      = ['item_writer', 'moderator'];
$prefill_subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$prefill_exam_id    = isset($_GET['exam_id'])    ? (int)$_GET['exam_id']    : 0;
$prefill_role       = trim($_GET['role'] ?? '');
if (!in_array($prefill_role, $allowed_roles, true)) { $prefill_role = ''; }
$came_from_manage = ($prefill_subject_id > 0 && $prefill_role !== '');

/* ══════════════════════════════════════════
   FETCH DATA
══════════════════════════════════════════ */
$teachers = $conn->query("SELECT user_id, name, email, COALESCE(teacher_category,'') AS teacher_category
                          FROM users WHERE role='teacher' ORDER BY name");

$subjects = $conn->query("SELECT subject_id, subject_name, subject_code, category
                          FROM subjects WHERE status='active' ORDER BY subject_name");

$exams = $conn->query("SELECT exam_id, exam_name, exam_code, class, start_date, end_date, status
                       FROM exams ORDER BY exam_id DESC");
$exams_list = $exams ? $exams->fetch_all(MYSQLI_ASSOC) : [];

/* ══════════════════════════════════════════
   HANDLE POST
══════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacher_id   = (int)($_POST['teacher_id']   ?? 0);
    $subject_id   = (int)($_POST['subject_id']   ?? 0);
    $exam_id      = (int)($_POST['exam_id']      ?? 0);
    $role         = trim($_POST['role']           ?? '');
    $access_from  = trim($_POST['access_from']   ?? '');
    $access_until = trim($_POST['access_until']  ?? '');

    if ($role === 'marker') {
        $message = "Markers are assigned by headteachers, not through this page.";
        $message_type = "error";
    } elseif (!in_array($role, $allowed_roles, true)) {
        $message = "Please select a valid role.";
        $message_type = "error";
    } elseif (!$teacher_id || !$subject_id || !$exam_id) {
        $message = "Please select a subject, exam, teacher, and role.";
        $message_type = "error";
    } elseif ($access_from === '' || $access_until === '') {
        $message = "Please set both the access start and end dates for this role.";
        $message_type = "error";
    } elseif (strtotime($access_until) <= strtotime($access_from)) {
        $message = "Access end date must be after the start date.";
        $message_type = "error";
    } else {
        /* Validate dates fall within exam window */
        $ex_stmt = $conn->prepare("SELECT start_date, end_date, exam_name FROM exams WHERE exam_id = ?");
        $ex_stmt->bind_param("i", $exam_id);
        $ex_stmt->execute();
        $ex = $ex_stmt->get_result()->fetch_assoc();
        $ex_stmt->close();

        $exam_start = strtotime($ex['start_date'] ?? '2000-01-01');
        $exam_end   = strtotime($ex['end_date']   ?? '2099-12-31');
        $af_ts      = strtotime($access_from);
        $au_ts      = strtotime($access_until);

        if ($af_ts < $exam_start || $au_ts > $exam_end) {
            $fmt = fn($d) => date('d M Y', strtotime($d));
            $message = "Access window must fall within the exam period: <strong>{$fmt($ex['start_date'])}</strong> — <strong>{$fmt($ex['end_date'])}</strong>.";
            $message_type = "error";
        } else {
            /* Fetch teacher & subject details */
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
                /* Check if role already taken for this subject+exam */
                $check = $conn->prepare("SELECT assignment_id, u.name AS assigned_to
                    FROM subject_assignments sa
                    JOIN users u ON u.user_id = sa.teacher_id
                    WHERE sa.subject_id = ? AND sa.exam_id = ? AND sa.role = ? AND sa.status = 'assigned'");
                $check->bind_param("iis", $subject_id, $exam_id, $role);
                $check->execute();
                $taken = $check->get_result()->fetch_assoc();
                $check->close();

                if ($taken) {
                    $message = "This role (<strong>" . ucwords(str_replace('_', ' ', $role)) . "</strong>) is already assigned to <strong>" . htmlspecialchars($taken['assigned_to']) . "</strong> for this subject & exam. Reassign from Manage Assignments instead.";
                    $message_type = "error";
                } else {
                    try {
                        mysqli_report(MYSQLI_REPORT_OFF);
                        $af_val = date('Y-m-d H:i:s', $af_ts);
                        $au_val = date('Y-m-d H:i:s', $au_ts);

                        $stmt = $conn->prepare("INSERT INTO subject_assignments
                            (subject_id, exam_id, teacher_id, teacher_category, role, assigned_by, assigned_at, access_from, access_until, email_sent, status)
                            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, 1, 'assigned')");
                        $stmt->bind_param("iiississ",
                            $subject_id, $exam_id, $teacher_id,
                            $teacher['teacher_category'], $role, $admin_id,
                            $af_val, $au_val
                        );

                        if ($stmt->execute()) {
                            $stmt->close();
                            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

                            /* Email notification */
                            $role_label = ucwords(str_replace('_', ' ', $role));
                            $af_fmt = date('d M Y H:i', $af_ts);
                            $au_fmt = date('d M Y H:i', $au_ts);
                            send_email(
                                $teacher['email'],
                                "Exam Assignment Notification — {$role_label}",
                                "Hello {$teacher['name']},\n\n" .
                                "You have been assigned as {$role_label} for:\n\n" .
                                "  Exam:    {$ex['exam_name']}\n" .
                                "  Subject: {$subject['subject_name']}\n" .
                                "  Role:    {$role_label}\n\n" .
                                "Your access window:\n" .
                                "  From:    {$af_fmt}\n" .
                                "  Until:   {$au_fmt}\n\n" .
                                "You will only be able to access the exam paper during this period. " .
                                "The system will automatically lock your access outside these dates.\n\n" .
                                "Please log in to your dashboard to get started.\n\n" .
                                "NED-SEMS Security Notice: All access is monitored and logged."
                            );

                            log_audit_event('SUBJECT_ASSIGNED', [
                                'subject_id'   => $subject_id,
                                'exam_id'      => $exam_id,
                                'teacher_id'   => $teacher_id,
                                'role'         => $role,
                                'access_from'  => $af_val,
                                'access_until' => $au_val,
                            ], null, $conn);

                            if ($came_from_manage) {
                                $conn->close();
                                header("Location: manage_assignments.php?assigned=1");
                                exit();
                            }

                            $message      = "Teacher assigned successfully with scheduled access window.";
                            $message_type = 'success';
                            $prefill_subject_id = 0;
                            $prefill_role        = '';
                            $prefill_exam_id     = 0;
                        } else {
                            $stmt->close();
                            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                            $message = $conn->errno === 1062
                                ? 'This role already has an assignment for this subject & exam. Use Manage Assignments to reassign.'
                                : 'Database error (' . $conn->errno . '): ' . htmlspecialchars($conn->error);
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
        if ($message_type === 'error') {
            $prefill_subject_id = $subject_id;
            $prefill_role       = $role;
            $prefill_exam_id    = $exam_id;
            $came_from_manage   = ($prefill_subject_id > 0 && $prefill_role !== '');
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
    <title>Assign Subject — NED-SEMS Admin</title>
    <?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>
    <style>
        .assign-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }
        @media (max-width: 768px) { .assign-grid { grid-template-columns: 1fr; } }
        .full-span { grid-column: 1 / -1; }

        .window-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid var(--primary-dark, #1d4ed8);
            border-radius: 10px;
            padding: 18px 20px;
        }
        .window-box h4 {
            margin: 0 0 4px 0;
            font-size: 0.88rem;
            font-weight: 700;
            color: #0f172a;
        }
        .window-box p {
            font-size: 0.78rem;
            color: #64748b;
            margin: 0 0 14px 0;
            line-height: 1.4;
        }
        .window-inner {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .exam-banner {
            display: none;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.82rem;
            color: #1e40af;
            margin-top: 8px;
        }
        .exam-banner strong { color: #1e3a8a; }
        .warning-box {
            background: #fef3c7;
            border-left: 5px solid #f59e0b;
            padding: 12px 16px;
            border-radius: 8px;
            color: #92400e;
            font-size: 0.85rem;
        }
        .prefill-note {
            background: #eff6ff;
            border-left: 5px solid #3b82f6;
            padding: 12px 15px;
            border-radius: 8px;
            color: #1e3a8a;
            font-size: 0.875rem;
            grid-column: 1 / -1;
        }
        .role-hint {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 4px;
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
                <p class="page-subtitle">Assign an Item Writer or Moderator and set their secure access window</p>
            </div>
            <div class="header-actions">
                <a href="manage_assignments.php" class="btn btn-secondary btn-small">Manage Assignments</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" id="assignForm">
                <div class="assign-grid">

                    <?php if ($came_from_manage): ?>
                        <div class="prefill-note full-span">
                            Subject and role pre-filled from Manage Assignments — just pick a teacher and set the access window below.
                        </div>
                    <?php endif; ?>

                    <!-- SUBJECT -->
                    <div class="form-group">
                        <label>Subject <span style="color:red;">*</span></label>
                        <select name="subject_id" id="subjectSelect" required onchange="filterTeachers(); loadExamDates();">
                            <option value="">Select Subject</option>
                            <?php $subjects->data_seek(0); while ($s = $subjects->fetch_assoc()): ?>
                                <option value="<?= $s['subject_id'] ?>"
                                        data-category="<?= strtolower($s['category'] ?? '') ?>"
                                        <?= $prefill_subject_id === (int)$s['subject_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['subject_name']) ?> (<?= htmlspecialchars($s['subject_code'] ?? '') ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- EXAM -->
                    <div class="form-group">
                        <label>Examination <span style="color:red;">*</span></label>
                        <select name="exam_id" id="examSelect" required onchange="loadExamDates();">
                            <option value="">Select Exam</option>
                            <?php foreach ($exams_list as $ex): ?>
                                <option value="<?= $ex['exam_id'] ?>"
                                        data-start="<?= $ex['start_date'] ?>"
                                        data-end="<?= $ex['end_date'] ?>"
                                        <?= $prefill_exam_id === (int)$ex['exam_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ex['exam_name']) ?> (<?= htmlspecialchars($ex['class']) ?>, <?= htmlspecialchars($ex['exam_code'] ?? '') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <!-- Exam date banner -->
                        <div class="exam-banner" id="examDateBanner"></div>
                    </div>

                    <!-- TEACHER -->
                    <div class="form-group">
                        <label>Teacher <span style="color:red;">*</span></label>
                        <select name="teacher_id" id="teacherSelect" required onchange="filterSubjects();">
                            <option value="">Select Teacher</option>
                            <?php $teachers->data_seek(0); while ($t = $teachers->fetch_assoc()): ?>
                                <option value="<?= $t['user_id'] ?>" data-category="<?= strtolower($t['teacher_category']) ?>">
                                    <?= htmlspecialchars($t['name']) ?>
                                    <?= $t['teacher_category'] ? '(' . ucfirst($t['teacher_category']) . ')' : '' ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- ROLE -->
                    <div class="form-group">
                        <label>Role <span style="color:red;">*</span></label>
                        <select name="role" id="roleSelect" required onchange="updateWindowLabels();">
                            <option value="item_writer" <?= $prefill_role === 'item_writer' ? 'selected' : '' ?>>Item Writer</option>
                            <option value="moderator"   <?= $prefill_role === 'moderator'   ? 'selected' : '' ?>>Moderator</option>
                        </select>
                        <div class="role-hint">Markers are assigned separately by headteachers.</div>
                    </div>

                    <!-- ACCESS WINDOW BOX -->
                    <div class="window-box full-span">
                        <h4 id="windowBoxTitle">Item Writer — Composition Access Window</h4>
                        <p id="windowBoxDesc">Set the date range during which this teacher can access and compose exam questions. Must fall within the exam's scheduled period.</p>
                        <div class="window-inner">
                            <div class="form-group">
                                <label for="access_from">Access Starts <span style="color:red;">*</span></label>
                                <input type="date" name="access_from" id="access_from" required>
                                <div class="role-hint">Earliest possible: <span id="hint_start">—</span></div>
                            </div>
                            <div class="form-group">
                                <label for="access_until">Access Ends <span style="color:red;">*</span></label>
                                <input type="date" name="access_until" id="access_until" required>
                                <div class="role-hint">Latest possible: <span id="hint_end">—</span></div>
                            </div>
                        </div>
                        <div id="windowWarning" style="display:none;" class="warning-box" style="margin-top:10px;"></div>
                    </div>

                    <!-- FORM ACTIONS -->
                    <div class="full-span" style="display:flex; justify-content:space-between; align-items:center; margin-top: 8px;">
                        <?php if ($came_from_manage): ?>
                            <a href="manage_assignments.php" class="btn btn-secondary">Cancel &amp; go back</a>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-dark" id="submitBtn">Assign Teacher</button>
                    </div>

                </div><!-- /assign-grid -->
            </form>
        </div>

    </div>
</div>

<script>
/* ── Exam dates store (from PHP) ── */
const examOpts = Array.from(document.getElementById('examSelect').options);
let examStartDate = '';
let examEndDate   = '';

/* ── All teacher & subject options (for filtering) ── */
let allTeachers = [];
let allSubjects  = [];

document.addEventListener('DOMContentLoaded', () => {
    allTeachers = Array.from(document.getElementById('teacherSelect').options);
    allSubjects  = Array.from(document.getElementById('subjectSelect').options);

    if (document.getElementById('subjectSelect').value) filterTeachers();
    loadExamDates();
    updateWindowLabels();
});

function loadExamDates() {
    const examSel = document.getElementById('examSelect');
    const opt = examSel.selectedOptions[0];
    const banner = document.getElementById('examDateBanner');

    if (!opt || !opt.value) {
        examStartDate = '';
        examEndDate   = '';
        banner.style.display = 'none';
        document.getElementById('access_from').removeAttribute('min');
        document.getElementById('access_from').removeAttribute('max');
        document.getElementById('access_until').removeAttribute('min');
        document.getElementById('access_until').removeAttribute('max');
        document.getElementById('hint_start').textContent = '—';
        document.getElementById('hint_end').textContent   = '—';
        return;
    }

    examStartDate = opt.dataset.start || '';
    examEndDate   = opt.dataset.end   || '';

    if (examStartDate && examEndDate) {
        const fmtDate = d => {
            const dt = new Date(d);
            return dt.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});
        };

        banner.innerHTML = `Exam runs from <strong>${fmtDate(examStartDate)}</strong> to <strong>${fmtDate(examEndDate)}</strong> — access window must fall within this range.`;
        banner.style.display = 'block';

        document.getElementById('access_from').min  = examStartDate;
        document.getElementById('access_from').max  = examEndDate;
        document.getElementById('access_until').min = examStartDate;
        document.getElementById('access_until').max = examEndDate;
        document.getElementById('hint_start').textContent = fmtDate(examStartDate);
        document.getElementById('hint_end').textContent   = fmtDate(examEndDate);
    }
}

function updateWindowLabels() {
    const role = document.getElementById('roleSelect').value;
    const titleEl = document.getElementById('windowBoxTitle');
    const descEl  = document.getElementById('windowBoxDesc');

    if (role === 'item_writer') {
        titleEl.textContent = 'Item Writer — Composition Access Window';
        descEl.textContent  = 'Set the dates during which this teacher can access and compose exam questions. Must fall within the exam\'s scheduled period. Access is automatically locked outside this window.';
    } else {
        titleEl.textContent = 'Moderator — Moderation Access Window';
        descEl.textContent  = 'Set the dates during which this teacher can review and moderate exam questions. This window should begin only after the item writer has finished composing. Must fall within the exam\'s scheduled period.';
    }
}

function filterTeachers() {
    const subjectCat = document.getElementById('subjectSelect').value
        ? document.getElementById('subjectSelect').selectedOptions[0].dataset.category : '';
    const teacherSelect = document.getElementById('teacherSelect');
    const currentTeacher = teacherSelect.value;

    teacherSelect.innerHTML = '<option value="">Select Teacher</option>';
    allTeachers.forEach(opt => {
        if (!opt.value) return;
        if (!subjectCat || opt.dataset.category === subjectCat) {
            teacherSelect.appendChild(opt.cloneNode(true));
        }
    });
    if (currentTeacher) teacherSelect.value = currentTeacher;
}

function filterSubjects() {
    const teacherCat = document.getElementById('teacherSelect').value
        ? document.getElementById('teacherSelect').selectedOptions[0].dataset.category : '';
    const subjectSelect = document.getElementById('subjectSelect');
    const currentSubject = subjectSelect.value;

    subjectSelect.innerHTML = '<option value="">Select Subject</option>';
    allSubjects.forEach(opt => {
        if (!opt.value) return;
        if (!teacherCat || opt.dataset.category === teacherCat) {
            subjectSelect.appendChild(opt.cloneNode(true));
        }
    });
    if (currentSubject) subjectSelect.value = currentSubject;
}

/* ── Client-side date validation ── */
document.getElementById('assignForm').addEventListener('submit', function(e) {
    const af = document.getElementById('access_from').value;
    const au = document.getElementById('access_until').value;
    const warn = document.getElementById('windowWarning');

    if (af && au && new Date(au) <= new Date(af)) {
        warn.textContent = 'Access end date must be after the start date.';
        warn.style.display = 'block';
        e.preventDefault();
        return;
    }

    if (examStartDate && examEndDate && af && au) {
        if (af < examStartDate || au > examEndDate) {
            warn.textContent = `Access window must fall within the exam period (${examStartDate} — ${examEndDate}).`;
            warn.style.display = 'block';
            e.preventDefault();
            return;
        }
    }

    warn.style.display = 'none';
});

/* Clear warning on input change */
['access_from','access_until'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => {
        document.getElementById('windowWarning').style.display = 'none';
    });
});
</script>

<?php include '../common/footer.php'; ?>
</body>
</html>