<?php
/**
 * database/migrate_tpm.php
 * ─────────────────────────────────────────────────────────────────
 * Idempotent DB migration script: creates tables:
 *  - `teacher_performance_history`
 *  - `teacher_performance_reviews`
 *
 * Safe to execute multiple times.
 * ─────────────────────────────────────────────────────────────────
 */

if (php_sapi_name() !== 'cli' && (!isset($_SERVER['REMOTE_ADDR']) || $_SERVER['REMOTE_ADDR'] !== '127.0.0.1')) {
    http_response_code(403);
    exit('Forbidden: CLI only or local environment only');
}

require_once __DIR__ . '/../config/db.php';
$conn = get_db_connection();

echo "NED-SEMS Teacher Performance Management Tables Migration\n";
echo str_repeat('=', 60) . "\n";

// Table 1: teacher_performance_history
$sql_history = "
CREATE TABLE IF NOT EXISTS `teacher_performance_history` (
  `history_id` INT NOT NULL AUTO_INCREMENT,
  `teacher_id` INT NOT NULL,
  `school_id` INT NOT NULL,
  `term` VARCHAR(20) NOT NULL,
  `year` INT NOT NULL,
  `overall_score` DECIMAL(5,2) NOT NULL,
  `ranking` INT DEFAULT NULL,
  `promotion_status` VARCHAR(50) DEFAULT NULL,
  `recommendation` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  UNIQUE KEY `uq_teacher_history_snap` (`teacher_id`, `term`, `year`),
  KEY `idx_tph_school` (`school_id`),
  CONSTRAINT `fk_tph_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($sql_history) === TRUE) {
    echo "[OK]  teacher_performance_history — created or exists\n";
} else {
    echo "[ERR] teacher_performance_history — " . $conn->error . "\n";
}

// Table 2: teacher_performance_reviews
$sql_reviews = "
CREATE TABLE IF NOT EXISTS `teacher_performance_reviews` (
  `review_id` INT NOT NULL AUTO_INCREMENT,
  `teacher_id` INT NOT NULL,
  `school_id` INT NOT NULL,
  `meeting_date` DATE NOT NULL,
  `improvement_plan` TEXT DEFAULT NULL,
  `advice_given` TEXT DEFAULT NULL,
  `follow_up_date` DATE DEFAULT NULL,
  `review_status` ENUM('Pending', 'In Progress', 'Resolved') NOT NULL DEFAULT 'Pending',
  `comments` TEXT DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`review_id`),
  KEY `idx_tpr_teacher` (`teacher_id`),
  KEY `idx_tpr_school` (`school_id`),
  CONSTRAINT `fk_tpr_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tpr_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($sql_reviews) === TRUE) {
    echo "[OK]  teacher_performance_reviews — created or exists\n";
} else {
    echo "[ERR] teacher_performance_reviews — " . $conn->error . "\n";
}

$conn->close();
echo "\nMigration complete. Script execution finished.\n";
echo str_repeat('=', 60) . "\n";
