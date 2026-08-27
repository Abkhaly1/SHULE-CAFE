<?php
require_once __DIR__ . '/../../config/db.php';

try {
    // 1. Table for School Registered Education Levels
    $conn->exec("CREATE TABLE IF NOT EXISTS school_education_levels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id VARCHAR(36) NOT NULL,
        level_code VARCHAR(50) NOT NULL,
        level_name VARCHAR(100) NOT NULL,
        range_text VARCHAR(100) NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY school_level_unique (school_id, level_code)
    )");

    // 2. Table for School Approved Subjects
    $conn->exec("CREATE TABLE IF NOT EXISTS school_approved_subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id VARCHAR(36) NOT NULL,
        subject_code VARCHAR(50) NOT NULL,
        subject_name VARCHAR(100) NOT NULL,
        level_code VARCHAR(50) NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY school_subject_unique (school_id, subject_code, level_code)
    )");

    // 3. Table for Teacher Subject Stream Assignments
    $conn->exec("CREATE TABLE IF NOT EXISTS teacher_subject_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id VARCHAR(36) NOT NULL,
        academic_year VARCHAR(20),
        class_stream_id VARCHAR(50) NOT NULL,
        class_name VARCHAR(50) NOT NULL,
        subject_code VARCHAR(50) NOT NULL,
        subject_name VARCHAR(100) NOT NULL,
        teacher_id VARCHAR(36) NULL,
        teacher_name VARCHAR(100) NULL,
        status ENUM('assigned', 'unassigned') DEFAULT 'unassigned',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
    )");

    // Seed default data for active schools
    $schools = $conn->query("SELECT id FROM schools")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($schools as $s) {
        $sid = $s['id'];

        // Seed Education Levels for school
        $conn->exec("INSERT INTO school_education_levels (school_id, level_code, level_name, range_text, status) VALUES
            ('$sid', 'O-LEVEL', 'Ordinary Level Secondary Education', 'Form 1 - Form 4', 'active'),
            ('$sid', 'A-LEVEL', 'Advanced Level Secondary Education', 'Form 5 - Form 6', 'inactive')
            ON DUPLICATE KEY UPDATE status=VALUES(status)");

        // Seed Approved Subjects for school
        $subjects = [
            ['B-MATH', 'Basic Mathematics', 'O-LEVEL', 'active'],
            ['PHY', 'Physics', 'O-LEVEL', 'active'],
            ['CHE', 'Chemistry', 'O-LEVEL', 'active'],
            ['BIO', 'Biology', 'O-LEVEL', 'active'],
            ['ENG', 'English Language', 'O-LEVEL', 'active'],
            ['KISW', 'Kiswahili', 'O-LEVEL', 'active'],
            ['HIST', 'History', 'O-LEVEL', 'active'],
            ['GEO', 'Geography', 'O-LEVEL', 'active'],
            ['CIV', 'Civics', 'O-LEVEL', 'active'],
            ['ADD-MATH', 'Additional Mathematics', 'O-LEVEL', 'inactive']
        ];

        $stmtSbj = $conn->prepare("INSERT INTO school_approved_subjects (school_id, subject_code, subject_name, level_code, status) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status=VALUES(status)");
        foreach ($subjects as $sb) {
            $stmtSbj->execute([$sid, $sb[0], $sb[1], $sb[2], $sb[3]]);
        }

        // Seed Teacher Allocations
        $streams = ['Form 1A', 'Form 1B', 'Form 2B', 'Form 4M'];
        $allocSubjects = [
            ['HIST', 'History'],
            ['KISW', 'Kiswahili'],
            ['PHY', 'Physics'],
            ['CHE', 'Chemistry']
        ];

        // Find teachers for this school
        $stmtTeachers = $conn->prepare("SELECT id, full_name FROM users WHERE school_id = ? AND role IN ('teacher', 'tenant_admin')");
        $stmtTeachers->execute([$sid]);
        $teachers = $stmtTeachers->fetchAll(PDO::FETCH_ASSOC);

        $conn->exec("DELETE FROM teacher_subject_assignments WHERE school_id = '$sid'");
        $stmtAssign = $conn->prepare("INSERT INTO teacher_subject_assignments (school_id, academic_year, class_stream_id, class_name, subject_code, subject_name, teacher_id, teacher_name, status) VALUES (?, '" . date('Y') . "', ?, ?, ?, ?, ?, ?, ?)");

        foreach ($streams as $idx => $st) {
            $sb = $allocSubjects[$idx % count($allocSubjects)];
            // Mwl. Juma Kapuya & Mwl. Asha Rose assignments for seed demo
            $teacher = null;
            if ($st === 'Form 1A' && $sb[0] === 'KISW') {
                $teacher = !empty($teachers[0]) ? $teachers[0] : ['id' => null, 'full_name' => 'Mwl. Juma Kapuya'];
            } else if ($st === 'Form 4M' && $sb[0] === 'CHE') {
                $teacher = !empty($teachers[1]) ? $teachers[1] : (!empty($teachers[0]) ? $teachers[0] : ['id' => null, 'full_name' => 'Mwl. Asha Rose']);
            }

            $stmtAssign->execute([
                $sid,
                $st,
                $st,
                $sb[0],
                $sb[1],
                ($teacher && isset($teacher['id'])) ? $teacher['id'] : null,
                ($teacher && isset($teacher['full_name'])) ? $teacher['full_name'] : null,
                ($teacher && !empty($teacher['id'])) ? 'assigned' : 'unassigned'
            ]);
        }
    }

    echo json_encode(["success" => true, "message" => "Headmaster Academic tables created and pre-seeded successfully."]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database migration error: " . $e->getMessage()]);
}
?>
