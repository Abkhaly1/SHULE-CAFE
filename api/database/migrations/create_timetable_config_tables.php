<?php
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    // 1. Timetable Boundary Configurations
    $conn->exec("CREATE TABLE IF NOT EXISTS timetable_configs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id VARCHAR(36) NOT NULL,
        academic_year_id VARCHAR(20) NOT NULL,
        level_code VARCHAR(50) NOT NULL,
        selected_grades TEXT NOT NULL,
        operational_days TEXT NOT NULL,
        periods_per_day INT NOT NULL DEFAULT 8,
        breaks_count INT NOT NULL DEFAULT 2,
        total_weekly_capacity INT NOT NULL DEFAULT 40,
        is_published TINYINT(1) NOT NULL DEFAULT 0,
        status ENUM('draft', 'active') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_school_year_level (school_id, academic_year_id, level_code)
    )");

    try {
        $conn->exec("ALTER TABLE timetable_configs ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 0");
    } catch (Exception $e) {}

    // 2. Subject Weekly Frequencies (Stage 4 Ledger)
    $conn->exec("CREATE TABLE IF NOT EXISTS timetable_subject_frequencies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        config_id INT NOT NULL,
        school_id VARCHAR(36) NOT NULL,
        academic_year_id VARCHAR(20) NOT NULL,
        grade_id INT NOT NULL,
        subject_code VARCHAR(50) NOT NULL,
        weekly_frequency INT NOT NULL DEFAULT 4,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_config_grade_subject (config_id, grade_id, subject_code),
        FOREIGN KEY (config_id) REFERENCES timetable_configs(id) ON DELETE CASCADE
    )");

    // 3. Timetable Periods Table
    $conn->exec("CREATE TABLE IF NOT EXISTS timetable_periods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id VARCHAR(36) NOT NULL,
        level_type ENUM('O-Level', 'A-Level', 'Primary', 'Nursery') NOT NULL DEFAULT 'O-Level',
        period_number INT NOT NULL,
        period_name VARCHAR(50) DEFAULT 'Period',
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        is_break BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_school_level_period (school_id, level_type, period_number)
    )");

    // 4. Class Timetables Table
    $conn->exec("CREATE TABLE IF NOT EXISTS class_timetables (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id VARCHAR(36) NOT NULL,
        academic_year_id VARCHAR(20),
        day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
        period_id INT NOT NULL,
        class_stream_id VARCHAR(50) NOT NULL,
        subject_code VARCHAR(50) NOT NULL,
        teacher_id VARCHAR(36) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_class_slot (school_id, academic_year_id, day_of_week, period_id, class_stream_id),
        UNIQUE KEY unique_teacher_slot (school_id, academic_year_id, day_of_week, period_id, teacher_id),
        FOREIGN KEY (period_id) REFERENCES timetable_periods(id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    echo json_encode([
        'success' => true,
        'message' => 'Timetable configuration & scheduling database tables initialized successfully.'
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Migration error: ' . $e->getMessage()
    ]);
}
?>
