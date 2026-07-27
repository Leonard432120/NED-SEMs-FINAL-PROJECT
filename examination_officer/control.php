<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'examination_officer') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();
$school_id = (int)($_SESSION['school_id'] ?? 0);
$message = '';
$scope = $school_id > 0 ? "e.created_by IN (SELECT user_id FROM users WHERE school_id = $school_id)" : "1=1";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_id = (int)($_POST['exam_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($exam_id > 0) {
        $eo_id = (int)$_SESSION['user_id'];
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '::1';
        
        if ($action === 'lock') {
            $conn->query("UPDATE results SET locked=1 WHERE exam_id=$exam_id");
            $message = 'Result entry locked for this exam.';
            
            // Audit log
            $action_name = "lock_exam_results";
            $details = "Locked result entry period for Exam ID {$exam_id}";
            $log = $conn->prepare("INSERT INTO audit_logs (user_id, target_user_id, action, details, ip_address) VALUES (?, NULL, ?, ?, ?)");
            $log->bind_param("isss", $eo_id, $action_name, $details, $ip_address);
            $log->execute();
            $log->close();
            
        } elseif ($action === 'unlock') {
            $conn->query("UPDATE results SET locked=0 WHERE exam_id=$exam_id");
            $message = 'Result entry unlocked for this exam.';
            
            // Audit log
            $action_name = "unlock_exam_results";
            $details = "Unlocked result entry period for Exam ID {$exam_id}";
            $log = $conn->prepare("INSERT INTO audit_logs (user_id, target_user_id, action, details, ip_address) VALUES (?, NULL, ?, ?, ?)");
            $log->bind_param("isss", $eo_id, $action_name, $details, $ip_address);
            $log->execute();
            $log->close();
            
        } elseif ($action === 'validate') {
            $conn->query("UPDATE exams SET status='approved' WHERE exam_id=$exam_id AND status IN ('submitted','under_moderation')");
            $message = 'Exam submission validated and approved.';
            
            // Audit log
            $action_name = "validate_exam_submission";
            $details = "Validated and approved exam submission for Exam ID {$exam_id}";
            $log = $conn->prepare("INSERT INTO audit_logs (user_id, target_user_id, action, details, ip_address) VALUES (?, NULL, ?, ?, ?)");
            $log->bind_param("isss", $eo_id, $action_name, $details, $ip_address);
            $log->execute();
            $log->close();
        }
    }
}

$exams = $conn->query("
    SELECT e.exam_id, e.exam_name, e.status, e.start_date,
           (SELECT COUNT(*) FROM results r WHERE r.exam_id=e.exam_id) AS result_count,
           (SELECT COUNT(*) FROM results r WHERE r.exam_id=e.exam_id AND r.locked=1) AS locked_count,
           (SELECT COUNT(*) FROM results r WHERE r.exam_id=e.exam_id AND r.status IN ('draft','submitted')) AS pending_count
    FROM exams e WHERE $scope
    ORDER BY e.start_date DESC, e.exam_id DESC
");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Exam Control Panel</title>
<?php $module_css = 'exam_officer'; include __DIR__ . '/../common/head_assets.php'; ?>
</head>
<body>
<?php include '../common/header.php'; ?>
<?php include __DIR__ . '/../common/watermark_helper.php'; ?>
<div class="dashboard">
<?php include '../common/sidebar.php'; ?>
<div class="content">

<div class="page-header">
    <div>
        <h1 class="page-title">Exam Control Panel</h1>
        <p class="stats-info">Lock/unlock result entry periods and validate exam submissions.</p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary">← Dashboard</a>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<div class="results-info">
    <strong>Control Guidelines:</strong> Lock exams after marks entry deadline. Validate submissions before releasing results to headteacher.
</div>

<div class="control-panel">
    <?php if ($exams && $exams->num_rows): while ($e = $exams->fetch_assoc()):
        $is_locked = (int)$e['locked_count'] > 0 && (int)$e['locked_count'] >= (int)$e['result_count'] && (int)$e['result_count'] > 0;
    ?>
        <div class="control-card <?= $is_locked ? 'locked' : 'unlocked' ?>">
            <h4><?= htmlspecialchars($e['exam_name']) ?></h4>
            <p class="muted-text">Date: <?= $e['start_date'] ? date('d M Y', strtotime($e['start_date'])) : 'Not scheduled' ?></p>
            <div class="insight-card__row"><span>Status</span><span class="badge badge-<?= str_replace('_','-',$e['status']) ?>"><?= $e['status'] ?></span></div>
            <div class="insight-card__row"><span>Results</span><strong><?= (int)$e['result_count'] ?></strong></div>
            <div class="insight-card__row"><span>Pending</span><strong><?= (int)$e['pending_count'] ?></strong></div>
            <div class="insight-card__row"><span>Entry Period</span><strong><?= $is_locked ? '🔒 Locked' : '🔓 Open' ?></strong></div>
            <div class="action-buttons" style="margin-top:16px;">
                <form method="POST">
                    <input type="hidden" name="exam_id" value="<?= $e['exam_id'] ?>">
                    <input type="hidden" name="action" value="lock">
                    <button type="submit" class="btn btn-small btn-danger">Lock Entry</button>
                </form>
                <form method="POST">
                    <input type="hidden" name="exam_id" value="<?= $e['exam_id'] ?>">
                    <input type="hidden" name="action" value="unlock">
                    <button type="submit" class="btn btn-small btn-success">Unlock</button>
                </form>
                <?php if (in_array($e['status'], ['submitted','under_moderation'], true)): ?>
                    <form method="POST">
                        <input type="hidden" name="exam_id" value="<?= $e['exam_id'] ?>">
                        <input type="hidden" name="action" value="validate">
                        <button type="submit" class="btn btn-small btn-primary">Validate</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endwhile; else: ?>
        <p class="empty-state">No exams available for control.</p>
    <?php endif; ?>
</div>

</div>
</div>
<?php include '../common/footer.php'; ?>
