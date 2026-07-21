<?php
session_start();
include '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
$conn = get_db_connection();

/* ================= CORE STATS ================= */
$total_schools = $conn->query("SELECT COUNT(*) AS total FROM schools")->fetch_assoc()['total'];
$active_schools = $conn->query("SELECT COUNT(*) AS total FROM schools WHERE status = 'active'")->fetch_assoc()['total'] ?? 0;

$total_users = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
$male_users = $conn->query("SELECT COUNT(*) AS t FROM users WHERE gender='Male' OR gender='male'")->fetch_assoc()['t'] ?? 0;
$female_users = $conn->query("SELECT COUNT(*) AS t FROM users WHERE gender='Female' OR gender='female'")->fetch_assoc()['t'] ?? 0;

$teachers = $conn->query("SELECT COUNT(*) AS t FROM users WHERE role='teacher'")->fetch_assoc()['t'] ?? 0;
$students = $conn->query("SELECT COUNT(*) AS t FROM users WHERE role='student'")->fetch_assoc()['t'] ?? 0;

$total_exams = $conn->query("SELECT COUNT(*) AS total FROM exams")->fetch_assoc()['total'];
$draft_exams = $conn->query("SELECT COUNT(*) AS t FROM exams WHERE status='draft'")->fetch_assoc()['t'] ?? 0;
$published_exams = $total_exams - $draft_exams;

$pending_moderation = $conn->query("SELECT COUNT(*) AS t FROM exams WHERE status='under_moderation'")->fetch_assoc()['t'] ?? 0;

$recent_users = $conn->query("SELECT name, role FROM users ORDER BY user_id DESC LIMIT 5");
$logs = $conn->query("SELECT a.*, u.name FROM audit_logs a LEFT JOIN users u ON a.user_id = u.user_id ORDER BY a.created_at DESC LIMIT 5");
$announcements = $conn->query("
    SELECT a.*, u.name AS author_name, u.role AS author_role
    FROM announcements a
    LEFT JOIN users u ON a.published_by = u.user_id
    ORDER BY a.created_at DESC LIMIT 3
");
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>
    <style>
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 16px;
            margin: 25px 0;
        }

        .stat-box {
            background: white;
            border-radius: 12px;
            padding: 20px 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.07);
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            min-height: 172px;
        }

        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.1);
        }

        .stat-badge img {
            width: 48px;
            height: 48px;
            object-fit: contain;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 6px 0 4px;
            color: #1e2937;
        }

        .stat-box h4 {
            margin: 0 0 6px 0;
            font-size: 0.93rem;
            color: #334155;
            font-weight: 600;
        }

        .stat-box small {
            color: #64748b;
            font-size: 0.82rem;
            line-height: 1.4;
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
                <h1 class="page-title">Admin Dashboard</h1>
                <p class="page-subtitle">Real-time overview & system insights</p>
            </div>
        </div>

        <!-- ================= WELL ALIGNED STAT CARDS ================= -->
        <div class="stats">
            <div class="stat-box">
                
                <h4>Schools</h4>
                <p class="stat-number"><?= number_format($total_schools) ?></p>
                <small><?= number_format($active_schools) ?> Active • <?= number_format($total_schools - $active_schools) ?> Inactive</small>
                <a href="manage_schools.php" class="btn btn-small btn-view">View All</a>
            </div>

            <div class="stat-box">
                
                <h4>Total Users</h4>
                <p class="stat-number"><?= number_format($total_users) ?></p>
                <small><?= $male_users ?> Male • <?= $female_users ?> Female</small>
                <a href="manage_users.php" class="btn btn-small btn-view">Manage</a>
            </div>

            <div class="stat-box">
                
                <h4>Teachers</h4>
                <p class="stat-number"><?= number_format($teachers) ?></p>
                <small>Active Teaching Staff</small>
                <a href="manage_users.php" class="btn btn-small btn-view">View</a>
            </div>

            <div class="stat-box">
                
                <h4>Students</h4>
                <p class="stat-number"><?= number_format($students) ?></p>
                <small>Enrolled Learners</small>
                <a href="manage_users.php" class="btn btn-small btn-view">View</a>
            </div>            
        </div>

        <!-- Quick Actions -->
        <div class="section">
            <h3>Quick Actions</h3>
            <div class="quick-links">
                <a href="add_user.php">+ Add User</a>
                <a href="add_school.php">+ Add School</a>
                <a href="add_exam.php">+ Create Exam</a>
                <a href="assign.php">+ Assign Teachers</a>
            </div>
        </div>

        <!-- Announcements & Recent Users -->
        <div class="panel-grid">
            <div class="section">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <h3>Announcements</h3>
                    <a href="../common/announcements.php" class="btn btn-primary">+ New Announcement</a>
                </div>
                <div class="announcement-section">
                    <?php if ($announcements && $announcements->num_rows > 0): ?>
                        <?php while ($ann = $announcements->fetch_assoc()): ?>
                            <div class="announcement-item">
                                <div class="announcement-header">
                                    <strong><?= htmlspecialchars($ann['title']) ?></strong>
                                    <span class="announcement-date" style="font-size: 0.8rem; color:#64748b;">
                                        <?= date('M j, Y', strtotime($ann['created_at'])) ?>
                                    </span>
                                </div>
                                <p style="margin-top: 5px; color:#334155;"><?= htmlspecialchars(mb_strimwidth($ann['content'], 0, 100, "...")) ?></p>
                                <small style="color:#64748b; font-size:0.75rem;">By <?= htmlspecialchars($ann['author_name'] ?? 'System') ?> (<?= ucfirst(htmlspecialchars($ann['author_role'] ?? '')) ?>)</small>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="empty-state">No announcements published yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section">
                <h3>Recent Users</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($u = $recent_users->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['name']) ?></td>
                                <td><?= ucwords(str_replace('_', ' ', $u['role'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../common/footer.php'; ?>
</body>
</html>