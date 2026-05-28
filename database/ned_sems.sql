-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 27, 2026 at 09:55 PM
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
-- Table structure for table `ai_moderation_logs`
--

DROP TABLE IF EXISTS `ai_moderation_logs`;
CREATE TABLE IF NOT EXISTS `ai_moderation_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `question_text` text COLLATE utf8mb4_unicode_ci,
  `bloom_level` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bloom_confidence` float DEFAULT NULL,
  `complexity_score` float DEFAULT NULL,
  `quality_score` float DEFAULT NULL,
  `feedback` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `details`, `created_at`) VALUES
(1, 2, 'update_user', 'Updated user ID 47 (ponje 1)', '2026-05-22 10:22:10'),
(2, 2, 'delete_user', 'Soft deleted user ID 47', '2026-05-22 10:22:48'),
(3, 2, 'deactivate', 'Admin 2 performed deactivate on user 62', '2026-05-22 10:41:15'),
(4, 2, 'activate', 'Admin 2 performed activate on user 62', '2026-05-22 10:41:48'),
(5, 2, 'deactivate', 'Admin 2 performed deactivate on user 62', '2026-05-22 10:53:46'),
(6, 2, 'deactivate', 'Bulk deactivate by admin 2 on user 63', '2026-05-22 10:59:20'),
(7, 2, 'deactivate', 'Bulk deactivate by admin 2 on user 62', '2026-05-22 10:59:20'),
(8, 2, 'deactivate', 'Bulk deactivate by admin 2 on user 61', '2026-05-22 10:59:20'),
(9, 2, 'deactivate', 'Bulk deactivate by admin 2 on user 52', '2026-05-22 10:59:20'),
(10, 2, 'deactivate', 'Bulk deactivate by admin 2 on user 48', '2026-05-22 10:59:20'),
(11, 2, 'activate', 'Bulk activate by admin 2 on user 63', '2026-05-22 10:59:39'),
(12, 2, 'activate', 'Bulk activate by admin 2 on user 62', '2026-05-22 10:59:39'),
(13, 2, 'activate', 'Bulk activate by admin 2 on user 61', '2026-05-22 10:59:39'),
(14, 2, 'activate', 'Bulk activate by admin 2 on user 52', '2026-05-22 10:59:39'),
(15, 2, 'activate', 'Bulk activate by admin 2 on user 48', '2026-05-22 10:59:39'),
(16, 2, 'deactivate', 'Admin 2 performed deactivate on user 62', '2026-05-22 11:01:18'),
(17, 2, 'deactivate', 'Admin 2 performed deactivate on user 61', '2026-05-22 11:31:53'),
(18, 2, 'activate', 'Admin 2 performed activate on user 62', '2026-05-22 11:32:12'),
(19, NULL, 'activate', 'Bulk activate on user 61', '2026-05-22 11:32:43'),
(20, 2, 'deactivate', 'Admin 2 performed deactivate on user 63', '2026-05-22 11:38:33'),
(21, 2, 'activate', 'Admin 2 performed activate on user 63', '2026-05-22 11:41:29'),
(22, 2, 'deactivate', 'Bulk deactivate on user 63', '2026-05-22 11:41:38'),
(23, 2, 'activate', 'Bulk activate on user 63', '2026-05-22 11:44:35'),
(24, 2, 'deactivate', 'Admin 2 performed deactivate on user 63', '2026-05-22 11:46:10'),
(25, 2, 'activate', 'Admin 2 performed activate on user 63', '2026-05-22 11:46:12'),
(26, 2, 'deactivate', 'Admin 2 performed deactivate on user 61', '2026-05-22 12:01:43'),
(27, 2, 'activate', 'Admin 2 performed activate on user 61', '2026-05-22 12:02:23'),
(28, 2, 'deactivate', 'Bulk deactivate on user 61', '2026-05-22 12:03:10'),
(29, 2, 'activate', 'Admin 2 performed activate on user 61', '2026-05-22 12:13:45'),
(30, 2, 'create_user', 'Admin 2 created user Robert Mlungu (leonardponjemlungu@outlook.com)', '2026-05-22 12:20:59'),
(31, 2, 'deactivate', 'Admin 2 performed deactivate on user 64', '2026-05-22 12:37:22'),
(32, 2, 'activate', 'Admin 2 performed activate on user 64', '2026-05-22 12:37:53'),
(33, 2, 'create_user', 'Admin 2 created user Robert Mlungu (leonardponjemlungu@outlook.com)', '2026-05-22 12:38:57'),
(34, 2, 'create_user', 'Admin 2 created email', '2026-05-22 13:07:15'),
(35, 2, 'create_user', 'Admin 2 created judithmatupi7@gmail.com', '2026-05-22 13:07:15'),
(36, 2, 'create_user', 'Admin 2 created ict-01-26-22@unilia.ac.mw', '2026-05-22 13:07:20'),
(37, 2, 'create_user', 'Admin 2 created matupijudith71@gmail.com', '2026-05-22 13:07:25'),
(38, 2, 'create_user', 'Admin 2 created leonardmlungupro@gmail.com', '2026-05-22 13:07:30'),
(39, 2, 'create_user', 'Admin 2 created leonardponjemlungu@outlook.com', '2026-05-22 13:07:35'),
(40, 2, 'create_user', 'Admin 2 created judithmatupi7@gmail.com', '2026-05-22 13:26:30'),
(41, 2, 'create_user', 'Admin 2 created ict-01-26-22@unilia.ac.mw', '2026-05-22 13:26:35'),
(42, 2, 'create_user', 'Admin 2 created matupijudith71@gmail.com', '2026-05-22 13:26:41'),
(43, 2, 'create_user', 'Admin 2 created leonardmlungupro@gmail.com', '2026-05-22 13:26:46'),
(44, 2, 'deactivate', 'Admin 2 performed deactivate on user 82', '2026-05-24 08:21:09'),
(45, 2, 'activate', 'Admin 2 performed activate on user 82', '2026-05-24 08:21:55'),
(46, 2, 'deactivate', 'Bulk deactivate on user 82', '2026-05-24 08:24:40'),
(47, 2, 'deactivate', 'Bulk deactivate on user 81', '2026-05-24 08:24:47'),
(48, 2, 'activate', 'Bulk activate on user 82', '2026-05-24 08:25:05'),
(49, 2, 'activate', 'Bulk activate on user 81', '2026-05-24 08:25:11'),
(50, 2, 'update_user', 'Updated user ID 82 (Ponje mlungu)', '2026-05-24 08:26:15'),
(51, 2, 'update_user', 'Updated user ID 82 (Ponje mlungu)', '2026-05-24 08:33:58');

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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `exams`
--

INSERT INTO `exams` (`exam_id`, `exam_name`, `subject_id`, `created_by`, `exam_date`, `duration_minutes`, `total_marks`, `status`, `year`, `class`) VALUES
(1, 'COMPUTER', 1, 2, '2026-03-03', 60, 40, 'draft', '2022', 'Form 2'),
(2, 'FORM', 2, 2, '2026-08-10', 90, 100, 'submitted', '2026', 'Form 2'),
(17, 'Math Test 2026', 1, 2, '2026-05-14', 80, 100, 'assigned', '2022', 'Form 2'),
(18, 'Mathematics 2026', 3, 2, '2026-05-13', 80, 100, 'approved', '2026', 'Form 2');

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
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'assigned',
  PRIMARY KEY (`assignment_id`),
  UNIQUE KEY `unique_assignment` (`exam_id`,`teacher_id`,`role`),
  KEY `assigned_by` (`assigned_by`),
  KEY `fk_assign_teacher` (`teacher_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`question_id`, `exam_id`, `question_order`, `question_text`, `marks`, `created_by`, `section_name`, `created_at`, `question_type`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`, `ai_score`, `moderation_status`, `moderator_comment`) VALUES
(89, 18, 1, 'wht is them?', 7, 63, 'Section B', '2026-05-13 13:01:30', 'structured', '', '', '', '', '', 1, 'approved', 'nice'),
(90, 18, 1, 'wht is them?', 7, 63, 'Section B', '2026-05-13 13:01:58', 'structured', NULL, NULL, NULL, NULL, NULL, 1, 'pending', 'nice'),
(91, 18, 2, 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee', 3, 63, 'Section B', '2026-05-13 13:19:31', 'short', '', '', '', '', 'A', 1, 'revise', 'choka iwe'),
(92, 18, 1, 'what is ai?', 7, 63, 'Section A', '2026-05-14 08:40:56', 'structured', '1', 'me', 'nnn', 'they', 'B', 1, 'pending', NULL),
(93, 18, 10, 'wwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwww', 7, 63, 'Section B', '2026-05-14 08:41:31', 'structured', '', '', '', '', 'A', 1, 'pending', NULL);

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
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(8, 75, 60, 'approved', '', '2026-05-07 08:04:42'),
(9, 89, 63, 'approved', 'good', '2026-05-13 14:08:44'),
(10, 91, 63, 'approved', 'ppp', '2026-05-13 14:08:55');

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
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `results`
--

INSERT INTO `results` (`result_id`, `student_id`, `exam_id`, `total_score`, `grade`, `remarks`, `position_in_class`, `percentage`, `recorded_by`, `recorded_at`, `status`, `teacher_id`, `editable_until`, `locked`) VALUES
(39, 1, 1, 42.00, NULL, NULL, NULL, 42.00, NULL, '2026-05-09 22:56:02', 'draft', NULL, NULL, 0),
(40, 2, 1, 45.00, NULL, NULL, NULL, 45.00, NULL, '2026-05-09 22:56:02', 'draft', NULL, NULL, 0),
(41, 3, 1, 40.00, NULL, NULL, NULL, 40.00, NULL, '2026-05-09 22:56:02', 'draft', NULL, NULL, 0),
(42, 1, 2, 50.00, NULL, NULL, NULL, 50.00, NULL, '2026-05-09 22:56:02', 'draft', NULL, NULL, 0),
(43, 2, 2, 55.00, NULL, NULL, NULL, 55.00, NULL, '2026-05-09 22:56:02', 'draft', NULL, NULL, 0),
(44, 3, 2, 52.00, NULL, NULL, NULL, 52.00, NULL, '2026-05-09 22:56:02', 'draft', NULL, NULL, 0),
(54, 16, 1, 10.00, 'F', 'Fail', 2, 25.00, 63, '2026-05-14 09:06:18', '', NULL, NULL, 0);

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
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  PRIMARY KEY (`school_id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schools`
--

INSERT INTO `schools` (`school_id`, `school_name`, `district`, `address`, `division`, `status`) VALUES
(29, 'KARONGA COMMUNITY SECONDARY SCHOOL', 'Karonga', 'P.O BOX 39', 'Northen', 'active'),
(32, 'Maghemo secondary school', 'Karonga', 'p.o.box 111', 'Unknown', 'active'),
(33, 'Maghemo secondary school', 'Karonga', 'p.o.box 111', 'Unknown', 'active'),
(34, 'Mlare secondary school', 'Karonga', 'p.o.box 11', 'Unknown', 'active'),
(35, 'Karonga girls secondary school', 'Karonga', 'p.o.box 10', 'Unknown', 'active'),
(37, 'IPONGA CDSS', 'CHITIPA', 'p.o.box 101', 'Northern', 'active');

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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `login_count` int DEFAULT '0',
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_user_school` (`school_id`)
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `phone`, `password`, `role`, `school_id`, `status`, `deleted_at`, `deleted_by`, `login_count`, `last_login`) VALUES
(2, 'Admin User', 'leonardponjemlungu@gmail.com', '0984487626', '1234', 'admin', NULL, 'active', NULL, NULL, 100, '2026-05-11 10:12:10'),
(63, 'Abuya Awa', 'leonardmlungu111111@gmail.com', '0899520423', '1234', 'teacher', 29, 'active', NULL, NULL, 0, NULL),
(77, 'John Ponje', 'judithmatupi7@gmail.com', '984487627', '$2y$10$kReUb.an3zmHH4LpuZ34QeocxZ2ADWFOEp5TjJArXLfGWeuOPu2JO', 'headteacher', 35, 'active', NULL, NULL, 0, NULL),
(78, 'Moses Mughogho', 'ict-01-26-22@unilia.ac.mw', '984487621', '$2y$10$5MoXbv/R2THIyvrOSTmWI.Qf71CDhn4r6tEyUNsq3OIGus880BOg6', 'teacher', 32, 'active', NULL, NULL, 0, NULL),
(79, 'Judith Matupi', 'matupijudith71@gmail.com', '899520423', '$2y$10$3nJJZgBs.PIykX2jqDamI.8d7UWcFxV9uLCnt.qhGk0DEOBPEAKJC', 'examination_officer', 34, 'active', NULL, NULL, 0, NULL),
(81, 'Leonard Mlungu', 'leonardponjemlungu@outlook.com', '805112419.88889', '$2y$10$aYJK5lOM8mVL3HJ7nhtU0.BtrtgJpIZFMl2IIFABwEEW7ARwXtxIq', 'teacher', 32, 'active', NULL, NULL, 0, NULL),
(82, 'Ponje mlungu', 'leonardmlungupro@gmail.com', '0899520423', '$2y$10$JQPZH8SWLbqkUvjoEv/71uNxk6t8XqqEl4l3qhfJ6Zs5ZyRYkHv92', 'teacher', 34, 'active', NULL, NULL, 0, NULL);

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
