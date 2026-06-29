<?php
session_start();
require_once '../config/db.php';
require_once '../common/pagination_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

/* ================= FILTERS ================= */
$search = $_GET['search'] ?? '';
$district_filter = $_GET['district'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;   // Increased for better UX

/* ================= COUNT ================= */
$count_sql = "SELECT COUNT(*) as total FROM schools WHERE 1";
$params = [];
$types = '';

if ($search) {
    $count_sql .= " AND school_name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

if ($district_filter) {
    $count_sql .= " AND district = ?";
    $params[] = $district_filter;
    $types .= "s";
}

if ($status_filter) {
    $count_sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$stmt = $conn->prepare($count_sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

/* ================= PAGINATION ================= */
$pagination = paginate($total, $page, $per_page);
$offset = ($page - 1) * $per_page;

/* ================= FETCH SCHOOLS ================= */
$sql = "
SELECT s.*,
       (SELECT name FROM users WHERE role='headteacher' AND school_id = s.school_id LIMIT 1) AS headteacher_name
FROM schools s
WHERE 1
";

$params = [];
$types = '';

if ($search) {
    $sql .= " AND s.school_name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

if ($district_filter) {
    $sql .= " AND s.district = ?";
    $params[] = $district_filter;
    $types .= "s";
}

if ($status_filter) {
    $sql .= " AND s.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql .= " ORDER BY s.school_id DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$schools = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ================= SINGLE ACTION (Activate/Deactivate) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $school_id = (int)$_POST['school_id'];
    $action = $_POST['action'];

    if ($action === 'activate') {
        $stmt = $conn->prepare("UPDATE schools SET status='active' WHERE school_id=?");
    } else {
        $stmt = $conn->prepare("UPDATE schools SET status='inactive' WHERE school_id=?");
    }

    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $stmt->close();

    header("Location: manage_schools.php?updated=1");
    exit();
}

/* ================= BULK ACTION ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulk_action = $_POST['bulk_action'];
    $selected = $_POST['selected_schools'] ?? [];

    if (!empty($selected)) {
        foreach ($selected as $sid) {
            $sid = (int)$sid;

            if ($bulk_action === 'activate') {
                $stmt = $conn->prepare("UPDATE schools SET status='active' WHERE school_id=?");
            } else {
                $stmt = $conn->prepare("UPDATE schools SET status='inactive' WHERE school_id=?");
            }

            $stmt->bind_param("i", $sid);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: manage_schools.php?updated=1");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Schools</title>
    <?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>
</head>
<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">
    <?php include '../common/sidebar.php'; ?>

    <div class="content">

        <!-- HEADER -->
        <div class="page-header">
            <h2 class="page-title">Manage Schools</h2>
            <div class="header-actions">
                <a href="add_school.php" class="btn btn-dark btn-small">+ Add School</a>
            </div>
        </div>

        <!-- FILTER -->
        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="Search school..." 
                   value="<?= htmlspecialchars($search) ?>">

            <select name="district">
                <option value="">All Districts</option>
                <option value="Chitipa" <?= $district_filter=='Chitipa'?'selected':'' ?>>Chitipa</option>
                <option value="Karonga" <?= $district_filter=='Karonga'?'selected':'' ?>>Karonga</option>
                <option value="Rumphi" <?= $district_filter=='Rumphi'?'selected':'' ?>>Rumphi</option>
                <option value="Mzimba" <?= $district_filter=='Mzimba'?'selected':'' ?>>Mzimba</option>
            </select>

            <select name="status">
                <option value="">All Status</option>
                <option value="active" <?= $status_filter=='active'?'selected':'' ?>>Active</option>
                <option value="inactive" <?= $status_filter=='inactive'?'selected':'' ?>>Inactive</option>
            </select>

            <button type="submit">Filter</button>
        </form>

        <!-- STATS -->
        <div class="table-meta">
            Showing <?= (($page - 1) * $per_page) + 1 ?> – 
            <?= min($page * $per_page, $total) ?> of <?= $total ?> schools
        </div>

        <!-- BULK + TABLE -->
        <form method="POST">

            <!-- BULK ACTION BAR -->
            <div class="bulk-action-bar">
                <div class="bulk-left">
                    <span class="bulk-label">Bulk Actions</span>
                </div>
                <div class="bulk-right-actions">
                    <select name="bulk_action" class="bulk-select" required>
                        <option value="">Select action</option>
                        <option value="activate">Activate Selected</option>
                        <option value="deactivate">Deactivate Selected</option>
                    </select>
                    <button type="submit" class="bulk-btn">Apply</button>
                </div>
            </div>

            <!-- TABLE -->
            <div class="card">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" onclick="toggleAll(this)"></th>
                                <th>School Name</th>
                                <th>District</th>
                                <th>School Type</th>
                                <th>Headteacher</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schools as $s): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected_schools[]" value="<?= $s['school_id'] ?>">
                                </td>
                                <td><?= htmlspecialchars($s['school_name']) ?></td>
                                <td><?= htmlspecialchars($s['district']) ?></td>
                                <td><?= htmlspecialchars($s['school_type'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($s['headteacher_name'] ?? 'Not Assigned') ?></td>
                                <td>
                                    <span class="badge badge-<?= $s['status'] ?>">
                                        <?= ucfirst($s['status']) ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a href="view_school.php?id=<?= $s['school_id'] ?>" 
                                       class="btn btn-view btn-small">View</a>
                                    
                                    <a href="edit_school.php?id=<?= $s['school_id'] ?>" 
                                       class="btn btn-edit btn-small">Edit</a>

                                    <?php if ($s['status'] === 'active'): ?>
                                        <button type="button" class="btn btn-deactivate btn-small"
                                            onclick="openModal(<?= $s['school_id'] ?>,'deactivate')">
                                            Deactivate
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-activate btn-small"
                                            onclick="openModal(<?= $s['school_id'] ?>,'activate')">
                                            Activate
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>

        <!-- PAGINATION -->
        <?php echo render_pagination($pagination, 'manage_schools.php'); ?>

    </div>
</div>

<!-- CONFIRM MODAL -->
<div id="confirmModal" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle"></h3>
        <p id="modalText"></p>

        <form method="POST">
            <input type="hidden" name="school_id" id="modalSchoolId">
            <input type="hidden" name="action" id="modalAction">

            <div class="modal-actions">
                <button type="button" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-confirm">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id, action) {
    document.getElementById('confirmModal').classList.add('show');
    document.getElementById('modalSchoolId').value = id;
    document.getElementById('modalAction').value = action;

    document.getElementById('modalTitle').innerText = 
        action === 'activate' ? 'Activate School' : 'Deactivate School';
    
    document.getElementById('modalText').innerText = 
        action === 'activate' 
        ? 'This school and its users will regain access.' 
        : 'This school will be deactivated. Users will lose access.';
}

function closeModal() {
    document.getElementById('confirmModal').classList.remove('show');
}

function toggleAll(source) {
    document.querySelectorAll('input[name="selected_schools[]"]').forEach(cb => {
        cb.checked = source.checked;
    });
}
</script>

<?php include '../common/footer.php'; ?>

</body>
</html>



    <style>
        /* ── Subject card ── */
        .subject-card {
            background: var(--card-color);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }

        .subject-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border-color);
            background: #f8fafc;
            flex-wrap: wrap;
            gap: 10px;
        }

        .subject-card-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .subject-name {
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .subject-cat-pill {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #dbeafe;
            color: #1d4ed8;
        }

        /* ── Role table ── */
        .role-table {
            width: 100%;
            border-collapse: collapse;
        }

        .role-table th {
            background: #f8fafc;
            text-align: left;
            padding: 10px 20px;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .05em;
            border-bottom: 1px solid var(--border-color);
        }

        .role-table td {
            padding: 13px 20px;
            font-size: 0.875rem;
            color: var(--text-color);
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .role-table tr:last-child td {
            border-bottom: none;
        }

        .role-table tr:hover td {
            background: #f8fafc;
        }

        .role-table tr.empty-role td {
            color: var(--text-muted);
            font-style: italic;
        }

        .role-table tr.marker-row td {
            background: #fafafa;
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        /* ── Inline action links ── */
        .link-action {
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            color: var(--primary-color);
            cursor: pointer;
            background: none;
            border: none;
            font-family: inherit;
            padding: 0;
            transition: color .15s;
        }

        .link-action:hover      { color: var(--info-color); }
        .link-action.danger     { color: var(--danger-color); }
        .link-action.danger:hover { color: #dc2626; }
        .link-action.assign     { color: var(--info-color); font-style: normal; }

        .link-sep { color: #cbd5e1; margin: 0 6px; user-select: none; }

        /* ── KPI bar ── */
        .kpi-bar {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }

        .kpi-tile {
            background: var(--card-color);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            padding: 16px 18px;
            box-shadow: var(--box-shadow);
        }

        .kpi-tile .kpi-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .kpi-tile .kpi-value {
            font-size: 1.9rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
        }

        .kpi-tile .kpi-hint {
            font-size: 0.73rem;
            color: var(--text-muted);
            margin-top: 5px;
        }

        .kpi-tile.kpi-blue   { border-left: 4px solid var(--info-color); }
        .kpi-tile.kpi-green  { border-left: 4px solid var(--success-color); }
        .kpi-tile.kpi-yellow { border-left: 4px solid var(--warning-color); }
        .kpi-tile.kpi-red    { border-left: 4px solid var(--danger-color); }

        /* ── Modal extras ── */
        .modal-subject-info {
            background: var(--background-color);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 10px 14px;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 16px;
            line-height: 1.6;
        }

        .modal-subject-info strong { color: #0f172a; }

        /* ── Marker lock note ── */
        .marker-note {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.78rem;
            color: var(--text-muted);
            background: #f1f5f9;
            border-radius: 999px;
            padding: 3px 10px;
        }

        /* ── Empty state ── */
        .empty-subjects {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }

        .empty-subjects h3 {
            font-size: 1rem;
            color: #0f172a;
            margin-bottom: 6px;
        }

        @media (max-width: 768px) {
            .kpi-bar { grid-template-columns: 1fr 1fr; }
            .role-table th:nth-child(3),
            .role-table td:nth-child(3) { display: none; }
        }

        @media (max-width: 480px) {
            .kpi-bar { grid-template-columns: 1fr; }
        }
    </style>