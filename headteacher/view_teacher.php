<?php
/* ════════════════════════════════════════════════════════════════
   headteacher/view_teacher.php
   HT: View detailed profile of a teacher and their tasks.
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'headteacher') {
    header("Location: ../login.php"); exit();
}

$conn      = get_db_connection();
$school_id = (int)($_SESSION['school_id'] ?? 0);
$teacher_id = (int)($_GET['id'] ?? 0);

// Fetch teacher details
$stmt = $conn->prepare("
    SELECT * FROM users 
    WHERE user_id = ? AND school_id = ? AND role = 'teacher'
");
$stmt->bind_param("ii", $teacher_id, $school_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$teacher) {
    die("Teacher not found or does not belong to your school.");
}

// Fetch Item Writer assignments
$writer_stmt = $conn->prepare("
    SELECT DISTINCT e.exam_name, s.subject_name, sa.status, sa.assigned_at
    FROM subject_assignments sa
    JOIN subjects s ON sa.subject_id = s.subject_id
    JOIN exam_subjects es ON s.subject_id = es.subject_id
    JOIN exams e ON es.exam_id = e.exam_id
    WHERE sa.teacher_id = ? AND sa.role = 'item_writer'
    ORDER BY sa.assigned_at DESC
");
$writer_stmt->bind_param("i", $teacher_id);
$writer_stmt->execute();
$writer_tasks = $writer_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$writer_stmt->close();

// Fetch Moderator assignments
$moderator_stmt = $conn->prepare("
    SELECT DISTINCT e.exam_name, s.subject_name, sa.status, sa.assigned_at
    FROM subject_assignments sa
    JOIN subjects s ON sa.subject_id = s.subject_id
    JOIN exam_subjects es ON s.subject_id = es.subject_id
    JOIN exams e ON es.exam_id = e.exam_id
    WHERE sa.teacher_id = ? AND sa.role = 'moderator'
    ORDER BY sa.assigned_at DESC
");
$moderator_stmt->bind_param("i", $teacher_id);
$moderator_stmt->execute();
$moderator_tasks = $moderator_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$moderator_stmt->close();

// Fetch Marker assignments
$marker_stmt = $conn->prepare("
    SELECT e.exam_name, s.subject_name, ma.deadline, ma.status, ma.assigned_at
    FROM marking_assignments ma
    JOIN exams e ON ma.exam_id = e.exam_id
    JOIN subjects s ON ma.subject_id = s.subject_id
    WHERE ma.teacher_id = ? AND ma.school_id = ?
    ORDER BY ma.deadline ASC
");
$marker_stmt->bind_param("ii", $teacher_id, $school_id);
$marker_stmt->execute();
$marking_tasks = $marker_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$marker_stmt->close();

$conn->close();

$module_css = 'headteacher';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Teacher | NED-SEMS</title>
<style>
.profile-card {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 30px;
    align-items: start;
}
.profile-img-container {
    text-align: center;
}
.profile-img {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--primary-light);
}
.profile-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px 30px;
}
.detail-item {
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 8px;
}
.detail-label {
    font-size: 0.8rem;
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
}
.detail-value {
    font-size: 1rem;
    font-weight: 500;
    margin-top: 4px;
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
        <h2 class="page-title">Teacher Profile</h2>
        <p class="page-subtitle">Detailed information for <?= htmlspecialchars($teacher['name']) ?></p>
    </div>
    <div class="actions">
        <a href="manage_teachers.php" class="btn btn-secondary">Back to Directory</a>
        <a href="teacher_performance.php?id=<?= $teacher['user_id'] ?>" class="btn btn-primary">View Performance</a>
    </div>
</div>

<div class="card" style="padding: 25px; margin-bottom: 25px;">
    <div class="profile-card">
        <div class="profile-img-container">
            <img src="<?= $teacher['profile_image'] ? BASE_URL . '/uploads/profiles/' . $teacher['profile_image'] : BASE_URL . '/assets/images/default-avatar.png' ?>" 
                 alt="Profile Picture" class="profile-img" onerror="this.src='<?= BASE_URL ?>/assets/images/default-avatar.png'">
            <h3 style="margin-top: 15px; margin-bottom: 5px;"><?= htmlspecialchars($teacher['name']) ?></h3>
            <span class="badge badge-<?= strtolower($teacher['status']) ?>"><?= ucfirst(htmlspecialchars($teacher['status'])) ?></span>
        </div>
        
        <div>
            <h3 style="margin-top: 0; border-bottom: 2px solid var(--primary-dark); padding-bottom: 8px;">Personal & Professional Details</h3>
            <div class="profile-details">
                <div class="detail-item">
                    <div class="detail-label">Email Address</div>
                    <div class="detail-value"><?= htmlspecialchars($teacher['email']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Phone Number</div>
                    <div class="detail-value"><?= htmlspecialchars($teacher['phone'] ?: '—') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Gender</div>
                    <div class="detail-value"><?= htmlspecialchars($teacher['gender'] ?: '—') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Employment Number</div>
                    <div class="detail-value">CLOSED</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Qualification</div>
                    <div class="detail-value"><?= htmlspecialchars($teacher['qualification'] ?: '—') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Teacher Category</div>
                    <div class="detail-value"><?= htmlspecialchars(ucfirst($teacher['teacher_category'] ?? '—')) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Major Subject</div>
                    <div class="detail-value"><?= htmlspecialchars($teacher['major_subject'] ?: '—') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Minor Subject</div>
                    <div class="detail-value"><?= htmlspecialchars($teacher['minor_subject'] ?: '—') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card" style="padding: 25px;">
    <h3 style="margin-top: 0; margin-bottom: 15px;">Examination Task Assignments</h3>
    
    <div style="margin-bottom: 20px;">
        <h4 style="border-left: 4px solid var(--primary-dark); padding-left: 10px; margin-bottom: 10px;">Marking Assignments (Marker)</h4>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Exam Name</th>
                        <th>Subject</th>
                        <th>Assigned Date</th>
                        <th>Deadline</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($marking_tasks)): ?>
                        <?php foreach ($marking_tasks as $mt): ?>
                            <?php $is_past = $mt['deadline'] && strtotime($mt['deadline']) < time(); ?>
                            <tr>
                                <td><?= htmlspecialchars($mt['exam_name']) ?></td>
                                <td><?= htmlspecialchars($mt['subject_name']) ?></td>
                                <td><?= date('d M Y', strtotime($mt['assigned_at'])) ?></td>
                                <td><?= $mt['deadline'] ? date('d M Y', strtotime($mt['deadline'])) : 'No Deadline' ?></td>
                                <td>
                                    <?php if ($is_past): ?>
                                        <span class="badge badge-danger">Late / Closed</span>
                                    <?php else: ?>
                                        <span class="badge badge-<?= strtolower($mt['status'] === 'assigned' ? 'warning' : 'success') ?>">
                                            <?= ucfirst(htmlspecialchars($mt['status'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center" style="padding:15px; color:var(--text-muted);">No marking tasks assigned.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div style="margin-bottom: 20px;">
        <h4 style="border-left: 4px solid var(--primary-dark); padding-left: 10px; margin-bottom: 10px;">Item Writer Assignments (Questions Creator)</h4>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Exam Name</th>
                        <th>Subject</th>
                        <th>Assigned Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($writer_tasks)): ?>
                        <?php foreach ($writer_tasks as $wt): ?>
                            <tr>
                                <td><?= htmlspecialchars($wt['exam_name']) ?></td>
                                <td><?= htmlspecialchars($wt['subject_name']) ?></td>
                                <td><?= date('d M Y', strtotime($wt['assigned_at'])) ?></td>
                                <td>
                                    <span class="badge badge-<?= strtolower($wt['status'] === 'assigned' ? 'warning' : 'success') ?>">
                                        <?= ucfirst(htmlspecialchars($wt['status'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center" style="padding:15px; color:var(--text-muted);">No item writer tasks assigned.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <h4 style="border-left: 4px solid var(--primary-dark); padding-left: 10px; margin-bottom: 10px;">Moderator Assignments</h4>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Exam Name</th>
                        <th>Subject</th>
                        <th>Assigned Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($moderator_tasks)): ?>
                        <?php foreach ($moderator_tasks as $mt): ?>
                            <tr>
                                <td><?= htmlspecialchars($mt['exam_name']) ?></td>
                                <td><?= htmlspecialchars($mt['subject_name']) ?></td>
                                <td><?= date('d M Y', strtotime($mt['assigned_at'])) ?></td>
                                <td>
                                    <span class="badge badge-<?= strtolower($mt['status'] === 'assigned' ? 'warning' : 'success') ?>">
                                        <?= ucfirst(htmlspecialchars($mt['status'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center" style="padding:15px; color:var(--text-muted);">No moderator tasks assigned.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>
</div>
<?php include '../common/footer.php'; ?>
</body>
</html>
