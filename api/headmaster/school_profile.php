<?php
/**
 * SHULE CAFE - Headmaster School Profile Management Endpoint
 * Proprietary License & Intellectual Property Protection Notice
 * Copyright (c) 2026 SHULE CAFE. All Rights Reserved.
 */

session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['tenant_admin', 'super_admin', 'headmaster'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

$school_id = $_SESSION['school_id'] ?? $_GET['school_id'] ?? null;

if (!$school_id && $_SESSION['role'] === 'super_admin') {
    $stmt = $conn->query("SELECT id FROM schools LIMIT 1");
    $school_id = $stmt->fetchColumn();
}

if (!$school_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "School ID missing from session."]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $stmt = $conn->prepare("
            SELECT 
                id,
                school_code,
                name,
                type,
                necta_no,
                ownership_type,
                motto,
                gender_classification,
                region,
                district,
                ward_address,
                postal_address,
                school_email,
                school_phone,
                status,
                created_at,
                updated_at
            FROM schools 
            WHERE id = ? 
            LIMIT 1
        ");
        $stmt->execute([$school_id]);
        $school = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$school) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "School records not found."]);
            exit();
        }

        // Additional counts
        $stuStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE school_id = ? AND role = 'student'");
        $stuStmt->execute([$school_id]);
        $totalStudents = (int)$stuStmt->fetchColumn();

        $tchStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE school_id = ? AND role = 'teacher'");
        $tchStmt->execute([$school_id]);
        $totalTeachers = (int)$tchStmt->fetchColumn();

        $clsStmt = $conn->prepare("SELECT COUNT(*) FROM classrooms WHERE school_id = ? AND is_active = 1");
        $clsStmt->execute([$school_id]);
        $totalClasses = (int)$clsStmt->fetchColumn();

        $school['total_students'] = $totalStudents;
        $school['total_teachers'] = $totalTeachers;
        $school['total_classes'] = $totalClasses;
        $school['license_key'] = 'SHULE-ENT-2026-ACTIVE';

        echo json_encode([
            "success" => true,
            "school" => $school
        ]);
        exit();

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
        exit();
    }

} else if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $name = trim($input['name'] ?? '');
    $necta_no = trim($input['necta_no'] ?? '');
    $type = trim($input['type'] ?? 'Secondary');
    $ownership_type = trim($input['ownership_type'] ?? 'Government');
    $motto = trim($input['motto'] ?? '');
    $region = trim($input['region'] ?? '');
    $district = trim($input['district'] ?? '');
    $ward_address = trim($input['ward_address'] ?? '');
    $postal_address = trim($input['postal_address'] ?? '');
    $school_email = trim($input['school_email'] ?? '');
    $school_phone = trim($input['school_phone'] ?? '');

    if (empty($name)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Official school name is required."]);
        exit();
    }

    if (empty($region)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Region is required."]);
        exit();
    }

    try {
        $updateStmt = $conn->prepare("
            UPDATE schools 
            SET 
                name = ?,
                necta_no = ?,
                type = ?,
                ownership_type = ?,
                motto = ?,
                region = ?,
                district = ?,
                ward_address = ?,
                postal_address = ?,
                school_email = ?,
                school_phone = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $updateStmt->execute([
            $name,
            $necta_no,
            $type,
            $ownership_type,
            $motto,
            $region,
            $district,
            $ward_address,
            $postal_address,
            $school_email,
            $school_phone,
            $school_id
        ]);

        echo json_encode([
            "success" => true,
            "message" => "School profile records successfully updated and synchronized across institutional registers."
        ]);
        exit();

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to update school profile: " . $e->getMessage()]);
        exit();
    }

} else {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed."]);
    exit();
}
