<?php
require_once __DIR__ . '/../../config/db.php';

try {
    $conn->exec("SET FOREIGN_KEY_CHECKS=0");

    // ============================================================
    // 1. EDUCATION LEVELS  (O-Level / A-Level — Super Admin owns)
    // ============================================================
    $conn->exec("CREATE TABLE IF NOT EXISTS education_levels (
        id   INT AUTO_INCREMENT PRIMARY KEY,
        name ENUM('O-Level','A-Level','Primary','Nursery') NOT NULL,
        code VARCHAR(20) NOT NULL,
        UNIQUE KEY unique_level_name (name)
    )");

    // ============================================================
    // 2. GRADES  (Form 1 … Form 6, Standard 1 … Standard 7, etc.)
    // ============================================================
    $conn->exec("CREATE TABLE IF NOT EXISTS grades (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        level_id   INT NOT NULL,
        name       VARCHAR(30) NOT NULL,   -- e.g. 'Form 1', 'Form 5'
        order_seq  INT DEFAULT 0,
        UNIQUE KEY unique_grade (level_id, name),
        FOREIGN KEY (level_id) REFERENCES education_levels(id) ON DELETE CASCADE
    )");

    // ============================================================
    // 3. CLASS STREAMS  (the actual rooms — school-scoped)
    // ============================================================
    $conn->exec("CREATE TABLE IF NOT EXISTS class_streams (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        school_id  VARCHAR(36) NOT NULL,
        grade_id   INT NOT NULL,
        name       VARCHAR(60) NOT NULL,   -- e.g. 'Form 1A', 'Form 4 Science', 'Form 5 PCM'
        stream_type ENUM('standard','science','arts','commerce','combination') DEFAULT 'standard',
        capacity   INT DEFAULT 40,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_school_stream (school_id, name),
        FOREIGN KEY (grade_id) REFERENCES grades(id) ON DELETE CASCADE
    )");

    // ============================================================
    // 4. MASTER SUBJECTS TABLE  (national / global, not school-scoped)
    // ============================================================
    $conn->exec("ALTER TABLE subjects 
        ADD COLUMN IF NOT EXISTS level_type ENUM('O-Level','A-Level','Primary','Nursery','All') DEFAULT 'O-Level' AFTER code,
        ADD COLUMN IF NOT EXISTS is_core BOOLEAN DEFAULT TRUE AFTER level_type,
        ADD COLUMN IF NOT EXISTS is_elective BOOLEAN DEFAULT FALSE AFTER is_core
    ");

    // ============================================================
    // 5. STREAM-SUBJECT MAPPING LEDGER  (per school, per year)
    //    Maps WHICH subjects belong to WHICH specific stream room.
    // ============================================================
    $conn->exec("CREATE TABLE IF NOT EXISTS stream_subjects (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        school_id        VARCHAR(36) NOT NULL,
        academic_year_id VARCHAR(20),
        class_stream_id  INT NOT NULL,
        subject_code     VARCHAR(50) NOT NULL,
        subject_name     VARCHAR(150) NOT NULL,
        is_core          BOOLEAN DEFAULT TRUE,
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_stream_subject (school_id, academic_year_id, class_stream_id, subject_code),
        FOREIGN KEY (class_stream_id) REFERENCES class_streams(id) ON DELETE CASCADE
    )");

    // ============================================================
    // 6. STUDENT-SUBJECT ENROLLMENT  (individual elective control)
    // ============================================================
    $conn->exec("CREATE TABLE IF NOT EXISTS student_subject_enrollments (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        school_id        VARCHAR(36) NOT NULL,
        academic_year_id VARCHAR(20),
        student_id       VARCHAR(36) NOT NULL,
        class_stream_id  INT NOT NULL,
        subject_code     VARCHAR(50) NOT NULL,
        subject_name     VARCHAR(150) NOT NULL,
        enrolled_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_student_subject_year (school_id, academic_year_id, student_id, subject_code),
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (class_stream_id) REFERENCES class_streams(id) ON DELETE CASCADE
    )");

    $conn->exec("SET FOREIGN_KEY_CHECKS=1");

    // ============================================================
    // SEED EDUCATION LEVELS
    // ============================================================
    $conn->exec("INSERT IGNORE INTO education_levels (name, code) VALUES
        ('O-Level', 'O-LEVEL'),
        ('A-Level', 'A-LEVEL'),
        ('Primary', 'PRIMARY'),
        ('Nursery', 'NURSERY')
    ");

    // ============================================================
    // SEED GRADES
    // ============================================================
    $oId = $conn->query("SELECT id FROM education_levels WHERE name='O-Level'")->fetchColumn();
    $aId = $conn->query("SELECT id FROM education_levels WHERE name='A-Level'")->fetchColumn();
    $pId = $conn->query("SELECT id FROM education_levels WHERE name='Primary'")->fetchColumn();

    $stmt = $conn->prepare("INSERT IGNORE INTO grades (level_id, name, order_seq) VALUES (?,?,?)");
    // O-Level
    foreach (['Form 1'=>1,'Form 2'=>2,'Form 3'=>3,'Form 4'=>4] as $n=>$o) $stmt->execute([$oId,$n,$o]);
    // A-Level
    foreach (['Form 5'=>1,'Form 6'=>2] as $n=>$o) $stmt->execute([$aId,$n,$o]);
    // Primary
    foreach (['Standard 1'=>1,'Standard 2'=>2,'Standard 3'=>3,'Standard 4'=>4,'Standard 5'=>5,'Standard 6'=>6,'Standard 7'=>7] as $n=>$o) $stmt->execute([$pId,$n,$o]);

    // ============================================================
    // SEED CLASS STREAMS  (per school)
    // ============================================================
    $schools = $conn->query("SELECT id FROM schools")->fetchAll(PDO::FETCH_ASSOC);

    $streamDefs = [
        // [grade_name, stream_name, stream_type]
        // Form 1 & 2 — standard parallel sections
        ['Form 1','Form 1A','standard'], ['Form 1','Form 1B','standard'],
        ['Form 2','Form 2A','standard'], ['Form 2','Form 2B','standard'],
        // Form 3 & 4 — elective paths
        ['Form 3','Form 3 Science','science'], ['Form 3','Form 3 Arts','arts'],
        ['Form 4','Form 4 Science','science'], ['Form 4','Form 4 Commerce','commerce'],
        // A-Level combinations
        ['Form 5','Form 5 PCM','combination'], ['Form 5','Form 5 PCB','combination'], ['Form 5','Form 5 HGL','combination'],
        ['Form 6','Form 6 PCM','combination'], ['Form 6','Form 6 PCB','combination'], ['Form 6','Form 6 HGL','combination'],
    ];

    $stmtStream = $conn->prepare("INSERT IGNORE INTO class_streams (school_id, grade_id, name, stream_type) VALUES (?,?,?,?)");

    foreach ($schools as $s) {
        $sid = $s['id'];
        foreach ($streamDefs as $def) {
            [$gradeName, $streamName, $sType] = $def;
            $gradeId = $conn->query("SELECT id FROM grades WHERE name='$gradeName'")->fetchColumn();
            if ($gradeId) {
                $stmtStream->execute([$sid, $gradeId, $streamName, $sType]);
            }
        }
    }

    // ============================================================
    // SEED MASTER SUBJECTS  (national curriculum)
    // ============================================================
    $subjectDefs = [
        // O-Level Core
        ['O-Level','B-MATH','Basic Mathematics',     true, false],
        ['O-Level','PHY',   'Physics',               false, true],
        ['O-Level','CHE',   'Chemistry',             false, true],
        ['O-Level','BIO',   'Biology',               false, true],
        ['O-Level','ENG',   'English Language',      true, false],
        ['O-Level','KISW',  'Kiswahili',             true, false],
        ['O-Level','HIST',  'History',               false, true],
        ['O-Level','GEO',   'Geography',             false, true],
        ['O-Level','CIV',   'Civics',                true, false],
        ['O-Level','ADD-MATH','Additional Mathematics', false, true],
        ['O-Level','COMM',  'Commerce',              false, true],
        ['O-Level','BOOK',  'Book Keeping',          false, true],
        ['O-Level','AGRI',  'Agriculture',           false, true],
        // A-Level Principal Subjects
        ['A-Level','ADV-MATH','Advanced Mathematics', true, false],
        ['A-Level','ADV-PHY', 'Advanced Physics',     true, false],
        ['A-Level','ADV-CHE', 'Advanced Chemistry',   true, false],
        ['A-Level','ADV-BIO', 'Advanced Biology',     true, false],
        ['A-Level','ADV-HIST','Advanced History',     true, false],
        ['A-Level','ADV-GEO', 'Advanced Geography',   true, false],
        ['A-Level','ADV-ENG', 'Advanced English Language', true, false],
        ['A-Level','GS',      'General Studies',      true, false],
    ];

    // Add unique constraint on school_id+code if missing
    try { $conn->exec("ALTER TABLE subjects ADD UNIQUE KEY unique_school_subject_code (school_id, code)"); } catch(Exception $e) {}

    $stmtSub = $conn->prepare("INSERT INTO subjects (id, school_id, name, code, level_type, is_core, is_elective) VALUES (UUID(),?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE level_type=VALUES(level_type), is_core=VALUES(is_core), is_elective=VALUES(is_elective)");

    foreach ($schools as $s) {
        $sid = $s['id'];
        foreach ($subjectDefs as $sd) {
            $stmtSub->execute([$sid, $sd[2], $sd[1], $sd[0], (int)$sd[3], (int)$sd[4]]);
        }

    }

    // ============================================================
    // SEED STREAM-SUBJECT MAPPINGS  (elective path logic)
    // ============================================================
    $streamSubjectDefs = [
        // Form 1 & 2 — core only (all streams same subjects)
        'Form 1A'       => ['B-MATH','ENG','KISW','BIO','PHY','CHE','GEO','HIST','CIV'],
        'Form 1B'       => ['B-MATH','ENG','KISW','BIO','PHY','CHE','GEO','HIST','CIV'],
        'Form 2A'       => ['B-MATH','ENG','KISW','BIO','PHY','CHE','GEO','HIST','CIV'],
        'Form 2B'       => ['B-MATH','ENG','KISW','BIO','PHY','CHE','GEO','HIST','CIV'],
        // Form 3 paths
        'Form 3 Science' => ['B-MATH','ADD-MATH','ENG','KISW','PHY','CHE','BIO','GEO','CIV'],
        'Form 3 Arts'    => ['B-MATH','ENG','KISW','HIST','GEO','CIV','AGRI'],
        // Form 4 paths
        'Form 4 Science'  => ['B-MATH','ADD-MATH','ENG','KISW','PHY','CHE','BIO','GEO','CIV'],
        'Form 4 Commerce' => ['B-MATH','ENG','KISW','HIST','GEO','CIV','COMM','BOOK','AGRI'],
        // A-Level combinations
        'Form 5 PCM' => ['ADV-MATH','ADV-PHY','ADV-CHE','GS'],
        'Form 5 PCB' => ['ADV-PHY','ADV-CHE','ADV-BIO','GS'],
        'Form 5 HGL' => ['ADV-HIST','ADV-GEO','ADV-ENG','GS'],
        'Form 6 PCM' => ['ADV-MATH','ADV-PHY','ADV-CHE','GS'],
        'Form 6 PCB' => ['ADV-PHY','ADV-CHE','ADV-BIO','GS'],
        'Form 6 HGL' => ['ADV-HIST','ADV-GEO','ADV-ENG','GS'],
    ];

    // Core subject codes
    $coreSubjects = ['B-MATH','ENG','KISW','CIV','GS','ADV-MATH','ADV-PHY','ADV-CHE','ADV-BIO','ADV-HIST','ADV-GEO','ADV-ENG'];

    $stmtSS = $conn->prepare("INSERT IGNORE INTO stream_subjects
        (school_id, academic_year_id, class_stream_id, subject_code, subject_name, is_core)
        VALUES (?,?,?,?,?,?)");

    foreach ($schools as $s) {
        $sid = $s['id'];
        foreach ($streamSubjectDefs as $streamName => $codes) {
            $streamId = $conn->query("SELECT id FROM class_streams WHERE school_id='$sid' AND name='$streamName'")->fetchColumn();
            if (!$streamId) continue;
            foreach ($codes as $code) {
                $subjectRow = $conn->query("SELECT name FROM subjects WHERE school_id='$sid' AND code='$code'")->fetch(PDO::FETCH_ASSOC);
                if (!$subjectRow) continue;
                $isCore = in_array($code, $coreSubjects) ? 1 : 0;
                $stmtSS->execute([$sid, date('Y'), $streamId, $code, $subjectRow['name'], $isCore]);
            }
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "Class subdivision schema (education_levels, grades, class_streams, stream_subjects, student_subject_enrollments) created and seeded."
    ]);

} catch (PDOException $e) {
    $conn->exec("SET FOREIGN_KEY_CHECKS=1");
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
