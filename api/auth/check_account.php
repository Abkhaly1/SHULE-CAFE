<?php
/**
 * SHULE CAFE Enterprise
 * Copyright (c) 2026 SHULE CAFE Enterprise. All Rights Reserved.
 * PROPRIETARY & CONFIDENTIAL. Unauthorized copying or redistribution is strictly prohibited.
 */
require_once __DIR__ . '/../config/auth_guard.php';
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

// Accept both GET and POST requests for quick verification
$rawInput = file_get_contents("php://input");
$data = !empty($rawInput) ? json_decode($rawInput) : null;

$identifier = '';
if (!empty($data->identifier)) {
    $identifier = trim($data->identifier);
} else if (!empty($_GET['identifier'])) {
    $identifier = trim($_GET['identifier']);
} else if (!empty($_POST['identifier'])) {
    $identifier = trim($_POST['identifier']);
}

if (empty($identifier)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Identifier parameter is required."]);
    exit();
}

// 🤖 Anti-Bot checks
detectBotUserAgent();

// Phone normalization (e.g. 0700000000 -> +255700000000)
$phoneAlt = $identifier;
if (strpos($identifier, '0') === 0) {
    $phoneAlt = '+255' . substr($identifier, 1);
} else if (strpos($identifier, '+255') === 0) {
    $phoneAlt = '0' . substr($identifier, 4);
}

try {
    $stmt = $conn->prepare("
        SELECT u.id, u.user_code, u.full_name, u.email, u.phone, u.role, u.status, u.school_id, s.name as school_name, s.school_code
        FROM users u
        LEFT JOIN schools s ON u.school_id = s.id
        WHERE u.email = ? 
           OR s.school_email = ?
           OR s.school_code = ?
           OR u.user_code = ?
        ORDER BY 
            (s.school_code = ? OR u.user_code = ?) DESC,
            (u.email = ? OR s.school_email = ?) DESC,
            (u.role IN ('tenant_admin', 'school_admin')) DESC,
            u.created_at ASC
        LIMIT 1
    ");
    $stmt->execute([
        $identifier, $identifier, $identifier, $identifier,
        $identifier, $identifier, $identifier, $identifier
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Also check if standalone school match
        echo json_encode([
            "success" => true,
            "exists" => true,
            "account_type" => $user['role'],
            "role" => ($user['role'] === 'school_admin' ? 'tenant_admin' : $user['role']),
            "display_name" => $user['full_name'],
            "school_name" => $user['school_name'] ?? 'SHULE CAFE Platform',
            "school_code" => $user['school_code'] ?? '',
            "status" => $user['status']
        ]);
    } else {
        // Check if school registered under this code/email with pending admin
        $schStmt = $conn->prepare("SELECT id, name, school_code, school_email, status FROM schools WHERE school_code = ? OR school_email = ? LIMIT 1");
        $schStmt->execute([$identifier, $identifier]);
        $school = $schStmt->fetch(PDO::FETCH_ASSOC);

        if ($school) {
            echo json_encode([
                "success" => true,
                "exists" => true,
                "account_type" => "school",
                "role" => "tenant_admin",
                "display_name" => $school['name'],
                "school_name" => $school['name'],
                "school_code" => $school['school_code'],
                "status" => $school['status']
            ]);
        } else {
            echo json_encode([
                "success" => true,
                "exists" => false,
                "account_needed" => true,
                "message" => "Account Needed: No registered account found for this identifier. If you are a new school, please register your school to create your portal.",
                "register_url" => "register-school.html"
            ]);
        }
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database check error: " . $e->getMessage()]);
}
