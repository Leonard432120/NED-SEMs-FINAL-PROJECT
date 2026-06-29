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

// Get available results from school students
$available_results = $conn->query("
    SELECT 
        r.result_id,
        r.exam_id,
        e.exam_name,
        st.name AS student_name,
        r.term,
        r.year,
        r.class,
        r.total_score,
        r.average_score,
        r.status,
        r.compiled_at
    FROM results r
    JOIN students st ON r.student_id = st.student_id
    JOIN exams e ON r.exam_id = e.exam_id
    WHERE st.school_id = $school_id
    AND r.status IN ('draft', 'submitted', 'approved', 'published', 'eo_approved', 'head_approved')
    ORDER BY r.compiled_at DESC
    LIMIT 100
");

// Get examination officers
$exam_officers = $conn->query("
    SELECT user_id, name
    FROM users
    WHERE school_id = $school_id
    AND role = 'examination_officer'
    AND status = 'active'
");

// Get forwarded results history
$forwarded_results = false;
$forwarding_table_exists = $conn->query("SHOW TABLES LIKE 'exam_documents_forwarding'")->num_rows > 0;
if ($forwarding_table_exists) {
    $forwarded_results = $conn->query("
        SELECT ed.*, e.exam_name, COUNT(ed.id) as result_count, r.name as receiver_name
        FROM exam_documents_forwarding ed
        LEFT JOIN exams e ON ed.exam_id = e.exam_id
        LEFT JOIN users r ON ed.receiver_id = r.user_id
        WHERE ed.document_type = 'results'
        AND ed.receiver_school_id = $school_id
        GROUP BY ed.id
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
<title>Forward Results</title>

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
    <h1>Forward Results to Examination Officer</h1>
    <p>Send compiled results to the examination officer for final review</p>
</div>

<div class="stat-card">
    <p>Available Results: <?= $available_results ? $available_results->num_rows : 0 ?></p>
</div>

<div class="section">

<h3>Forward Results by Exam</h3>

<form method="POST" action="forwarding_center.php">
    <input type="hidden" name="action" value="forward_results">
    
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
        <label>Select Results to Forward (By Exam)</label>
        <div class="checkbox-group">
            <?php 
            if ($available_results && $available_results->num_rows > 0): 
                $exams = [];
                $available_results->data_seek(0);
                while ($result = $available_results->fetch_assoc()):
                    $exam_id = $result['exam_id'];
                    if (!isset($exams[$exam_id])) {
                        $exams[$exam_id] = [
                            'exam_name' => $result['exam_name'],
                            'class' => $result['class'],
                            'count' => 0
                        ];
                    }
                    $exams[$exam_id]['count']++;
                endwhile;
                
                foreach ($exams as $exam_id => $exam_info):
            ?>
            <div class="checkbox-item">
                <input type="checkbox" name="exam_ids[]" value="<?= $exam_id ?>">
                <label>
                    <strong><?= htmlspecialchars($exam_info['exam_name']) ?></strong>
                    <br>
                    <small style="color: #666;">
                        Class: <?= htmlspecialchars($exam_info['class'] ?? 'N/A') ?> 
                        | <?= $exam_info['count'] ?> result(s)
                    </small>
                </label>
            </div>
            <?php 
                endforeach;
            else: 
            ?>
            
            <div class="no-data">
                <p>No approved results available to forward.</p>
            </div>
            
            <?php endif; ?>
        </div>
    </div>
    
    <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" placeholder="Add any notes for the examination officer (e.g., clarifications, special cases)..."></textarea>
    </div>
    
    <button type="submit" class="btn btn-primary">Forward Selected Results</button>
</form>

</div>

<div class="section">

<h3>Forwarded Results History</h3>

<?php if ($forwarded_results && $forwarded_results->num_rows > 0): ?>

<table>

<thead>
<tr>
    <th>Exam Name</th>
    <th>Results Count</th>
    <th>To Officer</th>
    <th>Sent Date</th>
    <th>Status</th>
</tr>
</thead>

<tbody>

<?php while ($fwd = $forwarded_results->fetch_assoc()): ?>

<tr>
    <td><?= htmlspecialchars($fwd['exam_name'] ?? 'Batch Results') ?></td>
    <td><?= $fwd['result_count'] ?></td>
    <td><?= htmlspecialchars($fwd['receiver_name'] ?? 'N/A') ?></td>
    <td><?= date('d M Y H:i', strtotime($fwd['created_at'])) ?></td>
    <td>
        <span class="badge badge-<?= strtolower($fwd['status']) ?>">
            <?= ucfirst($fwd['status']) ?>
        </span>
    </td>
</tr>

<?php endwhile; ?>

</tbody>

</table>

<?php else: ?>

<div class="no-data">
    <p>No forwarded results yet.</p>
</div>

<?php endif; ?>

</div>

</div>

</div>

<?php
$conn->close();
?>
<?php include '../common/footer.php'; ?>
