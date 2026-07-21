<?php
require_once __DIR__ . '/teacher_init.php';

$search = trim($_GET['search'] ?? '');
$filter = trim($_GET['filter'] ?? '');   // pending | in_progress | fully_approved

$conn = get_db_connection();

/*
|--------------------------------------------------------------------------
| FETCH EXAMS ASSIGNED TO THIS MODERATOR
| Status is derived from questions.moderation_status counts, NOT exams.status
|--------------------------------------------------------------------------
*/
$query = "
    SELECT DISTINCT
        e.exam_id,
        e.exam_name,
        es.subject_id,
        e.status        AS exam_status,
        e.year,
        e.class,
        s.subject_name,
        t.name          AS teacher_name,
        COUNT(q.question_id)                                                     AS total_q,
        SUM(IF(q.question_id IS NOT NULL AND q.moderation_status = 'approved', 1, 0)) AS approved_q,
        SUM(IF(q.question_id IS NOT NULL AND q.moderation_status = 'revise', 1, 0)) AS revise_q,
        SUM(IF(q.question_id IS NOT NULL AND q.moderation_status = 'rejected', 1, 0)) AS rejected_q,
        SUM(IF(q.question_id IS NOT NULL AND (q.moderation_status = 'pending' OR q.moderation_status IS NULL), 1, 0)) AS pending_q
    FROM subject_assignments ea
    INNER JOIN exam_subjects es
        ON ea.subject_id = es.subject_id
    INNER JOIN exams e
        ON es.exam_id = e.exam_id
    INNER JOIN subjects s
        ON es.subject_id = s.subject_id
    INNER JOIN users t
        ON ea.teacher_id = t.user_id
    LEFT JOIN questions q
        ON q.exam_subject_id = es.id
    WHERE ea.teacher_id = ?
      AND ea.role = 'moderator'
      AND ea.status = 'assigned'
";

$params = [$user_id];
$types  = 'i';

if ($search !== '') {
    $query .= " AND e.exam_name LIKE ?";
    $params[] = "%{$search}%";
    $types   .= 's';
}

$query .= " GROUP BY e.exam_id, es.subject_id, e.status, e.year, e.class, s.subject_name, t.name, e.exam_name";
$query .= " ORDER BY e.exam_id DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Temporary connection for self-healing
$sync_conn = get_db_connection();

$exams = [];
while ($row = $result->fetch_assoc()) {
    // Derive a semantic moderation status from question counts
    $total    = (int)$row['total_q'];
    $approved = (int)$row['approved_q'];
    $revise   = (int)$row['revise_q'];
    $rejected = (int)$row['rejected_q'];
    $pending  = (int)$row['pending_q'];

    if ($total === 0) {
        $row['mod_status'] = 'no_questions';
    } elseif ($approved === $total) {
        $row['mod_status'] = 'fully_approved';
        
        // Self-heal: If database still says 'draft' or anything other than 'approved', update it
        if ($row['exam_status'] !== 'approved') {
            $sync_stmt = $sync_conn->prepare("UPDATE exams SET status = 'approved' WHERE exam_id = ?");
            $sync_stmt->bind_param("i", $row['exam_id']);
            $sync_stmt->execute();
            $sync_stmt->close();
            $row['exam_status'] = 'approved';
        }
    } elseif ($approved > 0 || $revise > 0 || $rejected > 0) {
        $row['mod_status'] = 'in_progress';
    } else {
        $row['mod_status'] = 'pending';
    }

    // Filter
    if ($filter !== '' && $row['mod_status'] !== $filter) {
        continue;
    }

    $exams[] = $row;
}

$sync_conn->close();
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
<style>
/* Moderation Status Badges */
.mod-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
    white-space: nowrap;
}
.mod-badge.pending       { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
.mod-badge.in_progress   { background: rgba(37,99,235,0.08); color: #2563eb; border: 1px solid rgba(37,99,235,0.25); }
.mod-badge.fully_approved{ background: rgba(34,197,94,0.1);  color: #15803d; border: 1px solid rgba(34,197,94,0.35); }
.mod-badge.no_questions  { background: #fff7ed; color: #b45309; border: 1px solid #fed7aa; }

/* Progress bar */
.mod-progress {
    margin-top: 5px;
    height: 5px;
    border-radius: 3px;
    background: #e2e8f0;
    overflow: hidden;
    width: 120px;
}
.mod-progress-fill {
    height: 100%;
    border-radius: 3px;
    background: #22c55e;
    transition: width 0.4s;
}

/* Locked action */
.btn-locked {
    background: #f1f5f9;
    color: #94a3b8;
    border: 1px solid #e2e8f0;
    cursor: default;
    pointer-events: none;
}
</style>
</head>
<body>

<?php include __DIR__ . '/../common/header.php'; ?>

<div class="dashboard">

    <?php include __DIR__ . '/../common/sidebar.php'; ?>

    <div class="content">

        <div class="page-header">
            <div>
                <h2 class="page-title">Moderation Tasks</h2>
                <p class="page-subtitle">Exams assigned to you for moderation</p>
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

            <select name="filter">
                <option value="">All Status</option>
                <option value="pending"        <?= $filter === 'pending'        ? 'selected' : '' ?>>Pending</option>
                <option value="in_progress"    <?= $filter === 'in_progress'    ? 'selected' : '' ?>>In Progress</option>
                <option value="fully_approved" <?= $filter === 'fully_approved' ? 'selected' : '' ?>>Fully Approved</option>
                <option value="no_questions"   <?= $filter === 'no_questions'   ? 'selected' : '' ?>>No Questions</option>
            </select>

            <button type="submit" class="btn btn-primary">Filter</button>

        </form>

        <div class="card">

            <div class="table-container">

                <table>

                    <thead>
                        <tr>
                            <th>Exam Title</th>
                            <th>Subject</th>
                            <th>Item Writer</th>
                            <th>Year</th>
                            <th>Class</th>
                            <th>Moderation Status</th>
                            <th>Progress</th>
                            <th width="200">Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (empty($exams)): ?>

                        <tr>
                            <td colspan="8" style="text-align:center; color:#64748b; padding:32px;">
                                No exams assigned for moderation.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($exams as $exam):
                            $total    = (int)$exam['total_q'];
                            $approved = (int)$exam['approved_q'];
                            $pct      = $total > 0 ? round(($approved / $total) * 100) : 0;
                            $ms       = $exam['mod_status'];

                            $badgeLabels = [
                                'pending'        => 'Pending',
                                'in_progress'    => 'In Progress',
                                'fully_approved' => 'Fully Approved',
                                'no_questions'   => 'No Questions',
                            ];
                            $badgeLabel = $badgeLabels[$ms] ?? ucfirst($ms);

                            $isFullyApproved = ($ms === 'fully_approved');
                        ?>

                            <tr>

                                <td><strong><?= htmlspecialchars($exam['exam_name']) ?></strong></td>

                                <td><?= htmlspecialchars($exam['subject_name']) ?></td>

                                <td><?= htmlspecialchars($exam['teacher_name']) ?></td>

                                <td><?= htmlspecialchars($exam['year']) ?></td>

                                <td><?= htmlspecialchars($exam['class']) ?></td>

                                <td>
                                    <span class="mod-badge <?= $ms ?>">
                                        <?= $isFullyApproved ? '&#10003; ' : '' ?><?= $badgeLabel ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($total > 0): ?>
                                    <div style="font-size:11px; color:#64748b; margin-bottom:3px;">
                                        <?= $approved ?> / <?= $total ?> approved
                                    </div>
                                    <div class="mod-progress">
                                        <div class="mod-progress-fill" style="width:<?= $pct ?>%"></div>
                                    </div>
                                    <?php else: ?>
                                    <span style="font-size:12px; color:#94a3b8;">—</span>
                                    <?php endif; ?>
                                </td>

                                <td class="actions">

                                    <?php if ($isFullyApproved): ?>
                                        <span class="btn btn-small btn-locked" title="All questions approved — no further moderation needed">
                                            Fully Moderated
                                        </span>
                                    <?php else: ?>
                                        <a
                                            href="moderate_exam.php?exam_id=<?= $exam['exam_id'] ?>&subject_id=<?= $exam['subject_id'] ?>"
                                            class="btn btn-teal btn-small">
                                            Moderate
                                        </a>
                                    <?php endif; ?>

                                    <a
                                        href="<?= BASE_URL ?>/teacher/exam.php?id=<?= $exam['exam_id'] ?>&subject_id=<?= $exam['subject_id'] ?>&page=cover"
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