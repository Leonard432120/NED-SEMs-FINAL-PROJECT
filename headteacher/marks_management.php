<?php
/* ════════════════════════════════════════════════════════════════
   headteacher/marks_management.php
   HT: Tracks marking assignments, deadlines, lock overrides
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'headteacher') {
    header("Location: ../login.php"); exit();
}

$conn      = get_db_connection();
$school_id = (int)($_SESSION['school_id'] ?? 0);
$message   = ''; $msg_type = '';

/* ── POST ACTIONS ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = trim($_POST['action'] ?? '');
    
    if ($act === 'toggle_lock') {
        $assignment_id = (int)$_POST['assignment_id'];
        $new_override  = (int)$_POST['override_lock']; // 1 to unlock override, 0 to enforce deadline lock
        
        $stmt = $conn->prepare("UPDATE marking_assignments SET override_lock = ? WHERE assignment_id = ? AND school_id = ?");
        $stmt->bind_param("iii", $new_override, $assignment_id, $school_id);
        if ($stmt->execute()) {
            $message = $new_override ? 'Mark entry override unlocked for this teacher.' : 'Mark entry lock re-enforced.';
            $msg_type = 'success';
        } else {
            $message = 'Error updating lock state: ' . $conn->error;
            $msg_type = 'error';
        }
        $stmt->close();
    }
    
    if ($act === 'update_deadline') {
        $assignment_id = (int)$_POST['assignment_id'];
        $new_deadline  = !empty($_POST['deadline']) ? trim($_POST['deadline']) : null;
        
        $stmt = $conn->prepare("UPDATE marking_assignments SET deadline = ? WHERE assignment_id = ? AND school_id = ?");
        $stmt->bind_param("sii", $new_deadline, $assignment_id, $school_id);
        if ($stmt->execute()) {
            $message = 'Submission deadline updated successfully.';
            $msg_type = 'success';
        } else {
            $message = 'Error updating deadline: ' . $conn->error;
            $msg_type = 'error';
        }
        $stmt->close();
    }
    
    header("Location: marks_management.php?msg=" . urlencode($message) . "&mtype={$msg_type}");
    exit();
}

if (!empty($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
    $msg_type = $_GET['mtype'] ?? 'success';
}

/* ── FETCH MARKING ASSIGNMENTS ── */
$assignments_query = $conn->prepare("
    SELECT 
        ma.assignment_id,
        e.exam_name, e.class, e.exam_id,
        s.subject_name, s.subject_id, s.category,
        u.name AS marker_name,
        ma.deadline,
        ma.override_lock,
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
$assignments_query->execute();
$assignments = $assignments_query->get_result()->fetch_all(MYSQLI_ASSOC);
$assignments_query->close();

$conn->close();

$module_css = 'headteacher';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Marks Tracking & Management | NED-SEMS</title>
<style>
.lock-btn {
    padding: 6px 12px;
    border-radius: var(--border-radius);
    border: none;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
}
.btn-unlock {
    background-color: #10b981;
    color: white;
}
.btn-lock {
    background-color: #ef4444;
    color: white;
}
.deadline-input {
    padding: 6px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    font-size: 0.85rem;
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
        <h2 class="page-title">Marks Management</h2>
        <p class="page-subtitle">Track teacher mark entries, deadlines, and lock/unlock submission windows.</p>
    </div>
    <a href="assign_markers.php" class="btn btn-primary">Assign New Markers</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $msg_type ?>"><?= $message ?></div>
<?php endif; ?>

<div class="card" style="padding: 20px;">
    <h3 style="margin-top: 0; margin-bottom: 15px;">Active Marking Assignments & Deadlines</h3>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Exam & Subject</th>
                    <th>Marker</th>
                    <th>Progress</th>
                    <th>Deadline</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($assignments)): ?>
                    <?php foreach ($assignments as $as): ?>
                        <?php 
                        $total = (int)$as['total_students'];
                        $sub_cnt = (int)$as['submitted_count'];
                        $pct = $total > 0 ? round(($sub_cnt / $total) * 100) : 0;
                        
                        $is_past = $as['deadline'] && strtotime($as['deadline']) < strtotime(date('Y-m-d'));
                        
                        $status_label = 'Pending';
                        $status_class = 'warning';
                        $is_locked = false;
                        
                        if ($sub_cnt === 0) {
                            if ($is_past) {
                                if ($as['override_lock']) {
                                    $status_label = 'Unlocked (Late)';
                                    $status_class = 'info';
                                } else {
                                    $status_label = 'Locked (Overdue)';
                                    $status_class = 'danger';
                                    $is_locked = true;
                                }
                            } else {
                                $status_label = 'Not Started';
                                $status_class = 'secondary';
                            }
                        } elseif ($sub_cnt < $total) {
                            if ($is_past) {
                                if ($as['override_lock']) {
                                    $status_label = 'Unlocked (Late)';
                                    $status_class = 'info';
                                } else {
                                    $status_label = 'Locked (Overdue)';
                                    $status_class = 'danger';
                                    $is_locked = true;
                                }
                            } else {
                                $status_label = 'In Progress';
                                $status_class = 'info';
                            }
                        } else {
                            $status_label = 'Submitted';
                            $status_class = 'success';
                        }
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($as['subject_name']) ?></strong>
                                <div style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($as['exam_name']) ?> (<?= htmlspecialchars($as['class']) ?>)</div>
                            </td>
                            <td><?= htmlspecialchars($as['marker_name']) ?></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span style="font-size:0.8rem; font-weight:600; min-width:35px;"><?= $sub_cnt ?>/<?= $total ?></span>
                                    <div class="progress-bar" style="flex:1; margin:0; height:8px; background:#e2e8f0; border-radius:4px; overflow:hidden;">
                                        <div class="progress-fill" style="width: <?= $pct ?>%; height:100%; background:var(--primary-dark); transition: width 0.3s;"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <form method="POST" style="display:flex; gap:5px; align-items:center;">
                                    <input type="hidden" name="action" value="update_deadline">
                                    <input type="hidden" name="assignment_id" value="<?= $as['assignment_id'] ?>">
                                    <input type="date" name="deadline" value="<?= $as['deadline'] ?: '' ?>" class="deadline-input" style="width: 125px;">
                                    <button type="submit" class="btn btn-secondary btn-sm" style="padding: 5px 8px;">Save</button>
                                </form>
                            </td>
                            <td>
                                <span class="badge badge-<?= $status_class ?>"><?= $status_label ?></span>
                            </td>
                            <td>
                                <?php if ($is_past && $sub_cnt < $total): ?>
                                    <?php if ($as['override_lock']): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="toggle_lock">
                                            <input type="hidden" name="assignment_id" value="<?= $as['assignment_id'] ?>">
                                            <input type="hidden" name="override_lock" value="0">
                                            <button type="submit" class="lock-btn btn-lock" onclick="return confirm('Re-lock this assignment? Teacher will not be able to enter marks.')">
                                                🔒 Lock Access
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="toggle_lock">
                                            <input type="hidden" name="assignment_id" value="<?= $as['assignment_id'] ?>">
                                            <input type="hidden" name="override_lock" value="1">
                                            <button type="submit" class="lock-btn btn-unlock" onclick="return confirm('Unlock this assignment? Teacher will be allowed to submit marks.')">
                                                🔓 Grant Unlock
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="font-size:0.85rem; color:var(--text-muted);">Active / Closed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center" style="padding:20px;">
                            No markers assigned. <a href="assign_markers.php">Assign markers now</a>.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
</div>
<?php include '../common/footer.php'; ?>
</body>
</html>
