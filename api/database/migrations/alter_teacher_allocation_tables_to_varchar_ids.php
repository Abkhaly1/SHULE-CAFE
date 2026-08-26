<?php
require_once __DIR__ . '/../../config/db.php';

echo "Altering teacher allocation tables to use varchar IDs...\n";
try {
    $sqls = [
        "ALTER TABLE teacher_subject_qualifications MODIFY teacher_id VARCHAR(36) NOT NULL;",
        "ALTER TABLE teacher_subject_qualifications MODIFY subject_id VARCHAR(36) NOT NULL;",
        "ALTER TABLE teacher_classroom_assignments MODIFY teacher_id VARCHAR(36) NOT NULL;",
        "ALTER TABLE teacher_classroom_assignments MODIFY subject_id VARCHAR(36) NOT NULL;",
        // adjust unique key if exists (drop and recreate)
        "ALTER TABLE teacher_classroom_assignments DROP INDEX unique_yearly_allocation, ADD UNIQUE KEY unique_yearly_allocation (academic_year_id, teacher_id, subject_id, classroom_id);",
        "ALTER TABLE teacher_subject_qualifications DROP INDEX unique_teacher_subject, ADD UNIQUE KEY unique_teacher_subject (teacher_id, subject_id);",
    ];

    foreach ($sqls as $sql) {
        echo "Running: $sql\n";
        $conn->exec($sql);
    }

    echo "Done.\n";
} catch (PDOException $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}

?>