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

/* SCHOOL INFO */
$sc_stmt = $conn->prepare("SELECT school_name FROM schools WHERE school_id = ?");
$sc_stmt->bind_param("i", $school_id);
$sc_stmt->execute();
$school = $sc_stmt->get_result()->fetch_assoc();
$sc_stmt->close();

$message      = '';
$message_type = '';

/* HANDLE POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ADD STUDENT */
    if ($action === 'add') {
        $name        = trim($_POST['name'] ?? '');
        $exam_number = trim($_POST['exam_number'] ?? '');
        $class       = trim($_POST['class'] ?? '');

        if ($name === '' || $exam_number === '' || $class === '') {
            $message = "Name, exam number, and class are required.";
            $message_type = "error";
        } elseif (!in_array($class, ['Form 2', 'Form 4'])) {
            $message = "Invalid class selected.";
            $message_type = "error";
        } else {
            $chk = $conn->prepare("SELECT student_id FROM students WHERE exam_number = ?");
            $chk->bind_param("s", $exam_number);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $message = "A student with exam number '{$exam_number}' already exists.";
                $message_type = "error";
            } else {
                $ins = $conn->prepare("INSERT INTO students (name, exam_number, class, school_id, status) VALUES (?, ?, ?, ?, 'active')");
                $ins->bind_param("sssi", $name, $exam_number, $class, $school_id);
                if ($ins->execute()) {
                    $new_id  = (int)$conn->insert_id;
                    $message = "Student '{$name}' registered successfully.";
                    $message_type = "success";
                    /* Auto-assign any subjects POSTed with the new student form */
                    $subjects = $_POST['subject_ids'] ?? [];
                    if (!empty($subjects)) {
                        $vals = [];
                        foreach ($subjects as $sid) {
                            $vals[] = "({$new_id}," . (int)$sid . ")";
                        }
                        $conn->query("INSERT IGNORE INTO student_subjects (student_id, subject_id) VALUES " . implode(',', $vals));
                    }
                } else {
                    $message = "Error: " . $conn->error;
                    $message_type = "error";
                }
                $ins->close();
            }
            $chk->close();
        }
    }

    /* LINK EXISTING STUDENT */
    if ($action === 'link_existing') {
        $student_id = (int)$_POST['student_id'];
        $class      = trim($_POST['class'] ?? '');
        if ($student_id <= 0 || $class === '') {
            $message = "Please select a student and a class.";
            $message_type = "error";
        } elseif (!in_array($class, ['Form 2', 'Form 4'])) {
            $message = "Invalid class selected.";
            $message_type = "error";
        } else {
            $upd = $conn->prepare("UPDATE students SET school_id = ?, class = ?, status = 'active' WHERE student_id = ?");
            $upd->bind_param("isi", $school_id, $class, $student_id);
            if ($upd->execute()) {
                $message = "Student linked to your school and assigned to {$class}.";
                $message_type = "success";
            } else {
                $message = "Error: " . $conn->error;
                $message_type = "error";
            }
            $upd->close();
        }
    }

    /* EDIT STUDENT */
    if ($action === 'edit') {
        $student_id  = (int)$_POST['student_id'];
        $name        = trim($_POST['name'] ?? '');
        $exam_number = trim($_POST['exam_number'] ?? '');
        $class       = trim($_POST['class'] ?? '');

        if ($name === '' || $exam_number === '' || $class === '') {
            $message = "All fields are required.";
            $message_type = "error";
        } else {
            $upd = $conn->prepare("UPDATE students SET name=?, exam_number=?, class=? WHERE student_id=? AND school_id=?");
            $upd->bind_param("sssii", $name, $exam_number, $class, $student_id, $school_id);
            if ($upd->execute()) {
                $message = "Student updated successfully.";
                $message_type = "success";
            } else {
                $message = "Error: " . $conn->error;
                $message_type = "error";
            }
            $upd->close();
        }
    }

    /* ASSIGN SUBJECTS */
    if ($action === 'assign_subjects') {
        $student_id = (int)$_POST['student_id'];
        $subjects   = $_POST['subject_ids'] ?? [];

        if (empty($subjects)) {
            $message = "At least one subject must be selected before saving.";
            $message_type = "error";
        } else {
            /* Verify this student belongs to this school */
            $chk = $conn->prepare("SELECT student_id FROM students WHERE student_id = ? AND school_id = ?");
            $chk->bind_param("ii", $student_id, $school_id);
            $chk->execute();
            if ($chk->get_result()->num_rows === 0) {
                $message = "Student not found in your school.";
                $message_type = "error";
            } else {
                $conn->query("DELETE FROM student_subjects WHERE student_id = {$student_id}");
                $values = [];
                foreach ($subjects as $s_id) {
                    $values[] = "(" . $student_id . ", " . (int)$s_id . ")";
                }
                $conn->query("INSERT IGNORE INTO student_subjects (student_id, subject_id) VALUES " . implode(',', $values));
                $message = "Subjects updated successfully.";
                $message_type = "success";
            }
            $chk->close();
        }
    }

    /* TOGGLE STATUS */
    if ($action === 'toggle_status') {
        $student_id     = (int)$_POST['student_id'];
        $current_status = trim($_POST['current_status'] ?? 'active');
        $new_status     = $current_status === 'active' ? 'inactive' : 'active';
        $upd = $conn->prepare("UPDATE students SET status=? WHERE student_id=? AND school_id=?");
        $upd->bind_param("sii", $new_status, $student_id, $school_id);
        $upd->execute(); $upd->close();
        $message = "Student status updated to {$new_status}.";
        $message_type = "success";
    }

    header("Location: manage_students.php?msg=" . urlencode($message) . "&mt=" . urlencode($message_type)
        . "&search=" . urlencode($_GET['search'] ?? '')
        . "&class=" . urlencode($_GET['class'] ?? '')
        . "&subject=" . urlencode($_GET['subject'] ?? '')
        . "&page=" . urlencode($_GET['page'] ?? 1));
    exit();
}

if (isset($_GET['msg']) && $_GET['msg'] !== '') {
    $message      = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['mt'] ?? 'success');
}

/* FILTERS + PAGINATION */
$search         = trim($_GET['search'] ?? '');
$filter_class   = trim($_GET['class'] ?? '');
$filter_subject = (int)($_GET['subject'] ?? 0);
$per_page       = 5;
$page           = max(1, (int)($_GET['page'] ?? 1));
$offset         = ($page - 1) * $per_page;

/* Build WHERE clause shared by count + list */
$base_sql    = "FROM students s WHERE s.school_id = ?";
$base_types  = "i";
$base_params = [$school_id];

if ($search !== '') {
    $base_sql    .= " AND (s.name LIKE ? OR s.exam_number LIKE ?)";
    $base_params[] = "%{$search}%";
    $base_params[] = "%{$search}%";
    $base_types   .= "ss";
}
if ($filter_class !== '') {
    $base_sql    .= " AND s.class = ?";
    $base_params[] = $filter_class;
    $base_types   .= "s";
}
if ($filter_subject > 0) {
    $base_sql    .= " AND EXISTS (SELECT 1 FROM student_subjects ss WHERE ss.student_id = s.student_id AND ss.subject_id = ?)";
    $base_params[] = $filter_subject;
    $base_types   .= "i";
}

/* Count */
$cs = $conn->prepare("SELECT COUNT(*) AS cnt " . $base_sql);
$cs->bind_param($base_types, ...$base_params);
$cs->execute();
$total_rows  = (int)$cs->get_result()->fetch_assoc()['cnt'];
$cs->close();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

/* Students list */
$list_params   = array_merge($base_params, [$per_page, $offset]);
$list_types    = $base_types . "ii";
$stmt = $conn->prepare("SELECT s.* " . $base_sql . " ORDER BY s.name ASC LIMIT ? OFFSET ?");
$stmt->bind_param($list_types, ...$list_params);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* KPI */
$kpi = $conn->query("
    SELECT
        SUM(status='active')                               AS active,
        SUM(status='inactive')                             AS inactive,
        SUM(class='Form 2')                                AS form2,
        SUM(class='Form 4')                                AS form4
    FROM students WHERE school_id = {$school_id}
")->fetch_assoc();

/* KPI: students with at least one subject */
$kpi_with_subjects = (int)$conn->query("
    SELECT COUNT(DISTINCT student_id) AS cnt
    FROM student_subjects
    WHERE student_id IN (SELECT student_id FROM students WHERE school_id = {$school_id})
")->fetch_assoc()['cnt'];

/* Unassigned students for dropdown */
$unassigned_students = $conn->query("
    SELECT student_id, name, exam_number, class
    FROM students
    WHERE (school_id IS NULL OR school_id != {$school_id})
    ORDER BY name ASC
    LIMIT 500
")->fetch_all(MYSQLI_ASSOC);

/* All active subjects */
$all_subjects_res = $conn->query("SELECT subject_id, subject_name, subject_code, category FROM subjects WHERE status='active' ORDER BY subject_name ASC");
$all_subjects = $all_subjects_res ? $all_subjects_res->fetch_all(MYSQLI_ASSOC) : [];

/* Subject map for the current page */
$student_ids_on_page = array_column($students, 'student_id');
$student_subject_map = [];
if (!empty($student_ids_on_page)) {
    $id_list = implode(',', array_map('intval', $student_ids_on_page));
    $ss_rows = $conn->query("
        SELECT ss.student_id, ss.subject_id, s.subject_name, s.subject_code, s.category
        FROM student_subjects ss
        JOIN subjects s ON s.subject_id = ss.subject_id
        WHERE ss.student_id IN ({$id_list})
        ORDER BY s.subject_name ASC
    ");
    if ($ss_rows) {
        while ($ss = $ss_rows->fetch_assoc()) {
            $student_subject_map[(int)$ss['student_id']][] = $ss;
        }
    }
}

/* Subject ID list per student (for JS pre-check) */
$student_subject_ids_map = [];
foreach ($student_subject_map as $sid => $subjs) {
    $student_subject_ids_map[$sid] = array_column($subjs, 'subject_id');
}

/* Leave connection open — shared panel partial will use $panel_conn */

function page_url_st(int $p, string $search, string $class, int $subject): string {
    return 'manage_students.php?' . http_build_query(array_filter([
        'page'    => $p,
        'search'  => $search,
        'class'   => $class,
        'subject' => $subject ?: null,
    ], fn($v) => $v !== '' && $v !== null && $v !== 0));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students | NED-SEMS</title>
    <?php $module_css = 'exam_officer'; include __DIR__ . '/../common/head_assets.php'; ?>
    <style>
        .kpi-bar { display:grid; grid-template-columns:repeat(5,1fr); gap:14px; margin-bottom:22px; }
        .kpi-tile { background:var(--card-color); border:1px solid var(--border-color); border-radius:var(--border-radius-lg); padding:15px 17px; box-shadow:var(--box-shadow); }
        .kpi-tile .kpi-label { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted); margin-bottom:5px; }
        .kpi-tile .kpi-value { font-size:1.8rem; font-weight:800; color:#0f172a; line-height:1; }
        .kpi-tile .kpi-hint  { font-size:.72rem; color:var(--text-muted); margin-top:4px; }
        .kpi-tile.kpi-blue   { border-left:4px solid var(--info-color); }
        .kpi-tile.kpi-green  { border-left:4px solid var(--success-color); }
        .kpi-tile.kpi-slate  { border-left:4px solid var(--primary-color); }
        .kpi-tile.kpi-red    { border-left:4px solid var(--danger-color); }
        .kpi-tile.kpi-purple { border-left:4px solid #7c3aed; }
        .form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:14px; }
        .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; overflow-y:auto; }
        .modal.show { display:block; }
        .modal-content { background:#fff; max-width:480px; margin:5% auto; padding:24px; border-radius:var(--border-radius); }
        .modal-content--wide { max-width:640px; }
        .page-btn { display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border-radius:var(--border-radius);border:1px solid var(--border-color);background:var(--card-color);color:var(--text-color);font-size:.8rem;font-weight:600;text-decoration:none;transition:background .15s; }
        .page-btn:hover { background:#f1f5f9; }
        .page-btn.active { background:var(--primary-dark);color:#fff;border-color:var(--primary-dark); }
        .page-btn.disabled { opacity:.4;pointer-events:none; }
        .subjects-inline { display:flex; flex-wrap:wrap; gap:5px; margin-top:4px; max-width:260px; }
        .subj-mini { font-size:.7rem; padding:2px 7px; border-radius:999px; background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; white-space:nowrap; font-weight:600; }
        @media(max-width:900px){ .kpi-bar{grid-template-columns:repeat(3,1fr);} }
        @media(max-width:600px){ .kpi-bar{grid-template-columns:1fr 1fr;} }
        @media(max-width:400px){ .kpi-bar{grid-template-columns:1fr;} }
        .subjects-scroll { max-height:320px; overflow-y:auto; }
        /* Compulsory subjects get a warm amber highlight */
        .subj-compulsory {
            background: #fefce8 !important;
            border-color: #fcd34d !important;
            color: #92400e !important;
        }
        .subj-compulsory .subj-compulsory-badge {
            display: inline-block;
            font-size: .65rem;
            font-weight: 700;
            background: #f59e0b;
            color: #fff;
            border-radius: 4px;
            padding: 1px 5px;
            margin-left: 5px;
            vertical-align: middle;
        }
        /* Default (compulsory only) count badge — amber */
        .subject-count-badge.default {
            background: #f59e0b;
            color: #fff;
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
                <h2 class="page-title">Manage Students</h2>
                <p class="page-subtitle"><?= htmlspecialchars($school['school_name'] ?? 'Your School') ?></p>
            </div>
            <div class="header-actions">
                <a href="manage_exam_candidates.php" class="btn btn-secondary btn-small">Exam Candidates</a>
            </div>
        </div>

        <!-- FLASH -->
        <?php if ($message): ?>
            <div class="alert alert-<?= htmlspecialchars($message_type) ?>"><?= $message ?></div>
        <?php endif; ?>

        <!-- KPI BAR -->
        <div class="kpi-bar">
            <div class="kpi-tile kpi-blue">
                <div class="kpi-label">Total Students</div>
                <div class="kpi-value"><?= (int)(($kpi['active'] ?? 0) + ($kpi['inactive'] ?? 0)) ?></div>
                <div class="kpi-hint">All records</div>
            </div>
            <div class="kpi-tile kpi-green">
                <div class="kpi-label">Form 2</div>
                <div class="kpi-value"><?= (int)($kpi['form2'] ?? 0) ?></div>
                <div class="kpi-hint">JCE candidates</div>
            </div>
            <div class="kpi-tile kpi-slate">
                <div class="kpi-label">Form 4</div>
                <div class="kpi-value"><?= (int)($kpi['form4'] ?? 0) ?></div>
                <div class="kpi-hint">MSCE candidates</div>
            </div>
            <div class="kpi-tile kpi-red">
                <div class="kpi-label">Inactive</div>
                <div class="kpi-value"><?= (int)($kpi['inactive'] ?? 0) ?></div>
                <div class="kpi-hint">Withdrawn</div>
            </div>
            <div class="kpi-tile kpi-purple">
                <div class="kpi-label">With Subjects</div>
                <div class="kpi-value"><?= $kpi_with_subjects ?></div>
                <div class="kpi-hint">Have subjects registered</div>
            </div>
        </div>

        <!-- REGISTER STUDENT -->
        <div class="card">
            <div class="section-header"><h3>Register Student</h3></div>
            <p class="muted-text" style="margin-bottom:16px;">
                Select an existing student from the system or add a brand-new one.
            </p>

            <!-- TABS -->
            <div style="display:flex; gap:0; margin-bottom:20px; border-bottom:2px solid var(--border-color);">
                <button type="button" id="tab-existing" onclick="switchTab('existing')"
                    style="padding:10px 20px; border:none; background:none; font-weight:700;
                           font-size:.9rem; color:var(--primary-dark); border-bottom:2px solid var(--primary-dark);
                           cursor:pointer; margin-bottom:-2px;">From System</button>
                <button type="button" id="tab-new" onclick="switchTab('new')"
                    style="padding:10px 20px; border:none; background:none; font-weight:600;
                           font-size:.9rem; color:var(--text-muted); cursor:pointer;">New Student</button>
            </div>

            <!-- FROM SYSTEM -->
            <div id="panel-existing">
                <?php if (!empty($unassigned_students)): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="link_existing">
                    <div class="form-grid">
                        <div class="form-group" style="grid-column:1/-1">
                            <label>Select Student <span style="color:var(--danger-color)">*</span></label>
                            <select name="student_id" id="existingStudentSelect" required
                                    onchange="fillStudentDetails(this)">
                                <option value="">— Search and select student —</option>
                                <?php foreach ($unassigned_students as $us): ?>
                                    <option value="<?= $us['student_id'] ?>"
                                            data-name="<?= htmlspecialchars($us['name']) ?>"
                                            data-exam="<?= htmlspecialchars($us['exam_number']) ?>"
                                            data-class="<?= htmlspecialchars($us['class'] ?? '') ?>">
                                        <?= htmlspecialchars($us['name']) ?>
                                        — <?= htmlspecialchars($us['exam_number']) ?>
                                        <?= $us['class'] ? '(' . htmlspecialchars($us['class']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Class / Form <span style="color:var(--danger-color)">*</span></label>
                            <select name="class" id="existingClassSelect" required
                                    onchange="updateSubjectsCheckboxPanel(this.value, 'existingSubjects')">
                                <option value="">— Select Class —</option>
                                <option value="Form 2">Form 2 (JCE)</option>
                                <option value="Form 4">Form 4 (MSCE)</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-top:6px; padding:12px 14px; background:#f8fafc;
                                border:1px solid var(--border-color); border-radius:var(--border-radius);
                                font-size:.875rem; color:var(--text-muted); margin-bottom:10px;" id="selectedStudentInfo">
                        Select a student above to preview details.
                    </div>
                    <div id="existingSubjects"></div>
                    <div class="form-actions" style="margin-top:14px;">
                        <button type="submit" class="btn btn-dark">Add to School</button>
                    </div>
                </form>
                <?php else: ?>
                    <p class="empty-state">All system students are already linked to your school.</p>
                <?php endif; ?>
            </div>

            <!-- NEW STUDENT -->
            <div id="panel-new" style="display:none;">
                <form method="POST" id="newStudentForm" onsubmit="return validateNewStudent(this)">
                    <input type="hidden" name="action" value="add">
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
                            <label>Class / Form <span style="color:var(--danger-color)">*</span></label>
                            <select name="class" required
                                    onchange="updateSubjectsCheckboxPanel(this.value, 'newSubjects')">
                                <option value="">— Select Class —</option>
                                <option value="Form 2">Form 2 (JCE)</option>
                                <option value="Form 4">Form 4 (MSCE)</option>
                            </select>
                        </div>
                    </div>
                    <div id="newSubjects"></div>
                    <p id="newSubjectsError" style="display:none; color:var(--danger-color); font-size:.85rem; margin-top:6px;">
                        Please select at least one subject before registering.
                    </p>
                    <div class="form-actions" style="margin-top:14px;">
                        <button type="submit" class="btn btn-dark">Register New Student</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- FILTER BAR -->
        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="Search name or exam number..."
                   value="<?= htmlspecialchars($search) ?>">
            <select name="class">
                <option value="">All Classes</option>
                <option value="Form 2" <?= $filter_class === 'Form 2' ? 'selected' : '' ?>>Form 2</option>
                <option value="Form 4" <?= $filter_class === 'Form 4' ? 'selected' : '' ?>>Form 4</option>
            </select>
            <select name="subject">
                <option value="">All Subjects</option>
                <?php foreach ($all_subjects as $subj): ?>
                    <option value="<?= $subj['subject_id'] ?>"
                            <?= $filter_subject === (int)$subj['subject_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($subj['subject_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-dark">Filter</button>
            <a href="manage_students.php" class="btn btn-secondary">Reset</a>
        </form>

        <!-- STUDENT TABLE -->
        <div class="card">
            <div class="section-header">
                <h3>Student Records</h3>
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
                            <th>Class</th>
                            <th>Subjects</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr><td colspan="7" class="empty-state">No students found. <a href="manage_students.php">Reset filters</a></td></tr>
                        <?php else: ?>
                            <?php $i = $offset + 1; foreach ($students as $s):
                                $s_subjects = $student_subject_map[$s['student_id']] ?? [];
                                $subj_count = count($s_subjects);
                                $display_count = $subj_count > 0 ? $subj_count : 5;
                            ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                                <td><?= htmlspecialchars($s['exam_number']) ?></td>
                                <td><?= htmlspecialchars($s['class'] ?? '—') ?></td>
                                <td>
                                    <span class="subject-count-badge <?= $subj_count === 0 ? 'default' : '' ?>" title="<?= $subj_count === 0 ? '5 default compulsory subjects' : $display_count . ' registered subjects' ?>">
                                        <?= $display_count ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $s['status'] === 'active' ? 'active' : 'inactive' ?>">
                                        <?= ucfirst($s['status']) ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <!-- Manage subjects -->
                                    <button type="button" class="btn btn-subjects btn-small"
                                        onclick="openSubjectsModal(<?= $s['student_id'] ?>, <?= htmlspecialchars(json_encode($s['name'])) ?>, <?= json_encode($student_subject_ids_map[$s['student_id']] ?? []) ?>)">
                                        Subjects
                                    </button>

                                    <!-- View full details including registered subjects -->
                                    <button type="button" class="btn btn-view-details btn-small"
                                        onclick="openDetailsModal(<?= (int)$s['student_id'] ?>, <?= htmlspecialchars(json_encode($s['name'])) ?>, <?= htmlspecialchars(json_encode($s['exam_number'])) ?>, <?= htmlspecialchars(json_encode($s['class'] ?? 'Form 2')) ?>, <?= htmlspecialchars(json_encode($s['status'])) ?>)">
                                        Details
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
            <div style="
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 12px;
                margin-top: 24px;
                padding-top: 16px;
                border-top: 1px solid var(--border-color);
            ">
                <!-- Info text -->
                <div style="font-size: .82rem; color: var(--text-muted); line-height: 1.4;">
                    Showing
                    <strong style="color: var(--text-color);"><?= $offset + 1 ?></strong>
                    &ndash;
                    <strong style="color: var(--text-color);"><?= min($offset + $per_page, $total_rows) ?></strong>
                    of
                    <strong style="color: var(--text-color);"><?= $total_rows ?></strong>
                    students
                </div>

                <!-- Page buttons -->
                <div style="display: flex; gap: 4px; align-items: center;">

                    <!-- First + Prev -->
                    <a href="<?= page_url_st(1, $search, $filter_class, $filter_subject) ?>"
                       class="page-btn <?= $page === 1 ? 'disabled' : '' ?>"
                       title="First page"
                       style="font-size: .8rem;">&laquo;</a>

                    <a href="<?= page_url_st(max(1, $page - 1), $search, $filter_class, $filter_subject) ?>"
                       class="page-btn <?= $page === 1 ? 'disabled' : '' ?>"
                       title="Previous page"
                       style="font-size: .8rem;">&lsaquo; Prev</a>

                    <!-- Numbered pages (window of 5) -->
                    <?php
                    $window_start = max(1, $page - 2);
                    $window_end   = min($total_pages, $page + 2);
                    if ($window_start > 1): ?>
                        <a href="<?= page_url_st(1, $search, $filter_class, $filter_subject) ?>" class="page-btn">1</a>
                        <?php if ($window_start > 2): ?>
                            <span style="padding: 0 4px; color: var(--text-muted); font-size: .8rem;">&hellip;</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($p = $window_start; $p <= $window_end; $p++): ?>
                        <a href="<?= page_url_st($p, $search, $filter_class, $filter_subject) ?>"
                           class="page-btn <?= $p === $page ? 'active' : '' ?>"
                           style="min-width: 36px; font-size: .82rem;">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($window_end < $total_pages): ?>
                        <?php if ($window_end < $total_pages - 1): ?>
                            <span style="padding: 0 4px; color: var(--text-muted); font-size: .8rem;">&hellip;</span>
                        <?php endif; ?>
                        <a href="<?= page_url_st($total_pages, $search, $filter_class, $filter_subject) ?>" class="page-btn"><?= $total_pages ?></a>
                    <?php endif; ?>

                    <!-- Next + Last -->
                    <a href="<?= page_url_st(min($total_pages, $page + 1), $search, $filter_class, $filter_subject) ?>"
                       class="page-btn <?= $page === $total_pages ? 'disabled' : '' ?>"
                       title="Next page"
                       style="font-size: .8rem;">Next &rsaquo;</a>

                    <a href="<?= page_url_st($total_pages, $search, $filter_class, $filter_subject) ?>"
                       class="page-btn <?= $page === $total_pages ? 'disabled' : '' ?>"
                       title="Last page"
                       style="font-size: .8rem;">&raquo;</a>

                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- EDIT MODAL -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <h3>Edit Student</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="student_id" id="edit_id">
            <div class="form-group" style="margin-bottom:14px;">
                <label>Full Name <span style="color:var(--danger-color)">*</span></label>
                <input type="text" name="name" id="edit_name" required>
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label>Exam Number <span style="color:var(--danger-color)">*</span></label>
                <input type="text" name="exam_number" id="edit_exam_number" required>
            </div>
            <div class="form-group" style="margin-bottom:20px;">
                <label>Class / Form <span style="color:var(--danger-color)">*</span></label>
                <select name="class" id="edit_class" required>
                    <option value="Form 2">Form 2 (JCE)</option>
                    <option value="Form 4">Form 4 (MSCE)</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeEdit()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-dark">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- SUBJECTS MODAL (edit subjects) -->
<div id="subjectsModal" class="modal">
    <div class="modal-content" style="max-width:560px;">
        <h3>Registered Subjects &mdash; <span id="subj_student_name"></span></h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:16px;">
            Select all subjects this student will sit for the examination. At least one subject is required.
        </p>
        <div id="subjectsError" style="display:none; background:#fee2e2; border:1px solid #fecdd3; color:#991b1b;
             padding:10px 14px; border-radius:8px; font-size:.85rem; margin-bottom:12px;">
            Please select at least one subject before saving.
        </div>
        <form method="POST" id="subjectsForm" onsubmit="return validateSubjectsForm()">
            <input type="hidden" name="action" value="assign_subjects">
            <input type="hidden" name="student_id" id="subj_student_id">
            <div id="subjects_checkboxes" class="subjects-scroll"
                 style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:20px;">
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeSubjectsModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-dark">Save Subjects</button>
            </div>
        </form>
    </div>
</div>

<!-- STUDENT DETAILS MODAL (read + subjects panel) -->
<div id="detailsModal" class="modal">
    <div class="modal-content modal-content--wide">
        <h3 id="details_heading">Student Details</h3>
        <div id="details_body"></div>
        <div class="modal-actions" style="margin-top:20px;">
            <button type="button" class="btn btn-edit" id="modal_edit_btn">Edit Student</button>
            <form method="POST" id="modal_status_form" style="display:inline;">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="student_id" id="modal_status_student_id">
                <input type="hidden" name="current_status" id="modal_status_current_status">
                <button type="submit" class="btn btn-small" id="modal_status_btn">Deactivate</button>
            </form>
            <button type="button" onclick="closeDetailsModal()" class="btn btn-secondary">Close</button>
        </div>
    </div>
</div>

<script>
const allSubjects = <?= json_encode($all_subjects) ?>;

const categoryChipMap = {
    'science':    'subject-chip--science',
    'language':   'subject-chip--language',
    'humanities': 'subject-chip--humanities',
};

/* Build subjects checkbox panel for registration forms */
function updateSubjectsCheckboxPanel(formClass, containerId) {
    const container = document.getElementById(containerId);
    container.innerHTML = '<strong style="font-size:.875rem;color:#0f172a;">Select Exam Subjects <span style="color:var(--danger-color)">*</span></strong><div id="' + containerId + '_grid" style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;"></div>';
    const grid = document.getElementById(containerId + '_grid');
    const compulsoryNames = ['Mathematics', 'Chichewa', 'English', 'Biology', 'Agriculture'];

    allSubjects.forEach(function(subj) {
        const isCompulsory = compulsoryNames.includes(subj.subject_name) || (formClass === 'Form 4' && subj.category === 'science');
        const label = document.createElement('label');
        label.style.cssText = 'display:flex;align-items:center;gap:8px;padding:7px 10px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;cursor:' + (isCompulsory ? 'not-allowed' : 'pointer') + ';font-size:.85rem;';

        const checkbox = document.createElement('input');
        checkbox.type  = 'checkbox';
        checkbox.value = subj.subject_id;

        if (isCompulsory) {
            checkbox.checked  = true;
            checkbox.disabled = true;
            const hidden = document.createElement('input');
            hidden.type  = 'hidden';
            hidden.name  = 'subject_ids[]';
            hidden.value = subj.subject_id;
            label.appendChild(hidden);
        } else {
            checkbox.name = 'subject_ids[]';
        }

        label.appendChild(checkbox);
        const txt = document.createElement('span');
        txt.innerHTML = '<strong>' + subj.subject_code + '</strong> ' + subj.subject_name;
        label.appendChild(txt);
        grid.appendChild(label);
    });
}

/* Validate: at least one subject must be selected in new-student form */
function validateNewStudent(form) {
    const checked = form.querySelectorAll('input[name="subject_ids[]"]:not([disabled])');
    let hasChecked = false;
    checked.forEach(function(c) { if (c.checked) hasChecked = true; });
    /* Also count hidden (compulsory) — those always count */
    const hidden = form.querySelectorAll('input[type="hidden"][name="subject_ids[]"]');
    if (hidden.length > 0 || hasChecked) return true;
    document.getElementById('newSubjectsError').style.display = 'block';
    return false;
}

/* Edit modal */
function openEdit(s) {
    document.getElementById('edit_id').value          = s.student_id;
    document.getElementById('edit_name').value        = s.name;
    document.getElementById('edit_exam_number').value = s.exam_number;
    document.getElementById('edit_class').value       = s.class || 'Form 2';
    document.getElementById('editModal').classList.add('show');
}
function closeEdit() { document.getElementById('editModal').classList.remove('show'); }
document.getElementById('editModal').addEventListener('click', function(e){ if(e.target===this) closeEdit(); });

/* Tab switching */
function switchTab(tab) {
    document.getElementById('panel-existing').style.display = tab === 'existing' ? '' : 'none';
    document.getElementById('panel-new').style.display      = tab === 'new'      ? '' : 'none';
    const tabExisting = document.getElementById('tab-existing');
    const tabNew      = document.getElementById('tab-new');
    if (tab === 'existing') {
        tabExisting.style.fontWeight = '700'; tabExisting.style.color = 'var(--primary-dark)'; tabExisting.style.borderBottom = '2px solid var(--primary-dark)';
        tabNew.style.fontWeight = '600'; tabNew.style.color = 'var(--text-muted)'; tabNew.style.borderBottom = 'none';
    } else {
        tabNew.style.fontWeight = '700'; tabNew.style.color = 'var(--primary-dark)'; tabNew.style.borderBottom = '2px solid var(--primary-dark)';
        tabExisting.style.fontWeight = '600'; tabExisting.style.color = 'var(--text-muted)'; tabExisting.style.borderBottom = 'none';
    }
}

/* Auto-fill student preview */
function fillStudentDetails(sel) {
    const opt  = sel.selectedOptions[0];
    const info = document.getElementById('selectedStudentInfo');
    if (!sel.value) { info.textContent = 'Select a student above to preview details.'; return; }
    const cls = opt.dataset.class ? ' &mdash; Current class: <strong>' + opt.dataset.class + '</strong>' : '';
    info.innerHTML = '<strong>Name:</strong> ' + opt.dataset.name + ' &nbsp;|&nbsp; <strong>Exam No:</strong> ' + opt.dataset.exam + cls;
    const classSelect = document.getElementById('existingClassSelect');
    if (opt.dataset.class && (opt.dataset.class === 'Form 2' || opt.dataset.class === 'Form 4')) {
        classSelect.value = opt.dataset.class;
        updateSubjectsCheckboxPanel(classSelect.value, 'existingSubjects');
    }
}

/* Subjects modal */
function openSubjectsModal(studentId, studentName, currentSubjectIds) {
    document.getElementById('subj_student_id').value = studentId;
    document.getElementById('subj_student_name').textContent = studentName;
    document.getElementById('subjectsError').style.display = 'none';
    const container = document.getElementById('subjects_checkboxes');
    container.innerHTML = '';
    currentSubjectIds = currentSubjectIds || [];
    const compulsoryNames = ['Mathematics', 'Chichewa', 'English', 'Biology', 'Agriculture'];

    allSubjects.forEach(function(subj) {
        const isCompulsory = compulsoryNames.includes(subj.subject_name);
        const checked      = isCompulsory || currentSubjectIds.includes(parseInt(subj.subject_id));

        const label = document.createElement('label');
        label.style.cssText = 'display:flex;align-items:center;gap:8px;padding:7px 10px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;cursor:' + (isCompulsory ? 'not-allowed' : 'pointer') + ';font-size:.85rem;';

        const checkbox  = document.createElement('input');
        checkbox.type   = 'checkbox';
        checkbox.value  = subj.subject_id;

        if (isCompulsory) {
            checkbox.checked  = true;
            checkbox.disabled = true;
            const hidden = document.createElement('input');
            hidden.type  = 'hidden';
            hidden.name  = 'subject_ids[]';
            hidden.value = subj.subject_id;
            label.appendChild(hidden);
        } else {
            checkbox.checked = checked;
            checkbox.name    = 'subject_ids[]';
        }

        const span = document.createElement('span');
        span.innerHTML = '<strong>' + subj.subject_code + '</strong> &mdash; ' + subj.subject_name;
        label.appendChild(checkbox);
        label.appendChild(span);
        container.appendChild(label);
    });
    document.getElementById('subjectsModal').classList.add('show');
}

function validateSubjectsForm() {
    const checked = document.querySelectorAll('#subjects_checkboxes input[name="subject_ids[]"]:not([disabled]):checked');
    const hidden  = document.querySelectorAll('#subjects_checkboxes input[type="hidden"][name="subject_ids[]"]');
    if (checked.length > 0 || hidden.length > 0) return true;
    document.getElementById('subjectsError').style.display = 'block';
    return false;
}

function closeSubjectsModal() { document.getElementById('subjectsModal').classList.remove('show'); }
document.getElementById('subjectsModal').addEventListener('click', function(e){ if(e.target===this) closeSubjectsModal(); });

/* Details modal */
function openDetailsModal(studentId, studentName, examNumber, studentClass, status) {
    document.getElementById('details_heading').textContent = studentName;
    document.getElementById('details_body').innerHTML = '<div class="empty-state">Loading student details...</div>';
    document.getElementById('detailsModal').classList.add('show');

    // Populate Edit Student Button
    const editBtn = document.getElementById('modal_edit_btn');
    editBtn.onclick = function() {
        closeDetailsModal();
        openEdit({
            student_id: studentId,
            name: studentName,
            exam_number: examNumber,
            class: studentClass
        });
    };

    // Populate Status Toggle Button/Form
    document.getElementById('modal_status_student_id').value = studentId;
    document.getElementById('modal_status_current_status').value = status;
    const statusBtn = document.getElementById('modal_status_btn');
    if (status === 'active') {
        statusBtn.textContent = 'Deactivate';
        statusBtn.className = 'btn btn-small btn-danger';
    } else {
        statusBtn.textContent = 'Activate';
        statusBtn.className = 'btn btn-small btn-success';
    }

    fetch('../common/get_student_details.php?student_id=' + studentId + '&show_print=true')
        .then(response => response.text())
        .then(html => {
            document.getElementById('details_body').innerHTML = html;
        })
        .catch(err => {
            document.getElementById('details_body').innerHTML = '<div class="alert alert-error">Error loading details.</div>';
        });
}
function closeDetailsModal() { document.getElementById('detailsModal').classList.remove('show'); }
document.getElementById('detailsModal').addEventListener('click', function(e){ if(e.target===this) closeDetailsModal(); });
</script>

<?php include '../common/footer.php'; ?>
</body>
</html>
