<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

require_once __DIR__ . '/../config/db.php';

// Ensure academic_templates table exists
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS academic_templates (
            id VARCHAR(50) PRIMARY KEY,
            type ENUM('level', 'class', 'subject', 'term') NOT NULL,
            name VARCHAR(150) NOT NULL,
            code VARCHAR(50) NOT NULL,
            level_code VARCHAR(50) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            details JSON DEFAULT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(type, level_code),
            INDEX(code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Check if table needs initial seed
    $count = $conn->query("SELECT COUNT(*) FROM academic_templates")->fetchColumn();
    // Clean any legacy nursery and primary templates
    $conn->exec("DELETE FROM academic_templates WHERE level_code IN ('NURSERY', 'PRIM', 'PRIMARY')");

    // Check if table needs initial seed or update
    $count = $conn->query("SELECT COUNT(*) FROM academic_templates WHERE level_code IN ('O-LEVEL', 'A-LEVEL')")->fetchColumn();
    if ($count == 0) {
        $seedData = [
            // O-Level Classes (Form 1 - Form 4)
            ["tpl-cls-o1", "class", "Form 1", "F1", "O-LEVEL", "Ordinary Level Secondary Year 1", '{"assigned_subjects":[{"subject_code":"BMATH","is_core":1},{"subject_code":"ENG","is_core":1},{"subject_code":"KISW","is_core":1},{"subject_code":"PHY","is_core":1},{"subject_code":"CHEM","is_core":1},{"subject_code":"BIO","is_core":1},{"subject_code":"HIST","is_core":1},{"subject_code":"GEO","is_core":1},{"subject_code":"CIV","is_core":1}]}'],
            ["tpl-cls-o2", "class", "Form 2", "F2", "O-LEVEL", "Form Two National Assessment (FTNA)", '{"assigned_subjects":[{"subject_code":"BMATH","is_core":1},{"subject_code":"ENG","is_core":1},{"subject_code":"KISW","is_core":1},{"subject_code":"PHY","is_core":1},{"subject_code":"CHEM","is_core":1},{"subject_code":"BIO","is_core":1},{"subject_code":"HIST","is_core":1},{"subject_code":"GEO","is_core":1},{"subject_code":"CIV","is_core":1}]}'],
            ["tpl-cls-o3", "class", "Form 3", "F3", "O-LEVEL", "Ordinary Level Secondary Year 3 Stream Specialization", '{"assigned_subjects":[{"subject_code":"BMATH","is_core":1},{"subject_code":"ENG","is_core":1},{"subject_code":"KISW","is_core":1},{"subject_code":"PHY","is_core":0},{"subject_code":"CHEM","is_core":0},{"subject_code":"BIO","is_core":1},{"subject_code":"HIST","is_core":0},{"subject_code":"GEO","is_core":0},{"subject_code":"CIV","is_core":1}]}'],
            ["tpl-cls-o4", "class", "Form 4", "F4", "O-LEVEL", "Certificate of Secondary Education (CSEE)", '{"assigned_subjects":[{"subject_code":"BMATH","is_core":1},{"subject_code":"ENG","is_core":1},{"subject_code":"KISW","is_core":1},{"subject_code":"PHY","is_core":0},{"subject_code":"CHEM","is_core":0},{"subject_code":"BIO","is_core":1},{"subject_code":"HIST","is_core":0},{"subject_code":"GEO","is_core":0},{"subject_code":"CIV","is_core":1}]}'],

            // A-Level Classes (Form 5 - Form 6)
            ["tpl-cls-a5", "class", "Form 5", "F5", "A-LEVEL", "High School Advanced Studies Year 1", '{"assigned_subjects":[{"subject_code":"GS","is_core":1},{"subject_code":"BAM","is_core":0},{"subject_code":"ADV-MATH","is_core":0},{"subject_code":"PHY-ADV","is_core":0},{"subject_code":"CHEM-ADV","is_core":0},{"subject_code":"BIO-ADV","is_core":0}]}'],
            ["tpl-cls-a6", "class", "Form 6", "F6", "A-LEVEL", "Advanced Certificate of Secondary Education (ACSEE)", '{"assigned_subjects":[{"subject_code":"GS","is_core":1},{"subject_code":"BAM","is_core":0},{"subject_code":"ADV-MATH","is_core":0},{"subject_code":"PHY-ADV","is_core":0},{"subject_code":"CHEM-ADV","is_core":0},{"subject_code":"BIO-ADV","is_core":0}]}'],

            // O-Level Subjects
            ["tpl-sbj-o1", "subject", "Basic Mathematics", "BMATH", "O-LEVEL", "National NECTA Secondary Basic Mathematics", null],
            ["tpl-sbj-o2", "subject", "English Language", "ENG-O", "O-LEVEL", "Secondary English Grammar and Literature", null],
            ["tpl-sbj-o3", "subject", "Kiswahili", "KISW-O", "O-LEVEL", "Fasihi na Sarufi ya Kiswahili", null],
            ["tpl-sbj-o4", "subject", "Physics", "PHY", "O-LEVEL", "Theory, laws and practical laboratory science", null],
            ["tpl-sbj-o5", "subject", "Chemistry", "CHEM", "O-LEVEL", "Inorganic, organic and experimental chemistry", null],
            ["tpl-sbj-o6", "subject", "Biology", "BIO", "O-LEVEL", "Living organisms, ecology and health science", null],
            ["tpl-sbj-o7", "subject", "History", "HIST", "O-LEVEL", "African and world history", null],
            ["tpl-sbj-o8", "subject", "Geography", "GEO", "O-LEVEL", "Physical and human geography", null],
            ["tpl-sbj-o9", "subject", "Civics", "CIV-O", "O-LEVEL", "Governance, constitution and social development", null],
            ["tpl-sbj-o10", "subject", "Bookkeeping", "BK", "O-LEVEL", "Financial principles and double-entry accounting", null],
            ["tpl-sbj-o11", "subject", "Commerce", "COMM", "O-LEVEL", "Trade, business and economics foundations", null],

            // A-Level Subjects
            ["tpl-sbj-a1", "subject", "General Studies", "GS", "A-LEVEL", "Compulsory subsidiary general studies paper", null],
            ["tpl-sbj-a2", "subject", "Basic Applied Mathematics", "BAM", "A-LEVEL", "Subsidiary mathematics for science and economics", null],
            ["tpl-sbj-a3", "subject", "Advanced Mathematics", "ADV-MATH", "A-LEVEL", "Pure mathematics, mechanics and calculus", null],
            ["tpl-sbj-a4", "subject", "Physics (Advanced)", "PHY-ADV", "A-LEVEL", "Advanced mechanics, waves, electromagnetism and modern physics", null],
            ["tpl-sbj-a5", "subject", "Chemistry (Advanced)", "CHEM-ADV", "A-LEVEL", "Advanced physical, inorganic and organic chemistry", null],
            ["tpl-sbj-a6", "subject", "Biology (Advanced)", "BIO-ADV", "A-LEVEL", "Cytology, genetics, physiology and ecology", null],
            ["tpl-sbj-a7", "subject", "Economics (Advanced)", "ECON-ADV", "A-LEVEL", "Microeconomics, macroeconomics and development", null],

            // Standard Terms
            ["tpl-trm-1", "term", "Term 1 (January - June)", "TERM-1", "ALL", "First academic semester", null],
            ["tpl-trm-2", "term", "Term 2 (July - December)", "TERM-2", "ALL", "Second academic semester and annual finals", null]
        ];

        $ins = $conn->prepare("
            INSERT INTO academic_templates (id, type, name, code, level_code, description, details, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
            ON DUPLICATE KEY UPDATE name=VALUES(name), code=VALUES(code), level_code=VALUES(level_code), description=VALUES(description), details=VALUES(details)
        ");

        foreach ($seedData as $s) {
            $ins->execute([$s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6]]);
        }
    }
} catch (Exception $e) {}

$type = $_GET['type'] ?? null;
$levelCode = $_GET['level_code'] ?? null;

try {
    $where = [];
    $params = [];

    if ($type) {
        $where[] = "type = ?";
        $params[] = $type;
    }

    if ($levelCode) {
        $where[] = "(level_code = ? OR level_code = 'ALL')";
        $params[] = $levelCode;
    }

    $sql = "SELECT * FROM academic_templates";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY type ASC, name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "data" => $templates]);

} catch (PDOException $e) {
    http_response_code(200);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
