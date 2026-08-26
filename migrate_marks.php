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
    // Create the new marks_entry_dynamic table
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
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY `uq_student_assessment` (`school_id`, `academic_year`, `term`, `student_id`, `subject_code`, `assessment_type_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $conn->exec($sql);
    echo "Migration successful: Created marks_entry_dynamic table.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
