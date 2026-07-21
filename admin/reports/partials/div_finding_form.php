<?php
/**
 * admin/reports/partials/div_finding_form.php
 * ─────────────────────────────────────────────────────────────────
 * Form partial: log / edit a structured finding at the division level.
 * Gated to admin users.
 * ─────────────────────────────────────────────────────────────────
 */

$pf_trend    = htmlspecialchars($prefill['trend']            ?? $trend_direction);
$pf_cause    = htmlspecialchars($prefill['cause_category']   ?? '');
$pf_cdetail  = htmlspecialchars($prefill['cause_detail']     ?? '');
$pf_action   = htmlspecialchars($prefill['action_category']  ?? '');
$pf_adetail  = htmlspecialchars($prefill['action_detail']    ?? '');
$pf_rate     = htmlspecialchars($prefill['avg_score_pct']    ?? $division_avg);

$causes = [
    'teacher_shortage'             => 'Teacher Shortage (Division-wide)',
    'funding_delays'               => 'Funding Delays to Schools',
    'learning_material_deficiency' => 'Deficiency in Learning Materials',
    'curriculum_misalignment'      => 'Curriculum Misalignment / Rollout Issues',
    'teacher_compliance_low'       => 'Low Teacher Submission / Compliance',
    'extreme_weather'              => 'Extreme Weather / Disaster Disruption',
    'administrative_laxity'        => 'Administrative Laxity / Lack of Supervision',
    'positive_divisional_reform'   => 'Positive Divisional Policy Reforms',
    'other'                        => 'Other Strategic Factors'
];

$actions = [
    'teacher_recruitment'       => 'Hiring / Deploying Teaching Staff',
    'budget_allocation'         => 'Emergency Funding Allocation',
    'textbook_distribution'     => 'Procuring & Distributing Textbooks/Materials',
    'inspection_blitz'          => 'Strategic School Inspection Campaigns',
    'teacher_capacity_building' => 'Syllabus CPD Capacity Building Seminars',
    'remedial_policy_mandate'   => 'Mandatory Remedial Class Requirements',
    'divisional_recognition'    => 'Strategic Center Performance Awards',
    'other'                     => 'Other Intervention Programs'
];
?>
<form method="POST"
      action="<?= BASE_URL ?>/admin/routes/save_div_finding.php"
      class="htf-finding-form"
      id="div-finding-form-<?= $selected_exam_id ?>">

  <input type="hidden" name="exam_id"       value="<?= (int)$selected_exam_id ?>">
  <input type="hidden" name="trend"         value="<?= $pf_trend ?>">
  <input type="hidden" name="avg_score_pct" value="<?= $pf_rate ?>">

  <div class="htf-form-grid">

    <!-- LEFT: Cause -->
    <div class="htf-form-section">
      <div class="htf-form-section-title">
        Root Cause Analysis
        <span class="htf-form-required-note">* Required</span>
      </div>

      <div class="htf-form-group">
        <label class="htf-label" for="cause_category_<?= $selected_exam_id ?>">
          What is the primary division-wide cause? <span class="htf-required">*</span>
        </label>
        <select name="cause_category"
                id="cause_category_<?= $selected_exam_id ?>"
                class="rpt-filter-select htf-select"
                required>
          <option value="">— Select primary factor —</option>
          <?php foreach ($causes as $slug => $label): ?>
            <option value="<?= $slug ?>" <?= $pf_cause === $slug ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="htf-form-group">
        <label class="htf-label" for="cause_detail_<?= $selected_exam_id ?>">
          Details / Strategic Findings
          <span class="htf-label-hint">(optional)</span>
        </label>
        <textarea name="cause_detail"
                  id="cause_detail_<?= $selected_exam_id ?>"
                  class="htf-textarea"
                  rows="3"
                  maxlength="1000"
                  placeholder="e.g. Audit reveals multiple community day secondary schools are missing mathematics teachers for form 4 classes..."><?= $pf_cdetail ?></textarea>
      </div>
    </div>

    <!-- RIGHT: Action -->
    <div class="htf-form-section">
      <div class="htf-form-section-title">
        Strategic Intervention Response
        <span class="htf-form-required-note">* Required</span>
      </div>

      <div class="htf-form-group">
        <label class="htf-label" for="action_category_<?= $selected_exam_id ?>">
          What division-wide action is being taken? <span class="htf-required">*</span>
        </label>
        <select name="action_category"
                id="action_category_<?= $selected_exam_id ?>"
                class="rpt-filter-select htf-select"
                required>
          <option value="">— Select primary response —</option>
          <?php foreach ($actions as $slug => $label): ?>
            <option value="<?= $slug ?>" <?= $pf_action === $slug ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="htf-form-group">
        <label class="htf-label" for="action_detail_<?= $selected_exam_id ?>">
          Describe the action specifically
          <span class="htf-label-hint">(optional)</span>
        </label>
        <textarea name="action_detail"
                  id="action_detail_<?= $selected_exam_id ?>"
                  class="htf-textarea"
                  rows="3"
                  maxlength="1000"
                  placeholder="e.g. Initiated divisional teacher swap to deploy biology instructors to underperforming CDSS centers..."><?= $pf_adetail ?></textarea>
      </div>
    </div>

  </div>

  <div class="htf-form-actions">
    <button type="submit" class="btn btn-primary">
      <?= $prefill ? 'Update Strategic Finding' : 'Save Strategic Finding' ?>
    </button>
  </div>

</form>
