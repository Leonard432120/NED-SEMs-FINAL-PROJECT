<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'headteacher') {
    header('Location: ../login.php');
    exit();
}

$school_id = $_SESSION['school_id'];
$conn = get_db_connection();

function generate_summary(array $stats, int $school_id, $conn): string {
    $total   = (int)($stats['total_published']    ?? 0);
    $avg     = (float)($stats['average_percentage'] ?? 0);
    $passes  = (int)($stats['pass_count']          ?? 0);
    $fails   = max(0, $total - $passes);
    $rate    = $total > 0 ? round(($passes / $total) * 100) : 0;

    if ($total === 0) {
        return 'No published results are available for this school yet. Once results are compiled and published they will appear here.';
    }

    $performance = $avg >= 70 ? 'excellent' : ($avg >= 50 ? 'good' : ($avg >= 40 ? 'satisfactory' : 'below average'));
    $trend = $rate >= 80 ? 'The majority of students have passed.' : ($rate >= 50 ? 'More than half of students passed.' : 'A significant number of students require additional support.');

    // Best performing class
    $best_class = '';
    $bc = $conn->query("
        SELECT r.class, ROUND(AVG(r.average_score),1) AS avg_sc
        FROM results r
        JOIN students s ON s.student_id = r.student_id
        WHERE s.school_id = $school_id AND r.status = 'published'
        GROUP BY r.class ORDER BY avg_sc DESC LIMIT 1
    ");
    if ($bc && $bc->num_rows > 0) {
        $bcr = $bc->fetch_assoc();
        $best_class = " {$bcr['class']} is the top-performing class with an average of {$bcr['avg_sc']}%";
    }

    return "A total of {$total} result" . ($total > 1 ? 's have' : ' has') . " been published for this school. "
         . "The overall school average stands at " . number_format($avg, 1) . "%, which is considered {$performance}. "
         . "{$passes} student" . ($passes !== 1 ? 's' : '') . " passed (pass rate: {$rate}%) and {$fails} did not meet the passing threshold. "
         . $trend
         . ($best_class ? ".{$best_class}." : '');
}

$results_stmt = $conn->prepare("
    SELECT
        r.result_id,
        r.total_score,
        r.average_score,
        r.grade,
        r.class,
        r.term,
        r.year,
        r.status,
        s.name AS student_name,
        s.exam_number
    FROM results r
    INNER JOIN students s
        ON r.student_id = s.student_id
    WHERE s.school_id = ?
      AND r.status = 'published'
    ORDER BY r.average_score DESC
");

$results_stmt->bind_param('i', $school_id);
$results_stmt->execute();

$results = $results_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$results_stmt->close();

$summary_stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_published,
        ROUND(AVG(r.average_score),2) AS average_percentage,
        SUM(r.average_score >= 40) AS pass_count
    FROM results r
    INNER JOIN students s
        ON r.student_id = s.student_id
    WHERE s.school_id = ?
    AND r.status = 'published'
");

$summary_stmt->bind_param('i', $school_id);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();
$summary_stmt->close();

$ai_summary = generate_summary($summary, $school_id, $conn);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Final Results</title>
<?php
$portal_title = 'NED-SEMS | Headteacher Portal';
$module_css = 'headteacher';
include __DIR__ . '/../common/head_assets.php';
?>
</head>
<body>
<?php include '../common/header.php'; ?>

<div class="dashboard">

<?php include '../common/sidebar.php'; ?>

<div class="main-content">
        <h2>Published School Results</h2>

        <div class="card card-accent-light">
            <h3>School Performance Summary</h3>
            <p><?= nl2br(htmlspecialchars($ai_summary)); ?></p>
        </div>

        <div class="card card-accent-blue">
            <div class="analytics-grid">
                <div class="analytics-item">
                    <strong>Total Published</strong>
                    <span><?= htmlspecialchars($summary['total_published'] ?? 0); ?></span>
                </div>
                <div class="analytics-item">
                    <strong>Average Score</strong>
                    <span><?= htmlspecialchars($summary['average_percentage'] ?? '0'); ?>%</span>
                </div>
                <div class="analytics-item">
                    <strong>Pass Rate</strong>
                    <span><?php
                        $pass_rate = 0;
                        if (!empty($summary['total_published'])) {
                            $pass_rate = round($summary['pass_count'] / $summary['total_published'] * 100, 0);
                        }
                        echo htmlspecialchars($pass_rate) . '%';
                    ?></span>
                </div>
            </div>
        </div>

        <div class="card">
            <h3>Published Results</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Term</th>
                            <th>Year</th>
                            <th>Exam No.</th>
                            <th>Total Score</th>
                            <th>Average</th>
                            <th>Grade</th>
                            <th>Outcome</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($results)): ?>
                            <tr><td colspan="11">No published results are available yet.</td></tr>
                        <?php else: ?>
                            <?php $rank = 1; foreach ($results as $row):
                                $avg    = (float)($row['average_score'] ?? 0);
                                $passed = $avg >= 40;
                                $perf_cls = $avg >= 70 ? 'performance-indicator--high'
                                          : ($avg >= 40 ? 'performance-indicator--mid'
                                          : 'performance-indicator--low');
                            ?>
                                <tr>
                                    <td><strong><?= $rank++ ?></strong></td>
                                    <td><?= htmlspecialchars($row['student_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['class'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($row['term'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars((string)($row['year'] ?? '—')) ?></td>
                                    <td><?= htmlspecialchars($row['exam_number'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars((string)($row['total_score'] ?? '—')) ?></td>
                                    <td>
                                        <span class="performance-indicator <?= $perf_cls ?>">
                                            <?= number_format($avg, 1) ?>%
                                        </span>
                                    </td>
                                    <td><strong><?= htmlspecialchars($row['grade'] ?? '—') ?></strong></td>
                                    <td>
                                        <?php if ($passed): ?>
                                            <span class="badge badge-success" style="font-weight:700;">PASS</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger" style="font-weight:700;">FAIL</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="../admin/student_report_card.php?result_id=<?= (int)$row['result_id'] ?>" target="_blank" class="btn btn-secondary btn-small">View</a>
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
