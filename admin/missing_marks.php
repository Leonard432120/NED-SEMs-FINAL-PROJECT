<?php
/* ════════════════════════════════════════════════════════════════
   admin/missing_marks.php
   EDM/Admin: identifies missing or incomplete subject marks submissions
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php"); exit();
}

$conn = get_db_connection();

/* ── FILTERS ── */
$filter_exam_id   = (int)($_GET['exam_id'] ?? 0);
$filter_school_id = (int)($_GET['school_id'] ?? 0);

/* ── FETCH MASTER DROPDOWNS ── */
$all_exams = $conn->query("SELECT exam_id, exam_name, class FROM exams WHERE status IN ('draft', 'active') ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$all_schools = $conn->query("SELECT school_id, school_name FROM schools WHERE status='active' ORDER BY school_name")->fetch_all(MYSQLI_ASSOC);

/* ── DETECT MISSING SUBMISSIONS ── */
// Fetch all exams that are not completed (active or draft)
$exam_filter_sql = $filter_exam_id > 0 ? "AND e.exam_id = {$filter_exam_id}" : "";
$exams = $conn->query("
    SELECT e.exam_id, e.exam_name, e.class, e.year
    FROM exams e
    WHERE e.status IN ('draft', 'active') {$exam_filter_sql}
    ORDER BY e.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$missing_reports = [];
$total_missing_count = 0;
$total_incomplete_count = 0;

foreach ($exams as $ex) {
    $eid = (int)$ex['exam_id'];
    $class = $ex['class']; // e.g. Form 2, Form 4
    
    // Fetch subjects assigned to this exam
    $exam_subjects = $conn->query("
        SELECT es.subject_id, s.subject_name, s.subject_code, es.total_marks
        FROM exam_subjects es
        JOIN subjects s ON es.subject_id = s.subject_id
        WHERE es.exam_id = {$eid} AND es.status = 'active'
    ")->fetch_all(MYSQLI_ASSOC);
    
    if (empty($exam_subjects)) continue;
    
    // Fetch active schools and student counts in this class
    $school_filter_sql = $filter_school_id > 0 ? "AND school_id = {$filter_school_id}" : "";
    $schools_with_students = $conn->query("
        SELECT school_id, COUNT(*) AS student_count
        FROM students
        WHERE class = '{$conn->real_escape_string($class)}' AND status = 'active' {$school_filter_sql}
        GROUP BY school_id
    ")->fetch_all(MYSQLI_ASSOC);
    
    foreach ($schools_with_students as $sch_info) {
        $sch_id = (int)$sch_info['school_id'];
        $student_count = (int)$sch_info['student_count'];
        if ($student_count <= 0) continue;
        
        // Fetch school name
        $school_name = $conn->query("SELECT school_name FROM schools WHERE school_id = {$sch_id}")->fetch_assoc()['school_name'] ?? 'Unknown School';
        
        foreach ($exam_subjects as $sub) {
            $sid = (int)$sub['subject_id'];
            
            // Check entered marks for this school, exam, subject
            $stmt = $conn->prepare("
                SELECT COUNT(m.mark_id) AS marks_count, MAX(m.status) AS marks_status
                FROM marks m
                JOIN students st ON m.student_id = st.student_id
                WHERE m.exam_id = ? AND m.subject_id = ? AND st.school_id = ?
            ");
            $stmt->bind_param("iii", $eid, $sid, $sch_id);
            $stmt->execute();
            $marks_info = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            $marks_count  = (int)($marks_info['marks_count'] ?? 0);
            $marks_status = $marks_info['marks_status'] ?: 'not_entered';
            
            // Determine if missing or incomplete
            if ($marks_count === 0) {
                $total_missing_count++;
                $missing_reports[] = [
                    'exam_name'     => $ex['exam_name'],
                    'class'         => $class,
                    'school_name'   => $school_name,
                    'subject_name'  => $sub['subject_name'],
                    'subject_code'  => $sub['subject_code'],
                    'expected'      => $student_count,
                    'actual'        => 0,
                    'status'        => 'Not Entered',
                    'status_class'  => 'danger'
                ];
            } elseif ($marks_count < $student_count) {
                $total_incomplete_count++;
                $missing_reports[] = [
                    'exam_name'     => $ex['exam_name'],
                    'class'         => $class,
                    'school_name'   => $school_name,
                    'subject_name'  => $sub['subject_name'],
                    'subject_code'  => $sub['subject_code'],
                    'expected'      => $student_count,
                    'actual'        => $marks_count,
                    'status'        => 'Incomplete (' . ucfirst($marks_status) . ')',
                    'status_class'  => 'warning'
                ];
            }
        }
    }
}

$conn->close();
$module_css = 'admin';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Missing Mark Submissions | NED-SEMS</title>
</head>
<body>
<?php include '../common/header.php'; ?>
<div class="dashboard">
    <?php include '../common/sidebar.php'; ?>
    <div class="content">

        <!-- Header -->
        <div class="page-header">
            <div>
                <h2 class="page-title">Missing & Incomplete Marks</h2>
                <p class="page-subtitle">Identify subject marks that have not been entered or fully submitted by schools and teachers</p>
            </div>
            <a href="dashboard.php" class="btn btn-secondary">Back</a>
        </div>

        <!-- Filter Bar -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="section-header"><h3>Filter Monitor</h3></div>
            <form method="GET">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Exam</label>
                        <select name="exam_id">
                            <option value="">— All Active/Draft Exams —</option>
                            <?php foreach ($all_exams as $ex): ?>
                                <option value="<?= $ex['exam_id'] ?>" <?= $filter_exam_id === (int)$ex['exam_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ex['exam_name']) ?> (<?= htmlspecialchars($ex['class']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>School</label>
                        <select name="school_id">
                            <option value="">— All Schools —</option>
                            <?php foreach ($all_schools as $sch): ?>
                                <option value="<?= $sch['school_id'] ?>" <?= $filter_school_id === (int)$sch['school_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sch['school_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-actions" style="margin-top: 16px;">
                    <button type="submit" class="btn btn-dark">Scan Submissions</button>
                    <a href="missing_marks.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>

        <!-- KPIs -->
        <div class="kpi-grid" style="margin-bottom: 24px;">
            <div class="kpi-card kpi-card--danger">
                <div class="kpi-card__body">
                    <span class="kpi-card__label">Not Started Submissions</span>
                    <span class="kpi-card__value"><?= $total_missing_count ?></span>
                </div>
            </div>
            <div class="kpi-card kpi-card--warning">
                <div class="kpi-card__body">
                    <span class="kpi-card__label">Incomplete Submissions</span>
                    <span class="kpi-card__value"><?= $total_incomplete_count ?></span>
                </div>
            </div>
            <div class="kpi-card kpi-card--info">
                <div class="kpi-card__body">
                    <span class="kpi-card__label">Scan Status</span>
                    <span class="kpi-card__value">Active</span>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="card">
            <div class="section-header">
                <h3>Detected Gaps / Missing Subject Marks</h3>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Exam Name</th>
                            <th>Class</th>
                            <th>School Name</th>
                            <th>Subject</th>
                            <th style="text-align: center;">Students Expected</th>
                            <th style="text-align: center;">Marks Entered</th>
                            <th>Deficit</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($missing_reports)): ?>
                            <tr>
                                <td colspan="8" class="empty-state" style="color: var(--success-color); font-weight: 600; padding: 40px 20px;">
                                    🎉 Excellent! No missing or incomplete subject marks detected for the selected filters.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($missing_reports as $rep): ?>
                            <?php 
                            $deficit = $rep['expected'] - $rep['actual'];
                            ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($rep['exam_name']) ?></td>
                                <td><?= htmlspecialchars($rep['class']) ?></td>
                                <td style="font-weight: 500;"><?= htmlspecialchars($rep['school_name']) ?></td>
                                <td>
                                    <span style="font-weight: 600; color: var(--primary-dark);">
                                        <?= htmlspecialchars($rep['subject_name']) ?>
                                    </span> 
                                    (<?= htmlspecialchars($rep['subject_code']) ?>)
                                </td>
                                <td style="text-align: center;"><?= $rep['expected'] ?></td>
                                <td style="text-align: center; font-weight: bold;"><?= $rep['actual'] ?></td>
                                <td style="color: var(--danger-color); font-weight: bold;">
                                    -<?= $deficit ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $rep['status_class'] ?>">
                                        <?= htmlspecialchars($rep['status']) ?>
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
<?php include '../common/footer.php'; ?>
</body>
</html>
