<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

$exam_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($exam_id <= 0) die("Invalid Exam ID");

/* ═══ EXAM DETAILS ═══ */
$stmt = $conn->prepare("
    SELECT e.*, u.name AS created_by_name
    FROM exams e
    LEFT JOIN users u ON e.created_by = u.user_id
    WHERE e.exam_id = ?
");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) die("Exam not found");

$exam_class = $exam['class'] ?? null;

/* ═══ CANDIDATE COUNT ═══ */
$candidate_count = 0;
if ($exam_class) {
    $cnt = $conn->prepare("SELECT COUNT(*) AS cnt FROM students WHERE class = ? AND status = 'active'");
    $cnt->bind_param("s", $exam_class);
    $cnt->execute();
    $candidate_count = (int)$cnt->get_result()->fetch_assoc()['cnt'];
    $cnt->close();
}

/* ═══ ATTACHED SUBJECTS ═══ */
$stmt = $conn->prepare("
    SELECT es.*, s.subject_name, s.subject_code, s.paper_type
    FROM exam_subjects es
    INNER JOIN subjects s ON es.subject_id = s.subject_id
    WHERE es.exam_id = ?
    ORDER BY s.subject_name ASC
");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam_subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Details – <?= htmlspecialchars($exam['exam_name']) ?> | NED-SEMS</title>
    <?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>
    <style>
        .modal {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.5); z-index: 9999;
        }
        .modal.show { display: block; }
        .modal-content {
            background: #fff; max-width: 420px;
            margin: 8% auto; padding: 24px;
            border-radius: var(--border-radius); text-align: center;
        }

    </style>
</head>
<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
    <?php include '../common/sidebar.php'; ?>

    <div class="content">

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div>
                <h2 class="page-title">Exam Details</h2>
                <p class="page-subtitle">
                    <?= htmlspecialchars($exam['exam_name']) ?>
                    (<?= htmlspecialchars($exam['exam_code'] ?? 'No Code') ?>)
                </p>
            </div>
            <div class="header-actions">
                <a href="exams.php" class="btn btn-secondary btn-small">Back to Exams</a>
            </div>
        </div>

        <!-- EXAM INFO CARD -->
        <div class="card">
            <div class="section-header">
                <h3>Exam Information</h3>
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="badge badge-<?= htmlspecialchars($exam['status']) ?>">
                        <?= ucfirst(htmlspecialchars($exam['status'])) ?>
                    </span>
                    <a href="edit_exam.php?id=<?= $exam_id ?>" class="btn btn-edit btn-small">Edit</a>
                    <button type="button" onclick="openDeleteModal()" class="btn btn-delete btn-small">Delete</button>
                </div>
            </div>

            <div class="insight-grid">
                <div class="insight-card">
                    <h4>General Details</h4>
                    <div class="insight-card__row">
                        <span>Exam Name</span>
                        <strong><?= htmlspecialchars($exam['exam_name'] ?? '—') ?></strong>
                    </div>
                    <div class="insight-card__row">
                        <span>Exam Code</span>
                        <strong><?= htmlspecialchars($exam['exam_code'] ?? '—') ?></strong>
                    </div>
                    <div class="insight-card__row">
                        <span>Year</span>
                        <strong><?= htmlspecialchars($exam['year'] ?? '—') ?></strong>
                    </div>
                    <div class="insight-card__row">
                        <span>Class / Form</span>
                        <strong><?= htmlspecialchars($exam['class'] ?? '—') ?></strong>
                    </div>
                </div>

                <div class="insight-card">
                    <h4>Schedule &amp; Status</h4>
                    <div class="insight-card__row">
                        <span>Start Date</span>
                        <strong><?= htmlspecialchars($exam['start_date'] ?? '—') ?></strong>
                    </div>
                    <div class="insight-card__row">
                        <span>End Date</span>
                        <strong><?= htmlspecialchars($exam['end_date'] ?? '—') ?></strong>
                    </div>
                    <div class="insight-card__row">
                        <span>Status</span>
                        <strong><?= ucfirst(htmlspecialchars($exam['status'])) ?></strong>
                    </div>
                    <div class="insight-card__row">
                        <span>Created By</span>
                        <strong><?= htmlspecialchars($exam['created_by_name'] ?? '—') ?></strong>
                    </div>
                    <div class="insight-card__row">
                        <span>Registered Candidates</span>
                        <strong><?= $candidate_count ?></strong>
                    </div>
                </div>
            </div>
        </div>



        <!-- ATTACHED SUBJECTS TABLE -->
        <div class="card">
            <div class="section-header">
                <h3>Subjects in this Exam
                    <span class="muted-text" style="font-size:.85rem; font-weight:400; margin-left:6px;">
                        (<?= count($exam_subjects) ?> attached)
                    </span>
                </h3>
                <a href="manage_exam_subjects.php?exam_id=<?= $exam_id ?>" class="btn btn-dark btn-small">
                    Manage Subjects
                </a>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Subject Name</th>
                            <th>Code</th>
                            <th>Class</th>
                            <th>Paper Type</th>
                            <th>Duration</th>
                            <th>Total Marks</th>
                            <th>Candidates</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($exam_subjects)): ?>
                            <tr>
                                <td colspan="9" class="empty-state">
                                    No subjects attached yet.
                                    <a href="manage_exam_subjects.php?exam_id=<?= $exam_id ?>">Attach subjects</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $i = 1; foreach ($exam_subjects as $es): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($es['subject_name']) ?></strong></td>
                                <td><?= htmlspecialchars($es['subject_code']) ?></td>
                                <td><?= htmlspecialchars($es['class'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($es['paper_type'] ?? 'Theory') ?></td>
                                <td><?= (int)$es['duration_minutes'] ?> mins</td>
                                <td><?= (int)$es['total_marks'] ?></td>
                                <td><?= (int)$es['registered_candidates'] ?></td>
                                <td>
                                    <span class="badge badge-<?= htmlspecialchars($es['status'] ?? 'active') ?>">
                                        <?= ucfirst(htmlspecialchars($es['status'] ?? 'active')) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- HIDDEN DELETE FORM -->
<form id="deleteForm" method="POST" action="delete_exam.php" style="display:none;">
    <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
</form>

<script>
function openDeleteModal() {
    var modal = document.getElementById('globalDeleteModal');
    var confirmBtn = document.getElementById('globalDeleteConfirmBtn');
    if (!modal || !confirmBtn) return;

    confirmBtn.removeAttribute('href');
    confirmBtn.onclick = function(e) {
        e.preventDefault();
        document.getElementById('deleteForm').submit();
    };
    modal.classList.add('show');
}
</script>

<?php include '../common/footer.php'; ?>

</body>
</html>