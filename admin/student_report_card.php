<?php
/* ════════════════════════════════════════════════════════════════
   admin/student_report_card.php
   Printable per-student report card — subjects, scores, grade, position
   Accessible by: admin, headteacher, examination_officer
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once '../config/db.php';
require_once '../common/grade_helper.php';

$allowed_roles = ['admin', 'headteacher', 'examination_officer'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: ../login.php"); exit();
}

$conn      = get_db_connection();
$result_id = (int)($_GET['result_id'] ?? 0);
if ($result_id <= 0) { echo "<p>Invalid result.</p>"; exit(); }

/* ── RESULT RECORD ── */
$stmt = $conn->prepare("
    SELECT r.*, st.name AS student_name, st.exam_number, st.class AS student_class,
           sc.school_name, sc.district,
           e.exam_name, e.year AS exam_year, e.class AS exam_class,
           u.name AS compiled_by_name
    FROM results r
    JOIN students st ON st.student_id = r.student_id
    LEFT JOIN schools sc ON sc.school_id = st.school_id
    JOIN exams e ON e.exam_id = r.exam_id
    LEFT JOIN users u ON u.user_id = r.compiled_by
    WHERE r.result_id = ?
");
$stmt->bind_param("i", $result_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$result) { echo "<p>Result not found.</p>"; exit(); }

/* ── SUBJECT MARKS (LEFT JOIN exam_subjects — may not exist for all exams) ── */
$stmt2 = $conn->prepare("
    SELECT s.subject_name, s.subject_code, s.paper_type,
           m.score, m.grade,
           COALESCE(es.total_marks, 100) AS total_marks,
           u.name AS teacher_name
    FROM marks m
    JOIN subjects s ON s.subject_id = m.subject_id
    LEFT JOIN exam_subjects es ON es.exam_id = m.exam_id AND es.subject_id = m.subject_id
    LEFT JOIN users u ON u.user_id = m.teacher_id
    WHERE m.student_id = ? AND m.exam_id = ? AND m.status = 'approved'
    ORDER BY m.score DESC
");
$stmt2->bind_param("ii", $result['student_id'], $result['exam_id']);
$stmt2->execute();
$subject_marks = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

/* ── COMPUTE RANK from average_score across all results in this exam ── */
$rank_row = $conn->query("
    SELECT COUNT(*) + 1 AS rnk
    FROM results
    WHERE exam_id = {$result['exam_id']}
      AND average_score > {$result['average_score']}
")->fetch_assoc();
$rank      = (int)($rank_row['rnk'] ?? 1);
$class_size = (int)$conn->query("SELECT COUNT(*) AS c FROM results WHERE exam_id={$result['exam_id']}")->fetch_assoc()['c'];

$conn->close();

/* ── Derived values ── */
$avg        = (float)($result['average_score'] ?? 0);
$total      = (float)($result['total_score']   ?? 0);
$grade      = $result['grade'] ?? calcGrade($avg);

/**
 * Normalise Malawi-style grade strings (P5, C6, F9, C4, etc.) to a
 * simple group string used for CSS colouring.
 * Also handles plain numeric grades 1–9.
 */
function gradeGroupCss(string $g): string {
    $g = strtoupper(trim($g));
    // Malawi letter prefix: D=Distinction, C=Credit, P=Pass, F=Fail
    if ($g === '' || $g === '—') return 'fail';
    $letter = $g[0];
    if ($letter === 'D' || $letter === '1' || $letter === '2') return 'distinction';
    if ($letter === 'C' || $letter === '3' || $letter === '4' || $letter === '5') return 'credit';
    if ($letter === 'P' || $letter === '6' || $letter === '7') return 'pass';
    return 'fail'; // F, 8, 9
}

function isPassing2(string $g): bool {
    $g = strtoupper(trim($g));
    if (empty($g)) return false;
    $letter = $g[0];
    // D, C, P = passing; F = fail; numeric 1-7 = passing
    if (in_array($letter, ['D','C','P'])) return true;
    if ($letter === 'F') return false;
    // numeric
    $num = (int)$g;
    return $num >= 1 && $num <= 7;
}

function gradeLabelFull(string $g): string {
    $g = strtoupper(trim($g));
    if (empty($g)) return '—';
    $letter = $g[0];
    if ($letter === 'D' || in_array($g, ['1','2'])) return 'Distinction';
    if ($letter === 'C' || in_array($g, ['3','4','5'])) return 'Credit';
    if ($letter === 'P' || in_array($g, ['6','7'])) return 'Pass';
    return 'Fail';
}

$passed     = isPassing2($grade);
$grade_lbl  = gradeLabelFull($grade);
$grade_group = gradeGroupCss($grade);

$student_name  = htmlspecialchars($result['student_name']   ?? '—');
$exam_number   = htmlspecialchars($result['exam_number']     ?? '—');
$student_class = htmlspecialchars($result['class'] ?? $result['student_class'] ?? $result['exam_class'] ?? '—');
$school_name   = htmlspecialchars($result['school_name']     ?? '—');
$district      = htmlspecialchars($result['district']        ?? '');
$exam_name     = htmlspecialchars($result['exam_name']       ?? '—');
$term          = htmlspecialchars($result['term']            ?? '');
$year          = htmlspecialchars((string)($result['year'] ?? $result['exam_year'] ?? ''));
$compiled_by   = htmlspecialchars($result['compiled_by_name'] ?? 'EDM Office');
$compiled_at   = !empty($result['compiled_at']) ? date('d F Y', strtotime($result['compiled_at'])) : date('d F Y');
$status        = ucfirst($result['status'] ?? 'draft');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Report Card — <?= $student_name ?> | NED-SEMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap" rel="stylesheet">
<style>
/* ─── Reset ─── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: #0f172a; color: #1e293b; min-height: 100vh; }

/* ─── Toolbar (screen only) ─── */
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
}
.toolbar-left { display: flex; align-items: center; gap: 16px; }
.toolbar-left span { font-size: .8rem; color: #94a3b8; }
.toolbar a {
    color: #94a3b8; text-decoration: none; font-size: .85rem;
    display: inline-flex; align-items: center; gap: 5px;
    transition: color .15s;
}
.toolbar a:hover { color: #f8fafc; }
.toolbar-right { display: flex; gap: 10px; }
.btn-print {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: #fff; border: none;
    padding: 9px 22px; border-radius: 8px;
    font-weight: 700; font-size: .875rem;
    cursor: pointer; display: inline-flex; align-items: center; gap: 7px;
    transition: opacity .15s, transform .1s;
    box-shadow: 0 2px 8px rgba(59,130,246,.4);
}
.btn-print:hover { opacity: .9; transform: translateY(-1px); }
.btn-back {
    background: #334155; color: #e2e8f0;
    border: none; padding: 9px 18px; border-radius: 8px;
    font-weight: 600; font-size: .875rem; cursor: pointer;
    transition: background .15s;
}
.btn-back:hover { background: #475569; }

/* ─── Page wrapper ─── */
.report-page {
    max-width: 860px;
    margin: 36px auto;
    padding: 0 20px 60px;
}

/* ─── Card container ─── */
.rc-card {
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,.35);
}

/* ─── Header band ─── */
.rc-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 60%, #1d4ed8 100%);
    padding: 32px 36px;
    display: flex;
    align-items: center;
    gap: 22px;
    position: relative;
    overflow: hidden;
}
.rc-header::before {
    content: '';
    position: absolute; inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.rc-logo-wrap {
    width: 76px; height: 76px;
    background: rgba(255,255,255,.12);
    border-radius: 12px;
    border: 2px solid rgba(255,255,255,.2);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; backdrop-filter: blur(4px);
}
.rc-logo-wrap img { width: 56px; height: 56px; object-fit: contain; }
.rc-logo-fallback { font-weight: 900; font-size: 1.4rem; color: #fff; letter-spacing: -1px; }
.rc-org { flex: 1; }
.rc-org h1 { font-size: 1.05rem; font-weight: 800; color: #fff; letter-spacing: -.3px; }
.rc-org .sub { font-size: .78rem; color: rgba(255,255,255,.6); margin-top: 3px; }
.rc-org .school { font-size: .82rem; color: rgba(255,255,255,.85); margin-top: 6px; font-weight: 600; }
.rc-badge {
    text-align: right;
    flex-shrink: 0;
}
.rc-badge .title { font-size: 1.6rem; font-weight: 900; color: #fff; letter-spacing: -1px; line-height: 1; }
.rc-badge .exam  { font-size: .78rem; color: rgba(255,255,255,.7); margin-top: 6px; max-width: 180px; line-height: 1.4; }
.rc-badge .period {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 999px;
    padding: 3px 12px;
    font-size: .72rem;
    color: rgba(255,255,255,.9);
    margin-top: 8px;
    font-weight: 600;
}

/* ─── Student strip ─── */
.rc-student {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    border-bottom: 2px solid #e2e8f0;
}
.rc-student-cell {
    padding: 18px 24px;
    border-right: 1px solid #e2e8f0;
}
.rc-student-cell:last-child { border-right: none; }
.rc-student-cell .lbl {
    font-size: .68rem; font-weight: 700; color: #94a3b8;
    text-transform: uppercase; letter-spacing: .06em;
}
.rc-student-cell .val {
    font-size: .95rem; font-weight: 700; color: #0f172a; margin-top: 4px;
}

/* ─── KPI strip ─── */
.rc-kpi {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
}
.rc-kpi-item {
    padding: 22px 20px;
    text-align: center;
    border-right: 1px solid #e2e8f0;
    position: relative;
}
.rc-kpi-item:last-child { border-right: none; }
.rc-kpi-item .k-val {
    font-size: 2rem; font-weight: 900; line-height: 1;
    color: #0f172a;
}
.rc-kpi-item .k-lbl {
    font-size: .68rem; font-weight: 700; color: #94a3b8;
    text-transform: uppercase; letter-spacing: .05em;
    margin-top: 5px;
}
/* Outcome pill in KPI */
.outcome-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 18px; border-radius: 999px;
    font-size: 1rem; font-weight: 800;
    letter-spacing: .5px;
}
.outcome-pill.pass { background: #dcfce7; color: #15803d; }
.outcome-pill.fail { background: #fee2e2; color: #b91c1c; }

/* Grade colours */
.grade-distinction { color: #15803d; }
.grade-credit      { color: #1d4ed8; }
.grade-pass        { color: #d97706; }
.grade-fail        { color: #b91c1c; }

/* ─── Subject table ─── */
.rc-section-head {
    padding: 16px 24px 10px;
    border-bottom: 1px solid #e2e8f0;
    display: flex; align-items: center; justify-content: space-between;
}
.rc-section-head h3 {
    font-size: .85rem; font-weight: 800; color: #0f172a;
    text-transform: uppercase; letter-spacing: .06em;
}
.rc-section-head small { font-size: .75rem; color: #94a3b8; }

.rc-table { width: 100%; border-collapse: collapse; }
.rc-table thead tr { background: #f1f5f9; }
.rc-table th {
    padding: 10px 16px; text-align: left;
    font-size: .7rem; font-weight: 800; color: #64748b;
    text-transform: uppercase; letter-spacing: .06em;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
}
.rc-table th:first-child { width: 36px; }
.rc-table td {
    padding: 12px 16px; font-size: .875rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.rc-table tbody tr:last-child td { border-bottom: none; }
.rc-table tbody tr:hover td { background: #fafbfc; }

.subject-name { font-weight: 700; color: #0f172a; }
.subject-code { font-size: .72rem; color: #94a3b8; font-weight: 500; margin-top: 1px; }

/* Score bar */
.score-wrap { display: flex; align-items: center; gap: 10px; }
.score-num  { font-weight: 800; font-size: .9rem; min-width: 36px; color: #0f172a; }
.bar-track  { flex: 1; height: 7px; background: #e2e8f0; border-radius: 99px; overflow: hidden; min-width: 60px; }
.bar-fill   { height: 100%; border-radius: 99px; transition: width .3s; }
.bar-distinction { background: linear-gradient(90deg, #22c55e, #16a34a); }
.bar-credit      { background: linear-gradient(90deg, #60a5fa, #2563eb); }
.bar-pass        { background: linear-gradient(90deg, #fbbf24, #d97706); }
.bar-fail        { background: linear-gradient(90deg, #f87171, #dc2626); }

/* Grade pill */
.grade-pill {
    display: inline-block; padding: 3px 11px; border-radius: 999px;
    font-size: .72rem; font-weight: 800; white-space: nowrap;
}
.pill-distinction { background: #dcfce7; color: #15803d; }
.pill-credit      { background: #dbeafe; color: #1d4ed8; }
.pill-pass        { background: #fef3c7; color: #92400e; }
.pill-fail        { background: #fee2e2; color: #991b1b; }

/* Remarks */
.remarks { font-size: .75rem; color: #64748b; }

/* Total row */
.total-row td { background: #f1f5f9 !important; font-weight: 800; border-top: 2px solid #cbd5e1 !important; }

/* ─── Footer ─── */
.rc-footer {
    padding: 24px 28px;
    border-top: 2px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 20px;
    background: #fafbfc;
}
.footer-meta { font-size: .78rem; color: #64748b; line-height: 1.8; }
.footer-meta strong { color: #334155; }
.sig-block { text-align: center; }
.sig-line {
    border-top: 1.5px solid #334155;
    width: 150px; margin: 0 auto;
    padding-top: 5px;
    font-size: .68rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: .05em;
}
.sig-space { height: 36px; }

/* ─── Status badge ─── */
.status-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 999px; font-size: .7rem; font-weight: 700;
}
.status-published { background: #dcfce7; color: #15803d; }
.status-draft     { background: #fef3c7; color: #92400e; }

/* ─── Watermark (print) ─── */
.rc-watermark {
    text-align: center;
    font-size: .7rem; color: #cbd5e1;
    padding: 14px; letter-spacing: .04em;
}

/* ─── PRINT styles ─── */
@media print {
    body { background: #fff !important; }
    .no-print { display: none !important; }
    .report-page { margin: 0 !important; padding: 0 !important; max-width: 100% !important; }
    .rc-card { box-shadow: none !important; border-radius: 0 !important; }
    .rc-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .rc-table th, .rc-kpi { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .bar-track, .bar-fill { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .grade-pill, .outcome-pill, .status-badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    @page { margin: 12mm; }
}
</style>
</head>
<body>

<!-- ═══ Toolbar (no-print) ═══ -->
<div class="toolbar no-print">
    <div class="toolbar-left">
        <button class="btn-back" onclick="window.history.back()">← Back</button>
        <span>Report Card &mdash; <?= $student_name ?></span>
    </div>
    <div class="toolbar-right">
        <button class="btn-print" onclick="window.print()">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8" rx="1"/></svg>
            Print / Save PDF
        </button>
    </div>
</div>

<!-- ═══ Report Card ═══ -->
<div class="report-page">
<div class="rc-card">

    <!-- ── Header ── -->
    <div class="rc-header">
        <div class="rc-logo-wrap">
            <img src="../assets/images/logo.png" alt="NED"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
            <span class="rc-logo-fallback" style="display:none;">NED</span>
        </div>
        <div class="rc-org">
            <h1>Northern Education Division</h1>
            <div class="sub">Smart Examination Management System (NED-SEMS)</div>
            <div class="school"><?= $school_name ?><?= $district ? " &mdash; $district" : '' ?></div>
        </div>
        <div class="rc-badge">
            <div class="title">Report Card</div>
            <div class="exam"><?= $exam_name ?></div>
            <?php if ($term || $year): ?>
            <div class="period">
                <?= $term ?><?= ($term && $year) ? ' &bull; ' : '' ?><?= $year ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Student info strip ── -->
    <div class="rc-student">
        <div class="rc-student-cell">
            <div class="lbl">Student Name</div>
            <div class="val"><?= $student_name ?></div>
        </div>
        <div class="rc-student-cell">
            <div class="lbl">Examination No.</div>
            <div class="val"><?= $exam_number ?></div>
        </div>
        <div class="rc-student-cell">
            <div class="lbl">Class / Form</div>
            <div class="val"><?= $student_class ?></div>
        </div>
        <div class="rc-student-cell">
            <div class="lbl">School</div>
            <div class="val"><?= $school_name ?></div>
        </div>
    </div>

    <!-- ── KPI strip ── -->
    <div class="rc-kpi">
        <!-- Average Score -->
        <div class="rc-kpi-item">
            <div class="k-val grade-<?= $grade_group ?>"><?= number_format($avg, 1) ?>%</div>
            <div class="k-lbl">Average Score</div>
        </div>

        <!-- Overall Grade -->
        <div class="rc-kpi-item">
            <div class="k-val grade-<?= $grade_group ?>">
                <?= htmlspecialchars($grade) ?>
            </div>
            <div class="k-lbl"><?= htmlspecialchars($grade_lbl) ?></div>
        </div>

        <!-- Class Position -->
        <div class="rc-kpi-item">
            <div class="k-val"><?= $rank ?><span style="font-size:1rem;font-weight:600;color:#94a3b8;"> / <?= $class_size ?></span></div>
            <div class="k-lbl">Class Position</div>
        </div>

        <!-- Outcome -->
        <div class="rc-kpi-item">
            <div style="margin-top:4px;">
                <span class="outcome-pill <?= $passed ? 'pass' : 'fail' ?>">
                    <?= $passed ? '✓ PASS' : '✗ FAIL' ?>
                </span>
            </div>
            <div class="k-lbl" style="margin-top:8px;">Overall Outcome</div>
        </div>
    </div>

    <!-- ── Subject Breakdown ── -->
    <div class="rc-section-head">
        <h3>Subject Breakdown</h3>
        <small><?= count($subject_marks) ?> subject<?= count($subject_marks) !== 1 ? 's' : '' ?> sat</small>
    </div>

    <table class="rc-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Subject</th>
                <th>Score</th>
                <th>Out&nbsp;of</th>
                <th>Percentage</th>
                <th>Grade</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($subject_marks)): ?>
            <tr>
                <td colspan="7" style="text-align:center;padding:32px;color:#94a3b8;font-size:.9rem;">
                    No approved subject marks found for this result.
                </td>
            </tr>
        <?php else: ?>
            <?php $n = 1; foreach ($subject_marks as $sm):
                $out  = (float)($sm['total_marks'] ?? 100);
                $sc   = (float)($sm['score'] ?? 0);
                $pct  = $out > 0 ? round(($sc / $out) * 100, 1) : 0;
                $sg   = $sm['grade'] ?? calcGrade($pct);
                $sgrp = gradeGroupCss($sg);
            ?>
            <tr>
                <td style="color:#94a3b8;font-weight:600;"><?= $n++ ?></td>
                <td>
                    <div class="subject-name"><?= htmlspecialchars($sm['subject_name']) ?></div>
                    <?php if (!empty($sm['subject_code'])): ?>
                    <div class="subject-code"><?= htmlspecialchars($sm['subject_code']) ?><?= !empty($sm['paper_type']) ? ' · ' . htmlspecialchars($sm['paper_type']) : '' ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="score-wrap">
                        <span class="score-num"><?= number_format($sc, 1) ?></span>
                        <div class="bar-track">
                            <div class="bar-fill bar-<?= $sgrp ?>" style="width:<?= min($pct, 100) ?>%;"></div>
                        </div>
                    </div>
                </td>
                <td style="color:#94a3b8;"><?= (int)$out ?></td>
                <td style="font-weight:700; color:<?= $pct >= 70 ? '#15803d' : ($pct >= 40 ? '#1d4ed8' : '#b91c1c') ?>;">
                    <?= $pct ?>%
                </td>
                <td><span class="grade-pill pill-<?= $sgrp ?>">Grade <?= htmlspecialchars($sg) ?></span></td>
                <td class="remarks"><?= htmlspecialchars(gradeLabelFull($sg)) ?></td>
            </tr>
            <?php endforeach; ?>

            <!-- Total / summary row -->
            <tr class="total-row">
                <td colspan="2" style="text-align:right;font-size:.78rem;color:#64748b;letter-spacing:.05em;">OVERALL TOTAL</td>
                <td colspan="2" style="font-size:1rem;"><?= number_format($total, 1) ?></td>
                <td style="font-size:1rem;color:<?= $avg >= 70 ? '#15803d' : ($avg >= 40 ? '#1d4ed8' : '#b91c1c') ?>;">
                    <?= number_format($avg, 1) ?>%
                </td>
                <td>
                    <span class="grade-pill pill-<?= $grade_group ?>">
                        <?= htmlspecialchars($grade) ?>
                    </span>
                </td>
                <td class="remarks"><?= htmlspecialchars($grade_lbl) ?></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- ── Footer ── -->
    <div class="rc-footer">
        <div class="footer-meta">
            <div>Compiled by: <strong><?= $compiled_by ?></strong></div>
            <div>Date: <strong><?= $compiled_at ?></strong></div>
            <div style="margin-top:4px;">
                Status:
                <span class="status-badge status-<?= strtolower($result['status'] ?? 'draft') ?>">
                    <?= $status ?>
                </span>
            </div>
        </div>

        <div style="display:flex;gap:40px;">
            <div class="sig-block">
                <div class="sig-space"></div>
                <div class="sig-line">Headteacher's Signature</div>
            </div>
            <div class="sig-block">
                <div class="sig-space"></div>
                <div class="sig-line">School Stamp</div>
            </div>
        </div>
    </div>

</div><!-- rc-card -->

<div class="rc-watermark no-print">
    NED-SEMS &mdash; Northern Education Division Smart Examination Management System
</div>

</div><!-- report-page -->
</body>
</html>
