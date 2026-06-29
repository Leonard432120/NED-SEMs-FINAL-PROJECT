<?php
/* ════════════════════════════════════════════════════════════════
   headteacher/teacher_performance.php
   HT: Display marking performance metrics for a specific teacher.
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'headteacher') {
    header("Location: ../login.php"); exit();
}

$conn      = get_db_connection();
$school_id = (int)($_SESSION['school_id'] ?? 0);
$teacher_id = (int)($_GET['id'] ?? 0);

// Fetch teacher details
$stmt = $conn->prepare("
    SELECT * FROM users 
    WHERE user_id = ? AND school_id = ? AND role = 'teacher'
");
$stmt->bind_param("ii", $teacher_id, $school_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$teacher) {
    die("Teacher not found or does not belong to your school.");
}

// Fetch subject average scores and pass rates for subjects marked by this teacher
$perf_stmt = $conn->prepare("
    SELECT 
        sub.subject_id, sub.subject_name, sub.category,
        AVG(m.score) AS avg_score,
        COUNT(DISTINCT m.student_id) AS student_count,
        SUM(CASE WHEN m.score >= 40 THEN 1 ELSE 0 END) AS passed_count,
        COUNT(m.mark_id) AS total_entries
    FROM marks m
    JOIN subjects sub ON m.subject_id = sub.subject_id
    JOIN students st ON m.student_id = st.student_id
    WHERE m.teacher_id = ? AND st.school_id = ?
    GROUP BY sub.subject_id, sub.subject_name, sub.category
    ORDER BY avg_score DESC
");
$perf_stmt->bind_param("ii", $teacher_id, $school_id);
$perf_stmt->execute();
$subject_performances = $perf_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$perf_stmt->close();

// Fetch overall summary statistics for this teacher's markings
$summary_stmt = $conn->prepare("
    SELECT 
        AVG(m.score) AS school_avg,
        COUNT(m.mark_id) AS total_marks,
        SUM(CASE WHEN m.score >= 40 THEN 1 ELSE 0 END) AS passed_marks
    FROM marks m
    JOIN students st ON m.student_id = st.student_id
    WHERE m.teacher_id = ? AND st.school_id = ?
");
$summary_stmt->bind_param("ii", $teacher_id, $school_id);
$summary_stmt->execute();
$marking_summary = $summary_stmt->get_result()->fetch_assoc();
$summary_stmt->close();

$total_marked = (int)($marking_summary['total_marks'] ?? 0);
$passed_marked = (int)($marking_summary['passed_marks'] ?? 0);
$overall_avg = round((float)($marking_summary['school_avg'] ?? 0), 1);
$overall_pass_rate = $total_marked > 0 ? round(($passed_marked / $total_marked) * 100, 1) : 0;

// Fetch recent mark submissions by this teacher
$recent_stmt = $conn->prepare("
    SELECT 
        st.name AS student_name, st.exam_number, st.class,
        sub.subject_name,
        m.score, m.grade, m.status, m.created_at
    FROM marks m
    JOIN students st ON m.student_id = st.student_id
    JOIN subjects sub ON m.subject_id = sub.subject_id
    WHERE m.teacher_id = ? AND st.school_id = ?
    ORDER BY m.created_at DESC
    LIMIT 10
");
$recent_stmt->bind_param("ii", $teacher_id, $school_id);
$recent_stmt->execute();
$recent_entries = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_stmt->close();

$conn->close();

$module_css = 'headteacher';
include __DIR__ . '/../common/head_assets.php';

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
<title>Teacher Performance | NED-SEMS</title>
<style>
.performance-grid {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 20px;
    align-items: start;
}
.perf-header {
    border-bottom: 2px solid var(--primary-dark);
    padding-bottom: 10px;
    margin-top: 0;
    margin-bottom: 15px;
}
.progress-bar-container {
    height: 10px;
    background-color: #e2e8f0;
    border-radius: 5px;
    overflow: hidden;
    margin-top: 5px;
    margin-bottom: 15px;
}
.progress-bar-fill {
    height: 100%;
    background-color: var(--primary-dark);
    transition: width 0.3s;
}
.metric-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
    font-weight: 500;
}
</style>
</head>
<body>
<?php include '../common/header.php'; ?>
<div class="dashboard">
<?php include '../common/sidebar.php'; ?>
<div class="content">

<div class="page-header">
    <div>
        <h2 class="page-title">Teacher Performance Analytics</h2>
        <p class="page-subtitle">Marking analysis for <strong><?= htmlspecialchars($teacher['name']) ?></strong></p>
    </div>
    <div class="actions">
        <a href="manage_teachers.php" class="btn btn-secondary">Back to Directory</a>
        <a href="view_teacher.php?id=<?= $teacher['user_id'] ?>" class="btn btn-dark">View Profile</a>
    </div>
</div>

<div class="performance-grid">
    <!-- Left Profile Summary Card -->
    <div>
        <div class="card" style="padding: 20px; text-align: center; margin-bottom: 20px;">
            <img src="<?= $teacher['profile_image'] ? BASE_URL . '/uploads/profiles/' . $teacher['profile_image'] : BASE_URL . '/assets/images/default-avatar.png' ?>" 
                 alt="Profile Picture" style="width:100px; height:100px; border-radius:50%; object-fit:cover; margin-bottom:10px;"
                 onerror="this.src='<?= BASE_URL ?>/assets/images/default-avatar.png'">
            <h3 style="margin: 5px 0;"><?= htmlspecialchars($teacher['name']) ?></h3>
            <div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:15px;">
                Major: <?= htmlspecialchars($teacher['major_subject'] ?: '—') ?><br>
                Minor: <?= htmlspecialchars($teacher['minor_subject'] ?: '—') ?>
            </div>
            
            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 15px 0;">
            
            <div style="text-align: left;">
                <div style="margin-bottom: 10px;">
                    <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">OVERALL AVERAGE SCORE</div>
                    <div style="font-size:1.5rem; font-weight:700; color:var(--primary-dark);"><?= $overall_avg ?>%</div>
                </div>
                <div style="margin-bottom: 10px;">
                    <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">STUDENT PASS RATE (>=40%)</div>
                    <div style="font-size:1.5rem; font-weight:700; color:#16a34a;"><?= $overall_pass_rate ?>%</div>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">TOTAL SCRIPTS MARKED</div>
                    <div style="font-size:1.5rem; font-weight:700; color:#3b82f6;"><?= $total_marked ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Detailed Analytics -->
    <div>
        <!-- Subject Metrics Card -->
        <div class="card" style="padding: 20px; margin-bottom: 20px;">
            <h3 class="perf-header">Subject Performance Breakdown</h3>
            <?php if (!empty($subject_performances)): ?>
                <?php foreach ($subject_performances as $sp): ?>
                    <?php 
                    $pass_rate = $sp['total_entries'] > 0 ? round(($sp['passed_count'] / $sp['total_entries']) * 100, 1) : 0;
                    $avg = round($sp['avg_score'], 1);
                    ?>
                    <div style="margin-bottom: 20px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <strong><?= htmlspecialchars($sp['subject_name']) ?></strong>
                            <span style="font-size:0.8rem; color:var(--text-muted);">
                                <?= $sp['total_entries'] ?> entries | Pass Rate: <strong><?= $pass_rate ?>%</strong>
                            </span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" style="width: <?= $avg ?>%;"></div>
                        </div>
                        <div class="metric-row">
                            <span>Subject Average Score:</span>
                            <span class="performance-indicator <?= perf_class($avg) ?>"><?= $avg ?>%</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color:var(--text-muted); text-align:center; padding: 20px;">No subject marking entries recorded for this teacher.</p>
            <?php endif; ?>
        </div>

        <!-- Recent Submissions Table -->
        <div class="card" style="padding: 20px;">
            <h3 class="perf-header">Recent Marked Scripts Submissions</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Subject</th>
                            <th>Score</th>
                            <th>Grade</th>
                            <th>Status</th>
                            <th>Date Marked</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_entries)): ?>
                            <?php foreach ($recent_entries as $re): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($re['student_name']) ?></strong>
                                        <div style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($re['exam_number']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($re['class'] ?: '—') ?></td>
                                    <td><?= htmlspecialchars($re['subject_name']) ?></td>
                                    <td><strong><?= round($re['score'], 1) ?>%</strong></td>
                                    <td><span class="badge" style="background:#f1f5f9; color:#334155; font-weight:700;"><?= htmlspecialchars($re['grade'] ?: '—') ?></span></td>
                                    <td>
                                        <span class="badge badge-<?= strtolower($re['status']) ?>">
                                            <?= ucfirst(htmlspecialchars($re['status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d M Y H:i', strtotime($re['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center" style="padding:15px; color:var(--text-muted);">No recent mark submissions.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div>
</div>
<?php include '../common/footer.php'; ?>
</body>
</html>
