<?php
/* ════════════════════════════════════════════════════════════════
   teacher/view_results.php
   Teacher: selects exam → views compiled class results for their school
   ════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/teacher_init.php';

$conn      = get_db_connection();
$teacher_id = (int)$_SESSION['user_id'];
$school_id  = (int)($_SESSION['school_id'] ?? 0);
$exam_id    = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

$message = '';
$msg_type = '';

/* ── MODE 1: SELECT EXAM (No exam_id provided) ── */
if ($exam_id <= 0) {
    // Fetch all exams in the system
    $stmt = $conn->prepare("
        SELECT DISTINCT e.exam_id, e.exam_name, e.class, e.year, e.status
        FROM exams e
        ORDER BY e.year DESC, e.exam_name ASC
    ");
    $stmt->execute();
    $assigned_exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $conn->close();
    $module_css = 'teacher';
    include __DIR__ . '/../common/head_assets.php';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>View Results | NED-SEMS</title>
    </head>
    <body>
    <?php include __DIR__ . '/../common/header.php'; ?>
    <div class="dashboard">
        <?php include __DIR__ . '/../common/sidebar.php'; ?>
        <div class="content">
            <div class="page-header">
                <div>
                    <h2 class="page-title">Class Results</h2>
                    <p class="page-subtitle">Select an exam to view the compiled student results for your school</p>
                </div>
                <a href="dashboard.php" class="btn btn-secondary">Back</a>
            </div>

            <div class="card">
                <div class="section-header">
                    <h3>Your Assigned Exams</h3>
                </div>
                
                <?php if (empty($assigned_exams)): ?>
                    <div class="empty-state" style="padding: 40px; text-align: center;">
                        <p style="color: var(--text-muted); font-size: 1.1rem; margin-bottom: 16px;">No exams are currently linked to your assigned subjects.</p>
                        <p style="font-size: 0.9rem;">Contact your Examination Officer if you believe this is an error.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Exam Name</th>
                                    <th>Class</th>
                                    <th>Year</th>
                                    <th>Status</th>
                                    <th style="width: 150px; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assigned_exams as $ex): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?= htmlspecialchars($ex['exam_name']) ?></td>
                                    <td><?= htmlspecialchars($ex['class']) ?></td>
                                    <td><?= htmlspecialchars($ex['year']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $ex['status'] === 'completed' ? 'success' : ($ex['status'] === 'active' ? 'primary' : 'secondary') ?>">
                                            <?= ucfirst(htmlspecialchars($ex['status'])) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <a href="view_results.php?exam_id=<?= $ex['exam_id'] ?>" class="btn btn-teal btn-small">
                                            View Results
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../common/footer.php'; ?>
    </body>
    </html>
    <?php
    exit();
}

/* ── MODE 2: DISPLAY RESULTS FOR EXAM ── */

// Fetch exam details and sum of total marks
$stmt = $conn->prepare("
    SELECT exam_id, exam_name, class, status, year
    FROM exams
    WHERE exam_id = ?
");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) {
    $conn->close();
    header("Location: view_results.php?msg=" . urlencode("Exam not found.") . "&mtype=error");
    exit();
}

// Sum up total marks for this exam
$stmt = $conn->prepare("SELECT SUM(total_marks) AS total_marks FROM exam_subjects WHERE exam_id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$total_marks_row = $stmt->get_result()->fetch_assoc();
$exam_total_marks = (int)($total_marks_row['total_marks'] ?? 100);
$stmt->close();

// 3. Get compiled results for students of this school and exam
$stmt = $conn->prepare("
    SELECT r.*, s.name AS student_name, s.exam_number
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    WHERE r.exam_id = ? AND s.school_id = ?
    ORDER BY r.position_in_class ASC, r.average_score DESC
");
$stmt->bind_param("ii", $exam_id, $school_id);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
$module_css = 'teacher';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Results | NED-SEMS</title>
</head>
<body>
<?php include __DIR__ . '/../common/header.php'; ?>
<div class="dashboard">
    <?php include __DIR__ . '/../common/sidebar.php'; ?>
    <div class="content">
        <div class="page-header">
            <div>
                <h2 class="page-title">Results: <?= htmlspecialchars($exam['exam_name']) ?></h2>
                <p class="page-subtitle">Class: <?= htmlspecialchars($exam['class']) ?> | Year: <?= htmlspecialchars($exam['year']) ?></p>
            </div>
            <a href="view_results.php" class="btn btn-secondary">Back to List</a>
        </div>

        <div class="card">
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3>Compiled Student Standings (Your School)</h3>
                <span class="badge badge-info"><?= count($results) ?> students found</span>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 80px; text-align: center;">Rank</th>
                            <th>Student Name</th>
                            <th>Exam Number</th>
                            <th>Subjects Taken</th>
                            <th>Total Score (/<?= $exam_total_marks ?>)</th>
                            <th>Average</th>
                            <th>Grade</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($results)): ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    No results have been compiled or published for your school's students in this exam yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($results as $r): ?>
                            <tr>
                                <td style="text-align: center; font-weight: bold; color: var(--primary-dark);">
                                    <?= htmlspecialchars($r['position_in_class'] ?: '—') ?>
                                </td>
                                <td style="font-weight: 600;"><?= htmlspecialchars($r['student_name']) ?></td>
                                <td><code><?= htmlspecialchars($r['exam_number']) ?></code></td>
                                <td><?= htmlspecialchars($r['total_subjects']) ?></td>
                                <td><?= htmlspecialchars($r['total_score']) ?></td>
                                <td style="font-weight: 600;"><?= number_format($r['average_score'], 1) ?>%</td>
                                <td>
                                    <span class="grade-badge" style="background: var(--border-color); color: var(--text-color); font-weight: 700;">
                                        <?= htmlspecialchars($r['grade'] ?: '—') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $r['status'] === 'published' ? 'success' : ($r['status'] === 'eo_approved' || $r['status'] === 'head_approved' || $r['status'] === 'approved' ? 'primary' : 'warning') ?>">
                                        <?= ucfirst(str_replace('_', ' ', htmlspecialchars($r['status']))) ?>
                                    </span>
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
<?php include __DIR__ . '/../common/footer.php'; ?>
</body>
</html>