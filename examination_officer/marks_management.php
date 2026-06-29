<?php
/* ════════════════════════════════════════════════════════════════
   examination_officer/marks_management.php
   EO: reviews submitted marks, marks as received, forwards to HT
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once '../config/db.php';
require_once '../common/grade_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'examination_officer') {
    header("Location: ../login.php"); exit();
}

$conn      = get_db_connection();
$eo_id     = (int)$_SESSION['user_id'];
$school_id = (int)($_SESSION['school_id'] ?? 0);
$message   = ''; $msg_type = '';

if ($school_id <= 0) {
    $message = 'No school linked to your account. Contact administrator.';
    $msg_type = 'error';
}

/* ── FILTERS ── */
$exam_id    = (int)($_GET['exam_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);
$filter_status = trim($_GET['status'] ?? '');

/* ── POST ACTIONS ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $school_id > 0) {
    $act = trim($_POST['action'] ?? '');
    
    if ($act === 'toggle_lock') {
        $assignment_id = (int)($_POST['assignment_id'] ?? 0);
        if ($assignment_id > 0) {
            // fetch current lock status and teacher
            $res = $conn->query("SELECT teacher_id, override_lock, exam_id, subject_id FROM marking_assignments WHERE assignment_id = $assignment_id");
            $row = $res->fetch_assoc();
            if ($row) {
                $teacher_id = (int)$row['teacher_id'];
                $exam_id_val = (int)$row['exam_id'];
                $subj_id_val = (int)$row['subject_id'];
                $new_status = $row['override_lock'] ? 0 : 1;
                $upd = $conn->prepare("UPDATE marking_assignments SET override_lock = ? WHERE assignment_id = ?");
                $upd->bind_param('ii', $new_status, $assignment_id);
                $upd->execute();
                $upd->close();
                
                $status_text = $new_status ? 'unlocked' : 'locked';
                $message = $new_status ? 'Teacher unlocked for this exam.' : 'Teacher locked for this exam.';
                $msg_type = 'success';
                
                // Audit log
                $action_name = "toggle_marking_lock";
                $details = "Toggled marking lock for assignment ID {$assignment_id}: marker (Teacher ID {$teacher_id}) is now {$status_text} for Exam ID {$exam_id_val}, Subject ID {$subj_id_val}";
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '::1';
                $log = $conn->prepare("INSERT INTO audit_logs (user_id, target_user_id, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
                $log->bind_param("iisss", $eo_id, $teacher_id, $action_name, $details, $ip_address);
                $log->execute();
                $log->close();
            }
        }
    }

    /* Mark as received (per subject+exam batch) */
    if ($act === 'receive') {
        $eid = (int)$_POST['exam_id'];
        $sid = (int)$_POST['subject_id'];
        $now = date('Y-m-d H:i:s');
        $u = $conn->prepare("
            UPDATE marks m
            JOIN students st ON st.student_id = m.student_id
            SET m.submission_status = 'received', m.received_by = ?, m.received_at = ?
            WHERE m.exam_id = ? AND m.subject_id = ? AND st.school_id = ?
              AND m.status = 'submitted' AND m.submission_status = 'submitted'
        ");
        $u->bind_param("isiii", $eo_id, $now, $eid, $sid, $school_id);
        $u->execute(); $u->close();
        $message = 'Marks marked as received.'; $msg_type = 'success';
        
        // Audit log
        $action_name = "receive_marks";
        $details = "Received submitted marks for Exam ID {$eid}, Subject ID {$sid}";
        $target_user = null;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '::1';
        $log = $conn->prepare("INSERT INTO audit_logs (user_id, target_user_id, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $log->bind_param("iisss", $eo_id, $target_user, $action_name, $details, $ip_address);
        $log->execute();
        $log->close();
    }

    /* Forward to Headteacher */
    if ($act === 'forward') {
        $eid = (int)$_POST['exam_id'];
        $sid = (int)$_POST['subject_id'];
        $u = $conn->prepare("
            UPDATE marks m
            JOIN students st ON st.student_id = m.student_id
            SET m.submission_status = 'forwarded_to_edm'
            WHERE m.exam_id = ? AND m.subject_id = ? AND st.school_id = ?
              AND m.status = 'submitted'
        ");
        $u->bind_param("iii", $eid, $sid, $school_id);
        $u->execute(); $u->close();
        $message = 'Marks forwarded to Headteacher for approval.'; $msg_type = 'success';
        
        // Audit log
        $action_name = "forward_marks";
        $details = "Forwarded marks for Exam ID {$eid}, Subject ID {$sid} to Headteacher";
        $target_user = null;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '::1';
        $log = $conn->prepare("INSERT INTO audit_logs (user_id, target_user_id, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $log->bind_param("iisss", $eo_id, $target_user, $action_name, $details, $ip_address);
        $log->execute();
        $log->close();
    }

    /* Unlock ALL marks for subject (revert to draft so teacher can re-enter) */
    if ($act === 'unlock_all') {
        $eid = (int)$_POST['exam_id'];
        $sid = (int)$_POST['subject_id'];
        
        // Fetch teacher_id for audit logging first
        $t_stmt = $conn->prepare("SELECT DISTINCT teacher_id FROM marks WHERE exam_id=? AND subject_id=? LIMIT 1");
        $t_stmt->bind_param("ii", $eid, $sid);
        $t_stmt->execute();
        $t_res = $t_stmt->get_result()->fetch_assoc();
        $teacher_id = $t_res ? (int)$t_res['teacher_id'] : null;
        $t_stmt->close();
        
        $u = $conn->prepare("UPDATE marks m JOIN students st ON st.student_id = m.student_id SET m.status='draft', m.submission_status='submitted', m.submitted_at=NULL WHERE m.exam_id=? AND m.subject_id=? AND st.school_id=? AND m.status='submitted'");
        $u->bind_param("iii", $eid, $sid, $school_id);
        $u->execute(); $u->close();
        $message = 'All marks unlocked for teacher to edit.'; $msg_type = 'success';
        
        // Audit log
        $action_name = "unlock_all_marks";
        $details = "Unlocked all submitted marks for Exam ID {$eid}, Subject ID {$sid} (reverted to draft)";
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '::1';
        $log = $conn->prepare("INSERT INTO audit_logs (user_id, target_user_id, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $log->bind_param("iisss", $eo_id, $teacher_id, $action_name, $details, $ip_address);
        $log->execute();
        $log->close();
    }

    /* Unlock single mark (revert to draft so teacher can re-enter) */
    if ($act === 'unlock') {
        $mid = (int)$_POST['mark_id'];
        
        // Fetch details of mark for auditing
        $m_stmt = $conn->prepare("SELECT student_id, teacher_id, score, exam_id, subject_id FROM marks WHERE mark_id=?");
        $m_stmt->bind_param("i", $mid);
        $m_stmt->execute();
        $m_row = $m_stmt->get_result()->fetch_assoc();
        $m_stmt->close();
        
        $teacher_id = $m_row ? (int)$m_row['teacher_id'] : null;
        $st_id = $m_row ? (int)$m_row['student_id'] : null;
        
        $u = $conn->prepare("UPDATE marks SET status='draft', submission_status='submitted', submitted_at=NULL WHERE mark_id=? AND status='submitted'");
        $u->bind_param("i", $mid);
        $u->execute(); $u->close();
        $message = 'Mark entry unlocked for teacher to edit.'; $msg_type = 'success';
        
        // Audit log
        $action_name = "unlock_single_mark";
        $details = "Unlocked single mark entry ID {$mid} for Student ID {$st_id} (reverted to draft)";
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '::1';
        $log = $conn->prepare("INSERT INTO audit_logs (user_id, target_user_id, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $log->bind_param("iisss", $eo_id, $teacher_id, $action_name, $details, $ip_address);
        $log->execute();
        $log->close();
    }

    $redirect_tab = trim($_POST['tab'] ?? 'tracking');
    header("Location: marks_management.php?tab={$redirect_tab}&exam_id={$exam_id}&subject_id={$subject_id}&msg=" . urlencode($message) . "&mtype={$msg_type}");
    exit();
}

if (!empty($_GET['msg'])) { $message = htmlspecialchars($_GET['msg']); $msg_type = $_GET['mtype'] ?? 'success'; }

$tab = trim($_GET['tab'] ?? 'tracking');

/* ── FETCH MARKING ASSIGNMENTS (filtered by exam when selected) ── */
$assignments = [];
if ($school_id > 0) {
    if ($exam_id > 0) {
        $assignments_query = $conn->prepare("
            SELECT 
                ma.assignment_id,
                e.exam_name, e.class, e.exam_id,
                s.subject_name, s.subject_id, s.category,
                u.name AS marker_name,
                ma.deadline,
                ma.override_lock,
                ma.unlock_requested,
                ma.assigned_at,
                (
                    SELECT COUNT(*)
                    FROM students st
                    WHERE st.school_id = ma.school_id AND st.class = e.class AND st.status = 'active'
                ) AS total_students,
                (
                    SELECT COUNT(DISTINCT m.student_id)
                    FROM marks m
                    JOIN students st ON m.student_id = st.student_id
                    WHERE m.exam_id = ma.exam_id AND m.subject_id = ma.subject_id 
                      AND st.school_id = ma.school_id AND m.status IN ('submitted', 'approved')
                ) AS submitted_count
            FROM marking_assignments ma
            JOIN exams e ON ma.exam_id = e.exam_id
            JOIN subjects s ON ma.subject_id = s.subject_id
            JOIN users u ON ma.teacher_id = u.user_id
            WHERE ma.school_id = ? AND ma.exam_id = ?
            ORDER BY ma.deadline ASC, s.subject_name ASC
        ");
        $assignments_query->bind_param("ii", $school_id, $exam_id);
    } else {
        $assignments_query = $conn->prepare("
            SELECT 
                ma.assignment_id,
                e.exam_name, e.class, e.exam_id,
                s.subject_name, s.subject_id, s.category,
                u.name AS marker_name,
                ma.deadline,
                ma.override_lock,
                ma.unlock_requested,
                ma.assigned_at,
                (
                    SELECT COUNT(*)
                    FROM students st
                    WHERE st.school_id = ma.school_id AND st.class = e.class AND st.status = 'active'
                ) AS total_students,
                (
                    SELECT COUNT(DISTINCT m.student_id)
                    FROM marks m
                    JOIN students st ON m.student_id = st.student_id
                    WHERE m.exam_id = ma.exam_id AND m.subject_id = ma.subject_id 
                      AND st.school_id = ma.school_id AND m.status IN ('submitted', 'approved')
                ) AS submitted_count
            FROM marking_assignments ma
            JOIN exams e ON ma.exam_id = e.exam_id
            JOIN subjects s ON ma.subject_id = s.subject_id
            JOIN users u ON ma.teacher_id = u.user_id
            WHERE ma.school_id = ?
            ORDER BY ma.deadline ASC, e.exam_name ASC, s.subject_name ASC
        ");
        $assignments_query->bind_param("i", $school_id);
    }
    $assignments_query->execute();
    $assignments = $assignments_query->get_result()->fetch_all(MYSQLI_ASSOC);
    $assignments_query->close();
}

/* ── EXAMS with submitted marks for this school ── */
$exams_with_marks = $conn->query("
    SELECT DISTINCT e.exam_id, e.exam_name, e.class
    FROM exams e
    JOIN marks m ON m.exam_id = e.exam_id
    JOIN students st ON st.student_id = m.student_id
    WHERE st.school_id = {$school_id} AND m.status IN ('submitted','approved','rejected')
    ORDER BY e.exam_name
")->fetch_all(MYSQLI_ASSOC);

/* ── SUBJECTS in selected exam with submitted marks ── */
$subjects_with_marks = [];
if ($exam_id > 0) {
    $subjects_with_marks = $conn->query("
        SELECT DISTINCT sub.subject_id, sub.subject_name, sub.subject_code,
               COUNT(m.mark_id) AS mark_count,
               SUM(m.status='submitted') AS submitted_count,
               SUM(m.submission_status='received') AS received_count,
               SUM(m.submission_status='forwarded_to_edm') AS forwarded_count,
               MAX(m.submitted_at) AS last_submitted,
               u.name AS teacher_name
        FROM marks m
        JOIN students st ON st.student_id = m.student_id
        JOIN subjects sub ON sub.subject_id = m.subject_id
        LEFT JOIN users u ON u.user_id = m.teacher_id
        WHERE m.exam_id = {$exam_id} AND st.school_id = {$school_id}
          AND m.status IN ('submitted','approved','rejected')
        GROUP BY sub.subject_id, sub.subject_name, sub.subject_code, u.name
        ORDER BY sub.subject_name
    ")->fetch_all(MYSQLI_ASSOC);
}

/* ── MARKS DETAIL for selected subject ── */
$marks_detail = [];
if ($exam_id > 0 && $subject_id > 0) {
    $marks_detail = $conn->query("
        SELECT m.mark_id, m.score, m.grade, m.status, m.submission_status,
               m.submitted_at, m.received_at,
               st.name AS student_name, st.exam_number, st.class,
               sc.school_name, u.name AS teacher_name
        FROM marks m
        JOIN students st ON st.student_id = m.student_id
        LEFT JOIN schools sc ON sc.school_id = st.school_id
        LEFT JOIN users u ON u.user_id = m.teacher_id
        WHERE m.exam_id = {$exam_id} AND m.subject_id = {$subject_id}
          AND st.school_id = {$school_id}
        ORDER BY st.name
    ")->fetch_all(MYSQLI_ASSOC);
}

$all_exams = $conn->query("SELECT exam_id, exam_name, class, status, start_date FROM exams ORDER BY start_date DESC")->fetch_all(MYSQLI_ASSOC);
$active_exam_name = '';
foreach ($all_exams as $ex) {
    if ($ex['exam_id'] == $exam_id) {
        $active_exam_name = $ex['exam_name'];
        break;
    }
}
$conn->close();

/* ── KPIs ── */
$kpi_submitted  = count(array_filter($marks_detail, fn($r) => $r['submission_status'] === 'submitted'));
$kpi_received   = count(array_filter($marks_detail, fn($r) => $r['submission_status'] === 'received'));
$kpi_forwarded  = count(array_filter($marks_detail, fn($r) => $r['submission_status'] === 'forwarded_to_edm'));
$can_receive    = $kpi_submitted > 0;
$can_forward    = ($kpi_received + $kpi_forwarded) > 0 && $kpi_submitted === 0;

$module_css = 'exam_officer';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Marks Management | NED-SEMS</title>
</head>
<body>
<?php include '../common/header.php'; ?>
<div class="dashboard">
<?php include '../common/sidebar.php'; ?>
<div class="content">

<div class="page-header">
    <div>
        <h2 class="page-title">Marks Management</h2>
        <p class="page-subtitle">Review, receive and forward teacher-submitted marks</p>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $msg_type ?>"><?= $message ?></div>
<?php endif; ?>

<!-- Tab switcher and panels conditional container -->
<?php if ($exam_id === 0): ?>
    <!-- Landing Page: Select Exam -->
    <div style="max-width:1000px; margin: 30px auto;">
        <div class="card" style="padding:32px 40px; border-radius:16px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); background: #ffffff; border: 1px solid rgba(226, 232, 240, 0.8);">
            <div style="margin-bottom: 24px; border-bottom: 1px solid #f1f5f9; padding-bottom: 20px;">
                <h2 style="font-size:1.8rem; font-weight:700; color:#0f172a; margin: 0 0 8px 0; font-family: 'Outfit', sans-serif;">Select Examination</h2>
                <p style="color:#64748b; margin: 0; font-size:0.95rem; line-height:1.5;">
                    Select an examination from the registry below to track submission progress, check marker assignments, and manage approvals.
                </p>
            </div>
            
            <?php if (empty($all_exams)): ?>
                <div class="empty-state" style="padding: 40px; border: 2px dashed #cbd5e1; border-radius: 12px; background: #f8fafc; text-align: center;">
                    <p style="color: #64748b; font-size: 0.95rem; margin: 0;">No active examinations found in the system.</p>
                </div>
            <?php else: ?>
                <div class="table-container" style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: none;">
                    <table>
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th style="padding: 16px 20px; font-weight: 600; color: #475569; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;">Examination Name</th>
                                <th style="padding: 16px 20px; font-weight: 600; color: #475569; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;">Class / Level</th>
                                <th style="padding: 16px 20px; font-weight: 600; color: #475569; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;">Start Date</th>
                                <th style="padding: 16px 20px; font-weight: 600; color: #475569; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;">Status</th>
                                <th style="padding: 16px 20px; font-weight: 600; color: #475569; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_exams as $ex): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                    <td style="padding: 18px 20px; font-weight: 600; color: #1e293b; font-size: 0.95rem;"><?= htmlspecialchars($ex['exam_name']) ?></td>
                                    <td style="padding: 18px 20px; color: #64748b; font-size: 0.9rem;"><?= htmlspecialchars($ex['class'] ?? 'All Classes') ?></td>
                                    <td style="padding: 18px 20px; color: #64748b; font-size: 0.9rem;"><?= $ex['start_date'] ? date('d M Y', strtotime($ex['start_date'])) : 'Not set' ?></td>
                                    <td style="padding: 18px 20px;">
                                        <span class="badge badge-<?= $ex['status'] === 'active' ? 'success' : ($ex['status'] === 'draft' ? 'admin' : 'warning') ?>" style="font-size:0.75rem; font-weight:600; padding:4px 8px; border-radius: 6px;">
                                            <?= ucfirst(htmlspecialchars($ex['status'])) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 18px 20px; text-align: right;">
                                        <a href="marks_management.php?exam_id=<?= $ex['exam_id'] ?>&tab=tracking" class="btn btn-primary" style="display:inline-block; text-decoration:none; padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 0.85rem;">
                                            Manage Marks
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <!-- Active Exam View: Tabs Inside Card -->
    <div class="card" style="padding: 0; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); overflow: hidden; margin-top: 20px; border: 1px solid rgba(226, 232, 240, 0.8);">
        
        <!-- Card Header with Tabs and Context info -->
        <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 0 24px; flex-wrap: wrap; gap: 16px;">
            <div style="display: flex; gap: 8px;">
                <button type="button" id="tab-tracking" onclick="switchTab('tracking')"
                    style="padding: 20px 24px; border: none; background: none; font-size: 0.95rem; font-weight: 600; cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.2s; color: var(--text-muted); font-family: inherit;">
                    Marks Tracking &amp; Deadlines
                </button>
                <button type="button" id="tab-approvals" onclick="switchTab('approvals')"
                    style="padding: 20px 24px; border: none; background: none; font-size: 0.95rem; font-weight: 600; cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.2s; color: var(--text-muted); font-family: inherit;">
                    Review &amp; Approvals
                </button>
            </div>
            
            <div style="display: flex; align-items: center; gap: 12px; padding: 12px 0;">
                <span style="font-size: 0.85rem; color: var(--text-muted);">
                    Exam: <strong style="color: var(--text-color);"><?= htmlspecialchars($active_exam_name) ?></strong>
                </span>
                <a href="marks_management.php" class="btn btn-secondary btn-small" style="font-size: 0.75rem; padding: 6px 12px; border-radius: 6px;">
                    Change Exam
                </a>
            </div>
        </div>
        
        <!-- Card Body containing Panels -->
        <div style="padding: 24px;">
            
            <!-- PANEL 1: MARKS TRACKING -->
            <div id="panel-tracking">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <h3 style="margin:0; font-size:1.2rem; font-weight:700; color:#0f172a;">Active Marking Assignments &amp; Progress</h3>
                </div>

                <div class="table-container" style="border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: none;">
                    <table>
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Marker</th>
                                <th>Progress</th>
                                <th>Deadline</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($assignments)): ?>
                                <?php foreach ($assignments as $as): ?>
                                    <?php
                                    $total   = (int)$as['total_students'];
                                    $sub_cnt = (int)$as['submitted_count'];
                                    $pct     = $total > 0 ? round(($sub_cnt / $total) * 100) : 0;
                                    $is_past = $as['deadline'] && strtotime($as['deadline']) < strtotime(date('Y-m-d'));

                                    $status_label = 'Pending';
                                    $status_class = 'warning';
                                    if ($sub_cnt === 0) {
                                        if ($is_past) {
                                            $status_label = $as['override_lock'] ? 'Unlocked (Late)' : 'Locked (Overdue)';
                                            $status_class = $as['override_lock'] ? 'info' : 'danger';
                                        } else {
                                            $status_label = 'Not Started';
                                            $status_class = 'admin';
                                        }
                                    } elseif ($sub_cnt < $total) {
                                        if ($is_past) {
                                            $status_label = $as['override_lock'] ? 'Unlocked (Late)' : 'Locked (Overdue)';
                                            $status_class = $as['override_lock'] ? 'info' : 'danger';
                                        } else {
                                            $status_label = 'In Progress';
                                            $status_class = 'info';
                                        }
                                    } else {
                                        $status_label = 'Submitted';
                                        $status_class = 'success';
                                    }

                                    $deadline_str = $as['deadline'] ? date('d M Y', strtotime($as['deadline'])) : 'No Deadline';
                                    $deadline_cls = ($is_past && $sub_cnt < $total) ? 'color:#b91c1c; font-weight:700;' : '';
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($as['subject_name']) ?></strong>
                                            <?php if (!empty($as['category'])): ?>
                                                <div style="font-size:0.72rem; color:var(--text-muted); margin-top:2px;"><?= htmlspecialchars($as['category']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($as['marker_name']) ?></td>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:8px; min-width:120px;">
                                                <span style="font-size:0.8rem; font-weight:700; min-width:38px;"><?= $sub_cnt ?>/<?= $total ?></span>
                                                <div class="progress-bar" style="flex:1; margin:0; height:7px;">
                                                    <div class="progress-fill" style="width:<?= $pct ?>%;"></div>
                                                </div>
                                                <span style="font-size:0.72rem; color:var(--text-muted); min-width:30px;"><?= $pct ?>%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="<?= $deadline_cls ?>"><?= $deadline_str ?></span>
                                            <?php if ($is_past && $sub_cnt < $total): ?>
                                                <div style="font-size:0.72rem; color:#b91c1c; margin-top:2px;">Overdue</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $status_class ?>"><?= $status_label ?></span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="tab" value="tracking">
                                                    <input type="hidden" name="action" value="toggle_lock">
                                                    <input type="hidden" name="assignment_id" value="<?= $as['assignment_id'] ?>">
                                                    <?php if ($as['override_lock']): ?>
                                                        <button type="submit" class="btn btn-small btn-delete">Lock</button>
                                                    <?php else: ?>
                                                        <button type="submit" class="btn btn-small btn-success">Unlock</button>
                                                    <?php endif; ?>
                                                </form>
                                                <?php if ($sub_cnt > 0): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="tab" value="tracking">
                                                        <input type="hidden" name="action" value="unlock_all">
                                                        <input type="hidden" name="exam_id" value="<?= $as['exam_id'] ?>">
                                                        <input type="hidden" name="subject_id" value="<?= $as['subject_id'] ?>">
                                                        <button type="submit" class="btn btn-small btn-edit"
                                                            onclick="return confirm('Unlock all submitted marks for <?= htmlspecialchars(addslashes($as['marker_name'])) ?> to edit again?')">
                                                            Reset
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">
                                        No marking assignments found for this exam.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- PANEL 2: REVIEW & APPROVALS -->
            <div id="panel-approvals">
                <div style="display:grid; grid-template-columns:280px 1fr; gap:20px; align-items:start;">
                    
                    <!-- Left: Subjects List -->
                    <div>
                        <div class="card" style="padding:16px; border:1px solid #e2e8f0; border-radius:10px; background:#fff; box-shadow:none;">
                            <div class="section-header" style="margin-bottom:16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                                <h3 style="margin:0; font-size:1rem; font-weight:700; color:#0f172a;">Exam Subjects</h3>
                            </div>
                            <?php if (empty($subjects_with_marks)): ?>
                                <p class="empty-state" style="font-size:.8rem; padding:20px; color:var(--text-muted); text-align:center;">No submitted marks yet.</p>
                            <?php else: ?>
                                <div style="display:flex; flex-direction:column; gap:8px;">
                                    <?php foreach ($subjects_with_marks as $sub): ?>
                                        <a href="marks_management.php?tab=approvals&exam_id=<?= $exam_id ?>&subject_id=<?= $sub['subject_id'] ?>"
                                           class="subject-card <?= $subject_id == $sub['subject_id'] ? 'active' : '' ?>"
                                           style="text-decoration:none; color:inherit; display:flex; justify-content:space-between; align-items:center; padding:12px; border-radius:8px; border:1px solid <?= $subject_id == $sub['subject_id'] ? 'var(--primary-color)' : '#e2e8f0' ?>; background:<?= $subject_id == $sub['subject_id'] ? 'rgba(var(--primary-rgb), 0.05)' : '#fff' ?>; transition: all 0.2s;">
                                            <div>
                                                <div style="font-weight:600; font-size:.875rem; color:#1e293b;"><?= htmlspecialchars($sub['subject_name']) ?></div>
                                                <div style="font-size:.75rem; color:var(--text-muted); margin-top:2px;"><?= $sub['mark_count'] ?> entries</div>
                                            </div>
                                            <?php
                                            $all_fwd = ($sub['forwarded_count'] == $sub['mark_count']);
                                            $all_recv = ($sub['received_count'] == $sub['mark_count']);
                                            $badge = $all_fwd ? 'success' : ($all_recv ? 'info' : 'warning');
                                            $label = $all_fwd ? 'Forwarded' : ($all_recv ? 'Received' : 'Pending');
                                            ?>
                                            <span class="badge badge-<?= $badge ?>"><?= $label ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Right: Marks Details -->
                    <div>
                        <?php if ($subject_id > 0 && !empty($marks_detail)): ?>

                            <!-- Pipeline Status -->
                            <div class="pipeline" style="margin-bottom: 20px;">
                                <div class="pipe-step done">1. Teacher Submitted</div>
                                <div class="pipe-step <?= $kpi_received > 0 ? 'done' : ($can_receive ? 'active' : '') ?>">2. EO Received</div>
                                <div class="pipe-step <?= $kpi_forwarded > 0 ? 'done' : ($can_forward ? 'active' : '') ?>">3. Forwarded to HT</div>
                                <div class="pipe-step">4. HT Approval</div>
                                <div class="pipe-step">5. EDM Compilation</div>
                            </div>

                            <!-- KPIs -->
                            <div class="kpi-grid" style="grid-template-columns:repeat(3,1fr); margin-bottom:20px; gap:16px;">
                                <div class="kpi-card kpi-card--warning" style="padding:16px; border-radius:10px; box-shadow:none; border:1px solid #e2e8f0;">
                                    <div class="kpi-card__body">
                                        <span class="kpi-card__label" style="font-size:0.8rem; text-transform:uppercase; color:var(--text-muted);">Pending Receipt</span>
                                        <span class="kpi-card__value" style="font-size:1.6rem; font-weight:700; color:#d97706; display:block; margin-top:4px;"><?= $kpi_submitted ?></span>
                                    </div>
                                </div>
                                <div class="kpi-card kpi-card--info" style="padding:16px; border-radius:10px; box-shadow:none; border:1px solid #e2e8f0;">
                                    <div class="kpi-card__body">
                                        <span class="kpi-card__label" style="font-size:0.8rem; text-transform:uppercase; color:var(--text-muted);">Received</span>
                                        <span class="kpi-card__value" style="font-size:1.6rem; font-weight:700; color:#2563eb; display:block; margin-top:4px;"><?= $kpi_received ?></span>
                                    </div>
                                </div>
                                <div class="kpi-card kpi-card--success" style="padding:16px; border-radius:10px; box-shadow:none; border:1px solid #e2e8f0;">
                                    <div class="kpi-card__body">
                                        <span class="kpi-card__label" style="font-size:0.8rem; text-transform:uppercase; color:var(--text-muted);">Forwarded to HT</span>
                                        <span class="kpi-card__value" style="font-size:1.6rem; font-weight:700; color:#16a34a; display:block; margin-top:4px;"><?= $kpi_forwarded ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Batch Actions -->
                            <div class="action-buttons" style="margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap;">
                                <?php if ($can_receive): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="tab" value="approvals">
                                        <input type="hidden" name="action" value="receive">
                                        <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
                                        <input type="hidden" name="subject_id" value="<?= $subject_id ?>">
                                        <button class="btn btn-dark" onclick="return confirm('Mark all submitted marks as received?')">
                                            Mark All as Received
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($can_forward || $kpi_received > 0): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="tab" value="approvals">
                                        <input type="hidden" name="action" value="forward">
                                        <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
                                        <input type="hidden" name="subject_id" value="<?= $subject_id ?>">
                                        <button class="btn btn-edit" onclick="return confirm('Forward these marks to Headteacher for approval?')">
                                            Forward to Headteacher
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($kpi_submitted > 0 || $kpi_received > 0): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="tab" value="approvals">
                                        <input type="hidden" name="action" value="unlock_all">
                                        <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
                                        <input type="hidden" name="subject_id" value="<?= $subject_id ?>">
                                        <button class="btn btn-secondary" onclick="return confirm('Unlock ALL these marks for the teacher to edit again?')">
                                            Unlock All for Editing
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <!-- Detailed Marks Table -->
                            <div class="card" style="border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: none; padding: 0;">
                                <div class="table-container">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Student</th>
                                                <th>Exam No.</th>
                                                <th>Class</th>
                                                <th>Score</th>
                                                <th>Grade</th>
                                                <th>Teacher</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $n=1; foreach ($marks_detail as $m): ?>
                                                <tr>
                                                    <td><?= $n++ ?></td>
                                                    <td><?= htmlspecialchars($m['student_name']) ?></td>
                                                    <td><?= htmlspecialchars($m['exam_number']) ?></td>
                                                    <td><?= htmlspecialchars($m['class']) ?></td>
                                                    <td><strong><?= $m['score'] ?></strong></td>
                                                    <td>
                                                        <span class="badge badge-<?= gradeColor($m['grade'] ?? '9') ?>">
                                                            Grade <?= $m['grade'] ?? '—' ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($m['teacher_name'] ?? '—') ?></td>
                                                    <td>
                                                        <span class="badge badge-<?= $m['submission_status'] === 'forwarded_to_edm' ? 'success' : ($m['submission_status'] === 'received' ? 'info' : 'warning') ?>">
                                                            <?= str_replace(['submitted','received','forwarded_to_edm'],['Submitted','Received','Forwarded'], $m['submission_status']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($m['status'] === 'submitted' && $m['submission_status'] === 'submitted'): ?>
                                                            <form method="POST" style="display:inline;">
                                                                <input type="hidden" name="tab" value="approvals">
                                                                <input type="hidden" name="action" value="unlock">
                                                                <input type="hidden" name="mark_id" value="<?= $m['mark_id'] ?>">
                                                                <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
                                                                <input type="hidden" name="subject_id" value="<?= $subject_id ?>">
                                                                <button class="btn btn-secondary btn-sm" onclick="return confirm('Unlock this mark for the teacher to re-enter?')">Unlock</button>
                                                            </form>
                                                        <?php else: ?>&mdash;<?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        <?php else: ?>
                            <div class="card" style="text-align:center; padding:40px; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: none;">
                                <p class="empty-state" style="color:var(--text-muted); margin:0;">Select a subject from the left panel to review marks.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
<?php endif; ?>

</div><!-- content -->
</div><!-- dashboard -->

<script>
function switchTab(targetTab) {
    var panelTracking  = document.getElementById('panel-tracking');
    var panelApprovals = document.getElementById('panel-approvals');
    var btnTracking    = document.getElementById('tab-tracking');
    var btnApprovals   = document.getElementById('tab-approvals');

    if (!panelTracking || !panelApprovals || !btnTracking || !btnApprovals) return;

    if (targetTab === 'tracking') {
        panelTracking.style.display  = '';
        panelApprovals.style.display = 'none';
        
        btnTracking.style.fontWeight   = '700';
        btnTracking.style.color        = 'var(--primary-dark)';
        btnTracking.style.borderBottom = '3px solid var(--primary-dark)';
        
        btnApprovals.style.fontWeight   = '600';
        btnApprovals.style.color        = 'var(--text-muted)';
        btnApprovals.style.borderBottom = '3px solid transparent';
    } else {
        panelTracking.style.display  = 'none';
        panelApprovals.style.display = '';
        
        btnApprovals.style.fontWeight  = '700';
        btnApprovals.style.color       = 'var(--primary-dark)';
        btnApprovals.style.borderBottom = '3px solid var(--primary-dark)';
        
        btnTracking.style.fontWeight    = '600';
        btnTracking.style.color         = 'var(--text-muted)';
        btnTracking.style.borderBottom  = '3px solid transparent';
    }
}

document.addEventListener("DOMContentLoaded", function() {
    switchTab("<?= $tab ?>");
});
</script>

<?php include '../common/footer.php'; ?>
