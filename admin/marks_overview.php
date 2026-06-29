<?php
/* ════════════════════════════════════════════════════════════════
   admin/marks_overview.php
   EDM/Admin: overview of all entered marks across the entire system
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once '../config/db.php';
require_once '../common/grade_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php"); exit();
}

$conn = get_db_connection();

/* ── FILTERS ── */
$exam_id    = (int)($_GET['exam_id'] ?? 0);
$school_id  = (int)($_GET['school_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);
$status     = trim($_GET['status'] ?? '');

/* ── MASTER DATA FOR DROPDOWNS ── */
$all_exams = $conn->query("SELECT exam_id, exam_name, class FROM exams ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$all_schools = $conn->query("SELECT school_id, school_name FROM schools WHERE status='active' ORDER BY school_name")->fetch_all(MYSQLI_ASSOC);
$all_subjects = $conn->query("SELECT subject_id, subject_name, subject_code FROM subjects WHERE status='active' ORDER BY subject_name")->fetch_all(MYSQLI_ASSOC);

/* ── BUILD SEARCH QUERY ── */
$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($exam_id > 0) {
    $where_clauses[] = "m.exam_id = ?";
    $params[] = $exam_id;
    $types .= "i";
}
if ($school_id > 0) {
    $where_clauses[] = "st.school_id = ?";
    $params[] = $school_id;
    $types .= "i";
}
if ($subject_id > 0) {
    $where_clauses[] = "m.subject_id = ?";
    $params[] = $subject_id;
    $types .= "i";
}
if ($status !== '') {
    $where_clauses[] = "m.status = ?";
    $params[] = $status;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

// 1. Fetch KPI stats for the filtered set
$stats_query = "
    SELECT 
        COUNT(m.mark_id) AS total_count,
        SUM(m.status = 'approved') AS approved_count,
        SUM(m.status = 'submitted') AS pending_count,
        AVG((m.score / es.total_marks) * 100) AS avg_percentage
    FROM marks m
    JOIN students st ON m.student_id = st.student_id
    JOIN exam_subjects es ON m.exam_id = es.exam_id AND m.subject_id = es.subject_id
    WHERE {$where_sql}
";

$stmt = $conn->prepare($stats_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total_count    = (int)($stats['total_count'] ?? 0);
$approved_count = (int)($stats['approved_count'] ?? 0);
$pending_count  = (int)($stats['pending_count'] ?? 0);
$avg_percentage = (float)($stats['avg_percentage'] ?? 0);

// 2. Fetch Detailed Marks List
$list_query = "
    SELECT 
        m.mark_id, m.score, m.grade, m.status, m.submitted_at,
        st.name AS student_name, st.exam_number, st.class,
        sc.school_name, sub.subject_name, sub.subject_code, es.total_marks,
        u.name AS teacher_name
    FROM marks m
    JOIN students st ON m.student_id = st.student_id
    LEFT JOIN schools sc ON sc.school_id = st.school_id
    JOIN subjects sub ON sub.subject_id = m.subject_id
    JOIN exam_subjects es ON m.exam_id = es.exam_id AND m.subject_id = es.subject_id
    LEFT JOIN users u ON u.user_id = m.teacher_id
    WHERE {$where_sql}
    ORDER BY m.submitted_at DESC, st.name ASC
    LIMIT 100
";

$stmt = $conn->prepare($list_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$marks_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
$module_css = 'admin';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subject Marks Overview | NED-SEMS</title>
</head>
<body>
<?php include '../common/header.php'; ?>
<div class="dashboard">
    <?php include '../common/sidebar.php'; ?>
    <div class="content">

        <!-- Header -->
        <div class="page-header">
            <div>
                <h2 class="page-title">Subject Marks Overview</h2>
                <p class="page-subtitle">View and monitor entered student marks across all subjects, schools, and exams</p>
            </div>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>

        <!-- Filter Bar -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="section-header"><h3>Filter Marks</h3></div>
            <form method="GET">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Exam</label>
                        <select name="exam_id">
                            <option value="">— All Exams —</option>
                            <?php foreach ($all_exams as $ex): ?>
                                <option value="<?= $ex['exam_id'] ?>" <?= $exam_id === (int)$ex['exam_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ex['exam_name']) ?> (<?= htmlspecialchars($ex['class']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>School</label>
                        <select name="school_id">
                            <option value="">— All Schools —</option>
                            <?php foreach ($all_schools as $sch): ?>
                                <option value="<?= $sch['school_id'] ?>" <?= $school_id === (int)$sch['school_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sch['school_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Subject</label>
                        <select name="subject_id">
                            <option value="">— All Subjects —</option>
                            <?php foreach ($all_subjects as $sub): ?>
                                <option value="<?= $sub['subject_id'] ?>" <?= $subject_id === (int)$sub['subject_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sub['subject_name']) ?> (<?= htmlspecialchars($sub['subject_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">— All Status —</option>
                            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="submitted" <?= $status === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                            <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions" style="margin-top: 16px;">
                    <button type="submit" class="btn btn-dark">Apply Filters</button>
                    <a href="marks_overview.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>

        <!-- KPIs -->
        <div class="kpi-grid" style="margin-bottom: 24px;">
            <div class="kpi-card kpi-card--info">
                <div class="kpi-card__body">
                    <span class="kpi-card__label">Total Marks Loaded</span>
                    <span class="kpi-card__value"><?= $total_count ?></span>
                </div>
            </div>
            <div class="kpi-card kpi-card--success">
                <div class="kpi-card__body">
                    <span class="kpi-card__label">Approved entries</span>
                    <span class="kpi-card__value"><?= $approved_count ?></span>
                </div>
            </div>
            <div class="kpi-card kpi-card--warning">
                <div class="kpi-card__body">
                    <span class="kpi-card__label">Pending review</span>
                    <span class="kpi-card__value"><?= $pending_count ?></span>
                </div>
            </div>
            <div class="kpi-card kpi-card--info">
                <div class="kpi-card__body">
                    <span class="kpi-card__label">Average Percentage</span>
                    <span class="kpi-card__value"><?= number_format($avg_percentage, 1) ?>%</span>
                </div>
            </div>
        </div>

        <!-- Detailed List -->
        <div class="card">
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3>Detailed Mark Entries (Showing up to 100 recent)</h3>
                <span style="font-size: 0.8rem; color: var(--text-muted);">Real-time database sync</span>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Exam No.</th>
                            <th>School</th>
                            <th>Subject</th>
                            <th>Score</th>
                            <th>Grade</th>
                            <th>Status</th>
                            <th>Teacher</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($marks_list)): ?>
                            <tr>
                                <td colspan="8" class="empty-state">No mark entries match the selected filters.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($marks_list as $m): ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($m['student_name']) ?></td>
                                <td><code><?= htmlspecialchars($m['exam_number']) ?></code></td>
                                <td style="font-size: 0.8rem;"><?= htmlspecialchars($m['school_name'] ?? '—') ?></td>
                                <td>
                                    <?= htmlspecialchars($m['subject_name']) ?> 
                                    <small style="color: var(--text-muted);">(<?= htmlspecialchars($m['subject_code']) ?>)</small>
                                </td>
                                <td style="font-weight: 700;"><?= htmlspecialchars($m['score']) ?> / <?= htmlspecialchars($m['total_marks']) ?></td>
                                <td>
                                    <span class="grade-badge" style="background: var(--border-color); color: var(--text-color); font-weight: 700;">
                                        <?= htmlspecialchars($m['grade'] ?: '—') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $m['status'] === 'approved' ? 'success' : ($m['status'] === 'submitted' ? 'primary' : ($m['status'] === 'rejected' ? 'danger' : 'warning')) ?>">
                                        <?= ucfirst(htmlspecialchars($m['status'])) ?>
                                    </span>
                                </td>
                                <td style="font-size: 0.8rem;"><?= htmlspecialchars($m['teacher_name'] ?? '—') ?></td>
                            </tr>
                            <?php endforeach; ?>
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
