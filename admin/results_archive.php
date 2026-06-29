<?php
/* ════════════════════════════════════════════════════════════════
   admin/results_archive.php
   EDM/Admin: searchable historical archive of published results
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once '../config/db.php';
require_once '../common/grade_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php"); exit();
}

$conn = get_db_connection();

/* ── FILTERS ── */
$exam_id   = (int)($_GET['exam_id'] ?? 0);
$school_id = (int)($_GET['school_id'] ?? 0);
$class     = trim($_GET['class'] ?? '');
$year      = trim($_GET['year'] ?? '');
$search    = trim($_GET['search'] ?? '');

/* ── MASTER DATA FOR FILTER DROPDOWNS ── */
$all_exams = $conn->query("SELECT exam_id, exam_name FROM exams ORDER BY exam_name")->fetch_all(MYSQLI_ASSOC);
$all_schools = $conn->query("SELECT school_id, school_name FROM schools WHERE status='active' ORDER BY school_name")->fetch_all(MYSQLI_ASSOC);
$all_classes = $conn->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL ORDER BY class")->fetch_all(MYSQLI_ASSOC);
$all_years = $conn->query("SELECT DISTINCT year FROM results ORDER BY year DESC")->fetch_all(MYSQLI_ASSOC);

/* ── BUILD QUERY ── */
$where_clauses = ["r.status = 'published'"];
$params = [];
$types = "";

if ($exam_id > 0) {
    $where_clauses[] = "r.exam_id = ?";
    $params[] = $exam_id;
    $types .= "i";
}
if ($school_id > 0) {
    $where_clauses[] = "st.school_id = ?";
    $params[] = $school_id;
    $types .= "i";
}
if ($class !== '') {
    $where_clauses[] = "st.class = ?";
    $params[] = $class;
    $types .= "s";
}
if ($year !== '') {
    $where_clauses[] = "r.year = ?";
    $params[] = $year;
    $types .= "s";
}
if ($search !== '') {
    $where_clauses[] = "(st.name LIKE ? OR st.exam_number LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$where_sql = implode(" AND ", $where_clauses);

$list_query = "
    SELECT r.result_id, r.total_subjects, r.total_score, r.average_score, r.grade, r.position_in_class,
           r.term, r.year AS exam_year, r.class AS exam_class,
           st.name AS student_name, st.exam_number,
           sc.school_name, e.exam_name
    FROM results r
    JOIN students st ON r.student_id = st.student_id
    LEFT JOIN schools sc ON sc.school_id = st.school_id
    JOIN exams e ON r.exam_id = e.exam_id
    WHERE {$where_sql}
    ORDER BY r.year DESC, r.term DESC, r.average_score DESC
    LIMIT 150
";

$stmt = $conn->prepare($list_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$results_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
$module_css = 'admin';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Results Archive | NED-SEMS</title>
</head>
<body>
<?php include '../common/header.php'; ?>
<div class="dashboard">
    <?php include '../common/sidebar.php'; ?>
    <div class="content">

        <!-- Header -->
        <div class="page-header">
            <div>
                <h2 class="page-title">Results Archive</h2>
                <p class="page-subtitle">Search and view historical student results, class ranks, and printable academic report cards</p>
            </div>
            <a href="manage_results.php" class="btn btn-secondary">Results Dashboard</a>
        </div>

        <!-- Filter Bar -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="section-header"><h3>Search Archive</h3></div>
            <form method="GET">
                <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Search Student</label>
                        <input type="text" name="search" placeholder="Enter student name or exam number..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="form-group">
                        <label>Exam</label>
                        <select name="exam_id">
                            <option value="">— All Exams —</option>
                            <?php foreach ($all_exams as $ex): ?>
                                <option value="<?= $ex['exam_id'] ?>" <?= $exam_id === (int)$ex['exam_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ex['exam_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>School</label>
                        <select name="school_id">
                            <option value="">— All Schools —</option>
                            <?php foreach ($all_schools as $sch): ?>
                                <option value="<?= $sch['school_id'] ?>" <?= $school_id === (int)$sch['school_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sch['school_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Class</label>
                        <select name="class">
                            <option value="">— All Classes —</option>
                            <?php foreach ($all_classes as $cl): ?>
                                <option value="<?= htmlspecialchars($cl['class']) ?>" <?= $class === $cl['class'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cl['class']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Year</label>
                        <select name="year">
                            <option value="">— All Years —</option>
                            <?php foreach ($all_years as $yr): ?>
                                <option value="<?= htmlspecialchars($yr['year']) ?>" <?= $year === $yr['year'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($yr['year']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-actions" style="margin-top: 16px;">
                    <button type="submit" class="btn btn-dark">Search Archive</button>
                    <a href="results_archive.php" class="btn btn-secondary">Clear All</a>
                </div>
            </form>
        </div>

        <!-- Results Table -->
        <div class="card">
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3>Published Results Archive (Showing up to 150 records)</h3>
                <span class="badge badge-success"><?= count($results_list) ?> records found</span>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Exam Number</th>
                            <th>School</th>
                            <th>Exam Name</th>
                            <th>Class</th>
                            <th>Term / Year</th>
                            <th style="text-align: center;">Score</th>
                            <th style="text-align: center;">Average</th>
                            <th style="text-align: center;">Grade</th>
                            <th style="text-align: center;">Rank</th>
                            <th style="text-align: center; width: 130px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($results_list)): ?>
                            <tr>
                                <td colspan="11" class="empty-state">No published results found matching the search criteria.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($results_list as $r): ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($r['student_name']) ?></td>
                                <td><code><?= htmlspecialchars($r['exam_number']) ?></code></td>
                                <td style="font-size: 0.8rem;"><?= htmlspecialchars($r['school_name'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($r['exam_name']) ?></td>
                                <td><?= htmlspecialchars($r['exam_class']) ?></td>
                                <td><?= htmlspecialchars($r['term']) ?> / <?= htmlspecialchars($r['exam_year']) ?></td>
                                <td style="text-align: center; font-weight: bold;"><?= htmlspecialchars($r['total_score']) ?></td>
                                <td style="text-align: center; font-weight: 600; color: var(--primary-dark);"><?= number_format($r['average_score'], 1) ?>%</td>
                                <td style="text-align: center;">
                                    <span class="grade-badge" style="background: var(--border-color); color: var(--text-color); font-weight: 700;">
                                        <?= htmlspecialchars($r['grade'] ?: '—') ?>
                                    </span>
                                </td>
                                <td style="text-align: center; font-weight: bold;"><?= htmlspecialchars($r['position_in_class'] ?: '—') ?></td>
                                <td style="text-align: center;">
                                    <a href="student_report_card.php?result_id=<?= $r['result_id'] ?>" target="_blank" class="btn btn-secondary btn-small">
                                        Report Card
                                    </a>
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
<?php include '../common/footer.php'; ?>
</body>
</html>
