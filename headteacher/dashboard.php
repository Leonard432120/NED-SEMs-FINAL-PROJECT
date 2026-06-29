<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'headteacher') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();
$school_id = (int)$_SESSION['school_id'];
$user_name = $_SESSION['name'] ?? 'Headteacher';

$school = $conn->query("SELECT school_name, district FROM schools WHERE school_id = $school_id")->fetch_assoc();
$school_name = $school['school_name'] ?? 'Your School';
$school_district = $school['district'] ?? '';

$school_users = "SELECT user_id FROM users WHERE school_id = $school_id";

$student_count = (int)$conn->query("SELECT COUNT(*) AS t FROM students WHERE school_id = $school_id")->fetch_assoc()['t'];
$active_students = (int)$conn->query("SELECT COUNT(*) AS t FROM students WHERE school_id = $school_id AND status='active'")->fetch_assoc()['t'];
$teacher_count = (int)$conn->query("SELECT COUNT(*) AS t FROM users WHERE school_id = $school_id AND role='teacher' AND status='active'")->fetch_assoc()['t'];
$exam_officer_count = (int)$conn->query("SELECT COUNT(*) AS t FROM users WHERE school_id = $school_id AND role='examination_officer' AND status='active'")->fetch_assoc()['t'];

$class_count = (int)$conn->query("SELECT COUNT(DISTINCT class) AS t FROM students WHERE school_id = $school_id AND class IS NOT NULL")->fetch_assoc()['t'];

$total_exams = (int)$conn->query("SELECT COUNT(*) AS t FROM exams WHERE created_by IN ($school_users)")->fetch_assoc()['t'];
$pending_exams = (int)$conn->query("SELECT COUNT(*) AS t FROM exams WHERE created_by IN ($school_users) AND status IN ('draft','under_moderation','submitted')")->fetch_assoc()['t'];
$approved_exams = (int)$conn->query("SELECT COUNT(*) AS t FROM exams WHERE created_by IN ($school_users) AND status='approved'")->fetch_assoc()['t'];

$result_count = (int)$conn->query("SELECT COUNT(*) AS t FROM marks m JOIN students s ON m.student_id=s.student_id WHERE s.school_id=$school_id")->fetch_assoc()['t'];
$pending_marks = (int)$conn->query("SELECT COUNT(*) AS t FROM marks m JOIN students s ON m.student_id=s.student_id WHERE s.school_id=$school_id AND m.status IN ('draft','submitted')")->fetch_assoc()['t'];
$approved_results = (int)$conn->query("SELECT COUNT(*) AS t FROM results r JOIN students s ON r.student_id=s.student_id WHERE s.school_id=$school_id AND r.status IN ('eo_approved','head_approved','edm_approved')")->fetch_assoc()['t'];
 
$avg_row = $conn->query("SELECT AVG(m.score) AS avg FROM marks m JOIN students s ON m.student_id=s.student_id WHERE s.school_id=$school_id")->fetch_assoc();
$avg_score = round((float)($avg_row['avg'] ?? 0), 1);

$pass_row = $conn->query("
    SELECT
    SUM(CASE WHEN m.score >= 40 THEN 1 ELSE 0 END) AS passed,
    COUNT(*) AS total
    FROM marks m
    JOIN students s ON m.student_id=s.student_id
    WHERE s.school_id=$school_id
")->fetch_assoc();
$pass_rate = ($pass_row['total'] ?? 0) > 0 ? round(($pass_row['passed'] / $pass_row['total']) * 100, 1) : 0;

$exam_status_chart = $conn->query("
    SELECT status, COUNT(*) AS cnt
    FROM exams WHERE created_by IN ($school_users)
    GROUP BY status
");
$chart_labels = [];
$chart_data = [];
while ($row = $exam_status_chart->fetch_assoc()) {
    $chart_labels[] = ucwords(str_replace('_', ' ', $row['status']));
    $chart_data[] = (int)$row['cnt'];
}

$top_classes = $conn->query("
    SELECT
        s.class,
        COUNT(DISTINCT s.student_id) AS students,
        ROUND(AVG(r.average_score), 1) AS avg_score
    FROM students s
    INNER JOIN results r ON r.student_id = s.student_id
    WHERE s.school_id = $school_id
    AND s.class IS NOT NULL
    GROUP BY s.class
    ORDER BY avg_score DESC
    LIMIT 5
");

$top_subjects = $conn->query("
    SELECT
    sub.subject_name,
    ROUND(AVG(m.score),1) AS avg_score,
    COUNT(*) AS entries
    FROM marks m
    JOIN subjects sub ON m.subject_id=sub.subject_id
    JOIN students st ON m.student_id=st.student_id
    WHERE st.school_id=$school_id
    GROUP BY sub.subject_id
    ORDER BY avg_score DESC
    LIMIT 5
");

$active_teachers = $conn->query("
    SELECT u.name, u.last_login, u.status,
           (
               SELECT COUNT(*) 
               FROM subject_assignments sa 
               WHERE sa.teacher_id = u.user_id
           ) AS assignments
    FROM users u
    WHERE u.school_id = $school_id
      AND u.role = 'teacher'
    ORDER BY u.last_login DESC
    LIMIT 5
");
$announcements = $conn->query("
    SELECT
        a.id,
        a.title,
        a.content AS comment,
        a.created_at,
        u.name AS author
    FROM announcements a
    LEFT JOIN users u ON a.published_by = u.user_id
    WHERE a.school_id IS NULL OR a.school_id = $school_id
    ORDER BY a.created_at DESC
    LIMIT 4
");

/* ================= FIXED RECENT EXAMS (No subject_id join) ================= */
$recent_exams = $conn->query("
    SELECT 
        e.exam_id, 
        e.exam_name, 
        e.start_date, 
        e.end_date,
        e.status, 
        u.name AS teacher_name
    FROM exams e
    LEFT JOIN users u ON e.created_by = u.user_id
    WHERE e.created_by IN ($school_users)
    ORDER BY e.exam_id DESC
    LIMIT 7
");

$marking_delegation_query = $conn->query("
    SELECT 
        e.exam_name, e.class,
        s.subject_name,
        u.name AS marker_name,
        ma.deadline,
        ma.exam_id,
        ma.subject_id,
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
              AND st.school_id = ma.school_id AND m.status IN ('submitted', 'approved')
        ) AS submitted_count
    FROM marking_assignments ma
    JOIN exams e ON ma.exam_id = e.exam_id
    JOIN subjects s ON ma.subject_id = s.subject_id
    JOIN users u ON ma.teacher_id = u.user_id
    WHERE ma.school_id = $school_id
    ORDER BY ma.deadline ASC, e.exam_name ASC
    LIMIT 5
");
$marking_delegations = [];
if ($marking_delegation_query) {
    while ($row = $marking_delegation_query->fetch_assoc()) {
        $marking_delegations[] = $row;
    }
}

$conn->close();

function perf_class($score) {
    if ($score >= 70) return 'performance-indicator--high';
    if ($score >= 40) return 'performance-indicator--mid';
    return 'performance-indicator--low';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Headteacher Dashboard</title>
<?php $module_css = 'headteacher'; include __DIR__ . '/../common/head_assets.php'; ?>
</head>
<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
<?php include '../common/sidebar.php'; ?>

<div class="content">

<!-- ================= PAGE HEADER ================= -->
<div class="page-header">
    <h2 class="page-title">Headteacher Dashboard Overview</h2>
</div>

<!-- ================= STATISTICS ================= -->
<div class="stats">

    <div class="stat-box">
        <h4>Total Students</h4>
        <p><?= $student_count ?></p>
    </div>

    <div class="stat-box">
        <h4>Teachers</h4>
        <p><?= $teacher_count ?></p>
    </div>

    <div class="stat-box">
        <h4>Total Exams</h4>
        <p><?= $total_exams ?></p>
    </div>

    <div class="stat-box">
        <h4>Approved Results</h4>
        <p><?= $approved_results ?></p>
    </div>

    <div class="stat-box">
        <h4>Average Score</h4>
        <p><?= $avg_score ?>%</p>
    </div>

</div>

<!-- ================= QUICK ACTIONS ================= -->
<div class="card">
    <h3>Quick Actions</h3>

    <div class="quick-links">

        <a href="manage_teachers.php">
            Manage Teachers
        </a>

        <a href="manage_students.php">
            Manage Students
        </a>

        <a href="classes.php">
            Class Overview
        </a>

        <a href="performance.php">
            Performance Reports
        </a>

        <a href="marks_reception.php">
            Receive Marks
        </a>

        <a href="announcements.php">
            Announcements
        </a>

    </div>
</div>

<!-- ================= MARKING DELEGATION & DEADLINES ================= -->
<div class="card" style="margin-bottom: 25px; padding: 20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
        <div>
            <h3 style="margin:0;">Marking Delegation & Deadlines</h3>
            <p class="muted-text" style="margin: 5px 0 0 0; font-size:0.9rem; color:var(--text-muted);">Monitor teacher marking progress and submission deadlines.</p>
        </div>
        <a href="assign_markers.php" class="btn btn-small btn-primary">Assign Markers</a>
    </div>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Exam & Subject</th>
                    <th>Marker</th>
                    <th>Progress</th>
                    <th>Deadline</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($marking_delegations)): ?>
                    <?php foreach ($marking_delegations as $md): ?>
                        <?php 
                        $total = (int)$md['total_students'];
                        $sub_cnt = (int)$md['submitted_count'];
                        $pct = $total > 0 ? round(($sub_cnt / $total) * 100) : 0;
                        
                        $is_past = $md['deadline'] && strtotime($md['deadline']) < strtotime(date('Y-m-d'));
                        
                        $status_label = 'Pending';
                        $status_class = 'warning';
                        
                        if ($sub_cnt === 0) {
                            if ($is_past) {
                                $status_label = 'Overdue';
                                $status_class = 'danger';
                            } else {
                                $status_label = 'Not Started';
                                $status_class = 'secondary';
                            }
                        } elseif ($sub_cnt < $total) {
                            if ($is_past) {
                                $status_label = 'Overdue';
                                $status_class = 'danger';
                            } else {
                                $status_label = 'In Progress';
                                $status_class = 'info';
                            }
                        } else {
                            $status_label = 'Submitted';
                            $status_class = 'success';
                        }
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($md['subject_name']) ?></strong>
                                <div style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($md['exam_name']) ?> (<?= htmlspecialchars($md['class']) ?>)</div>
                            </td>
                            <td><?= htmlspecialchars($md['marker_name']) ?></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span style="font-size:0.8rem; font-weight:600; min-width:35px;"><?= $sub_cnt ?>/<?= $total ?></span>
                                    <div class="progress-bar" style="flex:1; margin:0; height:8px; background:#e2e8f0; border-radius:4px; overflow:hidden;">
                                        <div class="progress-fill" style="width: <?= $pct ?>%; height:100%; background:var(--primary-dark); transition: width 0.3s;"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?= $md['deadline'] ? date('d M Y', strtotime($md['deadline'])) : 'No Deadline' ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $status_class ?>"><?= $status_label ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center" style="padding: 20px;">
                            No marking assignments configured yet. <a href="assign_markers.php">Assign markers now</a>.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel-grid">
    <div class="section">
        <div class="section-header">
            <div>
                <h3>Exam Status Distribution</h3>
                <p class="muted-text">Breakdown of exams across workflow stages.</p>
            </div>
        </div>
          <?php include '../common/exam_status_chart.php'; ?>
    </div>

    <div class="section">
        <div class="section-header">
            <div>
                <h3>Performance Snapshot</h3>
                <p class="muted-text">Key institutional indicators at a glance.</p>
            </div>
        </div>
        <div class="alert-grid" style="grid-template-columns: 1fr;">
            <div class="results-info">
                <strong>School Pass Rate:</strong> <?= $pass_rate ?>% of recorded results meet the 40% threshold.
            </div>
            <div class="insight-card">
                <h4>Results Pipeline</h4>
                <div class="insight-card__row"><span>Total recorded</span><strong><?= $result_count ?></strong></div>
                <div class="insight-card__row"><span>Pending review</span><span class="badge badge-submitted"><?= $pending_marks ?></span></div>
                <div class="insight-card__row"><span>Approved</span><span class="badge badge-approved"><?= $approved_results ?></span></div>
                <div class="insight-card__row"><span>Average score</span><span class="performance-indicator <?= perf_class($avg_score) ?>"><?= $avg_score ?>%</span></div>
            </div>
        </div>
    </div>
</div>

<div class="insight-grid">
    <div class="insight-card">
        <h4>Top Performing Classes</h4>
        <?php if ($top_classes && $top_classes->num_rows > 0): ?>
            <?php while ($c = $top_classes->fetch_assoc()): ?>
                <div class="insight-card__row">
                    <span><?= htmlspecialchars($c['class']) ?> <small class="muted-text">(<?= (int)$c['students'] ?> students)</small></span>
                    <span class="performance-indicator <?= perf_class($c['avg_score'] ?? 0) ?>"><?= $c['avg_score'] ?? '—' ?>%</span>
                </div>
                <div class="progress-bar"><div class="progress-fill" style="width: <?= min(100, (float)($c['avg_score'] ?? 0)) ?>%"></div></div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="muted-text">No class performance data yet.</p>
        <?php endif; ?>
        <a href="classes.php" class="btn btn-small btn-secondary" style="margin-top:12px;">View all classes</a>
    </div>

    <div class="insight-card">
        <h4>Subject Performance</h4>
        <?php if ($top_subjects && $top_subjects->num_rows > 0): ?>
            <?php while ($s = $top_subjects->fetch_assoc()): ?>
                <div class="insight-card__row">
                    <span><?= htmlspecialchars($s['subject_name']) ?></span>
                    <span class="performance-indicator <?= perf_class($s['avg_score']) ?>"><?= $s['avg_score'] ?>%</span>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="muted-text">No subject results yet.</p>
        <?php endif; ?>
        <a href="performance.php" class="btn btn-small btn-secondary" style="margin-top:12px;">Detailed analytics</a>
    </div>

    <div class="insight-card">
        <h4>Teacher Activity</h4>
        <?php if ($active_teachers && $active_teachers->num_rows > 0): ?>
            <?php while ($t = $active_teachers->fetch_assoc()): ?>
                <div class="insight-card__row">
                    <span><?= htmlspecialchars($t['name']) ?></span>
                    <span class="badge badge-<?= $t['status'] === 'active' ? 'active' : 'inactive' ?>"><?= (int)$t['assignments'] ?> tasks</span>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="muted-text">No teachers registered.</p>
        <?php endif; ?>
        <a href="manage_teachers.php" class="btn btn-small btn-secondary" style="margin-top:12px;">Manage staff</a>
    </div>
</div>

<div class="panel-grid">
    <div class="section" id="announcements">
        <div class="section-header">
            <div>
                <h3>Latest Announcements</h3>
                <p class="muted-text">School-wide notices and updates.</p>
            </div>
            <a href="../common/announcements.php" class="btn btn-small btn-primary">Manage</a>
        </div>
        <div class="announcement-list">
            <?php if ($announcements && $announcements->num_rows > 0): ?>
                <?php while ($a = $announcements->fetch_assoc()): ?>
                    <div class="announcement-item">
                        <time><?= date('d M Y', strtotime($a['created_at'])) ?> · <?= htmlspecialchars($a['title'] ?? 'Notice') ?></time>
                        <p><?= nl2br(htmlspecialchars($a['comment'] ?? '')) ?></p>
                        <?php if (!empty($a['author'])): ?><small class="muted-text">— <?= htmlspecialchars($a['author']) ?></small><?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="empty-state">No announcements yet. <a href="../common/announcements.php">Create the first one</a>.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="section">
        <div class="section-header">
            <div>
                <h3>Recent Exams</h3>
                <p class="muted-text">Latest examinations in your school.</p>
            </div>
        </div>
        <div class="table-container">
            <table class="table-striped">
                <thead>
                    <tr>
                        <th>Exam</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_exams && $recent_exams->num_rows > 0): ?>
                        <?php while ($exam = $recent_exams->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($exam['exam_name']) ?></td>
                                <td>
                                    <?= $exam['start_date'] ? date('d M Y', strtotime($exam['start_date'])) : 'TBD' ?> 
                                    - 
                                    <?= $exam['end_date'] ? date('d M Y', strtotime($exam['end_date'])) : 'TBD' ?>
                                </td>
                                <td><span class="badge badge-<?= htmlspecialchars(str_replace('_', '-', $exam['status'])) ?>"><?= ucwords(str_replace('_', ' ', $exam['status'])) ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="text-center">No exams found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>
</div>

<?php include '../common/footer.php'; ?>

<script>
new Chart(document.getElementById('examStatusChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
            data: <?= json_encode($chart_data) ?>,
            backgroundColor: ['#facc15', '#3b82f6', '#22c55e', '#ef4444', '#94a3b8', '#a855f7'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>
</body>
</html>