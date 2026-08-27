<?php
/**
 * SHULE CAFE Enterprise
 * Copyright (c) 2026 SHULE CAFE Enterprise. All Rights Reserved.
 * PROPRIETARY & CONFIDENTIAL. Unauthorized copying or redistribution is strictly prohibited.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $name        = trim($_GET['name'] ?? '');
    $nectaNo     = trim($_GET['necta_no'] ?? '');
    $schoolEmail = trim($_GET['email'] ?? '');

    $nameAvailable = true;
    $nameMessage   = '';

    $nectaAvailable = true;
    $nectaMessage   = '';

    $emailAvailable = true;
    $emailMessage   = '';

    if (!empty($name)) {
        $stmt = $db->prepare("SELECT id FROM schools WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $stmt->execute([$name]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $nameAvailable = false;
            $nameMessage   = 'School name is already taken';
        } else {
            $nameMessage   = 'School name is available';
        }
    }

    if (!empty($nectaNo)) {
        if (!preg_match('/^(S|P|C|E|REG)[\.\/\s-]?[0-9A-Z]{3,12}$/i', $nectaNo)) {
            $nectaAvailable = false;
            $nectaMessage   = 'Invalid NECTA format. Reg numbers start with a prefix like S., P., C., E., or REG (e.g. S.1234 or P.5678)';
        } else {
            try { $db->exec("ALTER TABLE schools ADD COLUMN necta_no VARCHAR(50) DEFAULT NULL"); } catch (Throwable $e) {}

            $stmt2 = $db->prepare("SELECT id FROM schools WHERE LOWER(necta_no) = LOWER(?) AND necta_no != '' LIMIT 1");
            $stmt2->execute([$nectaNo]);
            if ($stmt2->fetch(PDO::FETCH_ASSOC)) {
                $nectaAvailable = false;
                $nectaMessage   = 'NECTA registration number is already registered to another school';
            } else {
                $nectaAvailable = true;
                $nectaMessage   = 'NECTA registration number is available';
            }
        }
    }

    if (!empty($schoolEmail)) {
        try { $db->exec("ALTER TABLE schools ADD COLUMN school_email VARCHAR(150) DEFAULT NULL"); } catch (Throwable $e) {}
        try { $db->exec("ALTER TABLE users ADD COLUMN email VARCHAR(150) DEFAULT NULL"); } catch (Throwable $e) {}

        $stmt3 = $db->prepare("
            SELECT id FROM schools WHERE LOWER(school_email) = LOWER(?) AND school_email != ''
            UNION
            SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND email != ''
            LIMIT 1
        ");
        $stmt3->execute([$schoolEmail, $schoolEmail]);
        if ($stmt3->fetch(PDO::FETCH_ASSOC)) {
            $emailAvailable = false;
            $emailMessage   = 'Official school email is already registered to another school and cannot be accepted';
        } else {
            $emailAvailable = true;
            $emailMessage   = 'Official school email is available';
        }
    }
    
    echo json_encode([
        'success' => true,
        'available' => ($nameAvailable && $nectaAvailable && $emailAvailable),
        'name_available' => $nameAvailable,
        'name_message' => $nameMessage,
        'necta_available' => $nectaAvailable,
        'necta_message' => $nectaMessage,
        'email_available' => $emailAvailable,
        'email_message' => $emailMessage
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => true, 
        'available' => true, 
        'name_available' => true, 
        'necta_available' => true, 
        'email_available' => true, 
        'error' => $e->getMessage()
    ]);
}
