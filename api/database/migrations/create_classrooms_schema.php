<?php
require_once __DIR__ . '/../../config/db.php';

try {
    $conn->exec("SET FOREIGN_KEY_CHECKS=0");

    // ── ADD level_type to grades (derived from education_levels) ──────────
    try {
        $conn->exec("ALTER TABLE grades ADD COLUMN level_type VARCHAR(20) GENERATED ALWAYS AS (
            (SELECT name FROM education_levels WHERE id = level_id)
        ) STORED AFTER level_id");
    } catch(Exception $e) { /* already exists */ }

    // ── CLASSROOMS (Headmaster-created dynamic rooms) ─────────────────────
    $conn->exec("CREATE TABLE IF NOT EXISTS classrooms (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        school_id        VARCHAR(36) NOT NULL,
        academic_year    VARCHAR(20) NOT NULL,
        grade_id         INT NOT NULL,
        classroom_name   VARCHAR(100) NOT NULL,
        capacity         INT DEFAULT 45,
        is_active        BOOLEAN DEFAULT TRUE,
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_school_year_name (school_id, academic_year, classroom_name),
        FOREIGN KEY (grade_id) REFERENCES grades(id) ON DELETE CASCADE
    )");

    // ── STUDENT CLASSROOM ALLOCATION HISTORY ─────────────────────────────
    $conn->exec("CREATE TABLE IF NOT EXISTS student_classroom_allocations (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        school_id      VARCHAR(36) NOT NULL,
        academic_year  VARCHAR(20) NOT NULL,
        student_id     VARCHAR(36) NOT NULL,
        classroom_id   INT NOT NULL,
        status         ENUM('Active','Promoted','Repeated','Transferred','Withdrew') DEFAULT 'Active',
        promoted_by    VARCHAR(36) DEFAULT NULL,
        allocated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_yearly_allocation (academic_year, student_id, school_id),
        FOREIGN KEY (student_id)   REFERENCES users(id)       ON DELETE CASCADE,
        FOREIGN KEY (classroom_id) REFERENCES classrooms(id)  ON DELETE CASCADE
    )");

    // ── PROMOTION BATCH LOG ───────────────────────────────────────────────
    $conn->exec("CREATE TABLE IF NOT EXISTS promotion_batches (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        school_id        VARCHAR(36) NOT NULL,
        from_year        VARCHAR(20) NOT NULL,
        to_year          VARCHAR(20) NOT NULL,
        from_classroom_id INT NOT NULL,
        processed_by     VARCHAR(36) NOT NULL,
        total_promoted   INT DEFAULT 0,
        total_repeated   INT DEFAULT 0,
        total_transferred INT DEFAULT 0,
        processed_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $conn->exec("SET FOREIGN_KEY_CHECKS=1");

    echo json_encode([
        "success" => true,
        "message" => "Classrooms schema (classrooms, student_classroom_allocations, promotion_batches) created successfully."
    ]);

} catch (PDOException $e) {
    $conn->exec("SET FOREIGN_KEY_CHECKS=1");
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
