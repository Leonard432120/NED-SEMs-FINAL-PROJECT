<?php
/*
 * common/student_subjects_panel.php
 *
 * Shared partial: renders a student's registered exam subjects.
 * Included in Examination Officer, Headteacher, and Admin views.
 *
 * Required variables (must be set by the including page):
 *   $panel_student_id   (int)    — the student's ID
 *   $panel_student_name (string) — display name for the section heading
 *   $panel_allow_print  (bool)   — whether to show the print-slip link
 *   $panel_conn         (mysqli) — an open, active database connection
 *
 * Optional:
 *   $panel_slip_base_url (string) — base URL for the print-slip link
 *                                   defaults to BASE_URL . '/examination_officer/subject_slip.php'
 */

$panel_student_id   = (int)($panel_student_id   ?? 0);
$panel_student_name = htmlspecialchars($panel_student_name ?? 'Student');
$panel_allow_print  = (bool)($panel_allow_print  ?? false);
$panel_slip_base_url = $panel_slip_base_url ?? (BASE_URL . '/examination_officer/subject_slip.php');

if ($panel_student_id <= 0 || !isset($panel_conn)) {
    echo '<p style="color:var(--danger-color);font-size:.85rem;">Subject panel error: missing student ID or database connection.</p>';
    return;
}

$sq = $panel_conn->prepare("
    SELECT s.subject_id, s.subject_name, s.subject_code, s.category
    FROM student_subjects ss
    JOIN subjects s ON s.subject_id = ss.subject_id
    WHERE ss.student_id = ?
    ORDER BY s.subject_name ASC
");
$sq->bind_param("i", $panel_student_id);
$sq->execute();
$panel_subjects = $sq->get_result()->fetch_all(MYSQLI_ASSOC);
$sq->close();

$count = count($panel_subjects);

$category_css_map = [
    'science'    => 'subject-chip--science',
    'language'   => 'subject-chip--language',
    'humanities' => 'subject-chip--humanities',
];
?>
<div class="subjects-panel">
    <div class="subjects-panel__header">
        <span class="subjects-panel__title">
            Registered Exam Subjects
            <span class="subject-count-badge <?= $count === 0 ? 'none' : '' ?>">
                <?= $count ?>
            </span>
        </span>
        <?php if ($panel_allow_print && $count > 0): ?>
            <div class="subjects-panel__actions">
                <a href="<?= htmlspecialchars($panel_slip_base_url) ?>?student_id=<?= $panel_student_id ?>"
                   target="_blank"
                   class="btn btn-secondary btn-small"
                   title="Open printable subject registration slip">
                    Print Slip
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($count === 0): ?>
        <p class="subjects-panel__empty">No subjects registered for this student yet.</p>
    <?php else: ?>
        <div class="subject-grid">
            <?php foreach ($panel_subjects as $subj):
                $cat_key = strtolower(trim($subj['category'] ?? ''));
                $chip_css = $category_css_map[$cat_key] ?? 'subject-chip--default';
            ?>
                <span class="subject-chip <?= $chip_css ?>"
                      title="<?= htmlspecialchars($subj['subject_name']) ?> (<?= htmlspecialchars(ucfirst($subj['category'] ?: 'General')) ?>)">
                    <span class="subject-chip__code"><?= htmlspecialchars($subj['subject_code']) ?></span>
                    <?= htmlspecialchars($subj['subject_name']) ?>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
