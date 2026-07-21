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
    SELECT s.name, s.exam_number, s.class, s.status, sc.school_name, sc.district
    FROM students s
    LEFT JOIN schools sc ON sc.school_id = s.school_id
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

        <!-- SCHOOL DETAILS CARD -->
        <div class="insight-card">
            <h4>School Affiliation</h4>

            <div class="insight-card__row">
                <span>School</span>
                <strong><?= htmlspecialchars($student['school_name'] ?? 'N/A') ?></strong>
            </div>

            <div class="insight-card__row">
                <span>District</span>
                <strong><?= htmlspecialchars($student['district'] ?? 'N/A') ?></strong>
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
