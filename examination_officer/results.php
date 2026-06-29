<?php
session_start();
require_once '../config/db.php';
require_once '../common/pagination_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'examination_officer') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

$school_id = (int)($_SESSION['school_id'] ?? 0);

$exam_id = (int)($_GET['exam_id'] ?? 0);
$class_filter = trim($_GET['class'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;

$message = "";

/* ================= APPROVE RESULT ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_id'])) {
    $rid = (int)$_POST['approve_id'];

    $stmt = $conn->prepare("UPDATE results SET status='eo_approved' WHERE result_id=?");
    $stmt->bind_param("i", $rid);
    $stmt->execute();
    $stmt->close();

    $message = "Result approved successfully.";
}

/* ================= BASE FILTER ================= */
$result_scope = $school_id > 0 ? "st.school_id = $school_id" : "1=1";

/* ================= COUNT (FOR PAGINATION) ================= */
$count_sql = "
    SELECT COUNT(*) AS total
    FROM results r
    JOIN students st ON r.student_id = st.student_id
    JOIN exams e ON r.exam_id = e.exam_id
    WHERE $result_scope
";

if ($exam_id > 0) $count_sql .= " AND r.exam_id = $exam_id";
if ($class_filter !== '') $count_sql .= " AND st.class = '" . $conn->real_escape_string($class_filter) . "'";
if ($status_filter !== '') $count_sql .= " AND r.status = '" . $conn->real_escape_string($status_filter) . "'";

$total = $conn->query($count_sql)->fetch_assoc()['total'];

/* ================= PAGINATION ================= */
$pagination = paginate($total, $page, $per_page);
$offset = ($page - 1) * $per_page;

/* ================= MAIN DATA ================= */
$sql = "
    SELECT 
        r.result_id,
        r.student_id,
        r.exam_id,
        r.total_score,
        ROUND(r.total_score, 1) AS percentage,
        r.grade,
        r.status AS result_status,
        r.locked,
        st.name AS student_name,
        st.class,
        e.exam_name
    FROM results r
    JOIN students st ON r.student_id = st.student_id
    JOIN exams e ON r.exam_id = e.exam_id
    WHERE $result_scope
";

if ($exam_id > 0) $sql .= " AND r.exam_id = $exam_id";
if ($class_filter !== '') $sql .= " AND st.class = '" . $conn->real_escape_string($class_filter) . "'";
if ($status_filter !== '') $sql .= " AND r.status = '" . $conn->real_escape_string($status_filter) . "'";

$sql .= " ORDER BY r.compiled_at DESC LIMIT $per_page OFFSET $offset";

$results = $conn->query($sql);

/* ================= EXAMS FOR FILTER ================= */
$exams = $conn->query("SELECT exam_id, exam_name FROM exams ORDER BY exam_name");

/* ================= STATS (FIXED AMBIGUOUS STATUS) ================= */
$stats_sql = "
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN r.status IN ('draft','submitted') THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN r.status='eo_approved' THEN 1 ELSE 0 END) AS approved
    FROM results r
    JOIN students st ON r.student_id = st.student_id
    WHERE $result_scope
";

$stats = $conn->query($stats_sql)->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Results Management</title>

<?php $module_css = 'exam_officer'; include __DIR__ . '/../common/head_assets.php'; ?>
</head>

<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
<?php include '../common/sidebar.php'; ?>

<div class="content">

<!-- HEADER -->
<div class="page-header">
    <div>
        <h1 class="page-title">Results Management</h1>
        <p class="stats-info">Review and approve student results.</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<!-- KPI -->
<div class="kpi-grid">
    <div class="kpi-card kpi-card--info">
        <div class="kpi-card__icon"></div>
        <div class="kpi-card__body">
            <span class="kpi-card__label">Total</span>
            <span class="kpi-card__value"><?= (int)$stats['total'] ?></span>
        </div>
    </div>

    <div class="kpi-card kpi-card--warning">
        <div class="kpi-card__icon"></div>
        <div class="kpi-card__body">
            <span class="kpi-card__label">Pending</span>
            <span class="kpi-card__value"><?= (int)$stats['pending'] ?></span>
        </div>
    </div>

    <div class="kpi-card kpi-card--success">
        <div class="kpi-card__icon"></div>
        <div class="kpi-card__body">
            <span class="kpi-card__label">Approved</span>
            <span class="kpi-card__value"><?= (int)$stats['approved'] ?></span>
        </div>
    </div>
</div>

<!-- FILTER -->
<form method="GET" class="search-form">

    <select name="exam_id">
        <option value="0">All Exams</option>
        <?php while ($ex = $exams->fetch_assoc()): ?>
            <option value="<?= $ex['exam_id'] ?>" <?= $exam_id == $ex['exam_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($ex['exam_name']) ?>
            </option>
        <?php endwhile; ?>
    </select>

    <select name="class">
        <option value="">All Classes</option>
        <option value="Form 1" <?= $class_filter == 'Form 1' ? 'selected' : '' ?>>Form 1</option>
        <option value="Form 2" <?= $class_filter == 'Form 2' ? 'selected' : '' ?>>Form 2</option>
    </select>

    <select name="status">
        <option value="">All Status</option>
        <option value="draft">Draft</option>
        <option value="submitted">Submitted</option>
        <option value="eo_approved">EO Approved</option>
        <option value="head_approved">Head Approved</option>
        <option value="rejected">Rejected</option>
    </select>

    <button type="submit">Filter</button>
</form>

<!-- TABLE -->
<div class="table-container">
<table class="table-striped">
<thead>
<tr>
    <th>Student</th>
    <th>Class</th>
    <th>Exam</th>
    <th>Score</th>
    <th>%</th>
    <th>Grade</th>
    <th>Status</th>
    <th>Action</th>
</tr>
</thead>

<tbody>

<?php if ($results && $results->num_rows > 0): ?>
<?php while ($r = $results->fetch_assoc()): ?>
<tr>

<td><?= htmlspecialchars($r['student_name']) ?></td>
<td><?= htmlspecialchars($r['class'] ?? '—') ?></td>
<td><?= htmlspecialchars($r['exam_name']) ?></td>
<td><?= $r['total_score'] ?></td>
<td><?= round($r['percentage'], 1) ?>%</td>
<td><?= htmlspecialchars($r['grade']) ?></td>

<td>
<span class="badge badge-<?= str_replace('_','-',$r['result_status']) ?>">
    <?= $r['result_status'] ?>
</span>
</td>

<td style="display:flex; gap:8px; align-items:center;">
<?php if ($r['result_status'] !== 'eo_approved' && !$r['locked']): ?>
<form method="POST" style="margin:0;">
    <input type="hidden" name="approve_id" value="<?= $r['result_id'] ?>">
    <button type="submit" class="btn btn-success btn-small">Approve</button>
</form>
<?php endif; ?>
<a href="../admin/student_report_card.php?result_id=<?= $r['result_id'] ?>" target="_blank" class="btn btn-secondary btn-small">Report Card</a>
</td>

</tr>
<?php endwhile; ?>
<?php else: ?>
<tr>
    <td colspan="9" class="text-center">No results found</td>
</tr>
<?php endif; ?>

</tbody>
</table>
</div>

<!-- PAGINATION -->
<div style="margin-top:20px;">
    <?php echo render_pagination($pagination, 'results.php'); ?>
</div>

</div>
</div>

<?php include '../common/footer.php'; ?>

</body>
</html>