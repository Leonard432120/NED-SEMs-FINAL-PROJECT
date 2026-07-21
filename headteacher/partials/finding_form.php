<?php
/**
 * headteacher/partials/finding_form.php
 * ─────────────────────────────────────────────────────────────────
 * Reusable form partial: log / edit a structured finding.
 * Included by reports.php in two contexts:
 *   1. New finding: $prefill = null
 *   2. Edit open finding: $prefill = $current_finding (associative array)
 *
 * Required in scope: $selected_exam_id, $school_id, $pass_rate, $trend_direction
 * All values are htmlspecialchars()-escaped before output.
 * ─────────────────────────────────────────────────────────────────
 */

// Determine pre-fill values (safe defaults if no prefill)
$pf_trend    = htmlspecialchars($prefill['trend']            ?? $trend_direction);
$pf_cause    = htmlspecialchars($prefill['cause_category']   ?? '');
$pf_cdetail  = htmlspecialchars($prefill['cause_detail']     ?? '');
$pf_action   = htmlspecialchars($prefill['action_category']  ?? '');
$pf_adetail  = htmlspecialchars($prefill['action_detail']    ?? '');
$pf_rate     = htmlspecialchars($prefill['pass_rate_pct']    ?? $pass_rate);

$causes = [
    'teacher_absenteeism'     => 'Teacher Absenteeism',
    'resource_shortage'       => 'Resource Shortage',
    'curriculum_gap'          => 'Curriculum Gap',
    'student_discipline'      => 'Student Discipline',
    'assessment_irregularity' => 'Assessment Irregularity',
    'illness_outbreak'        => 'Illness / Outbreak',
    'staff_turnover'          => 'Staff Turnover',
    'low_attendance'          => 'Low Attendance',
    'external_disruption'     => 'External Disruption',
    'positive_intervention'   => 'Positive Intervention',
    'other'                   => 'Other',
];
$actions = [
    'remedial_classes'        => 'Remedial Classes',
    'staff_redeployment'      => 'Staff Redeployment',
    'resource_procurement'    => 'Resource Procurement',
    'parent_engagement'       => 'Parent Engagement',
    'curriculum_revision'     => 'Curriculum Revision',
    'attendance_campaign'     => 'Attendance Campaign',
    'pastoral_support'        => 'Pastoral Support',
    'peer_mentoring'          => 'Peer Mentoring Programme',
    'teacher_cpd'             => 'Teacher CPD / Training',
    'celebration_recognition' => 'Celebration & Recognition',
    'no_action_yet'           => 'No Action Yet',
    'other'                   => 'Other',
];
?>
<form method="POST"
      action="<?= BASE_URL ?>/headteacher/routes/save_finding.php"
      class="htf-finding-form"
      id="finding-form-<?= $selected_exam_id ?>">

  <!-- Hidden fields — do NOT take school_id from POST; it comes from session in the handler -->
  <input type="hidden" name="exam_id"      value="<?= (int)$selected_exam_id ?>">
  <input type="hidden" name="trend"        value="<?= $pf_trend ?>">
  <input type="hidden" name="pass_rate_pct" value="<?= $pf_rate ?>">

  <div class="htf-form-grid">

    <!-- LEFT: Cause -->
    <div class="htf-form-section">
      <div class="htf-form-section-title">
        Root Cause
        <span class="htf-form-required-note">* Required</span>
      </div>

      <div class="htf-form-group">
        <label class="htf-label" for="cause_category_<?= $selected_exam_id ?>">
          What caused this outcome? <span class="htf-required">*</span>
        </label>
        <select name="cause_category"
                id="cause_category_<?= $selected_exam_id ?>"
                class="rpt-filter-select htf-select"
                required>
          <option value="">— Select the primary cause —</option>
          <?php foreach ($causes as $slug => $label): ?>
            <option value="<?= $slug ?>"
              <?= $pf_cause === $slug ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="htf-form-group">
        <label class="htf-label" for="cause_detail_<?= $selected_exam_id ?>">
          Explain the cause in your own words
          <span class="htf-label-hint">(optional — helps future searches)</span>
        </label>
        <textarea name="cause_detail"
                  id="cause_detail_<?= $selected_exam_id ?>"
                  class="htf-textarea"
                  rows="3"
                  maxlength="1000"
                  placeholder="e.g. Three teachers resigned mid-term; their classes were split across remaining staff without adequate handover…"><?= $pf_cdetail ?></textarea>
      </div>
    </div>

    <!-- RIGHT: Action -->
    <div class="htf-form-section">
      <div class="htf-form-section-title">
        Response &amp; Action
        <span class="htf-form-required-note">* Required</span>
      </div>

      <div class="htf-form-group">
        <label class="htf-label" for="action_category_<?= $selected_exam_id ?>">
          What action did you take (or are taking)? <span class="htf-required">*</span>
        </label>
        <select name="action_category"
                id="action_category_<?= $selected_exam_id ?>"
                class="rpt-filter-select htf-select"
                required>
          <option value="">— Select the primary action —</option>
          <?php foreach ($actions as $slug => $label): ?>
            <option value="<?= $slug ?>"
              <?= $pf_action === $slug ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="htf-form-group">
        <label class="htf-label" for="action_detail_<?= $selected_exam_id ?>">
          Describe the action specifically
          <span class="htf-label-hint">(optional — becomes evidence for future cycles)</span>
        </label>
        <textarea name="action_detail"
                  id="action_detail_<?= $selected_exam_id ?>"
                  class="htf-textarea"
                  rows="3"
                  maxlength="1000"
                  placeholder="e.g. Ran Saturday revision sessions for Form 4 Mathematics every week from March to May; hired two contract teachers through DEMA…"><?= $pf_adetail ?></textarea>
      </div>
    </div>

  </div><!-- /htf-form-grid -->

  <div class="htf-form-actions">
    <button type="submit" class="btn btn-primary">
      <?= $prefill ? 'Update Finding' : 'Save Investigation Finding' ?>
    </button>
    <span class="htf-form-note">
      This finding is scoped to your school only and will never be shared with other schools.
    </span>
  </div>

</form>
