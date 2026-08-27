<?php
/**
 * SHULE CAFE Enterprise
 * Copyright (c) 2026 SHULE CAFE Enterprise. All Rights Reserved.
 * PROPRIETARY & CONFIDENTIAL. Unauthorized copying or redistribution is strictly prohibited.
 */
ob_start();
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');

$input = json_decode($rawInput, true);

if (!$input) {
    $input = $_POST;
}

// 🛡️ Rule 4: Rate-Limiting & Anti-Bot Flood Protection (Max 5 attempts per IP per hour)
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$rateLimitFile = sys_get_temp_dir() . '/shule_reg_limit_' . md5($clientIp) . '.json';
$now = time();
$attempts = [];

if (file_exists($rateLimitFile)) {
    $raw = @file_get_contents($rateLimitFile);
    $data = json_decode($raw, true);
    if (is_array($data)) {
        $attempts = array_filter($data, function($t) use ($now) { return ($now - $t) < 3600; });
    }
}

if (count($attempts) >= 5) {
    ob_clean();
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many registration attempts from your IP address. For security, please try again in 1 hour.']);
    exit;
}

$attempts[] = $now;
@file_put_contents($rateLimitFile, json_encode(array_values($attempts)));

// Anti-bot honeypot check
if (!empty($input['website_hp_check'])) {
    ob_clean();
    echo json_encode(['success' => true, 'message' => 'School registered successfully']);
    exit;
}

$schoolName = mb_strtoupper(trim($input['school_name'] ?? ''), 'UTF-8');
$schoolType = trim($input['school_type'] ?? 'Secondary');
$nectaNo    = mb_strtoupper(trim($input['necta_no'] ?? ''), 'UTF-8');
$ownership  = trim($input['ownership_type'] ?? 'Private');
$gender     = trim($input['gender_classification'] ?? 'Co-Education');
$region     = mb_strtoupper(trim($input['region'] ?? ''), 'UTF-8');
$district   = mb_strtoupper(trim($input['district'] ?? ''), 'UTF-8');
$wardAddr   = mb_strtoupper(trim($input['ward_address'] ?? ''), 'UTF-8');
$schoolMail = trim($input['school_email'] ?? '');
$schoolPh   = trim($input['school_phone'] ?? '');

$adminName  = mb_strtoupper(trim($input['admin_name'] ?? ''), 'UTF-8');
$adminPhone = trim($input['admin_phone'] ?? '');
$adminEmail = trim($input['admin_email'] ?? '');
$loginEmail = !empty($schoolMail) ? $schoolMail : $adminEmail;
$password   = $input['password'] ?? '';
$confirmPw  = $input['confirm_password'] ?? '';

if (empty($schoolName) || empty($region) || empty($schoolMail) || empty($adminName) || empty($adminPhone) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields marked with * (including Official School Email in Step 2)']);
    exit;
}

if ($password !== $confirmPw) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
    exit;
}

// 🛡️ Rule 1: Registrar Contact Phone Format Check (Min 10 digits)
$cleanAdminPhoneDigits = preg_replace('/[^0-9]/', '', $adminPhone);
if (strlen($cleanAdminPhoneDigits) < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid Registrar Contact Phone number with at least 10 digits.']);
    exit;
}

// 🛡️ Rule 3: Official School Phone vs. Registrar Personal Phone Separation
$normSchoolPh = preg_replace('/[^0-9]/', '', $schoolPh);
if (!empty($normSchoolPh) && !empty($cleanAdminPhoneDigits) && $normSchoolPh === $cleanAdminPhoneDigits) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Official School Phone cannot be the same as Registrar Contact Phone. Please provide distinct contact numbers for institutional governance.']);
    exit;
}

// 🛡️ Rule 2: NECTA / Ministry Registration Number Format Check
if (!empty($nectaNo)) {
    if (!preg_match('/^(S|P|C|E|REG)[\.\/\s-]?[0-9A-Z]{3,12}$/i', $nectaNo)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid NECTA / Ministry Reg Number format. Official registration numbers start with a prefix like S., P., C., E., or REG (e.g. S.1234 or P.5678).']);
        exit;
    }
}

try {
    $db = Database::getInstance()->getConnection();

    // Ensure database columns and tables exist prior to transaction (DDL auto-commits in MySQL)
    try { $db->exec("ALTER TABLE schools ADD COLUMN school_code VARCHAR(50) UNIQUE DEFAULT NULL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE schools ADD COLUMN necta_no VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE schools ADD COLUMN ownership_type VARCHAR(50) DEFAULT 'Private'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE schools ADD COLUMN gender_classification VARCHAR(50) DEFAULT 'Co-Education'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE schools ADD COLUMN district VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE schools ADD COLUMN ward_address VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE schools ADD COLUMN school_email VARCHAR(150) DEFAULT NULL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE schools ADD COLUMN school_phone VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}

    try { $db->exec("ALTER TABLE users ADD COLUMN email VARCHAR(150) DEFAULT NULL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD COLUMN user_code VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD COLUMN is_password_changed TINYINT DEFAULT 0"); } catch (Throwable $e) {}
    try { $db->exec("ALTER TABLE users ADD COLUMN first_login_completed TINYINT DEFAULT 0"); } catch (Throwable $e) {}
    try { $db->exec("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'school_admin', 'tenant_admin', 'regional_officer', 'teacher', 'student', 'parent', 'guardian') NOT NULL DEFAULT 'school_admin'"); } catch (Throwable $e) {}
    try { $db->exec("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'school_admin'"); } catch (Throwable $e) {}

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS school_education_levels (
                school_id VARCHAR(36) NOT NULL,
                level_code VARCHAR(20) NOT NULL,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (school_id, level_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Exception $e) {}

    // Check if Registrar Personal Email matches Official School Email
    if (!empty($adminEmail) && !empty($schoolMail) && strtolower(trim($adminEmail)) === strtolower(trim($schoolMail))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Registrar Personal Email cannot be the same as the Official School Email.']);
        exit;
    }

    // Check Official School Email uniqueness across schools
    if (!empty($schoolMail)) {
        $mailStmt = $db->prepare("
            SELECT id FROM schools WHERE LOWER(school_email) = LOWER(?) AND school_email != ''
            UNION
            SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND email != ''
            LIMIT 1
        ");
        $mailStmt->execute([$schoolMail, $schoolMail]);
        if ($mailStmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'The Official School Email "' . htmlspecialchars($schoolMail) . '" is already registered to another school and cannot be accepted.']);
            exit;
        }
    }

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
        $nectaStmt = $db->prepare("SELECT id FROM schools WHERE LOWER(necta_no) = LOWER(?) AND necta_no != '' LIMIT 1");
        $nectaStmt->execute([$nectaNo]);
        if ($nectaStmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'The NECTA / Ministry Registration Number "' . htmlspecialchars($nectaNo) . '" is already registered to another school']);
            exit;
        }
    }

    // 🛡️ Rule 1: Registrar Phone Uniqueness Check (Dual Format Normalized +255 / 07)
    $phoneAlt = $adminPhone;
    if (strpos($adminPhone, '0') === 0) {
        $phoneAlt = '+255' . substr($adminPhone, 1);
    } else if (strpos($adminPhone, '+255') === 0) {
        $phoneAlt = '0' . substr($adminPhone, 4);
    }

    $phoneStmt = $db->prepare("SELECT id FROM users WHERE phone = ? OR phone = ? LIMIT 1");
    $phoneStmt->execute([$adminPhone, $phoneAlt]);
    if ($phoneStmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'The registrar contact phone number "' . htmlspecialchars($adminPhone) . '" is already registered to another account.']);
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
    if (!function_exists('gen_uuid')) {
        function gen_uuid() {
            return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
        }
    }

    $schoolId = gen_uuid();

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

    // Insert User (Headmaster / Tenant Admin - Bypass first time setup since password & details were established during onboarding)
    $userId = gen_uuid();
    $pwHash = password_hash($password, PASSWORD_BCRYPT);

    $insertUser = $db->prepare("
        INSERT INTO users 
        (id, school_id, full_name, email, phone, user_code, password_hash, role, status, is_password_changed, first_login_completed) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'tenant_admin', 'active', 1, 1)
    ");
    $insertUser->execute([$userId, $schoolId, $adminName, $loginEmail, $adminPhone, $shuleCafeId, $pwHash]);

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

    if ($db->inTransaction()) {
        $db->commit();
    }

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'School registered successfully!',
        'shule_cafe_id' => $shuleCafeId,
        'school_id' => $schoolId,
        'school_name' => $schoolName,
        'login_email' => $loginEmail,
        'admin_name' => $adminName,
        'admin_phone' => $adminPhone,
        'login_url' => 'login.html'
    ]);

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Registration Error: ' . $e->getMessage()]);
}
