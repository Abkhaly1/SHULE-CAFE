<?php
/**
 * SHULE CAFE - Headmaster Settings & Setup Status Endpoint
 * Proprietary License & Intellectual Property Protection Notice
 * Copyright (c) 2026 SHULE CAFE. All Rights Reserved.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

$user_id = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';

// Resolve school_id gracefully
$school_id = $_SESSION['school_id'] ?? $_GET['school_id'] ?? null;
if (empty($school_id) && !empty($user_id)) {
    $uStmt = $conn->prepare("SELECT school_id FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$user_id]);
    $school_id = $uStmt->fetchColumn() ?: null;
}
if (empty($school_id)) {
    $sStmt = $conn->query("SELECT id FROM schools ORDER BY id ASC LIMIT 1");
    $school_id = $sStmt->fetchColumn() ?: null;
}

if (!$school_id) {
    echo json_encode([
        "success" => true,
        "school_id" => null,
        "settings" => [
            "school_profile"     => ["configured" => false, "label" => "Needs Setup"],
            "academics"          => ["configured" => false, "label" => "Needs Setup"],
            "assessment_config"  => ["configured" => false, "label" => "Needs Setup"],
            "classrooms"         => ["configured" => false, "label" => "Needs Setup"],
            "class_guiders"      => ["configured" => false, "label" => "Needs Setup"],
            "subject_allocations"=> ["configured" => false, "label" => "Needs Setup"],
            "timetable"          => ["configured" => false, "label" => "Needs Setup"]
        ]
    ]);
    exit();
}

$year = date('Y');

try {
    // 1. School Profile Check
    $schStmt = $conn->prepare("SELECT name, school_email, school_phone, region, district, necta_no FROM schools WHERE id = ? LIMIT 1");
    $schStmt->execute([$school_id]);
    $schoolInfo = $schStmt->fetch(PDO::FETCH_ASSOC);
    $profileConfigured = false;
    if ($schoolInfo) {
        $hasEmail = !empty($schoolInfo['school_email']) && $schoolInfo['school_email'] !== 'N/A';
        $hasPhone = !empty($schoolInfo['school_phone']) && $schoolInfo['school_phone'] !== 'N/A';
        $hasRegion = !empty($schoolInfo['region']) && $schoolInfo['region'] !== 'N/A';
        $profileConfigured = ($hasEmail || $hasPhone) && $hasRegion;
    }

    // 2. Academics (Grades / Levels) Check
    $lvlCount = 0;
    $grdCount = 0;
    try {
        $lvlStmt = $conn->query("SELECT COUNT(*) FROM education_levels");
        $lvlCount = (int)$lvlStmt->fetchColumn();
        $grdStmt = $conn->query("SELECT COUNT(*) FROM grades");
        $grdCount = (int)$grdStmt->fetchColumn();
    } catch (Exception $e) {}
    $academicsConfigured = ($lvlCount > 0 && $grdCount > 0);

    // 3. Assessment Configuration Check
    $assessConfigured = false;
    try {
        $assStmt = $conn->prepare("SELECT COUNT(*) FROM assessment_types WHERE school_id = ?");
        $assStmt->execute([$school_id]);
        $assCount = (int)$assStmt->fetchColumn();
        if ($assCount > 0) {
            $assessConfigured = true;
        } else {
            $gsStmt = $conn->query("SELECT COUNT(*) FROM grading_scales");
            $assessConfigured = ((int)$gsStmt->fetchColumn() > 0);
        }
    } catch (Exception $e) {
        $assessConfigured = false;
    }

    // 4. Classrooms Check
    $totalClasses = 0;
    try {
        $clsStmt = $conn->prepare("SELECT COUNT(*) FROM classrooms WHERE school_id = ? AND is_active = 1");
        $clsStmt->execute([$school_id]);
        $totalClasses = (int)$clsStmt->fetchColumn();
    } catch (Exception $e) {}
    $classroomsConfigured = ($totalClasses > 0);

    // 5. Class Guiders Check
    $guidersConfigured = false;
    if ($totalClasses > 0) {
        try {
            $gStmt = $conn->prepare("SELECT COUNT(*) FROM classrooms WHERE school_id = ? AND is_active = 1 AND class_teacher_id IS NOT NULL AND class_teacher_id > 0");
            $gStmt->execute([$school_id]);
            $guidersCount = (int)$gStmt->fetchColumn();
            $guidersConfigured = ($guidersCount > 0);
        } catch (Exception $e) {
            $guidersConfigured = false;
        }
    }

    // 6. Subject Allocations Check
    $subAllocConfigured = false;
    try {
        $subStmt = $conn->prepare("SELECT COUNT(*) FROM teacher_subject_assignments WHERE school_id = ?");
        $subStmt->execute([$school_id]);
        $subAllocCount = (int)$subStmt->fetchColumn();
        $subAllocConfigured = ($subAllocCount > 0);
    } catch (Exception $e) {
        $subAllocConfigured = false;
    }

    // 7. Timetable Check
    $ttConfigured = false;
    try {
        $ttStmt = $conn->prepare("SELECT COUNT(*) FROM class_timetables WHERE school_id = ? AND academic_year = ?");
        $ttStmt->execute([$school_id, $year]);
        $ttCount = (int)$ttStmt->fetchColumn();
        $ttConfigured = ($ttCount > 0);
    } catch (Exception $e) {
        $ttConfigured = false;
    }

    echo json_encode([
        "success" => true,
        "school_id" => $school_id,
        "settings" => [
            "school_profile" => [
                "configured" => $profileConfigured,
                "label" => $profileConfigured ? "Configured" : "Needs Setup"
            ],
            "academics" => [
                "configured" => $academicsConfigured,
                "label" => $academicsConfigured ? "Configured" : "Needs Setup"
            ],
            "assessment_config" => [
                "configured" => $assessConfigured,
                "label" => $assessConfigured ? "Ready" : "Needs Setup"
            ],
            "classrooms" => [
                "configured" => $classroomsConfigured,
                "label" => $classroomsConfigured ? "Active" : "Needs Setup"
            ],
            "class_guiders" => [
                "configured" => $guidersConfigured,
                "label" => $guidersConfigured ? "Assigned" : "Needs Setup"
            ],
            "subject_allocations" => [
                "configured" => $subAllocConfigured,
                "label" => $subAllocConfigured ? "Assigned" : "Needs Setup"
            ],
            "timetable" => [
                "configured" => $ttConfigured,
                "label" => $ttConfigured ? "Generated" : "Needs Setup"
            ]
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error checking setup status: " . $e->getMessage()
    ]);
}
