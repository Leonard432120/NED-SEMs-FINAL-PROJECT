<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'examination_officer') {
    header("Location: ../login.php");
    exit();
}

$conn      = get_db_connection();
$school_id = (int)($_SESSION['school_id'] ?? 0);
if ($school_id <= 0) {
    $module_css = 'exam_officer';
    include __DIR__ . '/../common/head_assets.php';
    include '../common/header.php';
    echo '<div class="dashboard">';
    include '../common/sidebar.php';
    echo '<div class="content"><div class="alert alert-error" style="margin-top:30px;">';
    echo '<strong>No school assigned to your account.</strong><br>';
    echo 'Please contact the system administrator to link your account to a school before using this feature.';
    echo '</div></div></div>';
    include '../common/footer.php';
    exit();
}

/* ═══ SCHOOL ═══ */
$sc = $conn->prepare("SELECT school_name FROM schools WHERE school_id = ?");
$sc->bind_param("i", $school_id);
$sc->execute();
$school = $sc->get_result()->fetch_assoc();
$sc->close();

/* ═══ SELECTED EXAM ═══ */
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

$message      = '';
$message_type = '';

/* ═══════════════════════════════════════
   HANDLE POST
═══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ── REGISTER STUDENT TO EXAM (set their class to exam's class) ── */
    if ($action === 'register') {
        $student_id = (int)$_POST['student_id'];
        $eid        = (int)$_POST['exam_id'];

        // Get exam class
        $ex = $conn->prepare("SELECT class FROM exams WHERE exam_id = ?");
        $ex->bind_param("i", $eid);
        $ex->execute();
        $ex_row = $ex->get_result()->fetch_assoc();
        $ex->close();

        if ($ex_row && $ex_row['class']) {
            $eclass = $ex_row['class'];
            $u = $conn->prepare("UPDATE students SET class = ? WHERE student_id = ? AND school_id = ?");
            $u->bind_param("sii", $eclass, $student_id, $school_id);
            $u->execute(); $u->close();

            // Sync registered_candidates in exam_subjects
            $cnt = $conn->prepare("SELECT COUNT(*) AS c FROM students WHERE class = ? AND status = 'active'");
            $cnt->bind_param("s", $eclass);
            $cnt->execute();
            $count = (int)$cnt->get_result()->fetch_assoc()['c'];
            $cnt->close();
            $upd = $conn->prepare("UPDATE exam_subjects SET registered_candidates = ? WHERE exam_id = ?");
            $upd->bind_param("ii", $count, $eid);
            $upd->execute(); $upd->close();

            $message = "Student registered for exam successfully.";
            $message_type = "success";
        } else {
            $message = "Exam class not found.";
            $message_type = "error";
        }
    }

    /* ── WITHDRAW STUDENT FROM EXAM (set status inactive) ── */
    if ($action === 'withdraw') {
        $student_id = (int)$_POST['student_id'];
        $eid        = (int)$_POST['exam_id'];
        $u = $conn->prepare("UPDATE students SET status = 'inactive' WHERE student_id = ? AND school_id = ?");
        $u->bind_param("ii", $student_id, $school_id);
        $u->execute(); $u->close();

        // Re-sync
        $ex = $conn->prepare("SELECT class FROM exams WHERE exam_id = ?");
        $ex->bind_param("i", $eid);
        $ex->execute();
        $ex_row = $ex->get_result()->fetch_assoc();
        $ex->close();
        if ($ex_row && $ex_row['class']) {
            $eclass = $ex_row['class'];
            $cnt = $conn->query("SELECT COUNT(*) AS c FROM students WHERE class = '{$eclass}' AND status = 'active'");
            $count = (int)$cnt->fetch_assoc()['c'];
            $upd = $conn->prepare("UPDATE exam_subjects SET registered_candidates = ? WHERE exam_id = ?");
            $upd->bind_param("ii", $count, $eid);
            $upd->execute(); $upd->close();
        }

        $message = "Student withdrawn from exam.";
        $message_type = "success";
    }

    header("Location: manage_exam_candidates.php?exam_id=" . (int)$_POST['exam_id']
        . "&msg=" . urlencode($message)
        . "&mt=" . urlencode($message_type));
    exit();
}

if (isset($_GET['msg']) && $_GET['msg'] !== '') {
    $message      = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['mt'] ?? 'success');
}

/* ═══ ALL ACTIVE EXAMS ═══ */
$exams = $conn->query("SELECT exam_id, exam_name, exam_code, class, year FROM exams WHERE status != 'completed' ORDER BY exam_id DESC")->fetch_all(MYSQLI_ASSOC);

/* ═══ SELECTED EXAM DETAILS ═══ */
$selected_exam  = null;
$exam_class     = null;
$registered     = [];
$not_registered = [];

if ($exam_id > 0) {
    $ex = $conn->prepare("SELECT * FROM exams WHERE exam_id = ?");
    $ex->bind_param("i", $exam_id);
    $ex->execute();
    $selected_exam = $ex->get_result()->fetch_assoc();
    $ex->close();

    if ($selected_exam) {
        $exam_class = $selected_exam['class'];

        // Students from THIS school already in this exam's class
        $r = $conn->prepare("SELECT * FROM students WHERE school_id = ? AND class = ? ORDER BY name ASC");
        $r->bind_param("is", $school_id, $exam_class);
        $r->execute();
        $registered = $r->get_result()->fetch_all(MYSQLI_ASSOC);
        $r->close();

        // Students from THIS school NOT in this exam's class (can be registered)
        $nr = $conn->prepare("SELECT * FROM students WHERE school_id = ? AND (class IS NULL OR class != ?) AND status = 'active' ORDER BY name ASC");
        $nr->bind_param("is", $school_id, $exam_class);
        $nr->execute();
        $not_registered = $nr->get_result()->fetch_all(MYSQLI_ASSOC);
        $nr->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Candidates | NED-SEMS</title>
    <?php $module_css = 'exam_officer'; include __DIR__ . '/../common/head_assets.php'; ?>
    <style>
        .kpi-bar { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:22px; }
        .kpi-tile { background:var(--card-color); border:1px solid var(--border-color); border-radius:var(--border-radius-lg); padding:15px 17px; box-shadow:var(--box-shadow); }
        .kpi-tile .kpi-label { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted); margin-bottom:5px; }
        .kpi-tile .kpi-value { font-size:1.8rem; font-weight:800; color:#0f172a; line-height:1; }
        .kpi-tile .kpi-hint  { font-size:.72rem; color:var(--text-muted); margin-top:4px; }
        .kpi-tile.kpi-blue  { border-left:4px solid var(--info-color); }
        .kpi-tile.kpi-green { border-left:4px solid var(--success-color); }
        .kpi-tile.kpi-red   { border-left:4px solid var(--danger-color); }
        @media(max-width:600px){ .kpi-bar{grid-template-columns:1fr;} }
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
                <h2 class="page-title">Exam Candidates</h2>
                <p class="page-subtitle">
                    <?= htmlspecialchars($school['school_name'] ?? 'Your School') ?>
                    — Register your students for specific examinations
                </p>
            </div>
            <div class="header-actions">
                <a href="manage_students.php" class="btn btn-secondary btn-small">All Students</a>
            </div>
        </div>

        <!-- FLASH -->
        <?php if ($message): ?>
            <div class="alert alert-<?= htmlspecialchars($message_type) ?>"><?= $message ?></div>
        <?php endif; ?>

        <!-- SELECT EXAM -->
        <div class="card">
            <div class="section-header"><h3>Select Examination</h3></div>
            <form method="GET" class="search-form">
                <select name="exam_id" style="flex:1; min-width:240px;">
                    <option value="">— Choose an Exam —</option>
                    <?php foreach ($exams as $e): ?>
                        <option value="<?= $e['exam_id'] ?>" <?= $exam_id === (int)$e['exam_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['exam_name']) ?>
                            (<?= htmlspecialchars($e['exam_code']) ?>)
                            — <?= htmlspecialchars($e['class'] ?? '?') ?>
                            <?= htmlspecialchars($e['year'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-dark">Load Exam</button>
            </form>
        </div>

        <?php if ($selected_exam && $exam_class): ?>

        <!-- KPI -->
        <div class="kpi-bar">
            <div class="kpi-tile kpi-blue">
                <div class="kpi-label">Registered</div>
                <div class="kpi-value"><?= count(array_filter($registered, fn($r) => $r['status'] === 'active')) ?></div>
                <div class="kpi-hint">Active in <?= htmlspecialchars($exam_class) ?></div>
            </div>
            <div class="kpi-tile kpi-green">
                <div class="kpi-label">Can Register</div>
                <div class="kpi-value"><?= count($not_registered) ?></div>
                <div class="kpi-hint">Not yet assigned</div>
            </div>
            <div class="kpi-tile kpi-red">
                <div class="kpi-label">Withdrawn</div>
                <div class="kpi-value"><?= count(array_filter($registered, fn($r) => $r['status'] === 'inactive')) ?></div>
                <div class="kpi-hint">Inactive</div>
            </div>
        </div>

        <!-- REGISTERED STUDENTS -->
        <div class="card">
            <div class="section-header">
                <h3>Students Registered for <?= htmlspecialchars($selected_exam['exam_name']) ?></h3>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Exam Number</th>
                            <th>Class</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($registered)): ?>
                            <tr><td colspan="6" class="empty-state">No students registered for this exam yet.</td></tr>
                        <?php else: ?>
                            <?php $i = 1; foreach ($registered as $s): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                                <td><?= htmlspecialchars($s['exam_number']) ?></td>
                                <td><?= htmlspecialchars($s['class'] ?? '—') ?></td>
                                <td>
                                    <span class="badge badge-<?= $s['status'] === 'active' ? 'active' : 'inactive' ?>">
                                        <?= ucfirst($s['status']) ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <?php if ($s['status'] === 'active'): ?>
                                    <form method="POST" style="display:inline;"
                                          onsubmit="return confirm('Withdraw this student from the exam?');">
                                        <input type="hidden" name="action" value="withdraw">
                                        <input type="hidden" name="student_id" value="<?= $s['student_id'] ?>">
                                        <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
                                        <button type="submit" class="btn btn-delete btn-small">Withdraw</button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="register">
                                        <input type="hidden" name="student_id" value="<?= $s['student_id'] ?>">
                                        <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
                                        <button type="submit" class="btn btn-edit btn-small">Re-register</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- STUDENTS NOT YET REGISTERED -->
        <?php if (!empty($not_registered)): ?>
        <div class="card">
            <div class="section-header">
                <h3>Register Additional Students</h3>
            </div>
            <p class="muted-text" style="margin-bottom:14px;">
                These students from your school are not yet registered for <?= htmlspecialchars($selected_exam['exam_name']) ?> (<?= htmlspecialchars($exam_class) ?>).
            </p>
            <input type="text" id="unregSearch" placeholder="Search by name or exam number…"
                   onkeyup="filterUnreg(this.value)"
                   style="width:100%;padding:10px 14px;border:1px solid var(--border-color);border-radius:var(--border-radius-lg);font-size:.875rem;margin-bottom:14px;">
            <div class="table-container">
                <table id="unregTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Exam Number</th>
                            <th>Current Class</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($not_registered as $s): ?>
                        <tr class="unreg-row">
                            <td><?= $i++ ?></td>
                            <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                            <td><?= htmlspecialchars($s['exam_number']) ?></td>
                            <td><?= htmlspecialchars($s['class'] ?? '—') ?></td>
                            <td>
                                <form method="POST" style="display:inline;"
                                      onsubmit="return confirm('Register this student for <?= htmlspecialchars($selected_exam['exam_name']) ?>?');">
                                    <input type="hidden" name="action" value="register">
                                    <input type="hidden" name="student_id" value="<?= $s['student_id'] ?>">
                                    <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
                                    <button type="submit" class="btn btn-dark btn-small">Register</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="card">
            <p class="empty-state">Select an examination above to manage candidates.</p>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
function filterUnreg(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#unregTable .unreg-row').forEach(function(r) {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>

<?php include '../common/footer.php'; ?>
</body>
</html>
