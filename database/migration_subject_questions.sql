-- ============================================================
-- NED-SEMS: Subject-Based Question Management Migration
-- Adds exam_subject_id to questions table
-- Migrates existing data, drops old exam_id FK from questions
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- STEP 1: Add exam_subject_id column to questions
ALTER TABLE `questions`
  ADD COLUMN `exam_subject_id` INT NULL DEFAULT NULL
    COMMENT 'FK to exam_subjects(id) — replaces direct exam_id for subject scoping'
  AFTER `exam_id`;

-- STEP 2: Add index for performance
ALTER TABLE `questions`
  ADD INDEX `idx_question_exam_subject` (`exam_subject_id`);

-- STEP 3: Migrate existing questions
--   Q103: "EXLAIN ANY TWO WAYS OF MODERING." — teacher 127 → Mathematics (107) on exam 27
--   Q104: "what is agriculture ?" — contains "agriculture" → Agriculture (104) on exam 27
--   Q105: "bhhh" — teacher 127 → Mathematics (107) on exam 27
--   Q106: "where are you" — teacher 127 → Mathematics (107) on exam 27

UPDATE `questions` q
INNER JOIN `exam_subjects` es ON es.exam_id = q.exam_id AND es.subject_id = 107
SET q.exam_subject_id = es.id
WHERE q.question_id IN (103, 105, 106);

UPDATE `questions` q
INNER JOIN `exam_subjects` es ON es.exam_id = q.exam_id AND es.subject_id = 104
SET q.exam_subject_id = es.id
WHERE q.question_id IN (104);

-- For any remaining questions not yet mapped, fall back to the first subject for that exam
UPDATE `questions` q
INNER JOIN (
  SELECT exam_id, MIN(id) AS es_id
  FROM `exam_subjects`
  WHERE status = 'active'
  GROUP BY exam_id
) esf ON esf.exam_id = q.exam_id
SET q.exam_subject_id = esf.es_id
WHERE q.exam_subject_id IS NULL;

-- STEP 4: Add foreign key constraint
ALTER TABLE `questions`
  ADD CONSTRAINT `fk_question_exam_subject`
    FOREIGN KEY (`exam_subject_id`)
    REFERENCES `exam_subjects` (`id`)
    ON DELETE CASCADE;

-- STEP 5: Re-enable FK checks
SET FOREIGN_KEY_CHECKS = 1;

-- STEP 6: Verification — show result
SELECT
  q.question_id,
  q.exam_id,
  q.exam_subject_id,
  es.subject_id,
  s.subject_name,
  e.exam_name,
  q.question_text
FROM questions q
JOIN exam_subjects es ON q.exam_subject_id = es.id
JOIN subjects s ON es.subject_id = s.subject_id
JOIN exams e ON es.exam_id = e.exam_id
ORDER BY q.question_id;
