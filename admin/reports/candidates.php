<?php
/* ════════════════════════════════════════════════════════════════
   admin/reports/candidates.php
   EDM/Admin: Candidates reports and demographics
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../common/pagination_helper.php';
require_once __DIR__ . '/../../common/report_stats.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

$conn = get_db_connection();

// Get list of exams, schools, districts and classes for filter dropdowns
$exams_list = $conn->query("SELECT exam_id, exam_name, class, year FROM exams ORDER BY year DESC, start_date DESC")->fetch_all(MYSQLI_ASSOC);
$schools_list = $conn->query("SELECT school_id, school_name FROM schools WHERE status='active' ORDER BY school_name ASC")->fetch_all(MYSQLI_ASSOC);
$districts_list = $conn->query("SELECT DISTINCT district FROM schools WHERE status='active' ORDER BY district ASC")->fetch_all(MYSQLI_ASSOC);
$classes_list = $conn->query("SELECT DISTINCT class FROM students WHERE status='active' ORDER BY class ASC")->fetch_all(MYSQLI_ASSOC);

// Get filters
$selected_exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$selected_school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;
$selected_district = isset($_GET['district']) ? trim($_GET['district']) : '';
$selected_class = isset($_GET['class']) ? trim($_GET['class']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'name';
$sort_order = isset($_GET['sort_order']) && strtolower($_GET['sort_order']) === 'desc' ? 'DESC' : 'ASC';

// Allowed sorting fields to prevent SQL injection
$allowed_sorts = ['name', 'exam_number', 'school_name', 'district', 'gender', 'special_needs', 'class'];
if (!in_array($sort_by, $allowed_sorts, true)) {
    $sort_by = 'name';
}

// Build query conditions
$where_clauses = ["s.status = 'active'"];
$params = [];
$types = "";

// Class filter: an explicit Class dropdown takes priority. If no class was
// picked directly but an Exam is selected, fall back to that exam's class
// (keeps old behavior working for exam-specific views).
if ($selected_class !== '') {
    $where_clauses[] = "s.class = ?";
    $params[] = $selected_class;
    $types .= "s";
} elseif ($selected_exam_id > 0) {
    $ex_stmt = $conn->prepare("SELECT class FROM exams WHERE exam_id = ?");
    $ex_stmt->bind_param("i", $selected_exam_id);
    $ex_stmt->execute();
    $ex_class = $ex_stmt->get_result()->fetch_assoc()['class'] ?? '';
    $ex_stmt->close();

    if ($ex_class !== '') {
        $where_clauses[] = "s.class = ?";
        $params[] = $ex_class;
        $types .= "s";
    }
}

if ($selected_school_id > 0) {
    $where_clauses[] = "s.school_id = ?";
    $params[] = $selected_school_id;
    $types .= "i";
}

if ($selected_district !== '') {
    $where_clauses[] = "sch.district = ?";
    $params[] = $selected_district;
    $types .= "s";
}

if ($search !== '') {
    $where_clauses[] = "(s.name LIKE ? OR s.exam_number LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$where_sql = implode(" AND ", $where_clauses);

// Fetch summary metrics (Registered, Sat, Absent, Gender, Special Needs)
// 1. Total Registered
$count_query = "SELECT COUNT(*) as c FROM students s JOIN schools sch ON s.school_id = sch.school_id WHERE {$where_sql}";
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_registered = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

// 1b. Registered breakdown by class (ignores the Class filter itself, so the
// Registered card can always show how many are in each class; still respects
// school/district/search filters).
$class_where_clauses = ["s.status = 'active'"];
$class_params = [];
$class_types = "";

if ($selected_school_id > 0) {
    $class_where_clauses[] = "s.school_id = ?";
    $class_params[] = $selected_school_id;
    $class_types .= "i";
}
if ($selected_district !== '') {
    $class_where_clauses[] = "sch.district = ?";
    $class_params[] = $selected_district;
    $class_types .= "s";
}
if ($search !== '') {
    $class_where_clauses[] = "(s.name LIKE ? OR s.exam_number LIKE ?)";
    $class_search_param = "%{$search}%";
    $class_params[] = $class_search_param;
    $class_params[] = $class_search_param;
    $class_types .= "ss";
}
$class_where_sql = implode(" AND ", $class_where_clauses);

$by_class_query = "SELECT s.class, COUNT(*) as c FROM students s JOIN schools sch ON s.school_id = sch.school_id WHERE {$class_where_sql} GROUP BY s.class ORDER BY s.class ASC";
$stmt = $conn->prepare($by_class_query);
if (!empty($class_params)) {
    $stmt->bind_param($class_types, ...$class_params);
}
$stmt->execute();
$registered_by_class = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 2. Sat Candidates (Have results published for the selected exam if filtered, otherwise globally)
$sat_query = "SELECT COUNT(DISTINCT r.student_id) as c FROM results r 
              JOIN students s ON r.student_id = s.student_id 
              JOIN schools sch ON s.school_id = sch.school_id 
              WHERE r.status='published' AND " . ($selected_exam_id > 0 ? "r.exam_id = ? AND " : "") . $where_sql;

$stmt = $conn->prepare($sat_query);
$sat_params = [];
$sat_types = "";
if ($selected_exam_id > 0) {
    $sat_params[] = $selected_exam_id;
    $sat_types .= "i";
}
$sat_params = array_merge($sat_params, $params);
$sat_types .= $types;

if (!empty($sat_params)) {
    $stmt->bind_param($sat_types, ...$sat_params);
}
$stmt->execute();
$total_sat = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$total_absent = max(0, $total_registered - $total_sat);
$participation_rate = $total_registered > 0 ? round(($total_sat / $total_registered) * 100, 1) : 0.0;

// 3. Gender breakdown
$male_query = "SELECT COUNT(*) as c FROM students s JOIN schools sch ON s.school_id = sch.school_id WHERE s.gender = 'Male' AND {$where_sql}";
$stmt = $conn->prepare($male_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_male = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();
$total_female = max(0, $total_registered - $total_male);

// 4. Special Needs
$sn_query = "SELECT COUNT(*) as c FROM students s JOIN schools sch ON s.school_id = sch.school_id WHERE s.special_needs != 'None' AND {$where_sql}";
$stmt = $conn->prepare($sn_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_sn = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 5;
$pagination = paginate($total_registered, $page, $per_page);
$offset = ($pagination['page'] - 1) * $pagination['per_page'];

// Fetch detailed candidates list
$list_query = "
    SELECT s.student_id, s.name, s.exam_number, s.class, s.gender, s.special_needs, sch.school_name, sch.district,
           r.average_score, r.grade
    FROM students s
    JOIN schools sch ON s.school_id = sch.school_id
    LEFT JOIN results r ON s.student_id = r.student_id AND r.status='published' " . ($selected_exam_id > 0 ? "AND r.exam_id = {$selected_exam_id}" : "") . "
    WHERE {$where_sql}
    ORDER BY {$sort_by} {$sort_order}
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($list_query);
$list_params = $params;
$list_types = $types;
$list_params[] = $per_page;
$list_params[] = $offset;
$list_types .= "ii";

$stmt->bind_param($list_types, ...$list_params);
$stmt->execute();
$candidates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

$portal_title = 'NED-SEMS | Candidate Reports';
$module_css = 'admin';
include __DIR__ . '/../../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Candidate Reports | NED-SEMS</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
    <style>
        .export-btn {
            background-color: #0d9488;
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            transition: background 0.2s;
        }
        .export-btn:hover {
            background-color: #0f766e;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../common/header.php'; ?>

<div class="dashboard">
    <?php include __DIR__ . '/../../common/sidebar.php'; ?>

    <div class="content">
        <!-- Print Header -->
        <div class="print-header">
            <h2 class="print-title">Candidate Demographics & Participation Report</h2>
            <div class="print-meta">Generated: <?= date('Y-m-d') ?> | Prepared By: Administrator</div>
        </div>

        <div class="page-header">
            <div>
                <h2 class="page-title">Candidate Reports & Demographics</h2>
                <p class="page-subtitle">Candidate list, regional distributions, gender representation, and exam participation rates</p>
            </div>
            <div class="header-actions">
                <button onclick="window.print()" class="btn btn-secondary">Print Report</button>
                <button onclick="exportToCSV()" class="export-btn">Export CSV</button>
            </div>
        </div>

        <!-- Filter Panel -->
        <div class="rpt-filter-panel">
            <form method="GET" class="rpt-filter-form">
                <div class="rpt-filter-group">
                    <label class="rpt-filter-label">Examination</label>
                    <select name="exam_id" class="rpt-filter-select" onchange="this.form.submit()">
                        <option value="0">All Examinations</option>
                        <?php foreach ($exams_list as $ex): ?>
                            <option value="<?= $ex['exam_id'] ?>" <?= $selected_exam_id === (int)$ex['exam_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ex['exam_name']) ?> (<?= htmlspecialchars($ex['year']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="rpt-filter-group">
                    <label class="rpt-filter-label">Class</label>
                    <select name="class" class="rpt-filter-select" onchange="this.form.submit()">
                        <option value="">All Classes</option>
                        <?php foreach ($classes_list as $cl): ?>
                            <option value="<?= htmlspecialchars($cl['class']) ?>" <?= $selected_class === $cl['class'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cl['class']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="rpt-filter-group">
                    <label class="rpt-filter-label">District</label>
                    <select name="district" class="rpt-filter-select" onchange="this.form.submit()">
                        <option value="">All Districts</option>
                        <?php foreach ($districts_list as $d): ?>
                            <option value="<?= htmlspecialchars($d['district']) ?>" <?= $selected_district === $d['district'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['district']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="rpt-filter-group">
                    <label class="rpt-filter-label">School</label>
                    <select name="school_id" class="rpt-filter-select" onchange="this.form.submit()">
                        <option value="0">All Schools</option>
                        <?php foreach ($schools_list as $sc): ?>
                            <option value="<?= $sc['school_id'] ?>" <?= $selected_school_id === (int)$sc['school_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sc['school_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="rpt-filter-group">
                    <label class="rpt-filter-label">Search Candidate</label>
                    <input type="text" name="search" class="rpt-filter-input" placeholder="Name or Exam Number..." value="<?= htmlspecialchars($search) ?>">
                </div>

                <div class="rpt-filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="candidates.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- Summary KPI Cards -->
        <div class="rpt-kpi-grid">
            <div class="rpt-kpi-card rpt-kpi--blue">
                <span class="rpt-kpi-label">Registered Candidates</span>
                <span class="rpt-kpi-value"><?= $total_registered ?></span>
                <span class="rpt-kpi-sub">Total Active Candidates</span>
                <?php if (!empty($registered_by_class)): ?>
                    <div class="rpt-kpi-breakdown" style="margin-top:8px; display:flex; flex-direction:column; gap:2px;">
                        <?php foreach ($registered_by_class as $rbc): ?>
                            <span style="font-size:0.78rem; color:var(--text-muted); display:flex; justify-content:space-between; gap:12px;">
                                <span><?= htmlspecialchars($rbc['class']) ?></span>
                                <span style="font-weight:600;"><?= (int)$rbc['c'] ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="rpt-kpi-card rpt-kpi--green">
                <span class="rpt-kpi-label">Sat Examination</span>
                <span class="rpt-kpi-value"><?= $total_sat ?></span>
                <span class="rpt-kpi-sub">Participation: <?= $participation_rate ?>%</span>
            </div>
            <div class="rpt-kpi-card rpt-kpi--red">
                <span class="rpt-kpi-label">Absent Candidates</span>
                <span class="rpt-kpi-value"><?= $total_absent ?></span>
                <span class="rpt-kpi-sub">No Marks Recorded</span>
            </div>
            <div class="rpt-kpi-card rpt-kpi--teal">
                <span class="rpt-kpi-label">Gender (M / F)</span>
                <span class="rpt-kpi-value" style="font-size:1.8rem;"><?= $total_male ?> / <?= $total_female ?></span>
                <span class="rpt-kpi-sub">Male vs Female Breakdown</span>
            </div>
            <div class="rpt-kpi-card rpt-kpi--purple">
                <span class="rpt-kpi-label">Special Needs</span>
                <span class="rpt-kpi-value"><?= $total_sn ?></span>
                <span class="rpt-kpi-sub">Identified Special Services</span>
            </div>
        </div>

        <!-- Advanced Table of Candidates -->
        <div class="card">
            <div class="section-header">
                <h3>Candidate Standings Directory</h3>
                <span style="font-size:0.875rem; color:var(--text-muted);">Showing <?= count($candidates) ?> of <?= $total_registered ?> candidates</span>
            </div>

            <div class="rpt-table-wrap">
                <table class="rpt-table" id="candidatesTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Exam Number</th>
                            <th>School</th>
                            <th>District</th>
                            <th>Class</th>
                            <th>Gender</th>
                            <th>Special Needs</th>
                            <th class="val-col">Average</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($candidates)): ?>
                            <tr>
                                <td colspan="9" class="empty-state">No candidates found matching the selected filters.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($candidates as $c): ?>
                                <?php
                                $avg = $c['average_score'] !== null ? (float)$c['average_score'] : null;
                                $status_badge = '<span class="rpt-badge rpt-badge--nodata">No Data</span>';
                                if ($avg !== null) {
                                    if ($avg >= 40) {
                                        $status_badge = '<span class="rpt-badge rpt-badge--good">Passed</span>';
                                    } else {
                                        $status_badge = '<span class="rpt-badge rpt-badge--poor">Failed</span>';
                                    }
                                }
                                ?>
                                <tr>
                                    <td style="font-weight:600;"><?= htmlspecialchars($c['name']) ?></td>
                                    <td><?= htmlspecialchars($c['exam_number']) ?></td>
                                    <td><?= htmlspecialchars($c['school_name']) ?></td>
                                    <td><?= htmlspecialchars($c['district']) ?></td>
                                    <td><?= htmlspecialchars($c['class']) ?></td>
                                    <td><?= htmlspecialchars($c['gender']) ?></td>
                                    <td>
                                        <?php if ($c['special_needs'] !== 'None'): ?>
                                            <span class="rpt-badge rpt-badge--poor" style="background:#fef3c7; color:#d97706;"><?= htmlspecialchars($c['special_needs']) ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted); font-size:0.8rem;">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="val-col"><?= $avg !== null ? number_format($avg, 1) . '%' : '—' ?></td>
                                    <td><?= $status_badge ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <div style="margin-top:20px; display:flex; justify-content:center;">
                    <?= render_pagination($pagination, 'candidates.php') ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../common/footer.php'; ?>

<script>
function exportToCSV() {
    let table = document.getElementById("candidatesTable");
    let rows = table.querySelectorAll("tr");
    let csv = [];
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length; j++) {
            // Clean content, escape quotes
            let data = cols[j].innerText.replace(/"/g, '""').trim();
            row.push('"' + data + '"');
        }
        csv.push(row.join(","));
    }
    
    let csvString = csv.join("\n");
    let blob = new Blob([csvString], { type: "text/csv;charset=utf-8;" });
    let link = document.createElement("a");
    let url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    link.setAttribute("download", "candidates_report_" + new Date().toISOString().slice(0,10) + ".csv");
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

</body>
</html>