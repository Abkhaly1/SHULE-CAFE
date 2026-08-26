<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

// Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->school_name) || empty($data->headmaster_name) || empty($data->headmaster_phone)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "School Name, Headmaster Name, and Phone are required."]);
    exit();
}

$email = !empty($data->headmaster_email) ? trim($data->headmaster_email) : null;
$selectedLevels = isset($data->education_levels) && is_array($data->education_levels) ? $data->education_levels : ['O-LEVEL'];

// Auto-generate standard high-entropy temporary password
function generateStandardTempPassword() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789#@!';
    $pwd = 'Shule#' . date('Y') . '@';
    for ($i = 0; $i < 4; $i++) {
        $pwd .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $pwd;
}

$tempPassword = generateStandardTempPassword();

try {
    $conn->beginTransaction();

    // 1. Create School
    $school_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    
    $stmt = $conn->prepare("INSERT INTO schools (id, name, type, region) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $school_id, 
        trim($data->school_name), 
        $data->school_type ?? 'Secondary', 
        trim($data->region ?? '')
    ]);

    // 2. Create Headmaster (tenant_admin)
    $check = $conn->prepare("SELECT id FROM users WHERE phone = ? OR (email IS NOT NULL AND email = ?)");
    $check->execute([trim($data->headmaster_phone), $email]);
    if ($check->fetch()) {
        $conn->rollBack();
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Phone number or Email already exists for another user."]);
        exit();
    }

    $user_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    $hash = password_hash($tempPassword, PASSWORD_BCRYPT);
    
    $stmt2 = $conn->prepare("INSERT INTO users (id, school_id, full_name, email, phone, password_hash, temp_password, is_password_changed, role) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'tenant_admin')");
    $stmt2->execute([
        $user_id,
        $school_id,
        trim($data->headmaster_name),
        $email,
        trim($data->headmaster_phone),
        $hash,
        $tempPassword
    ]);

    // 3. Auto-provision selected Education Levels and their Master Curriculums
    $levelNames = [
        'NURSERY' => ['name' => 'Nursery & Pre-School Education', 'range' => 'Baby Class - Pre-Unit'],
        'PRIM'    => ['name' => 'Primary Education', 'range' => 'Standard 1 - Standard 7'],
        'O-LEVEL' => ['name' => 'Ordinary Level Secondary Education', 'range' => 'Form 1 - Form 4'],
        'A-LEVEL' => ['name' => 'Advanced Level Secondary Education', 'range' => 'Form 5 - Form 6']
    ];

    $insSEL = $conn->prepare("
        INSERT INTO school_education_levels (school_id, level_code, level_name, range_text, status)
        VALUES (?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE status = 'active'
    ");

    $insApp = $conn->prepare("
        INSERT INTO school_approved_subjects (school_id, subject_code, subject_name, level_code, status)
        VALUES (?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE subject_name = VALUES(subject_name), status = 'active'
    ");

    $insGS = $conn->prepare("
        INSERT INTO grade_subjects (school_id, academic_year, grade_id, subject_code, subject_name, is_core)
        VALUES (?, '" . date('Y') . "', ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE subject_name = VALUES(subject_name), is_core = VALUES(is_core)
    ");

    foreach ($selectedLevels as $levelCode) {
        $meta = $levelNames[$levelCode] ?? ['name' => $levelCode, 'range' => 'Universal'];
        $insSEL->execute([$school_id, $levelCode, $meta['name'], $meta['range']]);

        // Copy subject templates
        $stmtTplSub = $conn->prepare("SELECT name AS subject_name, code AS subject_code FROM academic_templates WHERE type = 'subject' AND (level_code = ? OR level_code = 'ALL')");
        $stmtTplSub->execute([$levelCode]);
        $tplSubjects = $stmtTplSub->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tplSubjects as $ts) {
            $insApp->execute([$school_id, $ts['subject_code'], $ts['subject_name'], $levelCode]);
        }

        // Copy grade templates
        $stmtTplClass = $conn->prepare("SELECT name, code, details FROM academic_templates WHERE type = 'class' AND level_code = ?");
        $stmtTplClass->execute([$levelCode]);
        $classTemplates = $stmtTplClass->fetchAll(PDO::FETCH_ASSOC);

        foreach ($classTemplates as $ct) {
            $stmtG = $conn->prepare("SELECT id FROM grades WHERE name = ? LIMIT 1");
            $stmtG->execute([$ct['name']]);
            $gradeId = $stmtG->fetchColumn();

            if ($gradeId) {
                $details = json_decode($ct['details'] ?? '{}', true) ?? [];
                $assigned = $details['assigned_subjects'] ?? [];

                foreach ($assigned as $as) {
                    $code   = is_array($as) ? ($as['subject_code'] ?? '') : $as;
                    $isCore = is_array($as) ? intval($as['is_core'] ?? 1) : 1;

                    if ($code) {
                        $stmtN = $conn->prepare("SELECT name FROM academic_templates WHERE type = 'subject' AND code = ? LIMIT 1");
                        $stmtN->execute([$code]);
                        $sbjName = $stmtN->fetchColumn() ?: $code;

                        $insGS->execute([$school_id, $gradeId, $code, $sbjName, $isCore]);
                    }
                }
            }
        }
    }

    $conn->commit();
    echo json_encode([
        "success" => true,
        "message" => "School created & education levels provisioned successfully.",
        "headmaster_credentials" => [
            "name" => trim($data->headmaster_name),
            "email" => $email,
            "phone" => trim($data->headmaster_phone),
            "temp_password" => $tempPassword,
            "is_password_changed" => false,
            "login_url" => "/shule-cafe/frontend/auth/login.html"
        ]
    ]);

} catch (PDOException $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error: " . $e->getMessage()]);
}
?>
