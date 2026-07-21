<?php
/* ════════════════════════════════════════════════════════════════
   admin/timetable_edit.php
   Edit an existing timetable — header + sessions
   Access: admin only
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit();
}

$conn = get_db_connection();
function safe($v) { return htmlspecialchars($v ?? ''); }

$tid = (int)($_GET['id'] ?? 0);
if ($tid <= 0) { header('Location: timetable.php'); exit(); }

/* ── Load timetable ── */
$st = $conn->prepare('SELECT t.*, e.exam_name, e.year, e.class AS exam_class FROM timetables t JOIN exams e ON e.exam_id=t.exam_id WHERE t.timetable_id=?');
$st->bind_param('i', $tid); $st->execute();
$timetable = $st->get_result()->fetch_assoc(); $st->close();
if (!$timetable) { header('Location: timetable.php'); exit(); }

/* ── Load existing sessions ── */
$ss = $conn->prepare('SELECT ts.*, s.subject_name FROM timetable_sessions ts LEFT JOIN subjects s ON s.subject_id=ts.subject_id WHERE ts.timetable_id=? ORDER BY ts.exam_date ASC, ts.start_time ASC');
$ss->bind_param('i', $tid); $ss->execute();
$sessions = $ss->get_result()->fetch_all(MYSQLI_ASSOC); $ss->close();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_id       = (int)($_POST['exam_id'] ?? 0);
    $instructions  = trim($_POST['instructions'] ?? '');
    $special_notes = trim($_POST['special_notes'] ?? '');
    $status        = in_array($_POST['status'] ?? '', ['draft','published','archived']) ? $_POST['status'] : 'draft';

    if ($exam_id <= 0) $errors[] = 'Please select an examination.';

    $session_dates    = $_POST['exam_date']    ?? [];
    $session_starts   = $_POST['start_time']   ?? [];
    $session_ends     = $_POST['end_time']      ?? [];
    $session_subjects = $_POST['subject_id']    ?? [];
    $session_papers   = $_POST['paper_number']  ?? [];

    if (empty($session_dates)) $errors[] = 'Add at least one session.';

    if (empty($errors)) {
        /* Update header */
        $up = $conn->prepare('UPDATE timetables SET exam_id=?, instructions=?, special_notes=?, status=?, updated_at=NOW() WHERE timetable_id=?');
        $up->bind_param('isssi', $exam_id, $instructions, $special_notes, $status, $tid);
        $up->execute(); $up->close();

        /* Replace sessions */
        $conn->query("DELETE FROM timetable_sessions WHERE timetable_id=$tid");
        $ins = $conn->prepare('INSERT INTO timetable_sessions (timetable_id, subject_id, paper_number, exam_date, start_time, end_time, duration_minutes) VALUES (?,?,?,?,?,?,?)');
        foreach ($session_dates as $k => $date) {
            if (empty($date)) continue;
            $sid  = !empty($session_subjects[$k]) ? (int)$session_subjects[$k] : null;
            $pnum = max(1, (int)($session_papers[$k] ?? 1));
            $st2  = $session_starts[$k] ?? '08:00';
            $en   = $session_ends[$k]   ?? '10:00';
            $dur  = max(0, (int)round((strtotime($en) - strtotime($st2)) / 60));
            $ins->bind_param('iiisssi', $tid, $sid, $pnum, $date, $st2, $en, $dur);
            $ins->execute();
        }
        $ins->close();

        log_audit_event('TIMETABLE_EDIT', ['timetable_id' => $tid, 'status' => $status], null, $conn);
        $_SESSION['message'] = 'Timetable updated successfully.';
        $_SESSION['message_type'] = 'success';
        header('Location: timetable.php'); exit();
    }
}

$exams    = $conn->query('SELECT exam_id, exam_name, year, class FROM exams ORDER BY year DESC, exam_name ASC')->fetch_all(MYSQLI_ASSOC);
$subjects = $conn->query('SELECT subject_id, subject_name, subject_code FROM subjects ORDER BY subject_name ASC')->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Timetable | NED-SEMS</title>
    <?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>
    <style>
        .session-row { display:grid; grid-template-columns:1.5fr 1fr 1fr 1.5fr 0.5fr 0.5fr; gap:10px; align-items:end; margin-bottom:12px; padding:14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; }
        .session-row label { font-size:0.78rem; font-weight:600; color:var(--secondary-color); margin-bottom:4px; display:block; }
        .session-row input, .session-row select { padding:9px 12px; border:1px solid #e2e8f0; border-radius:8px; font-size:0.875rem; width:100%; font-family:inherit; }
        .rm-session { background:var(--danger-color); color:#fff; border:none; border-radius:8px; padding:9px 14px; cursor:pointer; font-weight:600; align-self:end; }
        .add-session-btn { background:#eff6ff; color:#1d4ed8; border:2px dashed #bfdbfe; width:100%; padding:14px; border-radius:10px; font-weight:700; font-size:0.9rem; cursor:pointer; margin-top:8px; transition:background .2s; }
        .add-session-btn:hover { background:#dbeafe; }
        #sessions-container { margin-top:16px; }
    </style>
</head>
<body>
<?php include '../common/header.php'; ?>

<div class="dashboard">
    <?php include '../common/sidebar.php'; ?>

    <div class="main-content">

        <div class="page-header">
            <div>
                <h2 class="page-title">Edit Timetable</h2>
                <p class="page-subtitle"><?= safe($timetable['exam_name']) ?> — <?= safe($timetable['year']) ?></p>
            </div>
            <div class="header-actions">
                <a href="timetable_view.php?id=<?= $tid ?>" class="btn btn-secondary">&#128065; Preview</a>
                <a href="timetable.php" class="btn btn-secondary">&larr; Back</a>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin:0;padding-left:18px;"><?php foreach ($errors as $e): ?><li><?= safe($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST" id="timetable-form">

            <div class="card">
                <h3 style="margin-bottom:20px;">&#128203; Timetable Details</h3>
                <div class="panel-grid" style="grid-template-columns:1fr 1fr; gap:20px;">
                    <div class="form-group">
                        <label for="exam_id">Examination *</label>
                        <select name="exam_id" id="exam_id" required>
                            <option value="">-- Select Examination --</option>
                            <?php foreach ($exams as $ex): ?>
                                <option value="<?= $ex['exam_id'] ?>"
                                    <?= (int)($timetable['exam_id']) === (int)$ex['exam_id'] ? 'selected' : '' ?>>
                                    <?= safe($ex['exam_name']) ?> — <?= safe($ex['year']) ?> (<?= safe($ex['class']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="draft"     <?= $timetable['status'] === 'draft'     ? 'selected' : '' ?>>Draft</option>
                            <option value="published" <?= $timetable['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                            <option value="archived"  <?= $timetable['status'] === 'archived'  ? 'selected' : '' ?>>Archived</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="instructions">General Instructions</label>
                    <textarea name="instructions" id="instructions" rows="4"><?= safe($timetable['instructions']) ?></textarea>
                </div>
                <div class="form-group">
                    <label for="special_notes">Special Notes</label>
                    <textarea name="special_notes" id="special_notes" rows="3"><?= safe($timetable['special_notes']) ?></textarea>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-bottom:4px;">&#128197; Examination Sessions</h3>
                <p class="muted-text" style="margin-bottom:18px;">Edit sessions below. Duration is calculated automatically.</p>
                <div id="sessions-container"></div>
                <button type="button" class="add-session-btn" id="add-row">+ Add Session Row</button>
            </div>

            <div class="action-buttons">
                <button type="submit" class="btn btn-dark btn-create">Save Changes</button>
                <a href="timetable.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

    </div>
</div>

<?php include '../common/footer.php'; ?>

<script>
const subjects = <?= json_encode($subjects) ?>;
const existing = <?= json_encode($sessions) ?>;
let rowIdx = 0;

function buildSubjectOptions(selected) {
    let opts = '<option value="">-- Subject (optional) --</option>';
    subjects.forEach(s => {
        opts += `<option value="${s.subject_id}" ${selected == s.subject_id ? 'selected' : ''}>${s.subject_name} (${s.subject_code})</option>`;
    });
    return opts;
}

function addRow(data = {}) {
    const i = rowIdx++;
    const div = document.createElement('div');
    div.className = 'session-row';
    div.id = `row-${i}`;
    div.innerHTML = `
        <div>
            <label>Subject</label>
            <select name="subject_id[]">${buildSubjectOptions(data.subject_id || '')}</select>
        </div>
        <div>
            <label>Paper #</label>
            <select name="paper_number[]">
                <option value="1" ${(data.paper_number||1)==1?'selected':''}>Paper 1</option>
                <option value="2" ${(data.paper_number||1)==2?'selected':''}>Paper 2</option>
                <option value="3" ${(data.paper_number||1)==3?'selected':''}>Paper 3</option>
            </select>
        </div>
        <div>
            <label>Exam Date *</label>
            <input type="date" name="exam_date[]" value="${data.exam_date||''}" required>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            <div>
                <label>Start Time *</label>
                <input type="time" name="start_time[]" value="${data.start_time||'08:00'}" required>
            </div>
            <div>
                <label>End Time *</label>
                <input type="time" name="end_time[]" value="${data.end_time||'10:00'}" required>
            </div>
        </div>
        <div></div>
        <div style="display:flex;align-items:flex-end;">
            <button type="button" class="rm-session" onclick="document.getElementById('row-${i}').remove()">&times;</button>
        </div>
    `;
    document.getElementById('sessions-container').appendChild(div);
}

document.getElementById('add-row').addEventListener('click', () => addRow());
// Load existing sessions
if (existing.length > 0) {
    existing.forEach(s => addRow(s));
} else {
    addRow();
}
</script>
</body>
</html>
