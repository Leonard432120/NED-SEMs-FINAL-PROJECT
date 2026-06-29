<?php
/* ════════════════════════════════════════════════════════════════
   admin/compile_results.php
   EDM/Admin: compiles approved marks → results table
   Calculates total, average, grade, class position
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once '../config/db.php';
require_once '../common/grade_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php"); exit();
}

$conn    = get_db_connection();
$adm_id  = (int)$_SESSION['user_id'];
$message = ''; $msg_type = '';

$exam_id = (int)($_GET['exam_id'] ?? 0);

/* ── COMPILE ACTION ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['compile'])) {
    $eid  = (int)$_POST['exam_id'];
    $term = trim($_POST['term'] ?? 'Term 1');
    $year = (int)($_POST['year'] ?? date('Y'));

    $ex = $conn->query("SELECT class FROM exams WHERE exam_id = {$eid}")->fetch_assoc();
    $class = $ex['class'] ?? '';

    $rows = $conn->query("
        SELECT m.student_id,
               COUNT(DISTINCT m.subject_id) AS total_subjects,
               SUM(m.score)                 AS total_score,
               AVG(m.score)                 AS average_score
        FROM marks m
        WHERE m.exam_id = {$eid} AND m.status = 'approved'
        GROUP BY m.student_id
    ")->fetch_all(MYSQLI_ASSOC);

    if (empty($rows)) {
        $message = 'No approved marks found for this exam. Ensure Headteacher has approved all subjects.';
        $msg_type = 'error';
    } else {
        $compiled = [];
        foreach ($rows as $r) {
            $avg = round((float)$r['average_score'], 2);
            $compiled[] = [
                'student_id'     => (int)$r['student_id'],
                'total_subjects' => (int)$r['total_subjects'],
                'total_score'    => round((float)$r['total_score'], 2),
                'average_score'  => $avg,
                'grade'          => calcGrade($avg),
            ];
        }
        usort($compiled, fn($a, $b) => $b['average_score'] <=> $a['average_score']);

        $now = date('Y-m-d H:i:s');
        $inserted = 0; $updated = 0;

        foreach ($compiled as $pos => $c) {
            $position = $pos + 1;
            $chk = $conn->prepare("SELECT result_id FROM results WHERE exam_id=? AND student_id=?");
            $chk->bind_param("ii", $eid, $c['student_id']);
            $chk->execute();
            $existing = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($existing) {
                $upd = $conn->prepare("UPDATE results SET term=?,year=?,class=?,total_subjects=?,total_score=?,average_score=?,grade=?,position_in_class=?,compiled_by=?,compiled_at=?,status='draft' WHERE result_id=?");
                $upd->bind_param("sisiddssssi", $term,$year,$class,$c['total_subjects'],$c['total_score'],$c['average_score'],$c['grade'],$position,$adm_id,$now,$existing['result_id']);
                $upd->execute(); $upd->close(); $updated++;
            } else {
                $ins = $conn->prepare("INSERT INTO results (student_id,exam_id,term,year,class,total_subjects,total_score,average_score,grade,position_in_class,compiled_by,compiled_at,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'draft')");
                $ins->bind_param("iisisiddsiss", $c['student_id'],$eid,$term,$year,$class,$c['total_subjects'],$c['total_score'],$c['average_score'],$c['grade'],$position,$adm_id,$now);
                $ins->execute(); $ins->close(); $inserted++;
            }
        }

        $log = $conn->prepare("INSERT INTO result_workflow_logs (result_id,action,performed_by,role,notes,created_at) VALUES (0,'compile_results',?,'ADMIN',?,NOW())");
        $notes_log = "Compiled exam ID {$eid}: {$inserted} new, {$updated} updated";
        $log->bind_param("is", $adm_id, $notes_log);
        $log->execute(); $log->close();

        $message = "Results compiled: {$inserted} new, {$updated} updated. Review and publish below.";
        $msg_type = 'success';
        header("Location: compile_results.php?exam_id={$eid}&msg=".urlencode($message)."&mtype=success");
        exit();
    }
}

/* ── PUBLISH ACTION ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish'])) {
    $eid = (int)$_POST['exam_id'];
    $conn->query("UPDATE results SET status='published', locked=1 WHERE exam_id={$eid} AND status='draft'");
    $message = 'Results published successfully.'; $msg_type = 'success';
    header("Location: compile_results.php?exam_id={$eid}&msg=".urlencode($message)."&mtype=success");
    exit();
}

if (!empty($_GET['msg'])) { $message = htmlspecialchars($_GET['msg']); $msg_type = $_GET['mtype'] ?? 'success'; }

$all_exams = $conn->query("SELECT exam_id, exam_name, class FROM exams ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

$results_preview = []; $exam_info = null; $approved_marks = 0; $publish_ready = false;

if ($exam_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM exams WHERE exam_id=?");
    $stmt->bind_param("i", $exam_id); $stmt->execute();
    $exam_info = $stmt->get_result()->fetch_assoc(); $stmt->close();

    $results_preview = $conn->query("
        SELECT r.result_id,r.total_subjects,r.total_score,r.average_score,r.grade,
               r.position_in_class,r.status,r.locked,
               st.name AS student_name,st.exam_number,st.class AS student_class,
               sc.school_name
        FROM results r
        JOIN students st ON st.student_id=r.student_id
        LEFT JOIN schools sc ON sc.school_id=st.school_id
        WHERE r.exam_id={$exam_id}
        ORDER BY r.position_in_class ASC
    ")->fetch_all(MYSQLI_ASSOC);

    $approved_marks = (int)$conn->query("SELECT COUNT(*) AS c FROM marks WHERE exam_id={$exam_id} AND status='approved'")->fetch_assoc()['c'];
    $publish_ready  = !empty($results_preview) &&
                      count(array_filter($results_preview, fn($r) => $r['status'] === 'published')) === 0;
}

$conn->close();
$module_css = 'admin';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Compile Results | NED-SEMS</title>
</head>
<body>
<?php include '../common/header.php'; ?>
<div class="dashboard">
<?php include '../common/sidebar.php'; ?>
<div class="content">

<div class="page-header">
    <div>
        <h2 class="page-title">Compile Results</h2>
        <p class="page-subtitle">Compile approved marks into student results and publish</p>
    </div>
    <a href="results.php" class="btn btn-secondary">View All Results</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $msg_type ?>"><?= $message ?></div>
<?php endif; ?>

<div class="card">
    <div class="section-header"><h3>Step 1 — Select Exam and Compile</h3></div>
    <form method="POST">
        <input type="hidden" name="compile" value="1">
        <div class="form-grid">
            <div class="form-group">
                <label>Exam <span style="color:var(--danger-color)">*</span></label>
                <select name="exam_id" required onchange="window.location='compile_results.php?exam_id='+this.value">
                    <option value="">— Select Exam —</option>
                    <?php foreach ($all_exams as $ex): ?>
                        <option value="<?= $ex['exam_id'] ?>" <?= $exam_id == $ex['exam_id'] ? 'selected':'' ?>>
                            <?= htmlspecialchars($ex['exam_name']) ?> (<?= $ex['class'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Term <span style="color:var(--danger-color)">*</span></label>
                <select name="term" required>
                    <option>Term 1</option><option>Term 2</option><option>Term 3</option>
                </select>
            </div>
            <div class="form-group">
                <label>Year <span style="color:var(--danger-color)">*</span></label>
                <input type="number" name="year" value="<?= date('Y') ?>" min="2020" max="2040" required>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-dark"
                    onclick="return confirm('Compile results for this exam? Totals, averages, grades and positions will be calculated.')">
                Compile Results
            </button>
        </div>
    </form>
</div>

<?php if ($exam_id > 0): ?>
<div class="alert alert-<?= $approved_marks > 0 ? 'success' : 'error' ?>">
    <?= $approved_marks > 0
        ? "<strong>{$approved_marks}</strong> approved mark entries found and ready for compilation."
        : 'No approved marks found. Headteacher must approve marks before compilation.' ?>
</div>
<?php endif; ?>

<?php if (!empty($results_preview)): ?>
<div class="card">
    <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h3>Step 2 — Preview: <?= htmlspecialchars($exam_info['exam_name'] ?? '') ?></h3>
        <?php if ($publish_ready): ?>
        <form method="POST" style="margin:0;">
            <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
            <button name="publish" value="1" class="btn btn-dark"
                    onclick="return confirm('Publish results? This locks them and makes them visible to all roles.')">
                Publish Results
            </button>
        </form>
        <?php else: ?>
        <span class="badge badge-success">Published</span>
        <?php endif; ?>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Position</th><th>Student</th><th>Exam No.</th>
                    <th>School</th><th>Class</th><th>Subjects</th>
                    <th>Total</th><th>Average %</th><th>Grade</th>
                    <th>Status</th><th>Report Card</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($results_preview as $r): ?>
            <tr>
                <td><strong>#<?= $r['position_in_class'] ?></strong></td>
                <td><?= htmlspecialchars($r['student_name']) ?></td>
                <td><?= htmlspecialchars($r['exam_number']) ?></td>
                <td><?= htmlspecialchars($r['school_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($r['student_class']) ?></td>
                <td><?= $r['total_subjects'] ?></td>
                <td><?= number_format((float)$r['total_score'], 1) ?></td>
                <td><strong><?= number_format((float)$r['average_score'], 1) ?>%</strong></td>
                <td>
                    <span class="badge badge-<?= gradeColor($r['grade'] ?? '9') ?>">
                        Grade <?= $r['grade'] ?? '—' ?> &mdash; <?= gradeLabel($r['grade'] ?? '9') ?>
                    </span>
                </td>
                <td>
                    <span class="badge badge-<?= $r['status'] === 'published' ? 'success' : 'warning' ?>">
                        <?= ucfirst($r['status']) ?>
                    </span>
                </td>
                <td>
                    <a href="student_report_card.php?result_id=<?= $r['result_id'] ?>" target="_blank"
                       class="btn btn-secondary btn-sm">View</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

</div></div>
<?php include '../common/footer.php'; ?>
</body>
</html>