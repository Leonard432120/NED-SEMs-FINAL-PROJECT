-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 11, 2026 at 06:02 AM
-- Server version: 8.4.7
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ned_sems`
--

-- --------------------------------------------------------

--
-- Table structure for table `ai_alerts`
--

DROP TABLE IF EXISTS `ai_alerts`;
CREATE TABLE IF NOT EXISTS `ai_alerts` (
  `alert_id` int NOT NULL AUTO_INCREMENT,
  `type` enum('exam_leakage','result_anomaly','access_violation') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `related_exam` int DEFAULT NULL,
  `severity` enum('low','medium','high') COLLATE utf8mb4_unicode_ci DEFAULT 'low',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`alert_id`),
  KEY `related_exam` (`related_exam`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

DROP TABLE IF EXISTS `exams`;
CREATE TABLE IF NOT EXISTS `exams` (
  `exam_id` int NOT NULL AUTO_INCREMENT,
  `exam_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` int NOT NULL,
  `created_by` int NOT NULL,
  `exam_date` date DEFAULT NULL,
  `duration_minutes` int DEFAULT NULL,
  `total_marks` int DEFAULT NULL,
  `status` enum('draft','assigned','submitted','under_moderation','needs_revision','approved','rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `year` year DEFAULT NULL,
  `class` enum('Form 1','Form 2') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`exam_id`),
  KEY `fk_exam_subject` (`subject_id`),
  KEY `fk_exam_creator` (`created_by`),
  KEY `idx_exam_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `exams`
--

INSERT INTO `exams` (`exam_id`, `exam_name`, `subject_id`, `created_by`, `exam_date`, `duration_minutes`, `total_marks`, `status`, `year`, `class`) VALUES
(1, 'COMPUTER', 1, 2, '2026-03-03', 60, 40, 'approved', '2022', 'Form 2'),
(2, 'FORM', 2, 2, '2026-08-10', 90, 100, 'approved', '2026', 'Form 2'),
(8, 'Math Test 2022', 1, 2, NULL, NULL, NULL, 'draft', '2022', 'Form 1'),
(9, 'Math Test 2023', 1, 2, NULL, NULL, NULL, 'draft', '2023', 'Form 1'),
(10, 'Math Test 2024', 1, 2, NULL, NULL, NULL, 'draft', '2024', 'Form 1'),
(11, 'Math Test 2025', 1, 2, NULL, NULL, NULL, 'draft', '2025', 'Form 1'),
(12, 'Math Test 2026', 1, 2, NULL, NULL, NULL, 'draft', '2026', 'Form 1'),
(13, 'Math Test 2022', 1, 2, NULL, NULL, NULL, 'draft', '2022', 'Form 1'),
(14, 'Math Test 2023', 1, 2, NULL, NULL, NULL, 'draft', '2023', 'Form 1'),
(15, 'Math Test 2024', 1, 2, NULL, NULL, NULL, 'draft', '2024', 'Form 1'),
(16, 'Math Test 2025', 1, 2, NULL, NULL, NULL, 'draft', '2025', 'Form 1'),
(17, 'Math Test 2026', 1, 2, NULL, NULL, NULL, 'draft', '2026', 'Form 1');

-- --------------------------------------------------------

--
-- Table structure for table `exam_assignments`
--

DROP TABLE IF EXISTS `exam_assignments`;
CREATE TABLE IF NOT EXISTS `exam_assignments` (
  `assignment_id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `teacher_id` int NOT NULL,
  `role` enum('item_writer','moderator') COLLATE utf8mb4_unicode_ci NOT NULL,
  `assigned_by` int NOT NULL,
  `assigned_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `email_sent` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`assignment_id`),
  UNIQUE KEY `unique_assignment` (`exam_id`,`teacher_id`,`role`),
  KEY `assigned_by` (`assigned_by`),
  KEY `fk_assign_teacher` (`teacher_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `exam_assignments`
--

INSERT INTO `exam_assignments` (`assignment_id`, `exam_id`, `teacher_id`, `role`, `assigned_by`, `assigned_at`, `email_sent`) VALUES
(17, 1, 60, 'moderator', 2, '2026-05-02 15:27:24', 0),
(18, 2, 60, 'item_writer', 2, '2026-05-02 15:28:45', 0);

-- --------------------------------------------------------

--
-- Table structure for table `exam_comments`
--

DROP TABLE IF EXISTS `exam_comments`;
CREATE TABLE IF NOT EXISTS `exam_comments` (
  `comment_id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `user_id` int NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`comment_id`),
  KEY `exam_id` (`exam_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exam_documents`
--

DROP TABLE IF EXISTS `exam_documents`;
CREATE TABLE IF NOT EXISTS `exam_documents` (
  `document_id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `uploaded_by` int NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type` enum('question_paper','marking_scheme','attachment') COLLATE utf8mb4_unicode_ci DEFAULT 'question_paper',
  `version_number` int DEFAULT '1',
  `is_current` tinyint(1) DEFAULT '1',
  `uploaded_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`document_id`),
  KEY `exam_id` (`exam_id`),
  KEY `uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `exam_documents`
--

INSERT INTO `exam_documents` (`document_id`, `exam_id`, `uploaded_by`, `file_path`, `document_type`, `version_number`, `is_current`, `uploaded_at`) VALUES
(1, 1, 48, 'static/uploads/exams\\Introduction_to_MS_Word.pdf', 'question_paper', 1, 0, '2026-04-12 11:50:34'),
(2, 1, 48, 'static/uploads/exams\\NED-SES_SRS.pdf', 'question_paper', 2, 1, '2026-04-12 15:09:44');

-- --------------------------------------------------------

--
-- Table structure for table `exam_workflow_logs`
--

DROP TABLE IF EXISTS `exam_workflow_logs`;
CREATE TABLE IF NOT EXISTS `exam_workflow_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `performed_by` int DEFAULT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`log_id`),
  KEY `exam_id` (`exam_id`),
  KEY `performed_by` (`performed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `moderation`
--

DROP TABLE IF EXISTS `moderation`;
CREATE TABLE IF NOT EXISTS `moderation` (
  `moderation_id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `reviewer_id` int NOT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `status` enum('approved','rejected','needs_revision') COLLATE utf8mb4_unicode_ci NOT NULL,
  `review_date` datetime DEFAULT NULL,
  `version_number` int DEFAULT NULL,
  PRIMARY KEY (`moderation_id`),
  KEY `exam_id` (`exam_id`),
  KEY `reviewer_id` (`reviewer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `moderation`
--

INSERT INTO `moderation` (`moderation_id`, `exam_id`, `reviewer_id`, `comments`, `status`, `review_date`, `version_number`) VALUES
(1, 1, 48, 'this is good ', 'needs_revision', '2026-04-12 14:52:22', NULL),
(2, 1, 48, 'yes', 'needs_revision', '2026-04-12 15:12:56', NULL),
(3, 1, 48, 'yes', 'needs_revision', '2026-04-12 15:17:51', NULL),
(4, 1, 47, 'good', 'needs_revision', '2026-04-27 18:20:34', NULL),
(5, 2, 60, 'this is good', 'needs_revision', '2026-05-06 14:04:41', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

DROP TABLE IF EXISTS `questions`;
CREATE TABLE IF NOT EXISTS `questions` (
  `question_id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `question_order` int DEFAULT NULL,
  `question_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `marks` int NOT NULL,
  `created_by` int DEFAULT NULL,
  `section_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Section A',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `question_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'short',
  `option_a` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `option_b` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `option_c` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `option_d` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correct_option` char(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ai_score` float DEFAULT '1',
  `moderation_status` enum('pending','approved','revise','rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `moderator_comment` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`question_id`),
  KEY `fk_question_exam` (`exam_id`),
  KEY `idx_question_moderation` (`moderation_status`)
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`question_id`, `exam_id`, `question_order`, `question_text`, `marks`, `created_by`, `section_name`, `created_at`, `question_type`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`, `ai_score`, `moderation_status`, `moderator_comment`) VALUES
(68, 1, 1, 'what is agriculture?', 20, 47, 'Section B', '2026-04-30 16:58:30', 'structured', '', '', '', '', '', 1, 'approved', NULL),
(69, 1, 2, 'who are you?', 3, 47, 'Section B', '2026-04-30 17:46:03', 'structured', '', '', '', '', '', 1, 'approved', NULL),
(70, 1, 1, 'How many kilometers are ther?\r\n', 3, 60, 'Section B', '2026-05-06 10:11:30', 'structured', '', '', '', '', '', 1, 'revise', NULL),
(71, 1, 1, 'what is poly?', 1, 60, 'Section A', '2026-05-07 07:48:00', 'mcq', 'you', '2', 'nnn', 'they', 'B', 1, 'approved', NULL),
(72, 1, 2, 'what is it?', 1, 60, 'Section A', '2026-05-07 07:48:26', 'mcq', 'eeee', '2', 'nnn', 'they', 'C', 1, 'revise', NULL),
(73, 1, 3, 'why they don\'t like me?', 3, 60, 'Section B', '2026-05-07 07:49:15', 'structured', '', '', '', '', '', 1, 'revise', NULL),
(74, 1, 5, 'describe the use of computr in malawi?', 5, 60, 'Section B', '2026-05-07 07:49:51', 'structured', '', '', '', '', '', 1, 'approved', NULL),
(75, 1, 6, 'explain 10 ways of creating a program in vs code?', 9, 60, 'Section C', '2026-05-07 07:50:32', 'essay', '', '', '', '', '', 1, 'approved', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `question_moderation`
--

DROP TABLE IF EXISTS `question_moderation`;
CREATE TABLE IF NOT EXISTS `question_moderation` (
  `moderation_id` int NOT NULL AUTO_INCREMENT,
  `question_id` int DEFAULT NULL,
  `moderator_id` int DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `moderated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`moderation_id`),
  UNIQUE KEY `question_id` (`question_id`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `question_moderation`
--

INSERT INTO `question_moderation` (`moderation_id`, `question_id`, `moderator_id`, `status`, `comment`, `moderated_at`) VALUES
(1, 68, 60, 'approved', '', '2026-05-07 08:04:42'),
(2, 69, 60, 'approved', '', '2026-05-07 08:04:42'),
(3, 70, 60, 'revise', '', '2026-05-07 08:04:42'),
(4, 71, 60, 'approved', '', '2026-05-07 08:04:42'),
(5, 72, 60, 'revise', '', '2026-05-07 08:04:42'),
(6, 73, 60, 'revise', '', '2026-05-07 08:04:42'),
(7, 74, 60, 'approved', '', '2026-05-07 08:04:42'),
(8, 75, 60, 'approved', '', '2026-05-07 08:04:42');

-- --------------------------------------------------------

--
-- Table structure for table `results`
--

DROP TABLE IF EXISTS `results`;
CREATE TABLE IF NOT EXISTS `results` (
  `result_id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `exam_id` int NOT NULL,
  `total_score` decimal(5,2) DEFAULT NULL,
  `grade` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `position_in_class` int DEFAULT NULL,
  `percentage` decimal(5,2) DEFAULT NULL,
  `recorded_by` int DEFAULT NULL,
  `recorded_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` enum('draft','submitted','eo_approved','head_approved','edm_approved','rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `teacher_id` int DEFAULT NULL,
  `editable_until` datetime DEFAULT NULL,
  `locked` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`result_id`),
  UNIQUE KEY `unique_result` (`student_id`,`exam_id`),
  KEY `recorded_by` (`recorded_by`),
  KEY `fk_result_exam` (`exam_id`),
  KEY `idx_results_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `results`
--

INSERT INTO `results` (`result_id`, `student_id`, `exam_id`, `total_score`, `grade`, `remarks`, `position_in_class`, `percentage`, `recorded_by`, `recorded_at`, `status`, `teacher_id`, `editable_until`, `locked`) VALUES
(16, 17, 1, 10.00, 'F', 'Fail', 2, 25.00, 52, '2026-04-18 03:55:46', '', 52, '2026-04-25 03:55:46', 0),
(20, 16, 1, 20.00, 'C', 'Pass', 1, 50.00, 60, '2026-05-09 22:42:59', '', NULL, NULL, 0),
(39, 1, 1, 42.00, NULL, NULL, NULL, 42.00, NULL, '2026-05-09 22:56:02', 'draft', NULL, NULL, 0),
(40, 2, 1, 45.00, NULL, NULL, NULL, 45.00, NULL, '2026-05-09 22:56:02', 'draft', NULL, NULL, 0),
(41, 3, 1, 40.00, NULL, NULL, NULL, 40.00, NULL, '2026-05-09 22:56:02', 'draft', NULL, NULL, 0),
(42, 1, 2, 50.00, NULL, NULL, NULL, 50.00, NULL, '2026-05-09 22:56:02', 'draft', NULL, NULL, 0),
(43, 2, 2, 55.00, NULL, NULL, NULL, 55.00, NULL, '2026-05-09 22:56:02', 'draft', NULL, NULL, 0),
(44, 3, 2, 52.00, NULL, NULL, NULL, 52.00, NULL, '2026-05-09 22:56:02', 'draft', NULL, NULL, 0),
(45, 1, 8, 60.00, NULL, NULL, NULL, 60.00, NULL, '2026-05-09 22:56:02', 'draft', NULL, NULL, 0),
(46, 2, 8, 62.00, NULL, NULL, NULL, 62.00, NULL, '2026-05-09 22:56:02', 'draft', NULL, NULL, 0),
(47, 3, 8, 58.00, NULL, NULL, NULL, 58.00, NULL, '2026-05-09 22:56:02', 'draft', NULL, NULL, 0),
(48, 1, 9, 70.00, NULL, NULL, NULL, 70.00, NULL, '2026-05-09 22:56:02', 'draft', NULL, NULL, 0),
(49, 2, 9, 72.00, NULL, NULL, NULL, 72.00, NULL, '2026-05-09 22:56:02', 'draft', NULL, NULL, 0),
(50, 3, 9, 68.00, NULL, NULL, NULL, 68.00, NULL, '2026-05-09 22:56:02', 'draft', NULL, NULL, 0),
(51, 1, 10, 80.00, NULL, NULL, NULL, 80.00, NULL, '2026-05-09 22:56:02', 'draft', NULL, NULL, 0),
(52, 2, 10, 78.00, NULL, NULL, NULL, 78.00, NULL, '2026-05-09 22:56:02', 'draft', NULL, NULL, 0),
(53, 3, 10, 75.00, NULL, NULL, NULL, 75.00, NULL, '2026-05-09 22:56:02', 'draft', NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `result_workflow_logs`
--

DROP TABLE IF EXISTS `result_workflow_logs`;
CREATE TABLE IF NOT EXISTS `result_workflow_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `result_id` int NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `performed_by` int DEFAULT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `result_id` (`result_id`),
  KEY `performed_by` (`performed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schools`
--

DROP TABLE IF EXISTS `schools`;
CREATE TABLE IF NOT EXISTS `schools` (
  `school_id` int NOT NULL AUTO_INCREMENT,
  `school_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `district` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `division` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Unknown',
  PRIMARY KEY (`school_id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schools`
--

INSERT INTO `schools` (`school_id`, `school_name`, `district`, `address`, `division`) VALUES
(29, 'KARONGA COMMUNITY SECONDARY SCHOOL', 'Karonga', 'P.O BOX 39', 'Northen'),
(30, 'Lufita seecondary school', 'Chitipa', 'P.OBOX 18', 'Northen');

-- --------------------------------------------------------

--
-- Table structure for table `school_notes`
--

DROP TABLE IF EXISTS `school_notes`;
CREATE TABLE IF NOT EXISTS `school_notes` (
  `note_id` int NOT NULL AUTO_INCREMENT,
  `school_id` int DEFAULT NULL,
  `term` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` int DEFAULT NULL,
  `attendance_rate` float DEFAULT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`note_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `school_notes`
--

INSERT INTO `school_notes` (`note_id`, `school_id`, `term`, `year`, `attendance_rate`, `comments`, `created_by`, `created_at`) VALUES
(1, NULL, 'Term 2', 2026, 20, 'goog km', 50, '2026-04-17 22:08:41'),
(2, NULL, 'Term 2', 2026, 20, 'goog km', 50, '2026-04-17 22:08:51'),
(3, NULL, 'Term 2', 2026, 20, 'goog km', 50, '2026-04-17 22:09:18'),
(4, NULL, 'Term 2', 2026, 20, 'goog km', 50, '2026-04-17 22:10:26'),
(5, NULL, 'Term 2', 2026, 20, 'goog km', 50, '2026-04-17 22:15:25');

-- --------------------------------------------------------

--
-- Table structure for table `school_reports`
--

DROP TABLE IF EXISTS `school_reports`;
CREATE TABLE IF NOT EXISTS `school_reports` (
  `report_id` int NOT NULL AUTO_INCREMENT,
  `school_id` int NOT NULL,
  `term` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` int DEFAULT NULL,
  `total_students` int DEFAULT NULL,
  `attendance_rate` float DEFAULT '0',
  `pass_rate` float DEFAULT '0',
  `comments` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`report_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `school_reports`
--

INSERT INTO `school_reports` (`report_id`, `school_id`, `term`, `year`, `total_students`, `attendance_rate`, `pass_rate`, `comments`, `created_at`) VALUES
(1, 29, 'term 2', 2020, 0, 0, 0, 'hhhhhhhh', '2026-04-17 23:41:35');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
CREATE TABLE IF NOT EXISTS `students` (
  `student_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `school_id` int DEFAULT NULL,
  `class` enum('Form 1','Form 2') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exam_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `exam_number` (`exam_number`),
  KEY `fk_student_school` (`school_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `name`, `school_id`, `class`, `exam_number`) VALUES
(1, 'John Banda', NULL, NULL, 'EX001'),
(2, 'Mary Phiri', NULL, NULL, 'EX002'),
(3, 'Peter Mwale', NULL, NULL, 'EX003'),
(16, 'Leonardponje mlungu', 29, 'Form 2', 'MW298854'),
(17, 'JOHN PONJE', 29, 'Form 2', 'MW296968');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
CREATE TABLE IF NOT EXISTS `subjects` (
  `subject_id` int NOT NULL AUTO_INCREMENT,
  `subject_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  PRIMARY KEY (`subject_id`),
  UNIQUE KEY `subject_name` (`subject_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`subject_id`, `subject_name`, `status`) VALUES
(1, 'ENGLISH', 'active'),
(2, 'Computer ', 'active'),
(3, 'Mathematics', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_subjects`
--

DROP TABLE IF EXISTS `teacher_subjects`;
CREATE TABLE IF NOT EXISTS `teacher_subjects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `subject_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `subject_id` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','teacher','headteacher','examination_officer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `school_id` int DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_user_school` (`school_id`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `phone`, `password`, `role`, `school_id`, `status`) VALUES
(2, 'Admin User', 'leonardponjemlungu@gmail.com', '0984487626', '1234', 'admin', NULL, 'active'),
(47, 'ponje 12', 'judithmatupi7@gmail.com', '0984487626', '1234', 'headteacher', 30, 'active'),
(48, 'PROGRAMMER', 'ict-01-26-22@unilia.ac.mw', '0984487621', '1234', 'teacher', 29, 'active'),
(52, 'TEACHER WANE', 'matupijudith71@gmail.com', '0899520423', '1234', 'teacher', 29, 'deleted'),
(60, 'John Thomas Mlungu', 'leonardmlungupro@gmail.com', '0899520423', '123', 'teacher', 29, 'active');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `exams`
--
ALTER TABLE `exams`
  ADD CONSTRAINT `exams_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`),
  ADD CONSTRAINT `exams_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_exam_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_exam_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE;

--
-- Constraints for table `exam_assignments`
--
ALTER TABLE `exam_assignments`
  ADD CONSTRAINT `fk_assign_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assign_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `moderation`
--
ALTER TABLE `moderation`
  ADD CONSTRAINT `moderation_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`),
  ADD CONSTRAINT `moderation_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `fk_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_question_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`),
  ADD CONSTRAINT `questions_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON DELETE CASCADE;

--
-- Constraints for table `results`
--
ALTER TABLE `results`
  ADD CONSTRAINT `fk_result_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_result_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `results_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`),
  ADD CONSTRAINT `results_ibfk_3` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_student_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`school_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`school_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`school_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`school_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
