<?php
/* ════════════════════════════════════════════════════════════════
   headteacher/marks_reception.php
   HT: Approves or rejects forwarded marks, then sends to EDM
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once '../config/db.php';
require_once '../common/grade_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'headteacher') {
    header("Location: ../login.php"); exit();
}

$conn      = get_db_connection();
$ht_id     = (int)$_SESSION['user_id'];
$school_id = (int)($_SESSION['school_id'] ?? 0);
$message   = ''; $msg_type = '';

$exam_id    = (int)($_GET['exam_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);

/* ── POST ACTIONS ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = trim($_POST['action'] ?? '');
    $eid = (int)$_POST['exam_id'];
    $sid = (int)$_POST['subject_id'];

    if ($act === 'approve') {
        $u = $conn->prepare("
            UPDATE marks m
            JOIN students st ON st.student_id = m.student_id
            SET m.status = 'approved'
            WHERE m.exam_id = ? AND m.subject_id = ? AND st.school_id = ?
              AND m.submission_status = 'forwarded_to_edm'
        ");
        $u->bind_param("iii", $eid, $sid, $school_id);
        $u->execute(); $u->close();
        $message = 'Marks approved and ready for EDM compilation.'; $msg_type = 'success';
    }

    if ($act === 'reject') {
        $notes = trim($_POST['notes'] ?? '');
        $u = $conn->prepare("
            UPDATE marks m
            JOIN students st ON st.student_id = m.student_id
            SET m.status = 'rejected', m.notes = ?
            WHERE m.exam_id = ? AND m.subject_id = ? AND st.school_id = ?
        ");
        $u->bind_param("siii", $notes, $eid, $sid, $school_id);
        $u->execute(); $u->close();
        $message = 'Marks rejected. Teacher will need to resubmit.'; $msg_type = 'error';
    }

    header("Location: marks_reception.php?exam_id={$eid}&subject_id={$sid}&msg=" . urlencode($message) . "&mtype={$msg_type}");
    exit();
}

if (!empty($_GET['msg'])) { $message = htmlspecialchars($_GET['msg']); $msg_type = $_GET['mtype'] ?? 'success'; }

/* ── EXAMS with forwarded marks ── */
$exams_list = $conn->query("
    SELECT DISTINCT e.exam_id, e.exam_name, e.class
    FROM exams e
    JOIN marks m ON m.exam_id = e.exam_id
    JOIN students st ON st.student_id = m.student_id
    WHERE st.school_id = {$school_id} AND m.submission_status = 'forwarded_to_edm'
    ORDER BY e.exam_name
")->fetch_all(MYSQLI_ASSOC);

/* ── SUBJECTS per exam ── */
$subjects_list = [];
if ($exam_id > 0) {
    $subjects_list = $conn->query("
        SELECT sub.subject_id, sub.subject_name, sub.subject_code,
               COUNT(m.mark_id) AS total,
               SUM(m.status='approved') AS approved_cnt,
               SUM(m.status='rejected') AS rejected_cnt,
               SUM(m.status='submitted') AS pending_cnt,
               u.name AS teacher_name
        FROM marks m
        JOIN students st ON st.student_id = m.student_id
        JOIN subjects sub ON sub.subject_id = m.subject_id
        LEFT JOIN users u ON u.user_id = m.teacher_id
        WHERE m.exam_id = {$exam_id} AND st.school_id = {$school_id}
          AND m.submission_status = 'forwarded_to_edm'
        GROUP BY sub.subject_id, sub.subject_name, sub.subject_code, u.name
        ORDER BY sub.subject_name
    ")->fetch_all(MYSQLI_ASSOC);
}

/* ── MARKS DETAIL ── */
$marks_detail = [];
if ($exam_id > 0 && $subject_id > 0) {
    $marks_detail = $conn->query("
        SELECT m.mark_id, m.score, m.grade, m.status, m.notes,
               m.submitted_at, m.received_at,
               st.name AS student_name, st.exam_number, st.class,
               u.name AS teacher_name
        FROM marks m
        JOIN students st ON st.student_id = m.student_id
        LEFT JOIN users u ON u.user_id = m.teacher_id
        WHERE m.exam_id = {$exam_id} AND m.subject_id = {$subject_id}
          AND st.school_id = {$school_id}
          AND m.submission_status = 'forwarded_to_edm'
        ORDER BY st.name
    ")->fetch_all(MYSQLI_ASSOC);
}

$all_exams = $conn->query("SELECT exam_id, exam_name FROM exams ORDER BY exam_name")->fetch_all(MYSQLI_ASSOC);

$approved_all = !empty($marks_detail) && count(array_filter($marks_detail, fn($r) => $r['status'] !== 'approved')) === 0;
$pending_count = count(array_filter($marks_detail, fn($r) => $r['status'] === 'submitted'));

$conn->close();
$module_css = 'headteacher';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Marks Reception | NED-SEMS</title>
<style>
.subject-card{border:1px solid var(--border-color);border-radius:var(--border-radius);
              padding:14px 18px;margin-bottom:10px;
              display:flex;align-items:center;justify-content:space-between;
              text-decoration:none;color:inherit;transition:background .15s;}
.subject-card:hover,.subject-card.active{background:#f8fafc;}
.subject-card.active{border-color:var(--primary-dark);}
</style>
</head>
<body>
<?php include '../common/header.php'; ?>
<div class="dashboard">
<?php include '../common/sidebar.php'; ?>
<div class="content">

<div class="page-header">
    <div>
        <h2 class="page-title">Marks Reception</h2>
        <p class="page-subtitle">Review and approve marks forwarded by Examination Officer</p>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $msg_type ?>"><?= $message ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:280px 1fr;gap:20px;align-items:start;">

    <!-- LEFT -->
    <div>
        <div class="card" style="padding:16px;">
            <div class="section-header"><h3>Select Exam</h3></div>
            <form method="GET" id="ef">
                <div class="form-group">
                    <select name="exam_id" onchange="document.getElementById('ef').submit()" style="width:100%;">
                        <option value="">— Select Exam —</option>
                        <?php foreach ($all_exams as $ex): ?>
                            <option value="<?= $ex['exam_id'] ?>" <?= $exam_id == $ex['exam_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ex['exam_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <?php if ($exam_id > 0 && !empty($subjects_list)): ?>
            <div class="section-header" style="margin-top:16px;"><h3>Subjects</h3></div>
            <?php foreach ($subjects_list as $sub): ?>
                <a href="marks_reception.php?exam_id=<?= $exam_id ?>&subject_id=<?= $sub['subject_id'] ?>"
                   class="subject-card <?= $subject_id == $sub['subject_id'] ? 'active' : '' ?>">
                    <div>
                        <div style="font-weight:600;font-size:.875rem;"><?= htmlspecialchars($sub['subject_name']) ?></div>
                        <div style="font-size:.75rem;color:var(--text-muted);">By: <?= htmlspecialchars($sub['teacher_name'] ?? '—') ?></div>
                    </div>
                    <?php
                    $badge = $sub['approved_cnt'] == $sub['total'] ? 'success' :
                             ($sub['rejected_cnt'] > 0 ? 'danger' : 'warning');
                    $label = $sub['approved_cnt'] == $sub['total'] ? 'Approved' :
                             ($sub['rejected_cnt'] > 0 ? 'Rejected' : 'Pending');
                    ?>
                    <span class="badge badge-<?= $badge ?>"><?= $label ?></span>
                </a>
            <?php endforeach; ?>
            <?php elseif ($exam_id > 0): ?>
            <p class="empty-state" style="font-size:.8rem;">No forwarded marks for this exam.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT -->
    <div>
        <?php if (!empty($marks_detail)): ?>

        <!-- KPIs -->
        <div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px;">
            <div class="kpi-card kpi-card--warning">
                <div class="kpi-card__body">
                    <span class="kpi-card__label">Pending Review</span>
                    <span class="kpi-card__value"><?= $pending_count ?></span>
                </div>
            </div>
            <div class="kpi-card kpi-card--success">
                <div class="kpi-card__body">
                    <span class="kpi-card__label">Approved</span>
                    <span class="kpi-card__value"><?= count(array_filter($marks_detail, fn($r) => $r['status'] === 'approved')) ?></span>
                </div>
            </div>
            <div class="kpi-card kpi-card--danger">
                <div class="kpi-card__body">
                    <span class="kpi-card__label">Rejected</span>
                    <span class="kpi-card__value"><?= count(array_filter($marks_detail, fn($r) => $r['status'] === 'rejected')) ?></span>
                </div>
            </div>
        </div>

        <!-- Batch approve/reject -->
        <?php if (!$approved_all): ?>
        <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
                <input type="hidden" name="subject_id" value="<?= $subject_id ?>">
                <button class="btn btn-dark" onclick="return confirm('Approve all marks for this subject? They will be sent to EDM for compilation.')">
                    Approve All Marks
                </button>
            </form>
            <button class="btn btn-secondary" onclick="document.getElementById('rejectForm').style.display='block'">
                Reject with Reason
            </button>
        </div>
        <div id="rejectForm" style="display:none;" class="card" style="padding:16px;">
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
                <input type="hidden" name="subject_id" value="<?= $subject_id ?>">
                <div class="form-group">
                    <label>Reason for Rejection <span style="color:var(--danger-color)">*</span></label>
                    <textarea name="notes" rows="3" required placeholder="Explain why marks are being rejected…" style="width:100%;padding:10px;border:1px solid var(--border-color);border-radius:var(--border-radius);resize:vertical;"></textarea>
                </div>
                <button type="submit" class="btn btn-secondary">Submit Rejection</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('rejectForm').style.display='none'">Cancel</button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Marks table -->
        <div class="card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student Name</th>
                            <th>Exam No.</th>
                            <th>Class</th>
                            <th>Score</th>
                            <th>Grade</th>
                            <th>Teacher</th>
                            <th>Status</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $n=1; foreach ($marks_detail as $m): ?>
                        <tr>
                            <td><?= $n++ ?></td>
                            <td><?= htmlspecialchars($m['student_name']) ?></td>
                            <td><?= htmlspecialchars($m['exam_number']) ?></td>
                            <td><?= htmlspecialchars($m['class']) ?></td>
                            <td><strong><?= number_format((float)$m['score'], 1) ?></strong></td>
                            <td><span class="badge badge-<?= gradeColor($m['grade'] ?? '9') ?>">Grade <?= $m['grade'] ?? '—' ?></span></td>
                            <td><?= htmlspecialchars($m['teacher_name'] ?? '—') ?></td>
                            <td>
                                <span class="badge badge-<?= $m['status'] === 'approved' ? 'success' : ($m['status'] === 'rejected' ? 'danger' : 'warning') ?>">
                                    <?= ucfirst($m['status']) ?>
                                </span>
                            </td>
                            <td style="font-size:.78rem;color:var(--text-muted);"><?= htmlspecialchars($m['notes'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($exam_id > 0): ?>
        <div class="card" style="text-align:center;padding:40px;">
            <p class="empty-state">Select a subject to review marks.</p>
        </div>
        <?php else: ?>
        <div class="card" style="text-align:center;padding:40px;">
            <p class="empty-state">Select an exam from the left panel to begin.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>
</div>
<?php include '../common/footer.php'; ?>
</body>
</html>