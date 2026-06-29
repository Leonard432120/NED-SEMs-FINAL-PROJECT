<?php
/* ════════════════════════════════════════════════════════════════
   teacher/enter_marks.php
   Teacher selects exam → subject → enters/submits marks per student
   ════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/teacher_init.php';
require_once __DIR__ . '/../common/grade_helper.php';

$conn      = get_db_connection();
$teacher_id = (int)$_SESSION['user_id'];
$message   = '';
$msg_type  = '';

/* ── EXAM ── */
$exam_id    = (int)($_GET['exam_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);

$school_id = (int)($_SESSION['school_id'] ?? 0);

/* ── SECURITY CHECK: URL Manipulation ── */
$is_assigned = false;
$deadline = null;
$override_lock = 0;
if ($exam_id > 0 && $subject_id > 0) {
    $stmt = $conn->prepare("
        SELECT deadline, override_lock 
        FROM marking_assignments
        WHERE exam_id = ? AND subject_id = ? AND teacher_id = ? AND school_id = ?
    ");
    $stmt->bind_param("iiii", $exam_id, $subject_id, $teacher_id, $school_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $is_assigned = true;
        $assignment_data = $res->fetch_assoc();
        $deadline = $assignment_data['deadline'];
        $override_lock = (int)$assignment_data['override_lock'];
    }
    $stmt->close();
    
    if (!$is_assigned) {
        $message = "You are not assigned to enter marks for this subject in this exam at your school.";
        $msg_type = "error";
        $exam_id = 0;
        $subject_id = 0;
    }
}

/* ── FETCH EXAMS WHERE TEACHER HAS ASSIGNED SUBJECTS (Active, Draft, or Ready) ── */
$my_exams = $conn->query("
    SELECT DISTINCT e.exam_id, e.exam_name, e.class, e.status
    FROM exams e
    JOIN marking_assignments ma ON ma.exam_id = e.exam_id
    WHERE e.status IN ('active', 'draft', 'ready') 
      AND ma.teacher_id = {$teacher_id} 
      AND ma.school_id = {$school_id}
    ORDER BY e.exam_name
")->fetch_all(MYSQLI_ASSOC);

/* ── FETCH ASSIGNED SUBJECTS FOR CHOSEN EXAM ── */
$exam       = null;
$my_subjects = [];
if ($exam_id > 0) {
    /* ── EXAM DETAILS ── */
    $stmt = $conn->prepare("SELECT * FROM exams WHERE exam_id = ?");
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $exam = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!empty($exam['marks_deadline'])) {
        $deadline = $exam['marks_deadline'];
    }

    $my_subjects = $conn->query("
        SELECT DISTINCT es.id AS es_id, es.subject_id, s.subject_name, s.subject_code, es.total_marks, ma.deadline
        FROM exam_subjects es
        JOIN subjects s ON s.subject_id = es.subject_id
        JOIN marking_assignments ma ON es.subject_id = ma.subject_id AND es.exam_id = ma.exam_id
        WHERE es.exam_id = {$exam_id} 
          AND ma.teacher_id = {$teacher_id} 
          AND ma.school_id = {$school_id}
        ORDER BY s.subject_name
    ")->fetch_all(MYSQLI_ASSOC);
}

/* ── STUDENTS FOR THIS EXAM CLASS ── */
$students = [];
$marks_map = [];
$subject_info = null;
$submitted    = false;

if ($exam_id > 0 && $subject_id > 0 && $exam) {
    $stmt = $conn->prepare("
        SELECT s.subject_name, s.subject_code, es.total_marks
        FROM exam_subjects es
        JOIN subjects s ON s.subject_id = es.subject_id
        WHERE es.exam_id = ? AND es.subject_id = ?
    ");
    $stmt->bind_param("ii", $exam_id, $subject_id);
    $stmt->execute();
    $subject_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    /* Fetch students of the exam's class from the teacher's school */
    $school_id = (int)($_SESSION['school_id'] ?? 0);
    $stmt = $conn->prepare("
        SELECT st.student_id, st.name, st.exam_number, st.class,
               sc.school_name
        FROM students st
        LEFT JOIN schools sc ON sc.school_id = st.school_id
        WHERE st.class = ? AND st.status = 'active' AND st.school_id = ?
        ORDER BY sc.school_name, st.name
    ");
    $stmt->bind_param("si", $exam['class'], $school_id);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    /* Existing marks for this exam+subject by this teacher */
    $m = $conn->query("
        SELECT student_id, score, grade, status, notes, submitted_at
        FROM marks
        WHERE exam_id = {$exam_id} AND subject_id = {$subject_id} AND teacher_id = {$teacher_id}
    ");
    while ($row = $m->fetch_assoc()) {
        $marks_map[$row['student_id']] = $row;
    }

    /* Check if already submitted */
    $rejection_reason = '';
    $hours_remaining = 0;
    if (!empty($marks_map)) {
        $first = reset($marks_map);
        $submitted = false;
        
        if ($first['status'] === 'approved') {
            $submitted = true;
        } elseif ($first['status'] === 'submitted') {
            if (!empty($first['submitted_at'])) {
                $lock_time = strtotime('+1 day', strtotime($first['submitted_at']));
                if (time() >= $lock_time) {
                    $submitted = true;
                } else {
                    $hours_remaining = round(($lock_time - time()) / 3600, 1);
                }
            } else {
                $submitted = true;
            }
        }

        foreach ($marks_map as $mk) {
            if ($mk['status'] === 'rejected' && !empty($mk['notes'])) {
                $rejection_reason = $mk['notes'];
                break;
            }
        }
    }
}

/* ══════════════════════════════════
   POST — Save draft OR Submit
   ══════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $exam_id > 0 && $subject_id > 0) {
    $action     = trim($_POST['form_action'] ?? 'save');
    $new_status = ($action === 'submit') ? 'submitted' : 'draft';
    $total_marks = (int)($subject_info['total_marks'] ?? 100);

    $is_past_deadline = false;
    if ($deadline && strtotime($deadline) < strtotime(date('Y-m-d'))) {
        $is_past_deadline = true;
    }
    $is_locked = $is_past_deadline && ($override_lock === 0);

    if ($is_locked) {
        $message = "This marking assignment has been locked because the deadline has passed. Please contact the Headteacher to request an unlock.";
        $msg_type = "error";
    } elseif ($submitted && $action !== 'save') {
        $message  = 'Marks already submitted. Contact the Examination Officer to unlock.';
        $msg_type = 'error';
    } elseif ($is_past_deadline && $action === 'submit') {
        $message  = 'Submission deadline has passed. You can only save draft marks.';
        $msg_type = 'error';
    } else {
        $errors = 0;
        foreach ($_POST['marks'] as $sid => $score_raw) {
            $sid   = (int)$sid;
            $score = trim($score_raw);
            if ($score === '') continue;
            $score = max(0, min((float)$score, $total_marks));
            $grade = calcGrade(($score / $total_marks) * 100);

            $now   = date('Y-m-d H:i:s');

            /* Upsert */
            $chk = $conn->prepare("SELECT mark_id, status, submitted_at FROM marks WHERE exam_id=? AND subject_id=? AND student_id=?");
            $chk->bind_param("iii", $exam_id, $subject_id, $sid);
            $chk->execute();
            $existing = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($existing) {
                if ($existing['status'] === 'approved') continue; // locked
                if ($existing['status'] === 'submitted' && (empty($existing['submitted_at']) || strtotime($existing['submitted_at']) <= strtotime('-1 day'))) continue; // locked
                
                $upd = $conn->prepare("UPDATE marks SET score=?, grade=?, status=?, submitted_at=?, submission_status='submitted', notes=NULL WHERE mark_id=?");
                
                // If it's being submitted, either keep the old timestamp or start a new 24h window
                $submitted_at = null;
                if ($new_status === 'submitted') {
                    $submitted_at = !empty($existing['submitted_at']) ? $existing['submitted_at'] : $now;
                }
                
                $upd->bind_param("dsssi", $score, $grade, $new_status, $submitted_at, $existing['mark_id']);
                $upd->execute(); $upd->close();
            } else {
                $submitted_at = ($new_status === 'submitted') ? $now : null;
                $ins = $conn->prepare("
                    INSERT INTO marks (student_id, exam_id, subject_id, teacher_id, score, grade, status,
                                       submission_status, submitted_by, submitted_at, created_at)
                    VALUES (?,?,?,?,?,?,?,'submitted',?,?,NOW())
                ");
                $ins->bind_param("iiiidssss", $sid, $exam_id, $subject_id, $teacher_id,
                                  $score, $grade, $new_status, $teacher_id, $submitted_at);
                $ins->execute(); $ins->close();
            }
        }

        $message  = $action === 'submit'
            ? 'Marks submitted successfully. They are now locked for review.'
            : 'Draft marks saved successfully.';
        $msg_type = 'success';
        /* Reload to reflect new state */
        header("Location: enter_marks.php?exam_id={$exam_id}&subject_id={$subject_id}&msg=" . urlencode($message));
        exit();
    }
}

if (!empty($_GET['msg'])) {
    $message  = htmlspecialchars($_GET['msg']);
    $msg_type = 'success';
}

$conn->close();
$module_css = 'teacher';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enter Marks | NED-SEMS</title>
<?php /* head_assets already included above */ ?>
<style>
.step-bar{display:flex;gap:0;margin-bottom:24px;}
.step{flex:1;text-align:center;padding:10px;font-size:.8rem;font-weight:600;
      border-bottom:3px solid var(--border-color);color:var(--text-muted);}
.step.active{border-color:var(--primary-dark);color:var(--primary-dark);}
.step.done{border-color:#22c55e;color:#16a34a;}
.mark-input{width:90px;padding:7px 10px;border:1px solid var(--border-color);
            border-radius:var(--border-radius);font-size:.9rem;text-align:center;}
.mark-input:focus{outline:none;border-color:var(--primary-dark);}
.mark-input:disabled{background:#f1f5f9;color:var(--text-muted);}
.grade-badge{display:inline-block;min-width:32px;text-align:center;padding:3px 8px;
             border-radius:4px;font-size:.78rem;font-weight:700;}
.submitted-banner{background:#fef9c3;border:1px solid #fde047;color:#713f12;
                  padding:12px 16px;border-radius:var(--border-radius);margin-bottom:16px;
                  font-size:.9rem;}
</style>
</head>
<body>
<?php include __DIR__ . '/../common/header.php'; ?>
<div class="dashboard">
<?php include __DIR__ . '/../common/sidebar.php'; ?>
<div class="content">

<!-- Page header -->
<div class="page-header">
    <div>
        <h2 class="page-title">Enter Marks</h2>
        <p class="page-subtitle">Enter student marks by exam and subject</p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary">Back</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $msg_type ?>"><?= $message ?></div>
<?php endif; ?>

<!-- STEP 1 — Select Exam -->
<div class="card">
    <div class="section-header"><h3>Step 1 — Select Exam</h3></div>
    <form method="GET">
        <div class="form-grid">
            <div class="form-group">
                <label>Exam <span style="color:var(--danger-color)">*</span></label>
                <select name="exam_id" required onchange="this.form.submit()">
                    <option value="">— Select Exam —</option>
                    <?php foreach ($my_exams as $ex): ?>
                        <option value="<?= $ex['exam_id'] ?>" <?= $exam_id == $ex['exam_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ex['exam_name']) ?> (<?= $ex['class'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($exam_id > 0 && !empty($my_subjects)): ?>
            <div class="form-group">
                <label>Subject <span style="color:var(--danger-color)">*</span></label>
                <select name="subject_id" required onchange="this.form.submit()">
                    <option value="">— Select Subject —</option>
                    <?php foreach ($my_subjects as $sub): ?>
                        <option value="<?= $sub['subject_id'] ?>" <?= $subject_id == $sub['subject_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sub['subject_name']) ?> (<?= htmlspecialchars($sub['subject_code']) ?>)
                            — <?= $sub['total_marks'] ?> marks
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php elseif ($exam_id > 0): ?>
            <div class="form-group">
                <label>&nbsp;</label>
                <p style="color:var(--danger-color);font-size:.875rem;margin-top:10px;">
                    No subjects assigned to you for this exam.
                </p>
            </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- STEP 2 — Marks Table -->
<?php if ($exam_id > 0 && $subject_id > 0 && $subject_info): ?>

    <?php if ($submitted && $hours_remaining <= 0): ?>
        <div class="submitted-banner">
            <strong>🔒 Marks Locked</strong><br>
            These marks have been submitted and locked for review. If you need to make changes, please contact the Examination Officer to unlock them.
        </div>
    <?php elseif ($hours_remaining > 0): ?>
        <div class="submitted-banner" style="background:#e0f2fe; border-color:#bae6fd; color:#0369a1;">
            <strong>⏳ Editing Window Active</strong><br>
            You have already submitted these marks, but you can still make changes for the next <strong><?= $hours_remaining ?> hours</strong> before they are permanently locked.
        </div>
    <?php endif; ?>

<?php if ($deadline): ?>
    <?php 
    $is_past = strtotime($deadline) < strtotime(date('Y-m-d'));
    $deadline_formatted = date('d M Y', strtotime($deadline));
    $is_locked = $is_past && ($override_lock === 0);
    ?>
    <?php if ($is_locked): ?>
        <div class="submitted-banner" style="background: #fee2e2; border-color: #fca5a5; color: #991b1b; margin-bottom: 16px;">
            <strong>LOCKED:</strong> The submission deadline was <?= $deadline_formatted ?>. This subject is locked. Contact the Headteacher to request an unlock.
        </div>
    <?php elseif ($is_past && $override_lock): ?>
        <div class="submitted-banner" style="background: #fef9c3; border-color: #fde047; color: #713f12; margin-bottom: 16px;">
            <strong>UNLOCKED OVERRIDE:</strong> The deadline was <?= $deadline_formatted ?>, but the Headteacher has granted you access to edit and submit.
        </div>
    <?php else: ?>
        <div class="submitted-banner" style="background: #ecfdf5; border-color: #a7f3d0; color: #065f46; margin-bottom: 16px;">
            <strong>Submission Deadline:</strong> <?= $deadline_formatted ?> — (Active)
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($rejection_reason): ?>
<div class="submitted-banner" style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;margin-bottom:16px;">
    Marks for this subject were <strong>rejected</strong>. <br>
    <strong>Rejection Reason/Feedback:</strong> <?= htmlspecialchars($rejection_reason) ?>
</div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
    <input type="hidden" name="subject_id" value="<?= $subject_id ?>">

    <div class="card">
        <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;">
            <h3><?= htmlspecialchars($subject_info['subject_name']) ?> — <?= htmlspecialchars($exam['exam_name'] ?? '') ?></h3>
            <span class="badge badge-info"><?= $subject_info['total_marks'] ?> marks total</span>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student Name</th>
                        <th>Exam No.</th>
                        <th>School</th>
                        <th>Class</th>
                        <th>Score (/<?= $subject_info['total_marks'] ?>)</th>
                        <th>Grade</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($students)): ?>
                    <tr><td colspan="8" class="empty-state">No students found for <?= htmlspecialchars($exam['class']) ?>.</td></tr>
                <?php else: ?>
                <?php $n = 1; foreach ($students as $st): ?>
                    <?php
                    $existing = $marks_map[$st['student_id']] ?? null;
                    $score_val = $existing ? $existing['score'] : '';
                    $grade_val = $existing ? $existing['grade'] : '';
                    $is_past = $deadline && strtotime($deadline) < strtotime(date('Y-m-d'));
                    $is_locked = $is_past && ($override_lock === 0);
                    $locked = $submitted || $is_locked;
                    ?>
                    <tr>
                        <td><?= $n++ ?></td>
                        <td><?= htmlspecialchars($st['name']) ?></td>
                        <td><?= htmlspecialchars($st['exam_number']) ?></td>
                        <td><?= htmlspecialchars($st['school_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($st['class'] ?? '—') ?></td>
                        <td>
                            <input type="number" class="mark-input"
                                   name="marks[<?= $st['student_id'] ?>]"
                                   min="0" max="<?= $subject_info['total_marks'] ?>"
                                   step="0.5"
                                   value="<?= $score_val ?>"
                                   <?= $locked ? 'disabled' : '' ?>
                                   oninput="autoGrade(this, <?= $subject_info['total_marks'] ?>, 'grade_<?= $st['student_id'] ?>')">
                        </td>
                        <td>
                            <span class="grade-badge" id="grade_<?= $st['student_id'] ?>"
                                  style="background:#f1f5f9;color:#334155;">
                                <?= $grade_val ?: '—' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($existing): ?>
                                <span class="badge badge-<?= $existing['status'] === 'submitted' || $existing['status'] === 'approved' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($existing['status']) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Not entered</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php 
        $is_past = $deadline && strtotime($deadline) < strtotime(date('Y-m-d'));
        $is_locked = $is_past && ($override_lock === 0);
        if (!$submitted && !$is_locked): 
        ?>
        <div class="form-actions" style="margin-top:16px;display:flex;gap:12px;align-items:center;">
            <button type="submit" name="form_action" value="save" class="btn btn-secondary">
                Save as Draft
            </button>
            <button type="submit" name="form_action" value="submit" class="btn btn-dark"
                    onclick="return confirm('Submit marks for this subject? They will be locked after submission.')">
                Submit Marks
            </button>
            <span style="font-size:.8rem;color:var(--text-muted);">
                Draft = editable. Submit = sends to Examination Officer for review.
            </span>
        </div>
        <?php elseif ($is_locked): ?>
        <div class="form-actions" style="margin-top:16px;display:flex;gap:12px;align-items:center;">
            <button type="button" class="btn btn-secondary" disabled style="opacity:0.5; cursor:not-allowed;">
                Save as Draft (Locked)
            </button>
            <button type="button" class="btn btn-dark" disabled style="opacity:0.5; cursor:not-allowed;">
                Submit Marks (Locked)
            </button>
            <span style="font-size:.8rem;color:var(--danger-color);font-weight:600;">
                Locked out because the submission deadline has passed.
            </span>
        </div>
        <?php endif; ?>
    </div>
</form>

<?php elseif ($exam_id > 0 && empty($my_subjects)): ?>
<div class="alert alert-error">You have no subjects assigned for this exam.</div>
<?php endif; ?>

</div><!-- .content -->
</div><!-- .dashboard -->

<script>
const gradeThresholds = [
    [80,'1','#dcfce7','#166534'],
    [70,'2','#dcfce7','#166534'],
    [60,'3','#d1fae5','#065f46'],
    [50,'4','#dbeafe','#1e40af'],
    [40,'5','#dbeafe','#1e40af'],
    [33,'6','#fef3c7','#92400e'],
    [25,'7','#fef3c7','#92400e'],
    [20,'8','#fee2e2','#991b1b'],
    [0, '9','#fee2e2','#991b1b'],
];

function autoGrade(input, total, badgeId) {
    const score = parseFloat(input.value);
    const badge = document.getElementById(badgeId);
    if (isNaN(score) || input.value === '') { badge.textContent = '—'; badge.style.background='#f1f5f9'; badge.style.color='#334155'; return; }
    const pct = (score / total) * 100;
    for (const [min, grade, bg, color] of gradeThresholds) {
        if (pct >= min) { badge.textContent = grade; badge.style.background = bg; badge.style.color = color; break; }
    }
}
// Init grades for existing values
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.mark-input').forEach(inp => {
        const parts = inp.name.match(/marks\[(\d+)\]/);
        if (parts && inp.value) {
            const max = parseInt(inp.max);
            autoGrade(inp, max, 'grade_' + parts[1]);
        }
    });
});
</script>

<?php include __DIR__ . '/../common/footer.php'; ?>
</body>
</html>