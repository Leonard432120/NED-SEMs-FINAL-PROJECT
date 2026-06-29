<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'headteacher') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();
$school_id = (int)$_SESSION['school_id'];
$headteacher_id = (int)$_SESSION['user_id'];

// Get available exams from teachers at the school
$available_exams = $conn->query("
    SELECT e.exam_id, e.exam_name, s.subject_name, u.name as teacher_name, e.status
    FROM exams e
    LEFT JOIN subjects s ON e.subject_id = s.subject_id
    LEFT JOIN users u ON e.created_by = u.user_id
    WHERE u.school_id = $school_id
    AND e.status IN ('draft', 'approved', 'submitted')
    ORDER BY e.start_date DESC
");

// Get examination officers
$exam_officers = $conn->query("
    SELECT user_id, name
    FROM users
    WHERE school_id = $school_id
    AND role = 'examination_officer'
    AND status = 'active'
");

// Get forwarded exams history
$forwarded_exams = false;
$forwarding_table_exists = $conn->query("SHOW TABLES LIKE 'exam_documents_forwarding'")->num_rows > 0;
if ($forwarding_table_exists) {
    $forwarded_exams = $conn->query("
        SELECT ed.*, e.exam_name, s.subject_name, r.name as receiver_name
        FROM exam_documents_forwarding ed
        JOIN exams e ON ed.exam_id = e.exam_id
        LEFT JOIN subjects s ON e.subject_id = s.subject_id
        LEFT JOIN users r ON ed.receiver_id = r.user_id
        WHERE ed.document_type = 'exam'
        AND ed.receiver_school_id = $school_id
        ORDER BY ed.created_at DESC
        LIMIT 20
    ");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forward Exams</title>

<?php
$portal_title = 'NED-SEMS | Headteacher Portal';
$module_css = 'headteacher';
include __DIR__ . '/../common/head_assets.php';
?>
</head>

<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">

<?php include '../common/sidebar.php'; ?>

<div class="content">

<div class="page-header">
    <h1>Forward Exams to Examination Officers</h1>
    <p>Send exams created by your teachers to examination officers</p>
</div>

<div class="section">

<h3>Forward Exams</h3>

<form method="POST" action="forwarding_center.php">
    <input type="hidden" name="action" value="forward_exams">
    
    <div class="form-group">
        <label>Send to Examination Officer</label>
        <select name="recipient_id" required>
            <option value="">Select an officer...</option>
            <?php while ($officer = $exam_officers->fetch_assoc()): ?>
            <option value="<?= $officer['user_id'] ?>"><?= htmlspecialchars($officer['name']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    
    <div class="form-group">
        <label>Select Exams to Forward</label>
        <div class="checkbox-group">
            <?php if ($available_exams && $available_exams->num_rows > 0): ?>
            
            <?php while ($exam = $available_exams->fetch_assoc()): ?>
            <div class="checkbox-item">
                <input type="checkbox" name="exam_ids[]" value="<?= $exam['exam_id'] ?>">
                <label>
                    <strong><?= htmlspecialchars($exam['exam_name']) ?></strong>
                    <br>
                    <small style="color: #666;">
                        <?= htmlspecialchars($exam['subject_name'] ?? 'N/A') ?> 
                        | <?= htmlspecialchars($exam['teacher_name']) ?>
                        | <span class="badge badge-<?= strtolower($exam['status']) ?>">
                            <?= ucfirst($exam['status']) ?>
                        </span>
                    </small>
                </label>
            </div>
            <?php endwhile; ?>
            
            <?php else: ?>
            
            <div class="no-data">
                <p>No exams available to forward.</p>
            </div>
            
            <?php endif; ?>
        </div>
    </div>
    
    <div class="form-group">
        <label>Notes (Optional)</label>
        <textarea name="notes" placeholder="Add any notes for the examination officer..."></textarea>
    </div>
    
    <button type="submit" class="btn btn-primary">📤 Forward Selected Exams</button>
</form>

</div>

<div class="section">

<h3>Forwarded Exams History</h3>

<?php if ($forwarded_exams && $forwarded_exams->num_rows > 0): ?>

<table>

<thead>
<tr>
    <th>Exam Name</th>
    <th>Subject</th>
    <th>To Officer</th>
    <th>Sent Date</th>
    <th>Status</th>
</tr>
</thead>

<tbody>

<?php while ($exam = $forwarded_exams->fetch_assoc()): ?>

<tr>
    <td><?= htmlspecialchars($exam['exam_name']) ?></td>
    <td><?= htmlspecialchars($exam['subject_name'] ?? 'N/A') ?></td>
    <td><?= htmlspecialchars($exam['receiver_name'] ?? 'N/A') ?></td>
    <td><?= date('d M Y H:i', strtotime($exam['created_at'])) ?></td>
    <td>
        <span class="badge badge-<?= strtolower($exam['status']) ?>">
            <?= ucfirst($exam['status']) ?>
        </span>
    </td>
</tr>

<?php endwhile; ?>

</tbody>

</table>

<?php else: ?>

<div class="no-data">
    <p>No forwarded exams yet.</p>
</div>

<?php endif; ?>

</div>

</div>

</div>

<?php
$conn->close();
?>
<?php include '../common/footer.php'; ?>
