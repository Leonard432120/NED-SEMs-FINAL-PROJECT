<?php
require_once __DIR__ . '/teacher_init.php';

$conn = get_db_connection();

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$query = "
    SELECT DISTINCT
        e.exam_id,
        e.exam_name,
        es.id AS exam_subject_id,
        es.subject_id,
        e.status,
        e.year,
        e.class AS exam_class,
        s.subject_name
    FROM subject_assignments ea
    INNER JOIN exam_subjects es
        ON ea.subject_id = es.subject_id
    INNER JOIN exams e
        ON es.exam_id = e.exam_id
    INNER JOIN subjects s
        ON es.subject_id = s.subject_id
    WHERE ea.teacher_id = ?
      AND ea.role = 'item_writer'
      AND ea.status = 'assigned'
";

$params = [$user_id];
$types = 'i';

if ($status !== '') {
    $query .= " AND e.status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($search !== '') {
    $query .= " AND e.exam_name LIKE ?";
    $params[] = "%{$search}%";
    $types .= 's';
}

$query .= " ORDER BY e.exam_id DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
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
<?php
$portal_title = 'NED-SEMS | Teacher Portal';
$module_css = 'teacher';
include __DIR__ . '/../common/head_assets.php';
?>
</head>

<body>

<?php include __DIR__ . '/../common/header.php'; ?>

<div class="dashboard">
    <?php include __DIR__ . '/../common/sidebar.php'; ?>

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
                                    <?php
                                    $is_locked = in_array($exam['status'], ['submitted', 'under_moderation', 'approved']);
                                    ?>
                                    <a href="compose_exam.php?exam_id=<?= $exam['exam_id']; ?>&subject_id=<?= $exam['subject_id']; ?>&exam_subject_id=<?= $exam['exam_subject_id']; ?>"
                                    class="btn <?= $is_locked ? 'btn-dark' : 'btn-teal'; ?> btn-small">
                                        <?= $is_locked ? 'View Questions' : 'Compose'; ?>
                                    </a>

                                    <a href="<?= BASE_URL ?>/teacher/exam.php?id=<?= $exam['exam_id']; ?>&subject_id=<?= $exam['subject_id']; ?>&page=cover"
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

<?php include __DIR__ . '/../common/footer.php'; ?>