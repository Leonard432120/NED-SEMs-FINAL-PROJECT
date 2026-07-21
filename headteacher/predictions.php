<?php
/* ════════════════════════════════════════════════════════════════
   headteacher/predictions.php
   Headteacher: AI performance predictions for the school
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once '../config/db.php';
require_once '../common/ai_service.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'headteacher') {
    header("Location: ../login.php"); exit();
}

$conn = get_db_connection();
$school_id = (int)$_SESSION['school_id'];

// Get school details
$school = $conn->query("SELECT school_name, district, school_type FROM schools WHERE school_id = $school_id")->fetch_assoc();
$school_name = $school['school_name'] ?? 'Your School';

// Fetch available exams
$exams_query = "SELECT exam_id, exam_name, class, year FROM exams ORDER BY year DESC, start_date DESC";
$exams = $conn->query($exams_query)->fetch_all(MYSQLI_ASSOC);

$selected_exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : ($exams[0]['exam_id'] ?? null);

$prediction = null;
$error_msg = null;

if ($selected_exam_id) {
    // Resolve Python and run predictor script
    $python = ai_resolve_python() ?: 'python';
    $script_path = dirname(__DIR__) . '/ai/predict.py';
    
    $cmd = $python . ' ' . escapeshellarg($script_path) 
         . ' --school_id=' . $school_id 
         . ' --exam_id=' . $selected_exam_id . ' 2>&1';
         
    $output = shell_exec($cmd);
    
    if ($output) {
        $prediction = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($prediction['status']) || $prediction['status'] !== 'success') {
            $error_msg = "Failed to compile AI predictions. Run verification on script output.";
            // Keep debug log
            error_log("AI predict.py failed output: " . $output);
        }
    } else {
        $error_msg = "Python AI engine did not respond. Please ensure execution permissions are enabled.";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Academic Performance Predictor | NED-SEMS</title>
    <?php $module_css = 'headteacher'; include __DIR__ . '/../common/head_assets.php'; ?>
    <style>
        .predict-header {
            background: white;
            color: black;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.15);
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
            background: white;
            color: black;
            backdrop-filter: blur(10px);
            border: 1px solid #e2e8f0;
            padding: 15px 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .filter-section label {
            color: #1e293b; 
            font-weight: 600;
        }
        .filter-section select {
            background: white;
            color: #1e293b;
            border: none;
            padding: 8px 16px;
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
            font-size: 32px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.1;
        }
        .metric-sub {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 6px;
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
            background: linear-gradient(90deg, #3b82f6, #60a5fa);
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
        .factor-icon {
            font-size: 16px;
            font-weight: bold;
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
        .subject-table-card tr:last-child td {
            border-bottom: none;
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
            stroke: #3b82f6;
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
                <p>Advanced machine learning models projecting exam performance for <?= htmlspecialchars($school_name) ?></p>
            </div>
            
            <?php if (!empty($exams)): ?>
                <div class="filter-section">
                    <label for="exam-select">Select Exam:</label>
                    <form method="GET" action="" id="exam-form">
                        <select name="exam_id" id="exam-select" onchange="document.getElementById('exam-form').submit()">
                            <?php foreach ($exams as $ex): ?>
                                <option value="<?= $ex['exam_id'] ?>" <?= $selected_exam_id == $ex['exam_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ex['exam_name']) ?> (<?= htmlspecialchars($ex['class']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger" style="margin-bottom: 30px;">
                <strong>System Notice:</strong> <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($prediction): ?>
            <!-- Metric KPI Summary Cards -->
            <div class="metric-cards">
                <div class="metric-card blue">
                    <span class="metric-label">Predicted Avg. Score</span>
                    <span class="metric-val"><?= number_format($prediction['predicted_avg_score'] ?? 0, 1) ?>%</span>
                    <span class="metric-sub">Division Rank Projectable</span>
                </div>
                <div class="metric-card green">
                    <span class="metric-label">Predicted Pass Rate</span>
                    <span class="metric-val"><?= number_format($prediction['predicted_pass_rate'] ?? 0, 1) ?>%</span>
                    <span class="metric-sub">Based on current student data</span>
                </div>
                <div class="metric-card purple">
                    <span class="metric-label">Grade Band Estimate</span>
                    <span class="metric-val"><?= htmlspecialchars($prediction['grade_band'] ?? '') ?></span>
                    <span class="metric-sub">Expected school performance tier</span>
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
        <?php else: ?>
            <div class="card" style="text-align: center; padding: 50px 30px;">
                <h3 style="color: #475569; margin-bottom: 10px;">No Prediction Target Available</h3>
                <p style="color: #94a3b8; max-width: 500px; margin: 0 auto 20px auto;">
                    There are no exams currently registered or available for prediction. Make sure exams are defined by EDM Control Center.
                </p>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    <?php if ($prediction): ?>
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
