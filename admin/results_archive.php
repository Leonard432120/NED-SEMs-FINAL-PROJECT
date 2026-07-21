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
$all_exams   = $conn->query("SELECT exam_id, exam_name FROM exams ORDER BY exam_name")->fetch_all(MYSQLI_ASSOC);
$all_schools = $conn->query("SELECT school_id, school_name FROM schools WHERE status='active' ORDER BY school_name")->fetch_all(MYSQLI_ASSOC);
$all_classes = $conn->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL ORDER BY class")->fetch_all(MYSQLI_ASSOC);
$all_years   = $conn->query("SELECT DISTINCT year FROM results ORDER BY year DESC")->fetch_all(MYSQLI_ASSOC);

/* ── BUILD FILTER CLAUSES ── */
$where_clauses = ["r.status = 'published'"];
$params = [];
$types  = "";

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

/* ── PAGINATION ── */
$per_page = 10;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

/* ── COUNT (same filters, no LIMIT) ── */
$count_query = "
    SELECT COUNT(*) AS cnt
    FROM results r
    JOIN students st ON r.student_id = st.student_id
    LEFT JOIN schools sc ON sc.school_id = st.school_id
    JOIN exams e ON r.exam_id = e.exam_id
    WHERE {$where_sql}
";
$cs = $conn->prepare($count_query);
if (!empty($params)) {
    $cs->bind_param($types, ...$params);
}
$cs->execute();
$total_rows  = (int)$cs->get_result()->fetch_assoc()['cnt'];
$cs->close();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

/* ── MAIN LIST QUERY ──
   computed_rank is a live fallback rank (by average_score) within the
   same exam + class + term + year, used only when position_in_class
   was never saved by the publish-results step. */
$list_query = "
    SELECT r.result_id, r.total_subjects, r.total_score, r.average_score, r.grade, r.position_in_class,
           r.term, r.year AS exam_year, r.class AS exam_class,
           st.student_id, st.name AS student_name, st.exam_number,
           sc.school_name, e.exam_name,
           RANK() OVER (
               PARTITION BY r.exam_id, r.class, r.term, r.year
               ORDER BY r.average_score DESC
           ) AS computed_rank
    FROM results r
    JOIN students st ON r.student_id = st.student_id
    LEFT JOIN schools sc ON sc.school_id = st.school_id
    JOIN exams e ON r.exam_id = e.exam_id
    WHERE {$where_sql}
    ORDER BY r.year DESC, r.term DESC, r.average_score DESC
    LIMIT ? OFFSET ?
";
$list_params = $params;
$list_types  = $types . "ii";
$list_params[] = $per_page;
$list_params[] = $offset;

$stmt = $conn->prepare($list_query);
$stmt->bind_param($list_types, ...$list_params);
$stmt->execute();
$results_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
$module_css = 'admin';
include __DIR__ . '/../common/head_assets.php';

/* ── URL BUILDER FOR PAGINATION ── */
function page_url_archive(int $p, int $exam_id, int $school_id, string $class, string $year, string $search): string {
    return 'results_archive.php?' . http_build_query(array_filter([
        'page'      => $p,
        'exam_id'   => $exam_id ?: null,
        'school_id' => $school_id ?: null,
        'class'     => $class,
        'year'      => $year,
        'search'    => $search,
    ], fn($v) => $v !== null && $v !== ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <title>Results Archive | NED-SEMS</title>
    <style>
        /* Pagination — same styling as manage_candidates.php */
        .pagination-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }
        .pagination-info {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .pagination-links {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .page-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            background: var(--card-color);
            color: var(--text-color);
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.15s, border-color 0.15s;
        }
        .page-btn:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }
        .page-btn.active {
            background: var(--primary-dark);
            color: #fff;
            border-color: var(--primary-dark);
        }
        .page-btn.disabled {
            opacity: 0.4;
            pointer-events: none;
        }
    </style>
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
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search name or exam number…"
                    value="<?= htmlspecialchars($search) ?>">

                <select name="exam_id">
                    <option value="">All Exams</option>
                    <?php foreach ($all_exams as $ex): ?>
                        <option value="<?= $ex['exam_id'] ?>" <?= $exam_id === (int)$ex['exam_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ex['exam_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="school_id">
                    <option value="">All Schools</option>
                    <?php foreach ($all_schools as $sch): ?>
                        <option value="<?= $sch['school_id'] ?>" <?= $school_id === (int)$sch['school_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sch['school_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="class">
                    <option value="">All Classes</option>
                    <?php foreach ($all_classes as $cl): ?>
                        <option value="<?= htmlspecialchars($cl['class']) ?>" <?= $class === $cl['class'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cl['class']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="year">
                    <option value="">All Years</option>
                    <?php foreach ($all_years as $yr): ?>
                        <option value="<?= htmlspecialchars($yr['year']) ?>" <?= $year === $yr['year'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($yr['year']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-dark">Search Archive</button>
                <a href="results_archive.php" class="btn btn-secondary">Clear All</a>
            </form>
        </div>

        <!-- Results Table -->
        <div class="card">
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3>Published Results Archive</h3>
                <span class="muted-text" style="font-size:.85rem;">
                    Showing <?= $total_rows > 0 ? $offset + 1 : 0 ?>–<?= min($offset + $per_page, $total_rows) ?> of <?= $total_rows ?>
                </span>
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
                            <?php foreach ($results_list as $r):
                                $rank = $r['position_in_class'] !== null && $r['position_in_class'] !== ''
                                    ? $r['position_in_class']
                                    : $r['computed_rank'];
                            ?>
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
                                    <?php
                                        $grade_val   = $r['grade'] ?: '';
                                        $grade_name  = $grade_val !== '' ? gradeLabel($grade_val) : '—';
                                        $grade_class = $grade_val !== '' ? gradeColor($grade_val) : 'secondary';
                                    ?>
                                    <span class="grade-badge badge-<?= $grade_class ?>" style="font-weight: 700;">
                                        <?= htmlspecialchars($grade_name) ?>
                                    </span>
                                </td>
                                <td style="text-align: center; font-weight: bold;"><?= htmlspecialchars($rank ?: '—') ?></td>
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

            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-bar">
                <div class="pagination-info">Page <?= $page ?> of <?= $total_pages ?></div>
                <div class="pagination-links">
                    <a href="<?= page_url_archive(1, $exam_id, $school_id, $class, $year, $search) ?>"
                       class="page-btn <?= $page === 1 ? 'disabled' : '' ?>">First</a>
                    <a href="<?= page_url_archive(max(1, $page - 1), $exam_id, $school_id, $class, $year, $search) ?>"
                       class="page-btn <?= $page === 1 ? 'disabled' : '' ?>">Prev</a>

                    <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                        <a href="<?= page_url_archive($p, $exam_id, $school_id, $class, $year, $search) ?>"
                           class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>

                    <a href="<?= page_url_archive(min($total_pages, $page + 1), $exam_id, $school_id, $class, $year, $search) ?>"
                       class="page-btn <?= $page === $total_pages ? 'disabled' : '' ?>">Next</a>
                    <a href="<?= page_url_archive($total_pages, $exam_id, $school_id, $class, $year, $search) ?>"
                       class="page-btn <?= $page === $total_pages ? 'disabled' : '' ?>">Last</a>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>
<?php include '../common/footer.php'; ?>
</body>
</html>