<?php
/* ════════════════════════════════════════════════════════════════
   headteacher/assign_markers.php
   HT: Assigns markers (teachers) to exam subjects & sets deadlines.
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'headteacher') {
    header("Location: ../login.php"); exit();
}

$conn      = get_db_connection();
$ht_id     = (int)$_SESSION['user_id'];
$school_id = (int)($_SESSION['school_id'] ?? 0);
$message   = '';
$msg_type  = '';

$exam_id = (int)($_GET['exam_id'] ?? 0);

/* Helper function to check teacher eligibility for a subject */
function get_teacher_eligibility_reason($teacher, $subject) {
    $major = strtolower(trim($teacher['major_subject'] ?? ''));
    $minor = strtolower(trim($teacher['minor_subject'] ?? ''));
    $t_cat = strtolower(trim($teacher['teacher_category'] ?? ''));
    
    $sub_name = strtolower(trim($subject['subject_name'] ?? ''));
    $sub_cat  = strtolower(trim($subject['category'] ?? ''));
    
    $reasons = [];
    
    if ($major === $sub_name) {
        $reasons[] = "Major: " . htmlspecialchars($teacher['major_subject']);
    }
    if ($minor === $sub_name) {
        $reasons[] = "Minor: " . htmlspecialchars($teacher['minor_subject']);
    }
    
    // Check category matches
    if ($sub_cat !== '') {
        if ($t_cat === $sub_cat) {
            $reasons[] = "Category: " . ucfirst($sub_cat);
        }
        if ($major === $sub_cat) {
            $reasons[] = "Major Category: " . htmlspecialchars($teacher['major_subject']);
        }
        if ($minor === $sub_cat) {
            $reasons[] = "Minor Category: " . htmlspecialchars($teacher['minor_subject']);
        }
    }
    
    if (!empty($reasons)) {
        return implode(" & ", $reasons);
    }
    return null;
}

/* ── POST ACTIONS ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_assignments'])) {
    $post_exam_id = (int)$_POST['exam_id'];
    
    if ($post_exam_id > 0 && isset($_POST['assignments']) && is_array($_POST['assignments'])) {
        $conn->begin_transaction();
        try {
            // First, fetch exam subjects to validate subject_ids
            $valid_subjects_res = $conn->query("SELECT subject_id FROM exam_subjects WHERE exam_id = {$post_exam_id}");
            $valid_subject_ids = [];
            while ($row = $valid_subjects_res->fetch_assoc()) {
                $valid_subject_ids[] = (int)$row['subject_id'];
            }
            
            // Fetch school teachers to validate teacher_ids
            $teachers_res = $conn->query("SELECT user_id, name, major_subject, minor_subject, teacher_category FROM users WHERE school_id = {$school_id} AND role = 'teacher' AND status = 'active'");
            $school_teachers = [];
            while ($row = $teachers_res->fetch_assoc()) {
                $school_teachers[(int)$row['user_id']] = $row;
            }
            
            foreach ($_POST['assignments'] as $sub_id => $data) {
                $sub_id = (int)$sub_id;
                if (!in_array($sub_id, $valid_subject_ids)) {
                    continue; // Skip invalid subjects
                }
                
                $teacher_id = isset($data['teacher_id']) ? (int)$data['teacher_id'] : 0;
                $deadline   = !empty($data['deadline']) ? trim($data['deadline']) : null;
                
                if ($teacher_id === 0) {
                    // Delete assignment if unassigned
                    $del = $conn->prepare("DELETE FROM marking_assignments WHERE exam_id = ? AND subject_id = ? AND school_id = ?");
                    $del->bind_param("iii", $post_exam_id, $sub_id, $school_id);
                    $del->execute();
                    $del->close();
                } else {
                    // Validate teacher belongs to the school
                    if (!isset($school_teachers[$teacher_id])) {
                        throw new Exception("Selected teacher does not belong to this school.");
                    }
                    
                    // Validate eligibility
                    // Get subject info
                    $sub_info_stmt = $conn->prepare("SELECT subject_name, category FROM subjects WHERE subject_id = ?");
                    $sub_info_stmt->bind_param("i", $sub_id);
                    $sub_info_stmt->execute();
                    $subject = $sub_info_stmt->get_result()->fetch_assoc();
                    $sub_info_stmt->close();
                    
                    $eligibility = get_teacher_eligibility_reason($school_teachers[$teacher_id], $subject);
                    if ($eligibility === null) {
                        throw new Exception("Teacher " . htmlspecialchars($school_teachers[$teacher_id]['name']) . " is not qualified for " . htmlspecialchars($subject['subject_name']) . ".");
                    }
                    
                    // Save assignment
                    $ins = $conn->prepare("
                        INSERT INTO marking_assignments (school_id, exam_id, subject_id, teacher_id, deadline, assigned_by)
                        VALUES (?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id), deadline = VALUES(deadline), assigned_by = VALUES(assigned_by)
                    ");
                    $ins->bind_param("iiiiis", $school_id, $post_exam_id, $sub_id, $teacher_id, $deadline, $ht_id);
                    $ins->execute();
                    $ins->close();
                }
            }
            
            $conn->commit();
            $message = 'Marking assignments and deadlines saved successfully!';
            $msg_type = 'success';
        } catch (Exception $e) {
            $conn->rollback();
            $message = 'Error saving assignments: ' . $e->getMessage();
            $msg_type = 'error';
        }
        
        // Redirect to keep query string and avoid form re-submission
        header("Location: assign_markers.php?exam_id={$post_exam_id}&msg=" . urlencode($message) . "&mtype={$msg_type}");
        exit();
    }
}

if (!empty($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
    $msg_type = $_GET['mtype'] ?? 'success';
}

/* ── FETCH EXAMS ── */
$exams_list = $conn->query("
    SELECT exam_id, exam_name, class, status 
    FROM exams 
    WHERE status IN ('draft', 'active')
    ORDER BY exam_name ASC
")->fetch_all(MYSQLI_ASSOC);

/* ── GET TEACHERS AT THIS SCHOOL ── */
$teachers = $conn->query("
    SELECT user_id, name, major_subject, minor_subject, teacher_category 
    FROM users 
    WHERE school_id = {$school_id} AND role = 'teacher' AND status = 'active'
    ORDER BY name ASC
")->fetch_all(MYSQLI_ASSOC);

/* ── GET SUBJECTS & CURRENT ASSIGNMENTS FOR CHOSEN EXAM ── */
$exam_subjects = [];
$exam_details = null;
if ($exam_id > 0) {
    // Get exam details
    $exam_stmt = $conn->prepare("SELECT exam_name, class, status FROM exams WHERE exam_id = ?");
    $exam_stmt->bind_param("i", $exam_id);
    $exam_stmt->execute();
    $exam_details = $exam_stmt->get_result()->fetch_assoc();
    $exam_stmt->close();
    
    if ($exam_details) {
        $exam_subjects = $conn->query("
            SELECT es.subject_id, s.subject_name, s.subject_code, s.category,
                   ma.teacher_id, ma.deadline, ma.assigned_at, u.name AS marker_name
            FROM exam_subjects es
            JOIN subjects s ON es.subject_id = s.subject_id
            LEFT JOIN marking_assignments ma ON ma.exam_id = es.exam_id AND ma.subject_id = es.subject_id AND ma.school_id = {$school_id}
            LEFT JOIN users u ON u.user_id = ma.teacher_id
            WHERE es.exam_id = {$exam_id}
            ORDER BY s.subject_name ASC
        ")->fetch_all(MYSQLI_ASSOC);
    }
}

$conn->close();

$module_css = 'headteacher';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assign Markers | NED-SEMS</title>
<style>
.form-select {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    background-color: var(--card-bg);
    color: var(--text-color);
    font-size: 0.9rem;
    width: 100%;
}
.form-date {
    padding: 7px 12px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    background-color: var(--card-bg);
    color: var(--text-color);
    font-size: 0.9rem;
    width: 100%;
}
.reason-badge {
    display: block;
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 4px;
}
.empty-warning {
    color: var(--danger-color);
    font-size: 0.8rem;
    font-weight: 600;
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
        <h2 class="page-title">Marking Delegation</h2>
        <p class="page-subtitle">Assign teachers from your school to mark subjects and set deadlines</p>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $msg_type ?>"><?= $message ?></div>
<?php endif; ?>

<div class="card" style="padding:20px; margin-bottom:20px;">
    <h3 style="margin-top:0; margin-bottom:15px;">Select Examination</h3>
    <form method="GET" id="exam_form">
        <div style="display:flex; gap:15px; align-items:center;">
            <div style="flex:1; max-width:400px;">
                <select name="exam_id" onchange="document.getElementById('exam_form').submit()" class="form-select">
                    <option value="">— Select Exam —</option>
                    <?php foreach ($exams_list as $ex): ?>
                        <option value="<?= $ex['exam_id'] ?>" <?= $exam_id === (int)$ex['exam_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ex['exam_name']) ?> (<?= htmlspecialchars($ex['class']) ?>) - <?= ucfirst(htmlspecialchars($ex['status'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($exam_id > 0 && $exam_details): ?>
                <div style="font-size: 0.9rem; color: var(--text-muted);">
                    <strong>Class:</strong> <?= htmlspecialchars($exam_details['class']) ?> | 
                    <strong>Status:</strong> <span class="badge badge-<?= strtolower($exam_details['status']) ?>"><?= ucfirst(htmlspecialchars($exam_details['status'])) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if ($exam_id > 0 && $exam_details): ?>
    <form method="POST">
        <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
        
        <div class="card" style="padding:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3 style="margin:0;">Subject Marking Delegation</h3>
                <span style="font-size:0.85rem; color:var(--text-muted);">Only qualified teachers for each subject are listed.</span>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width:25%;">Subject</th>
                            <th style="width:15%;">Category</th>
                            <th style="width:35%;">Assigned Marker</th>
                            <th style="width:25%;">Submission Deadline</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($exam_subjects)): ?>
                            <?php foreach ($exam_subjects as $sub): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($sub['subject_name']) ?></strong>
                                        <div style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($sub['subject_code']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge" style="background:#e2e8f0; color:#334155;">
                                            <?= htmlspecialchars(ucfirst($sub['category'] ?: 'Other')) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        // Filter teachers eligible for this subject
                                        $eligible_teachers = [];
                                        foreach ($teachers as $t) {
                                            $reason = get_teacher_eligibility_reason($t, $sub);
                                            if ($reason !== null) {
                                                $eligible_teachers[] = [
                                                    'teacher' => $t,
                                                    'reason' => $reason
                                                ];
                                            }
                                        }
                                        ?>
                                        
                                        <select name="assignments[<?= $sub['subject_id'] ?>][teacher_id]" class="form-select">
                                            <option value="">— Unassigned —</option>
                                            <?php foreach ($eligible_teachers as $et): ?>
                                                <option value="<?= $et['teacher']['user_id'] ?>" <?= (int)$sub['teacher_id'] === (int)$et['teacher']['user_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($et['teacher']['name']) ?> (<?= $et['reason'] ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        
                                        <?php if (empty($eligible_teachers)): ?>
                                            <div class="empty-warning">No teachers qualified in major/minor for this subject at your school.</div>
                                        <?php endif; ?>
                                        
                                        <?php if ($sub['teacher_id']): ?>
                                            <div class="reason-badge">
                                                Assigned on: <?= date('d M Y', strtotime($sub['assigned_at'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <input type="date" name="assignments[<?= $sub['subject_id'] ?>][deadline]" 
                                               value="<?= $sub['deadline'] ?: '' ?>" class="form-date">
                                        <?php if ($sub['deadline'] && strtotime($sub['deadline']) < time()): ?>
                                            <div style="color:var(--danger-color); font-size:0.75rem; margin-top:4px; font-weight:600;">
                                                Deadline Passed (Late)
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align:center; padding:20px;">
                                    No subjects have been linked to this exam.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($exam_subjects)): ?>
                <div style="margin-top:20px; text-align:right;">
                    <button type="submit" name="save_assignments" class="btn btn-primary" style="padding:10px 20px;">
                        Save Marking Assignments & Deadlines
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </form>
<?php elseif ($exam_id > 0): ?>
    <div class="card" style="padding:20px; text-align:center;">
        <p>Selected exam not found or cannot be modified.</p>
    </div>
<?php else: ?>
    <div class="card" style="padding:30px; text-align:center; color:var(--text-muted);">
        <h3>Please select an exam to delegate marking tasks.</h3>
        <p>You can only manage marking assignments for draft or active examinations.</p>
    </div>
<?php endif; ?>

</div>
</div>
<?php include '../common/footer.php'; ?>
</body>
</html>
