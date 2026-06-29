<?php
/* ════════════════════════════════════════════════════════════════
   admin/reports/export_pdf.php
   EDM/Admin: export reports to print-friendly formats
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php"); exit();
}

$conn = get_db_connection();
$all_exams = $conn->query("SELECT exam_id, exam_name FROM exams ORDER BY exam_name")->fetch_all(MYSQLI_ASSOC);
$all_schools = $conn->query("SELECT school_id, school_name FROM schools WHERE status='active' ORDER BY school_name")->fetch_all(MYSQLI_ASSOC);
$conn->close();

$module_css = 'admin';
include __DIR__ . '/../../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Export Reports | NED-SEMS</title>
</head>
<body>
<?php include '../../common/header.php'; ?>
<div class="dashboard">
    <?php include '../../common/sidebar.php'; ?>
    <div class="content">

        <!-- Header -->
        <div class="page-header">
            <div>
                <h2 class="page-title">Export & Print Reports</h2>
                <p class="page-subtitle">Generate print-friendly and PDF formats of various administrative dashboards and academic performance logs</p>
            </div>
            <a href="../dashboard.php" class="btn btn-secondary">Back</a>
        </div>

        <div class="card" style="max-width: 600px; margin: 0 auto;">
            <div class="section-header">
                <h3>Select Report for Printing / PDF Export</h3>
            </div>
            <div style="padding: 15px 0;">
                <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 20px;">
                    Our reports are designed with responsive print media stylesheets. Select the desired report below, and click <strong>Generate & Print</strong>. The system will open the print layout and trigger your system's print dialog, from which you can choose "Save as PDF".
                </p>
                
                <form id="exportForm" action="" method="GET" target="_blank">
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label>Report Type <span style="color:var(--danger-color)">*</span></label>
                        <select id="report_type" required onchange="updateFormAction()" style="width: 100%;">
                            <option value="">— Select Report —</option>
                            <option value="school">School Performance Standings</option>
                            <option value="ranking">Student Rankings by Exam</option>
                            <option value="district">Division / District Performance</option>
                            <option value="anomalies">AI Academic Anomalies Log</option>
                        </select>
                    </div>

                    <div class="form-group" id="exam_selector" style="margin-bottom: 16px; display: none;">
                        <label>Select Exam <span style="color:var(--danger-color)">*</span></label>
                        <select name="exam_id" id="exam_id" style="width: 100%;">
                            <option value="">— Select Exam —</option>
                            <?php foreach ($all_exams as $ex): ?>
                                <option value="<?= $ex['exam_id'] ?>"><?= htmlspecialchars($ex['exam_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="school_selector" style="margin-bottom: 20px; display: none;">
                        <label>Select School</label>
                        <select name="school_id" id="school_id" style="width: 100%;">
                            <option value="">— All Schools —</option>
                            <?php foreach ($all_schools as $sch): ?>
                                <option value="<?= $sch['school_id'] ?>"><?= htmlspecialchars($sch['school_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="button" onclick="handleGenerate()" class="btn btn-dark" style="width: 100%;">
                        Generate & Print
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<script>
function updateFormAction() {
    const type = document.getElementById('report_type').value;
    const examSelect = document.getElementById('exam_selector');
    const schoolSelect = document.getElementById('school_selector');
    
    examSelect.style.display = 'none';
    schoolSelect.style.display = 'none';
    
    if (type === 'ranking') {
        examSelect.style.display = 'block';
        schoolSelect.style.display = 'block';
    } else if (type === 'district') {
        examSelect.style.display = 'block';
    }
}

function handleGenerate() {
    const type = document.getElementById('report_type').value;
    if (!type) {
        alert('Please select a report type.');
        return;
    }
    
    let url = '';
    if (type === 'school') {
        url = 'school_report.php';
    } else if (type === 'ranking') {
        const examId = document.getElementById('exam_id').value;
        if (!examId) {
            alert('Please select an exam for the student rankings report.');
            return;
        }
        const schoolId = document.getElementById('school_id').value;
        url = 'ranking.php?exam_id=' + examId + (schoolId ? '&school_id=' + schoolId : '');
    } else if (type === 'district') {
        const examId = document.getElementById('exam_id').value;
        if (!examId) {
            alert('Please select an exam.');
            return;
        }
        url = 'district_report.php?exam_id=' + examId;
    } else if (type === 'anomalies') {
        url = 'anomalies.php';
    }
    
    // Open in a new window and trigger printing once loaded
    const win = window.open(url, '_blank');
    if (win) {
        win.onload = function() {
            win.print();
        };
    }
}
</script>

<?php include '../../common/footer.php'; ?>
</body>
</html>
