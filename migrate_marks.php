<?php
$host = 'localhost';
$db_name = 'shule_cafe';
$username = 'root';
$password = '';
try {
    $conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name . ";charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Exception $e) { die("DB Error: " . $e->getMessage()); }

try {
    // 1. Create table if not exists
    $sql = "
    CREATE TABLE IF NOT EXISTS `marks_entry_dynamic` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `school_id` VARCHAR(36) NOT NULL,
      `academic_year` VARCHAR(10) NOT NULL,
      `term` VARCHAR(20) NOT NULL,
      `student_id` VARCHAR(36) NOT NULL,
      `subject_code` VARCHAR(50) NOT NULL,
      `assessment_type_id` INT NOT NULL,
      `score` DECIMAL(5,2) DEFAULT 0.00,
      `raw_score` DECIMAL(5,2) DEFAULT NULL,
      `entry_mode` VARCHAR(20) DEFAULT 'raw',
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY `uq_student_assessment` (`school_id`, `academic_year`, `term`, `student_id`, `subject_code`, `assessment_type_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $conn->exec($sql);

    // 2. Add raw_score column if missing
    $cols = $conn->query("SHOW COLUMNS FROM marks_entry_dynamic LIKE 'raw_score'")->fetchAll();
    if (empty($cols)) {
        $conn->exec("ALTER TABLE marks_entry_dynamic ADD COLUMN `raw_score` DECIMAL(5,2) DEFAULT NULL AFTER `score`");
    }

    // 3. Add entry_mode column if missing
    $colsMode = $conn->query("SHOW COLUMNS FROM marks_entry_dynamic LIKE 'entry_mode'")->fetchAll();
    if (empty($colsMode)) {
        $conn->exec("ALTER TABLE marks_entry_dynamic ADD COLUMN `entry_mode` VARCHAR(20) DEFAULT 'raw' AFTER `raw_score`");
    }

    echo "Migration successful: marks_entry_dynamic table updated with raw_score and entry_mode.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
