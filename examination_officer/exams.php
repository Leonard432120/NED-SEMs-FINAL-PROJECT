<?php
/* ════════════════════════════════════════════════════════════════
   examination_officer/exams.php
   Examination Officer: Exam Management & Status Control
   ────────────────────────────────────────────────────────────────
   Allows Examination Officers to inspect, filter, schedule, and review
   examinations by status (under_moderation, draft, active, approved, etc.),
   search by name/code, and navigate directly into control/results.
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../common/report_stats.php';
require_once __DIR__ . '/../common/pagination_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'examination_officer') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

$search       = trim($_GET['search'] ?? '');
$status_filter= trim($_GET['status'] ?? '');
$class_filter = trim($_GET['class'] ?? '');
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page     = 10;

// ── KPIs Query ────────────────────────────────────────────────────
$kpi_query = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='draft' THEN 1 ELSE 0 END) as drafted,
        SUM(CASE WHEN status='under_moderation' THEN 1 ELSE 0 END) as moderating,
        SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed_count,
        SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved_count
    FROM exams
");
$kpi = $kpi_query->fetch_assoc();

// Distinct classes for dropdown
$classes_res = $conn->query("SELECT DISTINCT class FROM exams WHERE class IS NOT NULL AND class != '' ORDER BY class ASC");
$classes_list = [];
if ($classes_res) {
    while ($cl = $classes_res->fetch_assoc()) {
        $classes_list[] = $cl['class'];
    }
}

// ── Build Filtered Query ──────────────────────────────────────────
$where_parts = ["1=1"];
$params      = [];
$types       = "";

if ($search !== '') {
    $where_parts[] = "(e.exam_name LIKE ? OR e.exam_code LIKE ?)";
    $s = "%{$search}%";
    $params[] = $s;
    $params[] = $s;
    $types   .= "ss";
}

if ($status_filter !== '') {
    $where_parts[] = "e.status = ?";
    $params[] = $status_filter;
    $types   .= "s";
}

if ($class_filter !== '') {
    $where_parts[] = "e.class = ?";
    $params[] = $class_filter;
    $types   .= "s";
}

$where_sql = implode(" AND ", $where_parts);

// Count Total for Pagination
$count_sql = "
    SELECT COUNT(*) as cnt
    FROM exams e
    WHERE {$where_sql}
";
$stmt = $conn->prepare($count_sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_records = (int)$stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$pagination = paginate($total_records, $current_page, $per_page);
$offset     = ($pagination['page'] - 1) * $per_page;

// Fetch Paginated List
$data_sql = "
    SELECT e.*, 
           (SELECT COUNT(subject_id) FROM exam_subjects es WHERE es.exam_id = e.exam_id) as subject_count,
           u.name AS creator_name
    FROM exams e
    LEFT JOIN users u ON e.created_by = u.user_id
    WHERE {$where_sql}
    ORDER BY e.start_date DESC, e.exam_id DESC
    LIMIT ? OFFSET ?
";

$all_params = array_merge($params, [$per_page, $offset]);
$all_types  = $types . "ii";

$stmt = $conn->prepare($data_sql);
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$exams_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

function safe($v) {
    return htmlspecialchars($v ?? '');
}

function status_badge_style(string $st): array {
    return match($st) {
        'under_moderation' => ['bg' => '#fefce8', 'color' => '#ca8a04', 'border' => '#fef08a', 'label' => 'Under Moderation'],
        'active'           => ['bg' => '#f0fdf4', 'color' => '#16a34a', 'border' => '#bbf7d0', 'label' => 'Active'],
        'completed'        => ['bg' => '#f3e8ff', 'color' => '#7c3aed', 'border' => '#e9d5ff', 'label' => 'Completed'],
        'approved'         => ['bg' => '#eff6ff', 'color' => '#2563eb', 'border' => '#bfdbfe', 'label' => 'Approved'],
        'submitted'        => ['bg' => '#f0fdf4', 'color' => '#0d9488', 'border' => '#99f6e4', 'label' => 'Submitted'],
        'assigned'         => ['bg' => '#e0f2fe', 'color' => '#0284c7', 'border' => '#bae6fd', 'label' => 'Assigned'],
        'draft'            => ['bg' => '#f8fafc', 'color' => '#64748b', 'border' => '#e2e8f0', 'label' => 'Draft'],
        default            => ['bg' => '#f8fafc', 'color' => '#64748b', 'border' => '#e2e8f0', 'label' => ucfirst(str_replace('_', ' ', $st))]
    };
}

$module_css = 'exam_officer';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Management | NED-SEMS</title>
    <meta name="description" content="Examination Officer exam list, moderation status tracking, scheduling and results management">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
    <style>
      .kpi-container {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
          gap: 16px;
          margin-bottom: 24px;
      }
      .kpi-card {
          background: #ffffff;
          border: 1px solid #e2e8f0;
          border-radius: 12px;
          padding: 18px 20px;
          box-shadow: 0 2px 10px rgba(0,0,0,0.04);
          text-decoration: none;
          display: block;
          transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
      }
      .kpi-card:hover {
          transform: translateY(-3px);
          box-shadow: 0 6px 18px rgba(0,0,0,0.08);
          border-color: #94a3b8;
      }
      .kpi-card.active {
          border-color: #2563eb;
          box-shadow: inset 0 0 0 1px #2563eb, 0 4px 12px rgba(0,0,0,0.06);
      }
      .kpi-value {
          font-size: 2.2rem;
          font-weight: 800;
          color: #0f172a;
          line-height: 1.1;
      }
      .kpi-label {
          font-size: 0.75rem;
          font-weight: 700;
          text-transform: uppercase;
          letter-spacing: 0.5px;
          color: #64748b;
          margin-top: 6px;
      }
      .status-pill {
          display: inline-block;
          padding: 4px 10px;
          border-radius: 6px;
          font-weight: 700;
          font-size: 0.78rem;
      }
    </style>
</head>
<body>

<?php include __DIR__ . '/../common/header.php'; ?>

<div class="dashboard">
    <?php include __DIR__ . '/../common/sidebar.php'; ?>

    <div class="content">

        <!-- ================= PAGE HEADER ================= -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Exam Management &amp; Moderation Control</h1>
                <p class="page-subtitle">Search, filter, and monitor all examinations across moderation, active, draft, and completed states</p>
            </div>
            <div class="header-actions">
                <a href="schedule.php" class="btn btn-dark">+ Schedule New Exam</a>
            </div>
        </div>

        <!-- ================= KPI STAT CARDS / FILTER TABS ================= -->
        <div class="kpi-container">
            <a href="exams.php" class="kpi-card <?= $status_filter === '' ? 'active' : '' ?>">
                <div class="kpi-value"><?= (int)$kpi['total'] ?></div>
                <div class="kpi-label">Total Exams</div>
            </a>
            <a href="exams.php?status=under_moderation" class="kpi-card <?= $status_filter === 'under_moderation' ? 'active' : '' ?>">
                <div class="kpi-value" style="color: #ca8a04;"><?= (int)$kpi['moderating'] ?></div>
                <div class="kpi-label">Under Moderation</div>
            </a>
            <a href="exams.php?status=active" class="kpi-card <?= $status_filter === 'active' ? 'active' : '' ?>">
                <div class="kpi-value" style="color: #16a34a;"><?= (int)$kpi['active_count'] ?></div>
                <div class="kpi-label">Active Exams</div>
            </a>
            <a href="exams.php?status=draft" class="kpi-card <?= $status_filter === 'draft' ? 'active' : '' ?>">
                <div class="kpi-value" style="color: #64748b;"><?= (int)$kpi['drafted'] ?></div>
                <div class="kpi-label">Drafts</div>
            </a>
            <a href="exams.php?status=completed" class="kpi-card <?= $status_filter === 'completed' ? 'active' : '' ?>">
                <div class="kpi-value" style="color: #7c3aed;"><?= (int)$kpi['completed_count'] ?></div>
                <div class="kpi-label">Completed</div>
            </a>
        </div>

        <!-- ================= FILTER PANEL ================= -->
        <div class="rpt-filter-panel no-print">
            <form method="GET" class="rpt-filter-form">
                <!-- Status Filter -->
                <div class="rpt-filter-group">
                    <label class="rpt-filter-label" for="ef-status">Examination Status</label>
                    <select name="status" id="ef-status" class="rpt-filter-select" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <?php foreach (['draft','assigned','submitted','under_moderation','approved','active','completed'] as $st): ?>
                            <option value="<?= $st ?>" <?= $status_filter === $st ? 'selected' : '' ?>>
                                <?= ucwords(str_replace('_',' ',$st)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Class Filter -->
                <div class="rpt-filter-group">
                    <label class="rpt-filter-label" for="ef-class">Target Class</label>
                    <select name="class" id="ef-class" class="rpt-filter-select" onchange="this.form.submit()">
                        <option value="">All Classes</option>
                        <?php foreach ($classes_list as $cl): ?>
                            <option value="<?= htmlspecialchars($cl) ?>" <?= $class_filter === $cl ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cl) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Search Input -->
                <div class="rpt-filter-group">
                    <label class="rpt-filter-label" for="ef-search">Search Exam</label>
                    <input type="text" name="search" id="ef-search" class="rpt-filter-input" placeholder="Exam name or code..." value="<?= safe($search) ?>">
                </div>

                <div class="rpt-filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="exams.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- ================= EXAMS TABLE CARD ================= -->
        <div class="card">
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <div>
                    <h3 style="margin: 0;">Examinations Directory</h3>
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin: 4px 0 0 0;">
                        Displaying <strong><?= count($exams_list) ?></strong> of <strong><?= $total_records ?></strong> registered exams
                        <?= $status_filter ? "filtered by <strong>" . htmlspecialchars(ucwords(str_replace('_', ' ', $status_filter))) . "</strong>" : "" ?>
                    </p>
                </div>
                <?php if ($pagination && $pagination['total_pages'] > 1): ?>
                    <span style="font-size: 0.82rem; color: var(--text-muted);">Page <?= $pagination['page'] ?> of <?= $pagination['total_pages'] ?></span>
                <?php endif; ?>
            </div>

            <div class="rpt-table-wrap">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            <th>Exam Name &amp; Code</th>
                            <th>Target Class</th>
                            <th class="val-col">Subjects</th>
                            <th>Schedule &amp; Duration</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($exams_list)): ?>
                            <tr>
                                <td colspan="6" class="empty-state">No examination papers found matching your filter criteria.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($exams_list as $e): ?>
                                <?php $b = status_badge_style($e['status']); ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: #0f172a; font-size: 0.95rem;"><?= safe($e['exam_name']) ?></div>
                                        <div style="font-size: 0.78rem; color: #64748b; margin-top: 2px;">
                                            ID: #<?= $e['exam_id'] ?> · Code: <?= safe($e['exam_code'] ?: 'N/A') ?> · Academic Year: <?= safe($e['year']) ?>
                                        </div>
                                    </td>

                                    <td style="font-weight: 600; color: #334155;"><?= safe($e['class']) ?></td>

                                    <td class="val-col" style="font-weight: 700; color: var(--info-color);">
                                        <?= (int)$e['subject_count'] ?> Subjects
                                    </td>

                                    <td>
                                        <div style="font-size: 0.84rem; font-weight: 600;">
                                            <?= $e['start_date'] ? date('d M Y', strtotime($e['start_date'])) : 'Unscheduled' ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">
                                            Duration: <?= ($e['duration_minutes'] ?? null) ? $e['duration_minutes'] . ' mins' : 'N/A' ?>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="status-pill" style="background: <?= $b['bg'] ?>; color: <?= $b['color'] ?>; border: 1px solid <?= $b['border'] ?>;">
                                            <?= htmlspecialchars($b['label']) ?>
                                        </span>
                                    </td>

                                    <td style="text-align: right;">
                                        <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                            <?php if ($e['status'] === 'under_moderation'): ?>
                                                <a href="control.php?exam_id=<?= $e['exam_id'] ?>" class="btn btn-primary" style="font-size: 0.78rem; padding: 5px 10px; background: #f59e0b; border: none; font-weight: 700;">
                                                    Review Moderation
                                                </a>
                                            <?php endif; ?>
                                            <a href="schedule.php?exam_id=<?= $e['exam_id'] ?>" class="btn btn-secondary" style="font-size: 0.78rem; padding: 5px 10px;">
                                                Schedule
                                            </a>
                                            <a href="results.php?exam_id=<?= $e['exam_id'] ?>" class="btn btn-dark" style="font-size: 0.78rem; padding: 5px 10px;">
                                                Results
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination controls -->
            <?php if ($pagination && $pagination['total_pages'] > 1): ?>
                <div style="margin-top: 16px;">
                    <?= render_pagination($pagination, 'exams.php') ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../common/footer.php'; ?>

</body>
</html>