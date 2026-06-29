<?php
/* ════════════════════════════════════════════════════════════════
   admin/publish_results.php
   EDM/Admin: reviews and publishes compiled student results
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php"); exit();
}

$conn    = get_db_connection();
$adm_id  = (int)$_SESSION['user_id'];
$message = '';
$msg_type = '';

/* ── PUBLISH ACTION ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_exam_id'])) {
    $eid = (int)$_POST['publish_exam_id'];
    
    // Check if there are draft results
    $draft_count = (int)$conn->query("SELECT COUNT(*) AS c FROM results WHERE exam_id = {$eid} AND status = 'draft'")->fetch_assoc()['c'];
    
    if ($draft_count === 0) {
        $message = "No draft results found for this exam. Compile results first.";
        $msg_type = "error";
    } else {
        // Update results to published and lock them
        $conn->query("UPDATE results SET status='published', locked=1 WHERE exam_id={$eid} AND status='draft'");
        
        // Log action in workflow logs
        $log = $conn->prepare("INSERT INTO result_workflow_logs (result_id, action, performed_by, role, notes, created_at) VALUES (0, 'publish_results', ?, 'ADMIN', ?, NOW())");
        $notes_log = "Published results for exam ID {$eid} ($draft_count students)";
        $log->bind_param("is", $adm_id, $notes_log);
        $log->execute();
        $log->close();
        
        $message = "Successfully published results for {$draft_count} students! Results are now locked and visible on report cards.";
        $msg_type = "success";
    }
}

/* ── FETCH EXAMS WITH COMPILED DRAFT RESULTS ── */
$draft_exams = $conn->query("
    SELECT e.exam_id, e.exam_name, e.class, e.year,
           COUNT(r.result_id) AS student_count,
           MIN(r.compiled_at) AS compiled_at
    FROM exams e
    JOIN results r ON e.exam_id = r.exam_id
    WHERE r.status = 'draft'
    GROUP BY e.exam_id, e.exam_name, e.class, e.year
    ORDER BY compiled_at DESC
")->fetch_all(MYSQLI_ASSOC);

/* ── FETCH EXAMS ALREADY PUBLISHED ── */
$published_exams = $conn->query("
    SELECT e.exam_id, e.exam_name, e.class, e.year,
           COUNT(r.result_id) AS student_count,
           MAX(r.compiled_at) AS compiled_at
    FROM exams e
    JOIN results r ON e.exam_id = r.exam_id
    WHERE r.status = 'published'
    GROUP BY e.exam_id, e.exam_name, e.class, e.year
    ORDER BY compiled_at DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

$conn->close();
$module_css = 'admin';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Publish Results | NED-SEMS</title>
</head>
<body>
<?php include '../common/header.php'; ?>
<div class="dashboard">
    <?php include '../common/sidebar.php'; ?>
    <div class="content">

        <!-- Header -->
        <div class="page-header">
            <div>
                <h2 class="page-title">Publish Results</h2>
                <p class="page-subtitle">Review and release compiled draft results, making them official and viewable by students and schools</p>
            </div>
            <a href="manage_results.php" class="btn btn-secondary">Results Dashboard</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $msg_type ?>"><?= $message ?></div>
        <?php endif; ?>

        <!-- Step 1: Draft Exams ready to publish -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="section-header">
                <h3>Draft Results Ready for Publication</h3>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Exam Name</th>
                            <th>Class</th>
                            <th>Year</th>
                            <th style="text-align: center;">Compiled Students</th>
                            <th>Compiled Date</th>
                            <th style="text-align: center; width: 180px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($draft_exams)): ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    No draft results are currently waiting for publication. 
                                    <br>
                                    <span style="font-size: 0.85rem; font-weight: normal; color: var(--text-muted);">
                                        Go to <a href="compile_results.php" style="font-weight: 600;">Compile Results</a> to calculate standings first.
                                    </span>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($draft_exams as $ex): ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($ex['exam_name']) ?></td>
                                <td><?= htmlspecialchars($ex['class']) ?></td>
                                <td><?= htmlspecialchars($ex['year']) ?></td>
                                <td style="text-align: center; font-weight: bold; color: var(--primary-dark);"><?= $ex['student_count'] ?></td>
                                <td><?= date('d M Y, H:i', strtotime($ex['compiled_at'])) ?></td>
                                <td style="text-align: center;">
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="publish_exam_id" value="<?= $ex['exam_id'] ?>">
                                        <button type="submit" class="btn btn-teal btn-small"
                                                onclick="return confirm('Are you sure you want to publish and release results for <?= htmlspecialchars($ex['exam_name']) ?>? This will lock the results and make them official.')">
                                            Publish & Lock
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 2: Recently Published Exams -->
        <div class="card">
            <div class="section-header">
                <h3>Recently Published Exams</h3>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Exam Name</th>
                            <th>Class</th>
                            <th>Year</th>
                            <th style="text-align: center;">Students Published</th>
                            <th>Published Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($published_exams)): ?>
                            <tr>
                                <td colspan="6" class="empty-state">No exams have been published recently.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($published_exams as $ex): ?>
                            <tr>
                                <td style="font-weight: 500; color: var(--text-muted);"><?= htmlspecialchars($ex['exam_name']) ?></td>
                                <td><?= htmlspecialchars($ex['class']) ?></td>
                                <td><?= htmlspecialchars($ex['year']) ?></td>
                                <td style="text-align: center;"><?= $ex['student_count'] ?></td>
                                <td><?= date('d M Y, H:i', strtotime($ex['compiled_at'])) ?></td>
                                <td>
                                    <span class="badge badge-success">Official & Published</span>
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
