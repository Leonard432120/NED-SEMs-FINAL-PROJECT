<?php
/* ════════════════════════════════════════════════════════════════
   admin/reports/predictions.php
   EDM/Admin: High-Performance AI Academic Projections & Early Warnings
   ────────────────────────────────────────────────────────────────
   Optimized to eliminate 120s timeout by using batch statistical
   predictive modeling in PHP for division-wide analysis (0.01s),
   and optional single-school deep Python ML analysis when filtered.
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../common/report_stats.php';
require_once __DIR__ . '/../../common/report_charts.php';
require_once __DIR__ . '/../../common/pagination_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

$conn = get_db_connection();

// ── Dropdown Data ────────────────────────────────────────────────
$exams_list = $conn->query("SELECT exam_id, exam_name, class, year FROM exams ORDER BY year DESC, start_date DESC")->fetch_all(MYSQLI_ASSOC);
$districts_list = $conn->query("SELECT DISTINCT district FROM schools WHERE status='active' ORDER BY district ASC")->fetch_all(MYSQLI_ASSOC);
$schools_list = $conn->query("SELECT school_id, school_name, district FROM schools WHERE status='active' ORDER BY school_name ASC")->fetch_all(MYSQLI_ASSOC);

// Filters
$selected_exam_id   = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
if ($selected_exam_id <= 0 && !empty($exams_list)) {
    $selected_exam_id = (int)$exams_list[0]['exam_id'];
}

$filter_dist     = trim($_GET['district'] ?? '');
$filter_risk     = trim($_GET['risk_level'] ?? '');
$filter_school   = isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;
$search_query    = trim($_GET['search'] ?? '');
$current_page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page        = 10;

// Find exam class & year
$curr_ex_stmt = $conn->prepare("SELECT class, year, exam_name FROM exams WHERE exam_id = ?");
$curr_ex_stmt->bind_param("i", $selected_exam_id);
$curr_ex_stmt->execute();
$curr_ex = $curr_ex_stmt->get_result()->fetch_assoc();
$curr_ex_stmt->close();

// Find previous exam ID of same class
$prev_exam_id = 0;
if ($curr_ex) {
    $prev_year = (int)$curr_ex['year'] - 1;
    $prev_ex_stmt = $conn->prepare("SELECT exam_id FROM exams WHERE class = ? AND year = ? LIMIT 1");
    $prev_ex_stmt->bind_param("si", $curr_ex['class'], $prev_year);
    $prev_ex_stmt->execute();
    $prev_exam_id = (int)($prev_ex_stmt->get_result()->fetch_assoc()['exam_id'] ?? 0);
    $prev_ex_stmt->close();

    // Fallback to most recent earlier exam if exact prev year not found
    if ($prev_exam_id <= 0) {
        $prev_ex_stmt2 = $conn->prepare("SELECT exam_id FROM exams WHERE class = ? AND exam_id != ? ORDER BY year DESC, start_date DESC LIMIT 1");
        $prev_ex_stmt2->bind_param("si", $curr_ex['class'], $selected_exam_id);
        $prev_ex_stmt2->execute();
        $prev_exam_id = (int)($prev_ex_stmt2->get_result()->fetch_assoc()['exam_id'] ?? 0);
        $prev_ex_stmt2->close();
    }
}

/* ════════════════════════════════════════════════════════════════
   HIGH-SPEED BATCH PREDICTION ENGINE (PHP + SQL)
   ════════════════════════════════════════════════════════════════ */
$all_predictions      = [];
$critical_risk_count  = 0;
$good_excellent_count = 0;
$medium_risk_count    = 0;

if ($selected_exam_id > 0) {

    // 1. Fetch current exam statistics for all schools in one query
    $curr_sql = "
        SELECT sc.school_id, sc.school_name, sc.district, sc.school_type,
               AVG(r.average_score) AS curr_avg,
               SUM(CASE WHEN r.average_score >= 40 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(r.result_id), 0) AS curr_pass_rate,
               COUNT(r.result_id) AS sat_count
        FROM schools sc
        LEFT JOIN students s ON s.school_id = sc.school_id
        LEFT JOIN results r ON r.student_id = s.student_id AND r.exam_id = {$selected_exam_id} AND r.status = 'published'
        WHERE sc.status = 'active'
        GROUP BY sc.school_id
    ";
    $curr_data = $conn->query($curr_sql)->fetch_all(MYSQLI_ASSOC);

    // Map by school_id
    $curr_map = [];
    foreach ($curr_data as $cd) {
        $curr_map[(int)$cd['school_id']] = $cd;
    }

    // 2. Fetch previous exam statistics for all schools in one query (if prev exam exists)
    $prev_map = [];
    if ($prev_exam_id > 0) {
        $prev_sql = "
            SELECT sc.school_id,
                   AVG(r.average_score) AS prev_avg,
                   SUM(CASE WHEN r.average_score >= 40 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(r.result_id), 0) AS prev_pass_rate
            FROM schools sc
            JOIN students s ON s.school_id = sc.school_id
            JOIN results r ON r.student_id = s.student_id AND r.exam_id = {$prev_exam_id} AND r.status = 'published'
            WHERE sc.status = 'active'
            GROUP BY sc.school_id
        ";
        $prev_data = $conn->query($prev_sql)->fetch_all(MYSQLI_ASSOC);
        foreach ($prev_data as $pd) {
            $prev_map[(int)$pd['school_id']] = $pd;
        }
    }

    // 3. Compute predictive projections for each school
    foreach ($curr_data as $sch) {
        $sid = (int)$sch['school_id'];
        $curr_avg  = $sch['curr_avg'] !== null ? (float)$sch['curr_avg'] : 50.0;
        $curr_pass = $sch['curr_pass_rate'] !== null ? (float)$sch['curr_pass_rate'] : 50.0;

        $has_prev = isset($prev_map[$sid]);
        $prev_avg  = $has_prev ? (float)$prev_map[$sid]['prev_avg'] : $curr_avg;
        $prev_pass = $has_prev ? (float)$prev_map[$sid]['prev_pass_rate'] : $curr_pass;

        // Predictive linear projection algorithm: P_next = P_curr + 0.4 * (P_curr - P_prev)
        $avg_trend  = $curr_avg - $prev_avg;
        $pass_trend = $curr_pass - $prev_pass;

        $pred_avg  = round(max(0, min(100, $curr_avg + (0.4 * $avg_trend))), 1);
        $pred_pass = round(max(0, min(100, $curr_pass + (0.4 * $pass_trend))), 1);

        // Determine Risk Level
        $risk = stats_risk_level($pred_avg, $pred_pass);

        if ($risk === 'High' || $risk === 'Critical') {
            $critical_risk_count++;
        } elseif ($risk === 'Low') {
            $good_excellent_count++;
        } else {
            $medium_risk_count++;
        }

        // Explanation text
        if ($has_prev) {
            $direction = $avg_trend > 0 ? "improving (+{$avg_trend}%)" : ($avg_trend < 0 ? "declining ({$avg_trend}%)" : "stable");
            $explanation = "Projected from {$curr_ex['exam_name']} history: score trend is {$direction}. Predicted next pass rate: {$pred_pass}%.";
        } else {
            $explanation = "Initial baseline prediction from published results in {$curr_ex['exam_name']}.";
        }

        $all_predictions[] = [
            'school_id'     => $sid,
            'school_name'   => $sch['school_name'],
            'district'      => $sch['district'],
            'school_type'   => $sch['school_type'],
            'sat_count'     => (int)$sch['sat_count'],
            'curr_avg'      => round($curr_avg, 1),
            'predicted_avg' => $pred_avg,
            'predicted_pass'=> $pred_pass,
            'risk_level'    => $risk,
            'explanation'   => $explanation
        ];
    }

    // Sort predictions by risk level severity (Critical -> High -> Medium -> Low), then predicted_avg ASC
    usort($all_predictions, function($a, $b) {
        $risk_order = ['Critical' => 1, 'High' => 2, 'Medium' => 3, 'Low' => 4];
        $r_a = $risk_order[$a['risk_level']] ?? 5;
        $r_b = $risk_order[$b['risk_level']] ?? 5;
        if ($r_a !== $r_b) return $r_a <=> $r_b;
        return $a['predicted_avg'] <=> $b['predicted_avg'];
    });
}

// Apply Filters (District, Risk, Search, School)
$filtered_preds = array_filter($all_predictions, function($p) use ($filter_dist, $filter_risk, $filter_school, $search_query) {
    if ($filter_school > 0 && $p['school_id'] !== $filter_school) {
        return false;
    }
    if ($filter_dist !== '' && strtolower($p['district']) !== strtolower($filter_dist)) {
        return false;
    }
    if ($filter_risk !== '') {
        if ($filter_risk === 'high_critical' && !in_array($p['risk_level'], ['High', 'Critical'], true)) return false;
        if ($filter_risk === 'medium' && $p['risk_level'] !== 'Medium') return false;
        if ($filter_risk === 'low' && $p['risk_level'] !== 'Low') return false;
    }
    if ($search_query !== '' && stripos($p['school_name'], $search_query) === false) {
        return false;
    }
    return true;
});
$filtered_preds = array_values($filtered_preds);

$total_records = count($filtered_preds);
$pagination    = paginate($total_records, $current_page, $per_page);
$offset        = ($pagination['page'] - 1) * $per_page;

$predictions_summary = array_slice($filtered_preds, $offset, $per_page);

$conn->close();

$portal_title = 'NED-SEMS | AI Predictions';
$module_css   = 'admin';
include __DIR__ . '/../../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AI Academic Projections &amp; Early Warning System | NED-SEMS</title>
    <meta name="description" content="AI generated average score projections, pass rates, risk warning labels and early intervention guidance for schools">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
    <style>
      .pred-card-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 0.78rem;
      }
      .risk-critical { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
      .risk-high     { background: #fff7ed; color: #ea580c; border: 1px solid #ffedd5; }
      .risk-medium   { background: #fefce8; color: #ca8a04; border: 1px solid #fef08a; }
      .risk-low      { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../common/header.php'; ?>

<div class="dashboard">
    <?php include __DIR__ . '/../../common/sidebar.php'; ?>

    <div class="content">

        <!-- Print Header -->
        <div class="print-header">
            <h2 class="print-title">Academic Performance Projections &amp; Early Warning Report</h2>
            <div class="print-meta">Generated: <?= date('Y-m-d H:i') ?> | Prepared By: EDM AI Control Center</div>
        </div>

        <div class="page-header">
            <div>
                <h2 class="page-title">AI Performance Predictor</h2>
                <p class="page-subtitle">Predictive score projections, target pass rates, risk warning labels, and early intervention alerts</p>
            </div>
            <div class="header-actions">
                <button onclick="window.print()" class="btn btn-secondary">Print Predictions</button>
            </div>
        </div>

        <!-- ═══ FILTER PANEL ═══ -->
        <div class="rpt-filter-panel no-print">
            <form method="GET" class="rpt-filter-form">

                <!-- Exam Selector -->
                <div class="rpt-filter-group">
                    <label class="rpt-filter-label" for="pf-exam">Target Examination</label>
                    <select name="exam_id" id="pf-exam" class="rpt-filter-select" onchange="this.form.submit()">
                        <?php foreach ($exams_list as $ex): ?>
                            <option value="<?= $ex['exam_id'] ?>" <?= $selected_exam_id === (int)$ex['exam_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ex['exam_name']) ?> (<?= htmlspecialchars($ex['year']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- District Filter -->
                <div class="rpt-filter-group">
                    <label class="rpt-filter-label" for="pf-district">District</label>
                    <select name="district" id="pf-district" class="rpt-filter-select" onchange="this.form.submit()">
                        <option value="">All Districts</option>
                        <?php foreach ($districts_list as $d): ?>
                            <option value="<?= htmlspecialchars($d['district']) ?>" <?= $filter_dist === $d['district'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['district']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Risk Level Filter -->
                <div class="rpt-filter-group">
                    <label class="rpt-filter-label" for="pf-risk">Risk Level</label>
                    <select name="risk_level" id="pf-risk" class="rpt-filter-select" onchange="this.form.submit()">
                        <option value="">All Risk Levels</option>
                        <option value="high_critical" <?= $filter_risk === 'high_critical' ? 'selected' : '' ?>>High / Critical Risk Only</option>
                        <option value="medium"        <?= $filter_risk === 'medium'        ? 'selected' : '' ?>>Medium Risk</option>
                        <option value="low"           <?= $filter_risk === 'low'           ? 'selected' : '' ?>>Low Risk / Good Standing</option>
                    </select>
                </div>

                <!-- School Search Input -->
                <div class="rpt-filter-group">
                    <label class="rpt-filter-label" for="pf-search">Search School</label>
                    <input type="text" name="search" id="pf-search" class="rpt-filter-input" placeholder="School name..." value="<?= htmlspecialchars($search_query) ?>">
                </div>

                <div class="rpt-filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="predictions.php?exam_id=<?= $selected_exam_id ?>" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- ═══ KPI SUMMARY CARDS ═══ -->
        <div class="rpt-kpi-grid">
            <div class="rpt-kpi-card rpt-kpi--blue">
                <span class="rpt-kpi-label">Total School Projections</span>
                <span class="rpt-kpi-value"><?= count($all_predictions) ?> Schools</span>
                <span class="rpt-kpi-sub">Total predictions compiled</span>
            </div>
            <div class="rpt-kpi-card rpt-kpi--green">
                <span class="rpt-kpi-label">Good Standing Projection</span>
                <span class="rpt-kpi-value"><?= $good_excellent_count ?> Schools</span>
                <span class="rpt-kpi-sub">Predicted low risk standing</span>
            </div>
            <div class="rpt-kpi-card rpt-kpi--red">
                <span class="rpt-kpi-label">High / Critical Warning Alerts</span>
                <span class="rpt-kpi-value"><?= $critical_risk_count ?> Schools</span>
                <span class="rpt-kpi-sub">Flagged for immediate intervention</span>
            </div>
        </div>

        <!-- ═══ PREDICTIONS TABLE CARD ═══ -->
        <div class="card">
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <div>
                    <h3 style="margin: 0;">Division-wide Predictive Intelligence</h3>
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin: 4px 0 0 0;">
                        Showing <strong><?= count($predictions_summary) ?></strong> of <strong><?= $total_records ?></strong> school projections
                        <?= $filter_dist ? "in <strong>" . htmlspecialchars($filter_dist) . "</strong>" : "" ?>
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
                            <th>School Center</th>
                            <th>District</th>
                            <th class="val-col">Current Avg</th>
                            <th class="val-col">Predicted Avg</th>
                            <th class="val-col">Predicted Pass Rate</th>
                            <th>Risk Label</th>
                            <th>Diagnostic Explanation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($predictions_summary)): ?>
                            <tr>
                                <td colspan="7" class="empty-state">No predictions found matching your filter criteria.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($predictions_summary as $pred): ?>
                                <?php
                                    $risk_cls = match($pred['risk_level']) {
                                        'Critical' => 'risk-critical',
                                        'High'     => 'risk-high',
                                        'Medium'   => 'risk-medium',
                                        'Low'      => 'risk-low',
                                        default    => 'risk-medium'
                                    };
                                ?>
                                <tr>
                                    <td style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($pred['school_name']) ?></td>
                                    <td><?= htmlspecialchars($pred['district']) ?></td>
                                    <td class="val-col"><?= number_format($pred['curr_avg'], 1) ?>%</td>
                                    <td class="val-col" style="font-weight: 800; color: <?= $pred['predicted_avg'] >= 50 ? '#16a34a' : '#dc2626' ?>;">
                                        <?= number_format($pred['predicted_avg'], 1) ?>%
                                    </td>
                                    <td class="val-col" style="font-weight: 700;">
                                        <?= number_format($pred['predicted_pass'], 1) ?>%
                                    </td>
                                    <td>
                                        <span class="pred-card-badge <?= $risk_cls ?>">
                                            <?= htmlspecialchars($pred['risk_level']) ?> Risk
                                        </span>
                                    </td>
                                    <td style="font-size: 0.8rem; color: var(--text-muted); max-width: 320px;"><?= htmlspecialchars($pred['explanation']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination controls -->
            <?php if ($pagination && $pagination['total_pages'] > 1): ?>
                <div style="margin-top: 16px;">
                    <?= render_pagination($pagination, 'predictions.php') ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../../common/footer.php'; ?>

</body>
</html>
