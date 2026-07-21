-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jul 13, 2026 at 09:45 AM
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
-- Table structure for table `ai_analysis`
--

DROP TABLE IF EXISTS `ai_analysis`;
CREATE TABLE IF NOT EXISTS `ai_analysis` (
  `analysis_id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int DEFAULT NULL,
  `question_id` int DEFAULT NULL,
  `analysis_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `feedback` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`analysis_id`),
  KEY `exam_id` (`exam_id`),
  KEY `question_id` (`question_id`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ai_analysis`
--

INSERT INTO `ai_analysis` (`analysis_id`, `exam_id`, `question_id`, `analysis_type`, `score`, `feedback`, `status`, `created_at`) VALUES
(1, 19, 96, 'question_ai', 50.00, '{\"quality_score\":50,\"bloom_level\":\"Remember\",\"suggested_marks\":1,\"readiness\":\"Needs Review\"}', 'completed', '2026-06-12 16:50:52'),
(2, 19, 97, 'question_ai', 50.00, '{\"quality_score\":50,\"bloom_level\":\"Remember\",\"suggested_marks\":3,\"readiness\":\"Needs Review\"}', 'completed', '2026-06-12 16:57:28'),
(3, 19, 98, 'question_ai', 50.00, '{\"quality_score\":50,\"bloom_level\":\"Remember\",\"suggested_marks\":1,\"readiness\":\"Needs Review\"}', 'completed', '2026-06-12 17:34:00'),
(4, 19, 99, 'question_compose', 45.00, '{\"status\":\"success\",\"analysis_type\":\"teacher_compose\",\"original_question\":\"Yyyyyyyyyy.\",\"suggested_question\":\"Yyyyyyyyyy.\",\"difficulty_level\":\"Easy\",\"cognitive_level\":\"Evaluating\",\"bloom_level\":\"Evaluate\",\"bloom_confidence\":99,\"topic\":\"yyyyyyyyyy\",\"quality_score\":45,\"suggested_marks\":10,\"current_marks\":4,\"suggested_improvements\":[\"Question is too short and may confuse learners.\",\"Question lacks an action verb.\",\"Not properly phrased as a question.\",\"Consider increasing marks to 10.\",\"Good higher-order thinking question.\"],\"grammar_corrections\":[],\"clarity_recommendations\":[\"Add context so students understand what is being assessed.\",\"Include a clear action verb (e.g. explain, calculate, compare).\",\"Phrase as a direct question starting with an action verb or question word.\"],\"ambiguities\":[],\"duplicate_detection\":{\"is_duplicate\":false,\"similarity_score\":23.88,\"matched_question\":\"rggggg\"},\"recommendations\":[\"Question is too short and may confuse learners.\",\"Question lacks an action verb.\",\"Not properly phrased as a question.\",\"Consider increasing marks to 10.\",\"Good higher-order thinking question.\",\"Add context so students understand what is being assessed.\",\"Include a clear action verb (e.g. explain, calculate, compare).\",\"Phrase as a direct question starting with an action verb or question word.\"],\"warnings\":[\"Low quality score ? review before publishing.\"],\"keywords\":[\"yyyyyyyyyy\"],\"readability\":{\"flesch_score\":121.2,\"grade_level\":-3.4,\"fog_index\":0.4,\"syllable_count\":1,\"label\":\"Easy to read\",\"grade_label\":\"Primary level\"},\"complexity_score\":1,\"teacher_guidance\":[\"Improve clarity and structure of the question.\",\"Question may be too simple for exam standards.\"],\"risk_level\":\"High\",\"ai_status\":\"AI Moderation Completed\",\"explanations\":{\"summary\":\"Question is too short and may confuse learners.\\nQuestion lacks an action verb.\\nNot properly phrased as a question.\\nConsider increasing marks to 10.\\nGood higher-order thinking question.\\nAdd context so students understand what is being assessed.\"}}', 'completed', '2026-06-13 03:13:24'),
(5, 19, 100, 'question_compose', 45.00, '{\"status\":\"success\",\"analysis_type\":\"teacher_compose\",\"original_question\":\"pone kj\",\"suggested_question\":\"Pone kj.\",\"difficulty_level\":\"Easy\",\"cognitive_level\":\"Creating\",\"bloom_level\":\"Create\",\"bloom_confidence\":50,\"topic\":\"pone kj, kj, pone\",\"quality_score\":45,\"suggested_marks\":12,\"current_marks\":6,\"suggested_improvements\":[\"Question is too short and may confuse learners.\",\"Question lacks an action verb.\",\"Not properly phrased as a question.\",\"Consider increasing marks to 12.\",\"Good higher-order thinking question.\"],\"grammar_corrections\":[\"Capitalize the first letter of the question.\",\"Consider ending the question with a question mark (?).\"],\"clarity_recommendations\":[\"Add context so students understand what is being assessed.\",\"Include a clear action verb (e.g. explain, calculate, compare).\",\"Phrase as a direct question starting with an action verb or question word.\"],\"ambiguities\":[],\"duplicate_detection\":{\"is_duplicate\":false,\"similarity_score\":28.23,\"matched_question\":\"ttttt\"},\"recommendations\":[\"Question is too short and may confuse learners.\",\"Question lacks an action verb.\",\"Not properly phrased as a question.\",\"Consider increasing marks to 12.\",\"Good higher-order thinking question.\",\"Add context so students understand what is being assessed.\",\"Include a clear action verb (e.g. explain, calculate, compare).\",\"Phrase as a direct question starting with an action verb or question word.\"],\"warnings\":[\"Low quality score ? review before publishing.\",\"2 grammar issue(s) detected.\"],\"keywords\":[\"pone kj\",\"kj\",\"pone\"],\"readability\":{\"flesch_score\":120.2,\"grade_level\":-3,\"fog_index\":0.8,\"syllable_count\":2,\"label\":\"Easy to read\",\"grade_label\":\"Primary level\"},\"complexity_score\":2,\"teacher_guidance\":[\"Improve clarity and structure of the question.\",\"Question may be too simple for exam standards.\"],\"risk_level\":\"High\",\"ai_status\":\"AI Moderation Completed\",\"explanations\":{\"summary\":\"Question is too short and may confuse learners.\\nQuestion lacks an action verb.\\nNot properly phrased as a question.\\nConsider increasing marks to 12.\\nGood higher-order thinking question.\\nAdd context so students understand what is being assessed.\"}}', 'completed', '2026-06-13 03:21:53'),
(6, 19, 101, 'question_compose', 55.00, '{\"status\":\"success\",\"analysis_type\":\"teacher_compose\",\"original_question\":\"ty thy thy?\",\"suggested_question\":\"Ty thy thy?\",\"difficulty_level\":\"Easy\",\"cognitive_level\":\"Remembering\",\"bloom_level\":\"Remember\",\"bloom_confidence\":99,\"topic\":\"ty thy, thy thy, thy\",\"quality_score\":55,\"suggested_marks\":2,\"current_marks\":5,\"suggested_improvements\":[\"Question is too short and may confuse learners.\",\"Question lacks an action verb.\",\"Marks too high. Suggested: 2.\",\"Low-level recall question.\",\"Consider raising cognitive demand to Applying or Analysing level.\"],\"grammar_corrections\":[\"Capitalize the first letter of the question.\"],\"clarity_recommendations\":[\"Add context so students understand what is being assessed.\",\"Include a clear action verb (e.g. explain, calculate, compare).\"],\"ambiguities\":[],\"duplicate_detection\":{\"is_duplicate\":false,\"similarity_score\":47.32,\"matched_question\":\"ttttt\"},\"recommendations\":[\"Question is too short and may confuse learners.\",\"Question lacks an action verb.\",\"Marks too high. Suggested: 2.\",\"Low-level recall question.\",\"Consider raising cognitive demand to Applying or Analysing level.\",\"Add context so students understand what is being assessed.\",\"Include a clear action verb (e.g. explain, calculate, compare).\"],\"warnings\":[\"Low quality score ? review before publishing.\",\"1 grammar issue(s) detected.\"],\"keywords\":[\"ty thy\",\"thy thy\",\"thy\",\"ty\"],\"readability\":{\"flesch_score\":119.2,\"grade_level\":-2.6,\"fog_index\":1.2,\"syllable_count\":3,\"label\":\"Easy to read\",\"grade_label\":\"Primary level\"},\"complexity_score\":4,\"teacher_guidance\":[\"Improve clarity and structure of the question.\",\"Consider increasing cognitive level (Apply or higher).\",\"Question may be too simple for exam standards.\"],\"risk_level\":\"Medium\",\"ai_status\":\"AI Moderation Completed\",\"explanations\":{\"summary\":\"Question is too short and may confuse learners.\\nQuestion lacks an action verb.\\nMarks too high. Suggested: 2.\\nLow-level recall question.\\nConsider raising cognitive demand to Applying or Analysing level.\\nAdd context so students understand what is being assessed.\"}}', 'completed', '2026-06-15 06:54:29'),
(7, 19, 102, 'question_compose', 100.00, '{\"status\":\"success\",\"analysis_type\":\"teacher_compose\",\"original_question\":\"explain any 4 ways of learning?\",\"suggested_question\":\"Explain any 4 ways of learning?\",\"difficulty_level\":\"Medium\",\"cognitive_level\":\"Understanding\",\"bloom_level\":\"Understand\",\"bloom_confidence\":50,\"topic\":\"ways learning, explain ways, learning\",\"quality_score\":100,\"suggested_marks\":4,\"current_marks\":3,\"suggested_improvements\":[\"Consider increasing marks to 4.\",\"Consider raising cognitive demand to Applying or Analysing level.\"],\"grammar_corrections\":[\"Capitalize the first letter of the question.\"],\"clarity_recommendations\":[\"Add context so students understand what is being assessed.\"],\"ambiguities\":[],\"duplicate_detection\":{\"is_duplicate\":false,\"similarity_score\":5.35,\"matched_question\":\"Ty thy thy?\"},\"recommendations\":[\"Consider increasing marks to 4.\",\"Consider raising cognitive demand to Applying or Analysing level.\",\"Add context so students understand what is being assessed.\"],\"warnings\":[\"1 grammar issue(s) detected.\"],\"keywords\":[\"ways learning\",\"explain ways\",\"learning\",\"ways\"],\"readability\":{\"flesch_score\":73.8,\"grade_level\":4.5,\"fog_index\":2.4,\"syllable_count\":9,\"label\":\"Easy to read\",\"grade_label\":\"Primary level\"},\"complexity_score\":8,\"teacher_guidance\":[\"Consider increasing cognitive level (Apply or higher).\"],\"risk_level\":\"Low\",\"ai_status\":\"AI Moderation Completed\",\"explanations\":{\"summary\":\"Consider increasing marks to 4.\\nConsider raising cognitive demand to Applying or Analysing level.\\nAdd context so students understand what is being assessed.\"}}', 'completed', '2026-06-15 11:44:12'),
(8, 27, 103, 'question_compose', 76.00, '{\"status\":\"success\",\"ai_status\":\"Analysis Complete\",\"quality_score\":76,\"bloom_level\":\"Analyze\",\"cognitive_level\":\"Analysing\",\"difficulty_level\":\"Medium\",\"complexity_score\":8,\"topic\":\"EXLAIN, MODERING\",\"suggested_marks\":8,\"current_marks\":48,\"original_question\":\"EXLAIN ANY TWO WAYS OF MODERING\",\"suggested_question\":\"EXLAIN ANY TWO WAYS OF MODERING.\",\"grammar_corrections\":[\"End the question with ? or .\"],\"recommendations\":[\"Add more context so learners understand what is assessed.\",\"Phrase as a direct question or command starting with a verb.\",\"End the question with ? or .\",\"Marks (48) seem high for Analyze level � suggest 8.\",\"Good higher-order thinking question.\"],\"warnings\":[\"1 grammar issue(s) detected.\"],\"duplicate_detection\":{\"is_duplicate\":false,\"similarity_score\":0,\"matched_question\":null},\"readability\":{\"flesch_score\":73.8,\"grade_level\":4.5,\"fog_index\":9.1,\"label\":\"Easy to read\"},\"teacher_guidance\":[\"Question is acceptable with no major issues.\"],\"risk_level\":\"Low\",\"moderation_recommendation\":\"revise\",\"moderation_reason\":\"Acceptable but needs minor revision before publishing.\",\"bloom_confidence\":50,\"bloom_scores\":{\"Remember\":14.82,\"Understand\":14.44,\"Apply\":12.32,\"Analyze\":16.52,\"Evaluate\":6.13,\"Create\":3.44},\"ambiguities\":[],\"linguistics\":{\"word_count\":6,\"has_verb\":true,\"is_proper_question\":false,\"entities\":[\"EXLAIN\",\"TWO\"],\"ambiguous_words\":[]},\"feedback\":[\"Add more context so learners understand what is assessed.\",\"Phrase as a direct question or command starting with a verb.\",\"End the question with ? or .\",\"Marks (48) seem high for Analyze level � suggest 8.\",\"Good higher-order thinking question.\"]}', 'completed', '2026-06-26 18:16:48'),
(9, 27, 104, 'question_compose', 51.00, '{\"status\":\"success\",\"ai_status\":\"Analysis Complete\",\"quality_score\":51,\"bloom_level\":\"Create\",\"cognitive_level\":\"Creating\",\"difficulty_level\":\"Easy\",\"complexity_score\":3,\"topic\":\"agriculture\",\"suggested_marks\":12,\"current_marks\":3,\"original_question\":\"what is agriculture ?\",\"suggested_question\":\"What is agriculture ?\",\"grammar_corrections\":[\"Start with a capital letter.\"],\"recommendations\":[\"Question is too short — add more context.\",\"Include an action verb (e.g. explain, calculate, compare).\",\"Start with a capital letter.\",\"Consider increasing marks to 12 for Create level.\",\"Good higher-order thinking question.\"],\"warnings\":[\"Low quality score — review before publishing.\",\"1 grammar issue(s) detected.\"],\"duplicate_detection\":{\"is_duplicate\":false,\"matched_question\":\"EXLAIN ANY TWO WAYS OF MODERING.\",\"similarity_score\":1.8},\"readability\":{\"flesch_score\":34.6,\"fog_index\":14.5,\"grade_level\":9.2,\"label\":\"Difficult\"},\"teacher_guidance\":[\"Improve clarity and structure of the question.\",\"Question may be too simple for exam standards.\"],\"risk_level\":\"Medium\",\"moderation_recommendation\":\"revise\",\"moderation_reason\":\"Low quality (51%) — significant revision needed.\",\"ambiguities\":[],\"bloom_confidence\":50,\"bloom_scores\":{\"Analyze\":25.9,\"Apply\":14.19,\"Create\":38.6,\"Evaluate\":17.33,\"Remember\":28.55,\"Understand\":35.87},\"feedback\":[\"Question is too short — add more context.\",\"Include an action verb (e.g. explain, calculate, compare).\",\"Start with a capital letter.\",\"Consider increasing marks to 12 for Create level.\",\"Good higher-order thinking question.\"],\"linguistics\":{\"ambiguous_words\":[],\"entities\":[],\"has_verb\":false,\"is_proper_question\":true,\"word_count\":4}}', 'completed', '2026-06-29 12:20:22'),
(10, 27, 105, 'question_compose', 37.00, '{\"status\":\"success\",\"ai_status\":\"Analysis Complete\",\"quality_score\":37,\"bloom_level\":\"Understand\",\"cognitive_level\":\"Understanding\",\"difficulty_level\":\"Easy\",\"complexity_score\":1,\"topic\":\"General\",\"suggested_marks\":4,\"current_marks\":8,\"original_question\":\"bhhh\",\"suggested_question\":\"Bhhh.\",\"grammar_corrections\":[\"Start with a capital letter.\",\"End the question with ? or .\"],\"recommendations\":[\"Question is too short — add more context.\",\"Include an action verb (e.g. explain, calculate, compare).\",\"Phrase as a direct question or command starting with a verb.\",\"Start with a capital letter.\",\"End the question with ? or .\",\"Marks (8) seem high for Understand level — suggest 4.\"],\"warnings\":[\"Low quality score — review before publishing.\",\"2 grammar issue(s) detected.\"],\"duplicate_detection\":{\"is_duplicate\":false,\"matched_question\":\"EXLAIN ANY TWO WAYS OF MODERING.\",\"similarity_score\":6.4},\"readability\":{\"flesch_score\":36.6,\"fog_index\":0.4,\"grade_level\":8.4,\"label\":\"Difficult\"},\"teacher_guidance\":[\"Improve clarity and structure of the question.\",\"Consider increasing cognitive level (Apply or higher).\",\"Question may be too simple for exam standards.\"],\"risk_level\":\"High\",\"moderation_recommendation\":\"revise\",\"moderation_reason\":\"Low quality (37%) — significant revision needed.\",\"ambiguities\":[],\"bloom_confidence\":50,\"bloom_scores\":{\"Analyze\":3.7,\"Apply\":11.02,\"Create\":2.76,\"Evaluate\":6.33,\"Remember\":9.41,\"Understand\":11.54},\"feedback\":[\"Question is too short — add more context.\",\"Include an action verb (e.g. explain, calculate, compare).\",\"Phrase as a direct question or command starting with a verb.\",\"Start with a capital letter.\",\"End the question with ? or .\",\"Marks (8) seem high for Understand level — suggest 4.\"],\"linguistics\":{\"ambiguous_words\":[],\"entities\":[],\"has_verb\":false,\"is_proper_question\":false,\"word_count\":1}}', 'completed', '2026-06-29 12:28:21'),
(11, 27, 106, 'question_compose', 47.00, '{\"status\":\"success\",\"ai_status\":\"Analysis Complete\",\"quality_score\":47,\"bloom_level\":\"Apply\",\"cognitive_level\":\"Applying\",\"difficulty_level\":\"Easy\",\"complexity_score\":4,\"topic\":\"where\",\"suggested_marks\":6,\"current_marks\":3,\"original_question\":\"where are you\",\"suggested_question\":\"Where are you?\",\"grammar_corrections\":[\"Start with a capital letter.\",\"End the question with ? or .\"],\"recommendations\":[\"Question is too short — add more context.\",\"Include an action verb (e.g. explain, calculate, compare).\",\"Start with a capital letter.\",\"End the question with ? or .\",\"Consider increasing marks to 6 for Apply level.\"],\"warnings\":[\"Low quality score — review before publishing.\",\"2 grammar issue(s) detected.\"],\"duplicate_detection\":{\"is_duplicate\":false,\"matched_question\":\"bhhh\",\"similarity_score\":18.3},\"readability\":{\"flesch_score\":119.2,\"fog_index\":1.2,\"grade_level\":-2.6,\"label\":\"Easy to read\"},\"teacher_guidance\":[\"Improve clarity and structure of the question.\",\"Question may be too simple for exam standards.\"],\"risk_level\":\"High\",\"moderation_recommendation\":\"revise\",\"moderation_reason\":\"Low quality (47%) — significant revision needed.\",\"ambiguities\":[],\"bloom_confidence\":50,\"bloom_scores\":{\"Analyze\":-4.6,\"Apply\":10.96,\"Create\":3.41,\"Evaluate\":-1.52,\"Remember\":10.94,\"Understand\":7.41},\"feedback\":[\"Question is too short — add more context.\",\"Include an action verb (e.g. explain, calculate, compare).\",\"Start with a capital letter.\",\"End the question with ? or .\",\"Consider increasing marks to 6 for Apply level.\"],\"linguistics\":{\"ambiguous_words\":[],\"entities\":[],\"has_verb\":false,\"is_proper_question\":true,\"word_count\":3}}', 'completed', '2026-06-29 12:33:00');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `published_by` int NOT NULL,
  `school_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `published_by` (`published_by`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `target_user_id` int DEFAULT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=302 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `target_user_id`, `action`, `details`, `created_at`, `ip_address`) VALUES
(161, 127, 127, 'update_profile', 'Updated profile information for user ID 127', '2026-06-25 11:35:05', '::1'),
(162, 2, 130, 'create_user', 'Created examination_officer account for Philipina Kangola (ict-01-09-22@unilia.ac.mw)', '2026-06-25 11:42:03', '::1'),
(163, 127, 125, 'toggle_marking_lock', 'Toggled marking lock for assignment ID 1: marker (Teacher ID 125) is now locked for Exam ID 27, Subject ID 110', '2026-06-26 08:28:21', '::1'),
(164, 127, NULL, 'CREATE_QUESTION', 'Q#103 in exam #27 | AI score: 76% | Status: revise', '2026-06-26 18:16:48', NULL),
(165, 127, NULL, 'CREATE_QUESTION', 'Q#104 in exam #27 | AI score: 51% | Status: revise', '2026-06-29 12:20:22', NULL),
(166, 127, NULL, 'CREATE_QUESTION', 'Q#105 in exam #27 | AI score: 37% | Status: revise', '2026-06-29 12:28:21', NULL),
(167, 127, NULL, 'CREATE_QUESTION', 'Q#106 in exam #27 | AI score: 47% | Status: revise', '2026-06-29 12:33:00', NULL),
(168, NULL, 1, 'TEST_AUDIT_LOG', '{\"test\":\"verification\"}', '2026-06-29 19:56:03', '127.0.0.1'),
(169, 127, NULL, 'USER_LOGOUT', '', '2026-06-29 19:57:48', '::1'),
(170, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"teacher\"}', '2026-06-29 19:59:11', '::1'),
(171, 127, NULL, 'QUESTION_MODERATED', '{\"exam_id\":27,\"question_id\":103,\"status\":\"approved\",\"comment\":\"AI Flagged: 1 grammar issue(s) detected.; Add more context so learners understand what is assessed.; Phrase as a direct question or command starting with a verb.\"}', '2026-06-29 20:41:57', '::1'),
(172, 127, NULL, 'QUESTION_MODERATED', '{\"exam_id\":27,\"question_id\":103,\"status\":\"approved\",\"comment\":\"AI Flagged: 1 grammar issue(s) detected.; Add more context so learners understand what is assessed.; Phrase as a direct question or command starting with a verb.\"}', '2026-06-29 20:42:18', '::1'),
(173, 127, NULL, 'QUESTION_MODERATED', '{\"exam_id\":27,\"question_id\":104,\"status\":\"rejected\",\"comment\":\"AI Flagged: Low quality score — review before publishing.; 1 grammar issue(s) detected.; Question is too short — add more context.\"}', '2026-06-29 21:01:41', '::1'),
(174, 127, NULL, 'QUESTION_MODERATED', '{\"exam_id\":27,\"question_id\":104,\"status\":\"approved\",\"comment\":\"AI Flagged: Low quality score — review before publishing.; 1 grammar issue(s) detected.; Question is too short — add more context.\"}', '2026-06-29 21:02:18', '::1'),
(175, 127, NULL, 'QUESTION_MODERATED', '{\"exam_id\":27,\"question_id\":105,\"status\":\"revise\",\"comment\":\"AI Flagged: Low quality score — review before publishing.; 2 grammar issue(s) detected.; Question is too short — add more context.\"}', '2026-06-29 21:02:26', '::1'),
(176, 127, NULL, 'QUESTION_MODERATED', '{\"exam_id\":27,\"question_id\":105,\"status\":\"approved\",\"comment\":\"AI Flagged: Low quality score — review before publishing.; 2 grammar issue(s) detected.; Question is too short — add more context.\"}', '2026-06-29 21:02:34', '::1'),
(177, 127, NULL, 'QUESTION_MODERATED', '{\"exam_id\":27,\"question_id\":106,\"status\":\"approved\",\"comment\":\"AI Flagged: Low quality score — review before publishing.; 2 grammar issue(s) detected.; Question is too short — add more context.\"}', '2026-06-29 21:02:43', '::1'),
(178, 127, NULL, 'EXAM_PAPER_DOWNLOADED', '{\"exam_id\":27,\"exam_name\":\"JCE 2026\"}', '2026-06-29 21:08:48', '::1'),
(179, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"teacher\"}', '2026-06-30 12:26:12', '::1'),
(180, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"teacher\"}', '2026-06-30 14:40:37', '::1'),
(181, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"teacher\"}', '2026-06-30 16:12:22', '::1'),
(182, 127, NULL, 'EXAM_PAPER_DOWNLOADED', '{\"exam_id\":27,\"exam_name\":\"JCE 2026\"}', '2026-06-30 16:15:54', '::1'),
(183, 2, 2, 'USER_LOGIN_SUCCESS', '{\"email\":\"leonardponjemlungu@gmail.com\",\"role\":\"admin\"}', '2026-06-30 20:00:30', '::1'),
(184, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"teacher\"}', '2026-07-01 12:57:42', '::1'),
(185, 2, 2, 'USER_LOGIN_SUCCESS', '{\"email\":\"leonardponjemlungu@gmail.com\",\"role\":\"admin\"}', '2026-07-01 12:58:14', '::1'),
(186, 2, 131, 'create_user', 'Created teacher account for Magret Bandah (beh-01-242-25@unilia.ac.mw)', '2026-07-01 13:04:48', '::1'),
(187, 127, NULL, 'USER_LOGOUT', '', '2026-07-01 13:05:47', '::1'),
(188, 131, 131, 'USER_LOGIN_SUCCESS', '{\"email\":\"beh-01-242-25@unilia.ac.mw\",\"role\":\"teacher\"}', '2026-07-01 13:06:57', '::1'),
(189, 131, 131, 'change_password', 'Changed password for user ID 131', '2026-07-01 13:08:12', '::1'),
(190, 2, 2, 'USER_LOGIN_SUCCESS', '{\"email\":\"leonardponjemlungu@gmail.com\",\"role\":\"admin\"}', '2026-07-02 10:20:06', '::1'),
(191, 2, NULL, 'USER_LOGOUT', '', '2026-07-02 10:20:27', '::1'),
(192, 2, 2, 'USER_LOGIN_SUCCESS', '{\"email\":\"leonardponjemlungu@gmail.com\",\"role\":\"admin\"}', '2026-07-02 10:20:44', '::1'),
(193, 131, 131, 'USER_LOGIN_SUCCESS', '{\"email\":\"beh-01-242-25@unilia.ac.mw\",\"role\":\"teacher\"}', '2026-07-02 10:21:02', '::1'),
(194, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"headteacher\"}', '2026-07-03 19:38:59', '::1'),
(195, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"headteacher\"}', '2026-07-04 10:58:45', '::1'),
(196, 2, 2, 'USER_LOGIN_SUCCESS', '{\"email\":\"leonardponjemlungu@gmail.com\",\"role\":\"admin\"}', '2026-07-04 11:36:30', '::1'),
(197, 2, 134, 'create_user', 'Created teacher account for lwitiko (lwitikomwalungila7@gmail.com)', '2026-07-04 11:41:09', '::1'),
(198, 127, NULL, 'USER_LOGOUT', '', '2026-07-04 11:48:47', '::1'),
(199, NULL, NULL, 'USER_LOGIN_FAILED', '{\"attempted_username\":\"lwitikomwlungila7@gmail.com\"}', '2026-07-04 11:51:42', '::1'),
(200, NULL, NULL, 'USER_LOGIN_FAILED', '{\"attempted_username\":\"lwitikomwlungila7@gmail.com\"}', '2026-07-04 11:53:04', '::1'),
(201, NULL, NULL, 'USER_LOGIN_FAILED', '{\"attempted_username\":\"lwitikomwlungila7@gmail.com\"}', '2026-07-04 11:54:19', '::1'),
(202, NULL, NULL, 'USER_LOGIN_FAILED', '{\"attempted_username\":\"lwitikomwlungila7@gmail.com\"}', '2026-07-04 11:58:55', '::1'),
(203, 134, 134, 'USER_LOGIN_SUCCESS', '{\"email\":\"lwitikomwalungila7@gmail.com\",\"role\":\"teacher\"}', '2026-07-04 11:59:19', '::1'),
(204, 2, 2, 'USER_LOGIN_SUCCESS', '{\"email\":\"leonardponjemlungu@gmail.com\",\"role\":\"admin\"}', '2026-07-05 19:53:42', '::1'),
(205, 2, 2, 'USER_LOGIN_SUCCESS', '{\"email\":\"leonardponjemlungu@gmail.com\",\"role\":\"admin\"}', '2026-07-06 12:32:01', '::1'),
(206, 134, 134, 'USER_LOGIN_SUCCESS', '{\"email\":\"lwitikomwalungila7@gmail.com\",\"role\":\"teacher\"}', '2026-07-06 12:59:15', '::1'),
(207, 134, NULL, 'USER_LOGOUT', '', '2026-07-06 12:59:18', '::1'),
(208, NULL, NULL, 'USER_LOGIN_FAILED', '{\"attempted_username\":\"mbalwelusekero@gmail.com\"}', '2026-07-06 12:59:22', '::1'),
(209, NULL, NULL, 'USER_LOGIN_FAILED', '{\"attempted_username\":\"mbalwelusekero@gmail.com\"}', '2026-07-06 12:59:44', '::1'),
(210, 2, 2, 'USER_LOGIN_SUCCESS', '{\"email\":\"leonardponjemlungu@gmail.com\",\"role\":\"admin\"}', '2026-07-07 00:10:31', '::1'),
(211, 2, 2, 'USER_LOGIN_SUCCESS', '{\"email\":\"leonardponjemlungu@gmail.com\",\"role\":\"admin\"}', '2026-07-07 14:11:06', '::1'),
(212, 2, 2, 'USER_LOGIN_SUCCESS', '{\"email\":\"leonardponjemlungu@gmail.com\",\"role\":\"admin\"}', '2026-07-07 16:17:36', '::1'),
(213, NULL, NULL, 'USER_LOGIN_FAILED', '{\"attempted_username\":\"mbalwelusekero@gmail.com\"}', '2026-07-07 17:36:30', '::1'),
(214, NULL, NULL, 'USER_LOGIN_FAILED', '{\"attempted_username\":\"mbalwelusekero@gmail.com\"}', '2026-07-07 17:36:35', '::1'),
(215, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"headteacher\"}', '2026-07-07 17:37:57', '::1'),
(216, 127, NULL, 'USER_LOGOUT', '', '2026-07-07 17:40:24', '::1'),
(217, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"examination_officer\"}', '2026-07-07 17:40:25', '::1'),
(218, 2, 2, 'USER_LOGIN_SUCCESS', '{\"email\":\"leonardponjemlungu@gmail.com\",\"role\":\"admin\"}', '2026-07-08 18:32:16', '::1'),
(219, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"examination_officer\"}', '2026-07-09 10:19:05', '::1'),
(220, 2, 2, 'USER_LOGIN_SUCCESS', '{\"email\":\"leonardponjemlungu@gmail.com\",\"role\":\"admin\"}', '2026-07-10 11:41:27', '::1'),
(221, 2, 2, 'USER_LOGIN_SUCCESS', '{\"email\":\"leonardponjemlungu@gmail.com\",\"role\":\"admin\"}', '2026-07-10 16:29:04', '::1'),
(222, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"examination_officer\"}', '2026-07-10 17:18:58', '::1'),
(223, 127, NULL, 'USER_LOGOUT', '', '2026-07-10 17:24:28', '::1'),
(224, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"examination_officer\"}', '2026-07-10 17:42:57', '::1'),
(225, 2, 2, 'USER_LOGIN_SUCCESS', '{\"email\":\"leonardponjemlungu@gmail.com\",\"role\":\"admin\"}', '2026-07-11 07:23:30', '::1'),
(226, 2, 2, 'USER_LOGIN_SUCCESS', '{\"email\":\"leonardponjemlungu@gmail.com\",\"role\":\"admin\"}', '2026-07-11 07:31:17', '::1'),
(227, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"examination_officer\"}', '2026-07-11 07:46:45', '::1'),
(228, 2, 2, 'USER_LOGIN_SUCCESS', '{\"email\":\"leonardponjemlungu@gmail.com\",\"role\":\"admin\"}', '2026-07-11 10:40:27', '::1'),
(229, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"examination_officer\"}', '2026-07-11 12:36:23', '::1'),
(230, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"examination_officer\"}', '2026-07-11 13:44:12', '::1'),
(231, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"examination_officer\"}', '2026-07-11 14:31:14', '::1'),
(232, 127, NULL, 'forward_marks', 'Forwarded marks for Exam ID 26, Subject ID 106 to Headteacher', '2026-07-11 15:56:10', '::1'),
(233, 127, 0, 'unlock_all_marks', 'Unlocked all submitted marks for Exam ID 26, Subject ID 117 (reverted to draft)', '2026-07-11 16:12:45', '::1'),
(234, 127, 0, 'unlock_all_marks', 'Unlocked all submitted marks for Exam ID 26, Subject ID 117 (reverted to draft)', '2026-07-11 16:13:05', '::1'),
(235, 127, NULL, 'forward_marks', 'Forwarded marks for Exam ID 26, Subject ID 117 to Headteacher', '2026-07-11 16:13:08', '::1'),
(236, 127, 0, 'unlock_single_mark', 'Unlocked single mark entry ID 890 for Student ID 115 (reverted to draft)', '2026-07-11 16:13:54', '::1'),
(237, 127, 0, 'unlock_all_marks', 'Unlocked all submitted marks for Exam ID 26, Subject ID 104 (reverted to draft)', '2026-07-11 16:18:31', '::1'),
(238, 127, 130, 'toggle_marking_lock', 'Toggled marking lock for assignment ID 449: marker (Teacher ID 130) is now unlocked for Exam ID 26, Subject ID 104', '2026-07-11 16:26:27', '::1'),
(239, 127, 126, 'toggle_marking_lock', 'Toggled marking lock for assignment ID 455: marker (Teacher ID 126) is now unlocked for Exam ID 26, Subject ID 110', '2026-07-11 16:26:33', '::1'),
(240, 127, 130, 'toggle_marking_lock', 'Toggled marking lock for assignment ID 450: marker (Teacher ID 130) is now unlocked for Exam ID 26, Subject ID 105', '2026-07-11 16:26:34', '::1'),
(241, 127, 124, 'toggle_marking_lock', 'Toggled marking lock for assignment ID 453: marker (Teacher ID 124) is now unlocked for Exam ID 26, Subject ID 108', '2026-07-11 16:26:36', '::1'),
(242, 127, NULL, 'USER_LOGOUT', '', '2026-07-11 16:34:40', '::1'),
(243, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"headteacher\"}', '2026-07-11 16:34:42', '::1'),
(244, 127, NULL, 'USER_LOGOUT', '', '2026-07-11 16:37:45', '::1'),
(245, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"examination_officer\"}', '2026-07-11 16:37:47', '::1'),
(246, 127, NULL, 'USER_LOGOUT', '', '2026-07-11 16:49:45', '::1'),
(247, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"headteacher\"}', '2026-07-11 16:49:47', '::1'),
(248, 127, NULL, 'USER_LOGOUT', '', '2026-07-11 17:08:20', '::1'),
(249, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"examination_officer\"}', '2026-07-11 17:08:22', '::1'),
(250, 127, NULL, 'USER_LOGOUT', '', '2026-07-11 17:19:18', '::1'),
(251, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"headteacher\"}', '2026-07-11 17:19:20', '::1'),
(252, 127, NULL, 'USER_LOGOUT', '', '2026-07-11 17:20:38', '::1'),
(253, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"examination_officer\"}', '2026-07-11 17:20:40', '::1'),
(254, 127, 130, 'toggle_marking_lock', 'Toggled marking lock for assignment ID 512: marker (Teacher ID 130) is now unlocked for Exam ID 27, Subject ID 105', '2026-07-11 17:23:01', '::1'),
(255, 127, 130, 'toggle_marking_lock', 'Toggled marking lock for assignment ID 512: marker (Teacher ID 130) is now locked for Exam ID 27, Subject ID 105', '2026-07-11 17:23:07', '::1'),
(256, 130, 130, 'USER_LOGIN_SUCCESS', '{\"email\":\"ict-01-09-22@unilia.ac.mw\",\"role\":\"teacher\"}', '2026-07-11 17:24:53', '::1'),
(257, 127, 130, 'toggle_marking_lock', 'Toggled marking lock for assignment ID 512: marker (Teacher ID 130) is now unlocked for Exam ID 27, Subject ID 105', '2026-07-11 17:26:18', '::1'),
(258, 127, 130, 'toggle_marking_lock', 'Toggled marking lock for assignment ID 512: marker (Teacher ID 130) is now locked for Exam ID 27, Subject ID 105', '2026-07-11 17:29:52', '::1'),
(259, 127, 130, 'toggle_marking_lock', 'Toggled marking lock for assignment ID 512: marker (Teacher ID 130) is now unlocked for Exam ID 27, Subject ID 105', '2026-07-11 17:35:43', '::1'),
(260, 127, 130, 'toggle_marking_lock', 'Toggled marking lock for assignment ID 498: marker (Teacher ID 130) is now unlocked for Exam ID 27, Subject ID 104', '2026-07-11 17:38:06', '::1'),
(261, 127, 125, 'toggle_marking_lock', 'Toggled marking lock for assignment ID 501: marker (Teacher ID 125) is now unlocked for Exam ID 27, Subject ID 110', '2026-07-11 17:38:08', '::1'),
(262, 127, 130, 'unlock_all_marks', 'Unlocked all submitted marks for Exam ID 27, Subject ID 104 (reverted to draft)', '2026-07-11 17:43:39', '::1'),
(263, 127, NULL, 'receive_marks', 'Received submitted marks for Exam ID 27, Subject ID 105', '2026-07-11 18:11:43', '::1'),
(264, 127, 130, 'unlock_all_marks', 'Unlocked all submitted marks for Exam ID 27, Subject ID 105 (reverted to draft)', '2026-07-11 18:11:58', '::1'),
(265, 127, NULL, 'receive_marks', 'Received submitted marks (including late submissions) for Exam ID 27, Subject ID 104', '2026-07-11 18:33:45', '::1'),
(266, 127, NULL, 'receive_marks', 'Received submitted marks (including late submissions) for Exam ID 27, Subject ID 105', '2026-07-11 18:33:56', '::1'),
(267, 127, NULL, 'receive_marks', 'Received submitted marks (including late submissions) for Exam ID 27, Subject ID 105', '2026-07-11 18:39:57', '::1'),
(268, 127, NULL, 'USER_LOGOUT', '', '2026-07-11 18:42:02', '::1'),
(269, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"headteacher\"}', '2026-07-11 18:42:03', '::1'),
(270, 127, NULL, 'USER_LOGOUT', '', '2026-07-11 18:46:15', '::1'),
(271, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"examination_officer\"}', '2026-07-11 18:46:17', '::1'),
(272, 127, NULL, 'receive_marks', 'Received submitted marks (including late submissions) for Exam ID 27, Subject ID 107', '2026-07-11 18:57:50', '::1'),
(273, 127, NULL, 'receive_marks', 'Received submitted marks (including late submissions) for Exam ID 27, Subject ID 107', '2026-07-11 19:00:44', '::1'),
(274, 127, NULL, 'receive_marks', 'Received submitted marks (including late submissions) for Exam ID 27, Subject ID 107', '2026-07-11 19:01:25', '::1'),
(275, 127, NULL, 'receive_and_approve_marks', 'Received and auto-approved marks for Exam ID 27, Subject ID 107', '2026-07-11 19:04:10', '::1'),
(276, 127, NULL, 'forward_marks', 'Forwarded marks for Exam ID 27, Subject ID 107 to Headteacher', '2026-07-11 19:04:45', '::1'),
(277, 127, NULL, 'EXAM_SCHEDULE_UPDATED', '{\"exam_id\":27,\"class\":\"Form 2\",\"marks_deadline\":\"2026-07-20\"}', '2026-07-11 19:44:27', '::1'),
(278, 2, 2, 'USER_LOGIN_SUCCESS', '{\"email\":\"leonardponjemlungu@gmail.com\",\"role\":\"admin\"}', '2026-07-11 21:12:50', '::1'),
(279, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"examination_officer\"}', '2026-07-11 21:13:08', '::1'),
(280, 130, 130, 'USER_LOGIN_SUCCESS', '{\"email\":\"ict-01-09-22@unilia.ac.mw\",\"role\":\"teacher\"}', '2026-07-11 21:13:50', '::1'),
(281, 127, NULL, 'receive_and_approve_marks', 'Received and auto-approved marks for Exam ID 27, Subject ID 107', '2026-07-11 21:24:02', '::1'),
(282, 2, 2, 'USER_LOGIN_SUCCESS', '{\"email\":\"leonardponjemlungu@gmail.com\",\"role\":\"admin\"}', '2026-07-12 00:23:42', '::1'),
(283, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"examination_officer\"}', '2026-07-12 18:45:29', '::1'),
(284, 130, 130, 'USER_LOGIN_SUCCESS', '{\"email\":\"ict-01-09-22@unilia.ac.mw\",\"role\":\"teacher\"}', '2026-07-12 19:28:37', '::1'),
(285, 2, 2, 'USER_LOGIN_SUCCESS', '{\"email\":\"leonardponjemlungu@gmail.com\",\"role\":\"admin\"}', '2026-07-12 19:30:06', '::1'),
(286, 2, 2, 'USER_LOGIN_SUCCESS', '{\"email\":\"leonardponjemlungu@gmail.com\",\"role\":\"admin\"}', '2026-07-12 21:54:10', '::1'),
(287, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"examination_officer\"}', '2026-07-12 22:52:58', '::1'),
(288, 127, NULL, 'USER_LOGOUT', '', '2026-07-12 22:53:46', '::1'),
(289, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"headteacher\"}', '2026-07-12 22:53:50', '::1'),
(290, 127, 127, 'USER_LOGIN_SUCCESS', '{\"email\":\"mbalwelusekero@gmail.com\",\"role\":\"headteacher\"}', '2026-07-13 01:07:30', '::1'),
(291, 130, 130, 'USER_LOGIN_SUCCESS', '{\"email\":\"ict-01-09-22@unilia.ac.mw\",\"role\":\"teacher\"}', '2026-07-13 08:13:36', '::1'),
(292, 127, NULL, 'HT_FINDING_SAVED', '{\"exam_id\":25,\"trend\":\"declining\",\"cause_category\":\"teacher_absenteeism\",\"action_category\":\"remedial_classes\"}', '2026-07-13 08:19:22', '127.0.0.1'),
(293, 127, NULL, 'HT_FINDING_SAVED', '{\"exam_id\":25,\"trend\":\"declining\",\"cause_category\":\"teacher_absenteeism\",\"action_category\":\"remedial_classes\"}', '2026-07-13 08:24:52', '127.0.0.1'),
(294, 127, NULL, 'HT_FINDING_OUTCOME_SAVED', '{\"finding_id\":2,\"exam_id\":27,\"outcome\":\"improved\"}', '2026-07-13 08:24:52', '127.0.0.1'),
(295, 127, NULL, 'HT_FINDING_SAVED', '{\"exam_id\":25,\"trend\":\"declining\",\"cause_category\":\"teacher_absenteeism\",\"action_category\":\"remedial_classes\"}', '2026-07-13 08:28:33', '127.0.0.1'),
(296, 127, NULL, 'HT_FINDING_OUTCOME_SAVED', '{\"finding_id\":3,\"exam_id\":27,\"outcome\":\"improved\"}', '2026-07-13 08:28:34', '127.0.0.1'),
(297, 2, 2, 'USER_LOGIN_SUCCESS', '{\"email\":\"leonardponjemlungu@gmail.com\",\"role\":\"admin\"}', '2026-07-13 08:35:06', '::1'),
(298, 127, NULL, 'HT_FINDING_SAVED', '{\"exam_id\":27,\"trend\":\"flat\",\"cause_category\":\"staff_turnover\",\"action_category\":\"celebration_recognition\"}', '2026-07-13 08:55:16', '::1'),
(299, 2, NULL, 'DIV_FINDING_SAVED', '{\"exam_id\":24,\"trend\":\"flat\",\"cause\":\"teacher_shortage\",\"action\":\"textbook_distribution\"}', '2026-07-13 09:00:13', '::1'),
(300, 2, NULL, 'DIV_FINDING_OUTCOME_SAVED', '{\"finding_id\":1,\"exam_id\":26,\"outcome\":\"improved\"}', '2026-07-13 10:01:09', '::1'),
(301, 2, NULL, 'DIV_FINDING_SAVED', '{\"exam_id\":27,\"trend\":\"flat\",\"cause\":\"teacher_shortage\",\"action\":\"teacher_recruitment\"}', '2026-07-13 10:02:55', '::1');

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

DROP TABLE IF EXISTS `comments`;
CREATE TABLE IF NOT EXISTS `comments` (
  `comment_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `school_id` int DEFAULT NULL,
  `exam_id` int DEFAULT NULL,
  `comment_type` enum('school_note','exam_comment','feedback','observation') COLLATE utf8mb4_unicode_ci NOT NULL,
  `term` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` int DEFAULT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`comment_id`),
  KEY `user_id` (`user_id`),
  KEY `school_id` (`school_id`),
  KEY `exam_id` (`exam_id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`comment_id`, `user_id`, `school_id`, `exam_id`, `comment_type`, `term`, `year`, `comment`, `created_at`) VALUES
(1, 77, 32, NULL, 'school_note', NULL, NULL, 'hy', '2026-06-10 12:22:40'),
(6, 77, 32, NULL, 'school_note', 'exams', 2026, 'ttttt', '2026-06-15 11:53:36');

-- --------------------------------------------------------

--
-- Table structure for table `div_findings`
--

DROP TABLE IF EXISTS `div_findings`;
CREATE TABLE IF NOT EXISTS `div_findings` (
  `finding_id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `recorded_by` int NOT NULL,
  `trend` enum('declining','improving','flat') COLLATE utf8mb4_unicode_ci NOT NULL,
  `avg_score_pct` decimal(5,2) NOT NULL COMMENT 'Division average score at time of logging',
  `cause_category` enum('teacher_shortage','funding_delays','learning_material_deficiency','curriculum_misalignment','teacher_compliance_low','extreme_weather','administrative_laxity','positive_divisional_reform','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `cause_detail` text COLLATE utf8mb4_unicode_ci,
  `action_category` enum('teacher_recruitment','budget_allocation','textbook_distribution','inspection_blitz','teacher_capacity_building','remedial_policy_mandate','divisional_recognition','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_detail` text COLLATE utf8mb4_unicode_ci,
  `lifecycle_status` enum('open','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`finding_id`),
  UNIQUE KEY `uq_div_exam` (`exam_id`),
  KEY `idx_div_trend` (`trend`),
  KEY `idx_div_cause` (`cause_category`),
  KEY `idx_div_lifecycle` (`lifecycle_status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Division-level strategic findings per exam cycle';

--
-- Dumping data for table `div_findings`
--

INSERT INTO `div_findings` (`finding_id`, `exam_id`, `recorded_by`, `trend`, `avg_score_pct`, `cause_category`, `cause_detail`, `action_category`, `action_detail`, `lifecycle_status`, `created_at`, `updated_at`) VALUES
(1, 24, 2, 'flat', 0.00, 'teacher_shortage', '', 'textbook_distribution', '', 'closed', '2026-07-13 09:00:13', '2026-07-13 10:01:09'),
(2, 27, 2, 'flat', 10.00, 'teacher_shortage', '', 'teacher_recruitment', '', 'open', '2026-07-13 10:02:55', '2026-07-13 10:02:55');

-- --------------------------------------------------------

--
-- Table structure for table `div_finding_outcomes`
--

DROP TABLE IF EXISTS `div_finding_outcomes`;
CREATE TABLE IF NOT EXISTS `div_finding_outcomes` (
  `outcome_id` int NOT NULL AUTO_INCREMENT,
  `finding_id` int NOT NULL,
  `exam_id` int NOT NULL COMMENT 'The next exam when outcome was assessed',
  `recorded_by` int NOT NULL,
  `outcome` enum('improved','no_change','worsened') COLLATE utf8mb4_unicode_ci NOT NULL,
  `outcome_detail` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`outcome_id`),
  UNIQUE KEY `uq_div_finding_outcome` (`finding_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Strategic outcome assessment for division-level findings';

--
-- Dumping data for table `div_finding_outcomes`
--

INSERT INTO `div_finding_outcomes` (`outcome_id`, `finding_id`, `exam_id`, `recorded_by`, `outcome`, `outcome_detail`, `created_at`) VALUES
(1, 1, 26, 2, 'improved', '', '2026-07-13 10:01:09');

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

DROP TABLE IF EXISTS `exams`;
CREATE TABLE IF NOT EXISTS `exams` (
  `exam_id` int NOT NULL AUTO_INCREMENT,
  `exam_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exam_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('draft','active','completed') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `year` year DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `class` enum('Form 2','Form 4') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marks_deadline` datetime DEFAULT NULL,
  PRIMARY KEY (`exam_id`),
  KEY `fk_exam_creator` (`created_by`),
  KEY `idx_exam_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `exams`
--

INSERT INTO `exams` (`exam_id`, `exam_name`, `exam_code`, `created_by`, `start_date`, `end_date`, `status`, `year`, `created_at`, `class`, `marks_deadline`) VALUES
(24, 'MSCE 2026', 'MSCE2026', 2, '2026-10-05', '2026-11-20', 'draft', '2026', '2026-06-18 22:59:53', 'Form 4', NULL),
(25, 'MSCE 2025', 'MSCE2025', 2, '2026-10-05', '2026-11-20', 'draft', '2026', '2026-06-18 22:59:53', 'Form 4', NULL),
(26, 'JCE 2025', 'JCE2025', 2, '2026-10-05', '2026-11-20', 'draft', '2026', '2026-06-18 22:59:57', 'Form 2', NULL),
(27, 'JCE 2026', 'JCE2026', 2, '2026-09-15', '2026-10-02', 'active', '2026', '2026-06-18 23:04:24', 'Form 2', '2026-07-20 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `exam_documents`
--

DROP TABLE IF EXISTS `exam_documents`;
CREATE TABLE IF NOT EXISTS `exam_documents` (
  `document_id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `uploaded_by` int NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type` enum('question_paper','marking_scheme','attachment') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'question_paper',
  `version_number` int DEFAULT '1',
  `is_current` tinyint(1) DEFAULT '1',
  `uploaded_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`document_id`),
  KEY `exam_id` (`exam_id`),
  KEY `uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exam_subjects`
--

DROP TABLE IF EXISTS `exam_subjects`;
CREATE TABLE IF NOT EXISTS `exam_subjects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `subject_id` int NOT NULL,
  `exam_id` int NOT NULL,
  `class` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `duration_minutes` int NOT NULL DEFAULT '120',
  `total_marks` int NOT NULL DEFAULT '100',
  `registered_candidates` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `exam_id_2` (`exam_id`,`subject_id`),
  KEY `subject_id` (`subject_id`),
  KEY `exam_id` (`exam_id`),
  KEY `subject_id_2` (`subject_id`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `exam_subjects`
--

INSERT INTO `exam_subjects` (`id`, `subject_id`, `exam_id`, `class`, `status`, `assigned_at`, `duration_minutes`, `total_marks`, `registered_candidates`, `created_at`) VALUES
(1, 104, 27, 'Form 2', 'active', '2026-06-24 15:14:01', 120, 100, 58, '2026-06-24 15:14:01'),
(2, 105, 27, 'Form 2', 'active', '2026-06-24 15:14:01', 120, 100, 58, '2026-06-24 15:14:01'),
(3, 106, 27, 'Form 2', 'active', '2026-06-24 15:14:01', 120, 100, 58, '2026-06-24 15:14:01'),
(4, 107, 27, 'Form 2', 'active', '2026-06-24 15:14:01', 120, 100, 58, '2026-06-24 15:14:01'),
(5, 108, 27, 'Form 2', 'active', '2026-06-24 15:14:01', 120, 100, 58, '2026-06-24 15:14:01'),
(6, 109, 27, 'Form 2', 'active', '2026-06-24 15:14:01', 120, 100, 58, '2026-06-24 15:14:01'),
(7, 110, 27, 'Form 2', 'active', '2026-06-24 15:14:01', 120, 100, 58, '2026-06-24 15:14:01'),
(8, 111, 27, 'Form 2', 'active', '2026-06-24 15:14:01', 120, 100, 58, '2026-06-24 15:14:01'),
(9, 112, 27, 'Form 2', 'active', '2026-06-24 15:14:01', 120, 100, 58, '2026-06-24 15:14:01'),
(10, 113, 27, 'Form 2', 'active', '2026-06-24 15:14:01', 120, 100, 58, '2026-06-24 15:14:01'),
(11, 114, 27, 'Form 2', 'active', '2026-06-24 15:14:01', 120, 100, 58, '2026-06-24 15:14:01'),
(12, 115, 27, 'Form 2', 'active', '2026-06-24 15:14:01', 120, 100, 58, '2026-06-24 15:14:01'),
(13, 116, 27, 'Form 2', 'active', '2026-06-24 15:14:01', 120, 100, 58, '2026-06-24 15:14:01'),
(14, 117, 27, 'Form 2', 'active', '2026-06-24 15:14:01', 120, 100, 58, '2026-06-24 15:14:01'),
(15, 118, 27, 'Form 2', 'active', '2026-06-24 15:14:01', 120, 100, 58, '2026-06-24 15:14:01'),
(16, 104, 24, 'Form 4', 'active', '2026-06-30 18:06:41', 120, 100, 55, '2026-06-30 18:06:41'),
(17, 105, 24, 'Form 4', 'active', '2026-06-30 18:06:41', 120, 100, 55, '2026-06-30 18:06:41'),
(18, 106, 24, 'Form 4', 'active', '2026-06-30 18:06:41', 120, 100, 55, '2026-06-30 18:06:41'),
(19, 107, 24, 'Form 4', 'active', '2026-06-30 18:06:41', 120, 100, 55, '2026-06-30 18:06:41'),
(20, 108, 24, 'Form 4', 'active', '2026-06-30 18:06:41', 120, 100, 55, '2026-06-30 18:06:41'),
(21, 109, 24, 'Form 4', 'active', '2026-06-30 18:06:41', 120, 100, 55, '2026-06-30 18:06:41'),
(22, 110, 24, 'Form 4', 'active', '2026-06-30 18:06:41', 120, 100, 55, '2026-06-30 18:06:41'),
(23, 111, 24, 'Form 4', 'active', '2026-06-30 18:06:41', 120, 100, 55, '2026-06-30 18:06:41'),
(24, 112, 24, 'Form 4', 'active', '2026-06-30 18:06:41', 120, 100, 55, '2026-06-30 18:06:41'),
(25, 113, 24, 'Form 4', 'active', '2026-06-30 18:06:41', 120, 100, 55, '2026-06-30 18:06:41'),
(26, 114, 24, 'Form 4', 'active', '2026-06-30 18:06:41', 120, 100, 55, '2026-06-30 18:06:41'),
(27, 115, 24, 'Form 4', 'active', '2026-06-30 18:06:42', 120, 100, 55, '2026-06-30 18:06:42'),
(28, 116, 24, 'Form 4', 'active', '2026-06-30 18:06:42', 120, 100, 55, '2026-06-30 18:06:42'),
(29, 117, 24, 'Form 4', 'active', '2026-06-30 18:06:42', 120, 100, 55, '2026-06-30 18:06:42'),
(30, 118, 24, 'Form 4', 'active', '2026-06-30 18:06:42', 120, 100, 55, '2026-06-30 18:06:42'),
(31, 121, 24, 'Form 4', 'active', '2026-06-30 18:06:42', 120, 100, 55, '2026-06-30 18:06:42'),
(32, 104, 25, 'Form 4', 'active', '2026-07-11 13:06:43', 120, 100, 54, '2026-07-11 13:06:43'),
(33, 105, 25, 'Form 4', 'active', '2026-07-11 13:06:43', 120, 100, 54, '2026-07-11 13:06:43'),
(34, 106, 25, 'Form 4', 'active', '2026-07-11 13:06:43', 120, 100, 54, '2026-07-11 13:06:43'),
(35, 107, 25, 'Form 4', 'active', '2026-07-11 13:06:43', 120, 100, 54, '2026-07-11 13:06:43'),
(36, 108, 25, 'Form 4', 'active', '2026-07-11 13:06:43', 120, 100, 54, '2026-07-11 13:06:43'),
(37, 109, 25, 'Form 4', 'active', '2026-07-11 13:06:43', 120, 100, 54, '2026-07-11 13:06:43'),
(38, 110, 25, 'Form 4', 'active', '2026-07-11 13:06:43', 120, 100, 54, '2026-07-11 13:06:43'),
(39, 111, 25, 'Form 4', 'active', '2026-07-11 13:06:43', 120, 100, 54, '2026-07-11 13:06:43'),
(40, 112, 25, 'Form 4', 'active', '2026-07-11 13:06:43', 120, 100, 54, '2026-07-11 13:06:43'),
(41, 113, 25, 'Form 4', 'active', '2026-07-11 13:06:43', 120, 100, 54, '2026-07-11 13:06:43'),
(42, 114, 25, 'Form 4', 'active', '2026-07-11 13:06:43', 120, 100, 54, '2026-07-11 13:06:43'),
(43, 115, 25, 'Form 4', 'active', '2026-07-11 13:06:43', 120, 100, 54, '2026-07-11 13:06:43'),
(44, 116, 25, 'Form 4', 'active', '2026-07-11 13:06:44', 120, 100, 54, '2026-07-11 13:06:44'),
(45, 117, 25, 'Form 4', 'active', '2026-07-11 13:06:44', 120, 100, 54, '2026-07-11 13:06:44'),
(46, 118, 25, 'Form 4', 'active', '2026-07-11 13:06:44', 120, 100, 54, '2026-07-11 13:06:44'),
(47, 121, 25, 'Form 4', 'active', '2026-07-11 13:06:44', 120, 100, 54, '2026-07-11 13:06:44');

-- --------------------------------------------------------

--
-- Table structure for table `exam_workflow_logs`
--

DROP TABLE IF EXISTS `exam_workflow_logs`;
CREATE TABLE IF NOT EXISTS `exam_workflow_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `performed_by` int DEFAULT NULL,
  `role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`log_id`),
  KEY `exam_id` (`exam_id`),
  KEY `performed_by` (`performed_by`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `exam_workflow_logs`
--

INSERT INTO `exam_workflow_logs` (`log_id`, `exam_id`, `action`, `performed_by`, `role`, `timestamp`, `notes`) VALUES
(1, 19, 'question_moderation', 78, 'moderator', '2026-06-15 11:39:19', 'Moderated question ID 101 as revise'),
(2, 19, 'question_moderation', 78, 'moderator', '2026-06-15 11:39:51', 'Moderated question ID 101 as rejected'),
(3, 19, 'question_moderation', 78, 'moderator', '2026-06-15 11:46:18', 'Moderated question ID 102 as approved'),
(4, 27, 'question_moderation', 127, 'moderator', '2026-06-29 20:41:57', 'Moderated question ID 103 as approved'),
(5, 27, 'question_moderation', 127, 'moderator', '2026-06-29 20:42:18', 'Moderated question ID 103 as approved'),
(6, 27, 'question_moderation', 127, 'moderator', '2026-06-29 21:01:41', 'Moderated question ID 104 as rejected'),
(7, 27, 'question_moderation', 127, 'moderator', '2026-06-29 21:02:18', 'Moderated question ID 104 as approved'),
(8, 27, 'question_moderation', 127, 'moderator', '2026-06-29 21:02:26', 'Moderated question ID 105 as revise'),
(9, 27, 'question_moderation', 127, 'moderator', '2026-06-29 21:02:34', 'Moderated question ID 105 as approved'),
(10, 27, 'question_moderation', 127, 'moderator', '2026-06-29 21:02:43', 'Moderated question ID 106 as approved');

-- --------------------------------------------------------

--
-- Table structure for table `ht_findings`
--

DROP TABLE IF EXISTS `ht_findings`;
CREATE TABLE IF NOT EXISTS `ht_findings` (
  `finding_id` int NOT NULL AUTO_INCREMENT,
  `school_id` int NOT NULL,
  `exam_id` int NOT NULL,
  `recorded_by` int NOT NULL,
  `trend` enum('declining','improving','flat') COLLATE utf8mb4_unicode_ci NOT NULL,
  `pass_rate_pct` decimal(5,2) NOT NULL COMMENT 'Snapshot pass rate at time of logging',
  `cause_category` enum('teacher_absenteeism','resource_shortage','curriculum_gap','student_discipline','assessment_irregularity','illness_outbreak','staff_turnover','low_attendance','external_disruption','positive_intervention','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `cause_detail` text COLLATE utf8mb4_unicode_ci,
  `action_category` enum('remedial_classes','staff_redeployment','resource_procurement','parent_engagement','curriculum_revision','attendance_campaign','pastoral_support','peer_mentoring','teacher_cpd','celebration_recognition','no_action_yet','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_detail` text COLLATE utf8mb4_unicode_ci,
  `lifecycle_status` enum('open','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`finding_id`),
  UNIQUE KEY `uq_school_exam` (`school_id`,`exam_id`),
  KEY `idx_school_trend` (`school_id`,`trend`),
  KEY `idx_cause_cat` (`cause_category`),
  KEY `idx_lifecycle` (`lifecycle_status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Headteacher structured findings per exam cycle';

--
-- Dumping data for table `ht_findings`
--

INSERT INTO `ht_findings` (`finding_id`, `school_id`, `exam_id`, `recorded_by`, `trend`, `pass_rate_pct`, `cause_category`, `cause_detail`, `action_category`, `action_detail`, `lifecycle_status`, `created_at`, `updated_at`) VALUES
(3, 39, 25, 127, 'declining', 42.50, 'teacher_absenteeism', 'Three teachers left early in the trimester.', 'remedial_classes', 'Set up Saturday booster sessions.', 'closed', '2026-07-13 08:28:33', '2026-07-13 08:28:34'),
(4, 39, 27, 127, 'flat', 0.00, 'staff_turnover', '', 'celebration_recognition', '', 'open', '2026-07-13 08:55:16', '2026-07-13 08:55:16');

-- --------------------------------------------------------

--
-- Table structure for table `ht_finding_outcomes`
--

DROP TABLE IF EXISTS `ht_finding_outcomes`;
CREATE TABLE IF NOT EXISTS `ht_finding_outcomes` (
  `outcome_id` int NOT NULL AUTO_INCREMENT,
  `finding_id` int NOT NULL,
  `school_id` int NOT NULL COMMENT 'Denormalised for scoping queries',
  `exam_id` int NOT NULL COMMENT 'The next exam when outcome was assessed',
  `recorded_by` int NOT NULL,
  `outcome` enum('improved','no_change','worsened') COLLATE utf8mb4_unicode_ci NOT NULL,
  `outcome_detail` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`outcome_id`),
  UNIQUE KEY `uq_finding_outcome` (`finding_id`),
  KEY `idx_school_outcome` (`school_id`,`outcome`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Outcome assessment for a prior headteacher finding';

--
-- Dumping data for table `ht_finding_outcomes`
--

INSERT INTO `ht_finding_outcomes` (`outcome_id`, `finding_id`, `school_id`, `exam_id`, `recorded_by`, `outcome`, `outcome_detail`, `created_at`) VALUES
(2, 3, 39, 27, 127, 'improved', 'Booster sessions increased the average pass rate.', '2026-07-13 08:28:34');

-- --------------------------------------------------------

--
-- Table structure for table `marking_assignments`
--

DROP TABLE IF EXISTS `marking_assignments`;
CREATE TABLE IF NOT EXISTS `marking_assignments` (
  `assignment_id` int NOT NULL AUTO_INCREMENT,
  `school_id` int NOT NULL,
  `exam_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `teacher_id` int NOT NULL,
  `deadline` date DEFAULT NULL,
  `override_lock` tinyint(1) NOT NULL DEFAULT '0',
  `unlock_requested` tinyint(1) NOT NULL DEFAULT '0',
  `assigned_by` int NOT NULL,
  `assigned_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'assigned',
  PRIMARY KEY (`assignment_id`),
  UNIQUE KEY `unique_marking_assignment` (`exam_id`,`subject_id`,`school_id`),
  KEY `subject_id` (`subject_id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `assigned_by` (`assigned_by`)
) ENGINE=InnoDB AUTO_INCREMENT=525 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `marking_assignments`
--

INSERT INTO `marking_assignments` (`assignment_id`, `school_id`, `exam_id`, `subject_id`, `teacher_id`, `deadline`, `override_lock`, `unlock_requested`, `assigned_by`, `assigned_at`, `status`) VALUES
(513, 39, 27, 104, 130, '2026-07-06', 0, 0, 127, '2026-07-11 18:43:44', 'assigned'),
(514, 39, 27, 110, 124, '2026-07-06', 0, 0, 127, '2026-07-11 18:43:50', 'assigned'),
(515, 39, 27, 105, 129, '2026-07-06', 0, 0, 127, '2026-07-11 18:43:58', 'assigned'),
(516, 39, 27, 108, 130, '2026-07-06', 0, 0, 127, '2026-07-11 18:44:03', 'assigned'),
(517, 39, 27, 109, 126, '2026-07-06', 0, 0, 127, '2026-07-11 18:44:09', 'assigned'),
(518, 39, 27, 112, 129, '2026-07-06', 0, 0, 127, '2026-07-11 18:44:14', 'assigned'),
(519, 39, 27, 114, 125, '2026-07-06', 0, 0, 127, '2026-07-11 18:44:20', 'assigned'),
(520, 39, 27, 111, 126, '2026-07-06', 0, 0, 127, '2026-07-11 18:44:25', 'assigned'),
(521, 39, 27, 117, 125, '2026-07-06', 0, 0, 127, '2026-07-11 18:44:31', 'assigned'),
(522, 39, 27, 107, 130, '2026-07-06', 0, 0, 127, '2026-07-11 18:44:37', 'assigned'),
(523, 39, 27, 106, 130, '2026-07-06', 0, 0, 127, '2026-07-11 18:44:43', 'assigned'),
(524, 39, 27, 118, 125, '2026-07-06', 0, 0, 127, '2026-07-11 18:44:49', 'assigned');

-- --------------------------------------------------------

--
-- Table structure for table `marks`
--

DROP TABLE IF EXISTS `marks`;
CREATE TABLE IF NOT EXISTS `marks` (
  `mark_id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `exam_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `teacher_id` int NOT NULL,
  `score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `grade` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('draft','submitted','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `submitted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submission_status` enum('submitted','received','forwarded_to_edm') COLLATE utf8mb4_unicode_ci DEFAULT 'submitted',
  `submitted_by` int DEFAULT NULL,
  `received_by` int DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `received_at` datetime DEFAULT NULL,
  PRIMARY KEY (`mark_id`),
  UNIQUE KEY `unique_mark` (`exam_id`,`subject_id`,`student_id`),
  KEY `fk_marks_exam` (`exam_id`),
  KEY `fk_marks_teacher` (`teacher_id`),
  KEY `fk_marks_subject` (`subject_id`)
) ENGINE=MyISAM AUTO_INCREMENT=1379 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `marks`
--

INSERT INTO `marks` (`mark_id`, `student_id`, `exam_id`, `subject_id`, `teacher_id`, `score`, `grade`, `status`, `remarks`, `submitted_at`, `created_at`, `file_path`, `submission_status`, `submitted_by`, `received_by`, `notes`, `received_at`) VALUES
(1378, 120, 27, 107, 130, 10.00, '9', 'approved', NULL, '2026-07-11 16:54:30', '2026-07-11 18:54:30', NULL, 'received', 130, 127, NULL, '2026-07-11 19:24:02');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE IF NOT EXISTS `password_resets` (
  `reset_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `otp_code` varchar(8) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reset_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`reset_id`, `user_id`, `otp_code`, `expires_at`, `used`, `created_at`) VALUES
(1, 82, '291439', '2026-05-28 07:44:22', 0, '2026-05-28 09:34:22'),
(2, 82, '394168', '2026-05-28 07:45:24', 1, '2026-05-28 09:35:24'),
(3, 2, '337382', '2026-06-15 12:16:27', 0, '2026-06-15 14:06:27'),
(4, 2, '192844', '2026-06-16 08:13:50', 1, '2026-06-16 10:03:50'),
(5, 2, '608112', '2026-06-16 08:19:01', 0, '2026-06-16 10:09:01'),
(6, 2, '288827', '2026-06-16 09:08:02', 0, '2026-06-16 10:58:02'),
(7, 82, '367213', '2026-06-16 10:08:33', 1, '2026-06-16 11:58:33'),
(8, 2, '099754', '2026-06-16 10:11:04', 1, '2026-06-16 12:01:04'),
(9, 2, '362574', '2026-06-16 10:11:32', 0, '2026-06-16 12:01:32'),
(10, 2, '274231', '2026-06-16 10:18:40', 1, '2026-06-16 12:08:40'),
(11, 2, '367028', '2026-06-16 10:31:49', 0, '2026-06-16 12:21:49'),
(12, 2, '496620', '2026-06-16 12:46:22', 0, '2026-06-16 12:36:22'),
(13, 2, '461855', '2026-06-16 12:46:54', 0, '2026-06-16 12:36:54'),
(14, 2, '994785', '2026-06-16 12:50:29', 0, '2026-06-16 12:40:29'),
(15, 2, '215619', '2026-06-16 12:51:30', 0, '2026-06-16 12:41:30'),
(16, 2, '449330', '2026-06-16 12:52:22', 0, '2026-06-16 12:42:22'),
(17, 127, '285944', '2026-06-18 00:31:48', 1, '2026-06-18 00:21:48'),
(18, 2, '360559', '2026-06-18 21:57:02', 1, '2026-06-18 21:47:02'),
(19, 127, '058752', '2026-06-19 03:22:15', 0, '2026-06-19 03:12:15'),
(20, 129, '741523', '2026-06-19 03:26:16', 1, '2026-06-19 03:16:16'),
(21, 125, '425157', '2026-06-19 03:32:58', 0, '2026-06-19 03:22:58'),
(22, 125, '379744', '2026-06-19 03:33:28', 0, '2026-06-19 03:23:28'),
(23, 125, '281501', '2026-06-19 03:35:01', 1, '2026-06-19 03:25:01'),
(24, 126, '531361', '2026-06-19 03:40:54', 1, '2026-06-19 03:30:54'),
(25, 134, '752935', '2026-07-04 12:07:20', 1, '2026-07-04 11:57:20'),
(26, 127, '675689', '2026-07-07 17:46:49', 1, '2026-07-07 17:36:49'),
(27, 127, '813378', '2026-07-10 17:40:19', 0, '2026-07-10 17:30:19'),
(28, 127, '225878', '2026-07-10 17:45:50', 0, '2026-07-10 17:35:50'),
(29, 127, '301998', '2026-07-10 17:46:18', 0, '2026-07-10 17:36:18'),
(30, 127, '436063', '2026-07-10 17:47:23', 0, '2026-07-10 17:37:23'),
(31, 127, '500925', '2026-07-10 17:50:15', 1, '2026-07-10 17:40:15');

-- --------------------------------------------------------

--
-- Table structure for table `profile_change_otps`
--

DROP TABLE IF EXISTS `profile_change_otps`;
CREATE TABLE IF NOT EXISTS `profile_change_otps` (
  `otp_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `otp_code` varchar(8) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`otp_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `profile_change_otps`
--

INSERT INTO `profile_change_otps` (`otp_id`, `user_id`, `otp_code`, `expires_at`, `used`, `created_at`) VALUES
(1, 2, '210753', '2026-06-18 01:52:00', 1, '2026-06-18 01:42:00'),
(2, 127, '707779', '2026-06-18 01:56:24', 1, '2026-06-18 01:46:24'),
(3, 127, '374444', '2026-06-18 01:56:48', 0, '2026-06-18 01:46:48'),
(4, 127, '126547', '2026-06-18 02:01:26', 1, '2026-06-18 01:51:26'),
(5, 127, '101222', '2026-06-18 02:04:05', 1, '2026-06-18 01:54:05'),
(6, 127, '158636', '2026-06-18 22:29:45', 1, '2026-06-18 22:19:45'),
(7, 129, '273531', '2026-06-19 03:29:00', 0, '2026-06-19 03:19:00'),
(8, 129, '510969', '2026-06-19 03:30:25', 1, '2026-06-19 03:20:25'),
(9, 127, '878605', '2026-06-25 11:40:44', 1, '2026-06-25 11:30:44'),
(10, 127, '503733', '2026-06-30 15:00:27', 1, '2026-06-30 14:50:27'),
(11, 131, '092036', '2026-07-01 13:17:03', 1, '2026-07-01 13:07:03'),
(12, 131, '919947', '2026-07-02 10:31:05', 0, '2026-07-02 10:21:05'),
(13, 127, '996997', '2026-07-04 11:43:18', 0, '2026-07-04 11:33:18'),
(14, 134, '270468', '2026-07-04 12:11:20', 1, '2026-07-04 12:01:20'),
(15, 127, '207849', '2026-07-10 17:29:04', 0, '2026-07-10 17:19:04');

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

DROP TABLE IF EXISTS `questions`;
CREATE TABLE IF NOT EXISTS `questions` (
  `question_id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `exam_subject_id` int DEFAULT NULL COMMENT 'FK to exam_subjects(id) ??? replaces direct exam_id for subject scoping',
  `question_order` int DEFAULT NULL,
  `question_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `marks` int NOT NULL,
  `created_by` int DEFAULT NULL,
  `section_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Section A',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `question_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'short',
  `option_a` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `option_b` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `option_c` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `option_d` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correct_option` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ai_score` float DEFAULT '1',
  `moderation_status` enum('pending','approved','revise','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `moderator_comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ai_difficulty` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ai_cognitive_level` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ai_recommendations` text COLLATE utf8mb4_unicode_ci,
  `ai_topic` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ai_quality_score` decimal(5,2) DEFAULT NULL,
  `ai_analysis_date` datetime DEFAULT NULL,
  PRIMARY KEY (`question_id`),
  KEY `fk_question_exam` (`exam_id`),
  KEY `idx_question_moderation` (`moderation_status`),
  KEY `idx_question_exam_subject` (`exam_subject_id`)
) ENGINE=InnoDB AUTO_INCREMENT=107 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`question_id`, `exam_id`, `exam_subject_id`, `question_order`, `question_text`, `marks`, `created_by`, `section_name`, `created_at`, `question_type`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`, `ai_score`, `moderation_status`, `moderator_comment`, `ai_difficulty`, `ai_cognitive_level`, `ai_recommendations`, `ai_topic`, `ai_quality_score`, `ai_analysis_date`) VALUES
(103, 27, 4, 1, 'EXLAIN ANY TWO WAYS OF MODERING.', 48, 127, 'Section B', '2026-06-26 16:16:48', 'structured', '', '', '', '', 'A', 76, 'revise', 'AI Flagged: 1 grammar issue(s) detected.; Add more context so learners understand what is assessed.; Phrase as a direct question or command starting with a verb.', 'Medium', 'Analysing', '[\"Add more context so learners understand what is assessed.\",\"Phrase as a direct question or command starting with a verb.\",\"End the question with ? or .\",\"Marks (48) seem high for Analyze level � suggest 8.\",\"Good higher-order thinking question.\"]', 'EXLAIN, MODERING', 76.00, '2026-06-26 16:16:48'),
(104, 27, 1, 2, 'what is agriculture ?', 3, 127, 'Section B', '2026-06-29 10:20:22', 'structured', '', '', '', '', 'A', 51, 'approved', 'AI Flagged: Low quality score — review before publishing.; 1 grammar issue(s) detected.; Question is too short — add more context.', 'Easy', 'Creating', '[\"Question is too short — add more context.\",\"Include an action verb (e.g. explain, calculate, compare).\",\"Start with a capital letter.\",\"Consider increasing marks to 12 for Create level.\",\"Good higher-order thinking question.\"]', 'agriculture', 51.00, '2026-06-29 10:20:22'),
(105, 27, 4, 3, 'bhhh', 8, 127, 'Section B', '2026-06-29 10:28:21', 'structured', '', '', '', '', 'A', 37, 'approved', 'AI Flagged: Low quality score — review before publishing.; 2 grammar issue(s) detected.; Question is too short — add more context.', 'Easy', 'Understanding', '[\"Question is too short — add more context.\",\"Include an action verb (e.g. explain, calculate, compare).\",\"Phrase as a direct question or command starting with a verb.\",\"Start with a capital letter.\",\"End the question with ? or .\",\"Marks (8) seem high for Understand level — suggest 4.\"]', 'General', 37.00, '2026-06-29 10:28:21'),
(106, 27, 4, 4, 'where are you', 3, 127, 'Section C', '2026-06-29 10:33:00', 'structured', '', '', '', '', 'A', 47, 'approved', 'AI Flagged: Low quality score — review before publishing.; 2 grammar issue(s) detected.; Question is too short — add more context.', 'Easy', 'Applying', '[\"Question is too short — add more context.\",\"Include an action verb (e.g. explain, calculate, compare).\",\"Start with a capital letter.\",\"End the question with ? or .\",\"Consider increasing marks to 6 for Apply level.\"]', 'where', 47.00, '2026-06-29 10:33:00');

-- --------------------------------------------------------

--
-- Table structure for table `question_moderation`
--

DROP TABLE IF EXISTS `question_moderation`;
CREATE TABLE IF NOT EXISTS `question_moderation` (
  `moderation_id` int NOT NULL AUTO_INCREMENT,
  `question_id` int NOT NULL,
  `moderator_id` int NOT NULL,
  `status` enum('approved','revise','rejected') COLLATE utf8mb4_unicode_ci NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `moderated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`moderation_id`),
  KEY `question_id` (`question_id`),
  KEY `moderator_id` (`moderator_id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `question_moderation`
--

INSERT INTO `question_moderation` (`moderation_id`, `question_id`, `moderator_id`, `status`, `comment`, `moderated_at`) VALUES
(1, 101, 78, 'revise', 'ffff', '2026-06-15 11:39:19'),
(2, 101, 78, 'rejected', 'kkkkkk', '2026-06-15 11:39:51'),
(3, 102, 78, 'approved', 'it is good', '2026-06-15 11:46:18'),
(4, 103, 127, 'approved', 'AI Flagged: 1 grammar issue(s) detected.; Add more context so learners understand what is assessed.; Phrase as a direct question or command starting with a verb.', '2026-06-29 20:41:57'),
(5, 103, 127, 'approved', 'AI Flagged: 1 grammar issue(s) detected.; Add more context so learners understand what is assessed.; Phrase as a direct question or command starting with a verb.', '2026-06-29 20:42:18'),
(6, 104, 127, 'rejected', 'AI Flagged: Low quality score — review before publishing.; 1 grammar issue(s) detected.; Question is too short — add more context.', '2026-06-29 21:01:41'),
(7, 104, 127, 'approved', 'AI Flagged: Low quality score — review before publishing.; 1 grammar issue(s) detected.; Question is too short — add more context.', '2026-06-29 21:02:18'),
(8, 105, 127, 'revise', 'AI Flagged: Low quality score — review before publishing.; 2 grammar issue(s) detected.; Question is too short — add more context.', '2026-06-29 21:02:26'),
(9, 105, 127, 'approved', 'AI Flagged: Low quality score — review before publishing.; 2 grammar issue(s) detected.; Question is too short — add more context.', '2026-06-29 21:02:34'),
(10, 106, 127, 'approved', 'AI Flagged: Low quality score — review before publishing.; 2 grammar issue(s) detected.; Question is too short — add more context.', '2026-06-29 21:02:43');

-- --------------------------------------------------------

--
-- Table structure for table `results`
--

DROP TABLE IF EXISTS `results`;
CREATE TABLE IF NOT EXISTS `results` (
  `result_id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `exam_id` int NOT NULL,
  `term` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `year` year NOT NULL,
  `class` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_subjects` int DEFAULT '0',
  `total_score` decimal(8,2) DEFAULT NULL,
  `average_score` decimal(5,2) DEFAULT NULL,
  `grade` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position_in_class` int DEFAULT NULL,
  `compiled_by` int DEFAULT NULL,
  `compiled_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` enum('draft','submitted','approved','published','eo_approved','head_approved','edm_approved') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `locked` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`result_id`),
  KEY `student_id` (`student_id`),
  KEY `compiled_by` (`compiled_by`),
  KEY `fk_results_exam` (`exam_id`)
) ENGINE=MyISAM AUTO_INCREMENT=120 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `results`
--

INSERT INTO `results` (`result_id`, `student_id`, `exam_id`, `term`, `year`, `class`, `total_subjects`, `total_score`, `average_score`, `grade`, `position_in_class`, `compiled_by`, `compiled_at`, `status`, `locked`) VALUES
(119, 120, 27, 'Term 1', '2026', 'Form 2', 1, 10.00, 10.00, '9', 1, 2, '2026-07-12 18:51:00', 'published', 1);

-- --------------------------------------------------------

--
-- Table structure for table `result_workflow_logs`
--

DROP TABLE IF EXISTS `result_workflow_logs`;
CREATE TABLE IF NOT EXISTS `result_workflow_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `result_id` int NOT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `performed_by` int DEFAULT NULL,
  `role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `result_id` (`result_id`),
  KEY `performed_by` (`performed_by`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `result_workflow_logs`
--

INSERT INTO `result_workflow_logs` (`log_id`, `result_id`, `action`, `performed_by`, `role`, `from_status`, `to_status`, `notes`, `created_at`) VALUES
(1, 0, 'compile_results', 2, 'EDM', NULL, NULL, 'Bulk compilation for exam 1', '2026-06-10 18:32:38'),
(2, 0, 'compile_results', 2, 'ADMIN', NULL, NULL, 'Compiled exam ID 1', '2026-06-10 19:36:38'),
(3, 0, 'compile_results', 2, 'ADMIN', NULL, NULL, 'Compiled exam ID 2', '2026-06-10 19:36:55'),
(4, 0, 'compile_results', 2, 'ADMIN', NULL, NULL, 'Compiled exam ID 26: 0 new, 56 updated', '2026-07-07 00:31:17'),
(5, 0, 'compile_results', 2, 'ADMIN', NULL, NULL, 'Compiled exam ID 26: 0 new, 56 updated', '2026-07-07 00:46:06'),
(6, 0, 'compile_results', 2, 'ADMIN', NULL, NULL, 'Compiled exam ID 27: 1 new, 0 updated', '2026-07-12 20:51:00'),
(7, 0, 'publish_results', 2, 'ADMIN', NULL, NULL, 'Published results for exam ID 27 (1 students)', '2026-07-12 20:51:38');

-- --------------------------------------------------------

--
-- Table structure for table `schools`
--

DROP TABLE IF EXISTS `schools`;
CREATE TABLE IF NOT EXISTS `schools` (
  `school_id` int NOT NULL AUTO_INCREMENT,
  `school_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `district` enum('Chitipa','Karonga','Rumphi','Mzimba','Nkhata Bay','Likoma') COLLATE utf8mb4_unicode_ci NOT NULL,
  `cluster_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `school_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `school_type` enum('CDSS','DAY SECONDARY','BOARDING','PRIVATE') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `headteacher_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  PRIMARY KEY (`school_id`),
  UNIQUE KEY `school_number` (`school_number`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schools`
--

INSERT INTO `schools` (`school_id`, `school_name`, `district`, `cluster_name`, `school_number`, `school_type`, `phone`, `email`, `headteacher_name`, `address`, `status`) VALUES
(29, 'KARONGA COMMUNITY SECONDARY SCHOOL', 'Karonga', NULL, NULL, NULL, NULL, NULL, NULL, 'P.O BOX 39', 'active'),
(32, 'Maghemo secondary school', 'Karonga', NULL, NULL, NULL, NULL, NULL, NULL, 'p.o.box 111', 'active'),
(33, 'Maghemo secondary school', 'Karonga', NULL, NULL, NULL, NULL, NULL, NULL, 'p.o.box 111', 'active'),
(34, 'Mlare secondary school', 'Karonga', NULL, NULL, NULL, NULL, NULL, NULL, 'p.o.box 11', 'active'),
(35, 'Karonga girls secondary school', 'Karonga', NULL, NULL, NULL, NULL, NULL, NULL, 'p.o.box 10', 'active'),
(38, 'Karonga Community Secondary', 'Karonga', 'North', 'KSS001', 'DAY SECONDARY', '888123456', 'kss@education.gov.mw', 'John Banda', 'P.O. Box 39, Karonga', 'active'),
(39, 'Iponga CDSS', 'Chitipa', 'Central', 'IPC001', 'CDSS', '999876543', '', 'Mary Phiri', 'P.O. Box 101, Chitipa', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `school_reports`
--

DROP TABLE IF EXISTS `school_reports`;
CREATE TABLE IF NOT EXISTS `school_reports` (
  `report_id` int NOT NULL AUTO_INCREMENT,
  `school_id` int NOT NULL,
  `term` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` int DEFAULT NULL,
  `total_students` int DEFAULT NULL,
  `attendance_rate` float DEFAULT '0',
  `pass_rate` float DEFAULT '0',
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `school_id` int DEFAULT NULL,
  `class` enum('Form 2','Form 4') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exam_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `gender` enum('Male','Female') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Male',
  `special_needs` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'None',
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `exam_number` (`exam_number`),
  KEY `fk_student_school` (`school_id`)
) ENGINE=InnoDB AUTO_INCREMENT=127 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `name`, `school_id`, `class`, `exam_number`, `status`, `gender`, `special_needs`) VALUES
(1, 'John Banda', 39, 'Form 2', 'EX001', 'active', 'Male', 'None'),
(2, 'Mary Phiri', 39, 'Form 2', 'EX002', 'active', 'Female', 'None'),
(3, 'Peter Mwale', 32, 'Form 2', 'EX003', 'active', 'Male', 'None'),
(16, 'Leonardponje mlungu', 39, 'Form 2', 'MW298854', 'active', 'Female', 'None'),
(17, 'JOHN PONJE', 39, 'Form 2', 'MW296968', 'active', 'Male', 'Visually Impaired'),
(18, 'Mlungu Leonard Ponje', 39, 'Form 2', 'MW29885000', 'active', 'Female', 'None'),
(19, 'Memory Zulu', 29, 'Form 2', 'MW29114100', 'active', 'Male', 'None'),
(20, 'Sungeni Zulu', 29, 'Form 2', 'MW29428201', 'active', 'Female', 'None'),
(21, 'Gift Banda', 29, 'Form 2', 'MW29301463', 'active', 'Male', 'None'),
(22, 'Tinashe Chirwa', 29, 'Form 2', 'MW29182199', 'active', 'Female', 'None'),
(23, 'Peter Msiska', 29, 'Form 2', 'MW29421615', 'active', 'Male', 'None'),
(24, 'Tinashe Nyirenda', 29, 'Form 2', 'MW29361279', 'active', 'Female', 'None'),
(25, 'Sungeni Chirwa', 29, 'Form 2', 'MW29229138', 'active', 'Male', 'None'),
(26, 'Chikondi Nyirenda', 29, 'Form 2', 'MW29140079', 'active', 'Female', 'None'),
(27, 'Sungeni Nyirenda', 29, 'Form 4', 'MW29743771', 'active', 'Male', 'None'),
(28, 'Peter Gondwe', 29, 'Form 4', 'MW29492979', 'active', 'Female', 'None'),
(29, 'Memory Kawonga', 29, 'Form 4', 'MW29312576', 'active', 'Male', 'Hearing Impaired'),
(30, 'Gift Nkhoma', 29, 'Form 4', 'MW29147417', 'active', 'Female', 'None'),
(31, 'Gift Banda', 29, 'Form 4', 'MW29354589', 'active', 'Male', 'None'),
(32, 'Grace Kawonga', 29, 'Form 4', 'MW29510957', 'active', 'Female', 'None'),
(33, 'Mayamiko Gondwe', 29, 'Form 4', 'MW29487159', 'active', 'Male', 'None'),
(34, 'Sungeni Mwale', 29, 'Form 4', 'MW29520390', 'active', 'Female', 'Visually Impaired'),
(35, 'Yamiko Tembo', 32, 'Form 2', 'MW32476823', 'active', 'Male', 'None'),
(36, 'Chikondi Zulu', 32, 'Form 2', 'MW32543077', 'active', 'Female', 'None'),
(37, 'Esnart Msiska', 32, 'Form 2', 'MW32720055', 'active', 'Male', 'None'),
(38, 'Peter Phiri', 32, 'Form 2', 'MW32550734', 'active', 'Female', 'None'),
(39, 'Tiwonge Phiri', 32, 'Form 2', 'MW32137311', 'active', 'Male', 'None'),
(40, 'Chisomo Chilima', 32, 'Form 2', 'MW32138292', 'active', 'Female', 'None'),
(41, 'Gift Mwale', 32, 'Form 2', 'MW32490108', 'active', 'Male', 'None'),
(42, 'Limbani Gondwe', 32, 'Form 4', 'MW32249627', 'active', 'Female', 'None'),
(43, 'Mayamiko Tembo', 32, 'Form 4', 'MW32578246', 'active', 'Male', 'None'),
(44, 'Grace Chirwa', 32, 'Form 4', 'MW32296627', 'active', 'Female', 'None'),
(45, 'Grace Kawonga', 32, 'Form 4', 'MW32957899', 'active', 'Male', 'None'),
(46, 'Chikondi Phiri', 32, 'Form 4', 'MW32611676', 'active', 'Female', 'None'),
(47, 'Tiwonge Chilima', 32, 'Form 4', 'MW32457069', 'active', 'Male', 'None'),
(48, 'Chikondi Chilima', 32, 'Form 4', 'MW32934840', 'active', 'Female', 'None'),
(49, 'Mercy Kumwenda', 32, 'Form 4', 'MW32721268', 'active', 'Male', 'None'),
(50, 'Wongani Zulu', 33, 'Form 2', 'MW33276050', 'active', 'Female', 'None'),
(51, 'Sungeni Gondwe', 33, 'Form 2', 'MW33970909', 'active', 'Male', 'Visually Impaired'),
(52, 'Mercy Kumwenda', 33, 'Form 2', 'MW33290139', 'active', 'Female', 'None'),
(53, 'Mercy Gondwe', 33, 'Form 2', 'MW33251471', 'active', 'Male', 'None'),
(54, 'Esnart Banda', 33, 'Form 2', 'MW33715703', 'active', 'Female', 'None'),
(55, 'Wongani Chirwa', 33, 'Form 2', 'MW33219062', 'active', 'Male', 'None'),
(56, 'Memory Chirwa', 33, 'Form 2', 'MW33229225', 'active', 'Female', 'None'),
(57, 'Mercy Nkhoma', 33, 'Form 2', 'MW33279055', 'active', 'Male', 'None'),
(58, 'Mayamiko Tembo', 33, 'Form 4', 'MW33719149', 'active', 'Female', 'Hearing Impaired'),
(59, 'Yamiko Hara', 33, 'Form 4', 'MW33275717', 'active', 'Male', 'None'),
(60, 'Tiwonge Chilima', 33, 'Form 4', 'MW33148250', 'active', 'Female', 'None'),
(61, 'Sungeni Kawonga', 33, 'Form 4', 'MW33193445', 'active', 'Male', 'None'),
(62, 'Peter Phiri', 33, 'Form 4', 'MW33595253', 'active', 'Female', 'None'),
(63, 'Grace Nkhoma', 33, 'Form 2', 'MW33881142', 'active', 'Male', 'None'),
(64, 'Tiwonge Kumwenda', 33, 'Form 4', 'MW33259999', 'active', 'Female', 'None'),
(65, 'Mercy Nyirenda', 33, 'Form 4', 'MW33719168', 'active', 'Male', 'None'),
(66, 'Mercy Chilima', 34, 'Form 2', 'MW34474496', 'active', 'Female', 'None'),
(67, 'Tiwonge Banda', 34, 'Form 2', 'MW34440628', 'active', 'Male', 'None'),
(68, 'Tiwonge Hara', 34, 'Form 2', 'MW34516297', 'active', 'Female', 'Visually Impaired'),
(69, 'Chikondi Gondwe', 34, 'Form 2', 'MW34150547', 'active', 'Male', 'None'),
(70, 'Sungeni Hara', 34, 'Form 2', 'MW34564136', 'active', 'Female', 'None'),
(71, 'Mercy Banda', 34, 'Form 2', 'MW34859158', 'active', 'Male', 'None'),
(72, 'Mayamiko Lungu', 34, 'Form 2', 'MW34672491', 'active', 'Female', 'None'),
(73, 'Gift Nkhoma', 34, 'Form 2', 'MW34327852', 'active', 'Male', 'None'),
(74, 'Memory Tembo', 34, 'Form 4', 'MW34453622', 'active', 'Female', 'None'),
(75, 'Grace Kawonga', 34, 'Form 4', 'MW34965294', 'active', 'Male', 'None'),
(76, 'Grace Kumwenda', 34, 'Form 4', 'MW34874596', 'active', 'Female', 'None'),
(77, 'Tinashe Gondwe', 34, 'Form 4', 'MW34274886', 'active', 'Male', 'None'),
(78, 'Mercy Kumwenda', 34, 'Form 4', 'MW34131253', 'active', 'Female', 'None'),
(79, 'Sungeni Zulu', 34, 'Form 4', 'MW34834549', 'active', 'Male', 'None'),
(80, 'Esnart Zulu', 34, 'Form 4', 'MW34421958', 'active', 'Female', 'None'),
(81, 'Tiwonge Nkhoma', 34, 'Form 4', 'MW34273997', 'active', 'Male', 'None'),
(82, 'Memory Chilima', 35, 'Form 2', 'MW35538978', 'active', 'Female', 'None'),
(83, 'Tinashe Hara', 35, 'Form 2', 'MW35237409', 'active', 'Male', 'None'),
(84, 'Tinashe Banda', 35, 'Form 2', 'MW35278171', 'active', 'Female', 'None'),
(85, 'Chisomo Banda', 35, 'Form 2', 'MW35252584', 'active', 'Male', 'Visually Impaired'),
(86, 'Peter Chilima', 35, 'Form 2', 'MW35424392', 'active', 'Female', 'None'),
(87, 'Esnart Msiska', 35, 'Form 2', 'MW35852386', 'active', 'Male', 'Hearing Impaired'),
(88, 'Tiwonge Chirwa', 35, 'Form 2', 'MW35252700', 'active', 'Female', 'None'),
(89, 'Memory Nkhoma', 35, 'Form 2', 'MW35496672', 'active', 'Male', 'None'),
(90, 'Yamiko Gondwe', 35, 'Form 4', 'MW35196286', 'active', 'Female', 'None'),
(91, 'Esnart Msiska', 35, 'Form 4', 'MW35709806', 'active', 'Male', 'None'),
(92, 'Mercy Msiska', 35, 'Form 4', 'MW35916535', 'active', 'Female', 'None'),
(93, 'Chisomo Kawonga', 35, 'Form 4', 'MW35353806', 'active', 'Male', 'None'),
(94, 'Chisomo Nyirenda', 35, 'Form 4', 'MW35225272', 'active', 'Female', 'None'),
(95, 'Mercy Kawonga', 35, 'Form 4', 'MW35627699', 'active', 'Male', 'None'),
(96, 'Memory Hara', 35, 'Form 4', 'MW35857545', 'active', 'Female', 'None'),
(97, 'Kelvin Kumwenda', 35, 'Form 4', 'MW35141672', 'active', 'Male', 'None'),
(98, 'Kelvin Chirwa', 38, 'Form 2', 'MW38614727', 'active', 'Female', 'None'),
(99, 'Tiwonge Nyirenda', 38, 'Form 2', 'MW38623977', 'active', 'Male', 'None'),
(100, 'Sungeni Zulu', 38, 'Form 2', 'MW38792016', 'active', 'Female', 'None'),
(101, 'Peter Chirwa', 38, 'Form 2', 'MW38927647', 'active', 'Male', 'None'),
(102, 'Wongani Kawonga', 38, 'Form 2', 'MW38762156', 'active', 'Female', 'Visually Impaired'),
(103, 'Chikondi Gondwe', 38, 'Form 2', 'MW38456877', 'active', 'Male', 'None'),
(104, 'Mayamiko Chilima', 38, 'Form 2', 'MW38189921', 'active', 'Female', 'None'),
(105, 'Yamiko Chirwa', 38, 'Form 2', 'MW38253881', 'active', 'Male', 'None'),
(106, 'Mayamiko Lungu', 38, 'Form 4', 'MW38534538', 'active', 'Female', 'None'),
(107, 'Tinashe Chirwa', 38, 'Form 4', 'MW38429330', 'active', 'Male', 'None'),
(108, 'Mercy Lungu', 38, 'Form 4', 'MW38508268', 'active', 'Female', 'None'),
(109, 'Gift Msiska', 38, 'Form 4', 'MW38700515', 'active', 'Male', 'None'),
(110, 'Mercy Gondwe', 38, 'Form 4', 'MW38388346', 'active', 'Female', 'None'),
(111, 'Sungeni Hara', 38, 'Form 4', 'MW38224591', 'active', 'Male', 'None'),
(112, 'Wongani Mwale', 38, 'Form 4', 'MW38691780', 'active', 'Female', 'None'),
(113, 'Yamiko Zulu', 38, 'Form 4', 'MW38271141', 'active', 'Male', 'None'),
(114, 'Memory Chirwa', 39, 'Form 2', 'MW39389636', 'active', 'Female', 'None'),
(115, 'Esnart Phiri', 39, 'Form 2', 'MW39880596', 'active', 'Male', 'None'),
(116, 'Chikondi Chirwa', 39, 'Form 2', 'MW39761288', 'active', 'Female', 'Hearing Impaired'),
(117, 'Limbani Lungu', 39, 'Form 4', 'MW39591411', 'active', 'Male', 'None'),
(118, 'Limbani Kumwenda', 39, 'Form 4', 'MW39695183', 'active', 'Female', 'None'),
(119, 'Chikondi Nkhoma', 39, 'Form 4', 'MW39868794', 'inactive', 'Male', 'Visually Impaired'),
(120, 'Chikondi Banda', 39, 'Form 2', 'MW39589120', 'active', 'Female', 'None'),
(121, 'Limbani Hara', 39, 'Form 4', 'MW39289855', 'active', 'Male', 'None'),
(122, 'Yamiko Hara', 39, 'Form 4', 'MW39435507', 'active', 'Female', 'None'),
(123, 'Grace Hara', 39, 'Form 4', 'MW39206023', 'active', 'Male', 'None'),
(124, 'Kelvin Mwale', 39, 'Form 4', 'MW39428911', 'active', 'Female', 'None'),
(125, 'Esther Zulu', 39, 'Form 2', 'MW3958912', 'active', 'Male', 'None'),
(126, 'Leonard Ponje Mlungu', 39, 'Form 2', 'MW395891201', 'active', 'Male', 'None');

-- --------------------------------------------------------

--
-- Table structure for table `student_subjects`
--

DROP TABLE IF EXISTS `student_subjects`;
CREATE TABLE IF NOT EXISTS `student_subjects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `subject_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_subject` (`student_id`,`subject_id`),
  KEY `fk_subject` (`subject_id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `student_subjects`
--

INSERT INTO `student_subjects` (`id`, `student_id`, `subject_id`) VALUES
(1, 1, 104),
(3, 1, 105),
(8, 1, 107),
(4, 1, 109),
(2, 1, 110),
(5, 1, 112),
(7, 1, 117),
(9, 1, 118),
(6, 1, 121),
(19, 116, 104),
(21, 116, 105),
(25, 116, 107),
(22, 116, 109),
(20, 116, 110),
(24, 116, 117),
(23, 116, 121),
(10, 120, 104),
(12, 120, 105),
(18, 120, 107),
(13, 120, 108),
(14, 120, 109),
(11, 120, 110),
(16, 120, 111),
(17, 120, 117),
(15, 120, 121),
(26, 125, 104),
(27, 125, 105),
(32, 125, 106),
(31, 125, 107),
(28, 125, 108),
(29, 125, 109),
(33, 125, 118),
(30, 125, 121),
(34, 126, 104),
(35, 126, 105),
(40, 126, 107),
(36, 126, 109),
(37, 126, 112),
(39, 126, 117),
(41, 126, 118),
(38, 126, 121);

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
CREATE TABLE IF NOT EXISTS `subjects` (
  `subject_id` int NOT NULL AUTO_INCREMENT,
  `subject_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `subject_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('science','language','humanities') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `paper_type` enum('Theory','Practical') COLLATE utf8mb4_unicode_ci DEFAULT 'Theory',
  PRIMARY KEY (`subject_id`),
  UNIQUE KEY `subject_name` (`subject_name`)
) ENGINE=InnoDB AUTO_INCREMENT=122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`subject_id`, `subject_name`, `status`, `subject_code`, `category`, `created_at`, `paper_type`) VALUES
(104, 'Agriculture', 'active', 'AGR', 'science', '2026-06-18 23:03:47', 'Theory'),
(105, 'Biology', 'active', 'BIO', 'science', '2026-06-18 23:03:47', 'Theory'),
(106, 'Physics', 'active', 'PHY', 'science', '2026-06-18 23:03:47', 'Theory'),
(107, 'Mathematics', 'active', 'MAT', 'science', '2026-06-18 23:03:47', 'Theory'),
(108, 'Chemistry', 'active', 'CHE', 'science', '2026-06-18 23:03:47', 'Theory'),
(109, 'Chichewa', 'active', 'CHI', 'language', '2026-06-18 23:03:47', 'Theory'),
(110, 'Bible Knowledge', 'active', 'BK', 'humanities', '2026-06-18 23:03:47', 'Theory'),
(111, 'French', 'active', 'FRE', 'language', '2026-06-18 23:03:47', 'Theory'),
(112, 'Computer Studies', 'active', 'COM', 'science', '2026-06-18 23:03:47', 'Theory'),
(113, 'Technical Drawing', 'active', 'TD', '', '2026-06-18 23:03:47', 'Theory'),
(114, 'Creative Arts', 'active', 'CA', 'humanities', '2026-06-18 23:03:47', 'Theory'),
(115, 'Home Economics', 'active', 'HE', '', '2026-06-18 23:03:47', 'Theory'),
(116, 'Metalwork', 'active', 'MW', '', '2026-06-18 23:03:47', 'Theory'),
(117, 'Geography', 'active', 'GEO', 'humanities', '2026-06-18 23:03:47', 'Theory'),
(118, 'Social and Life Skills', 'active', 'SLS', 'humanities', '2026-06-18 23:03:47', 'Theory'),
(121, 'English', 'active', 'ENG', 'language', '2026-06-25 14:55:11', 'Theory');

-- --------------------------------------------------------

--
-- Table structure for table `subject_assignments`
--

DROP TABLE IF EXISTS `subject_assignments`;
CREATE TABLE IF NOT EXISTS `subject_assignments` (
  `assignment_id` int NOT NULL AUTO_INCREMENT,
  `subject_id` int NOT NULL,
  `teacher_id` int NOT NULL,
  `teacher_category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('item_writer','moderator') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `assigned_by` int NOT NULL,
  `assigned_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `email_sent` tinyint(1) DEFAULT '0',
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'assigned',
  PRIMARY KEY (`assignment_id`),
  UNIQUE KEY `unique_assignment` (`teacher_id`,`role`),
  KEY `assigned_by` (`assigned_by`),
  KEY `fk_assign_teacher` (`teacher_id`),
  KEY `fk_subject_assignment_subject` (`subject_id`)
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subject_assignments`
--

INSERT INTO `subject_assignments` (`assignment_id`, `subject_id`, `teacher_id`, `teacher_category`, `role`, `assigned_by`, `assigned_at`, `email_sent`, `status`) VALUES
(47, 107, 127, 'science', 'item_writer', 2, '2026-06-23 12:25:28', 1, 'assigned'),
(48, 107, 119, 'science', 'item_writer', 2, '2026-06-24 11:41:17', 1, 'assigned'),
(51, 104, 119, 'science', 'moderator', 2, '2026-07-05 20:53:36', 1, 'assigned');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` enum('Male','Female') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','teacher','headteacher','examination_officer') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `school_id` int DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `login_count` int DEFAULT '0',
  `last_login` datetime DEFAULT NULL,
  `teacher_category` enum('science','language','humanities') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qualification` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employment_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `major_subject` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `minor_subject` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `employment_number` (`employment_number`),
  KEY `fk_user_school` (`school_id`)
) ENGINE=InnoDB AUTO_INCREMENT=135 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `phone`, `gender`, `password`, `role`, `school_id`, `status`, `login_count`, `last_login`, `teacher_category`, `qualification`, `employment_number`, `major_subject`, `minor_subject`, `profile_image`) VALUES
(2, 'Leonard Ponje Mlungu', 'leonardponjemlungu@gmail.com', '0984487626', 'Male', '$2y$10$YZaaHckNul0GOfyQTvKxleOV5iZjT.MhyEJIhhtjBRGIgakaZ1RuS', 'admin', NULL, 'active', 39, '2026-07-13 08:35:06', 'humanities', NULL, NULL, '', '', NULL),
(119, 'Alice Banda', 'alice.banda@school.edu', '+265 999 123 456', 'Female', '$2y$10$8F4H5QX8PHH.C2b1dIPHDOfdTmJbDuy9Phe2aMVNimiVqsdub3Zju', 'teacher', NULL, 'active', 0, NULL, 'science', 'B.Ed', 'EMP-001', 'Mathematics', 'Science', 'user_127_1781738225.jpg'),
(120, 'James Phiri', 'james.phiri@school.edu', '+265 888 234 567', 'Male', '$2y$10$lFQ1pISbw346c.ftww5qeOaOPUTHPbzkWnLgmiho8x0ANmOA02kXa', 'examination_officer', 29, 'active', 0, NULL, 'language', 'M.Ed', 'EMP-002', 'English', 'History', 'user_127_1781738225.jpg'),
(121, 'Grace Mwale', 'ict-01-25-22@unilia.ac.mw', '+265 777 345 678', 'Female', '$2y$10$OAj/6OiYyppURJnHRqKQserPPiPJs5fYDTxPBz65c6VFRup0EhK3m', 'teacher', NULL, 'active', 0, NULL, 'science', 'Diploma in Education', 'EMP-003', 'Science', 'Art', 'user_127_1781738225.jpg'),
(122, 'Robert Chirwa', 'robert.chirwa@school.edu', '+265 999 456 789', 'Male', '$2y$10$O0qU9HfmLR/9/XCXy.lfIe7AcC7fJ2PO8kUbvk1WBKO8qdvXxlnRa', 'headteacher', 35, 'active', 0, NULL, 'science', 'M.Sc Education', 'EMP-004', 'Physics', 'Mathematics', 'user_127_1781738225.jpg'),
(123, 'Mary Tembo', 'mary.tembo@school.edu', '+265 888 567 890', 'Female', '$2y$10$9iG2c1E50NOCSacYxlV3ie86ND0GxfTBadN8wNnSuLEpAT/9gLhMK', 'teacher', 35, 'active', 0, NULL, 'science', 'B.Ed', 'EMP-005', 'Biology', 'Chemistry', 'user_127_1781738225.jpg'),
(124, 'Peter Gondwe', 'peter.gondwe@school.edu', '+265 777 678 901', 'Male', '$2y$10$zVUbqlgCcGKowbFa6Mkh6u.BHK1N.vNOugb3QZQsWAsWrdjd7kW56', 'teacher', 39, 'active', 0, NULL, 'humanities', 'Diploma in Education', 'EMP-006', 'Social Studies', 'English', 'user_127_1781738225.jpg'),
(125, 'Fatima Msiska', 'leonardmlungupro2@gmail.com', '+265 999 789 012', 'Female', '$2y$10$vxkpEFfu0Y8VReg1FUOpj.E1FCbtuNAgq6W9QhVSPo2kkWZIllNvm', 'teacher', 39, 'active', 1, '2026-06-19 03:25:43', 'humanities', 'B.Ed', 'EMP-007', 'Geography', 'History', 'user_127_1781738225.jpg'),
(126, 'David Lungu', 'leonardmlungupro@gmail.com', '+265 888 890 123', 'Male', '$2y$10$zsPb1m0REvl0VX32KgeFmO0xyGkxcTP3vj.ogwJ6uQ0WfuxIedjd6', 'teacher', 39, 'active', 3, '2026-06-24 18:09:33', 'language', 'Ph.D Education', 'EMP-008', 'Literature', 'Philosophy', 'user_127_1781738225.jpg'),
(127, 'Esther Zulu', 'mbalwelusekero@gmail.com', '+265 777 901 230', 'Male', '$2y$10$yTv5DcQkYCQO5e7vlHTALeIqryndeCXZAYnHXQU7sbOu9T1pNrrs6', 'headteacher', 39, 'active', 65, '2026-07-13 01:07:27', 'science', 'Certificate in Education', 'EMP-009', 'Agriculture', 'Biology', 'user_127_1782380105.jpeg'),
(129, 'PONJE JOHN', 'leonardmlungupro1@gmail.com', '0899520423', 'Male', '$2y$10$7ZwhOUXWQCLqA0MDm1Uecuc2Nm6qYnaj38PZYgrpG9Ku5iEtB7MEK', 'teacher', 39, 'active', 1, '2026-06-19 03:17:38', 'science', 'PhD', 'NED!O!0', 'Chemistry', 'Biology', 'user_127_1781738225.jpg'),
(130, 'Philipina Kangola', 'ict-01-09-22@unilia.ac.mw', '0986142992', 'Female', '$2y$10$LIhJhSQbzU9Mo5RHvzYjSuAIup7nXVShvlQR/HUmZpnP4ZQL5.oyq', 'teacher', 39, 'active', 4, '2026-07-13 08:13:36', 'science', 'Bachelor Degree', 'EMP-0081', 'Compoter studies', 'Mathematics', NULL),
(131, 'Magret Bandah', 'beh-01-242-25@unilia.ac.mw', '0989459969', 'Female', '$2y$10$Y7GgL08s9VeOG8VKSJOUce6bn.HOXsgaRofe8lrqwQcZFgEXZ6YmC', 'teacher', 35, 'active', 2, '2026-07-02 10:21:02', 'humanities', 'Bachelor Degree', 'EMP-242', 'Geography', 'Social studies', NULL),
(134, 'lwitiko', 'lwitikomwalungila7@gmail.com', '0997781013', 'Male', '$2y$10$iBYAtp4u.fOyQn./ysxQQ.6j.HcT1uHjrESZ3vt0Ha/fY6ZTieAxq', 'teacher', 32, 'active', 2, '2026-07-06 12:59:15', 'science', 'Bachelor Degree', 'EMP-00611', 'computer studies', 'Mathematics', NULL);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`published_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `div_finding_outcomes`
--
ALTER TABLE `div_finding_outcomes`
  ADD CONSTRAINT `fk_div_outcome_finding` FOREIGN KEY (`finding_id`) REFERENCES `div_findings` (`finding_id`) ON DELETE CASCADE;

--
-- Constraints for table `exams`
--
ALTER TABLE `exams`
  ADD CONSTRAINT `fk_exam_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `exam_subjects`
--
ALTER TABLE `exam_subjects`
  ADD CONSTRAINT `fk_exam_subjects_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`),
  ADD CONSTRAINT `fk_exam_subjects_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`);

--
-- Constraints for table `ht_finding_outcomes`
--
ALTER TABLE `ht_finding_outcomes`
  ADD CONSTRAINT `fk_outcome_finding` FOREIGN KEY (`finding_id`) REFERENCES `ht_findings` (`finding_id`) ON DELETE CASCADE;

--
-- Constraints for table `marking_assignments`
--
ALTER TABLE `marking_assignments`
  ADD CONSTRAINT `marking_assignments_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `marking_assignments_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `marking_assignments_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `marking_assignments_ibfk_4` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `fk_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_question_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_question_exam_subject` FOREIGN KEY (`exam_subject_id`) REFERENCES `exam_subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`),
  ADD CONSTRAINT `questions_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_student_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`school_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`school_id`);

--
-- Constraints for table `student_subjects`
--
ALTER TABLE `student_subjects`
  ADD CONSTRAINT `fk_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE;

--
-- Constraints for table `subject_assignments`
--
ALTER TABLE `subject_assignments`
  ADD CONSTRAINT `fk_assign_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_subject_assignment_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE;

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
