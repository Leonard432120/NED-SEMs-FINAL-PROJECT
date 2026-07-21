<?php
/* ════════════════════════════════════════════════════════════════
   teacher/analytics.php
   Teacher: views subject-specific and exam performance analytics
   ════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/teacher_init.php';
require_once __DIR__ . '/../common/grade_helper.php';
require_once __DIR__ . '/../common/pagination_helper.php';

$conn      = get_db_connection();
$teacher_id = (int)$_SESSION['user_id'];
$school_id  = (int)($_SESSION['school_id'] ?? 0);

$exam_id    = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

$message = '';
$msg_type = '';

/* ── MODE 1: SELECT EXAM & SUBJECT (If not both provided) ── */
if ($exam_id <= 0 || $subject_id <= 0) {
    // Fetch all assigned exam-subject pairings for this teacher
    $assigned_pairings = $conn->query("
        SELECT DISTINCT e.exam_id, e.exam_name, e.class, e.year, es.subject_id, s.subject_name, s.subject_code, es.total_marks
        FROM exams e
        JOIN exam_subjects es ON e.exam_id = es.exam_id
        JOIN subjects s ON es.subject_id = s.subject_id
        JOIN subject_assignments sa ON es.subject_id = sa.subject_id
        WHERE sa.teacher_id = {$teacher_id}
        ORDER BY e.year DESC, e.exam_name ASC, s.subject_name ASC
    ")->fetch_all(MYSQLI_ASSOC);

    $conn->close();
    $module_css = 'teacher';
    include __DIR__ . '/../common/head_assets.php';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Performance Analytics | NED-SEMS</title>
    </head>
    <body>
    <?php include __DIR__ . '/../common/header.php'; ?>
    <div class="dashboard">
        <?php include __DIR__ . '/../common/sidebar.php'; ?>
        <div class="content">
            <div class="page-header">
                <div>
                    <h2 class="page-title">Performance Analytics</h2>
                    <p class="page-subtitle">Select an exam and subject to view detailed performance metrics and AI insights</p>
                </div>
                <a href="dashboard.php" class="btn btn-secondary">Back</a>
            </div>

            <div class="card">
                <div class="section-header">
                    <h3>Your Assigned Exam Subjects</h3>
                </div>
                
                <?php if (empty($assigned_pairings)): ?>
                    <div class="empty-state" style="padding: 40px; text-align: center;">
                        <p style="color: var(--text-muted); font-size: 1.1rem; margin-bottom: 16px;">No exam subjects are currently linked to your assignments.</p>
                        <p style="font-size: 0.9rem;">Contact your Examination Officer if you believe this is an error.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Exam Name</th>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Total Marks</th>
                                    <th>Year</th>
                                    <th style="width: 150px; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assigned_pairings as $pair): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?= htmlspecialchars($pair['exam_name']) ?></td>
                                    <td><?= htmlspecialchars($pair['class']) ?></td>
                                    <td>
                                        <span style="font-weight: 600; color: var(--primary-dark);">
                                            <?= htmlspecialchars($pair['subject_name']) ?>
                                        </span> 
                                        (<?= htmlspecialchars($pair['subject_code']) ?>)
                                    </td>
                                    <td><?= htmlspecialchars($pair['total_marks']) ?></td>
                                    <td><?= htmlspecialchars($pair['year']) ?></td>
                                    <td style="text-align: center;">
                                        <a href="analytics.php?exam_id=<?= $pair['exam_id'] ?>&subject_id=<?= $pair['subject_id'] ?>" class="btn btn-teal btn-small">
                                            View Analytics
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../common/footer.php'; ?>
    </body>
    </html>
    <?php
    exit();
}

/* ── MODE 2: DETAILED ANALYTICS FOR SELECTED EXAM + SUBJECT ── */

// 1. Verify access: does teacher have assignment for this subject in this exam?
$stmt = $conn->prepare("
    SELECT 1 
    FROM subject_assignments sa
    JOIN exam_subjects es ON sa.subject_id = es.subject_id
    WHERE es.exam_id = ? AND es.subject_id = ? AND sa.teacher_id = ?
");
$stmt->bind_param("iii", $exam_id, $subject_id, $teacher_id);
$stmt->execute();
$has_access = $stmt->get_result()->num_rows > 0;
$stmt->close();

if (!$has_access) {
    $conn->close();
    header("Location: analytics.php?msg=" . urlencode("Access denied to that subject's analytics.") . "&mtype=error");
    exit();
}

// 2. Fetch Exam and Subject details
$stmt = $conn->prepare("
    SELECT e.exam_id, e.exam_name, e.class, e.year, es.total_marks, s.subject_name, s.subject_code
    FROM exams e
    JOIN exam_subjects es ON e.exam_id = es.exam_id
    JOIN subjects s ON es.subject_id = s.subject_id
    WHERE e.exam_id = ? AND es.subject_id = ?
");
$stmt->bind_param("ii", $exam_id, $subject_id);
$stmt->execute();
$meta = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$meta) {
    $conn->close();
    header("Location: analytics.php?msg=" . urlencode("Exam or Subject metadata not found.") . "&mtype=error");
    exit();
}

$total_marks = (int)($meta['total_marks'] ?? 100);

// 3. Fetch Subject-specific Stats from the `marks` table for this school
$stmt = $conn->prepare("
    SELECT 
        COUNT(m.mark_id) AS total_students,
        AVG(m.score) AS avg_score,
        SUM(CASE WHEN (m.score / ?) * 100 >= 40 THEN 1 ELSE 0 END) AS passed
    FROM marks m
    JOIN students s ON m.student_id = s.student_id
    WHERE m.exam_id = ? AND m.subject_id = ? AND s.school_id = ? AND m.status IN ('submitted', 'approved')
");
$stmt->bind_param("iiii", $total_marks, $exam_id, $subject_id, $school_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total_students = (int)($stats['total_students'] ?? 0);
$avg_score_raw  = (float)($stats['avg_score'] ?? 0);
$avg_percentage = $total_marks > 0 ? ($avg_score_raw / $total_marks) * 100 : 0;
$passed_count   = (int)($stats['passed'] ?? 0);
$pass_rate      = $total_students > 0 ? round(($passed_count / $total_students) * 100, 1) : 0;

// 4. Fetch Division benchmark average for comparison
$stmt = $conn->prepare("
    SELECT AVG((m.score / es.total_marks) * 100) as div_avg
    FROM marks m
    JOIN exam_subjects es ON m.exam_id = es.exam_id AND m.subject_id = es.subject_id
    WHERE m.exam_id = ? AND m.subject_id = ? AND m.status IN ('submitted', 'approved')
");
$stmt->bind_param("ii", $exam_id, $subject_id);
$stmt->execute();
$div_benchmark = round((float)($stmt->get_result()->fetch_assoc()['div_avg'] ?? 0.0), 1);
$stmt->close();

// 5. Grade distribution in this subject (grades 1 to 9)
$stmt = $conn->prepare("
    SELECT m.grade, COUNT(*) AS count
    FROM marks m
    JOIN students s ON m.student_id = s.student_id
    WHERE m.exam_id = ? AND m.subject_id = ? AND s.school_id = ? AND m.status IN ('submitted', 'approved')
    GROUP BY m.grade
    ORDER BY m.grade ASC
");
$stmt->bind_param("iii", $exam_id, $subject_id, $school_id);
$stmt->execute();
$grades_res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$grade_dist = [];
for ($g = 1; $g <= 9; $g++) {
    $grade_dist[(string)$g] = 0;
}
foreach ($grades_res as $r) {
    if (isset($grade_dist[$r['grade']])) {
        $grade_dist[$r['grade']] = (int)$r['count'];
    }
}

// 6. Paginated Candidate Marks List (Tab 2)
$page_m = isset($_GET['page_m']) ? (int)$_GET['page_m'] : 1;
$pagination_m = paginate($total_students, $page_m, 6);
$offset_m = ($pagination_m['page'] - 1) * $pagination_m['per_page'];

$stmt = $conn->prepare("
    SELECT st.name AS student_name, st.exam_number, m.score, m.grade, m.status
    FROM marks m
    JOIN students st ON m.student_id = st.student_id
    WHERE m.exam_id = ? AND m.subject_id = ? AND st.school_id = ?
    ORDER BY m.score DESC, st.name ASC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iiiii", $exam_id, $subject_id, $school_id, $pagination_m['per_page'], $offset_m);
$stmt->execute();
$student_marks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 7. Paginated Students At Risk List (Tab 3 - score < 40%)
$at_risk_count = 0;
$stmt = $conn->prepare("
    SELECT COUNT(*) as cnt
    FROM marks m
    JOIN students st ON m.student_id = st.student_id
    WHERE m.exam_id = ? AND m.subject_id = ? AND st.school_id = ? AND m.score < 40.0
");
$stmt->bind_param("iii", $exam_id, $subject_id, $school_id);
$stmt->execute();
$at_risk_count = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt->close();

$page_r = isset($_GET['page_r']) ? (int)$_GET['page_r'] : 1;
$pagination_r = paginate($at_risk_count, $page_r, 6);
$offset_r = ($pagination_r['page'] - 1) * $pagination_r['per_page'];

$students_at_risk = [];
if ($at_risk_count > 0) {
    $stmt = $conn->prepare("
        SELECT st.name AS student_name, st.exam_number, m.score, m.grade
        FROM marks m
        JOIN students st ON m.student_id = st.student_id
        WHERE m.exam_id = ? AND m.subject_id = ? AND st.school_id = ? AND m.score < 40.0
        ORDER BY m.score ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iiiii", $exam_id, $subject_id, $school_id, $pagination_r['per_page'], $offset_r);
    $stmt->execute();
    $students_at_risk = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// 8. Paginated Missing Submissions List (Tab 4)
$missing_count = 0;
$stmt = $conn->prepare("
    SELECT COUNT(*) as cnt
    FROM students st
    JOIN student_subjects ss ON st.student_id = ss.student_id
    WHERE ss.subject_id = ? AND st.school_id = ? AND st.status = 'active'
      AND NOT EXISTS (
          SELECT 1 FROM marks m 
          WHERE m.student_id = st.student_id AND m.exam_id = ? AND m.subject_id = ?
      )
");
$stmt->bind_param("iiii", $subject_id, $school_id, $exam_id, $subject_id);
$stmt->execute();
$missing_count = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt->close();

$page_x = isset($_GET['page_x']) ? (int)$_GET['page_x'] : 1;
$pagination_x = paginate($missing_count, $page_x, 6);
$offset_x = ($pagination_x['page'] - 1) * $pagination_x['per_page'];

$missing_submissions = [];
if ($missing_count > 0) {
    $stmt = $conn->prepare("
        SELECT st.name as student_name, st.exam_number, st.class
        FROM students st
        JOIN student_subjects ss ON st.student_id = ss.student_id
        WHERE ss.subject_id = ? AND st.school_id = ? AND st.status = 'active'
          AND NOT EXISTS (
              SELECT 1 FROM marks m 
              WHERE m.student_id = st.student_id AND m.exam_id = ? AND m.subject_id = ?
          )
        ORDER BY st.name ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iiiiii", $subject_id, $school_id, $exam_id, $subject_id, $pagination_x['per_page'], $offset_x);
    $stmt->execute();
    $missing_submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// 9. Historical Performance Trend for benchmark
$stmt = $conn->prepare("
    SELECT e.year, AVG((m.score / es.total_marks) * 100) AS avg_pct
    FROM marks m
    JOIN exams e ON m.exam_id = e.exam_id
    JOIN exam_subjects es ON m.exam_id = es.exam_id AND m.subject_id = es.subject_id
    JOIN students st ON m.student_id = st.student_id
    WHERE m.subject_id = ? AND st.school_id = ? AND m.status IN ('submitted', 'approved')
    GROUP BY e.year
    ORDER BY e.year ASC
");
$stmt->bind_param("ii", $subject_id, $school_id);
$stmt->execute();
$historical = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$ai_insights = [];
$historical_values = array_column($historical, 'avg_pct');
if (count($historical_values) >= 2) {
    $growth = [];
    for ($i = 1; $i < count($historical_values); $i++) {
        $growth[] = $historical_values[$i] - $historical_values[$i-1];
    }
    $avg_growth = array_sum($growth) / count($growth);
    $predicted_next = round($historical_values[count($historical_values)-1] + $avg_growth, 1);
    
    if ($avg_growth > 1.5) {
        $ai_insights[] = "Performance in this subject is on an upward trajectory. Keep up the great work!";
    } elseif ($avg_growth < -1.5) {
        $ai_insights[] = "Performance in this subject is declining. Focus interventions on weaker topics.";
    } else {
        $ai_insights[] = "Performance remains stable. Look for opportunities to push middle-tier students higher.";
    }
    $ai_insights[] = "Predicted average percentage for the next exam: " . max(0, min($predicted_next, 100)) . "%";
} else {
    $ai_insights[] = "Historical data is limited. Compile results across multiple terms/years to generate predictive trends.";
}

$conn->close();
$module_css = 'teacher';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subject Analytics | NED-SEMS</title>
    <style>
        .analytics-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .stats-card {
            background: #fff;
            border-radius: var(--border-radius);
            padding: 20px;
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .stats-card h4 {
            color: var(--text-muted);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .stats-card .stats-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-dark);
        }

        .tabs-header {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 1px;
        }

        .tab-btn {
            padding: 10px 20px;
            border: none;
            background: none;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.25s ease;
        }

        .tab-btn:hover {
            color: var(--primary-dark);
        }

        .tab-btn.active {
            color: var(--info-color);
            border-bottom-color: var(--info-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .chart-placeholder {
            min-height: 200px;
            display: flex;
            align-items: flex-end;
            gap: 8px;
            padding-top: 20px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 10px;
        }
        .chart-bar-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            justify-content: flex-end;
        }
        .chart-bar {
            width: 100%;
            background: var(--primary-dark);
            border-radius: 4px 4px 0 0;
            transition: height 0.5s ease;
            min-height: 4px;
        }
        .chart-label {
            font-size: 0.75rem;
            font-weight: bold;
            margin-top: 6px;
            color: var(--text-color);
        }
        .chart-val {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-bottom: 2px;
        }
        .grid-two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        @media (max-width: 768px) {
            .grid-two-col {
                grid-template-columns: 1fr;
            }
        }
        
        .benchmark-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .benchmark-progress {
            flex: 1;
            height: 14px;
            background: #f1f5f9;
            border-radius: 6px;
            overflow: hidden;
            margin: 0 15px;
        }
        .benchmark-bar {
            height: 100%;
            background: var(--info-color);
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../common/header.php'; ?>
<div class="dashboard">
    <?php include __DIR__ . '/../common/sidebar.php'; ?>
    <div class="content">
        <div class="page-header">
            <div>
                <h2 class="page-title"><?= htmlspecialchars($meta['subject_name']) ?> Performance Analytics</h2>
                <p class="page-subtitle"><?= htmlspecialchars($meta['exam_name']) ?> (Class: <?= htmlspecialchars($meta['class']) ?>)</p>
            </div>
            <a href="analytics.php" class="btn btn-secondary">Back to List</a>
        </div>

        <!-- KPIs -->
        <div class="analytics-dashboard">
            <div class="stats-card">
                <h4>Total Assessed</h4>
                <div class="stats-value"><?= $total_students ?></div>
            </div>
            <div class="stats-card">
                <h4>Class Average</h4>
                <div class="stats-value"><?= number_format($avg_percentage, 1) ?>%</div>
            </div>
            <div class="stats-card">
                <h4>Pass Rate (&ge;40%)</h4>
                <div class="stats-value"><?= $pass_rate ?>%</div>
            </div>
            <div class="stats-card" style="border-left: 4px solid var(--warning-color);">
                <h4>Missing Marks</h4>
                <div class="stats-value" style="color: <?= $missing_count > 0 ? '#ef4444' : 'var(--success-color)' ?>;"><?= $missing_count ?></div>
            </div>
        </div>

        <!-- TABS -->
        <div class="tabs-header">
            <button class="tab-btn active" onclick="switchTab(event, 'perfTab')">Performance & Distribution</button>
            <button class="tab-btn" onclick="switchTab(event, 'marksTab')">Candidate Marks</button>
            <button class="tab-btn" onclick="switchTab(event, 'riskTab')">Students At Risk (<?= $at_risk_count ?>)</button>
            <button class="tab-btn" onclick="switchTab(event, 'missingTab')">Missing Scores (<?= $missing_count ?>)</button>
        </div>

        <!-- TAB CONTENTS -->
        <div class="card" style="padding: 24px;">

            <!-- 1. PERFORMANCE TAB -->
            <div id="perfTab" class="tab-content active">
                
                <div class="grid-two-col">
                    <!-- Grade Distribution -->
                    <div class="section" style="margin:0;">
                        <h3>Grade Distribution Chart</h3>
                        <div class="chart-placeholder">
                            <?php 
                            $max_count = max(1, max(array_values($grade_dist)));
                            foreach ($grade_dist as $g => $cnt): 
                                $pct_height = ($cnt / $max_count) * 100;
                            ?>
                                <div class="chart-bar-container">
                                    <span class="chart-val"><?= $cnt ?></span>
                                    <div class="chart-bar" style="height: <?= $pct_height ?>%; background: <?= $g <= 3 ? '#22c55e' : ($g <= 7 ? '#eab308' : '#ef4444') ?>;"></div>
                                    <span class="chart-label">G<?= $g ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p style="font-size: 0.8rem; color: var(--text-muted); text-align: center; margin-top: 10px;">
                            Grades 1-7 represent JCE/MSCE passing grades, 8-9 represent fails.
                        </p>
                    </div>

                    <!-- Division Benchmarks Comparison -->
                    <div class="section" style="margin:0;">
                        <h3>Division Benchmarks Comparison</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 20px;">Benchmark comparison between your class average and the division-wide subject average</p>
                        
                        <div class="benchmark-row">
                            <span style="font-weight:600; min-width: 100px;">Your Class:</span>
                            <div class="benchmark-progress">
                                <div class="benchmark-bar" style="width: <?= $avg_percentage ?>%; background: var(--info-color);"></div>
                            </div>
                            <span style="font-weight:bold; min-width: 50px; text-align: right;"><?= number_format($avg_percentage, 1) ?>%</span>
                        </div>

                        <div class="benchmark-row">
                            <span style="font-weight:600; min-width: 100px;">Division Avg:</span>
                            <div class="benchmark-progress">
                                <div class="benchmark-bar" style="width: <?= $div_benchmark ?>%; background: #64748b;"></div>
                            </div>
                            <span style="font-weight:bold; min-width: 50px; text-align: right;"><?= number_format($div_benchmark, 1) ?>%</span>
                        </div>

                        <div style="margin-top: 20px; padding: 12px; border-radius: 6px; background:#f8fafc; font-size: 0.85rem; color:#475569;">
                            <?php
                            $diff = $avg_percentage - $div_benchmark;
                            if ($diff > 0) {
                                echo "👏 Your class average is **" . number_format($diff, 1) . "% above** the division benchmark! Keep maintaining these standards.";
                            } else {
                                echo "⚠️ Your class average is **" . number_format(abs($diff), 1) . "% below** the division benchmark. Look at targeting borderline students in the risk tab.";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- AI Insights -->
                <div class="section" style="margin:0; background: #faf5ff; border: 1px solid #e9d5ff;">
                    <h3 style="color: #6b21a8;">Academic AI Insights</h3>
                    <ul style="margin: 8px 0 0; padding-left: 20px; font-size: 0.95rem; line-height: 1.6; color: #581c87;">
                        <?php foreach ($ai_insights as $insight): ?>
                            <li style="margin-bottom: 12px;"><?= htmlspecialchars($insight) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

            </div>

            <!-- 2. CANDIDATE MARKS TAB -->
            <div id="marksTab" class="tab-content">
                <div style="margin-bottom:16px;">
                    <h3>Candidate Marks & Grads</h3>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Exam Number</th>
                                <th style="text-align: center;">Score (/<?= $total_marks ?>)</th>
                                <th style="text-align: center;">Percentage</th>
                                <th style="text-align: center;">Grade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($student_marks)): ?>
                                <tr><td colspan="6" class="empty-state">No scores entered yet for this exam and subject.</td></tr>
                            <?php else: ?>
                                <?php foreach ($student_marks as $m): ?>
                                <?php $pct = $total_marks > 0 ? ($m['score'] / $total_marks) * 100 : 0; ?>
                                    <tr>
                                        <td style="font-weight: 600;"><?= htmlspecialchars($m['student_name']) ?></td>
                                        <td><code><?= htmlspecialchars($m['exam_number']) ?></code></td>
                                        <td style="text-align: center; font-weight: bold;"><?= htmlspecialchars($m['score']) ?></td>
                                        <td style="text-align: center;"><?= number_format($pct, 1) ?>%</td>
                                        <td style="text-align: center;">
                                            <span class="badge" style="background:#f1f5f9; color:#1e293b; font-weight:bold; padding: 4px 10px;">
                                                <?= htmlspecialchars($m['grade']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $m['status'] === 'approved' ? 'success' : ($m['status'] === 'submitted' ? 'primary' : 'warning') ?>">
                                                <?= ucfirst(htmlspecialchars($m['status'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION -->
                <?php if ($pagination_m['total_pages'] > 1): ?>
                    <div style="margin-top: 15px; display: flex; justify-content: center;">
                        <?= render_pagination_keyed($pagination_m, 'analytics.php', 'page_m') ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 3. STUDENTS AT RISK TAB -->
            <div id="riskTab" class="tab-content">
                <div style="margin-bottom:16px;">
                    <h3>Students At Risk (Score < 40%)</h3>
                    <p style="font-size:0.85rem; color:var(--text-muted);">List of students currently failing this subject area. Target these students for immediate revision.</p>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Exam Number</th>
                                <th style="text-align: center;">Score</th>
                                <th style="text-align: center;">Percentage</th>
                                <th style="text-align: center;">Grade</th>
                                <th>Action Recommendation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students_at_risk)): ?>
                                <tr><td colspan="6" class="empty-state" style="color:#15803d; font-weight:600;">No students at risk. Excellent class performance!</td></tr>
                            <?php else: ?>
                                <?php foreach ($students_at_risk as $sar): ?>
                                <?php $pct = $total_marks > 0 ? ($sar['score'] / $total_marks) * 100 : 0; ?>
                                    <tr>
                                        <td style="font-weight: 600;"><?= htmlspecialchars($sar['student_name']) ?></td>
                                        <td><code><?= htmlspecialchars($sar['exam_number']) ?></code></td>
                                        <td style="text-align: center; font-weight: bold; color:#dc2626;"><?= htmlspecialchars($sar['score']) ?></td>
                                        <td style="text-align: center; font-weight: bold; color:#dc2626;"><?= number_format($pct, 1) ?>%</td>
                                        <td style="text-align: center;">
                                            <span class="badge" style="background:#fee2e2; color:#ef4444; font-weight:bold;">
                                                G<?= htmlspecialchars($sar['grade']) ?>
                                            </span>
                                        </td>
                                        <td style="font-size:0.8rem; color:#475569;">
                                            <?php if ($pct >= 33): ?>
                                                Borderline failure. Focused tutoring on weak topics can push them into passing credit bands.
                                            <?php else: ?>
                                                Critical deficit. Requires foundational concept review and remedial study support.
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION -->
                <?php if ($pagination_r['total_pages'] > 1): ?>
                    <div style="margin-top: 15px; display: flex; justify-content: center;">
                        <?= render_pagination_keyed($pagination_r, 'analytics.php', 'page_r') ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 4. MISSING SCORES TAB -->
            <div id="missingTab" class="tab-content">
                <div style="margin-bottom:16px;">
                    <h3>Missing Student Mark Submissions</h3>
                    <p style="font-size:0.85rem; color:var(--text-muted);">Registered candidates who have not had a mark entered for this exam subject</p>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Exam Number</th>
                                <th>Registered Class</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($missing_submissions)): ?>
                                <tr><td colspan="4" class="empty-state" style="color:#15803d; font-weight:600;">All registered candidate scores entered! No missing marks.</td></tr>
                            <?php else: ?>
                                <?php foreach ($missing_submissions as $ms): ?>
                                    <tr>
                                        <td style="font-weight: 600;"><?= htmlspecialchars($ms['student_name']) ?></td>
                                        <td><code><?= htmlspecialchars($ms['exam_number']) ?></code></td>
                                        <td><?= htmlspecialchars($ms['class']) ?></td>
                                        <td>
                                            <a href="enter_marks.php?exam_id=<?= $exam_id ?>&subject_id=<?= $subject_id ?>" class="btn btn-teal btn-small">Enter Mark</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION -->
                <?php if ($pagination_x['total_pages'] > 1): ?>
                    <div style="margin-top: 15px; display: flex; justify-content: center;">
                        <?= render_pagination_keyed($pagination_x, 'analytics.php', 'page_x') ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </div>
</div>
<?php include __DIR__ . '/../common/footer.php'; ?>

<script>
function switchTab(evt, tabId) {
    const contents = document.querySelectorAll('.tab-content');
    contents.forEach(content => content.classList.remove('active'));

    const buttons = document.querySelectorAll('.tab-btn');
    buttons.forEach(btn => btn.classList.remove('active'));

    document.getElementById(tabId).classList.add('active');
    evt.currentTarget.classList.add('active');
    
    localStorage.setItem('t_active_tab', tabId);
}

document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    let activeTab = localStorage.getItem('t_active_tab') || 'perfTab';
    
    if (urlParams.has('page_m')) activeTab = 'marksTab';
    if (urlParams.has('page_r')) activeTab = 'riskTab';
    if (urlParams.has('page_x')) activeTab = 'missingTab';
    
    const targetTabBtn = Array.from(document.querySelectorAll('.tab-btn')).find(btn => {
        return btn.getAttribute('onclick').includes(activeTab);
    });
    if (targetTabBtn) {
        targetTabBtn.click();
    }
});
</script>
</body>
</html>