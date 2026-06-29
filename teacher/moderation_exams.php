<?php
require_once __DIR__ . '/teacher_init.php';

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$allowedStatuses = [
    'submitted',
    'under_moderation',
    'needs_revision',
    'approved',
    'rejected'
];

if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$conn = get_db_connection();

/*
|--------------------------------------------------------------------------
| FETCH EXAMS ASSIGNED TO THIS MODERATOR
|--------------------------------------------------------------------------
| subject_assignments.subject_id = exams.subject_id
| NOT exams.exam_id
|--------------------------------------------------------------------------
*/
$query = "
    SELECT DISTINCT
        e.exam_id,
        e.exam_name,
        es.subject_id,
        e.status,
        e.year,
        e.class,
        s.subject_name,
        t.name AS teacher_name
    FROM subject_assignments ea
    INNER JOIN exam_subjects es
        ON ea.subject_id = es.subject_id
    INNER JOIN exams e
        ON es.exam_id = e.exam_id
    INNER JOIN subjects s
        ON es.subject_id = s.subject_id
    INNER JOIN users t
        ON ea.teacher_id = t.user_id
    WHERE ea.teacher_id = ?
      AND ea.role = 'moderator'
      AND ea.status = 'assigned'
";

$params = [$user_id];
$types = 'i';

if ($search !== '') {
    $query .= " AND e.exam_name LIKE ?";
    $params[] = "%{$search}%";
    $types .= 's';
}

if ($status !== '') {
    $query .= " AND e.status = ?";
    $params[] = $status;
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
<title>Moderation Tasks</title>

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

    <div class="content">

        <div class="page-header">
            <div>
                <h2 class="page-title">Moderation Tasks</h2>
                <p class="page-subtitle">
                    Exams assigned to you for moderation
                </p>
            </div>
        </div>

        <!-- FILTERS -->
        <form method="GET" class="search-filter-bar">

            <input
                type="text"
                name="search"
                placeholder="Search exam title..."
                value="<?= htmlspecialchars($search) ?>"
            >

            <select name="status">
                <option value="">All Status</option>

                <option value="submitted"
                    <?= $status === 'submitted' ? 'selected' : '' ?>>
                    Submitted
                </option>

                <option value="under_moderation"
                    <?= $status === 'under_moderation' ? 'selected' : '' ?>>
                    Under Moderation
                </option>

                <option value="needs_revision"
                    <?= $status === 'needs_revision' ? 'selected' : '' ?>>
                    Needs Revision
                </option>

                <option value="approved"
                    <?= $status === 'approved' ? 'selected' : '' ?>>
                    Approved
                </option>

                <option value="rejected"
                    <?= $status === 'rejected' ? 'selected' : '' ?>>
                    Rejected
                </option>
            </select>

            <button type="submit" class="btn btn-primary">
                Filter
            </button>

        </form>

        <div class="card">

            <div class="table-container">

                <table>

                    <thead>
                        <tr>
                            <th>Exam Title</th>
                            <th>Subject</th>
                            <th>Moderator</th>
                            <th>Year</th>
                            <th>Class</th>
                            <th>Status</th>
                            <th width="220">Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (empty($exams)): ?>

                        <tr>
                            <td colspan="7" style="text-align:center;">
                                No exams assigned for moderation.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($exams as $exam): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($exam['exam_name']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($exam['subject_name']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($exam['teacher_name']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($exam['year']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($exam['class']) ?>
                                </td>

                                <td>
                                    <span class="badge badge-<?= htmlspecialchars($exam['status']) ?>">
                                        <?= ucfirst(str_replace('_', ' ', $exam['status'])) ?>
                                    </span>
                                </td>

                                <td class="actions">

                                    <a
                                        href="moderate_exam.php?exam_id=<?= $exam['exam_id'] ?>"
                                        class="btn btn-teal btn-small">
                                        Moderate
                                    </a>

                                    <a
                                        href="<?= BASE_URL ?>/teacher/exam.php?id=<?= $exam['exam_id'] ?>&page=cover"
                                        class="btn btn-dark btn-small">
                                        View Paper
                                    </a>

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