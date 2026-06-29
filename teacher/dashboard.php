<?php
require_once __DIR__ . '/teacher_init.php';
$conn = get_db_connection();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Teacher';

/* ================= ROLE COUNTS ================= */

// Item Writer Tasks
$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM subject_assignments 
    WHERE teacher_id=? AND role='item_writer'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$item_writer = $stmt->get_result()->fetch_assoc()['total'];

// Moderator Tasks
$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM subject_assignments 
    WHERE teacher_id=? AND role='moderator'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$moderator = $stmt->get_result()->fetch_assoc()['total'];

// Submissions
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT e.exam_id) as total
    FROM subject_assignments ea
    JOIN exam_subjects es ON ea.subject_id = es.subject_id
    JOIN exams e ON es.exam_id = e.exam_id
    WHERE ea.teacher_id=?
    AND ea.role='item_writer'
    AND e.status IN ('submitted','under_moderation','approved')
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$submissions = $stmt->get_result()->fetch_assoc()['total'];

// Pending Reviews
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT e.exam_id) as total
    FROM subject_assignments ea
    JOIN exam_subjects es ON ea.subject_id = es.subject_id
    JOIN exams e ON es.exam_id = e.exam_id
    WHERE ea.teacher_id=?
    AND ea.role='item_writer'
    AND e.status='under_moderation'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending = $stmt->get_result()->fetch_assoc()['total'];

/* ================= MARKING ASSIGNMENTS FOR DASHBOARD ================= */
$school_id = isset($_SESSION['school_id']) ? (int)$_SESSION['school_id'] : 0;
$stmt = $conn->prepare("
    SELECT 
        ma.assignment_id,
        e.exam_name, e.class, e.exam_id,
        s.subject_name, s.subject_id,
        ma.deadline,
        ma.override_lock,
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
$stmt->bind_param("ii", $user_id, $school_id);
$stmt->execute();
$marking_assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ================= FETCH RECENT ANNOUNCEMENTS ================= */
$school_id = isset($_SESSION['school_id']) ? (int)$_SESSION['school_id'] : 0;
$ann_stmt = $conn->prepare("
    SELECT a.*, u.name AS author_name, u.role AS author_role
    FROM announcements a
    LEFT JOIN users u ON a.published_by = u.user_id
    WHERE a.school_id IS NULL OR a.school_id = ?
    ORDER BY a.created_at DESC LIMIT 3
");
$ann_stmt->bind_param("i", $school_id);
$ann_stmt->execute();
$announcements = $ann_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$ann_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Teacher Dashboard</title>

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

<!-- ================= CONTENT ================= -->
<div class="content">

    <div class="page-header">
        <h2 class="page-title">Teacher Dashboard Overview</h2>
    </div>

    <!-- ================= STATS ================= -->
    <div class="stats">

        <div class="stat-box">
            <h4>Item Writer Tasks</h4>
            <p><?= $item_writer ?></p>
        </div>

        <div class="stat-box">
            <h4>Moderator Tasks</h4>
            <p><?= $moderator ?></p>
        </div>

        <div class="stat-box">
            <h4>Submissions</h4>
            <p><?= $submissions ?></p>
        </div>

        <div class="stat-box">
            <h4>Pending Reviews</h4>
            <p><?= $pending ?></p>
        </div>

    </div>

    <!-- ================= QUICK ACTIONS ================= -->
    <div class="card">
        <h3>Quick Actions</h3>

        <div class="quick-links">

            <a href="<?= BASE_URL ?>/teacher/assigned_exams.php">
                Assigned Exams
            </a>

            <a href="<?= BASE_URL ?>/teacher/moderation_exams.php">
                Moderation Tasks
            </a>

            <a href="<?= BASE_URL ?>/teacher/marks_activities.php">
                Marks Activities
            </a>

            <a href="<?= BASE_URL ?>/teacher/my_submissions.php">
                My Submissions
            </a>

            </div>
    </div>

    <!-- ================= ANNOUNCEMENTS ================= -->
    <div class="card" style="margin-bottom: 25px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="margin:0;">Recent Announcements</h3>
            <a href="../common/announcements.php" class="btn btn-secondary btn-small">View All</a>
        </div>
        <div class="announcement-list" style="display:flex; flex-direction:column; gap:15px;">
            <?php if (!empty($announcements)): ?>
                <?php foreach ($announcements as $ann): ?>
                    <div style="padding:15px; border-radius:8px; border:1px solid #e2e8f0; background:#f8fafc;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <strong><?= htmlspecialchars($ann['title']) ?></strong>
                            <span style="font-size:0.75rem; color:#64748b;">
                                <?= date('M j, Y', strtotime($ann['created_at'])) ?>
                            </span>
                        </div>
                        <p style="margin:0 0 8px 0; color:#334155; line-height:1.5; font-size:0.9rem;"><?= htmlspecialchars($ann['content']) ?></p>
                        <small style="color:#64748b;">By <?= htmlspecialchars($ann['author_name'] ?? 'System') ?> (<?= ucfirst(htmlspecialchars($ann['author_role'] ?? '')) ?>)</small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="empty-state">No announcements published yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================= MARK ENTRY TABLE ================= -->
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="margin:0;">My Marking Tasks</h3>
            <a href="<?= BASE_URL ?>/teacher/marks_activities.php" class="btn btn-secondary btn-small">View All</a>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Exam & Subject</th>
                        <th>Progress</th>
                        <th>Deadline</th>
                        <th>Status</th>
                        <th style="width:150px;">Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (!empty($marking_assignments)): ?>
                    <?php 
                    // Show top 5 assignments on dashboard
                    $dashboard_assignments = array_slice($marking_assignments, 0, 5);
                    foreach ($dashboard_assignments as $as): 
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
                                <div style="display:flex; align-items:center; gap:8px; min-width:120px;">
                                    <span style="font-size:0.8rem; font-weight:600; min-width:35px;"><?= $ent_cnt ?>/<?= $total ?></span>
                                    <div class="progress-bar" style="flex:1; margin:0; height:6px; background:#e2e8f0; border-radius:3px; overflow:hidden;">
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
                                    <button class="btn btn-secondary btn-small" style="opacity: 0.5; cursor: not-allowed; padding: 4px 8px; font-size: 0.8rem;" disabled title="Locked because the deadline has passed.">
                                        🔒 Locked
                                    </button>
                                <?php else: ?>
                                    <a href="enter_marks.php?exam_id=<?= $as['exam_id'] ?>&subject_id=<?= $as['subject_id'] ?>" class="btn btn-dark btn-small" style="padding: 4px 8px; font-size: 0.8rem;">
                                        <?= ($sub_cnt === $total && $total > 0) ? 'View Marks' : 'Enter' ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center;padding:20px;">
                            You have not been assigned as a marker for any subjects.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>

    <!-- ================= ROLE INFO ================= -->
    <div class="dashboard-grid">

        <div class="card">
            <h3>Item Writer</h3>
            <p>Create and submit exam questions assigned by EDM.</p>
        </div>

        <div class="card">
            <h3>Moderator</h3>
            <p>Review and approve exam content before release.</p>
        </div>

        <div class="card">
            <h3>Marks Entry</h3>
            <p>Enter student raw scores. EDM will consolidate final results.</p>
        </div>

    </div>

</div>
</div>

<?php include __DIR__ . '/../common/footer.php'; ?>