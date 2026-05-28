<?php
require_once __DIR__ . '/teacher_init.php';
$conn = get_db_connection();

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$searchParam = "%{$search}%";

$query = "SELECT e.*, s.subject_name, e.class AS exam_class
          FROM exam_assignments ea
          JOIN exams e ON ea.exam_id = e.exam_id
          JOIN subjects s ON e.subject_id = s.subject_id
          WHERE ea.teacher_id = ? AND ea.role = 'item_writer'";

if ($status !== '') {
    $query .= " AND e.status = ?";
}

if ($search !== '') {
    $query .= " AND e.exam_name LIKE ?";
}

$query .= " ORDER BY e.exam_id DESC";

if ($status !== '' && $search !== '') {
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iss', $user_id, $status, $searchParam);
} elseif ($status !== '') {
    $stmt = $conn->prepare($query);
    $stmt->bind_param('is', $user_id, $status);
} elseif ($search !== '') {
    $stmt = $conn->prepare($query);
    $stmt->bind_param('is', $user_id, $searchParam);
} else {
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
$exams = [];
while ($row = $result->fetch_assoc()) {
    $exams[] = $row;
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Item Writer Tasks</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/static/css/styles.css">
</head>

<body>

<div class="header">
    <div class="header-left">
        <span class="dashboard-title">NED-SEMS | Teacher Portal</span>
    </div>
    <div class="header-right">
        <div class="profile">
            <span class="profile-name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Teacher'); ?></span>
            <img src="<?= BASE_URL ?>/static/images/user.png" alt="Profile">
            <a href="<?= BASE_URL ?>/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</div>

<div class="dashboard">
    <?php include __DIR__ . '/teacher_sidebar.php'; ?>

    <div class="main-content">

        <div class="page-header">
            <h2 class="page-title">Item Writer Tasks</h2>
        </div>

        <form method="GET" class="search-filter-bar">
            <input type="text"
                   name="search"
                   placeholder="Search exam title..."
                   value="<?= htmlspecialchars($search); ?>">

            <select name="status">
                <option value="">All Status</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                <option value="assigned" <?= $status === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                <option value="submitted" <?= $status === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                <option value="under_moderation" <?= $status === 'under_moderation' ? 'selected' : ''; ?>>Under Moderation</option>
                <option value="approved" <?= $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="rejected" <?= $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>

            <button type="submit">Filter</button>
        </form>

        <div class="card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Subject</th>
                            <th>Year</th>
                            <th>Class</th>
                            <th>Status</th>
                            <th style="width:200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($exams): ?>
                            <?php foreach ($exams as $exam): ?>
                            <tr>
                                <td><?= htmlspecialchars($exam['exam_name']); ?></td>
                                <td><?= htmlspecialchars($exam['subject_name']); ?></td>
                                <td><?= htmlspecialchars($exam['year'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($exam['exam_class'] ?? ''); ?></td>
                                <td>
                                    <span class="badge badge-<?= htmlspecialchars($exam['status']); ?>">
                                        <?= htmlspecialchars($exam['status']); ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a href="compose_exam.php?exam_id=<?= $exam['exam_id']; ?>"
                                       class="btn btn-teal btn-small">
                                        Compose
                                    </a>
                                    <a href="<?= BASE_URL ?>/teacher/exam.php?id=<?= $exam['exam_id']; ?>&page=cover"
                                       class="btn btn-dark btn-small">
                                        View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:20px;">
                                    No assigned exams found.
                                </td>
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