<?php
/* Migration: create_teacher_allocation_tables.php
   Adds teacher_subject_qualifications and teacher_classroom_assignments tables
*/
require_once __DIR__ . '/../../config/db.php';

try {
    // Table: teacher_subject_qualifications
    $conn->exec("CREATE TABLE IF NOT EXISTS teacher_subject_qualifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        subject_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_teacher_subject (teacher_id, subject_id),
        KEY idx_teacher (teacher_id),
        KEY idx_subject (subject_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Table: teacher_classroom_assignments
    $conn->exec("CREATE TABLE IF NOT EXISTS teacher_classroom_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        academic_year_id VARCHAR(16) NOT NULL,
        teacher_id INT NOT NULL,
        subject_id INT NOT NULL,
        classroom_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_yearly_allocation (academic_year_id, teacher_id, subject_id, classroom_id),
        KEY idx_teacher (teacher_id),
        KEY idx_subject (subject_id),
        KEY idx_classroom (classroom_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "Migration executed: teacher allocation tables created.\n";
} catch (PDOException $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}

?>
