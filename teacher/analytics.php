<?php
/* ════════════════════════════════════════════════════════════════
   teacher/analytics.php
   Teacher: views subject-specific and exam performance analytics
   ════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/teacher_init.php';
require_once __DIR__ . '/../common/grade_helper.php';

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
        SUM(CASE WHEN (m.score / ?) * 100 >= 50 THEN 1 ELSE 0 END) AS passed
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

// 4. Grade distribution in this subject (grades 1 to 9)
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

// 5. Subject Performance Comparison (all subjects in this exam for this school)
$stmt = $conn->prepare("
    SELECT s.subject_name, s.subject_code, AVG((m.score / es.total_marks) * 100) AS avg_pct
    FROM marks m
    JOIN subjects s ON m.subject_id = s.subject_id
    JOIN exam_subjects es ON m.exam_id = es.exam_id AND m.subject_id = es.subject_id
    JOIN students st ON m.student_id = st.student_id
    WHERE m.exam_id = ? AND st.school_id = ? AND m.status IN ('submitted', 'approved')
    GROUP BY s.subject_id, s.subject_name, s.subject_code
    ORDER BY avg_pct DESC
");
$stmt->bind_param("ii", $exam_id, $school_id);
$stmt->execute();
$subject_performance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 6. Historical Performance of this subject over years (for this school)
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

// 7. AI Trend and Prediction
$ai_insights = [];
$predicted_next = null;
$historical_values = array_column($historical, 'avg_pct');

if (count($historical_values) >= 2) {
    $growth = [];
    for ($i = 1; $i < count($historical_values); $i++) {
        $growth[] = $historical_values[$i] - $historical_values[$i-1];
    }
    $avg_growth = array_sum($growth) / count($growth);
    $predicted_next = round($historical_values[count($historical_values)-1] + $avg_growth, 1);

    if ($avg_growth > 1.5) {
        $ai_insights[] = "Performance in this subject is on an upward trajectory. Keep up the great work! 📈";
    } elseif ($avg_growth < -1.5) {
        $ai_insights[] = "Performance in this subject is declining. Focus interventions on weaker topics. ⚠️";
    } else {
        $ai_insights[] = "Performance remains stable. Look for opportunities to push middle-tier students higher.";
    }
    $ai_insights[] = "Predicted average percentage for the next exam: " . max(0, min($predicted_next, 100)) . "%";
} else {
    $ai_insights[] = "Historical data is limited. Compile results across multiple terms/years to generate predictive trends.";
}

// 8. Class student marks list for this subject
$stmt = $conn->prepare("
    SELECT st.name AS student_name, st.exam_number, m.score, m.grade, m.status
    FROM marks m
    JOIN students st ON m.student_id = st.student_id
    WHERE m.exam_id = ? AND m.subject_id = ? AND st.school_id = ?
    ORDER BY m.score DESC, st.name ASC
");
$stmt->bind_param("iii", $exam_id, $subject_id, $school_id);
$stmt->execute();
$student_marks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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
    </style>
</head>
<body>
<?php include __DIR__ . '/../common/header.php'; ?>
<div class="dashboard">
    <?php include __DIR__ . '/../common/sidebar.php'; ?>
    <div class="content">
        <div class="page-header">
            <div>
                <h2 class="page-title"><?= htmlspecialchars($meta['subject_name']) ?> Analytics</h2>
                <p class="page-subtitle"><?= htmlspecialchars($meta['exam_name']) ?> (Class: <?= htmlspecialchars($meta['class']) ?>)</p>
            </div>
            <a href="analytics.php" class="btn btn-secondary">Back to List</a>
        </div>

        <!-- 1. KPIs -->
        <div class="analytics-dashboard">
            <div class="stats-card">
                <h4>Total Students Tested</h4>
                <div class="stats-value"><?= $total_students ?></div>
            </div>
            <div class="stats-card">
                <h4>Average Percentage Score</h4>
                <div class="stats-value"><?= number_format($avg_percentage, 1) ?>%</div>
            </div>
            <div class="stats-card">
                <h4>Subject Pass Rate (Score >= 50%)</h4>
                <div class="stats-value"><?= $pass_rate ?>%</div>
            </div>
        </div>

        <div class="grid-two-col">
            <!-- 2. Grade Distribution Chart -->
            <div class="card">
                <div class="section-header">
                    <h3>Grade Distribution</h3>
                </div>
                <div class="chart-placeholder">
                    <?php 
                    $max_count = max(1, max(array_values($grade_dist)));
                    foreach ($grade_dist as $g => $cnt): 
                        $pct_height = ($cnt / $max_count) * 100;
                    ?>
                        <div class="chart-bar-container">
                            <span class="chart-val"><?= $cnt ?></span>
                            <div class="chart-bar" style="height: <?= $pct_height ?>%; background: <?= $g <= 6 ? '#22c55e' : ($g <= 7 ? '#eab308' : '#ef4444') ?>;"></div>
                            <span class="chart-label">G<?= $g ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p style="font-size: 0.8rem; color: var(--text-muted); text-align: center; margin-top: 10px;">
                    Grades 1-6 represent passing grades, 7-8 are weak passes, and 9 is a fail.
                </p>
            </div>

            <!-- 3. AI Insights -->
            <div class="card" style="background: #faf5ff; border-color: #e9d5ff;">
                <div class="section-header">
                    <h3 style="color: #6b21a8;">✨ Academic AI Insights</h3>
                </div>
                <div style="padding: 10px 0;">
                    <ul style="margin: 0; padding-left: 20px; font-size: 0.95rem; line-height: 1.6; color: #581c87;">
                        <?php foreach ($ai_insights as $insight): ?>
                            <li style="margin-bottom: 12px;"><?= htmlspecialchars($insight) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="grid-two-col">
            <!-- 4. Subject Comparison in this Exam -->
            <div class="card">
                <div class="section-header">
                    <h3>Subject Performance Comparison</h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th style="text-align: right;">Average Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subject_performance as $sp): ?>
                            <tr style="<?= $sp['subject_code'] === $meta['subject_code'] ? 'background: #f1f5f9; font-weight: bold;' : '' ?>">
                                <td>
                                    <?= htmlspecialchars($sp['subject_name']) ?> (<?= htmlspecialchars($sp['subject_code']) ?>)
                                    <?php if ($sp['subject_code'] === $meta['subject_code']): ?>
                                        <span class="badge badge-info">This Subject</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;"><?= number_format($sp['avg_pct'], 1) ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 5. Historical Trends -->
            <div class="card">
                <div class="section-header">
                    <h3>Historical Performance Trend</h3>
                </div>
                <?php if (empty($historical)): ?>
                    <p class="empty-state">No historical records available for this subject.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Year</th>
                                    <th style="text-align: right;">Average Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historical as $h): ?>
                                <tr>
                                    <td>Year <?= htmlspecialchars($h['year']) ?></td>
                                    <td style="text-align: right; font-weight: bold;"><?= number_format($h['avg_pct'], 1) ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 6. Class student list for this subject -->
        <div class="card">
            <div class="section-header">
                <h3>Student Marks List</h3>
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
                            <tr>
                                <td colspan="6" class="empty-state">No marks found for this subject and school.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($student_marks as $m): ?>
                            <?php 
                            $pct = $total_marks > 0 ? ($m['score'] / $total_marks) * 100 : 0;
                            ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($m['student_name']) ?></td>
                                <td><code><?= htmlspecialchars($m['exam_number']) ?></code></td>
                                <td style="text-align: center; font-weight: bold;"><?= htmlspecialchars($m['score']) ?></td>
                                <td style="text-align: center;"><?= number_format($pct, 1) ?>%</td>
                                <td style="text-align: center;">
                                    <span class="grade-badge" style="background: var(--border-color); color: var(--text-color); font-weight: 700;">
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
        </div>

    </div>
</div>
<?php include __DIR__ . '/../common/footer.php'; ?>
</body>
</html>