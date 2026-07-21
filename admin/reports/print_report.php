<?php
/* ════════════════════════════════════════════════════════════════
   admin/reports/print_report.php
   EDM/Admin: Unified Printable Report Generator
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../common/report_stats.php';
require_once __DIR__ . '/../../common/report_charts.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

$conn = get_db_connection();

$type = isset($_GET['type']) ? trim($_GET['type']) : 'division';
$selected_exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;
$district = isset($_GET['district']) ? trim($_GET['district']) : '';
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

// Default exam fallback if none specified
if ($selected_exam_id <= 0) {
    $ex_res = $conn->query("SELECT exam_id FROM exams ORDER BY year DESC, start_date DESC LIMIT 1")->fetch_assoc();
    $selected_exam_id = (int)($ex_res['exam_id'] ?? 0);
}

// Fetch exam details
$exam_name = '';
$exam_year = '';
$exam_class = '';
if ($selected_exam_id > 0) {
    $stmt = $conn->prepare("SELECT exam_name, year, class FROM exams WHERE exam_id = ?");
    $stmt->bind_param("i", $selected_exam_id);
    $stmt->execute();
    $ex = $stmt->get_result()->fetch_assoc();
    $exam_name = $ex['exam_name'] ?? '';
    $exam_year = $ex['year'] ?? '';
    $exam_class = $ex['class'] ?? '';
    $stmt->close();
}

// Prepare report title and parameters
$report_title = 'Division Performance Report';
$meta_params = "Exam: {$exam_name} ({$exam_year})";
$kpis = [];
$tables_data = [];
$charts_html = [];
$recommendations = [];

/* ────────────────────────────────────────────────────────────────
   DIVISION REPORT GENERATOR
   ──────────────────────────────────────────────────────────────── */
if ($type === 'division') {
    $report_title = "Division Strategic Report";
    
    // Division Avg Score
    $stmt = $conn->prepare("SELECT AVG(average_score) as v FROM results WHERE exam_id = ? AND status='published'");
    $stmt->bind_param("i", $selected_exam_id);
    $stmt->execute();
    $div_avg = round((float)($stmt->get_result()->fetch_assoc()['v'] ?? 0.0), 1);
    $stmt->close();

    // District comparisons
    $dist_stmt = $conn->prepare("
        SELECT sc.district, AVG(r.average_score) as avg_score, COUNT(*) as count
        FROM results r
        JOIN students s ON r.student_id = s.student_id
        JOIN schools sc ON s.school_id = sc.school_id
        WHERE r.exam_id = ? AND r.status='published'
        GROUP BY sc.district
        ORDER BY avg_score DESC
    ");
    $dist_stmt->bind_param("i", $selected_exam_id);
    $dist_stmt->execute();
    $district_perf = $dist_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $dist_stmt->close();

    $kpis = [
        ['label' => 'Division Average', 'value' => $div_avg . '%'],
        ['label' => 'Districts Compared', 'value' => count($district_perf)],
        ['label' => 'Best District', 'value' => $district_perf[0]['district'] ?? 'N/A'],
        ['label' => 'Lowest District', 'value' => (count($district_perf) > 1) ? $district_perf[count($district_perf)-1]['district'] : 'N/A']
    ];

    $tables_data[] = [
        'title' => 'District Standings Comparisons',
        'headers' => ['District', 'Candidates Count', 'Mean Average Score'],
        'rows' => array_map(fn($dp) => [$dp['district'], $dp['count'], number_format($dp['avg_score'], 1) . '%'], $district_perf)
    ];

    $recommendations = stats_recommendations($div_avg, 60.0, 'stable', 'division');
}

/* ────────────────────────────────────────────────────────────────
   SCHOOL REPORT GENERATOR
   ──────────────────────────────────────────────────────────────── */
elseif ($type === 'school' && $school_id > 0) {
    // School Details
    $sch_stmt = $conn->prepare("SELECT school_name, district, school_type FROM schools WHERE school_id = ?");
    $sch_stmt->bind_param("i", $school_id);
    $sch_stmt->execute();
    $sch = $sch_stmt->get_result()->fetch_assoc();
    $school_name = $sch['school_name'] ?? '';
    $sch_stmt->close();

    $report_title = "School Performance Portfolio: " . $school_name;
    $meta_params .= " | District: " . ($sch['district'] ?? '') . " | Type: " . ($sch['school_type'] ?? 'DAY');

    // School Average Score
    $avg_stmt = $conn->prepare("SELECT AVG(r.average_score) as v FROM results r 
                               JOIN students s ON r.student_id = s.student_id 
                               WHERE s.school_id = ? AND r.exam_id = ? AND r.status='published'");
    $avg_stmt->bind_param("ii", $school_id, $selected_exam_id);
    $avg_stmt->execute();
    $school_avg = round((float)($avg_stmt->get_result()->fetch_assoc()['v'] ?? 0.0), 1);
    $avg_stmt->close();

    $kpis = [
        ['label' => 'School Mean', 'value' => $school_avg . '%'],
        ['label' => 'School Type', 'value' => $sch['school_type'] ?? 'DAY'],
        ['label' => 'District Division', 'value' => $sch['district'] ?? 'N/A']
    ];

    // Subject averages inside school
    $subj_stmt = $conn->prepare("
        SELECT s.subject_name, s.subject_code, AVG(m.score) as avg_score
        FROM marks m
        JOIN subjects s ON m.subject_id = s.subject_id
        JOIN students st ON m.student_id = st.student_id
        WHERE st.school_id = ? AND m.exam_id = ? AND m.status='approved'
        GROUP BY m.subject_id
        ORDER BY avg_score DESC
    ");
    $subj_stmt->bind_param("ii", $school_id, $selected_exam_id);
    $subj_stmt->execute();
    $subjects_perf = $subj_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $subj_stmt->close();

    $tables_data[] = [
        'title' => 'Subject Standings Analysis',
        'headers' => ['Syllabus Code', 'Syllabus Subject', 'School Average Score'],
        'rows' => array_map(fn($sp) => [$sp['subject_code'], $sp['subject_name'], number_format($sp['avg_score'], 1) . '%'], $subjects_perf)
    ];

    $recommendations = stats_recommendations($school_avg, 55.0, 'stable', 'school');
}

/* ────────────────────────────────────────────────────────────────
   DISTRICT REPORT GENERATOR
   ──────────────────────────────────────────────────────────────── */
elseif ($type === 'district' && $district !== '') {
    $report_title = "District Analysis Report: " . $district;
    
    // District Average
    $avg_stmt = $conn->prepare("
        SELECT AVG(r.average_score) as v FROM results r
        JOIN students s ON r.student_id = s.student_id
        JOIN schools sch ON s.school_id = sch.school_id
        WHERE sch.district = ? AND r.exam_id = ? AND r.status='published'
    ");
    $avg_stmt->bind_param("si", $district, $selected_exam_id);
    $avg_stmt->execute();
    $dist_avg = round((float)($avg_stmt->get_result()->fetch_assoc()['v'] ?? 0.0), 1);
    $avg_stmt->close();

    $kpis = [
        ['label' => 'District Average', 'value' => $dist_avg . '%'],
        ['label' => 'District Region', 'value' => $district]
    ];

    // Top schools in District
    $top_sch_stmt = $conn->prepare("
        SELECT sc.school_name, AVG(r.average_score) as avg_score
        FROM results r
        JOIN students s ON r.student_id = s.student_id
        JOIN schools sc ON s.school_id = sc.school_id
        WHERE sc.district = ? AND r.exam_id = ? AND r.status='published'
        GROUP BY s.school_id
        ORDER BY avg_score DESC
    ");
    $top_sch_stmt->bind_param("si", $district, $selected_exam_id);
    $top_sch_stmt->execute();
    $schools_ranking = $top_sch_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $top_sch_stmt->close();

    $tables_data[] = [
        'title' => 'School Standings in ' . $district,
        'headers' => ['Rank', 'School Center', 'Mean Average Score'],
        'rows' => array_map(fn($idx, $sr) => [$idx + 1, $sr['school_name'], number_format($sr['avg_score'], 1) . '%'], array_keys($schools_ranking), $schools_ranking)
    ];

    $recommendations = stats_recommendations($dist_avg, 58.0, 'stable', 'district');
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($report_title) ?></title>
    <style>
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: #1e293b;
            background: #ffffff;
            line-height: 1.5;
            padding: 40px;
        }
        .header-wrap {
            border-bottom: 3px solid #0f172a;
            padding-bottom: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .title-h1 {
            font-size: 2rem;
            margin: 0;
            color: #0f172a;
            font-weight: 800;
        }
        .meta-p {
            font-size: 0.875rem;
            color: #64748b;
            margin: 4px 0 0 0;
        }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }
        .kpi-card {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 14px 16px;
            text-align: center;
        }
        .kpi-label {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #64748b;
            font-weight: 700;
            margin-bottom: 6px;
            display: block;
        }
        .kpi-val {
            font-size: 1.6rem;
            font-weight: bold;
            color: #0f172a;
        }
        .table-wrap {
            margin-bottom: 30px;
        }
        .table-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: #0f172a;
        }
        .print-table {
            width: 100%;
            border-collapse: collapse;
        }
        .print-table th {
            background: #f8fafc;
            padding: 10px 14px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            border-bottom: 2px solid #cbd5e1;
            text-align: left;
            font-weight: 700;
        }
        .print-table td {
            padding: 10px 14px;
            font-size: 0.875rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .print-table tr:nth-child(even) {
            background: #fafbfc;
        }
        .rec-panel {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
            color: #1e40af;
            page-break-inside: avoid;
        }
        .rec-title {
            font-size: 1rem;
            font-weight: bold;
            margin-top: 0;
            margin-bottom: 10px;
            color: #1e3a8a;
        }
        .rec-ul {
            margin: 0;
            padding-left: 20px;
            font-size: 0.875rem;
        }
        .rec-ul li {
            margin-bottom: 6px;
            line-height: 1.5;
        }
        .btn-print-trigger {
            background: #3b82f6;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: bold;
            cursor: pointer;
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        @media print {
            .btn-print-trigger {
                display: none !important;
            }
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>

    <button onclick="window.print()" class="btn-print-trigger">🖨️ Open Print Dialog</button>

    <div class="header-wrap">
        <div>
            <h1 class="title-h1"><?= htmlspecialchars($report_title) ?></h1>
            <p class="meta-p"><?= htmlspecialchars($meta_params) ?></p>
        </div>
        <div style="text-align: right;">
            <p class="meta-p">Prepared: <?= date('Y-m-d H:i:s') ?></p>
            <p class="meta-p">EDM Control Room Dashboard</p>
        </div>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid">
        <?php foreach ($kpis as $kp): ?>
            <div class="kpi-card">
                <span class="kpi-label"><?= htmlspecialchars($kp['label']) ?></span>
                <span class="kpi-val"><?= htmlspecialchars($kp['value']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Tables -->
    <?php foreach ($tables_data as $tbl): ?>
        <div class="table-wrap">
            <div class="table-title"><?= htmlspecialchars($tbl['title']) ?></div>
            <table class="print-table">
                <thead>
                    <tr>
                        <?php foreach ($tbl['headers'] as $h): ?>
                            <th><?= htmlspecialchars($h) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tbl['rows'] as $r): ?>
                        <tr>
                            <?php foreach ($r as $val): ?>
                                <td><?= htmlspecialchars($val) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>

    <!-- Recommendations -->
    <div class="rec-panel">
        <h4 class="rec-title">Strategic Action Recommendations</h4>
        <ul class="rec-ul">
            <?php foreach ($recommendations as $rec): ?>
                <li><?= htmlspecialchars($rec) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

</body>
</html>
