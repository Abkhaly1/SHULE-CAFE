<?php
/**
 * SHULE CAFE - Central Database Migration & Schema Integrity Runner
 * Ensures all required tables and indexes exist across the entire platform.
 * Copyright (c) 2026 SHULE CAFE. All Rights Reserved.
 */

require_once __DIR__ . '/../../config/db.php';

echo "Executing Central Schema Migration...\n";

$tables = [
    "login_attempts" => "
        CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `ip_address` VARCHAR(45) NOT NULL,
            `identifier` VARCHAR(150) NOT NULL,
            `attempts` INT DEFAULT 1,
            `locked_until` DATETIME DEFAULT NULL,
            `last_attempt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (`ip_address`, `identifier`),
            INDEX (`locked_until`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    "security_audit_logs" => "
        CREATE TABLE IF NOT EXISTS `security_audit_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `school_id` VARCHAR(36) DEFAULT NULL,
            `user_id` VARCHAR(36) DEFAULT NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `event_type` VARCHAR(50) NOT NULL,
            `description` TEXT,
            `user_agent` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`school_id`),
            INDEX (`user_id`),
            INDEX (`event_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    "academic_templates" => "
        CREATE TABLE IF NOT EXISTS `academic_templates` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(150) NOT NULL,
            `education_level` VARCHAR(50) NOT NULL,
            `curriculum_type` VARCHAR(50) DEFAULT 'NECTA',
            `description` TEXT,
            `status` ENUM('active','draft','archived') DEFAULT 'active',
            `structure_data` JSON DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    "marks_entry_locks" => "
        CREATE TABLE IF NOT EXISTS `marks_entry_locks` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `school_id` VARCHAR(36) NOT NULL,
            `academic_year` VARCHAR(10) NOT NULL,
            `term` VARCHAR(20) NOT NULL,
            `classroom_id` INT NOT NULL,
            `subject_code` VARCHAR(50) NOT NULL,
            `is_locked` TINYINT(1) DEFAULT 0,
            `locked_by` VARCHAR(36) DEFAULT NULL,
            `locked_at` DATETIME DEFAULT NULL,
            `unlocked_by` VARCHAR(36) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_lock_target` (`school_id`, `academic_year`, `term`, `classroom_id`, `subject_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    "marks_entry_dynamic" => "
        CREATE TABLE IF NOT EXISTS `marks_entry_dynamic` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `school_id` VARCHAR(36) NOT NULL,
            `academic_year` VARCHAR(10) NOT NULL,
            `term` VARCHAR(20) NOT NULL,
            `student_id` VARCHAR(36) NOT NULL,
            `subject_code` VARCHAR(50) NOT NULL,
            `assessment_type_id` VARCHAR(50) NOT NULL,
            `score` DECIMAL(5,2) DEFAULT 0.00,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_student_assessment` (`school_id`, `academic_year`, `term`, `student_id`, `subject_code`, `assessment_type_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    "student_report_comments" => "
        CREATE TABLE IF NOT EXISTS `student_report_comments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `school_id` VARCHAR(36) NOT NULL,
            `student_id` VARCHAR(36) NOT NULL,
            `academic_year` VARCHAR(10) NOT NULL,
            `term` VARCHAR(20) NOT NULL,
            `class_teacher_comment` TEXT DEFAULT NULL,
            `headmaster_comment` TEXT DEFAULT NULL,
            `conduct` VARCHAR(50) DEFAULT 'Good',
            `closing_date` DATE DEFAULT NULL,
            `opening_date` DATE DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_student_comment` (`school_id`, `student_id`, `academic_year`, `term`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    "timetable_configs" => "
        CREATE TABLE IF NOT EXISTS `timetable_configs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `school_id` VARCHAR(36) NOT NULL,
            `academic_year_id` VARCHAR(20) NOT NULL,
            `periods_per_day` INT NOT NULL DEFAULT 8,
            `days_per_week` INT NOT NULL DEFAULT 5,
            `start_time` TIME NOT NULL DEFAULT '08:00:00',
            `period_duration_minutes` INT NOT NULL DEFAULT 40,
            `break_after_period_1` INT DEFAULT 2,
            `break_duration_1` INT DEFAULT 15,
            `break_after_period_2` INT DEFAULT 4,
            `break_duration_2` INT DEFAULT 45,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_school_year_cfg` (`school_id`, `academic_year_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    "timetable_subject_frequencies" => "
        CREATE TABLE IF NOT EXISTS `timetable_subject_frequencies` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `school_id` VARCHAR(36) NOT NULL,
            `grade_id` INT NOT NULL,
            `subject_code` VARCHAR(50) NOT NULL,
            `periods_per_week` INT NOT NULL DEFAULT 4,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_school_grade_subj_freq` (`school_id`, `grade_id`, `subject_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    "timetable_periods" => "
        CREATE TABLE IF NOT EXISTS `timetable_periods` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `school_id` VARCHAR(36) NOT NULL,
            `period_number` INT NOT NULL,
            `period_label` VARCHAR(50) NOT NULL,
            `period_type` ENUM('lesson', 'short_break', 'lunch_break', 'assembly') DEFAULT 'lesson',
            `start_time` TIME NOT NULL,
            `end_time` TIME NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_school_period_num` (`school_id`, `period_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",

    "class_timetables" => "
        CREATE TABLE IF NOT EXISTS `class_timetables` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `school_id` VARCHAR(36) NOT NULL,
            `academic_year_id` VARCHAR(20) NOT NULL,
            `day_of_week` VARCHAR(20) NOT NULL,
            `period_id` INT NOT NULL,
            `class_stream_id` VARCHAR(50) NOT NULL,
            `subject_code` VARCHAR(50) NOT NULL,
            `teacher_id` VARCHAR(36) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`school_id`, `academic_year_id`),
            INDEX (`class_stream_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    "
];

foreach ($tables as $name => $sql) {
    try {
        $conn->exec($sql);
        echo "  [OK] Table '$name' ensured.\n";
    } catch (Exception $e) {
        echo "  [FAIL] Table '$name': " . $e->getMessage() . "\n";
    }
}

echo "Central Schema Migration completed successfully.\n";
