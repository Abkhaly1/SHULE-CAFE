<?php
require_once __DIR__ . '/../../config/db.php';

try {
    $conn->exec("ALTER TABLE teacher_subject_assignments DROP INDEX unique_stream_subject");
    $conn->exec("ALTER TABLE teacher_subject_assignments ADD UNIQUE KEY unique_stream_subject_teacher (school_id, academic_year_id, class_stream_id, subject_code, teacher_id)");
    echo json_encode(["success" => true, "message" => "Unique key updated to support multi-teacher co-teaching per subject stream."]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Migration error: " . $e->getMessage()]);
}
?>
