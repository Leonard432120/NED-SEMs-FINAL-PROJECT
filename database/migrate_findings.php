<?php
/**
 * database/migrate_findings.php
 * ─────────────────────────────────────────────────────────────────
 * Idempotent migration: creates ht_findings and ht_finding_outcomes.
 * Safe to re-run — uses CREATE TABLE IF NOT EXISTS throughout.
 * Does NOT drop or alter any existing tables.
 *
 * Usage: visit /NED-SEMs FINAL YEAR PROJECT/database/migrate_findings.php
 *        from localhost only (guarded below).
 * ─────────────────────────────────────────────────────────────────
 */

// ── Localhost guard ──────────────────────────────────────────────
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Migration scripts are only accessible from localhost.');
}

require_once __DIR__ . '/../config/db.php';
$conn = get_db_connection();

header('Content-Type: text/plain; charset=utf-8');
echo "NED-SEMS Findings Migration\n";
echo str_repeat('=', 50) . "\n\n";

// ─────────────────────────────────────────────────────────────────
// TABLE 1: ht_findings
// One structured finding per (school_id, exam_id).
// ─────────────────────────────────────────────────────────────────
$sql_findings = "
CREATE TABLE IF NOT EXISTS `ht_findings` (
  `finding_id`       INT          NOT NULL AUTO_INCREMENT,
  `school_id`        INT          NOT NULL,
  `exam_id`          INT          NOT NULL,
  `recorded_by`      INT          NOT NULL,
  `trend`            ENUM('declining','improving','flat') NOT NULL,
  `pass_rate_pct`    DECIMAL(5,2) NOT NULL COMMENT 'Snapshot pass rate at time of logging',

  -- Root cause (structured category + optional free-text detail)
  `cause_category`   ENUM(
    'teacher_absenteeism',
    'resource_shortage',
    'curriculum_gap',
    'student_discipline',
    'assessment_irregularity',
    'illness_outbreak',
    'staff_turnover',
    'low_attendance',
    'external_disruption',
    'positive_intervention',
    'other'
  ) NOT NULL,
  `cause_detail`     TEXT DEFAULT NULL,

  -- Action taken (structured category + optional free-text detail)
  `action_category`  ENUM(
    'remedial_classes',
    'staff_redeployment',
    'resource_procurement',
    'parent_engagement',
    'curriculum_revision',
    'attendance_campaign',
    'pastoral_support',
    'peer_mentoring',
    'teacher_cpd',
    'celebration_recognition',
    'no_action_yet',
    'other'
  ) NOT NULL,
  `action_detail`    TEXT DEFAULT NULL,

  `lifecycle_status` ENUM('open','closed') NOT NULL DEFAULT 'open',
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`finding_id`),
  UNIQUE KEY `uq_school_exam` (`school_id`, `exam_id`),
  KEY `idx_school_trend`  (`school_id`, `trend`),
  KEY `idx_cause_cat`     (`cause_category`),
  KEY `idx_lifecycle`     (`lifecycle_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Headteacher structured findings per exam cycle'
";

if ($conn->query($sql_findings) === true) {
    echo "[OK]  ht_findings — created (or already exists, skipped)\n";
} else {
    echo "[ERR] ht_findings — " . $conn->error . "\n";
}

// ─────────────────────────────────────────────────────────────────
// TABLE 2: ht_finding_outcomes
// Closes the loop: headteacher records whether the action worked
// at the NEXT exam cycle.
// ─────────────────────────────────────────────────────────────────
$sql_outcomes = "
CREATE TABLE IF NOT EXISTS `ht_finding_outcomes` (
  `outcome_id`     INT NOT NULL AUTO_INCREMENT,
  `finding_id`     INT NOT NULL,
  `school_id`      INT NOT NULL COMMENT 'Denormalised for scoping queries',
  `exam_id`        INT NOT NULL COMMENT 'The next exam when outcome was assessed',
  `recorded_by`    INT NOT NULL,
  `outcome`        ENUM('improved','no_change','worsened') NOT NULL,
  `outcome_detail` TEXT DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`outcome_id`),
  UNIQUE KEY `uq_finding_outcome` (`finding_id`),
  KEY `idx_school_outcome` (`school_id`, `outcome`),
  CONSTRAINT `fk_outcome_finding`
    FOREIGN KEY (`finding_id`) REFERENCES `ht_findings` (`finding_id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Outcome assessment for a prior headteacher finding'
";

if ($conn->query($sql_outcomes) === true) {
    echo "[OK]  ht_finding_outcomes — created (or already exists, skipped)\n";
} else {
    echo "[ERR] ht_finding_outcomes — " . $conn->error . "\n";
}

// ─────────────────────────────────────────────────────────────────
// TABLE 3: div_findings
// One structured strategic finding per exam cycle at the division level.
// ─────────────────────────────────────────────────────────────────
$sql_div_findings = "
CREATE TABLE IF NOT EXISTS `div_findings` (
  `finding_id`       INT          NOT NULL AUTO_INCREMENT,
  `exam_id`          INT          NOT NULL,
  `recorded_by`      INT          NOT NULL,
  `trend`            ENUM('declining','improving','flat') NOT NULL,
  `avg_score_pct`    DECIMAL(5,2) NOT NULL COMMENT 'Division average score at time of logging',

  -- Strategic cause (structured category + optional detail)
  `cause_category`   ENUM(
    'teacher_shortage',
    'funding_delays',
    'learning_material_deficiency',
    'curriculum_misalignment',
    'teacher_compliance_low',
    'extreme_weather',
    'administrative_laxity',
    'positive_divisional_reform',
    'other'
  ) NOT NULL,
  `cause_detail`     TEXT DEFAULT NULL,

  -- Strategic action taken
  `action_category`  ENUM(
    'teacher_recruitment',
    'budget_allocation',
    'textbook_distribution',
    'inspection_blitz',
    'teacher_capacity_building',
    'remedial_policy_mandate',
    'divisional_recognition',
    'other'
  ) NOT NULL,
  `action_detail`    TEXT DEFAULT NULL,

  `lifecycle_status` ENUM('open','closed') NOT NULL DEFAULT 'open',
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`finding_id`),
  UNIQUE KEY `uq_div_exam` (`exam_id`),
  KEY `idx_div_trend` (`trend`),
  KEY `idx_div_cause` (`cause_category`),
  KEY `idx_div_lifecycle` (`lifecycle_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Division-level strategic findings per exam cycle'
";

if ($conn->query($sql_div_findings) === true) {
    echo "[OK]  div_findings — created (or already exists, skipped)\n";
} else {
    echo "[ERR] div_findings — " . $conn->error . "\n";
}

// ─────────────────────────────────────────────────────────────────
// TABLE 4: div_finding_outcomes
// Assessment of division strategic actions in subsequent exam cycles.
// ─────────────────────────────────────────────────────────────────
$sql_div_outcomes = "
CREATE TABLE IF NOT EXISTS `div_finding_outcomes` (
  `outcome_id`     INT NOT NULL AUTO_INCREMENT,
  `finding_id`     INT NOT NULL,
  `exam_id`        INT NOT NULL COMMENT 'The next exam when outcome was assessed',
  `recorded_by`    INT NOT NULL,
  `outcome`        ENUM('improved','no_change','worsened') NOT NULL,
  `outcome_detail` TEXT DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`outcome_id`),
  UNIQUE KEY `uq_div_finding_outcome` (`finding_id`),
  CONSTRAINT `fk_div_outcome_finding`
    FOREIGN KEY (`finding_id`) REFERENCES `div_findings` (`finding_id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Strategic outcome assessment for division-level findings'
";

if ($conn->query($sql_div_outcomes) === true) {
    echo "[OK]  div_finding_outcomes — created (or already exists, skipped)\n";
} else {
    echo "[ERR] div_finding_outcomes — " . $conn->error . "\n";
}

$conn->close();

echo "\nMigration complete. This script is safe to re-run.\n";
echo str_repeat('=', 50) . "\n";

