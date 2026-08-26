<?php
/**
 * SHULE CAFE - Proprietary License Integrity & Code Anti-Theft Guard
 * Copyright (c) 2026 SHULE CAFE. All Rights Reserved.
 * Protects software intellectual property against illegal cloning, stolen installations, and unauthorized hosting.
 */

function verifyLicenseIntegrity() {
    $currentHost = strtolower($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
    
    // Allowed operational environments (Local dev, test, production domain, localhost, 127.0.0.1)
    $allowedHosts = [
        'localhost',
        '127.0.0.1',
        'shulecafe.com',
        'shulecafe.com',
        'app.shulecafe.com'
    ];

    // Extract clean hostname without port
    $cleanHost = explode(':', $currentHost)[0];

    // Signature check: Allow any legitimate subdomain or local dev environment
    $isAuthorized = false;
    foreach ($allowedHosts as $allowed) {
        if ($cleanHost === $allowed || str_ends_with($cleanHost, '.' . $allowed)) {
            $isAuthorized = true;
            break;
        }
    }

    if (!$isAuthorized) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "License Violation Lock 🔒: Unauthorized Software Installation Detected. SHULE CAFE is proprietary software protected under Copyright Laws. Contact platform owner."
        ]);
        exit();
    }
}
?>
