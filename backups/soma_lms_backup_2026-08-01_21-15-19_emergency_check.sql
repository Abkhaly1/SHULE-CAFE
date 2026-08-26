-- SOMA LMS Database Backup
-- Generated: 2026-08-01 21:15:19
-- Reason: emergency_check

SET FOREIGN_KEY_CHECKS=0;

-- Table structure for `academic_templates` --
DROP TABLE IF EXISTS `academic_templates`;
CREATE TABLE `academic_templates` (
  `id` varchar(36) NOT NULL,
  `type` enum('level','class','subject','grading','term') NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `level_code` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `academic_templates` --
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-cls-1', 'class', 'Form 1', 'F1', 'O-LEVEL', 'First year of O-Level Secondary Education', '{\"level\":\"O-Level\"}', 'active', '2026-08-01 21:26:28');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-cls-2', 'class', 'Form 2', 'F2', 'O-LEVEL', 'Second year of O-Level Secondary Education', '{\"level\":\"O-Level\"}', 'active', '2026-08-01 21:26:28');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-cls-3', 'class', 'Form 3', 'F3', 'O-LEVEL', 'Third year of O-Level Secondary Education', '{\"level\":\"O-Level\"}', 'active', '2026-08-01 21:26:28');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-cls-4', 'class', 'Form 4', 'F4', 'O-LEVEL', 'Final year of O-Level Secondary Education', '{\"level\":\"O-Level\"}', 'active', '2026-08-01 21:26:28');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-cls-5', 'class', 'Form 5', 'F5', 'A-LEVEL', 'First year of A-Level High School', NULL, 'active', '2026-08-01 21:31:14');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-cls-6', 'class', 'Form 6', 'F6', 'A-LEVEL', 'Final year of A-Level High School', NULL, 'active', '2026-08-01 21:31:14');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-cls-p0', 'class', 'Nursery / Pre-Primary', 'PRE-PRIM', 'PRIM', 'Early Childhood & Nursery', NULL, 'active', '2026-08-01 21:33:52');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-cls-p1', 'class', 'Standard 1', 'STD1', 'PRIM', 'Primary Education Standard 1', NULL, 'active', '2026-08-01 21:31:14');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-cls-p2', 'class', 'Standard 2', 'STD2', 'PRIM', 'Primary Education Standard 2', NULL, 'active', '2026-08-01 21:33:52');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-cls-p3', 'class', 'Standard 3', 'STD3', 'PRIM', 'Primary Education Standard 3', NULL, 'active', '2026-08-01 21:33:52');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-cls-p4', 'class', 'Standard 4', 'STD4', 'PRIM', 'Primary Education Standard 4', NULL, 'active', '2026-08-01 21:33:52');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-cls-p5', 'class', 'Standard 5', 'STD5', 'PRIM', 'Primary Education Standard 5', NULL, 'active', '2026-08-01 21:33:52');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-cls-p6', 'class', 'Standard 6', 'STD6', 'PRIM', 'Primary Education Standard 6', NULL, 'active', '2026-08-01 21:33:52');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-cls-p7', 'class', 'Standard 7', 'STD7', 'PRIM', 'Primary Education Standard 7 Final Year', NULL, 'active', '2026-08-01 21:31:14');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-grd-1', 'grading', 'NECTA O-Level Grading', 'NECTA-O', NULL, 'Standard NECTA Grading Criteria (A: 75-100, B: 65-74, C: 45-64, D: 30-44, F: 0-29)', '{\"A\":\"75-100\",\"B\":\"65-74\",\"C\":\"45-64\",\"D\":\"30-44\",\"F\":\"0-29\"}', 'active', '2026-08-01 21:26:29');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-grd-2', 'grading', 'NECTA Primary Grading', 'NECTA-P', NULL, 'Primary School Grading Criteria (A: 81-100, B: 61-80, C: 41-60, D: 21-40, E: 0-20)', '{\"A\":\"81-100\",\"B\":\"61-80\",\"C\":\"41-60\",\"D\":\"21-40\",\"E\":\"0-20\"}', 'active', '2026-08-01 21:26:29');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-lvl-1', 'level', 'Primary Education', 'PRIM', NULL, 'Standard Primary Education (Std 1 - Std 7)', '{\"grades\":\"Std 1 - 7\",\"exam_body\":\"NECTA\"}', 'active', '2026-08-01 21:26:28');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-lvl-2', 'level', 'Ordinary Level (O-Level)', 'O-LEVEL', NULL, 'Secondary Education (Form 1 - Form 4)', '{\"grades\":\"Form 1 - 4\",\"exam_body\":\"NECTA CSEE\"}', 'active', '2026-08-01 21:26:28');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-lvl-3', 'level', 'Advanced Level (A-Level)', 'A-LEVEL', NULL, 'High School Education (Form 5 - Form 6)', '{\"grades\":\"Form 5 - 6\",\"exam_body\":\"NECTA ACSEE\"}', 'active', '2026-08-01 21:26:28');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-sbj-1', 'subject', 'Mathematics', 'MATH', 'O-LEVEL', 'Basic & Advanced Mathematics', '{\"category\":\"Core Science\"}', 'active', '2026-08-01 21:26:29');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-sbj-2', 'subject', 'English Language', 'ENG', 'O-LEVEL', 'English Language & Grammar', '{\"category\":\"Core Language\"}', 'active', '2026-08-01 21:26:29');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-sbj-3', 'subject', 'Kiswahili', 'KISW', 'O-LEVEL', 'Lugha na Fasihi ya Kiswahili', '{\"category\":\"Core Language\"}', 'active', '2026-08-01 21:26:29');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-sbj-4', 'subject', 'Biology', 'BIO', 'O-LEVEL', 'Biological Sciences', '{\"category\":\"Science\"}', 'active', '2026-08-01 21:26:29');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-sbj-5', 'subject', 'Chemistry', 'CHEM', 'O-LEVEL', 'Chemical Sciences', '{\"category\":\"Science\"}', 'active', '2026-08-01 21:26:29');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-sbj-6', 'subject', 'Physics', 'PHYS', 'O-LEVEL', 'Physical Sciences', '{\"category\":\"Science\"}', 'active', '2026-08-01 21:26:29');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-sbj-a1', 'subject', 'Advanced Mathematics', 'ADV-MATH', 'A-LEVEL', 'Advanced Mathematics for PCM/PGM/EGM', NULL, 'active', '2026-08-01 21:31:16');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-sbj-a2', 'subject', 'Basic Applied Mathematics (BAM)', 'BAM', 'A-LEVEL', 'BAM for Science/Arts Combinations', NULL, 'active', '2026-08-01 21:31:16');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-sbj-p1', 'subject', 'Sayansi na Teknolojia', 'SAYANSI', 'PRIM', 'Sayansi ya Shule ya Msingi', NULL, 'active', '2026-08-01 21:31:15');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-sbj-p2', 'subject', 'Maarifa ya Jamii', 'JAMII', 'PRIM', 'Maarifa ya Jamii Shule ya Msingi', NULL, 'active', '2026-08-01 21:31:16');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-trm-1', 'term', 'Term 1 (First Semester)', 'T1', NULL, 'January - June Academic Period', '{\"months\":\"Jan - Jun\"}', 'active', '2026-08-01 21:26:29');
INSERT INTO `academic_templates` (`id`, `type`, `name`, `code`, `level_code`, `description`, `details`, `status`, `created_at`) VALUES ('tpl-trm-2', 'term', 'Term 2 (Second Semester)', 'T2', NULL, 'July - December Academic Period', '{\"months\":\"Jul - Dec\"}', 'active', '2026-08-01 21:26:29');

-- Table structure for `classes` --
DROP TABLE IF EXISTS `classes`;
CREATE TABLE `classes` (
  `id` varchar(36) NOT NULL,
  `school_id` varchar(36) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `school_id` (`school_id`),
  CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `classes` --
INSERT INTO `classes` (`id`, `school_id`, `name`, `created_at`) VALUES ('5fa6c2ad-19fa-4367-8ad5-8bbadea47a78', '45589df8-9975-4fa2-897b-65f9d78e4c54', 'Form 1A', '2026-08-01 21:08:07');

-- Table structure for `parent_student` --
DROP TABLE IF EXISTS `parent_student`;
CREATE TABLE `parent_student` (
  `parent_id` varchar(36) NOT NULL,
  `student_id` varchar(36) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`parent_id`,`student_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `parent_student_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parent_student_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `parent_student` --
INSERT INTO `parent_student` (`parent_id`, `student_id`, `created_at`) VALUES ('87f4e46e-62a9-4fe6-b30b-ba93c2b50d5d', 'c7fda74c-ebbc-4800-bc9c-4da14011c83b', '2026-08-01 21:08:20');

-- Table structure for `schools` --
DROP TABLE IF EXISTS `schools`;
CREATE TABLE `schools` (
  `id` varchar(36) NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` enum('Primary','Secondary','High School','College') DEFAULT 'Secondary',
  `region` varchar(100) DEFAULT NULL,
  `status` enum('active','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `schools` --
INSERT INTO `schools` (`id`, `name`, `type`, `region`, `status`, `created_at`, `updated_at`) VALUES ('45589df8-9975-4fa2-897b-65f9d78e4c54', 'Mlimani Secondary Test', 'Secondary', 'Dar es Salaam', 'active', '2026-08-01 21:08:07', '2026-08-01 21:08:07');

-- Table structure for `subjects` --
DROP TABLE IF EXISTS `subjects`;
CREATE TABLE `subjects` (
  `id` varchar(36) NOT NULL,
  `school_id` varchar(36) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `school_id` (`school_id`),
  CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for `users` --
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` varchar(36) NOT NULL,
  `school_id` varchar(36) DEFAULT NULL,
  `class_id` varchar(36) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `temp_password` varchar(255) DEFAULT NULL,
  `is_password_changed` tinyint(1) DEFAULT 0,
  `role` enum('super_admin','tenant_admin','regional_officer','teacher','student','parent','guardian') NOT NULL,
  `status` enum('active','suspended','locked') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`),
  UNIQUE KEY `idx_user_email` (`email`),
  KEY `fk_user_school` (`school_id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `fk_user_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `users` --
INSERT INTO `users` (`id`, `school_id`, `class_id`, `full_name`, `phone`, `email`, `password_hash`, `temp_password`, `is_password_changed`, `role`, `status`, `created_at`, `updated_at`) VALUES ('7717a810-bc2f-42b5-ae28-8fafb7cf0494', '45589df8-9975-4fa2-897b-65f9d78e4c54', NULL, 'Headmaster Mlimani', '+255711222333', 'headmaster.mlimani@somalms.ac.tz', '$2y$10$KPkG7OYPMOui3.HsWozG6OjnO89siEtqjZhr4bpozaT2Ti.Z102ta', NULL, '1', 'tenant_admin', 'active', '2026-08-01 21:08:07', '2026-08-01 22:12:33');
INSERT INTO `users` (`id`, `school_id`, `class_id`, `full_name`, `phone`, `email`, `password_hash`, `temp_password`, `is_password_changed`, `role`, `status`, `created_at`, `updated_at`) VALUES ('780e7ef6-2d61-4572-9ac9-b82a48b53f69', '45589df8-9975-4fa2-897b-65f9d78e4c54', NULL, 'Mwalimu Juma', '+255755111222', 'teacher.juma@somalms.ac.tz', '$2y$10$1/9qG6pXqVyAWjXwZXBb/.8WPxLWzrk4mm/jRg5e3n5eZ3GKWzpRC', NULL, '0', 'teacher', 'active', '2026-08-01 21:08:07', '2026-08-01 22:01:02');
INSERT INTO `users` (`id`, `school_id`, `class_id`, `full_name`, `phone`, `email`, `password_hash`, `temp_password`, `is_password_changed`, `role`, `status`, `created_at`, `updated_at`) VALUES ('87f4e46e-62a9-4fe6-b30b-ba93c2b50d5d', '45589df8-9975-4fa2-897b-65f9d78e4c54', NULL, 'Mzazi Juma', '+255799333444', 'parent.juma@somalms.ac.tz', '$2y$10$8qw/sQXOFy8Q38Rq/xl9EOn8yaEDYFNrUbrTctbeWCAO37adqV8RW', NULL, '0', 'parent', 'active', '2026-08-01 21:08:20', '2026-08-01 22:01:03');
INSERT INTO `users` (`id`, `school_id`, `class_id`, `full_name`, `phone`, `email`, `password_hash`, `temp_password`, `is_password_changed`, `role`, `status`, `created_at`, `updated_at`) VALUES ('c1221001-2841-4cef-a433-70733403483b', NULL, NULL, 'System Administrator', '+255700000000', 'admin@somalms.ac.tz', '$2y$10$4NyrIc/DNlqWt9a79G4uquoi0e2RaDZ5GOk5.8jjFjBUD4LCH1GQm', NULL, '0', 'super_admin', 'active', '2026-08-01 19:37:26', '2026-08-01 22:01:02');
INSERT INTO `users` (`id`, `school_id`, `class_id`, `full_name`, `phone`, `email`, `password_hash`, `temp_password`, `is_password_changed`, `role`, `status`, `created_at`, `updated_at`) VALUES ('c7fda74c-ebbc-4800-bc9c-4da14011c83b', '45589df8-9975-4fa2-897b-65f9d78e4c54', NULL, 'Amani Juma', '+255788111222', 'student.amani@somalms.ac.tz', '$2y$10$MRkrpsnbQY/2beKhLGEWaOztdX74IXRZ/AgKZAMkrhLLQ6/VBfDha', NULL, '0', 'student', 'active', '2026-08-01 21:08:08', '2026-08-01 22:01:03');

SET FOREIGN_KEY_CHECKS=1;
