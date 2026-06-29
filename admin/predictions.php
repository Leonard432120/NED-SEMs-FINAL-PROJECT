<?php
/* ════════════════════════════════════════════════════════════════
   admin/predictions.php
   EDM/Admin: AI performance predictions at division/district and school level
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once '../config/db.php';
require_once '../common/ai_service.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php"); exit();
}

$conn = get_db_connection();

// Fetch available exams
$exams_query = "SELECT exam_id, exam_name, class, year FROM exams ORDER BY year DESC, start_date DESC";
$exams = $conn->query($exams_query)->fetch_all(MYSQLI_ASSOC);

$selected_exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : ($exams[0]['exam_id'] ?? null);
$selected_school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : null;

$python = ai_resolve_python() ?: 'python';
$script_path = dirname(__DIR__) . '/ai/predict.py';

$prediction = null;
$division_data = [];
$error_msg = null;

if ($selected_exam_id) {
    if ($selected_school_id) {
        // School-level prediction detailed view
        $cmd = $python . ' ' . escapeshellarg($script_path) 
             . ' --school_id=' . $selected_school_id 
             . ' --exam_id=' . $selected_exam_id . ' 2>&1';
             
        $output = shell_exec($cmd);
        if ($output) {
            $prediction = json_decode($output, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($prediction['status']) || $prediction['status'] !== 'success') {
                $error_msg = "Failed to compile detailed AI prediction for this school.";
            }
        }
    } else {
        // Division-level comparative view: run predictions for all active schools
        $schools = $conn->query("SELECT school_id, school_name, district, school_type FROM schools WHERE status='active' ORDER BY school_name ASC")->fetch_all(MYSQLI_ASSOC);
        
        foreach ($schools as $sch) {
            // Check if school has students in this class/exam
            $sch_id = (int)$sch['school_id'];
            
            $cmd = $python . ' ' . escapeshellarg($script_path) 
                 . ' --school_id=' . $sch_id 
                 . ' --exam_id=' . $selected_exam_id . ' 2>&1';
                 
            $output = shell_exec($cmd);
            if ($output) {
                $res = json_decode($output, true);
                if ($res && isset($res['status']) && $res['status'] === 'success') {
                    $division_data[] = $res;
                }
            }
        }
        
        if (empty($division_data)) {
            $error_msg = "Could not load division predictions. Ensure Python predictor has execute permissions.";
        }
    }
}

// Fetch schools list for the dropdown filter
$all_schools = $conn->query("SELECT school_id, school_name FROM schools WHERE status='active' ORDER BY school_name ASC")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Academic Predictor | EDM Control Room</title>
    <?php $module_css = 'admin'; include __DIR__ . '/../common/head_assets.php'; ?>
    <style>
        .predict-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .predict-header h2 {
            margin: 0 0 8px 0;
            font-size: 28px;
            font-weight: 700;
        }
        .predict-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 15px;
        }
        .filter-section {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 15px 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .filter-section label {
            color: white;
            font-weight: 600;
            font-size: 13px;
        }
        .filter-section select {
            background: #1e293b;
            color: white;
            border: 1px solid #475569;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 600;
            outline: none;
            cursor: pointer;
        }
        .metric-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .metric-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }
        .metric-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }
        .metric-card.blue::after { background: #3b82f6; }
        .metric-card.green::after { background: #10b981; }
        .metric-card.purple::after { background: #8b5cf6; }
        .metric-card.orange::after { background: #f59e0b; }
        
        .metric-label {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .metric-val {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.1;
        }
        .metric-sub {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 6px;
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
        }
        
        .risk-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .risk-badge.low { background: #dcfce7; color: #15803d; }
        .risk-badge.medium { background: #fef3c7; color: #b45309; }
        .risk-badge.high { background: #fee2e2; color: #b91c1c; }

        .predict-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        @media (max-width: 1024px) {
            .predict-grid { grid-template-columns: 1fr; }
        }
        
        .panel-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            margin-bottom: 30px;
        }
        .panel-title {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 12px;
        }
        
        .grade-bars {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .grade-bar-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .grade-name {
            width: 30px;
            font-weight: 700;
            color: #475569;
            font-size: 13px;
        }
        .grade-track {
            flex-grow: 1;
            background: #e2e8f0;
            height: 12px;
            border-radius: 6px;
            overflow: hidden;
        }
        .grade-fill {
            background: linear-gradient(90deg, #6366f1, #818cf8);
            height: 100%;
            border-radius: 6px;
            transition: width 0.6s ease-in-out;
        }
        .grade-count {
            width: 40px;
            text-align: right;
            font-weight: 600;
            color: #475569;
            font-size: 13px;
        }

        .influence-factors {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .factor-item {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            border-left: 4px solid;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }
        .factor-item.positive {
            background: #f0fdf4;
            border-left-color: #22c55e;
            color: #166534;
        }
        .factor-item.negative {
            background: #fef2f2;
            border-left-color: #ef4444;
            color: #991b1b;
        }
        .factor-item.neutral {
            background: #f8fafc;
            border-left-color: #64748b;
            color: #334155;
        }

        .subject-table-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            overflow: hidden;
        }
        .subject-table-card table {
            width: 100%;
            border-collapse: collapse;
        }
        .subject-table-card th {
            background: #f8fafc;
            padding: 14px 20px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            font-size: 13px;
            border-bottom: 1px solid #e2e8f0;
        }
        .subject-table-card td {
            padding: 14px 20px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 14px;
        }
        .subject-badge {
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            text-transform: uppercase;
        }
        .subject-badge.science { background: #fee2e2; color: #991b1b; }
        .subject-badge.language { background: #e0f2fe; color: #0369a1; }
        .subject-badge.humanities { background: #fef3c7; color: #92400e; }

        .progress-circle-wrap {
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            width: 140px;
            height: 140px;
            margin: 0 auto 15px auto;
        }
        .progress-circle-svg {
            transform: rotate(-90deg);
            width: 100%;
            height: 100%;
        }
        .progress-circle-bg {
            fill: none;
            stroke: #e2e8f0;
            stroke-width: 10;
        }
        .progress-circle-val {
            fill: none;
            stroke: #6366f1;
            stroke-width: 10;
            stroke-dasharray: 440;
            stroke-dashoffset: 440;
            stroke-linecap: round;
            transition: stroke-dashoffset 0.8s ease;
        }
        .progress-circle-text {
            position: absolute;
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
        }
        .metrics-badge {
            display: inline-block;
            background: #f1f5f9;
            color: #475569;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 4px;
            margin-right: 5px;
        }
    </style>
</head>
<body>
<?php include '../common/header.php'; ?>
<div class="dashboard">
    <?php include '../common/sidebar.php'; ?>
    <div class="content">
        
        <!-- Header banner -->
        <div class="predict-header">
            <div>
                <h2>AI Performance Predictor</h2>
                <p>EDM Control Center — Division Analysis and School Forecasting</p>
            </div>
            
            <div class="filter-section">
                <form method="GET" action="" id="filter-form" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <div>
                        <label for="exam-select">Exam:</label>
                        <select name="exam_id" id="exam-select" onchange="document.getElementById('filter-form').submit()">
                            <?php foreach ($exams as $ex): ?>
                                <option value="<?= $ex['exam_id'] ?>" <?= $selected_exam_id == $ex['exam_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ex['exam_name']) ?> (<?= htmlspecialchars($ex['class']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="school-select">School View:</label>
                        <select name="school_id" id="school-select" onchange="document.getElementById('filter-form').submit()">
                            <option value="">-- View All Schools (Division Level) --</option>
                            <?php foreach ($all_schools as $asc): ?>
                                <option value="<?= $asc['school_id'] ?>" <?= $selected_school_id == $asc['school_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($asc['school_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($selected_school_id): ?>
                        <a href="?exam_id=<?= $selected_exam_id ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 13px;">Clear School</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger" style="margin-bottom: 30px;">
                <strong>System Notice:</strong> <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <!-- ================= SCHOOL LEVEL predictions VIEW ================= -->
        <?php if ($selected_school_id && $prediction): ?>
            <!-- Back to Division button -->
            <div style="margin-bottom: 20px;">
                <a href="?exam_id=<?= $selected_exam_id ?>" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                    ← Back to Division Performance
                </a>
            </div>

            <!-- KPI Cards -->
            <div class="metric-cards">
                <div class="metric-card blue">
                    <span class="metric-label">Predicted Avg. Score</span>
                    <span class="metric-val"><?= number_format($prediction['predicted_avg_score'] ?? 0, 1) ?>%</span>
                    <span class="metric-sub"><?= htmlspecialchars($prediction['school_name'] ?? '') ?></span>
                </div>
                <div class="metric-card green">
                    <span class="metric-label">Predicted Pass Rate</span>
                    <span class="metric-val"><?= number_format($prediction['predicted_pass_rate'] ?? 0, 1) ?>%</span>
                    <span class="metric-sub">Based on current student data</span>
                </div>
                <div class="metric-card purple">
                    <span class="metric-label">Grade Band Estimate</span>
                    <span class="metric-val"><?= htmlspecialchars($prediction['grade_band'] ?? '') ?></span>
                    <span class="metric-sub">Expected performance tier</span>
                </div>
                <div class="metric-card orange">
                    <span class="metric-label">Academic Risk Level</span>
                    <div style="margin-top: 5px;">
                        <span class="risk-badge <?= strtolower($prediction['risk_level'] ?? '') ?>">
                            <?= htmlspecialchars($prediction['risk_level'] ?? '') ?> Risk
                        </span>
                    </div>
                    <span class="metric-sub">Based on scores and pass rate</span>
                </div>
            </div>

            <!-- Two Column Prediction Insights -->
            <div class="predict-grid">
                
                <!-- Left Column: Key Factors & Subject Breakdown -->
                <div>
                    <!-- Key Influencing Factors -->
                    <div class="panel-card">
                        <div class="panel-title">
                            Key Influencing Factors
                            <span style="font-size: 13px; color: #64748b; font-weight: normal;">Model explanation metrics</span>
                        </div>
                        <div class="influence-factors">
                            <?php foreach ($prediction['key_factors'] as $factor): ?>
                                <?php 
                                $impact = strtolower($factor['impact'] ?? 'neutral');
                                $icon = $impact === 'positive' ? '✓' : ($impact === 'negative' ? '⚠' : 'ℹ');
                                ?>
                                <div class="factor-item <?= $impact ?>">
                                    <span class="factor-icon"><?= $icon ?></span>
                                    <div>
                                        <strong><?= htmlspecialchars($factor['label']) ?></strong>
                                        <p style="margin: 3px 0 0 0; font-size: 13px; opacity: 0.95;"><?= htmlspecialchars($factor['detail']) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Subject Predictions Table -->
                    <div class="panel-card" style="padding: 0;">
                        <div style="padding: 24px 24px 0 24px;">
                            <div class="panel-title" style="margin-bottom: 15px;">
                                Projected Subject Performance
                                <span style="font-size: 13px; color: #64748b; font-weight: normal;">Ordered by predicted average score</span>
                            </div>
                        </div>
                        <div class="subject-table-card">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Subject Name</th>
                                        <th>Category</th>
                                        <th style="text-align: center;">Projected Average</th>
                                        <th style="text-align: center;">Projected Pass Rate</th>
                                        <th style="text-align: center;">Target Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($prediction['subject_predictions'] as $subj): ?>
                                        <tr>
                                            <td style="font-weight: 600;"><?= htmlspecialchars($subj['subject_name']) ?></td>
                                            <td>
                                                <span class="subject-badge <?= strtolower($subj['category']) ?>">
                                                    <?= htmlspecialchars($subj['category']) ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center; font-weight: 700; color: #1e3a8a;">
                                                <?= number_format($subj['predicted_avg'], 1) ?>%
                                            </td>
                                            <td style="text-align: center; font-weight: 600; color: #10b981;">
                                                <?= number_format($subj['predicted_pass_rate'], 1) ?>%
                                            </td>
                                            <td style="text-align: center;">
                                                <span class="badge badge-dark"><?= htmlspecialchars($subj['grade']) ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Gauge, Grade Dist & Model Diagnostics -->
                <div>
                    <!-- Prediction Confidence Gauge -->
                    <div class="panel-card" style="text-align: center;">
                        <div class="panel-title" style="text-align: left;">Prediction Confidence</div>
                        <div class="progress-circle-wrap">
                            <svg class="progress-circle-svg" viewBox="0 0 160 160">
                                <circle class="progress-circle-bg" cx="80" cy="80" r="70"></circle>
                                <circle class="progress-circle-val" cx="80" cy="80" r="70" id="confidence-circle"></circle>
                            </svg>
                            <span class="progress-circle-text"><?= (int)$prediction['confidence'] ?>%</span>
                        </div>
                        <p style="font-size: 13px; color: #64748b; margin: 0 0 10px 0;">
                            Confidence is calculated based on years of historical data and submission completeness.
                        </p>
                    </div>

                    <!-- Grade Distribution -->
                    <div class="panel-card">
                        <div class="panel-title">Expected Grade Distribution</div>
                        <div class="grade-bars">
                            <?php 
                            $max_count = max(1, max(array_values($prediction['grade_distribution'])));
                            foreach ($prediction['grade_distribution'] as $grade => $count): 
                                $pct = ($count / $max_count) * 100;
                            ?>
                                <div class="grade-bar-item">
                                    <span class="grade-name"><?= htmlspecialchars($grade) ?></span>
                                    <div class="grade-track">
                                        <div class="grade-fill" style="width: <?= $pct ?>%;"></div>
                                    </div>
                                    <span class="grade-count"><?= $count ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Model Diagnostics -->
                    <div class="panel-card">
                        <div class="panel-title">Model Diagnostics</div>
                        <div style="font-size: 13px; color: #475569; line-height: 1.6;">
                            <div style="margin-bottom: 8px;">
                                <span class="metrics-badge">MAE</span>
                                <strong><?= number_format($prediction['model_metrics']['mae'], 2) ?> marks</strong>
                                <div style="color: #94a3b8; font-size: 11px;">Mean Absolute Error of model cross validation</div>
                            </div>
                            <div style="margin-bottom: 8px;">
                                <span class="metrics-badge">R²</span>
                                <strong><?= number_format($prediction['model_metrics']['r2'], 4) ?></strong>
                                <div style="color: #94a3b8; font-size: 11px;">Coefficient of Determination</div>
                            </div>
                            <div style="margin-bottom: 8px;">
                                <span class="metrics-badge">RMSE</span>
                                <strong><?= number_format($prediction['model_metrics']['rmse'], 2) ?> marks</strong>
                                <div style="color: #94a3b8; font-size: 11px;">Root Mean Squared Error</div>
                            </div>
                            <div style="margin-top: 15px; border-top: 1px solid #f1f5f9; padding-top: 10px; font-size: 11px; color: #94a3b8; text-align: center;">
                                Model Version: <?= htmlspecialchars($prediction['model_version']) ?> | Custom Ensemble RF+Ridge
                            </div>
                        </div>
                    </div>

                </div>

            </div>

        <!-- ================= DIVISION LEVEL VIEW ================= -->
        <?php else: ?>
            
            <?php if (!empty($division_data)): ?>
                <?php
                // Compute Division metrics
                $total_predicted_score = 0;
                $total_predicted_pass = 0;
                $school_count = count($division_data);
                
                $highest_performing = null;
                $highest_risk = null;
                
                // Group by district
                $district_stats = [];
                // Group by school type
                $type_stats = [];
                
                foreach ($division_data as $row) {
                    $total_predicted_score += $row['predicted_avg_score'];
                    $total_predicted_pass += $row['predicted_pass_rate'];
                    
                    // Highest performing school
                    if ($highest_performing === null || $row['predicted_avg_score'] > $highest_performing['predicted_avg_score']) {
                        $highest_performing = $row;
                    }
                    
                    // Highest risk school
                    if ($highest_risk === null || $row['predicted_pass_rate'] < $highest_risk['predicted_pass_rate']) {
                        $highest_risk = $row;
                    }
                    
                    // District aggregation
                    $dist = $row['district'];
                    if (!isset($district_stats[$dist])) {
                        $district_stats[$dist] = ['total_score' => 0, 'total_pass' => 0, 'count' => 0];
                    }
                    $district_stats[$dist]['total_score'] += $row['predicted_avg_score'];
                    $district_stats[$dist]['total_pass'] += $row['predicted_pass_rate'];
                    $district_stats[$dist]['count']++;
                    
                    // School type aggregation
                    $type = $row['school_type'];
                    if (!isset($type_stats[$type])) {
                        $type_stats[$type] = ['total_score' => 0, 'total_pass' => 0, 'count' => 0];
                    }
                    $type_stats[$type]['total_score'] += $row['predicted_avg_score'];
                    $type_stats[$type]['total_pass'] += $row['predicted_pass_rate'];
                    $type_stats[$type]['count']++;
                }
                
                $div_avg_score = $total_predicted_score / $school_count;
                $div_avg_pass = $total_predicted_pass / $school_count;
                ?>

                <!-- Division Level Overview KPIs -->
                <div class="metric-cards">
                    <div class="metric-card blue">
                        <span class="metric-label">Division Avg. Score Projection</span>
                        <span class="metric-val"><?= number_format($div_avg_score, 1) ?>%</span>
                        <span class="metric-sub">Across <?= $school_count ?> registered schools</span>
                    </div>
                    <div class="metric-card green">
                        <span class="metric-label">Division Pass Rate Projection</span>
                        <span class="metric-val"><?= number_format($div_avg_pass, 1) ?>%</span>
                        <span class="metric-sub">Weighted school avg</span>
                    </div>
                    <div class="metric-card purple">
                        <span class="metric-label">Top Predicted School</span>
                        <span class="metric-val" style="font-size: 20px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?= htmlspecialchars($highest_performing['school_name'] ?? '') ?>
                        </span>
                        <span class="metric-sub">Projecting <?= number_format($highest_performing['predicted_avg_score'], 1) ?>% average</span>
                    </div>
                    <div class="metric-card orange">
                        <span class="metric-label">Highest Risk School</span>
                        <span class="metric-val" style="font-size: 20px; color: #b91c1c; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?= htmlspecialchars($highest_risk['school_name'] ?? '') ?>
                        </span>
                        <span class="metric-sub">Projecting <?= number_format($highest_risk['predicted_pass_rate'], 1) ?>% pass rate</span>
                    </div>
                </div>

                <!-- Division Grid Tables -->
                <div class="predict-grid">
                    
                    <!-- Left: Comparative Standings Table -->
                    <div class="panel-card" style="padding: 0;">
                        <div style="padding: 24px 24px 0 24px;">
                            <div class="panel-title" style="margin-bottom: 15px;">
                                Division School Standings
                                <span style="font-size: 13px; color: #64748b; font-weight: normal;">Ordered by projected average score</span>
                            </div>
                        </div>
                        <div class="subject-table-card" style="border: none;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>School Name</th>
                                        <th>District</th>
                                        <th>School Type</th>
                                        <th style="text-align: center;">Projected Average</th>
                                        <th style="text-align: center;">Projected Pass Rate</th>
                                        <th style="text-align: center;">Risk Level</th>
                                        <th style="text-align: center;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Sort schools by projected average score descending
                                    usort($division_data, function($a, $b) {
                                        return $b['predicted_avg_score'] <=> $a['predicted_avg_score'];
                                    });
                                    foreach ($division_data as $row): 
                                    ?>
                                        <tr>
                                            <td style="font-weight: 600;"><?= htmlspecialchars($row['school_name'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['district'] ?? '') ?></td>
                                            <td>
                                                <span class="subject-badge science">
                                                    <?= htmlspecialchars($row['school_type'] ?? '') ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center; font-weight: 700; color: #1e3a8a;">
                                                <?= number_format($row['predicted_avg_score'], 1) ?>%
                                            </td>
                                            <td style="text-align: center; font-weight: 600; color: #10b981;">
                                                <?= number_format($row['predicted_pass_rate'], 1) ?>%
                                            </td>
                                            <td style="text-align: center;">
                                                <span class="risk-badge <?= strtolower($row['risk_level'] ?? '') ?>" style="font-size: 11px; padding: 2px 6px;">
                                                    <?= htmlspecialchars($row['risk_level'] ?? '') ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center;">
                                                <a href="?exam_id=<?= $selected_exam_id ?>&school_id=<?= $row['school_id'] ?>" 
                                                   class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px; font-weight: 600;">
                                                    View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Right: District Breakdown & School Types -->
                    <div>
                        <!-- District Performance Breakdown -->
                        <div class="panel-card">
                            <div class="panel-title">District Performance</div>
                            <div style="display: flex; flex-direction: column; gap: 14px;">
                                <?php foreach ($district_stats as $district => $stat): 
                                    $dist_avg = $stat['total_score'] / $stat['count'];
                                    $dist_pass = $stat['total_pass'] / $stat['count'];
                                ?>
                                    <div>
                                        <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 14px; margin-bottom: 5px; color: #334155;">
                                            <span><?= htmlspecialchars($district) ?></span>
                                            <span style="color: #1e3a8a;"><?= number_format($dist_avg, 1) ?>% avg</span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; font-size: 12px; color: #64748b; margin-bottom: 8px;">
                                            <span><?= $stat['count'] ?> schools predicted</span>
                                            <span style="color: #10b981; font-weight: 600;"><?= number_format($dist_pass, 1) ?>% pass rate</span>
                                        </div>
                                        <div class="grade-track" style="height: 6px;">
                                            <div class="grade-fill" style="width: <?= $dist_avg ?>%; background: #3b82f6;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- School Type Projections -->
                        <div class="panel-card">
                            <div class="panel-title">School Type Projections</div>
                            <div style="display: flex; flex-direction: column; gap: 14px;">
                                <?php foreach ($type_stats as $type => $stat): 
                                    $type_avg = $stat['total_score'] / $stat['count'];
                                    $type_pass = $stat['total_pass'] / $stat['count'];
                                ?>
                                    <div>
                                        <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 14px; margin-bottom: 5px; color: #334155;">
                                            <span><?= htmlspecialchars($type) ?></span>
                                            <span style="color: #8b5cf6;"><?= number_format($type_avg, 1) ?>% avg</span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; font-size: 12px; color: #64748b; margin-bottom: 8px;">
                                            <span><?= $stat['count'] ?> schools predicted</span>
                                            <span style="color: #10b981; font-weight: 600;"><?= number_format($type_pass, 1) ?>% pass rate</span>
                                        </div>
                                        <div class="grade-track" style="height: 6px;">
                                            <div class="grade-fill" style="width: <?= $type_avg ?>%; background: #8b5cf6;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                </div>

            <?php else: ?>
                <div class="card" style="text-align: center; padding: 50px 30px;">
                    <h3 style="color: #475569; margin-bottom: 10px;">No Prediction Targets Available</h3>
                    <p style="color: #94a3b8; max-width: 500px; margin: 0 auto;">
                        No schools could be loaded for comparison. Check school entries in the School Management section.
                    </p>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    <?php if ($selected_school_id && $prediction): ?>
        // Animate the confidence circle
        const circle = document.getElementById('confidence-circle');
        if (circle) {
            const confidence = <?= (int)$prediction['confidence'] ?>;
            const r = circle.r.baseVal.value;
            const circumference = 2 * Math.PI * r;
            const offset = circumference - (confidence / 100) * circumference;
            circle.style.strokeDasharray = circumference;
            circle.style.strokeDashoffset = offset;
        }
    <?php endif; ?>
});
</script>
</body>
</html>
