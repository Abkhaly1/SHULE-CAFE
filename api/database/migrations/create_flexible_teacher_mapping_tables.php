<?php
require_once __DIR__ . '/../../config/db.php';

try {
    $conn->exec("DROP TABLE IF EXISTS teacher_subject_assignments");

    // 1. Pivot Table for Subject Teachers (Many-to-Many)
    $conn->exec("CREATE TABLE teacher_subject_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id VARCHAR(36) NOT NULL,
        academic_year_id VARCHAR(20),
        class_stream_id VARCHAR(50) NOT NULL,
        subject_code VARCHAR(50) NOT NULL,
        teacher_id VARCHAR(36) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_stream_subject (school_id, academic_year_id, class_stream_id, subject_code),
        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
    )");

    // 2. Pivot Table for Class Teachers / Form Masters
    $conn->exec("CREATE TABLE IF NOT EXISTS class_teachers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id VARCHAR(36) NOT NULL,
        academic_year_id VARCHAR(20),
        class_stream_id VARCHAR(50) NOT NULL,
        teacher_id VARCHAR(36) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_class_teacher (school_id, academic_year_id, class_stream_id),
        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Seed default Class Streams & Teacher Assignments for active schools
    $schools = $conn->query("SELECT id FROM schools")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($schools as $s) {
        $sid = $s['id'];

        $stmtTeachers = $conn->prepare("SELECT id, full_name FROM users WHERE school_id = ? AND role IN ('teacher', 'tenant_admin')");
        $stmtTeachers->execute([$sid]);
        $teachers = $stmtTeachers->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($teachers)) {
            $t1 = $teachers[0]['id'];
            $t2 = !empty($teachers[1]) ? $teachers[1]['id'] : $t1;

            // Seed Form Master for Form 1A
            $conn->exec("INSERT INTO class_teachers (school_id, academic_year_id, class_stream_id, teacher_id) VALUES
                ('$sid', '" . date('Y') . "', 'Form 1A', '$t1')
                ON DUPLICATE KEY UPDATE teacher_id=VALUES(teacher_id)");

            // Seed Subject Assignments across class streams
            $streams = ['Form 1A', 'Form 1B', 'Form 2A', 'Form 2B', 'Form 3A', 'Form 4M'];
            $subjectCodes = ['B-MATH', 'PHY', 'CHE', 'BIO', 'ENG', 'KISW', 'HIST', 'GEO'];

            $stmtAssign = $conn->prepare("INSERT INTO teacher_subject_assignments (school_id, academic_year_id, class_stream_id, subject_code, teacher_id) VALUES (?, '" . date('Y') . "', ?, ?, ?)");

            foreach ($streams as $sIdx => $st) {
                foreach ($subjectCodes as $cIdx => $sc) {
                    // Assign t1 to Math/Phys in 1A, t2 to Math in 1B, unassigned for others to trigger alert badges
                    $teacher = null;
                    if ($st === 'Form 1A' && ($sc === 'B-MATH' || $sc === 'PHY')) {
                        $teacher = $t1;
                    } else if ($st === 'Form 1B' && $sc === 'B-MATH') {
                        $teacher = $t2;
                    } else if ($st === 'Form 4M' && $sc === 'CHE') {
                        $teacher = $t2;
                    }

                    $stmtAssign->execute([$sid, $st, $sc, $teacher]);
                }
            }
        }
    }

    echo json_encode(["success" => true, "message" => "Flexible Teacher Subject and Class Teacher mapping tables created and seeded."]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Migration error: " . $e->getMessage()]);
}
?>
