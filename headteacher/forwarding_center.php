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
$forwarding_table_exists = $conn->query("SHOW TABLES LIKE 'exam_documents_forwarding'")->num_rows > 0;

// Handle forwarding actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'forward_timetable') {
        $timetable_file = $_FILES['timetable_file'] ?? null;
        $recipient_id = (int)($_POST['recipient_id'] ?? 0);
        
        if ($timetable_file && $recipient_id > 0) {
            $upload_dir = '../static/uploads/';
            $filename = 'timetable_' . time() . '_' . basename($timetable_file['name']);
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($timetable_file['tmp_name'], $filepath)) {
                if ($forwarding_table_exists) {
                    $conn->query("
                        INSERT INTO exam_documents_forwarding (
                            document_type, file_path, sender_id, receiver_id, 
                            receiver_school_id, status, created_at
                        ) VALUES (
                            'timetable', '$filename', $headteacher_id, $recipient_id,
                            $school_id, 'pending', NOW()
                        )
                    ");
                    $success_msg = "Timetable forwarded successfully!";
                } else {
                    $error_msg = "Forwarding is unavailable because the exam_documents_forwarding table is not present.";
                }
            }
        }
    }
    
    if ($action === 'forward_exams') {
        $exam_ids = $_POST['exam_ids'] ?? [];
        $recipient_id = (int)($_POST['recipient_id'] ?? 0);
        $notes = $conn->real_escape_string($_POST['notes'] ?? '');
        
        if (!empty($exam_ids) && $recipient_id > 0) {
            if ($forwarding_table_exists) {
                foreach ($exam_ids as $exam_id) {
                    $exam_id = (int)$exam_id;
                    $conn->query("
                        INSERT INTO exam_documents_forwarding (
                            exam_id, document_type, file_path, sender_id, receiver_id,
                            receiver_school_id, status, notes, created_at
                        ) VALUES (
                            $exam_id, 'exam', '', $headteacher_id, $recipient_id,
                            $school_id, 'pending', '$notes', NOW()
                        )
                    ");
                }
                $success_msg = "Exams forwarded successfully!";
            } else {
                $error_msg = "Forwarding is unavailable because the exam_documents_forwarding table is not present.";
            }
        }
    }
    
    if ($action === 'forward_results') {
        $exam_ids = $_POST['exam_ids'] ?? [];
        $recipient_id = (int)($_POST['recipient_id'] ?? 0);
        $notes = $conn->real_escape_string($_POST['notes'] ?? '');
        
        if (!empty($exam_ids) && $recipient_id > 0) {
            if ($forwarding_table_exists) {
                foreach ($exam_ids as $exam_id) {
                    $exam_id = (int)$exam_id;
                    $conn->query("
                        INSERT INTO exam_documents_forwarding (
                            exam_id, document_type, file_path, sender_id, receiver_id,
                            receiver_school_id, status, notes, created_at
                        ) VALUES (
                            $exam_id, 'results', '', $headteacher_id, $recipient_id,
                            $school_id, 'pending', '$notes', NOW()
                        )
                    ");
                    // Update results of this school and exam to 'submitted'
                    $conn->query("
                        UPDATE results r
                        JOIN students st ON r.student_id = st.student_id
                        SET r.status = 'submitted'
                        WHERE r.exam_id = $exam_id AND st.school_id = $school_id
                          AND r.status = 'draft'
                    ");
                }
                $success_msg = "Results forwarded successfully!";
            } else {
                $error_msg = "Forwarding is unavailable because the exam_documents_forwarding table is not present.";
            }
        }
    }
}

$pending_docs = false;
$received_docs = false;
$sent_docs = false;
if ($forwarding_table_exists) {
    // Get pending documents
    $pending_docs = $conn->query("
        SELECT d.*, u.name as sender_name, r.name as receiver_name
        FROM exam_documents_forwarding d
        LEFT JOIN users u ON d.sender_id = u.user_id
        LEFT JOIN users r ON d.receiver_id = r.user_id
        WHERE d.receiver_school_id = $school_id
        AND d.status = 'pending'
        ORDER BY d.created_at DESC
        LIMIT 10
    ");

    // Get received documents
    $received_docs = $conn->query("
        SELECT d.*, u.name as sender_name, r.name as receiver_name
        FROM exam_documents_forwarding d
        LEFT JOIN users u ON d.sender_id = u.user_id
        LEFT JOIN users r ON d.receiver_id = r.user_id
        WHERE d.receiver_school_id = $school_id
        AND d.status = 'received'
        ORDER BY d.created_at DESC
        LIMIT 10
    ");

    // Get sent documents
    $sent_docs = $conn->query("
        SELECT d.*, u.name as sender_name, r.name as receiver_name
        FROM exam_documents_forwarding d
        LEFT JOIN users u ON d.sender_id = u.user_id
        LEFT JOIN users r ON d.receiver_id = r.user_id
        WHERE d.sender_id = $headteacher_id
        ORDER BY d.created_at DESC
        LIMIT 10
    ");
}

// Get examination officers for dropdown
$exam_officers = $conn->query("
    SELECT user_id, name
    FROM users
    WHERE school_id = $school_id
    AND role = 'examination_officer'
    AND status = 'active'
");

// Get available exams from teachers
$available_exams = $conn->query("
    SELECT e.exam_id, e.exam_name, s.subject_name, u.name as teacher_name
    FROM exams e
    LEFT JOIN subjects s ON e.subject_id = s.subject_id
    LEFT JOIN users u ON e.created_by = u.user_id
    WHERE u.school_id = $school_id
    AND e.status IN ('draft', 'approved', 'submitted')
    ORDER BY e.start_date DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Communication Hub - Forwarding Center</title>

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
    <h1>Communication Hub</h1>
    <p>Forward documents, exams, and results to examination officers</p>
</div>

<?php if (isset($success_msg)): ?>
<div class="success-msg"><?= htmlspecialchars($success_msg) ?></div>
<?php endif; ?>

<div class="tabs">
    <button class="tab-btn active" onclick="switchTab('forward')">Forward Documents</button>
    <button class="tab-btn" onclick="switchTab('pending')">Pending Documents</button>
    <button class="tab-btn" onclick="switchTab('received')">Received Documents</button>
    <button class="tab-btn" onclick="switchTab('sent')">Sent Documents</button>
</div>

<!-- ================= FORWARD DOCUMENTS ================= -->
<div id="forward" class="tab-content active">

<div class="section">

<h3>Forward Timetable</h3>

<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="forward_timetable">
    
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
        <label>Timetable File (PDF, DOCX, XLS)</label>
        <input type="file" name="timetable_file" accept=".pdf,.docx,.xlsx,.xls,.doc" required>
    </div>
    
    <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" placeholder="Add any notes for the examination officer..."></textarea>
    </div>
    
    <button type="submit" class="btn btn-primary">📤 Forward Timetable</button>
</form>

</div>

<div class="section">

<h3>Forward Exams</h3>

<form method="POST">
    <input type="hidden" name="action" value="forward_exams">
    
    <div class="form-group">
        <label>Send to Examination Officer</label>
        <select name="recipient_id" required>
            <option value="">Select an officer...</option>
            <?php 
            $exam_officers->data_seek(0);
            while ($officer = $exam_officers->fetch_assoc()): ?>
            <option value="<?= $officer['user_id'] ?>"><?= htmlspecialchars($officer['name']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    
    <div class="form-group">
        <label>Select Exams to Forward</label>
        <div class="checkbox-group">
            <?php while ($exam = $available_exams->fetch_assoc()): ?>
            <div class="checkbox-item">
                <input type="checkbox" name="exam_ids[]" value="<?= $exam['exam_id'] ?>">
                <label><?= htmlspecialchars($exam['exam_name'] . ' - ' . $exam['subject_name']) ?></label>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    
    <button type="submit" class="btn btn-primary">📤 Forward Selected Exams</button>
</form>

</div>

</div>

<!-- ================= PENDING DOCUMENTS ================= -->
<div id="pending" class="tab-content">

<div class="section">

<h3>Pending Documents (Waiting for Acknowledgement)</h3>

<?php if ($pending_docs && $pending_docs->num_rows > 0): ?>

<table>

<thead>
<tr>
    <th>Type</th>
    <th>From</th>
    <th>To</th>
    <th>Sent Date</th>
    <th>Status</th>
</tr>
</thead>

<tbody>

<?php while ($doc = $pending_docs->fetch_assoc()): ?>

<tr>
    <td><?= ucfirst(str_replace('_', ' ', $doc['document_type'])) ?></td>
    <td><?= htmlspecialchars($doc['sender_name'] ?? 'N/A') ?></td>
    <td><?= htmlspecialchars($doc['receiver_name'] ?? 'N/A') ?></td>
    <td><?= date('d M Y H:i', strtotime($doc['created_at'])) ?></td>
    <td>
        <span class="badge badge-pending">
            <?= ucfirst($doc['status']) ?>
        </span>
    </td>
</tr>

<?php endwhile; ?>

</tbody>

</table>

<?php else: ?>

<div class="no-data">
    <p>No pending documents.</p>
</div>

<?php endif; ?>

</div>

</div>

<!-- ================= RECEIVED DOCUMENTS ================= -->
<div id="received" class="tab-content">

<div class="section">

<h3>Received Documents</h3>

<?php if ($received_docs && $received_docs->num_rows > 0): ?>

<table>

<thead>
<tr>
    <th>Type</th>
    <th>From</th>
    <th>Received Date</th>
    <th>Status</th>
</tr>
</thead>

<tbody>

<?php while ($doc = $received_docs->fetch_assoc()): ?>

<tr>
    <td><?= ucfirst(str_replace('_', ' ', $doc['document_type'])) ?></td>
    <td><?= htmlspecialchars($doc['sender_name'] ?? 'N/A') ?></td>
    <td><?= date('d M Y H:i', strtotime($doc['created_at'])) ?></td>
    <td>
        <span class="badge badge-received">
            <?= ucfirst($doc['status']) ?>
        </span>
    </td>
</tr>

<?php endwhile; ?>

</tbody>

</table>

<?php else: ?>

<div class="no-data">
    <p>No received documents.</p>
</div>

<?php endif; ?>

</div>

</div>

<!-- ================= SENT DOCUMENTS ================= -->
<div id="sent" class="tab-content">

<div class="section">

<h3>Sent Documents</h3>

<?php if ($sent_docs && $sent_docs->num_rows > 0): ?>

<table>

<thead>
<tr>
    <th>Type</th>
    <th>To</th>
    <th>Sent Date</th>
    <th>Status</th>
</tr>
</thead>

<tbody>

<?php while ($doc = $sent_docs->fetch_assoc()): ?>

<tr>
    <td><?= ucfirst(str_replace('_', ' ', $doc['document_type'])) ?></td>
    <td><?= htmlspecialchars($doc['receiver_name'] ?? 'N/A') ?></td>
    <td><?= date('d M Y H:i', strtotime($doc['created_at'])) ?></td>
    <td>
        <span class="badge badge-sent">
            <?= ucfirst($doc['status']) ?>
        </span>
    </td>
</tr>

<?php endwhile; ?>

</tbody>

</table>

<?php else: ?>

<div class="no-data">
    <p>No sent documents.</p>
</div>

<?php endif; ?>

</div>

</div>

</div>

</div>

<script>
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName).classList.add('active');
    
    // Add active class to clicked button
    event.target.classList.add('active');
}
</script>

<?php
$conn->close();
?>
<?php include '../common/footer.php'; ?>
