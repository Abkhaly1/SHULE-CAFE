<?php
require_once __DIR__ . '/../../config/db.php';

try {
    // 1. Definition of Time Slots / Periods
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

    // 2. Final Timetable Ledger
    $conn->exec("CREATE TABLE IF NOT EXISTS class_timetables (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id VARCHAR(36) NOT NULL,
        academic_year_id VARCHAR(20),
        day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday') NOT NULL,
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

    // 3. Seed Default Tanzanian School Day Periods (O-Level & A-Level)
    $schools = $conn->query("SELECT id FROM schools")->fetchAll(PDO::FETCH_ASSOC);

    $defaultPeriods = [
        ['O-Level', 1, 'Period 1', '08:00:00', '08:40:00', 0],
        ['O-Level', 2, 'Period 2', '08:40:00', '09:20:00', 0],
        ['O-Level', 3, 'Period 3', '09:20:00', '10:00:00', 0],
        ['O-Level', 4, 'Tea Break', '10:00:00', '10:30:00', 1],
        ['O-Level', 5, 'Period 4', '10:30:00', '11:10:00', 0],
        ['O-Level', 6, 'Period 5', '11:10:00', '11:50:00', 0],
        ['O-Level', 7, 'Period 6', '11:50:00', '12:30:00', 0],
        ['O-Level', 8, 'Lunch Break', '12:30:00', '13:30:00', 1],
        ['O-Level', 9, 'Period 7', '13:30:00', '14:10:00', 0],
        ['O-Level', 10, 'Period 8', '14:10:00', '14:50:00', 0],
        ['O-Level', 11, 'Extra / Sports', '14:50:00', '15:30:00', 1]
    ];

    $stmtInsert = $conn->prepare("INSERT INTO timetable_periods (school_id, level_type, period_number, period_name, start_time, end_time, is_break) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE period_name=VALUES(period_name), start_time=VALUES(start_time), end_time=VALUES(end_time), is_break=VALUES(is_break)");

    foreach ($schools as $s) {
        $sid = $s['id'];
        foreach ($defaultPeriods as $dp) {
            $stmtInsert->execute([$sid, $dp[0], $dp[1], $dp[2], $dp[3], $dp[4], $dp[5]]);
        }
    }

    echo json_encode(["success" => true, "message" => "Timetable core schema (timetable_periods & class_timetables) successfully created and seeded."]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Migration error: " . $e->getMessage()]);
}
?>
