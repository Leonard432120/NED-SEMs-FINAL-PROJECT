<?php
/*
 * examination_officer/subject_slip.php
 * Printable subject registration slip for a single student.
 * Accessible to: examination_officer, headteacher, admin
 */
session_start();
require_once '../config/db.php';

$allowed_roles = ['examination_officer', 'headteacher', 'admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: ../login.php"); exit();
}

$conn       = get_db_connection();
$student_id = (int)($_GET['student_id'] ?? 0);

if ($student_id <= 0) {
    echo '<p style="font-family:sans-serif;padding:40px;color:#dc2626;">No student specified.</p>'; exit();
}

/* Student record */
$stmt = $conn->prepare("
    SELECT s.student_id, s.name, s.exam_number, s.class, s.status,
           sc.school_name, sc.district
    FROM students s
    LEFT JOIN schools sc ON sc.school_id = s.school_id
    WHERE s.student_id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    echo '<p style="font-family:sans-serif;padding:40px;color:#dc2626;">Student not found.</p>'; exit();
}

/* Registered subjects */
$sq = $conn->prepare("
    SELECT sub.subject_name, sub.subject_code, sub.category, sub.paper_type
    FROM student_subjects ss
    JOIN subjects sub ON sub.subject_id = ss.subject_id
    WHERE ss.student_id = ?
    ORDER BY sub.subject_name ASC
");
$sq->bind_param("i", $student_id);
$sq->execute();
$subjects = $sq->get_result()->fetch_all(MYSQLI_ASSOC);
$sq->close();
$conn->close();

$subj_count    = count($subjects);
$generated_at  = date('d F Y, H:i');
$student_name  = htmlspecialchars($student['name']);
$exam_number   = htmlspecialchars($student['exam_number']);
$student_class = htmlspecialchars($student['class'] ?? '—');
$school_name   = htmlspecialchars($student['school_name'] ?? 'N/A');
$district      = htmlspecialchars($student['district'] ?? '');

/* Back URL per role */
$role = $_SESSION['role'];
$back_url = match($role) {
    'headteacher'        => '../headteacher/manage_students.php',
    'admin'              => '../admin/manage_candidates.php',
    default              => 'manage_students.php',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Registration Slip &mdash; <?= $student_name ?> | NED-SEMS</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Segoe UI", Tahoma, Verdana, sans-serif; background: #0f172a; color: #1e293b; min-height: 100vh; }

        /* Screen toolbar */
        .toolbar {
            background: #1e293b;
            color: #f8fafc;
            padding: 14px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 200;
            box-shadow: 0 2px 12px rgba(0,0,0,.35);
            gap: 12px;
        }
        .toolbar-left { display: flex; align-items: center; gap: 16px; }
        .toolbar-left span { font-size: .8rem; color: #94a3b8; }
        .toolbar a { color: #94a3b8; text-decoration: none; font-size: .85rem; transition: color .15s; }
        .toolbar a:hover { color: #f8fafc; }
        .toolbar-right { display: flex; gap: 10px; }
        .btn-print {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: #fff; border: none;
            padding: 9px 22px; border-radius: 8px;
            font-weight: 700; font-size: .875rem;
            cursor: pointer; display: inline-flex; align-items: center; gap: 7px;
            box-shadow: 0 2px 8px rgba(59,130,246,.4);
            transition: opacity .15s;
        }
        .btn-print:hover { opacity: .9; }
        .btn-back {
            background: #334155; color: #e2e8f0;
            border: none; padding: 9px 18px; border-radius: 8px;
            font-weight: 600; font-size: .875rem; cursor: pointer;
            text-decoration: none; display: inline-flex; align-items: center;
            transition: background .15s;
        }
        .btn-back:hover { background: #475569; }

        /* Slip card */
        .slip-page { max-width: 760px; margin: 36px auto; padding: 0 20px 60px; }

        .slip-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,.35);
        }

        /* Header band */
        .slip-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 60%, #1d4ed8 100%);
            padding: 28px 32px;
            color: #fff;
        }
        .slip-header__top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            flex-wrap: wrap;
        }
        .slip-header__org {
            font-size: .75rem;
            color: #93c5fd;
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 6px;
        }
        .slip-header h1 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 2px;
        }
        .slip-header__subtitle {
            font-size: .82rem;
            color: #bfdbfe;
        }
        .slip-doc-badge {
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 8px;
            padding: 10px 16px;
            text-align: center;
            min-width: 130px;
        }
        .slip-doc-badge__label { font-size: .65rem; color: #bfdbfe; text-transform: uppercase; letter-spacing: .06em; }
        .slip-doc-badge__value { font-size: 1.1rem; font-weight: 800; color: #fff; margin-top: 2px; }

        /* Body section */
        .slip-body { padding: 28px 32px; }

        .slip-meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 24px;
        }
        .slip-meta-row {
            display: flex;
            flex-direction: column;
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
        }
        .slip-meta-row:nth-child(odd) { border-right: 1px solid #f1f5f9; }
        .slip-meta-row:nth-last-child(-n+2) { border-bottom: none; }
        .slip-meta-row__label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; margin-bottom: 3px; }
        .slip-meta-row__value { font-size: .9rem; font-weight: 600; color: #0f172a; }

        /* Subjects section */
        .subjects-section__heading {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
        }
        .subjects-section__title {
            font-size: .875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #334155;
        }
        .subjects-section__count {
            background: #1d4ed8;
            color: #fff;
            font-size: .7rem;
            font-weight: 700;
            padding: 2px 9px;
            border-radius: 999px;
        }

        .subjects-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .subjects-table th {
            background: #f8fafc;
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            color: #334155;
            border-bottom: 2px solid #e2e8f0;
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .subjects-table td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; color: #1e293b; vertical-align: middle; }
        .subjects-table tbody tr:last-child td { border-bottom: none; }
        .subjects-table tbody tr:hover { background: #f8fafc; }

        .cat-pill {
            display: inline-flex;
            padding: 2px 9px;
            border-radius: 999px;
            font-size: .7rem;
            font-weight: 700;
        }
        .cat-pill--science    { background: #eff6ff; color: #1e40af; }
        .cat-pill--language   { background: #f0fdf4; color: #166534; }
        .cat-pill--humanities { background: #fdf4ff; color: #6b21a8; }
        .cat-pill--default    { background: #f1f5f9; color: #334155; }

        .no-subjects {
            text-align: center;
            padding: 28px 16px;
            color: #94a3b8;
            font-style: italic;
            font-size: .9rem;
        }

        /* Footer band */
        .slip-footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 14px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: .75rem;
            color: #94a3b8;
            flex-wrap: wrap;
            gap: 8px;
        }

        /* Signature section */
        .sig-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .sig-box { text-align: center; }
        .sig-line { border-bottom: 1.5px solid #334155; margin-bottom: 6px; height: 40px; }
        .sig-label { font-size: .72rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }

        /* Print styles */
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .slip-page { margin: 0; padding: 0; max-width: 100%; }
            .slip-card { box-shadow: none; border-radius: 0; border: none; }
            .slip-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .cat-pill { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<!-- SCREEN TOOLBAR -->
<div class="toolbar">
    <div class="toolbar-left">
        <a href="<?= htmlspecialchars($back_url) ?>">&larr; Back</a>
        <span>Subject Registration Slip</span>
    </div>
    <div class="toolbar-right">
        <button class="btn-print" onclick="window.print()">Print Slip</button>
    </div>
</div>

<!-- SLIP -->
<div class="slip-page">
    <div class="slip-card">

        <!-- HEADER -->
        <div class="slip-header">
            <div class="slip-header__top">
                <div>
                    <div class="slip-header__org">NED-SEMS &mdash; National Examination Management System</div>
                    <h1>Subject Registration Slip</h1>
                    <div class="slip-header__subtitle">Mock Examination &mdash; <?= $student_class ?></div>
                </div>
                <div class="slip-doc-badge">
                    <div class="slip-doc-badge__label">Subjects</div>
                    <div class="slip-doc-badge__value"><?= $subj_count ?></div>
                </div>
            </div>
        </div>

        <!-- BODY -->
        <div class="slip-body">

            <!-- Student meta -->
            <div class="slip-meta-grid">
                <div class="slip-meta-row">
                    <span class="slip-meta-row__label">Student Name</span>
                    <span class="slip-meta-row__value"><?= $student_name ?></span>
                </div>
                <div class="slip-meta-row">
                    <span class="slip-meta-row__label">Exam Number</span>
                    <span class="slip-meta-row__value"><?= $exam_number ?></span>
                </div>
                <div class="slip-meta-row">
                    <span class="slip-meta-row__label">Class / Form</span>
                    <span class="slip-meta-row__value"><?= $student_class ?></span>
                </div>
                <div class="slip-meta-row">
                    <span class="slip-meta-row__label">School</span>
                    <span class="slip-meta-row__value"><?= $school_name ?><?= $district ? ' &mdash; ' . $district : '' ?></span>
                </div>
            </div>

            <!-- Subjects list -->
            <div class="subjects-section__heading">
                <span class="subjects-section__title">Registered Subjects</span>
                <span class="subjects-section__count"><?= $subj_count ?></span>
            </div>

            <?php if ($subj_count === 0): ?>
                <div class="no-subjects">No subjects have been registered for this student yet.</div>
            <?php else: ?>
                <table class="subjects-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Code</th>
                            <th>Subject Name</th>
                            <th>Category</th>
                            <th>Paper Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $n = 1; foreach ($subjects as $subj):
                            $cat     = strtolower(trim($subj['category'] ?? ''));
                            $cat_css = in_array($cat, ['science','language','humanities']) ? 'cat-pill--' . $cat : 'cat-pill--default';
                        ?>
                        <tr>
                            <td><?= $n++ ?></td>
                            <td><strong><?= htmlspecialchars($subj['subject_code']) ?></strong></td>
                            <td><?= htmlspecialchars($subj['subject_name']) ?></td>
                            <td><span class="cat-pill <?= $cat_css ?>"><?= ucfirst(htmlspecialchars($subj['category'] ?: 'General')) ?></span></td>
                            <td><?= htmlspecialchars($subj['paper_type'] ?? 'Theory') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- Signature boxes -->
            <div class="sig-row">
                <div class="sig-box">
                    <div class="sig-line"></div>
                    <div class="sig-label">Student Signature</div>
                </div>
                <div class="sig-box">
                    <div class="sig-line"></div>
                    <div class="sig-label">Examination Officer Signature</div>
                </div>
            </div>

        </div>

        <!-- FOOTER -->
        <div class="slip-footer">
            <span>Generated: <?= $generated_at ?></span>
            <span>NED-SEMS &mdash; Confidential</span>
            <span>Exam No: <?= $exam_number ?></span>
        </div>

    </div>
</div>

</body>
</html>
