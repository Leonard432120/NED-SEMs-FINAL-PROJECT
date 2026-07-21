<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
if ($exam_id <= 0) die("Invalid Exam ID");

/* ═══ EXAM DETAILS ═══ */
$stmt = $conn->prepare("SELECT * FROM exams WHERE exam_id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) die("Exam not found");

$exam_class = $exam['class'] ?? 'Form 4';

$message      = '';
$message_type = '';

/* ═══════════════════════════════════════
   AUTO-SYNC registered_candidates
═══════════════════════════════════════ */
function syncCandidateCount(mysqli $conn, int $exam_id, string $exam_class): int {
    $s = $conn->prepare("SELECT COUNT(*) AS cnt FROM students WHERE class = ? AND status = 'active'");
    $s->bind_param("s", $exam_class);
    $s->execute();
    $count = (int)$s->get_result()->fetch_assoc()['cnt'];
    $s->close();

    $u = $conn->prepare("UPDATE exam_subjects SET registered_candidates = ? WHERE exam_id = ?");
    $u->bind_param("ii", $count, $exam_id);
    $u->execute();
    $u->close();
    return $count;
}

// Sync on every page load
$live_count = syncCandidateCount($conn, $exam_id, $exam_class);

/* ═══════════════════════════════════════
   HANDLE POST
═══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ── ATTACH SINGLE SUBJECT ── */
    if ($action === 'attach') {
        $subject_id       = (int)$_POST['subject_id'];
        $duration_minutes = (int)$_POST['duration_minutes'];
        $total_marks      = (int)$_POST['total_marks'];
        $status           = $_POST['status'] ?? 'active';

        if ($subject_id <= 0) {
            $message = "Please select a valid subject.";
            $message_type = "error";
        } else {
            $chk = $conn->prepare("SELECT id FROM exam_subjects WHERE exam_id = ? AND subject_id = ?");
            $chk->bind_param("ii", $exam_id, $subject_id);
            $chk->execute();
            $exists = $chk->get_result()->num_rows > 0;
            $chk->close();

            if ($exists) {
                $message      = "This subject is already attached to this exam.";
                $message_type = "error";
            } else {
                $ins = $conn->prepare("
                    INSERT INTO exam_subjects
                        (exam_id, subject_id, class, duration_minutes, total_marks, registered_candidates, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $ins->bind_param("iisiiis", $exam_id, $subject_id, $exam_class, $duration_minutes, $total_marks, $live_count, $status);
                if ($ins->execute()) {
                    $message      = "Subject attached successfully.";
                    $message_type = "success";
                } else {
                    $message      = "Failed to attach subject: " . $conn->error;
                    $message_type = "error";
                }
                $ins->close();
            }
        }
    }

    /* ── BULK ATTACH ALL SUBJECTS ── */
    elseif ($action === 'bulk_attach') {
        $duration_minutes = (int)($_POST['bulk_duration'] ?? 120);
        $total_marks      = (int)($_POST['bulk_marks']    ?? 100);

        $res = $conn->query("
            SELECT subject_id FROM subjects
            WHERE status = 'active'
              AND subject_id NOT IN (SELECT subject_id FROM exam_subjects WHERE exam_id = {$exam_id})
        ");
        $to_attach = $res->fetch_all(MYSQLI_ASSOC);

        if (empty($to_attach)) {
            $message      = "All active subjects are already attached to this exam.";
            $message_type = "error";
        } else {
            $ins = $conn->prepare("
                INSERT INTO exam_subjects
                    (exam_id, subject_id, class, duration_minutes, total_marks, registered_candidates, status)
                VALUES (?, ?, ?, ?, ?, ?, 'active')
            ");
            $attached = 0;
            foreach ($to_attach as $s) {
                $sid = $s['subject_id'];
                $ins->bind_param("iisiis", $exam_id, $sid, $exam_class, $duration_minutes, $total_marks, $live_count);
                if ($ins->execute()) $attached++;
            }
            $ins->close();
            $message      = "{$attached} subject(s) attached successfully.";
            $message_type = "success";
        }
    }

    /* ── UPDATE SUBJECT CONFIG ── */
    elseif ($action === 'update') {
        $assoc_id         = (int)$_POST['assoc_id'];
        $duration_minutes = (int)$_POST['duration_minutes'];
        $total_marks      = (int)$_POST['total_marks'];
        $status           = $_POST['status'] ?? 'active';

        $upd = $conn->prepare("
            UPDATE exam_subjects
            SET class = ?, duration_minutes = ?, total_marks = ?, registered_candidates = ?, status = ?
            WHERE id = ? AND exam_id = ?
        ");
        $upd->bind_param("siiisii", $exam_class, $duration_minutes, $total_marks, $live_count, $status, $assoc_id, $exam_id);
        if ($upd->execute()) {
            $message      = "Subject configuration updated.";
            $message_type = "success";
        } else {
            $message      = "Failed to update: " . $conn->error;
            $message_type = "error";
        }
        $upd->close();
    }

    /* ── DETACH SUBJECT ── */
    elseif ($action === 'detach') {
        $assoc_id = (int)$_POST['assoc_id'];
        $del = $conn->prepare("DELETE FROM exam_subjects WHERE id = ? AND exam_id = ?");
        $del->bind_param("ii", $assoc_id, $exam_id);
        if ($del->execute()) {
            $message      = "Subject detached.";
            $message_type = "success";
        } else {
            $message      = "Failed to detach subject.";
            $message_type = "error";
        }
        $del->close();
    }

    // Re-sync after action
    $live_count = syncCandidateCount($conn, $exam_id, $exam_class);
}

/* ═══════════════════════════════════════
   FETCH SUBJECT DATA
═══════════════════════════════════════ */
// Active subjects NOT yet attached
$active_subjects = [];
$res = $conn->query("
    SELECT subject_id, subject_name, subject_code
    FROM subjects
    WHERE status = 'active'
      AND subject_id NOT IN (SELECT subject_id FROM exam_subjects WHERE exam_id = {$exam_id})
    ORDER BY subject_name ASC
");
while ($row = $res->fetch_assoc()) $active_subjects[] = $row;

// Attached subjects
$stmt = $conn->prepare("
    SELECT es.*, s.subject_name, s.subject_code, s.paper_type
    FROM exam_subjects es
    INNER JOIN subjects s ON es.subject_id = s.subject_id
    WHERE es.exam_id = ?
    ORDER BY s.subject_name ASC
");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$attached_subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects – <?= htmlspecialchars($exam['exam_name']) ?> | NED-SEMS</title>
    <?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>
    <style>
        /* Form grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
        }
        /* Modal */
        .modal {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.5); z-index: 9999;
        }
        .modal.show { display: block; }
       .modal-content {
        background: #fff; max-width: 480px;
        margin: 4% auto; padding: 24px;
        border-radius: var(--border-radius);
        max-height: 88vh;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }
    .modal-content form {
        display: flex;
        flex-direction: column;
        min-height: 0;
    }
    .modal-actions {
        position: sticky;
        bottom: -24px;
        background: #fff;
        padding: 14px 0 4px;
        margin: 0 -24px -24px;
        padding-left: 24px;
        padding-right: 24px;
        border-top: 1px solid var(--border-color);
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
        /* Bulk row */
        .bulk-row {
            display: flex; flex-wrap: wrap; align-items: flex-end; gap: 14px;
        }
        .bulk-row .form-group { flex: 1; min-width: 130px; }
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
                <h2 class="page-title">Manage Subjects</h2>
                <p class="page-subtitle">
                    <?= htmlspecialchars($exam['exam_name']) ?>
                    (<?= htmlspecialchars($exam['exam_code']) ?>)
                    &mdash; <?= htmlspecialchars($exam_class) ?>
                    &mdash; <?= $live_count ?> registered candidate(s)
                </p>
            </div>
            <div class="header-actions">
                <a href="manage_candidates.php?exam_id=<?= $exam_id ?>" class="btn btn-secondary btn-small">
                    Manage Candidates
                </a>
                <a href="view_exam.php?id=<?= $exam_id ?>" class="btn btn-secondary btn-small">Back to Exam</a>
            </div>
        </div>

        <!-- ALERTS -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- ATTACH SUBJECT FORM -->
        <div class="card">
            <div class="section-header">
                <h3>Attach Subject to Exam</h3>
            </div>

            <?php if (empty($active_subjects)): ?>
                <p class="empty-state">All active subjects are already attached to this exam.</p>
            <?php else: ?>

                <!-- BULK ATTACH -->
                <div class="section" style="margin-bottom: 20px;">
                    <h3>Bulk Attach — Add All Remaining Subjects at Once</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="bulk_attach">
                        <div class="bulk-row">
                            <div class="form-group">
                                <label>Default Duration (minutes)</label>
                                <input type="number" name="bulk_duration" value="120" min="1" required>
                            </div>
                            <div class="form-group">
                                <label>Default Total Marks</label>
                                <input type="number" name="bulk_marks" value="100" min="1" required>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-dark"
                                    onclick="return confirm('Attach all <?= count($active_subjects) ?> remaining subject(s)?');">
                                    Attach All <?= count($active_subjects) ?> Subject(s)
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- SINGLE SUBJECT ATTACH -->
                <form method="POST">
                    <input type="hidden" name="action" value="attach">

                    <div class="form-grid" style="margin-bottom: 14px;">
                        <div class="form-group">
                            <label>Select Subject <span style="color:var(--danger-color)">*</span></label>
                            <select name="subject_id" required>
                                <option value="">— Choose Subject —</option>
                                <?php foreach ($active_subjects as $as): ?>
                                    <option value="<?= $as['subject_id'] ?>">
                                        <?= htmlspecialchars($as['subject_name']) ?>
                                        (<?= htmlspecialchars($as['subject_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Class / Form</label>
                            <select name="class">
                                <option value="Form 2" <?= $exam_class === 'Form 2' ? 'selected' : '' ?>>Form 2</option>
                                <option value="Form 4" <?= $exam_class === 'Form 4' ? 'selected' : '' ?>>Form 4</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Duration (minutes) <span style="color:var(--danger-color)">*</span></label>
                            <input type="number" name="duration_minutes" value="120" min="1" required>
                        </div>

                        <div class="form-group">
                            <label>Total Marks <span style="color:var(--danger-color)">*</span></label>
                            <input type="number" name="total_marks" value="100" min="1" required>
                        </div>

                        <div class="form-group">
                            <label>Registered Candidates</label>
                            <input type="text" value="<?= $live_count ?>" readonly
                                   style="background:#f8fafc; color:var(--text-muted);">
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-dark">Attach Subject</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <!-- ATTACHED SUBJECTS TABLE -->
        <div class="card">
            <div class="section-header">
                <h3>Attached Subjects
                    <span class="muted-text" style="font-size:.85rem; font-weight:400; margin-left:6px;">
                        (<?= count($attached_subjects) ?> total)
                    </span>
                </h3>
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
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attached_subjects)): ?>
                            <tr>
                                <td colspan="10" class="empty-state">No subjects attached yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php $i = 1; foreach ($attached_subjects as $subj): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($subj['subject_name']) ?></strong></td>
                                <td><?= htmlspecialchars($subj['subject_code']) ?></td>
                                <td><?= htmlspecialchars($subj['class'] ?? $exam_class) ?></td>
                                <td><?= htmlspecialchars($subj['paper_type'] ?? 'Theory') ?></td>
                                <td><?= (int)$subj['duration_minutes'] ?> mins</td>
                                <td><?= (int)$subj['total_marks'] ?></td>
                                <td><?= (int)$subj['registered_candidates'] ?></td>
                                <td>
                                    <span class="badge badge-<?= htmlspecialchars($subj['status']) ?>">
                                        <?= ucfirst(htmlspecialchars($subj['status'])) ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <button type="button" class="btn btn-edit btn-small"
                                        onclick="openEditModal(<?= htmlspecialchars(json_encode($subj)) ?>)">
                                        Edit
                                    </button>
                                    <form method="POST" style="display:inline;"
                                          onsubmit="return confirm('Detach this subject from the exam?');">
                                        <input type="hidden" name="action" value="detach">
                                        <input type="hidden" name="assoc_id" value="<?= $subj['id'] ?>">
                                        <button type="submit" class="btn btn-delete btn-small">Detach</button>
                                    </form>
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

<!-- EDIT MODAL -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <h3>Edit Subject Configuration</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="assoc_id" id="edit_assoc_id">

            <div class="form-group" style="margin-bottom:14px;">
                <label>Subject</label>
                <input type="text" id="edit_subject_name" readonly
                       style="background:#f8fafc; color:var(--text-muted);">
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label>Class / Form</label>
                <select name="class">
                    <option value="Form 2" <?= $exam_class === 'Form 2' ? 'selected' : '' ?>>Form 2</option>
                    <option value="Form 4" <?= $exam_class === 'Form 4' ? 'selected' : '' ?>>Form 4</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label>Duration (minutes) <span style="color:var(--danger-color)">*</span></label>
                <input type="number" name="duration_minutes" id="edit_duration" min="1" required>
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label>Total Marks <span style="color:var(--danger-color)">*</span></label>
                <input type="number" name="total_marks" id="edit_marks" min="1" required>
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label>Registered Candidates <span class="muted-text">(auto-calculated)</span></label>
                <input type="text" value="<?= $live_count ?>" readonly
                       style="background:#f8fafc; color:var(--text-muted);">
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label>Status</label>
                <select name="status" id="edit_status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-dark">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(subj) {
    document.getElementById('edit_assoc_id').value    = subj.id;
    document.getElementById('edit_subject_name').value = subj.subject_name + ' (' + subj.subject_code + ')';
    document.getElementById('edit_duration').value    = subj.duration_minutes || 120;
    document.getElementById('edit_marks').value       = subj.total_marks || 100;
    document.getElementById('edit_status').value      = subj.status || 'active';
    document.getElementById('editModal').classList.add('show');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>

<?php include '../common/footer.php'; ?>

</body>
</html>
