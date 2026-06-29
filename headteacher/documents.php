<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'headteacher') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();
$school_id = (int)$_SESSION['school_id'];

// Handle file upload
$school_documents_exists = $conn->query("SHOW TABLES LIKE 'school_documents'")->num_rows > 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_document') {
    $doc_type = htmlspecialchars($_POST['doc_type']);
    $doc_file = $_FILES['doc_file'] ?? null;
    
    if ($school_documents_exists && $doc_file && $doc_type) {
        $upload_dir = '../static/uploads/documents/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $filename = $doc_type . '_' . time() . '_' . basename($doc_file['name']);
        $filepath = $upload_dir . $filename;
        
        if (move_uploaded_file($doc_file['tmp_name'], $filepath)) {
            $conn->query("
                INSERT INTO school_documents (
                    school_id, document_type, file_path, uploaded_by, created_at
                ) VALUES (
                    $school_id, '$doc_type', '$filename', {$_SESSION['user_id']}, NOW()
                )
            ");
            
            $success_msg = "Document uploaded successfully!";
        }
    } else {
        $error_msg = "Document upload is unavailable because the school_documents table is not present.";
    }
}

// Get uploaded documents
$documents = false;
if ($school_documents_exists) {
    $documents = $conn->query("\
        SELECT * FROM school_documents
        WHERE school_id = $school_id
        ORDER BY created_at DESC
    ");
}

// Get timetables (if table exists)
$timetables = [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Documents & Timetables</title>

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
    <h1>📄 Documents & Timetables</h1>
    <p>Manage and store important school documents and timetables</p>
</div>

<?php if (isset($success_msg)): ?>
<div class="success-msg"><?= htmlspecialchars($success_msg) ?></div>
<?php endif; ?>

<div class="section">

<h3>Upload Document</h3>

<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="upload_document">
    
    <div class="form-group">
        <label>Document Type</label>
        <select name="doc_type" required>
            <option value="">Select type...</option>
            <option value="timetable">Examination Timetable</option>
            <option value="syllabus">Syllabus</option>
            <option value="policy">School Policy</option>
            <option value="procedure">Procedure Document</option>
            <option value="other">Other</option>
        </select>
    </div>
    
    <div class="form-group">
        <label>File Upload (PDF, DOCX, XLSX)</label>
        <input type="file" name="doc_file" accept=".pdf,.docx,.xlsx,.xls,.doc" required>
    </div>
    
    <button type="submit" class="btn btn-primary">📤 Upload Document</button>
</form>

</div>

<div class="section">

<h3>Uploaded Documents</h3>

<?php if ($documents && $documents->num_rows > 0): ?>

<table>

<thead>
<tr>
    <th>Type</th>
    <th>File Name</th>
    <th>Upload Date</th>
    <th>Uploaded By</th>
    <th>Actions</th>
</tr>
</thead>

<tbody>

<?php while ($doc = $documents->fetch_assoc()): ?>

<tr>
    <td>
        <span class="badge">
            <?= ucfirst(str_replace('_', ' ', $doc['document_type'])) ?>
        </span>
    </td>
    <td><?= htmlspecialchars($doc['file_path']) ?></td>
    <td><?= date('d M Y H:i', strtotime($doc['created_at'])) ?></td>
    <td>N/A</td>
    <td>
        <a href="../static/uploads/documents/<?= htmlspecialchars($doc['file_path']) ?>" 
           class="btn btn-sm btn-download" download>
            📥 Download
        </a>
    </td>
</tr>

<?php endwhile; ?>

</tbody>

</table>

<?php else: ?>

<div class="no-data">
    <p>No documents uploaded yet.</p>
</div>

<?php endif; ?>

</div>

</div>

</div>

<?php
$conn->close();
?>
<?php include '../common/footer.php'; ?>
