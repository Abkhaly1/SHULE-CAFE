<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

$school_id = $_SESSION['school_id'] ?? $_GET['school_id'] ?? null;

if (empty($school_id) && !empty($_SESSION['user_id'])) {
    $uStmt = $conn->prepare("SELECT school_id FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$_SESSION['user_id']]);
    $school_id = $uStmt->fetchColumn() ?: null;
}

if (empty($school_id)) {
    $sStmt = $conn->query("SELECT id FROM schools ORDER BY id ASC LIMIT 1");
    $school_id = $sStmt->fetchColumn() ?: null;
}

if (empty($school_id)) {
    echo json_encode([
        "success" => true,
        "school" => ["name" => "School", "type" => "Secondary", "region" => "Tanzania"],
        "stats" => [
            "total_students" => 0,
            "total_teachers" => 0,
            "total_classes" => 0,
            "total_parents" => 0,
            "total_sub_allocations" => 0,
            "total_timetables" => 0
        ],
        "activities" => []
    ]);
    exit();
}

try {
    // School details
    $schStmt = $conn->prepare("SELECT name, type, region FROM schools WHERE id = ? LIMIT 1");
    $schStmt->execute([$school_id]);
    $school = $schStmt->fetch(PDO::FETCH_ASSOC) ?: ["name" => "School", "type" => "Secondary", "region" => "Tanzania"];

    // Aggregated user counts in 1 single fast query
    $userCntStmt = $conn->prepare("
        SELECT role, COUNT(*) AS cnt 
        FROM users 
        WHERE school_id = ? AND role IN ('student', 'teacher', 'parent') 
        GROUP BY role
    ");
    $userCntStmt->execute([$school_id]);
    $roleCounts = $userCntStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $totalStudents = (int)($roleCounts['student'] ?? 0);
    $totalTeachers = (int)($roleCounts['teacher'] ?? 0);
    $totalParents  = (int)($roleCounts['parent'] ?? 0);

    $year = date('Y');

    // Classrooms count
    $clsStmt = $conn->prepare("SELECT COUNT(*) FROM classrooms WHERE school_id = ? AND academic_year = ? AND is_active = 1");
    $clsStmt->execute([$school_id, $year]);
    $totalClasses = (int)$clsStmt->fetchColumn();

    // Subject allocations count
    $subAllocStmt = $conn->prepare("SELECT COUNT(*) FROM teacher_subject_assignments WHERE school_id = ?");
    $subAllocStmt->execute([$school_id]);
    $totalSubAllocations = (int)$subAllocStmt->fetchColumn();

    // Timetables count
    $ttStmt = $conn->prepare("SELECT COUNT(*) FROM class_timetables WHERE school_id = ? AND academic_year = ?");
    $ttStmt->execute([$school_id, $year]);
    $totalTimetables = (int)$ttStmt->fetchColumn();

    // Recent Activities (latest 5 user registrations or updates)
    $actStmt = $conn->prepare("SELECT full_name, role, created_at FROM users WHERE school_id = ? ORDER BY created_at DESC LIMIT 5");
    $actStmt->execute([$school_id]);
    $recentUsers = $actStmt->fetchAll(PDO::FETCH_ASSOC);

    $activities = [];
    foreach ($recentUsers as $u) {
        $activities[] = [
            "title" => ucfirst($u['role']) . " Registration",
            "desc" => htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8') . " was registered in the system.",
            "time" => date('M d, H:i', strtotime($u['created_at']))
        ];
    }

    echo json_encode([
        "success" => true,
        "school" => $school,
        "stats" => [
            "total_students" => $totalStudents,
            "total_teachers" => $totalTeachers,
            "total_classes" => $totalClasses,
            "total_parents" => $totalParents,
            "total_sub_allocations" => $totalSubAllocations,
            "total_timetables" => $totalTimetables
        ],
        "activities" => $activities
    ]);

} catch (Exception $e) {
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "school" => ["name" => "School", "type" => "Secondary", "region" => "Tanzania"],
        "stats" => [
            "total_students" => 0,
            "total_teachers" => 0,
            "total_classes" => 0,
            "total_parents" => 0,
            "total_sub_allocations" => 0,
            "total_timetables" => 0
        ],
        "activities" => []
    ]);
}
?>
