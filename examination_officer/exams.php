<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'examination_officer') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

$school_id = (int)($_SESSION['school_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql = "SELECT e.*, 
               (SELECT COUNT(subject_id) FROM exam_subjects es WHERE es.exam_id = e.exam_id) as subject_count,
               u.name AS creator
        FROM exams e
        LEFT JOIN users u ON e.created_by = u.user_id
        WHERE 1=1";

/* Exams are global, no school filter needed for viewing the list. */

/* ================= SEARCH ================= */
if ($search !== '') {
    $safe = $conn->real_escape_string($search);
    $sql .= " AND e.exam_name LIKE '%$safe%'";
}

/* ================= STATUS FILTER ================= */
if ($status !== '') {
    $safe = $conn->real_escape_string($status);
    $sql .= " AND e.status = '$safe'";
}

/* ================= ORDER ================= */
$sql .= " ORDER BY e.exam_id DESC";
$exams = $conn->query($sql);
if (!$exams) { die("Query Error: " . $conn->error); }

/* ================= KPIs ================= */
$kpi_query = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(e.status='draft') as drafted,
        SUM(e.status='under_moderation') as moderating,
        SUM(e.status='active' OR e.status='completed') as active_completed
    FROM exams e
    LEFT JOIN users u ON e.created_by = u.user_id
    WHERE 1=1
");
$kpi = $kpi_query->fetch_assoc();

$conn->close();

function safe($v) {
    return htmlspecialchars($v ?? '');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Exam Management</title>

<?php
$module_css = 'exam_officer';
include __DIR__ . '/../common/head_assets.php';
?>
<style>
/* Modern Rich Aesthetics for Exams.php */
:root {
    --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
    --hover-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.kpi-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.kpi-card {
    background: linear-gradient(145deg, #ffffff, #f8fafc);
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 16px;
    padding: 24px;
    box-shadow: var(--card-shadow);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    position: relative;
    overflow: hidden;
}
.kpi-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--hover-shadow);
}
.kpi-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
}
.kpi-card.kpi-draft::before { background: linear-gradient(90deg, #94a3b8, #64748b); }
.kpi-card.kpi-mod::before { background: linear-gradient(90deg, #f59e0b, #d97706); }
.kpi-card.kpi-active::before { background: linear-gradient(90deg, #10b981, #059669); }

.kpi-value {
    font-size: 2.5rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
    font-family: 'Outfit', sans-serif;
}
.kpi-label {
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    margin-top: 8px;
}

.search-panel {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 20px;
    box-shadow: var(--card-shadow);
    margin-bottom: 24px;
    border: 1px solid rgba(226, 232, 240, 0.8);
}
.search-form-modern {
    display: flex;
    gap: 16px;
    align-items: center;
    flex-wrap: wrap;
}
.search-form-modern input, .search-form-modern select {
    flex: 1;
    min-width: 200px;
    padding: 12px 16px;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    font-family: 'Inter', sans-serif;
    transition: all 0.2s ease;
    background: #f8fafc;
}
.search-form-modern input:focus, .search-form-modern select:focus {
    background: #ffffff;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    outline: none;
}
.search-form-modern button {
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 600;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: white;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
}
.search-form-modern button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 8px -1px rgba(37, 99, 235, 0.3);
}

.table-modern {
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: var(--card-shadow);
    border: 1px solid rgba(226, 232, 240, 0.8);
}
.table-modern table {
    width: 100%;
    border-collapse: collapse;
}
.table-modern th {
    background: #f8fafc;
    color: #475569;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
    padding: 16px 20px;
    border-bottom: 2px solid #e2e8f0;
}
.table-modern td {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    transition: background 0.2s ease;
}
.table-modern tr:hover td {
    background: #f8fafc;
}
.table-modern tr:last-child td {
    border-bottom: none;
}
</style>
</head>

<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
<?php include '../common/sidebar.php'; ?>

<div class="content">

<!-- ================= HEADER ================= -->
<div class="page-header">
    <div>
        <h1 class="page-title">Exam Management</h1>
        <p class="stats-info">Search, filter, and monitor all examinations.</p>
    </div>
    <a href="schedule.php" class="btn btn-dark">+ Schedule Exam</a>
</div>

<!-- ================= KPIs ================= -->
<div class="kpi-container">
    <div class="kpi-card">
        <div class="kpi-value"><?= (int)$kpi['total'] ?></div>
        <div class="kpi-label">Total Exams</div>
    </div>
    <div class="kpi-card kpi-draft">
        <div class="kpi-value"><?= (int)$kpi['drafted'] ?></div>
        <div class="kpi-label">Drafts</div>
    </div>
    <div class="kpi-card kpi-mod">
        <div class="kpi-value"><?= (int)$kpi['moderating'] ?></div>
        <div class="kpi-label">Under Moderation</div>
    </div>
    <div class="kpi-card kpi-active">
        <div class="kpi-value"><?= (int)$kpi['active_completed'] ?></div>
        <div class="kpi-label">Active / Completed</div>
    </div>
</div>

<!-- ================= FILTER ================= -->
<div class="search-panel">
    <form method="GET" class="search-form-modern">
        <input type="text" name="search" placeholder="Search exam name or code..." value="<?= safe($search) ?>">

        <select name="status">
            <option value="">All Statuses</option>
            <?php foreach (['draft','assigned','submitted','under_moderation','approved','active','completed'] as $st): ?>
                <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>>
                    <?= ucwords(str_replace('_',' ',$st)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">🔍 Filter Results</button>
        <?php if ($search || $status): ?>
            <a href="exams.php" style="color: #64748b; text-decoration: none; font-size: 0.9rem; font-weight: 600; padding: 12px; transition: color 0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#64748b'">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- ================= TABLE ================= -->
<div class="section">
    <div class="table-modern">
        <table>
            <thead>
                <tr>
                    <th>Exam Details</th>
                    <th>Subject</th>
                    <th>Class</th>
                    <th>Schedule</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($exams->num_rows > 0): ?>
                    <?php while ($e = $exams->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong style="color: #0f172a; font-size: 1.05rem;"><?= safe($e['exam_name']) ?></strong>
                                <div style="font-size: 0.8rem; color: #64748b; margin-top: 4px;">ID: #<?= $e['exam_id'] ?> | Code: <?= safe($e['exam_code'] ?? '—') ?></div>
                            </td>

                            <td>
                                <span style="font-weight: 600; color: var(--primary-dark);"><?= (int)$e['subject_count'] ?> Subjects</span>
                            </td>

                            <td><?= safe($e['class']) ?></td>

                            <td>
                                <div><span style="color:#64748b;font-size:0.8rem;">Date:</span> <?= $e['start_date'] ? date('d M Y', strtotime($e['start_date'])) : '—' ?></div>
                                <div style="margin-top:4px;"><span style="color:#64748b;font-size:0.8rem;">Duration:</span> <?= $e['duration_minutes'] ?? '—' ?> min</div>
                            </td>

                            <td>
                                <?php 
                                    $badge_class = str_replace('_','-',$e['status']);
                                    if ($e['status'] === 'under_moderation') $badge_class = 'warning';
                                    if ($e['status'] === 'active') $badge_class = 'success';
                                    if ($e['status'] === 'draft') $badge_class = 'secondary';
                                ?>
                                <span class="badge badge-<?= $badge_class ?>" style="padding: 6px 12px; font-size: 0.8rem;">
                                    <?= ucwords(str_replace('_',' ', $e['status'])) ?>
                                </span>
                            </td>

                            <td class="actions" style="text-align: right;">
                                <?php if ($e['status'] === 'under_moderation'): ?>
                                    <a href="control.php?exam_id=<?= $e['exam_id'] ?>" class="btn btn-small" style="background: #f59e0b; color: white; border: none; font-weight: 600;">
                                        Review Moderation
                                    </a>
                                <?php else: ?>
                                    <a href="schedule.php?exam_id=<?= $e['exam_id'] ?>" class="btn btn-small btn-edit" style="font-weight: 600;">
                                        Schedule
                                    </a>
                                    <a href="results.php?exam_id=<?= $e['exam_id'] ?>" class="btn btn-small btn-dark" style="font-weight: 600;">
                                        Results
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">
                            <div style="font-size: 2rem; margin-bottom: 10px;">📭</div>
                            <div style="font-size: 1.1rem; font-weight: 600; color: #334155;">No exams found</div>
                            <p>Try adjusting your search or filters.</p>
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