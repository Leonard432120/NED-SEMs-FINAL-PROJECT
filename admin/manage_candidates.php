<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn    = get_db_connection();
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
if ($exam_id <= 0) die("Invalid Exam ID");

/* ═══ EXAM ═══ */
$stmt = $conn->prepare("SELECT * FROM exams WHERE exam_id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$exam) die("Exam not found");

$exam_class = $exam['class'];

$message      = '';
$message_type = '';

/* ═══════════════════════════════════════
   SYNC candidate count in exam_subjects
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

/* ═══════════════════════════════════════
   HANDLE POST
═══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_student') {
        $name        = trim($_POST['name'] ?? '');
        $exam_number = trim($_POST['exam_number'] ?? '');
        $school_id   = (int)($_POST['school_id'] ?? 0);

        if ($name === '' || $exam_number === '') {
            $message = "Name and exam number are required.";
            $message_type = "error";
        } else {
            $chk = $conn->prepare("SELECT student_id FROM students WHERE exam_number = ?");
            $chk->bind_param("s", $exam_number);
            $chk->execute();
            $existing = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($existing) {
                $u = $conn->prepare("UPDATE students SET class = ?, school_id = ? WHERE student_id = ?");
                $u->bind_param("sii", $exam_class, $school_id, $existing['student_id']);
                $u->execute(); $u->close();
                $message = "Existing student updated and assigned to {$exam_class}.";
                $message_type = "success";
            } else {
                $sid = $school_id > 0 ? $school_id : null;
                $ins = $conn->prepare("INSERT INTO students (name, exam_number, class, school_id, status) VALUES (?, ?, ?, ?, 'active')");
                $ins->bind_param("sssi", $name, $exam_number, $exam_class, $sid);
                if ($ins->execute()) {
                    $message = "Candidate '{$name}' registered successfully.";
                    $message_type = "success";
                } else {
                    $message = "Error: " . $conn->error;
                    $message_type = "error";
                }
                $ins->close();
            }
            if ($message_type === 'success') syncCandidateCount($conn, $exam_id, $exam_class);
        }
    }

    if ($action === 'register_existing') {
        $student_id = (int)$_POST['student_id'];
        $u = $conn->prepare("UPDATE students SET class = ? WHERE student_id = ?");
        $u->bind_param("si", $exam_class, $student_id);
        $u->execute(); $u->close();
        $message = "Student assigned to {$exam_class}.";
        $message_type = "success";
        syncCandidateCount($conn, $exam_id, $exam_class);
    }

    if ($action === 'withdraw') {
        $student_id = (int)$_POST['student_id'];
        $u = $conn->prepare("UPDATE students SET status = 'inactive' WHERE student_id = ?");
        $u->bind_param("i", $student_id);
        $u->execute(); $u->close();
        $message = "Candidate withdrawn.";
        $message_type = "success";
        syncCandidateCount($conn, $exam_id, $exam_class);
    }

    if ($action === 'reactivate') {
        $student_id = (int)$_POST['student_id'];
        $u = $conn->prepare("UPDATE students SET status = 'active' WHERE student_id = ?");
        $u->bind_param("i", $student_id);
        $u->execute(); $u->close();
        $message = "Candidate re-registered.";
        $message_type = "success";
        syncCandidateCount($conn, $exam_id, $exam_class);
    }

    // Redirect to avoid resubmit, preserve filters
    $qs = http_build_query([
        'exam_id' => $exam_id,
        'search'  => $_GET['search']  ?? '',
        'school'  => $_GET['school']  ?? '',
        'status'  => $_GET['status']  ?? '',
        'page'    => $_GET['page']    ?? 1,
        'msg'     => $message,
        'mt'      => $message_type,
    ]);
    header("Location: manage_candidates.php?{$qs}");
    exit();
}

// Carry flash from redirect
if (isset($_GET['msg']) && $_GET['msg'] !== '') {
    $message      = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['mt'] ?? 'success');
}

/* ═══════════════════════════════════════
   FILTERS
═══════════════════════════════════════ */
$search     = trim($_GET['search'] ?? '');
$filter_school  = (int)($_GET['school'] ?? 0);
$filter_status  = trim($_GET['status'] ?? '');

/* ═══════════════════════════════════════
   PAGINATION
═══════════════════════════════════════ */
$per_page = 15;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

/* ═══ COUNT for pagination ═══ */
$count_sql  = "SELECT COUNT(*) AS cnt FROM students s WHERE s.class = ?";
$count_params = [$exam_class];
$count_types  = "s";

if ($search !== '') {
    $count_sql   .= " AND (s.name LIKE ? OR s.exam_number LIKE ?)";
    $count_params[] = "%{$search}%";
    $count_params[] = "%{$search}%";
    $count_types   .= "ss";
}
if ($filter_school > 0) {
    $count_sql   .= " AND s.school_id = ?";
    $count_params[] = $filter_school;
    $count_types   .= "i";
}
if ($filter_status !== '') {
    $count_sql   .= " AND s.status = ?";
    $count_params[] = $filter_status;
    $count_types   .= "s";
}

$cs = $conn->prepare($count_sql);
$cs->bind_param($count_types, ...$count_params);
$cs->execute();
$total_rows  = (int)$cs->get_result()->fetch_assoc()['cnt'];
$cs->close();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

/* ═══ CANDIDATE LIST with filters ═══ */
$sql  = "SELECT s.*, sc.school_name
         FROM students s
         LEFT JOIN schools sc ON s.school_id = sc.school_id
         WHERE s.class = ?";
$types  = "s";
$params = [$exam_class];

if ($search !== '') {
    $sql    .= " AND (s.name LIKE ? OR s.exam_number LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $types   .= "ss";
}
if ($filter_school > 0) {
    $sql    .= " AND s.school_id = ?";
    $params[] = $filter_school;
    $types   .= "i";
}
if ($filter_status !== '') {
    $sql    .= " AND s.status = ?";
    $params[] = $filter_status;
    $types   .= "s";
}
$sql    .= " ORDER BY sc.school_name ASC, s.name ASC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types   .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$candidates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ═══ KPI totals (full, no filter) ═══ */
$kpi = $conn->prepare("
    SELECT
        SUM(status='active')   AS total_active,
        SUM(status='inactive') AS total_inactive,
        COUNT(DISTINCT school_id) AS school_cnt
    FROM students WHERE class = ?
");
$kpi->bind_param("s", $exam_class);
$kpi->execute();
$kpi_data = $kpi->get_result()->fetch_assoc();
$kpi->close();

/* ═══ Schools for filter dropdown ═══ */
$schools = $conn->query("
    SELECT sc.school_id, sc.school_name
    FROM schools sc
    INNER JOIN students s ON s.school_id = sc.school_id
    WHERE s.class = '{$exam_class}'
    GROUP BY sc.school_id, sc.school_name
    ORDER BY sc.school_name ASC
")->fetch_all(MYSQLI_ASSOC);

/* ═══ Schools for registration form ═══ */
$all_schools = $conn->query("SELECT school_id, school_name FROM schools WHERE status='active' ORDER BY school_name ASC")->fetch_all(MYSQLI_ASSOC);

/* ═══ Other students (not in this class) ═══ */
$other_students = $conn->query("
    SELECT s.student_id, s.name, s.exam_number, s.class, sc.school_name
    FROM students s
    LEFT JOIN schools sc ON s.school_id = sc.school_id
    WHERE (s.class IS NULL OR s.class != '{$exam_class}') AND s.status = 'active'
    ORDER BY s.name ASC
    LIMIT 200
")->fetch_all(MYSQLI_ASSOC);

$conn->close();

/* ═══ URL builder for pagination ═══ */
function page_url(int $p, int $exam_id, string $search, int $school, string $status): string {
    return 'manage_candidates.php?' . http_build_query(array_filter([
        'exam_id' => $exam_id,
        'page'    => $p,
        'search'  => $search,
        'school'  => $school ?: null,
        'status'  => $status,
    ], fn($v) => $v !== null && $v !== '' && $v !== 0));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Candidates – <?= htmlspecialchars($exam['exam_name']) ?> | NED-SEMS</title>
    <?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>
    <style>
        .kpi-bar {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 22px;
        }
        .kpi-tile {
            background: var(--card-color);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            padding: 15px 17px;
            box-shadow: var(--box-shadow);
        }
        .kpi-tile .kpi-label {
            font-size: 0.7rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.06em;
            color: var(--text-muted); margin-bottom: 5px;
        }
        .kpi-tile .kpi-value {
            font-size: 1.8rem; font-weight: 800;
            color: #0f172a; line-height: 1;
        }
        .kpi-tile .kpi-hint { font-size: 0.72rem; color: var(--text-muted); margin-top: 4px; }
        .kpi-tile.kpi-blue   { border-left: 4px solid var(--info-color); }
        .kpi-tile.kpi-green  { border-left: 4px solid var(--success-color); }
        .kpi-tile.kpi-red    { border-left: 4px solid var(--danger-color); }
        .kpi-tile.kpi-slate  { border-left: 4px solid var(--primary-color); }
        /* Pagination */
        .pagination-bar {
            display: flex; align-items: center;
            justify-content: space-between;
            flex-wrap: wrap; gap: 10px; margin-top: 20px;
        }
        .pagination-info { font-size: 0.8rem; color: var(--text-muted); }
        .pagination-links { display: flex; gap: 6px; flex-wrap: wrap; }
        .page-btn {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 34px; height: 34px; padding: 0 10px;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            background: var(--card-color); color: var(--text-color);
            font-size: 0.8rem; font-weight: 600; text-decoration: none;
            transition: background 0.15s, border-color 0.15s;
        }
        .page-btn:hover  { background: #f1f5f9; border-color: #cbd5e1; }
        .page-btn.active { background: var(--primary-dark); color: #fff; border-color: var(--primary-dark); }
        .page-btn.disabled { opacity: 0.4; pointer-events: none; }
        /* Form grid */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; }

        @media (max-width: 768px) { .kpi-bar { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 480px) { .kpi-bar { grid-template-columns: 1fr; } }
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
                <h2 class="page-title">Manage Candidates</h2>
                <p class="page-subtitle">
                    <?= htmlspecialchars($exam['exam_name']) ?>
                    (<?= htmlspecialchars($exam['exam_code']) ?>)
                    &mdash; <?= htmlspecialchars($exam_class) ?>
                </p>
            </div>
            <div class="header-actions">
                <a href="manage_exam_subjects.php?exam_id=<?= $exam_id ?>" class="btn btn-secondary btn-small">Manage Subjects</a>
                <a href="view_exam.php?id=<?= $exam_id ?>" class="btn btn-secondary btn-small">Back to Exam</a>
            </div>
        </div>

        <!-- FLASH -->
        <?php if ($message): ?>
            <div class="alert alert-<?= htmlspecialchars($message_type) ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- KPI BAR -->
        <div class="kpi-bar">
            <div class="kpi-tile kpi-blue">
                <div class="kpi-label">Registered</div>
                <div class="kpi-value"><?= (int)($kpi_data['total_active'] ?? 0) ?></div>
                <div class="kpi-hint">Active candidates</div>
            </div>
            <div class="kpi-tile kpi-slate">
                <div class="kpi-label">Schools</div>
                <div class="kpi-value"><?= (int)($kpi_data['school_cnt'] ?? 0) ?></div>
                <div class="kpi-hint">With candidates</div>
            </div>
            <div class="kpi-tile kpi-red">
                <div class="kpi-label">Withdrawn</div>
                <div class="kpi-value"><?= (int)($kpi_data['total_inactive'] ?? 0) ?></div>
                <div class="kpi-hint">Inactive candidates</div>
            </div>
            <div class="kpi-tile kpi-green">
                <div class="kpi-label">Exam Year</div>
                <div class="kpi-value"><?= htmlspecialchars($exam['year'] ?? date('Y')) ?></div>
                <div class="kpi-hint"><?= htmlspecialchars($exam_class) ?></div>
            </div>
        </div>

        <!-- REGISTER NEW CANDIDATE -->
        <div class="card">
            <div class="section-header">
                <h3>Register Candidate</h3>
            </div>
            <p class="muted-text" style="margin-bottom:16px;">
                Select an existing student from the system, or enter a new candidate manually.
            </p>

            <!-- TABS -->
            <div style="display:flex; gap:0; margin-bottom:20px; border-bottom:2px solid var(--border-color);">
                <button type="button" id="adm-tab-existing" onclick="admSwitchTab('existing')"
                    style="padding:10px 20px; border:none; background:none; font-weight:700;
                           font-size:.9rem; color:var(--primary-dark);
                           border-bottom:2px solid var(--primary-dark);
                           cursor:pointer; margin-bottom:-2px;">From System</button>
                <button type="button" id="adm-tab-new" onclick="admSwitchTab('new')"
                    style="padding:10px 20px; border:none; background:none; font-weight:600;
                           font-size:.9rem; color:var(--text-muted); cursor:pointer;">New Candidate</button>
            </div>

            <!-- FROM SYSTEM -->
            <div id="adm-panel-existing">
                <?php if (!empty($other_students)): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="register_existing">
                    <div class="form-grid">
                        <div class="form-group" style="grid-column:1/-1">
                            <label>Select Student <span style="color:var(--danger-color)">*</span></label>
                            <select name="student_id" required onchange="admFillStudent(this)">
                                <option value="">— Search and select student —</option>
                                <?php foreach ($other_students as $os): ?>
                                    <option value="<?= $os['student_id'] ?>"
                                            data-name="<?= htmlspecialchars($os['name']) ?>"
                                            data-exam="<?= htmlspecialchars($os['exam_number']) ?>"
                                            data-school="<?= htmlspecialchars($os['school_name'] ?? '—') ?>"
                                            data-class="<?= htmlspecialchars($os['class'] ?? '') ?>">
                                        <?= htmlspecialchars($os['name']) ?>
                                        — <?= htmlspecialchars($os['exam_number']) ?>
                                        <?= $os['school_name'] ? '(' . htmlspecialchars($os['school_name']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div id="adm-student-preview"
                         style="margin-top:8px; padding:12px 14px; background:#f8fafc;
                                border:1px solid var(--border-color); border-radius:var(--border-radius);
                                font-size:.875rem; color:var(--text-muted);">
                        Select a student above to preview details.
                    </div>
                    <div class="form-actions" style="margin-top:14px;">
                        <button type="submit" class="btn btn-dark"
                            onclick="return confirm('Register this student as <?= htmlspecialchars($exam_class) ?> candidate?');">
                            Register for <?= htmlspecialchars($exam_class) ?>
                        </button>
                    </div>
                </form>
                <?php else: ?>
                    <p class="empty-state">All system students are already assigned to <?= htmlspecialchars($exam_class) ?>.</p>
                <?php endif; ?>
            </div>

            <!-- NEW CANDIDATE -->
            <div id="adm-panel-new" style="display:none;">
                <form method="POST">
                    <input type="hidden" name="action" value="add_student">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Full Name <span style="color:var(--danger-color)">*</span></label>
                            <input type="text" name="name" required placeholder="e.g. John Banda">
                        </div>
                        <div class="form-group">
                            <label>Exam Number <span style="color:var(--danger-color)">*</span></label>
                            <input type="text" name="exam_number" required placeholder="e.g. MW298800">
                        </div>
                        <div class="form-group">
                            <label>School</label>
                            <select name="school_id">
                                <option value="">— Select School —</option>
                                <?php foreach ($all_schools as $sc): ?>
                                    <option value="<?= $sc['school_id'] ?>"><?= htmlspecialchars($sc['school_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Class / Form</label>
                            <input type="text" value="<?= htmlspecialchars($exam_class) ?>" readonly
                                   style="background:#f8fafc; color:var(--text-muted);">
                        </div>
                    </div>
                    <div class="form-actions" style="margin-top:14px;">
                        <button type="submit" class="btn btn-dark">Register New Candidate</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- FILTER BAR -->
        <form method="GET" class="search-form">
            <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
            <input type="text" name="search" placeholder="Search name or exam number…"
                   value="<?= htmlspecialchars($search) ?>">
            <select name="school">
                <option value="">All Schools</option>
                <?php foreach ($schools as $sc): ?>
                    <option value="<?= $sc['school_id'] ?>" <?= $filter_school === (int)$sc['school_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sc['school_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="status">
                <option value="">All Statuses</option>
                <option value="active"   <?= $filter_status === 'active'   ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $filter_status === 'inactive' ? 'selected' : '' ?>>Withdrawn</option>
            </select>
            <button type="submit" class="btn btn-dark">Filter</button>
            <a href="manage_candidates.php?exam_id=<?= $exam_id ?>" class="btn btn-secondary">Reset</a>
        </form>

        <!-- CANDIDATE TABLE -->
        <div class="card">
            <div class="section-header">
                <h3>Registered Candidates</h3>
                <span class="muted-text" style="font-size:.85rem;">
                    Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_rows) ?> of <?= $total_rows ?>
                </span>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Exam Number</th>
                            <th>School</th>
                            <th>Class</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($candidates)): ?>
                            <tr>
                                <td colspan="7" class="empty-state">
                                    No candidates found.
                                    <?php if ($search || $filter_school || $filter_status): ?>
                                        <a href="manage_candidates.php?exam_id=<?= $exam_id ?>">Reset filters</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $i = $offset + 1; foreach ($candidates as $c): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                                <td><?= htmlspecialchars($c['exam_number']) ?></td>
                                <td><?= htmlspecialchars($c['school_name'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($c['class'] ?? '—') ?></td>
                                <td>
                                    <span class="badge badge-<?= $c['status'] === 'active' ? 'active' : 'inactive' ?>">
                                        <?= $c['status'] === 'active' ? 'Active' : 'Withdrawn' ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <?php if ($c['status'] === 'active'): ?>
                                        <form method="POST" style="display:inline;"
                                              onsubmit="return confirm('Withdraw this candidate?');">
                                            <input type="hidden" name="action" value="withdraw">
                                            <input type="hidden" name="student_id" value="<?= $c['student_id'] ?>">
                                            <button type="submit" class="btn btn-delete btn-small">Withdraw</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="reactivate">
                                            <input type="hidden" name="student_id" value="<?= $c['student_id'] ?>">
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

            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-bar">
                <div class="pagination-info">
                    Page <?= $page ?> of <?= $total_pages ?>
                </div>
                <div class="pagination-links">
                    <a href="<?= page_url(1, $exam_id, $search, $filter_school, $filter_status) ?>"
                       class="page-btn <?= $page === 1 ? 'disabled' : '' ?>">First</a>
                    <a href="<?= page_url(max(1, $page - 1), $exam_id, $search, $filter_school, $filter_status) ?>"
                       class="page-btn <?= $page === 1 ? 'disabled' : '' ?>">Prev</a>

                    <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                        <a href="<?= page_url($p, $exam_id, $search, $filter_school, $filter_status) ?>"
                           class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>

                    <a href="<?= page_url(min($total_pages, $page + 1), $exam_id, $search, $filter_school, $filter_status) ?>"
                       class="page-btn <?= $page === $total_pages ? 'disabled' : '' ?>">Next</a>
                    <a href="<?= page_url($total_pages, $exam_id, $search, $filter_school, $filter_status) ?>"
                       class="page-btn <?= $page === $total_pages ? 'disabled' : '' ?>">Last</a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ADD EXISTING STUDENT -->
        <?php if (!empty($other_students)): ?>
        <div class="card">
            <div class="section-header">
                <h3>Add Existing Student</h3>
            </div>
            <p class="muted-text" style="margin-bottom:14px;">
                These students exist in the system but are not in <?= htmlspecialchars($exam_class) ?>.
                Registering them will update their class to <?= htmlspecialchars($exam_class) ?>.
            </p>
            <input type="text" id="existingSearch" placeholder="Search by name or exam number…"
                   onkeyup="filterExisting(this.value)"
                   style="width:100%; padding:10px 14px; border:1px solid var(--border-color);
                          border-radius:var(--border-radius-lg); font-size:.875rem; margin-bottom:14px;">
            <div class="table-container">
                <table id="existingTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Exam Number</th>
                            <th>Current Class</th>
                            <th>School</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($other_students as $os): ?>
                        <tr class="existing-row">
                            <td><?= $i++ ?></td>
                            <td><strong><?= htmlspecialchars($os['name']) ?></strong></td>
                            <td><?= htmlspecialchars($os['exam_number']) ?></td>
                            <td><?= htmlspecialchars($os['class'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($os['school_name'] ?? '—') ?></td>
                            <td>
                                <form method="POST" style="display:inline;"
                                      onsubmit="return confirm('Register as <?= htmlspecialchars($exam_class) ?> candidate?');">
                                    <input type="hidden" name="action" value="register_existing">
                                    <input type="hidden" name="student_id" value="<?= $os['student_id'] ?>">
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

    </div>
</div>

<script>
function filterExisting(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#existingTable .existing-row').forEach(function(r) {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

function admSwitchTab(tab) {
    document.getElementById('adm-panel-existing').style.display = tab === 'existing' ? '' : 'none';
    document.getElementById('adm-panel-new').style.display      = tab === 'new'      ? '' : 'none';
    const te = document.getElementById('adm-tab-existing');
    const tn = document.getElementById('adm-tab-new');
    if (tab === 'existing') {
        te.style.fontWeight = '700'; te.style.color = 'var(--primary-dark)'; te.style.borderBottom = '2px solid var(--primary-dark)';
        tn.style.fontWeight = '600'; tn.style.color = 'var(--text-muted)';   tn.style.borderBottom = 'none';
    } else {
        tn.style.fontWeight = '700'; tn.style.color = 'var(--primary-dark)'; tn.style.borderBottom = '2px solid var(--primary-dark)';
        te.style.fontWeight = '600'; te.style.color = 'var(--text-muted)';   te.style.borderBottom = 'none';
    }
}

function admFillStudent(sel) {
    const opt     = sel.selectedOptions[0];
    const preview = document.getElementById('adm-student-preview');
    if (!sel.value) {
        preview.textContent = 'Select a student above to preview details.';
        return;
    }
    preview.innerHTML =
        '<strong>Name:</strong> ' + opt.dataset.name +
        ' &nbsp;|&nbsp; <strong>Exam No:</strong> ' + opt.dataset.exam +
        ' &nbsp;|&nbsp; <strong>School:</strong> ' + opt.dataset.school +
        (opt.dataset.class ? ' &nbsp;|&nbsp; <strong>Current Class:</strong> ' + opt.dataset.class : '');
}
</script>

<?php include '../common/footer.php'; ?>
</body>
</html>
