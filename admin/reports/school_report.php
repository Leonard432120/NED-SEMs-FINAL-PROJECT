<?php
/* ════════════════════════════════════════════════════════════════
   admin/reports/school_report.php
   EDM/Admin: school performance comparison and metrics
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php"); exit();
}

$conn = get_db_connection();

/* ── FETCH SCHOOL COMPARATIVE PERFORMANCE ── */
$school_stats = $conn->query("
    SELECT 
        sc.school_id, sc.school_name, sc.district, sc.school_type,
        COUNT(DISTINCT st.student_id) AS student_count,
        AVG(r.average_score) AS avg_score,
        SUM(r.average_score >= 40) AS pass_count,
        COUNT(r.result_id) AS results_count
    FROM schools sc
    LEFT JOIN students st ON sc.school_id = st.school_id AND st.status = 'active'
    LEFT JOIN results r ON st.student_id = r.student_id AND r.status = 'published'
    WHERE sc.status = 'active'
    GROUP BY sc.school_id, sc.school_name, sc.district, sc.school_type
    ORDER BY avg_score DESC, student_count DESC
")->fetch_all(MYSQLI_ASSOC);

$conn->close();
$module_css = 'admin';
include __DIR__ . '/../../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>School Performance Report | NED-SEMS</title>
</head>
<body>
<?php include '../../common/header.php'; ?>
<div class="dashboard">
    <?php include '../../common/sidebar.php'; ?>
    <div class="content">

        <!-- Header -->
        <div class="page-header">
            <div>
                <h2 class="page-title">School Performance Report</h2>
                <p class="page-subtitle">Comparative analysis of academic achievement, pass rates, and student populations across schools</p>
            </div>
            <a href="../dashboard.php" class="btn btn-secondary">Back</a>
        </div>

        <!-- Comparative Table -->
        <div class="card">
            <div class="section-header">
                <h3>School Standings & Metrics</h3>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>School Name</th>
                            <th>District</th>
                            <th>School Type</th>
                            <th style="text-align: center;">Active Students</th>
                            <th style="text-align: center;">Avg. Percentage</th>
                            <th style="text-align: center;">Pass Rate</th>
                            <th>Standing</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($school_stats)): ?>
                            <tr>
                                <td colspan="7" class="empty-state">No schools or performance records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($school_stats as $sch): ?>
                            <?php 
                            $avg_pct = $sch['avg_score'] ? (float)$sch['avg_score'] : 0;
                            $res_count = (int)$sch['results_count'];
                            $pass_count = (int)$sch['pass_count'];
                            $pass_rate = $res_count > 0 ? ($pass_count / $res_count) * 100 : 0;
                            
                            // Determine standing label
                            if ($res_count === 0) {
                                $standing = '<span class="badge badge-secondary">No Data</span>';
                            } elseif ($avg_pct >= 65) {
                                $standing = '<span class="badge badge-success">Excellent</span>';
                            } elseif ($avg_pct >= 50) {
                                $standing = '<span class="badge badge-primary">Good</span>';
                            } else {
                                $standing = '<span class="badge badge-warning">Needs Focus</span>';
                            }
                            ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($sch['school_name']) ?></td>
                                <td><?= htmlspecialchars($sch['district']) ?></td>
                                <td><small style="font-weight: bold; color: var(--text-muted);"><?= htmlspecialchars($sch['school_type'] ?? 'CDSS') ?></small></td>
                                <td style="text-align: center; font-weight: 600;"><?= $sch['student_count'] ?></td>
                                <td style="text-align: center; font-weight: bold; color: var(--primary-dark);">
                                    <?= $res_count > 0 ? number_format($avg_pct, 1) . '%' : '—' ?>
                                </td>
                                <td style="text-align: center; font-weight: bold; color: <?= $pass_rate >= 50 ? '#16a34a' : '#dc2626' ?>;">
                                    <?= $res_count > 0 ? number_format($pass_rate, 1) . '%' : '—' ?>
                                </td>
                                <td><?= $standing ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
<?php include '../../common/footer.php'; ?>
</body>
</html>
