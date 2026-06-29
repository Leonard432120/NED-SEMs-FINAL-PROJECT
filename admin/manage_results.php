<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

/* =========================================================
   FILTERS
========================================================= */
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$class   = $_GET['class'] ?? '';

/* =========================================================
   DASHBOARD STATS
========================================================= */

$total_results = $conn->query("
    SELECT COUNT(*) total
    FROM results
")->fetch_assoc()['total'] ?? 0;

$compiled_results = $conn->query("
    SELECT COUNT(*) total
    FROM results
    WHERE status='draft'
")->fetch_assoc()['total'] ?? 0;

$published_results = $conn->query("
    SELECT COUNT(*) total
    FROM results
    WHERE status='published'
")->fetch_assoc()['total'] ?? 0;

$approved_results = $conn->query("
    SELECT COUNT(*) total
    FROM results
    WHERE status IN ('approved', 'eo_approved', 'head_approved', 'edm_approved')
")->fetch_assoc()['total'] ?? 0;

$average_score = $conn->query("
    SELECT AVG(average_score) avg_score
    FROM results
")->fetch_assoc()['avg_score'] ?? 0;

/* =========================================================
   EXAMS
========================================================= */

$exams = $conn->query("
    SELECT exam_id, exam_name
    FROM exams
    ORDER BY exam_name ASC
");

/* =========================================================
   CLASSES
========================================================= */

$classes = $conn->query("
    SELECT DISTINCT class
    FROM students
    ORDER BY class ASC
");

/* =========================================================
   RESULTS QUERY
========================================================= */

$sql = "
SELECT
    r.*,
    r.average_score AS percentage,
    s.name AS student_name,
    s.class,
    e.exam_name
FROM results r
INNER JOIN students s
    ON r.student_id = s.student_id
INNER JOIN exams e
    ON r.exam_id = e.exam_id
WHERE 1=1
";

if ($exam_id > 0) {
    $sql .= " AND r.exam_id = $exam_id";
}

if (!empty($class)) {
    $safe_class = $conn->real_escape_string($class);
    $sql .= " AND s.class = '$safe_class'";
}

$sql .= " ORDER BY r.total_score DESC";

$results = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Results Dashboard</title>

<?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>

</head>

<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">

<?php include '../common/sidebar.php'; ?>

<div class="content">

    <!-- PAGE HEADER -->

    <div class="page-header">

        <div>
            <h2 class="page-title">
                Results Dashboard
            </h2>

            <p class="page-description">
                Centralized EDM Results Management and Publication Center
            </p>
        </div>

        <div class="header-actions">

            <a href="compile_results.php" class="btn-small btn-dark">
                Compile Results
            </a>

            <a href="publish_results.php" class="btn-small btn-teal">
                Publish Results
            </a>

        </div>

    </div>

    <!-- INFO BOX -->

    <div class="results-info">

        <strong>Results Workflow:</strong><br>

        Teachers Enter Marks →
        EDM Reviews →
        Compile Results →
        Generate Grades & Rankings →
        Publish Results →
        Archive Results

    </div>

    <!-- STATS -->

    <div class="stats-grid">

        <div class="stat-card">
            <h4>Total Results</h4>
            <p><?= number_format($total_results) ?></p>
        </div>

        <div class="stat-card">
            <h4>Compiled (Draft)</h4>
            <p><?= number_format($compiled_results) ?></p>
        </div>

        <div class="stat-card">
            <h4>Published Results</h4>
            <p><?= number_format($published_results) ?></p>
        </div>

        <div class="stat-card">
            <h4>Approved Results</h4>
            <p><?= number_format($approved_results) ?></p>
        </div>

        <div class="stat-card">
            <h4>Average Score</h4>
            <p><?= number_format($average_score, 1) ?>%</p>
        </div>

    </div>

    <!-- FILTERS -->

    <div class="card">

        <form method="GET" class="search-form">

            <select name="exam_id">

                <option value="">
                    All Exams
                </option>

                <?php while($exam = $exams->fetch_assoc()): ?>

                    <option
                        value="<?= $exam['exam_id'] ?>"
                        <?= ($exam_id == $exam['exam_id']) ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($exam['exam_name']) ?>
                    </option>

                <?php endwhile; ?>

            </select>

            <select name="class">

                <option value="">
                    All Classes
                </option>

                <?php while($c = $classes->fetch_assoc()): ?>

                    <option
                        value="<?= htmlspecialchars($c['class']) ?>"
                        <?= ($class == $c['class']) ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($c['class']) ?>
                    </option>

                <?php endwhile; ?>

            </select>

            <button type="submit">
                Filter Results
            </button>

        </form>

    </div>

    <!-- RESULTS TABLE -->
<div class="card">

    <div class="table-wrapper">

        <table class="modern-table">

            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Class</th>
                    <th>Exam</th>
                    <th>Total Score</th>
                    <th>Percentage</th>
                    <th>Grade</th>
                    <th>Position</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($results && $results->num_rows > 0): ?>

                <?php $count = 1; ?>
                <?php while($row = $results->fetch_assoc()): ?>

                    <?php $status = strtolower($row['status'] ?? 'draft'); ?>

                    <tr>

                        <td><?= $count++ ?></td>

                        <td class="truncate">
                            <?= htmlspecialchars($row['student_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['class']) ?>
                        </td>

                        <td class="truncate">
                            <?= htmlspecialchars($row['exam_name']) ?>
                        </td>

                        <td>
                            <?= number_format($row['total_score'], 2) ?>
                        </td>

                        <td>
                            <?= number_format($row['percentage'], 2) ?>%
                        </td>

                        <td>
                            <?= $row['grade'] ?: '-' ?>
                        </td>

                        <td>
                            <?= $row['position_in_class'] ?: '-' ?>
                        </td>

                        <td>
                            <span class="badge badge-<?= $status ?>">
                                <?= ucfirst($status) ?>
                            </span>
                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="9" class="empty-state">
                        No results found.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>
</div>
    <!-- WORKFLOW CARD -->

    <div class="card">

        <h3>
            Results Management Workflow
        </h3>

        <p>
            Draft → Marks have been submitted by teachers.
        </p>

        <br>

        <p>
            Compiled → Final calculations, averages, grades and rankings generated.
        </p>

        <br>

        <p>
            Published → Official results released by the Education Division Manager.
        </p>

        <br>

        <p>
            Archived → Results permanently stored and locked.
        </p>

    </div>

</div>

</div>

<?php include '../common/footer.php'; ?>

</body>
</html>
















