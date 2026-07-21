<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'headteacher') {
    header("Location: ../login.php");
    exit();
}

$school_id = (int)$_SESSION['school_id'];
$search = trim($_GET['search'] ?? '');
$class_filter = trim($_GET['class'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$subject_filter = (int)($_GET['subject'] ?? 0);

$conn = get_db_connection();

// Get active subjects for filter
$subjects_res = $conn->query("SELECT subject_id, subject_name FROM subjects WHERE status='active' ORDER BY subject_name ASC");
$all_subjects = [];
if ($subjects_res) {
    while ($r = $subjects_res->fetch_assoc()) {
        $all_subjects[] = $r;
    }
}

// Build query
$sql = "SELECT s.student_id, s.name, s.exam_number, s.class, s.status FROM students s WHERE s.school_id = $school_id";
if ($search !== '') {
    $safe = $conn->real_escape_string($search);
    $sql .= " AND (s.name LIKE '%$safe%' OR s.exam_number LIKE '%$safe%')";
}
if ($class_filter !== '') {
    $safe = $conn->real_escape_string($class_filter);
    $sql .= " AND s.class = '$safe'";
}
if ($status_filter !== '') {
    $safe = $conn->real_escape_string($status_filter);
    $sql .= " AND s.status = '$safe'";
}
if ($subject_filter > 0) {
    $sql .= " AND EXISTS (SELECT 1 FROM student_subjects ss WHERE ss.student_id = s.student_id AND ss.subject_id = $subject_filter)";
}
$sql .= " ORDER BY s.name ASC";

$result = $conn->query($sql);
$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
$total = count($students);
$active = count(array_filter($students, fn($s) => $s['status'] === 'active'));

// Fetch subject counts in one query
$student_subject_counts = [];
$student_ids = array_column($students, 'student_id');
if (!empty($student_ids)) {
    $id_list = implode(',', array_map('intval', $student_ids));
    $cnt_res = $conn->query("SELECT student_id, COUNT(*) as cnt FROM student_subjects WHERE student_id IN ($id_list) GROUP BY student_id");
    if ($cnt_res) {
        while ($row = $cnt_res->fetch_assoc()) {
            $student_subject_counts[(int)$row['student_id']] = (int)$row['cnt'];
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
<title>Manage Students</title>
<?php $module_css = 'headteacher'; include __DIR__ . '/../common/head_assets.php'; ?>
<style>
    .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; overflow-y:auto; }
    .modal.show { display:block; }
    .modal-content { background:#fff; max-width:640px; margin:8% auto; padding:24px; border-radius:var(--border-radius); }
    .modal-actions { display:flex; justify-content:flex-end; gap:12px; margin-top:22px; }
</style>
</head>
<body>
<?php include '../common/header.php'; ?>
<div class="dashboard">
<?php include '../common/sidebar.php'; ?>
<div class="content">

<div class="page-header">
    <div>
        <h1 class="page-title">Student Management</h1>
        <p class="stats-info"><?= $total ?> students · <?= $active ?> active</p>
    </div>
    <a href="add_student.php" class="btn btn-dark">+ Add Student</a>
</div>

<form method="GET" class="search-form">
    <input type="text" name="search" placeholder="Search name or exam number..." value="<?= htmlspecialchars($search) ?>">
    <select name="class">
        <option value="">All Classes</option>
        <option value="Form 2" <?= $class_filter === 'Form 2' ? 'selected' : '' ?>>Form 2</option>
        <option value="Form 4" <?= $class_filter === 'Form 4' ? 'selected' : '' ?>>Form 4</option>
    </select>
    <select name="status">
        <option value="">All Status</option>
        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
    </select>
    <select name="subject">
        <option value="">All Subjects</option>
        <?php foreach ($all_subjects as $subj): ?>
            <option value="<?= $subj['subject_id'] ?>" <?= $subject_filter === (int)$subj['subject_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($subj['subject_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Filter</button>
</form>

<div class="section">
    <div class="table-container">
        <table class="table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Exam Number</th>
                    <th>Class</th>
                    <th>Subjects</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($students): foreach ($students as $student):
                    $subj_count = $student_subject_counts[(int)$student['student_id']] ?? 0;
                ?>
                    <tr>
                        <td>#<?= (int)$student['student_id'] ?></td>
                        <td><strong><?= htmlspecialchars($student['name']) ?></strong></td>
                        <td><?= htmlspecialchars($student['exam_number']) ?></td>
                        <td><?= htmlspecialchars($student['class'] ?? '—') ?></td>
                        <td>
                            <span class="subject-count-badge <?= $subj_count === 0 ? 'none' : '' ?>">
                                <?= $subj_count ?>
                            </span>
                        </td>
                        <td><span class="badge badge-<?= $student['status'] === 'active' ? 'active' : 'inactive' ?>"><?= ucfirst($student['status']) ?></span></td>
                        <td class="actions">
                            <button type="button" class="btn btn-small btn-secondary"
                                    onclick="openDetailsModal(<?= (int)$student['student_id'] ?>, <?= htmlspecialchars(json_encode($student['name'])) ?>)">
                                Details
                            </button>
                            <a href="released_results.php" class="btn btn-small btn-primary">Results</a>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="7" class="text-center">No students found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
</div>

<!-- STUDENT DETAILS MODAL -->
<div id="detailsModal" class="modal">
    <div class="modal-content">
        <h3 id="details_heading">Student Details</h3>
        <div id="details_body"></div>
        <div class="modal-actions">
            <button type="button" onclick="closeDetailsModal()" class="btn btn-secondary">Close</button>
        </div>
    </div>
</div>

<script>
function openDetailsModal(studentId, studentName) {
    document.getElementById('details_heading').textContent = studentName;
    document.getElementById('details_body').innerHTML = '<div class="empty-state">Loading student details...</div>';
    document.getElementById('detailsModal').classList.add('show');

    fetch('../common/get_student_details.php?student_id=' + studentId + '&show_print=false')
        .then(response => response.text())
        .then(html => {
            document.getElementById('details_body').innerHTML = html;
        })
        .catch(err => {
            document.getElementById('details_body').innerHTML = '<div class="alert alert-error">Error loading details.</div>';
        });
}

function closeDetailsModal() {
    document.getElementById('detailsModal').classList.remove('show');
}

document.getElementById('detailsModal').addEventListener('click', function(e) {
    if (e.target === this) closeDetailsModal();
});
</script>

<?php include '../common/footer.php'; ?>
</body>
</html>
