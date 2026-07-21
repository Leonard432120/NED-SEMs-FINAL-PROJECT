<?php
/* ════════════════════════════════════════════════════════════════
   admin/reports/performance.php
   EDM/Admin: division-level overall performance reports & statistics
   ════════════════════════════════════════════════════════════════ */
session_start();
header("Location: index.php");
exit();

$conn = get_db_connection();

/* ================= DIVISION-WIDE CORE KPIs ================= */
$total_schools = (int)($conn->query("SELECT COUNT(*) as c FROM schools WHERE status='active'")->fetch_assoc()['c'] ?? 0);
$total_teachers = (int)($conn->query("SELECT COUNT(*) as c FROM users WHERE role='teacher' AND status='active'")->fetch_assoc()['c'] ?? 0);
$total_students = (int)($conn->query("SELECT COUNT(*) as c FROM students WHERE status='active'")->fetch_assoc()['c'] ?? 0);
$total_exams = (int)($conn->query("SELECT COUNT(*) as c FROM exams")->fetch_assoc()['c'] ?? 0);

// Submission status of JCE/MSCE marking assignments
$assignments_total = (int)($conn->query("SELECT COUNT(*) as c FROM marking_assignments")->fetch_assoc()['c'] ?? 0);
$assignments_submitted = (int)($conn->query("SELECT COUNT(*) as c FROM marking_assignments WHERE status IN ('submitted', 'completed')")->fetch_assoc()['c'] ?? 0);
$submission_rate = $assignments_total > 0 ? round(($assignments_submitted / $assignments_total) * 100, 1) : 0;

// Overall division pass rate
$results_total = (int)($conn->query("SELECT COUNT(*) as c FROM results WHERE status='published'")->fetch_assoc()['c'] ?? 0);
$results_passed = (int)($conn->query("SELECT COUNT(*) as c FROM results WHERE status='published' AND average_score >= 40")->fetch_assoc()['c'] ?? 0);
$division_pass_rate = $results_total > 0 ? round(($results_passed / $results_total) * 100, 1) : 0;

// Overall average score
$division_avg_score = round((float)($conn->query("SELECT AVG(average_score) as v FROM results WHERE status='published'")->fetch_assoc()['v'] ?? 0), 1);

/* ================= HISTORICAL EXAM TRENDS (SVG LINE CHART) ================= */
$trend_query = $conn->query("
    SELECT e.exam_id, e.exam_name, e.year, AVG(r.average_score) as avg_score
    FROM results r
    JOIN exams e ON r.exam_id = e.exam_id
    WHERE r.status = 'published'
    GROUP BY e.exam_id
    ORDER BY e.year ASC, e.exam_id ASC
");
$trend_labels = [];
$trend_data = [];
while ($row = $trend_query->fetch_assoc()) {
    $trend_labels[] = $row['exam_name'] . ' (' . $row['year'] . ')';
    $trend_data[] = round((float)$row['avg_score'], 1);
}

/* ================= SUBJECT AVERAGE SCORES (SVG BAR CHART) ================= */
$subject_query = $conn->query("
    SELECT s.subject_name, s.subject_code, ROUND(AVG(m.score), 1) as avg_score
    FROM marks m
    JOIN subjects s ON m.subject_id = s.subject_id
    WHERE m.status = 'approved'
    GROUP BY m.subject_id
    ORDER BY avg_score DESC
");
$subjects_labels = [];
$subjects_data = [];
while ($row = $subject_query->fetch_assoc()) {
    $subjects_labels[] = $row['subject_code'];
    $subjects_data[] = (float)$row['avg_score'];
}

/* ================= COMPARATIVE SCHOOLS SUMMARY (PAGINATED) ================= */
$count_query = $conn->query("SELECT COUNT(*) as cnt FROM schools WHERE status='active'")->fetch_assoc();
$total_schools_count = (int)($count_query['cnt'] ?? 0);

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 4; // Paginated list of schools
$pagination = paginate($total_schools_count, $page, $per_page);
$offset = ($pagination['page'] - 1) * $pagination['per_page'];

$schools_query = $conn->prepare("
    SELECT 
        sc.school_id, sc.school_name, sc.district,
        COUNT(DISTINCT st.student_id) AS student_count,
        AVG(r.average_score) AS avg_score,
        SUM(r.average_score >= 40) AS pass_count,
        COUNT(r.result_id) AS results_count
    FROM schools sc
    LEFT JOIN students st ON sc.school_id = st.school_id AND st.status = 'active'
    LEFT JOIN results r ON st.student_id = r.student_id AND r.status = 'published'
    WHERE sc.status = 'active'
    GROUP BY sc.school_id
    ORDER BY avg_score DESC, student_count DESC
    LIMIT ? OFFSET ?
");
$schools_query->bind_param("ii", $pagination['per_page'], $offset);
$schools_query->execute();
$schools_list = $schools_query->get_result()->fetch_all(MYSQLI_ASSOC);
$schools_query->close();

$conn->close();

$module_css = 'admin';
include __DIR__ . '/../../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Performance Analysis | EDM Control Center</title>
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .card-kpi {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .card-kpi::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--info-color);
        }
        .card-kpi.kpi-success::before { background: var(--success-color); }
        .card-kpi.kpi-warning::before { background: var(--warning-color); }
        .card-kpi.kpi-danger::before { background: var(--danger-color); }

        .card-kpi h3 {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .card-kpi h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0;
            color: #0f172a;
        }

        .card-kpi p {
            margin: 8px 0 0;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        @media (max-width: 992px) {
            .chart-grid {
                grid-template-columns: 1fr;
            }
        }

        .chart-box {
            background: #fff;
            padding: 24px;
            border-radius: 14px;
            box-shadow: var(--box-shadow);
            border: 1px solid var(--border-color);
        }

        .chart-box h3 {
            font-size: 1.05rem;
            color: #0f172a;
            margin-bottom: 6px;
            font-weight: 600;
        }
        .chart-box p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 18px;
        }

        .insight-card {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            color: #1e40af;
        }
        .insight-card h4 {
            color: #1e3a8a;
            margin-bottom: 8px;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .insight-card ul {
            padding-left: 20px;
            font-size: 0.9rem;
            margin: 0;
        }
        .insight-card li {
            margin-bottom: 6px;
        }

        .table-title-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        @media print {
            .header, .sidebar, .btn, .pagination, .insight-card {
                display: none !important;
            }
            .content {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
            .chart-grid {
                grid-template-columns: 1fr 1fr !important;
            }
            .card, .chart-box {
                box-shadow: none !important;
                border: 1px solid #ccc !important;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../common/header.php'; ?>

<div class="dashboard">
    <?php include __DIR__ . '/../../common/sidebar.php'; ?>

    <div class="content">

        <!-- HEADER -->
        <div class="page-header">
            <div>
                <h2 class="page-title">Performance Analytics Dashboard</h2>
                <p class="page-subtitle">Highest-level educational division summary and school comparative analytics</p>
            </div>
            <a href="../dashboard.php" class="btn btn-secondary">Dashboard</a>
        </div>

        <!-- KPI CARDS -->
        <div class="dashboard-grid">
            <div class="card-kpi">
                <h3>Total Institutions</h3>
                <h1><?= $total_schools ?></h1>
                <p>Active schools in division</p>
            </div>
            <div class="card-kpi">
                <h3>Student Enrollment</h3>
                <h1><?= number_format($total_students) ?></h1>
                <p>Total active candidates JCE/MSCE</p>
            </div>
            <div class="card-kpi kpi-success">
                <h3>Overall Pass Rate</h3>
                <h1><?= $division_pass_rate ?>%</h1>
                <p>Score threshold of &ge; 40%</p>
            </div>
            <div class="card-kpi kpi-success">
                <h3>Average Score</h3>
                <h1><?= $division_avg_score ?>%</h1>
                <p>Across all published grades</p>
            </div>
            <div class="card-kpi kpi-warning">
                <h3>Mark Submission</h3>
                <h1><?= $submission_rate ?>%</h1>
                <p><?= $assignments_submitted ?> / <?= $assignments_total ?> marker tasks finalized</p>
            </div>
        </div>

        <!-- INSIGHTS BOX -->
        <div class="insight-card">
            <h4>💡 Education Division Manager Insights</h4>
            <ul>
                <li><strong>Division Benchmarks:</strong> The overall average score stands at <?= $division_avg_score ?>% with a passing rate of <?= $division_pass_rate ?>%. Schools displaying performance indicators below 40% will be highlighted in the standings for targeted intervention.</li>
                <li><strong>Moderation Status:</strong> Double check that JCE JCE2026/MSCE2026 marks submission processes are fully completed; currently <?= 100 - $submission_rate ?>% of teacher marking tasks remain unfinalized.</li>
                <li><strong>Strategic Direction:</strong> Allocate additional science kits and language labs to schools in the lower-quartile average score band.</li>
            </ul>
        </div>

        <!-- CHARTS -->
        <div class="chart-grid">

            <!-- LINE CHART: TREND OVER TIME -->
            <div class="chart-box">
                <h3>Academic Performance Trends</h3>
                <p>Average percentage score trends across division exam history</p>

                <div style="padding-top: 10px;">
                <?php if (!empty($trend_data)): ?>
                    <?php
                        $svgW = 500; $svgH = 220;
                        $padL = 40; $padR = 20; $padT = 20; $padB = 40;
                        $w = $svgW - $padL - $padR;
                        $h = $svgH - $padT - $padB;
                        $n = count($trend_data);
                        $maxVal = max(array_merge([100], $trend_data));
                        $minVal = min(array_merge([0], $trend_data));
                        $range = $maxVal - $minVal > 0 ? $maxVal - $minVal : 1;

                        $pts = [];
                        for ($i = 0; $i < $n; $i++) {
                            $x = $padL + ($n === 1 ? $w/2 : ($i / ($n - 1)) * $w);
                            $y = $padT + $h - (($trend_data[$i] - $minVal) / $range) * $h;
                            $pts[] = "$x,$y";
                        }
                        $points_str = implode(' ', $pts);
                        $fill_points = $points_str . " " . ($padL + $w) . "," . ($padT + $h) . " $padL," . ($padT + $h);
                    ?>
                    <svg viewBox="0 0 <?= $svgW ?> <?= $svgH ?>" width="100%">
                        <!-- Y-Grid and labels -->
                        <?php for ($k = 0; $k <= 4; $k++): ?>
                            <?php 
                            $val = $minVal + ($range / 4) * $k;
                            $y = $padT + $h - ($k / 4) * $h;
                            ?>
                            <line x1="<?= $padL ?>" y1="<?= $y ?>" x2="<?= $svgW - $padR ?>" y2="<?= $y ?>" stroke="#f1f5f9" stroke-width="1.5" />
                            <text x="<?= $padL - 10 ?>" y="<?= $y + 4 ?>" text-anchor="end" font-size="10" fill="#64748b"><?= round($val) ?>%</text>
                        <?php endfor; ?>

                        <!-- Gradient Fill -->
                        <polygon points="<?= htmlspecialchars($fill_points) ?>" fill="rgba(59,130,246,0.08)" />
                        <!-- Line -->
                        <polyline points="<?= htmlspecialchars($points_str) ?>" fill="none" stroke="var(--info-color)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />

                        <!-- Data points -->
                        <?php for ($i = 0; $i < $n; $i++): 
                            $coords = explode(',', $pts[$i]);
                        ?>
                            <circle cx="<?= $coords[0] ?>" cy="<?= $coords[1] ?>" r="5" fill="var(--info-color)" stroke="#fff" stroke-width="2" />
                            <text x="<?= $coords[0] ?>" y="<?= $coords[1] - 8 ?>" text-anchor="middle" font-size="9" font-weight="bold" fill="#0f172a"><?= $trend_data[$i] ?>%</text>
                            <text x="<?= $coords[0] ?>" y="<?= $padT + $h + 20 ?>" text-anchor="middle" font-size="9" fill="#64748b"><?= htmlspecialchars($trend_labels[$i]) ?></text>
                        <?php endfor; ?>
                    </svg>
                <?php else: ?>
                    <div style="text-align:center; padding: 40px; color: var(--text-muted);">No published exam historical data found.</div>
                <?php endif; ?>
                </div>
            </div>

            <!-- BAR CHART: SUBJECT PERFORMANCE -->
            <div class="chart-box">
                <h3>Subject Area Performance</h3>
                <p>Comparison of average score percentage by subject across all centers</p>

                <div style="padding-top: 10px;">
                <?php if (!empty($subjects_data)): ?>
                    <?php
                        $svgW = 500; $svgH = 220;
                        $padL = 35; $padR = 15; $padT = 15; $padB = 30;
                        $w = $svgW - $padL - $padR;
                        $h = $svgH - $padT - $padB;
                        $count = count($subjects_data);
                        $bar_width = ($w / $count) * 0.65;
                        $bar_gap = ($w / $count) * 0.35;
                        $max_score = 100;
                    ?>
                    <svg viewBox="0 0 <?= $svgW ?> <?= $svgH ?>" width="100%">
                        <!-- Y-Grid and labels -->
                        <?php for ($k = 0; $k <= 4; $k++): ?>
                            <?php 
                            $val = ($max_score / 4) * $k;
                            $y = $padT + $h - ($k / 4) * $h;
                            ?>
                            <line x1="<?= $padL ?>" y1="<?= $y ?>" x2="<?= $svgW - $padR ?>" y2="<?= $y ?>" stroke="#f1f5f9" stroke-width="1.5" />
                            <text x="<?= $padL - 8 ?>" y="<?= $y + 4 ?>" text-anchor="end" font-size="9" fill="#64748b"><?= $val ?>%</text>
                        <?php endfor; ?>

                        <!-- Bars -->
                        <?php for ($i = 0; $i < $count; $i++): 
                            $val = $subjects_data[$i];
                            $bar_height = ($val / $max_score) * $h;
                            $x = $padL + ($i * ($bar_width + $bar_gap)) + ($bar_gap / 2);
                            $y = $padT + $h - $bar_height;
                            $color = $val >= 60 ? '#10b981' : ($val >= 40 ? 'var(--info-color)' : '#ef4444');
                        ?>
                            <rect x="<?= $x ?>" y="<?= $y ?>" width="<?= $bar_width ?>" height="<?= $bar_height ?>" rx="3" ry="3" fill="<?= $color ?>" />
                            <text x="<?= $x + ($bar_width / 2) ?>" y="<?= $y - 4 ?>" text-anchor="middle" font-size="9" font-weight="bold" fill="#0f172a"><?= $val ?>%</text>
                            <text x="<?= $x + ($bar_width / 2) ?>" y="<?= $padT + $h + 16 ?>" text-anchor="middle" font-size="9" font-weight="600" fill="#64748b"><?= htmlspecialchars($subjects_labels[$i]) ?></text>
                        <?php endfor; ?>
                    </svg>
                <?php else: ?>
                    <div style="text-align:center; padding: 40px; color: var(--text-muted);">No subject marks data approved.</div>
                <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- TABLE: COMPARATIVE STANDINGS -->
        <div class="card">
            <div class="table-title-bar">
                <h3>Comparative School Standings</h3>
                <span style="font-size: 0.85rem; color: var(--text-muted);">Showing <?= count($schools_list) ?> of <?= $total_schools_count ?> schools</span>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>School Name</th>
                            <th>District</th>
                            <th style="text-align: center;">Active Candidates</th>
                            <th style="text-align: center;">Avg. Percentage</th>
                            <th style="text-align: center;">Pass Rate (&ge;40%)</th>
                            <th>Status / Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($schools_list)): ?>
                            <tr>
                                <td colspan="6" class="empty-state">No registered schools or compiled results found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($schools_list as $sch): ?>
                            <?php 
                            $avg_pct = $sch['avg_score'] ? (float)$sch['avg_score'] : 0;
                            $res_count = (int)$sch['results_count'];
                            $pass_count = (int)$sch['pass_count'];
                            $pass_rate = $res_count > 0 ? ($pass_count / $res_count) * 100 : 0;
                            
                            if ($res_count === 0) {
                                $standing = '<span class="badge badge-secondary" style="background:#cbd5e1;color:#475569;">No Data</span>';
                            } elseif ($avg_pct >= 65) {
                                $standing = '<span class="badge badge-success" style="background:#dcfce7;color:#15803d;">Excellent</span>';
                            } elseif ($avg_pct >= 40) {
                                $standing = '<span class="badge badge-primary" style="background:#dbeafe;color:#1d4ed8;">Good Standings</span>';
                            } else {
                                $standing = '<span class="badge badge-warning" style="background:#fef9c3;color:#a16207;">Needs Intervention</span>';
                            }
                            ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($sch['school_name']) ?></td>
                                <td><?= htmlspecialchars($sch['district']) ?></td>
                                <td style="text-align: center; font-weight: bold;"><?= $sch['student_count'] ?></td>
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

            <!-- PAGINATION -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <div style="margin-top: 15px; display: flex; justify-content: center;">
                    <?= render_pagination($pagination, 'performance.php') ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../../common/footer.php'; ?>

</body>
</html>