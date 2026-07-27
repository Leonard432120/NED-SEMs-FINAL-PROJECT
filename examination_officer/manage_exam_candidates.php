<?php
session_start();
require_once '../config/db.php';
require_once '../common/pagination_helper.php';

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
   HANDLE POST ACTIONS
═══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ── RE-REGISTER STUDENT TO EXAM ── */
    if ($action === 'register') {
        $student_id = (int)$_POST['student_id'];
        $eid        = (int)$_POST['exam_id'];

        $u = $conn->prepare("UPDATE students SET status = 'active' WHERE student_id = ? AND school_id = ?");
        $u->bind_param("ii", $student_id, $school_id);
        $u->execute(); $u->close();

        // Re-sync registered_candidates in exam_subjects
        $ex = $conn->prepare("SELECT class FROM exams WHERE exam_id = ?");
        $ex->bind_param("i", $eid);
        $ex->execute();
        $ex_row = $ex->get_result()->fetch_assoc();
        $ex->close();

        if ($ex_row && $ex_row['class']) {
            $eclass = $ex_row['class'];
            $cnt = $conn->prepare("SELECT COUNT(*) AS c FROM students WHERE school_id = ? AND class = ? AND status = 'active'");
            $cnt->bind_param("is", $school_id, $eclass);
            $cnt->execute();
            $count = (int)$cnt->get_result()->fetch_assoc()['c'];
            $cnt->close();

            $upd = $conn->prepare("UPDATE exam_subjects SET registered_candidates = ? WHERE exam_id = ?");
            $upd->bind_param("ii", $count, $eid);
            $upd->execute(); $upd->close();
        }

        $message = "Student re-registered for exam successfully.";
        $message_type = "success";
    }

    /* ── WITHDRAW STUDENT FROM EXAM ── */
    if ($action === 'withdraw') {
        $student_id = (int)$_POST['student_id'];
        $eid        = (int)$_POST['exam_id'];

        $u = $conn->prepare("UPDATE students SET status = 'inactive' WHERE student_id = ? AND school_id = ?");
        $u->bind_param("ii", $student_id, $school_id);
        $u->execute(); $u->close();

        // Re-sync registered_candidates
        $ex = $conn->prepare("SELECT class FROM exams WHERE exam_id = ?");
        $ex->bind_param("i", $eid);
        $ex->execute();
        $ex_row = $ex->get_result()->fetch_assoc();
        $ex->close();

        if ($ex_row && $ex_row['class']) {
            $eclass = $ex_row['class'];
            $cnt = $conn->prepare("SELECT COUNT(*) AS c FROM students WHERE school_id = ? AND class = ? AND status = 'active'");
            $cnt->bind_param("is", $school_id, $eclass);
            $cnt->execute();
            $count = (int)$cnt->get_result()->fetch_assoc()['c'];
            $cnt->close();

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
$per_page       = 5;
$reg_page       = max(1, (int)($_GET['rp'] ?? 1));

if ($exam_id > 0) {
    $ex = $conn->prepare("SELECT * FROM exams WHERE exam_id = ?");
    $ex->bind_param("i", $exam_id);
    $ex->execute();
    $selected_exam = $ex->get_result()->fetch_assoc();
    $ex->close();

    if ($selected_exam) {
        $exam_class = $selected_exam['class'];

        // Students from THIS school in this exam's class
        $r_all = $conn->prepare("SELECT * FROM students WHERE school_id = ? AND class = ? ORDER BY name ASC");
        $r_all->bind_param("is", $school_id, $exam_class);
        $r_all->execute();
        $all_registered = $r_all->get_result()->fetch_all(MYSQLI_ASSOC);
        $r_all->close();

        $active_count    = count(array_filter($all_registered, fn($r) => $r['status'] === 'active'));
        $withdrawn_count = count(array_filter($all_registered, fn($r) => $r['status'] === 'inactive'));
        $total_registered= count($all_registered);

        // Paginate candidates
        $reg_pagination = paginate($total_registered, $reg_page, $per_page);
        $reg_offset     = ($reg_pagination['page'] - 1) * $per_page;
        $registered     = array_slice($all_registered, $reg_offset, $per_page);
    }
}

$conn->close();

function render_cand_pagination(array $pag, int $exam_id, string $param, int $total_rows): string {
    if ($pag['total_pages'] <= 1) return '';
    $page  = $pag['page'];
    $total = $pag['total_pages'];
    $per   = $pag['per_page'];
    $offset= ($page - 1) * $per;

    $html  = '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-top:20px;padding-top:14px;border-top:1px solid var(--border-color);">';
    $html .= '<div style="font-size:.82rem;color:var(--text-muted);">';
    $html .= 'Showing <strong style="color:var(--text-color);">' . ($offset + 1) . '</strong>';
    $html .= '&ndash;<strong style="color:var(--text-color);">' . min($offset + $per, $total_rows) . '</strong>';
    $html .= ' of <strong style="color:var(--text-color);">' . $total_rows . '</strong> candidates';
    $html .= '</div>';

    $html .= '<div style="display:flex;gap:4px;align-items:center;">';

    $url = function(int $p) use ($exam_id, $param) {
        $params = ['exam_id' => $exam_id, $param => $p];
        return 'manage_exam_candidates.php?' . http_build_query($params);
    };

    $dis_prev = $page === 1 ? ' style="opacity:.4;pointer-events:none;"' : '';
    $dis_next = $page === $total ? ' style="opacity:.4;pointer-events:none;"' : '';

    $html .= '<a href="' . $url(1) . '" class="page-btn"' . $dis_prev . '>&laquo;</a>';
    $html .= '<a href="' . $url(max(1, $page - 1)) . '" class="page-btn"' . $dis_prev . '>&lsaquo; Prev</a>';

    $ws = max(1, $page - 2);
    $we = min($total, $page + 2);
    if ($ws > 1) {
        $html .= '<a href="' . $url(1) . '" class="page-btn">1</a>';
        if ($ws > 2) $html .= '<span style="padding:0 4px;color:var(--text-muted);font-size:.8rem;">&hellip;</span>';
    }
    for ($p = $ws; $p <= $we; $p++) {
        $active = $p === $page ? ' active' : '';
        $html .= '<a href="' . $url($p) . '" class="page-btn' . $active . '" style="min-width:36px;">' . $p . '</a>';
    }
    if ($we < $total) {
        if ($we < $total - 1) $html .= '<span style="padding:0 4px;color:var(--text-muted);font-size:.8rem;">&hellip;</span>';
        $html .= '<a href="' . $url($total) . '" class="page-btn">' . $total . '</a>';
    }

    $html .= '<a href="' . $url(min($total, $page + 1)) . '" class="page-btn"' . $dis_next . '>Next &rsaquo;</a>';
    $html .= '<a href="' . $url($total) . '" class="page-btn"' . $dis_next . '>&raquo;</a>';
    $html .= '</div></div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Candidates | NED-SEMS</title>
    <?php $module_css = 'exam_officer'; include __DIR__ . '/../common/head_assets.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
    <style>
        .kpi-bar { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:22px; }
        .kpi-tile { background:var(--card-color); border:1px solid var(--border-color); border-radius:var(--border-radius-lg); padding:18px 20px; box-shadow:var(--box-shadow); transition: transform .2s ease, box-shadow .2s ease; }
        .kpi-tile:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(0,0,0,.08); }
        .kpi-tile .kpi-label { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted); margin-bottom:5px; }
        .kpi-tile .kpi-value { font-size:1.8rem; font-weight:800; color:#0f172a; line-height:1; }
        .kpi-tile .kpi-hint  { font-size:.72rem; color:var(--text-muted); margin-top:4px; }
        .kpi-tile.kpi-blue  { border-left:4px solid var(--info-color); }
        .kpi-tile.kpi-green { border-left:4px solid var(--success-color); }
        .kpi-tile.kpi-red   { border-left:4px solid var(--danger-color); }
        @media(max-width:600px){ .kpi-bar{grid-template-columns:1fr;} }
        .page-btn { display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border-radius:var(--border-radius);border:1px solid var(--border-color);background:var(--card-color);color:var(--text-color);font-size:.8rem;font-weight:600;text-decoration:none;transition:background .15s; }
        .page-btn:hover { background:#f1f5f9; }
        .page-btn.active { background:var(--primary-dark);color:#fff;border-color:var(--primary-dark); }
        .exam-info-banner {
            display: flex; gap: 20px; flex-wrap: wrap; align-items: center;
            padding: 14px 18px; background: #f8fafc; border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg); margin-bottom: 20px;
        }
        .exam-info-banner .info-item { font-size: .82rem; color: var(--text-muted); }
        .exam-info-banner .info-item strong { color: #0f172a; }
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
                <h2 class="page-title">Exam Candidates Management</h2>
                <p class="page-subtitle">
                    <?= htmlspecialchars($school['school_name'] ?? 'Your School') ?>
                    — View and manage candidate status for specific examinations
                </p>
            </div>
            <div class="header-actions">
                <a href="manage_students.php" class="btn btn-secondary btn-small">Manage Students</a>
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
                <select name="exam_id" style="flex:1; min-width:240px;" onchange="this.form.submit()">
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

        <!-- EXAM INFO BANNER -->
        <div class="exam-info-banner">
            <div class="info-item">
                Exam: <strong><?= htmlspecialchars($selected_exam['exam_name']) ?></strong>
            </div>
            <div class="info-item">
                Code: <strong><?= htmlspecialchars($selected_exam['exam_code'] ?? 'N/A') ?></strong>
            </div>
            <div class="info-item">
                Target Class: <strong><?= htmlspecialchars($exam_class) ?></strong>
            </div>
            <div class="info-item">
                Year: <strong><?= htmlspecialchars($selected_exam['year'] ?? 'N/A') ?></strong>
            </div>
            <div class="info-item">
                Status: <strong><?= ucfirst(str_replace('_', ' ', $selected_exam['status'] ?? '')) ?></strong>
            </div>
        </div>

        <!-- KPI -->
        <div class="kpi-bar">
            <div class="kpi-tile kpi-blue">
                <div class="kpi-label">Total Candidates</div>
                <div class="kpi-value"><?= $total_registered ?></div>
                <div class="kpi-hint">Candidates in <?= htmlspecialchars($exam_class) ?></div>
            </div>
            <div class="kpi-tile kpi-green">
                <div class="kpi-label">Active Candidates</div>
                <div class="kpi-value"><?= $active_count ?></div>
                <div class="kpi-hint">Sitting for examination</div>
            </div>
            <div class="kpi-tile kpi-red">
                <div class="kpi-label">Withdrawn</div>
                <div class="kpi-value"><?= $withdrawn_count ?></div>
                <div class="kpi-hint">Inactive candidates</div>
            </div>
        </div>

        <!-- REGISTERED STUDENTS TABLE -->
        <div class="card">
            <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;">Registered Candidates for <?= htmlspecialchars($selected_exam['exam_name']) ?></h3>
                <span style="font-size:.82rem;color:var(--text-muted);"><?= $total_registered ?> total candidates</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width:50px;">#</th>
                            <th>Student Name</th>
                            <th>Exam Number</th>
                            <th>Class</th>
                            <th>Status</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($registered)): ?>
                            <tr><td colspan="6" class="empty-state">No students found in <?= htmlspecialchars($exam_class) ?> for this school. <a href="manage_students.php">Add students to <?= htmlspecialchars($exam_class) ?></a></td></tr>
                        <?php else: ?>
                            <?php foreach ($registered as $idx => $s): ?>
                            <tr>
                                <td style="font-weight:700;color:var(--text-muted);font-size:.8rem;"><?= $reg_offset + $idx + 1 ?></td>
                                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                                <td style="font-family:monospace;font-size:.85rem;"><?= htmlspecialchars($s['exam_number']) ?></td>
                                <td><?= htmlspecialchars($s['class'] ?? '—') ?></td>
                                <td>
                                    <span class="badge badge-<?= $s['status'] === 'active' ? 'active' : 'inactive' ?>">
                                        <?= ucfirst($s['status']) ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
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
            <?= render_cand_pagination($reg_pagination, $exam_id, 'rp', $total_registered) ?>
        </div>

        <?php else: ?>
        <div class="card">
            <p class="empty-state">Select an examination above to view and manage candidate status.</p>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include '../common/footer.php'; ?>
</body>
</html>
