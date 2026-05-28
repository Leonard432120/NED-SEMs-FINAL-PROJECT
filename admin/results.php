<?php
session_start();
require_once '../config/db.php';
require_once '../common/email_service.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$conn = get_db_connection();
$message = '';
$message_type = '';

function shell_escape($value) {
    return '"' . str_replace('"', '\\"', $value) . '"';
}

function get_ai_summary(array $metrics, $context = 'overall') {
    $repoRoot = dirname(__DIR__);
    $cli = $repoRoot . '/ai_results_summary_cli.py';
    $pythonCandidates = ['python', 'py -3', 'python3'];
    $summary = 'AI summary unavailable at the moment.';
    $metrics_json = json_encode($metrics);

    foreach ($pythonCandidates as $python) {
        $command = 'cd /d ' . shell_escape($repoRoot) . ' && ' . $python . ' ' . shell_escape($cli);
        $command .= ' --context ' . shell_escape($context);
        $command .= ' --metrics ' . shell_escape($metrics_json);
        $command .= ' 2>&1';
        $output = [];
        $exitCode = 1;
        exec($command, $output, $exitCode);

        if ($exitCode === 0) {
            $json = implode("\n", $output);
            $data = json_decode($json, true);
            if (is_array($data) && isset($data['summary'])) {
                return $data['summary'];
            }
        }
    }

    return $summary;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['exam_id'])) {
    $action = $_POST['action'];
    $exam_id = (int)$_POST['exam_id'];
    $admin_id = $_SESSION['user_id'];

    if ($exam_id > 0) {
        if ($action === 'compile') {
            $update = $conn->prepare(
                "UPDATE results SET status='eo_approved' WHERE exam_id=? AND status IN ('draft','submitted')"
            );
            $update->bind_param('i', $exam_id);
            $update->execute();
            $updated = $update->affected_rows;
            $update->close();

            if ($updated > 0) {
                $message = "Results have been compiled and moved to approval stage. ($updated records updated.)";
                $message_type = 'success';
            } else {
                $message = 'No draft or submitted results were available for compilation.';
                $message_type = 'warning';
            }
        }

        if ($action === 'release') {
            $exam_stmt = $conn->prepare(
                "SELECT exam_name FROM exams WHERE exam_id=?"
            );
            $exam_stmt->bind_param('i', $exam_id);
            $exam_stmt->execute();
            $exam = $exam_stmt->get_result()->fetch_assoc();
            $exam_stmt->close();

            $release_stmt = $conn->prepare(
                "SELECT result_id, status FROM results WHERE exam_id=? AND status IN ('draft','submitted','eo_approved')"
            );
            $release_stmt->bind_param('i', $exam_id);
            $release_stmt->execute();
            $results_to_release = $release_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $release_stmt->close();

            if (!empty($results_to_release)) {
                $update = $conn->prepare(
                    "UPDATE results SET status='head_approved' WHERE exam_id=? AND status IN ('draft','submitted','eo_approved')"
                );
                $update->bind_param('i', $exam_id);
                $update->execute();
                $published_count = $update->affected_rows;
                $update->close();

                $log_stmt = $conn->prepare(
                    "INSERT INTO result_workflow_logs (result_id, action, performed_by, role, from_status, to_status, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );

                foreach ($results_to_release as $result_row) {
                    $note = sprintf(
                        'Admin %d released exam %d result from %s to head_approved.',
                        $admin_id,
                        $exam_id,
                        $result_row['status']
                    );
                    $action_label = 'Final release';
                    $log_stmt->bind_param(
                        'issssss',
                        $result_row['result_id'],
                        $action_label,
                        $admin_id,
                        $_SESSION['role'],
                        $result_row['status'],
                        $to_status,
                        $note
                    );
                    $to_status = 'head_approved';
                    $log_stmt->execute();
                }
                $log_stmt->close();

                $headteachers = [];
                $notify_stmt = $conn->prepare(
                    "SELECT name, email FROM users WHERE role='headteacher' AND status='active' AND email <> ''"
                );
                $notify_stmt->execute();
                $result = $notify_stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $headteachers[] = $row;
                }
                $notify_stmt->close();

                $subject = 'Final Exam Results Released';
                $exam_name = $exam['exam_name'] ?? 'selected exam';
                $text = "Hello,\n\nFinal results for exam '{$exam_name}' have been released across the system.\nTotal records published: {$published_count}.\n\nPlease login to your headteacher portal to review the results and share them with your school.\n\nRegards,\nNED-SEMS Team";

                foreach ($headteachers as $teacher) {
                    send_email($teacher['email'], $subject, $text);
                }

                $message = "Final results have been released to headteachers. ({$published_count} records published.)";
                $message_type = 'success';
            } else {
                $message = 'No compilable results found for release. Please compile teacher submissions first.';
                $message_type = 'warning';
            }
        }
    }
}

$summary_stmt = $conn->prepare(
    "SELECT e.exam_id, e.exam_name, e.class,
        COUNT(r.result_id) AS total_results,
        SUM(r.status IN ('draft','submitted')) AS pending_count,
        SUM(r.status = 'eo_approved') AS ready_for_release,
        SUM(r.status = 'head_approved') AS published_count,
        ROUND(AVG(r.percentage), 2) AS average_percentage,
        ROUND(SUM(r.percentage >= 40) / COUNT(r.result_id) * 100, 2) AS pass_rate
    FROM exams e
    JOIN results r ON e.exam_id = r.exam_id
    GROUP BY e.exam_id
    ORDER BY e.exam_name"
);
$summary_stmt->execute();
$exams = $summary_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$summary_stmt->close();

$metrics_stmt = $conn->prepare(
    "SELECT
        COUNT(result_id) AS total_published,
        ROUND(AVG(percentage), 2) AS average_percentage,
        SUM(percentage >= 40) AS pass_count,
        SUM(status IN ('draft','submitted')) AS pending_count,
        SUM(status = 'eo_approved') AS eo_approved_count
    FROM results"
);
$metrics_stmt->execute();
$overall_metrics = $metrics_stmt->get_result()->fetch_assoc();
$metrics_stmt->close();

$ai_summary = get_ai_summary($overall_metrics, 'overall');
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Results Workflow</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/admin.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/form.css">
</head>
<body>
<?php include '../common/header.php'; ?>
<div class="dashboard">
    <?php include '../common/sidebar.php'; ?>
    <div class="content">
        <div class="page-header">
            <h2 class="page-title">Results Workflow</h2>
            <div class="header-actions">
                <span class="badge">AI Insight</span>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= htmlspecialchars($message_type); ?>"><?= htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="card card-accent-light">
            <h3>AI Results Insight</h3>
            <p><?= nl2br(htmlspecialchars($ai_summary)); ?></p>
        </div>

        <div class="card">
            <h3>Exam Release Dashboard</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Exam</th>
                            <th>Class</th>
                            <th>Total Results</th>
                            <th>Pending</th>
                            <th>Ready for Release</th>
                            <th>Published</th>
                            <th>Avg. %</th>
                            <th>Pass Rate</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($exams)): ?>
                            <tr><td colspan="9">No exam results available yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($exams as $exam): ?>
                                <tr>
                                    <td><?= htmlspecialchars($exam['exam_name']); ?></td>
                                    <td><?= htmlspecialchars($exam['class']); ?></td>
                                    <td><?= htmlspecialchars($exam['total_results']); ?></td>
                                    <td><?= htmlspecialchars($exam['pending_count']); ?></td>
                                    <td><?= htmlspecialchars($exam['ready_for_release']); ?></td>
                                    <td><?= htmlspecialchars($exam['published_count']); ?></td>
                                    <td><?= htmlspecialchars($exam['average_percentage'] ?? 'N/A'); ?></td>
                                    <td><?= htmlspecialchars($exam['pass_rate'] ?? '0'); ?>%</td>
                                    <td>
                                        <form method="POST" style="display:inline-block; margin-right: 0.4rem;">
                                            <input type="hidden" name="exam_id" value="<?= (int)$exam['exam_id']; ?>">
                                            <input type="hidden" name="action" value="compile">
                                            <button type="submit" class="btn btn-secondary btn-small">Compile</button>
                                        </form>
                                        <form method="POST" style="display:inline-block;">
                                            <input type="hidden" name="exam_id" value="<?= (int)$exam['exam_id']; ?>">
                                            <input type="hidden" name="action" value="release">
                                            <button type="submit" class="btn btn-teal btn-small">Release</button>
                                        </form>
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
<script src="<?= BASE_URL ?>/static/js/main.js"></script>
</body>
</html>
