<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$rawInput = file_get_contents('php_input');
if (empty($rawInput)) {
    $rawInput = file_get_contents('php://input');
}

$input = json_decode($rawInput, true);

if (!$input) {
    $input = $_POST;
}

// Anti-bot honeypot check
if (!empty($input['website_hp_check'])) {
    echo json_encode(['success' => true, 'message' => 'School registered successfully']);
    exit;
}

$schoolName = trim($input['school_name'] ?? '');
$schoolType = trim($input['school_type'] ?? 'Secondary');
$nectaNo    = trim($input['necta_no'] ?? '');
$ownership  = trim($input['ownership_type'] ?? 'Private');
$gender     = trim($input['gender_classification'] ?? 'Co-Education');
$region     = trim($input['region'] ?? '');
$district   = trim($input['district'] ?? '');
$wardAddr   = trim($input['ward_address'] ?? '');
$schoolMail = trim($input['school_email'] ?? '');
$schoolPh   = trim($input['school_phone'] ?? '');

$adminName  = trim($input['admin_name'] ?? '');
$adminPhone = trim($input['admin_phone'] ?? '');
$adminEmail = trim($input['admin_email'] ?? '');
$password   = $input['password'] ?? '';
$confirmPw  = $input['confirm_password'] ?? '';

if (empty($schoolName) || empty($region) || empty($adminName) || empty($adminPhone) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields marked with *']);
    exit;
}

if ($password !== $confirmPw) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Check school name uniqueness
    $checkStmt = $db->prepare("SELECT id FROM schools WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $checkStmt->execute([$schoolName]);
    if ($checkStmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'The school name "' . htmlspecialchars($schoolName) . '" is already registered']);
        exit;
    }

    // Check NECTA / Ministry Registration Number uniqueness if provided
    if (!empty($nectaNo)) {
        try { $db->exec("ALTER TABLE schools ADD COLUMN necta_no VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
        $nectaStmt = $db->prepare("SELECT id FROM schools WHERE LOWER(necta_no) = LOWER(?) AND necta_no != '' LIMIT 1");
        $nectaStmt->execute([$nectaNo]);
        if ($nectaStmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'The NECTA / Ministry Registration Number "' . htmlspecialchars($nectaNo) . '" is already registered to another school']);
            exit;
        }
    }

    // Check phone uniqueness
    $phoneStmt = $db->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
    $phoneStmt->execute([$adminPhone]);
    if ($phoneStmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'The phone number ' . htmlspecialchars($adminPhone) . ' is already registered']);
        exit;
    }

    $db->beginTransaction();

    // Generate ShuleCafe School ID: S/CAFE-{YYYY}-{XXXX}
    $currentYear = date('Y');
    $prefix = "S/CAFE-{$currentYear}-%";
    
    $codeQuery = $db->prepare("
        SELECT school_code 
        FROM schools 
        WHERE school_code LIKE ? 
        ORDER BY CAST(SUBSTRING_INDEX(school_code, '-', -1) AS UNSIGNED) DESC 
        LIMIT 1
    ");
    $codeQuery->execute([$prefix]);
    $lastCodeRow = $codeQuery->fetch(PDO::FETCH_ASSOC);

    $nextNum = 1;
    if ($lastCodeRow && !empty($lastCodeRow['school_code'])) {
        $parts = explode('-', $lastCodeRow['school_code']);
        $lastNum = intval(end($parts));
        $nextNum = $lastNum + 1;
    }
    
    $shuleCafeId = sprintf("S/CAFE-%s-%04d", $currentYear, $nextNum);

    // Generate UUIDs
    function gen_uuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    $schoolId = gen_uuid();

    // Ensure columns exist on schools table dynamically
    try { $db->exec("ALTER TABLE schools ADD COLUMN school_code VARCHAR(50) UNIQUE DEFAULT NULL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE schools ADD COLUMN necta_no VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE schools ADD COLUMN ownership_type VARCHAR(50) DEFAULT 'Private'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE schools ADD COLUMN gender_classification VARCHAR(50) DEFAULT 'Co-Education'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE schools ADD COLUMN district VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE schools ADD COLUMN ward_address VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE schools ADD COLUMN school_email VARCHAR(150) DEFAULT NULL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE schools ADD COLUMN school_phone VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}

    // Insert School
    $insertSchool = $db->prepare("
        INSERT INTO schools 
        (id, school_code, name, type, necta_no, ownership_type, gender_classification, region, district, ward_address, school_email, school_phone, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    $insertSchool->execute([
        $schoolId, $shuleCafeId, $schoolName, $schoolType, $nectaNo, $ownership, $gender, 
        $region, $district, $wardAddr, $schoolMail, $schoolPh
    ]);

    // Insert User (School Admin)
    $userId = gen_uuid();
    $pwHash = password_hash($password, PASSWORD_BCRYPT);
    
    $insertUser = $db->prepare("
        INSERT INTO users 
        (id, school_id, full_name, phone, password_hash, role, status) 
        VALUES (?, ?, ?, ?, ?, 'school_admin', 'active')
    ");
    $insertUser->execute([$userId, $schoolId, $adminName, $adminPhone, $pwHash]);

    // Ensure school_education_levels table exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS school_education_levels (
            school_id VARCHAR(36) NOT NULL,
            level_code VARCHAR(20) NOT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (school_id, level_code),
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Process selected education levels
    $selectedLevels = $input['education_levels'] ?? [];
    if (!is_array($selectedLevels) || empty($selectedLevels)) {
        if ($schoolType === 'Primary') {
            $selectedLevels = ['PRIM'];
        } else {
            $selectedLevels = ['O-LEVEL', 'A-LEVEL'];
        }
    }

    // Insert selected levels into school_education_levels
    $levelStmt = $db->prepare("INSERT IGNORE INTO school_education_levels (school_id, level_code, status) VALUES (?, ?, 'active')");
    foreach ($selectedLevels as $lvl) {
        $levelStmt->execute([$schoolId, strtoupper(trim($lvl))]);
    }

    // Build dynamic class list based on selected levels
    $defaultClasses = [];
    if (in_array('PRIM', $selectedLevels)) {
        $defaultClasses = array_merge($defaultClasses, ['Standard 1', 'Standard 2', 'Standard 3', 'Standard 4', 'Standard 5', 'Standard 6', 'Standard 7']);
    }
    if (in_array('O-LEVEL', $selectedLevels)) {
        $defaultClasses = array_merge($defaultClasses, ['Form 1', 'Form 2', 'Form 3', 'Form 4']);
    }
    if (in_array('A-LEVEL', $selectedLevels)) {
        $defaultClasses = array_merge($defaultClasses, ['Form 5', 'Form 6']);
    }

    // Fallback if no classes selected
    if (empty($defaultClasses)) {
        $defaultClasses = ($schoolType === 'Primary')
            ? ['Standard 1', 'Standard 2', 'Standard 3', 'Standard 4', 'Standard 5', 'Standard 6', 'Standard 7']
            : ['Form 1', 'Form 2', 'Form 3', 'Form 4', 'Form 5', 'Form 6'];
    }

    $classStmt = $db->prepare("INSERT INTO classes (id, school_id, name) VALUES (?, ?, ?)");
    foreach ($defaultClasses as $cName) {
        $classStmt->execute([gen_uuid(), $schoolId, $cName]);
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'School registered successfully!',
        'shule_cafe_id' => $shuleCafeId,
        'school_id' => $schoolId,
        'school_name' => $schoolName,
        'admin_name' => $adminName,
        'admin_phone' => $adminPhone,
        'login_url' => 'login.html'
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}
