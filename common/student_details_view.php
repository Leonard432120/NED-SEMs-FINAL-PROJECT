<?php
/*
 * common/student_details_view.php
 *
 * Shared reusable student details component/partial.
 * Formatted with the same premium aesthetics as view_user.php.
 *
 * Variables required:
 *   $student_id (int)       - The student's ID
 *   $conn       (mysqli)    - Open database connection
 *   $show_print (bool)      - Optional. Whether to allow printing the subject slip.
 */

if (!isset($student_id) || $student_id <= 0 || !isset($conn)) {
    echo '<div class="alert alert-error">Invalid student or missing database connection.</div>';
    return;
}

// Fetch student details if not already passed
$stmt = $conn->prepare("
    SELECT s.name, s.exam_number, s.class, s.status
    FROM students s
    WHERE s.student_id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    echo '<div class="alert alert-error">Student not found.</div>';
    return;
}

$show_print = $show_print ?? false;

// Subject count
$subj_count_res = $conn->prepare("SELECT COUNT(*) AS cnt FROM student_subjects WHERE student_id = ?");
$subj_count_res->bind_param("i", $student_id);
$subj_count_res->execute();
$reg_subj_count = (int)$subj_count_res->get_result()->fetch_assoc()['cnt'];
$subj_count_res->close();
$display_subj = $reg_subj_count > 0 ? $reg_subj_count : 5;

// Marks submitted
$marks_res = $conn->prepare("SELECT COUNT(*) AS cnt FROM marks WHERE student_id = ? AND status IN ('submitted','approved')");
$marks_res->bind_param("i", $student_id);
$marks_res->execute();
$marks_count = (int)$marks_res->get_result()->fetch_assoc()['cnt'];
$marks_res->close();

// Results compiled
$results_res = $conn->prepare("SELECT COUNT(*) AS cnt FROM results WHERE student_id = ?");
$results_res->bind_param("i", $student_id);
$results_res->execute();
$results_count = (int)$results_res->get_result()->fetch_assoc()['cnt'];
$results_res->close();
?>
<div class="student-details-view">

    <!-- HEADER / AVATAR ROW -->
    <div class="section-header" style="margin-bottom: 20px;">
        <div style="display:flex;align-items:center;gap:16px;">
            <!-- AVATAR -->
            <div style="
                width:65px;
                height:65px;
                border-radius:50%;
                background:var(--primary-dark);
                color:#fff;
                display:flex;
                align-items:center;
                justify-content:center;
                font-size:24px;
                font-weight:700;
            ">
                <?= strtoupper(substr($student['name'], 0, 1)) ?>
            </div>

            <div>
                <h3 style="margin:0; font-size: 1.3rem;">
                    <?= htmlspecialchars($student['name']) ?>
                </h3>
                <p class="muted-text" style="margin: 2px 0 6px 0;">
                    <?= htmlspecialchars($student['exam_number']) ?>
                </p>
                <span class="badge badge-<?= $student['status'] === 'active' ? 'active' : 'inactive' ?>">
                    <?= ucfirst($student['status']) ?>
                </span>
            </div>
        </div>
    </div>

    <!-- INSIGHT GRID -->
    <div class="insight-grid">

        <!-- STUDENT PROFILE CARD -->
        <div class="insight-card">
            <h4>Student Information</h4>

            <div class="insight-card__row">
                <span>Exam Number</span>
                <strong><?= htmlspecialchars($student['exam_number']) ?></strong>
            </div>

            <div class="insight-card__row">
                <span>Class / Form</span>
                <strong><?= htmlspecialchars($student['class'] ?? 'N/A') ?></strong>
            </div>

            <div class="insight-card__row">
                <span>Status</span>
                <strong><?= ucfirst($student['status']) ?></strong>
            </div>
        </div>

        <!-- EXAM REGISTRATION SUMMARY CARD -->
        <div class="insight-card">
            <h4>Exam Registration Summary</h4>

            <div class="insight-card__row">
                <span>Registered Subjects</span>
                <strong><?= $display_subj ?><?= $reg_subj_count === 0 ? ' <span style="font-size:.75rem;color:#64748b;font-weight:400;">(default)</span>' : '' ?></strong>
            </div>

            <div class="insight-card__row">
                <span>Marks Submitted</span>
                <strong><?= $marks_count ?></strong>
            </div>

            <div class="insight-card__row">
                <span>Results Available</span>
                <strong><?= $results_count ?></strong>
            </div>
        </div>

    </div>

    <!-- SUBJECTS PANEL -->
    <?php
    $panel_student_id = $student_id;
    $panel_student_name = $student['name'];
    $panel_allow_print = $show_print;
    $panel_conn = $conn;
    include __DIR__ . '/student_subjects_panel.php';
    ?>

</div>
