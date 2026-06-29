<?php
session_start();
include '../config/db.php';

/* ================= AUTH CHECK ================= */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = get_db_connection();

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$exam_id    = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

/* ================= STUDENT DETAILS ================= */
$student = $conn->query("
    SELECT *
    FROM students
    WHERE student_id = $student_id
")->fetch_assoc();

/* ================= SUBJECT PERFORMANCE ================= */
$subjects = $conn->query("
    SELECT
        s.subject_name,
        m.score,
        m.grade
    FROM marks m
    INNER JOIN subjects s
        ON m.subject_id = s.subject_id
    WHERE m.student_id = $student_id
    AND m.exam_id = $exam_id
    ORDER BY s.subject_name ASC
");

/* ================= RESULT SUMMARY ================= */
$result = $conn->query("
    SELECT *
    FROM results
    WHERE student_id = $student_id
    LIMIT 1
")->fetch_assoc();

/* ================= BUILD SUBJECTS ARRAY FOR JS ================= */
$subjects_array = [];
if ($subjects && $subjects->num_rows > 0) {
    while ($s = $subjects->fetch_assoc()) {
        $subjects_array[] = $s;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Report Card</title>

    <?php include '../common/head_assets.php'; ?>

    <style>

        /* ===== REPORT CARD STYLES ===== */

        .rc-header {
            background: var(--card-bg, #fff);
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 14px;
            padding: 22px 28px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .rc-header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .rc-icon-box {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            background: #e6f1fb;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .rc-icon-box i {
            font-size: 24px;
            color: #185fa5;
        }

        .rc-header-text h1 {
            margin: 0 0 3px;
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary, #0f172a);
        }

        .rc-header-text p {
            margin: 0;
            font-size: 13px;
            color: var(--text-secondary, #64748b);
        }

        .rc-logo {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary, #64748b);
            text-align: right;
            line-height: 1.3;
        }

        .rc-logo span {
            display: block;
            font-size: 22px;
            font-weight: 600;
            color: #185fa5;
        }

        /* ===== INFO GRID ===== */

        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-top: 4px;
        }

        .info-item {
            background: #f8fafc;
            border-radius: 10px;
            padding: 12px 14px;
            border: 1px solid #e2e8f0;
        }

        .info-item .info-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #64748b;
            margin-bottom: 5px;
        }

        .info-item .info-val {
            font-size: 15px;
            font-weight: 600;
            color: #0f172a;
        }

        /* ===== SUBJECT TABLE ===== */

        .rc-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .rc-table thead th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #64748b;
            font-weight: 500;
            padding: 0 12px 10px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .rc-table thead th:last-child {
            text-align: center;
        }

        .rc-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
        }

        .rc-table tbody tr:last-child {
            border-bottom: none;
        }

        .rc-table tbody td {
            padding: 11px 12px;
            color: #0f172a;
            vertical-align: middle;
        }

        .rc-table .row-num {
            color: #94a3b8;
            font-size: 12px;
        }

        /* ===== SCORE BAR ===== */

        .score-bar-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .score-bar-bg {
            flex: 1;
            height: 6px;
            background: #f1f5f9;
            border-radius: 3px;
            overflow: hidden;
        }

        .score-bar-fill {
            height: 100%;
            border-radius: 3px;
        }

        .score-val {
            font-size: 13px;
            font-weight: 500;
            min-width: 40px;
            text-align: right;
            color: #0f172a;
        }

        /* ===== GRADE BADGES ===== */

        .grade-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
        }

        .grade-A { background: #eaf3de; color: #3b6d11; }
        .grade-B { background: #e6f1fb; color: #185fa5; }
        .grade-C { background: #faeeda; color: #854f0b; }
        .grade-D { background: #faece7; color: #993c1d; }
        .grade-F { background: #fcebeb; color: #a32d2d; }

        /* ===== SUMMARY BOXES ===== */

        .report-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
        }

        .summary-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 14px;
            text-align: center;
        }

        .summary-box h4 {
            margin: 0 0 6px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #64748b;
            font-weight: 500;
        }

        .summary-box .value {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
        }

        .summary-box.highlight {
            background: #1e3a8a;
            border-color: #1e3a8a;
        }

        .summary-box.highlight h4 {
            color: rgba(255,255,255,.65);
        }

        .summary-box.highlight .value {
            color: #fff;
        }

        /* ===== PASS BANNER ===== */

        .pass-banner {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #eaf3de;
            border-radius: 10px;
            padding: 10px 14px;
            color: #3b6d11;
            font-size: 13px;
            font-weight: 500;
            margin-top: 14px;
        }

        .fail-banner {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fcebeb;
            border-radius: 10px;
            padding: 10px 14px;
            color: #a32d2d;
            font-size: 13px;
            font-weight: 500;
            margin-top: 14px;
        }

        /* ===== ACTION BUTTONS ===== */

        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* ===== PRINT ===== */

        @media print {
            .sidebar,
            .header,
            .footer,
            .action-buttons {
                display: none !important;
            }

            .content {
                margin: 0 !important;
                padding: 0 !important;
            }

            .card {
                box-shadow: none !important;
                border: 1px solid #e2e8f0 !important;
            }

            .rc-header {
                border: 1px solid #e2e8f0 !important;
            }
        }

    </style>

</head>

<body>

<?php include '../common/header.php'; ?>

<div class="dashboard">

    <?php include '../common/sidebar.php'; ?>

    <div class="content">

        <!-- ===== REPORT HEADER ===== -->
        <div class="rc-header">

            <div class="rc-header-left">
                <div class="rc-icon-box">
                    <i class="ti ti-school"></i>
                </div>
                <div class="rc-header-text">
                    <h1>Student Report Card</h1>
                    <p>Official academic performance report &mdash; NED-SEMS</p>
                </div>
            </div>

            <div class="rc-logo">
                <span>NED</span>SEMS
            </div>

        </div>

        <!-- ===== STUDENT INFORMATION ===== -->
        <div class="card">

            <div class="section-title">
                <h3>Student Information</h3>
            </div>

            <div class="report-grid">

                <div class="info-item">
                    <div class="info-label">Student Name</div>
                    <div class="info-val">
                        <?= htmlspecialchars($student['name'] ?? 'N/A') ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Exam Number</div>
                    <div class="info-val">
                        <?= htmlspecialchars($student['exam_number'] ?? 'N/A') ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Class</div>
                    <div class="info-val">
                        <?= htmlspecialchars($student['class'] ?? 'N/A') ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Examination</div>
                    <div class="info-val">
                        <?= htmlspecialchars($exam_id) ?>
                    </div>
                </div>

            </div>

        </div>

        <!-- ===== SUBJECT PERFORMANCE ===== -->
        <div class="card">

            <div class="section-title">
                <h3>Subject Performance</h3>
            </div>

            <div class="table-wrapper">
                <table class="rc-table">

                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th>Subject</th>
                            <th>Score</th>
                            <th style="text-align:center">Grade</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php
                    $counter = 1;
                    if (!empty($subjects_array)):
                        foreach ($subjects_array as $s):
                            $score = (int)$s['score'];
                            $grade = $s['grade'] ?? '-';

                            if ($score >= 80)      $bar_color = '#639922';
                            elseif ($score >= 65)  $bar_color = '#378add';
                            elseif ($score >= 50)  $bar_color = '#ba7517';
                            else                   $bar_color = '#e24b4a';

                            $grade_upper = strtoupper(substr($grade, 0, 1));
                            $badge_class = in_array($grade_upper, ['A','B','C','D'])
                                ? 'grade-' . $grade_upper
                                : 'grade-F';
                    ?>
                        <tr>
                            <td class="row-num"><?= $counter++ ?></td>

                            <td><?= htmlspecialchars($s['subject_name']) ?></td>

                            <td>
                                <div class="score-bar-wrap">
                                    <div class="score-bar-bg">
                                        <div class="score-bar-fill"
                                             style="width:<?= $score ?>%; background:<?= $bar_color ?>;"></div>
                                    </div>
                                    <span class="score-val"><?= $score ?>%</span>
                                </div>
                            </td>

                            <td style="text-align:center">
                                <span class="grade-badge <?= $badge_class ?>">
                                    <?= htmlspecialchars($grade) ?>
                                </span>
                            </td>
                        </tr>
                    <?php
                        endforeach;
                    else:
                    ?>
                        <tr>
                            <td colspan="4" class="empty-state">
                                No subject marks available.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>

                </table>
            </div>

        </div>

        <!-- ===== OVERALL PERFORMANCE ===== -->
        <div class="card">

            <div class="section-title">
                <h3>Overall Performance Summary</h3>
            </div>

            <div class="report-summary">

                <div class="summary-box">
                    <h4>Total Score</h4>
                    <div class="value">
                        <?= $result['total_score'] ?? 0 ?>
                    </div>
                </div>

                <div class="summary-box">
                    <h4>Average Score</h4>
                    <div class="value">
                        <?= isset($result['average_score'])
                            ? number_format($result['average_score'], 1) . '%'
                            : '0%' ?>
                    </div>
                </div>

                <div class="summary-box highlight">
                    <h4>Overall Grade</h4>
                    <div class="value">
                        <?= htmlspecialchars($result['grade'] ?? '-') ?>
                    </div>
                </div>

                <div class="summary-box">
                    <h4>Class Position</h4>
                    <div class="value">
                        <?= htmlspecialchars($result['position_in_class'] ?? '-') ?>
                    </div>
                </div>

            </div>

            <?php
            $passed = isset($result['average_score']) && $result['average_score'] >= 50;
            if ($passed): ?>
                <div class="pass-banner">
                    <i class="ti ti-circle-check"></i>
                    Student has passed this examination.
                </div>
            <?php else: ?>
                <div class="fail-banner">
                    <i class="ti ti-circle-x"></i>
                    Student has not passed this examination.
                </div>
            <?php endif; ?>

        </div>

        <!-- ===== ACTIONS ===== -->
        <div class="card">

            <div class="action-buttons">

                <a href="compile_results.php" class="btn btn-secondary">
                    <i class="ti ti-arrow-left"></i> Back to Results
                </a>

                <button onclick="window.print()" class="btn btn-primary">
                    <i class="ti ti-printer"></i> Print Report Card
                </button>

            </div>

        </div>

    </div>

</div>

<?php include '../common/footer.php'; ?>

</body>
</html>