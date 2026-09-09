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

    // 2. Academics (Curriculum Subjects & Levels Check)
    $activeSubjCount = 0;
    try {
        $subStmt = $conn->prepare("
            SELECT COUNT(*) FROM school_approved_subjects 
            WHERE school_id = ? AND status = 'active'
        ");
        $subStmt->execute([$school_id]);
        $activeSubjCount = (int)$subStmt->fetchColumn();
        if ($activeSubjCount === 0) {
            $gsStmt = $conn->prepare("SELECT COUNT(*) FROM grade_subjects WHERE school_id = ?");
            $gsStmt->execute([$school_id]);
            $activeSubjCount = (int)$gsStmt->fetchColumn();
        }
    } catch (Exception $e) {
        $activeSubjCount = 0;
    }
    $academicsConfigured = ($activeSubjCount > 0);

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

    // 5. Class Guiders Check (Requires Classrooms to exist)
    $guidersConfigured = false;
    if ($totalClasses > 0) {
        try {
            $gStmt = $conn->prepare("SELECT COUNT(*) FROM class_teachers WHERE school_id = ?");
            $gStmt->execute([$school_id]);
            $guidersCount = (int)$gStmt->fetchColumn();
            $guidersConfigured = ($guidersCount > 0);
        } catch (Exception $e) {
            $guidersConfigured = false;
        }
    }

    // 6. Subject Allocations Check (Requires Curriculum Subjects)
    $subAllocConfigured = false;
    try {
        $subStmt = $conn->prepare("SELECT COUNT(*) FROM teacher_subject_assignments WHERE school_id = ?");
        $subStmt->execute([$school_id]);
        $subAllocCount = (int)$subStmt->fetchColumn();
        $subAllocConfigured = ($subAllocCount > 0);
    } catch (Exception $e) {
        $subAllocConfigured = false;
    }

    // 7. Timetable Check (Requires Classrooms and Subject Allocations)
    $ttConfigured = false;
    try {
        $ttStmt = $conn->prepare("SELECT COUNT(*) FROM class_timetables WHERE school_id = ? AND academic_year = ?");
        $ttStmt->execute([$school_id, $year]);
        $ttCount = (int)$ttStmt->fetchColumn();
        $ttConfigured = ($ttCount > 0);
    } catch (Exception $e) {
        $ttConfigured = false;
    }

    // Build Prerequisite Gating Metadata
    $settingsResponse = [
        "school_profile" => [
            "configured" => $profileConfigured,
            "label" => $profileConfigured ? "Configured" : "Needs Setup",
            "prerequisites_met" => true,
            "prerequisite_message" => null,
            "cta_url" => "school-profile.html",
            "cta_text" => "Configure Profile"
        ],
        "academics" => [
            "configured" => $academicsConfigured,
            "label" => $academicsConfigured ? "Configured" : "Needs Setup",
            "prerequisites_met" => true,
            "prerequisite_message" => null,
            "cta_url" => "../academics/index.html?tab=subjects",
            "cta_text" => "Configure Curriculum Subjects"
        ],
        "assessment_config" => [
            "configured" => $assessConfigured,
            "label" => $assessConfigured ? "Ready" : "Needs Setup",
            "prerequisites_met" => $academicsConfigured,
            "prerequisite_message" => $academicsConfigured ? null : "Curriculum subjects must be configured before setting assessment weights and grading rules.",
            "cta_url" => $academicsConfigured ? "../academics/assessment-config.html" : "../academics/index.html?tab=subjects",
            "cta_text" => $academicsConfigured ? "Configure Assessments" : "Configure Curriculum Subjects First"
        ],
        "classrooms" => [
            "configured" => $classroomsConfigured,
            "label" => $classroomsConfigured ? "Active" : "Needs Setup",
            "prerequisites_met" => true,
            "prerequisite_message" => null,
            "cta_url" => "../classrooms/index.html",
            "cta_text" => "Configure Classrooms"
        ],
        "class_guiders" => [
            "configured" => $guidersConfigured,
            "label" => $guidersConfigured ? "Assigned" : "Needs Setup",
            "prerequisites_met" => $classroomsConfigured,
            "prerequisite_message" => $classroomsConfigured ? null : "Classrooms and streams must be created before assigning class guiders / mentors.",
            "cta_url" => $classroomsConfigured ? "../allocations/class-guiders.html" : "../classrooms/index.html",
            "cta_text" => $classroomsConfigured ? "Assign Class Guiders" : "Create Classrooms First"
        ],
        "subject_allocations" => [
            "configured" => $subAllocConfigured,
            "label" => $subAllocConfigured ? "Assigned" : "Needs Setup",
            "prerequisites_met" => $academicsConfigured,
            "prerequisite_message" => $academicsConfigured ? null : "You cannot assign teachers to subjects until your school chooses and activates curriculum subjects.",
            "cta_url" => $academicsConfigured ? "../allocations/subject-allocations.html" : "../academics/index.html?tab=subjects",
            "cta_text" => $academicsConfigured ? "Assign Teachers" : "Configure Curriculum Subjects First"
        ],
        "timetable" => [
            "configured" => $ttConfigured,
            "label" => $ttConfigured ? "Generated" : "Needs Setup",
            "prerequisites_met" => ($classroomsConfigured && $subAllocConfigured),
            "prerequisite_message" => (!$classroomsConfigured) 
                ? "Classrooms must be created before timetable schedules can be generated." 
                : ((!$subAllocConfigured) ? "Teachers must be allocated to their curriculum subjects before timetable schedules can be prepared." : null),
            "cta_url" => (!$classroomsConfigured) 
                ? "../classrooms/index.html" 
                : ((!$subAllocConfigured) ? "../allocations/subject-allocations.html" : "../timetable/index.html"),
            "cta_text" => (!$classroomsConfigured) 
                ? "Create Classrooms First" 
                : ((!$subAllocConfigured) ? "Allocate Subjects First" : "Prepare Timetable")
        ]
    ];

    echo json_encode([
        "success" => true,
        "school_id" => $school_id,
        "settings" => $settingsResponse
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error checking setup status: " . $e->getMessage()
    ]);
}
