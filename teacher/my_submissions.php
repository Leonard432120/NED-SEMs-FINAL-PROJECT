<?php
require_once __DIR__ . '/teacher_init.php';

$conn = get_db_connection();

$sql = "SELECT 
            d.document_id,
            d.exam_id,
            d.file_path,
            d.version_number,
            d.uploaded_at,
           e.exam_name,
            e.status
        FROM exam_documents d
        JOIN exams e ON d.exam_id = e.exam_id
        WHERE d.uploaded_by = ?
        ORDER BY d.uploaded_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$submissions = [];
while ($row = $result->fetch_assoc()) {
    $submissions[] = $row;
}
$stmt->close();
$conn->close();

function get_file_url($path) {
    if (empty($path)) {
        return '#';
    }
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        return $path;
    }
    if (strpos($path, '/') === 0) {
        return BASE_URL . $path;
    }
    return BASE_URL . '/' . ltrim($path, '/');
}

function badge_class($status) {
    return 'badge-' . strtolower(str_replace(' ', '_', $status));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Submissions</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/styles.css">
</head>
<body>
<div class="header">
    <div class="header-left">
        <span class="dashboard-title">NED-SEMS | Teacher Portal</span>
    </div>
    <div class="header-right">
        <div class="profile">
           <a href="<?= BASE_URL ?>/logout.php">Logout</a><img src="<?= BASE_URL ?>/static/images/user.png" alt="User">
        </div>
    </div>
</div>
<div class="dashboard">
    <?php include __DIR__ . '/teacher_sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <div>
                <h2 class="page-title">My Submissions</h2>
                <p class="muted">View all files you have uploaded for exam moderation and review.</p>
            </div>
        </div>

        <div class="card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Exam</th>
                            <th>File</th>
                            <th>Version</th>
                            <th>Uploaded At</th>
                            <th>Status</th>
                            <th style="width:190px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($submissions)): ?>
                            <?php foreach ($submissions as $submission): ?>
                                <tr>
                                    <td><?= htmlspecialchars($submission['exam_name']); ?></td>
                                    <td><?= htmlspecialchars(basename($submission['file_path'])); ?></td>
                                    <td><?= htmlspecialchars($submission['version_number']); ?></td>
                                    <td><?= htmlspecialchars($submission['uploaded_at']); ?></td>
                                    <td>
                                        <span class="badge <?= badge_class($submission['status']); ?>">
                                            <?= htmlspecialchars($submission['status']); ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <a href="<?= get_file_url($submission['file_path']); ?>" target="_blank" class="btn btn-dark btn-small">View</a>
                                        <a href="<?= get_file_url($submission['file_path']); ?>" download class="btn btn-teal btn-small">Download</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:20px;">No submissions found.</td>
                            </tr>
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