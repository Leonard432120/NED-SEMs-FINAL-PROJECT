<?php
/* ════════════════════════════════════════════════════════════════
   teacher/marks_activities.php
   Teacher: Dashboard tracking assigned marking tasks and deadlines
   ════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/teacher_init.php';

$conn      = get_db_connection();
$teacher_id = (int)$_SESSION['user_id'];
$school_id = (int)($_SESSION['school_id'] ?? 0);

/* ── FETCH MARKING ASSIGNMENTS ── */
$assignments_query = $conn->prepare("
    SELECT 
        ma.assignment_id,
        e.exam_name, e.class, e.exam_id,
        s.subject_name, s.subject_id, s.category,
        ma.deadline,
        ma.override_lock,
        ma.assigned_at,
        (
            SELECT COUNT(*)
            FROM students st
            WHERE st.school_id = ma.school_id AND st.class = e.class AND st.status = 'active'
        ) AS total_students,
        (
            SELECT COUNT(DISTINCT m.student_id)
            FROM marks m
            JOIN students st ON m.student_id = st.student_id
            WHERE m.exam_id = ma.exam_id AND m.subject_id = ma.subject_id 
              AND st.school_id = ma.school_id
        ) AS entered_count,
        (
            SELECT COUNT(DISTINCT m.student_id)
            FROM marks m
            JOIN students st ON m.student_id = st.student_id
            WHERE m.exam_id = ma.exam_id AND m.subject_id = ma.subject_id 
              AND st.school_id = ma.school_id AND m.status IN ('submitted', 'approved')
        ) AS submitted_count
    FROM marking_assignments ma
    JOIN exams e ON ma.exam_id = e.exam_id
    JOIN subjects s ON ma.subject_id = s.subject_id
    WHERE ma.teacher_id = ? AND ma.school_id = ?
    ORDER BY ma.deadline ASC, e.exam_name ASC, s.subject_name ASC
");
$assignments_query->bind_param("ii", $teacher_id, $school_id);
$assignments_query->execute();
$assignments = $assignments_query->get_result()->fetch_all(MYSQLI_ASSOC);
$assignments_query->close();

$conn->close();

/* KPIs Calculations */
$kpi_total = count($assignments);
$kpi_completed = 0;
$kpi_locked = 0;
$kpi_pending = 0;

foreach ($assignments as $as) {
    $total = (int)$as['total_students'];
    $sub_cnt = (int)$as['submitted_count'];
    $is_past = $as['deadline'] && strtotime($as['deadline']) < strtotime(date('Y-m-d'));
    
    if ($sub_cnt > 0 && $sub_cnt === $total) {
        $kpi_completed++;
    } else {
        if ($is_past && !$as['override_lock']) {
            $kpi_locked++;
        } else {
            $kpi_pending++;
        }
    }
}

$module_css = 'teacher';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Marks Activities | NED-SEMS</title>
</head>
<body>
<?php include '../common/header.php'; ?>
<div class="dashboard">
<?php include '../common/sidebar.php'; ?>
<div class="content">

<div class="page-header">
    <div>
        <h2 class="page-title">Marks Activities</h2>
        <p class="page-subtitle">Manage and enter marks for subjects assigned to you by the Headteacher.</p>
    </div>
</div>

<!-- KPIs -->
<div class="kpi-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 20px;">
    <div class="kpi-card kpi-card--info">
        <div class="kpi-card__body">
            <span class="kpi-card__label">Pending Submissions</span>
            <span class="kpi-card__value"><?= $kpi_pending ?></span>
        </div>
    </div>
    <div class="kpi-card kpi-card--success">
        <div class="kpi-card__body">
            <span class="kpi-card__label">Completed (Submitted)</span>
            <span class="kpi-card__value"><?= $kpi_completed ?> / <?= $kpi_total ?></span>
        </div>
    </div>
    <div class="kpi-card kpi-card--danger">
        <div class="kpi-card__body">
            <span class="kpi-card__label">Locked Tasks</span>
            <span class="kpi-card__value"><?= $kpi_locked ?></span>
        </div>
    </div>
</div>

<div class="card" style="padding: 20px;">
    <h3 style="margin-top: 0; margin-bottom: 15px;">My Marking Assignments</h3>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Exam & Subject</th>
                    <th>Students Marked</th>
                    <th>Deadline</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($assignments)): ?>
                    <?php foreach ($assignments as $as): ?>
                        <?php 
                        $total = (int)$as['total_students'];
                        $ent_cnt = (int)$as['entered_count'];
                        $sub_cnt = (int)$as['submitted_count'];
                        $pct = $total > 0 ? round(($ent_cnt / $total) * 100) : 0;
                        
                        $is_past = $as['deadline'] && strtotime($as['deadline']) < strtotime(date('Y-m-d'));
                        
                        $status_label = 'Pending';
                        $status_class = 'warning';
                        $is_locked = false;
                        
                        if ($sub_cnt === $total && $total > 0) {
                            $status_label = 'Submitted';
                            $status_class = 'success';
                        } else {
                            if ($is_past) {
                                if ($as['override_lock']) {
                                    $status_label = 'Unlocked (Late)';
                                    $status_class = 'info';
                                } else {
                                    $status_label = 'Locked (Overdue)';
                                    $status_class = 'danger';
                                    $is_locked = true;
                                }
                            } else {
                                if ($ent_cnt > 0) {
                                    $status_label = 'In Progress';
                                    $status_class = 'info';
                                } else {
                                    $status_label = 'Not Started';
                                    $status_class = 'secondary';
                                }
                            }
                        }
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($as['subject_name']) ?></strong>
                                <div style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($as['exam_name']) ?> (<?= htmlspecialchars($as['class']) ?>)</div>
                            </td>
                            <td>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span style="font-size:0.8rem; font-weight:600; min-width:35px;"><?= $ent_cnt ?>/<?= $total ?></span>
                                    <div class="progress-bar" style="flex:1; margin:0; height:8px; background:#e2e8f0; border-radius:4px; overflow:hidden;">
                                        <div class="progress-fill" style="width: <?= $pct ?>%; height:100%; background:var(--primary-dark); transition: width 0.3s;"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?= $as['deadline'] ? date('d M Y', strtotime($as['deadline'])) : 'No Deadline' ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $status_class ?>"><?= $status_label ?></span>
                            </td>
                            <td>
                                <?php if ($is_locked): ?>
                                    <button class="btn btn-secondary btn-small" style="opacity: 0.5; cursor: not-allowed;" disabled title="This assignment is locked because the deadline has passed.">
                                        🔒 Locked
                                    </button>
                                <?php else: ?>
                                    <a href="enter_marks.php?exam_id=<?= $as['exam_id'] ?>&subject_id=<?= $as['subject_id'] ?>" class="btn btn-dark btn-small">
                                        <?= ($sub_cnt === $total && $total > 0) ? 'View Marks' : 'Enter Marks' ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center" style="padding:20px;">
                            You have not been assigned as a marker for any subjects.
                        </td>
                    </tr>
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
