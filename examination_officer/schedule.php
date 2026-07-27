<?php
/* ════════════════════════════════════════════════════════════════
   examination_officer/schedule.php
   Examination Officer: Exam Settings, Timing & Deadlines Control
   ────────────────────────────────────────────────────────────────
   Allows Examination Officers to:
   1. Manage Exam Code, Class, Start Date, End Date, Duration, and Hard Cutoff Deadline.
   2. Inspect Per-Subject Deadlines & Lagging Status (set by Headteachers).
   3. View all Exams in a 5-per-page paginated table with sequential counter (#).
   ════════════════════════════════════════════════════════════════ */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../common/report_stats.php';
require_once __DIR__ . '/../common/pagination_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'examination_officer') {
    header("Location: ../login.php");
    exit();
}

if (!function_exists('safe')) {
    function safe($v) {
        return htmlspecialchars($v ?? '');
    }
}

$conn = get_db_connection();
$school_id = (int)($_SESSION['school_id'] ?? 0);

$message  = '';
$error    = '';
$edit_id  = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 5;

/* ════════════════════════════════════════════════════════════════
   POST — UPDATE EXAM TIMING & DEADLINE SETTINGS
   ════════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_id          = (int)($_POST['exam_id'] ?? 0);
    $exam_code        = trim($_POST['exam_code'] ?? '');
    $exam_class       = trim($_POST['class'] ?? '');
    $start_date       = trim($_POST['start_date'] ?? '');
    $end_date         = trim($_POST['end_date'] ?? '');
    $duration_minutes = (int)($_POST['duration_minutes'] ?? 0);
    $marks_deadline   = trim($_POST['marks_deadline'] ?? '');

    if ($exam_id <= 0) {
        $error = 'Please select a valid examination.';
    } elseif ($exam_class === '') {
        $error = 'Please select a target class.';
    } else {
        $deadline_val = ($marks_deadline !== '') ? date('Y-m-d H:i:s', strtotime($marks_deadline)) : null;
        $start_val    = ($start_date !== '') ? $start_date : null;
        $end_val      = ($end_date !== '') ? $end_date : null;
        $duration_val = ($duration_minutes > 0) ? $duration_minutes : null;

        $stmt = $conn->prepare('
            UPDATE exams 
            SET exam_code = ?, 
                class = ?, 
                start_date = ?, 
                end_date = ?, 
                duration_minutes = ?, 
                marks_deadline = ? 
            WHERE exam_id = ?
        ');
        $stmt->bind_param('ssssisi', $exam_code, $exam_class, $start_val, $end_val, $duration_val, $deadline_val, $exam_id);
        
        if ($stmt->execute()) {
            $message = 'Exam settings and timing schedule updated successfully.';
            $edit_id = $exam_id;
            log_audit_event('EXAM_SCHEDULE_UPDATED', [
                'exam_id'          => $exam_id,
                'exam_code'        => $exam_code,
                'class'            => $exam_class,
                'start_date'       => $start_val,
                'duration_minutes' => $duration_val,
                'marks_deadline'   => $deadline_val
            ], null, $conn);
        } else {
            $error = 'Failed to update exam settings: ' . $conn->error;
        }
        $stmt->close();
    }
}

/* ════════════════════════════════════════════════════════════════
   DATA FETCHING
   ════════════════════════════════════════════════════════════════ */
// Dropdown exam list
$exam_list_res = $conn->query("
    SELECT e.exam_id, e.exam_name, e.exam_code, e.class,
           (SELECT COUNT(subject_id) FROM exam_subjects es WHERE es.exam_id = e.exam_id) AS subject_count
    FROM exams e 
    ORDER BY e.exam_id DESC
");
$exam_dropdown = $exam_list_res ? $exam_list_res->fetch_all(MYSQLI_ASSOC) : [];

// Selected exam details for editing
$edit_exam = null;
$subject_breakdown = [];
if ($edit_id > 0) {
    $e_stmt = $conn->prepare("SELECT * FROM exams WHERE exam_id = ?");
    $e_stmt->bind_param("i", $edit_id);
    $e_stmt->execute();
    $edit_exam = $e_stmt->get_result()->fetch_assoc();
    $e_stmt->close();

    // Fetch Per-Subject Deadlines Breakdown for this exam
    if ($edit_exam) {
        $sb_stmt = $conn->prepare("
            SELECT 
                s.subject_name,
                s.subject_code,
                s.category,
                u.name AS teacher_name,
                ma.deadline AS subject_deadline,
                ma.status AS assignment_status
            FROM marking_assignments ma
            JOIN subjects s ON ma.subject_id = s.subject_id
            LEFT JOIN users u ON ma.teacher_id = u.user_id
            WHERE ma.exam_id = ?
            ORDER BY ma.deadline ASC, s.subject_name ASC
        ");
        $sb_stmt->bind_param("i", $edit_id);
        $sb_stmt->execute();
        $subject_breakdown = $sb_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $sb_stmt->close();
    }
}

// Total Count for Pagination
$total_exams = (int)$conn->query("SELECT COUNT(*) AS cnt FROM exams")->fetch_assoc()['cnt'];
$pagination  = paginate($total_exams, $page, $per_page);
$offset      = ($pagination['page'] - 1) * $per_page;

// Paginated Exam Deadlines Overview List
$schedule_stmt = $conn->prepare("
    SELECT e.exam_id, e.exam_name, e.exam_code, e.class, e.status, e.start_date, 
           COALESCE(e.duration_minutes, (SELECT MAX(duration_minutes) FROM exam_subjects es WHERE es.exam_id = e.exam_id)) AS duration_minutes,
           e.marks_deadline,
           (SELECT COUNT(subject_id) FROM exam_subjects es WHERE es.exam_id = e.exam_id) AS subject_count
    FROM exams e 
    ORDER BY e.start_date DESC, e.exam_id DESC
    LIMIT ? OFFSET ?
");
$schedule_stmt->bind_param("ii", $per_page, $offset);
$schedule_stmt->execute();
$schedule_list = $schedule_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$schedule_stmt->close();

$conn->close();

$module_css = 'exam_officer';
include __DIR__ . '/../common/head_assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Settings &amp; Deadlines | NED-SEMS</title>
    <meta name="description" content="Manage examination schedules, paper timing, start dates, duration, cutoff deadlines, and per-subject breakdown">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reports.css">
    <style>
        .schedule-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        @media (max-width: 992px) {
            .schedule-grid { grid-template-columns: 1fr; }
        }
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .status-pill {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .lagging-badge {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecdd3;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .on-track-badge {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            white-space: nowrap;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../common/header.php'; ?>

<div class="dashboard">
    <?php include __DIR__ . '/../common/sidebar.php'; ?>

    <div class="content">

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Exam Settings &amp; Deadlines</h1>
                <p class="page-subtitle">Configure paper timing, start dates, durations, cutoff deadlines, and inspect per-subject deadlines</p>
            </div>
            <div class="header-actions">
                <a href="exams.php" class="btn btn-secondary">&larr; All Examinations</a>
            </div>
        </div>

        <!-- FLASH MESSAGES -->
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- MAIN PANEL GRID -->
        <div class="schedule-grid">

            <!-- FORM: EDIT EXAM TIMING & CUTOFF DEADLINE -->
            <div class="card" style="margin: 0;">
                <div class="section-header" style="margin-bottom: 16px;">
                    <h3 style="margin: 0;"><?= $edit_exam ? 'Edit Exam Timing &amp; Deadlines' : 'Select Exam to Configure' ?></h3>
                </div>

                <form method="POST">
                    <!-- Exam Selector -->
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="exam_id" style="font-weight: 700;">Select Examination <span style="color:var(--danger-color)">*</span></label>
                        <select name="exam_id" id="exam_id" required onchange="window.location.href='schedule.php?exam_id=' + this.value;">
                            <option value="">— Choose an Examination —</option>
                            <?php foreach ($exam_dropdown as $ex): ?>
                                <option value="<?= $ex['exam_id'] ?>" <?= $edit_id === (int)$ex['exam_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ex['exam_name']) ?> (<?= safe($ex['exam_code']) ?> &middot; <?= (int)$ex['subject_count'] ?> Subjects)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($edit_exam): ?>
                        <!-- Code & Class -->
                        <div class="form-row-2" style="margin-bottom: 16px;">
                            <div class="form-group">
                                <label for="exam_code">Exam Code</label>
                                <input type="text" name="exam_code" id="exam_code" value="<?= safe($edit_exam['exam_code'] ?? '') ?>" placeholder="e.g. JCE2026">
                            </div>
                            <div class="form-group">
                                <label for="class">Target Class <span style="color:var(--danger-color)">*</span></label>
                                <select name="class" id="class" required>
                                    <option value="Form 2" <?= ($edit_exam['class'] ?? '') === 'Form 2' ? 'selected' : '' ?>>Form 2 (JCE)</option>
                                    <option value="Form 4" <?= ($edit_exam['class'] ?? '') === 'Form 4' ? 'selected' : '' ?>>Form 4 (MSCE)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Start Date, End Date & Duration -->
                        <div class="form-row-2" style="margin-bottom: 16px;">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" name="start_date" id="start_date" value="<?= safe($edit_exam['start_date'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" name="end_date" id="end_date" value="<?= safe($edit_exam['end_date'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 16px;">
                            <label for="duration_minutes">Paper Duration (Minutes)</label>
                            <input type="number" name="duration_minutes" id="duration_minutes" min="1" max="600" value="<?= (int)($edit_exam['duration_minutes'] ?? 0) ?>" placeholder="e.g. 120">
                        </div>

                        <!-- Overall Cutoff Deadline -->
                        <div style="background: #f8fafc; padding: 16px; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
                            <label for="marks_deadline" style="color: #0f172a; font-weight: 700; display: block; margin-bottom: 4px;">
                                Overall Marks Submission Cutoff Deadline <span style="color: #dc2626;">(Hard Cutoff)</span>
                            </label>
                            <p style="font-size: 0.78rem; color: #64748b; margin: 0 0 10px 0; line-height: 1.4;">
                                System-wide final cutoff. Teachers will observe the earliest date between this cutoff and per-subject deadlines set by Headteachers.
                            </p>
                            <input type="date" name="marks_deadline" id="marks_deadline" 
                                   value="<?= safe(isset($edit_exam['marks_deadline']) && $edit_exam['marks_deadline'] ? date('Y-m-d', strtotime($edit_exam['marks_deadline'])) : '') ?>">
                        </div>

                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary" style="font-weight: 700;">Save Settings &amp; Deadlines</button>
                            <a href="schedule.php" class="btn btn-secondary">Reset Form</a>
                        </div>
                    <?php else: ?>
                        <p class="empty-state">Select an examination from the dropdown above to manage its timing, paper duration, and cutoff deadlines.</p>
                    <?php endif; ?>
                </form>
            </div>

            <!-- PER-SUBJECT DEADLINES BREAKDOWN -->
            <div class="card" style="margin: 0;">
                <div class="section-header" style="margin-bottom: 16px;">
                    <h3 style="margin: 0;">Per-Subject Deadlines Breakdown</h3>
                    <?php if ($edit_exam): ?>
                        <span style="font-size: 0.78rem; color: var(--text-muted);"><?= safe($edit_exam['exam_name']) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($edit_id > 0 && !empty($subject_breakdown)): ?>
                    <?php 
                        $cutoff_ts = isset($edit_exam['marks_deadline']) && $edit_exam['marks_deadline'] ? strtotime($edit_exam['marks_deadline']) : null;
                        $lagging_count = 0;
                        foreach ($subject_breakdown as $sb) {
                            $subj_ts = $sb['subject_deadline'] ? strtotime($sb['subject_deadline']) : null;
                            if (($cutoff_ts && $subj_ts && $subj_ts > $cutoff_ts) || ($subj_ts && $subj_ts < time())) {
                                $lagging_count++;
                            }
                        }
                    ?>
                    <div style="display: flex; gap: 8px; margin-bottom: 12px; font-size: 0.78rem;">
                        <span style="background: #eff6ff; color: #1d4ed8; padding: 4px 10px; border-radius: 6px; font-weight: 700;">
                            <?= count($subject_breakdown) ?> Subjects Assigned
                        </span>
                        <?php if ($lagging_count > 0): ?>
                            <span style="background: #fef2f2; color: #dc2626; padding: 4px 10px; border-radius: 6px; font-weight: 700;">
                                <?= $lagging_count ?> High Risk
                            </span>
                        <?php else: ?>
                            <span style="background: #f0fdf4; color: #16a34a; padding: 4px 10px; border-radius: 6px; font-weight: 700;">
                                All Subjects On Track
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="rpt-table-wrap" style="max-height: 280px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <table class="rpt-table">
                            <thead style="position: sticky; top: 0; background: #f8fafc; z-index: 2; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                <tr>
                                    <th>Subject</th>
                                    <th>Assigned Marker</th>
                                    <th>Deadline (HT)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subject_breakdown as $sb):
                                        $subj_ts = $sb['subject_deadline'] ? strtotime($sb['subject_deadline']) : null;
                                        $is_lagging = ($cutoff_ts && $subj_ts && $subj_ts > $cutoff_ts) || ($subj_ts && $subj_ts < time());
                                ?>
                                    <tr>
                                        <td>
                                            <strong style="color: #0f172a; font-size: 0.88rem;"><?= safe($sb['subject_name']) ?></strong>
                                            <div style="font-size: 0.75rem; color: #64748b;"><?= safe($sb['subject_code']) ?> &middot; <?= ucfirst(safe($sb['category'] ?: 'General')) ?></div>
                                        </td>
                                        <td style="font-size: 0.82rem; font-weight: 600; color: #334155;">
                                            <?= safe($sb['teacher_name'] ?: 'Unassigned') ?>
                                        </td>
                                        <td>
                                            <?php if ($sb['subject_deadline']): ?>
                                                <span style="font-weight: 700; color: <?= $is_lagging ? '#dc2626' : '#0f172a' ?>; font-size: 0.82rem;">
                                                    <?= date('d M Y', strtotime($sb['subject_deadline'])) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #94a3b8; font-style: italic; font-size: 0.8rem;">No Subject Deadline</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($is_lagging): ?>
                                                <span class="lagging-badge">High Risk</span>
                                            <?php else: ?>
                                                <span class="on-track-badge">On Track</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($edit_id > 0): ?>
                    <p class="empty-state">No per-subject marking assignments found for this examination yet.</p>
                <?php else: ?>
                    <p class="empty-state">Select an examination on the left to inspect its per-subject deadlines breakdown set by Headteachers.</p>
                <?php endif; ?>
            </div>

        </div><!-- /schedule-grid -->

        <!-- EXAM DEADLINES OVERVIEW TABLE (5 PER PAGE PAGINATED WITH SEQUENTIAL COUNTER #) -->
        <div class="card">
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <div>
                    <h3 style="margin: 0;">Exam Deadlines Overview</h3>
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin: 4px 0 0 0;">
                        Displaying <strong><?= count($schedule_list) ?></strong> of <strong><?= $total_exams ?></strong> total examinations (5 per page)
                    </p>
                </div>
                <?php if ($pagination && $pagination['total_pages'] > 1): ?>
                    <span style="font-size: 0.82rem; color: var(--text-muted);">Page <?= $pagination['page'] ?> of <?= $pagination['total_pages'] ?></span>
                <?php endif; ?>
            </div>

            <div class="rpt-table-wrap">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Exam Name &amp; Code</th>
                            <th>Target Class</th>
                            <th class="val-col">Subjects</th>
                            <th>Schedule &amp; Duration</th>
                            <th>Marks Cutoff Deadline</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($schedule_list)): ?>
                            <tr>
                                <td colspan="8" class="empty-state">No examination schedules available.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($schedule_list as $idx => $row): ?>
                                <tr>
                                    <!-- Sequential counter column (#) -->
                                    <td style="font-weight: 700; color: var(--text-muted); font-size: 0.8rem;">
                                        <?= $offset + $idx + 1 ?>
                                    </td>

                                    <td>
                                        <div style="font-weight: 700; color: #0f172a; font-size: 0.92rem;">
                                            <?= safe($row['exam_name']) ?>
                                        </div>
                                        <div style="font-size: 0.78rem; color: #64748b;">
                                            Code: <?= safe($row['exam_code'] ?: 'N/A') ?>
                                        </div>
                                    </td>

                                    <td style="font-weight: 600; color: #334155;"><?= safe($row['class'] ?? '—') ?></td>

                                    <td class="val-col" style="font-weight: 700; color: var(--info-color);">
                                        <?= (int)$row['subject_count'] ?> Subjects
                                    </td>

                                    <td>
                                        <div style="font-size: 0.82rem; font-weight: 600;">
                                            <?= $row['start_date'] ? date('d M Y', strtotime($row['start_date'])) : 'Unscheduled' ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: #64748b;">
                                            Duration: <?= $row['duration_minutes'] ? (int)$row['duration_minutes'] . ' mins' : 'N/A' ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?php if (!empty($row['marks_deadline'])): ?>
                                            <strong style="color: #dc2626; font-size: 0.85rem;">
                                                <?= date('d M Y', strtotime($row['marks_deadline'])) ?>
                                            </strong>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-style: italic; font-size: 0.8rem;">No Cutoff Set</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php
                                            $st = $row['status'];
                                            $st_label = ucfirst(str_replace('_', ' ', $st));
                                            $badge_bg = match($st) {
                                                'under_moderation' => '#fefce8', 'active' => '#f0fdf4', 'completed' => '#f3e8ff',
                                                'approved' => '#eff6ff', default => '#f8fafc'
                                            };
                                            $badge_fg = match($st) {
                                                'under_moderation' => '#ca8a04', 'active' => '#16a34a', 'completed' => '#7c3aed',
                                                'approved' => '#2563eb', default => '#64748b'
                                            };
                                        ?>
                                        <span class="status-pill" style="background: <?= $badge_bg ?>; color: <?= $badge_fg ?>;">
                                            <?= htmlspecialchars($st_label) ?>
                                        </span>
                                    </td>

                                    <td style="text-align: right;">
                                        <a href="schedule.php?exam_id=<?= $row['exam_id'] ?>" class="btn btn-secondary" style="font-size: 0.78rem; padding: 5px 10px;">
                                            Edit Settings
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Table Pagination -->
            <?php if ($pagination && $pagination['total_pages'] > 1): ?>
                <div style="margin-top: 16px;">
                    <?= render_pagination($pagination, 'schedule.php') ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../common/footer.php'; ?>

</body>
</html>
