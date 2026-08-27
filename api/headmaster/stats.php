<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['tenant_admin', 'super_admin'])) {
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
    echo json_encode(["success" => false, "message" => "School ID missing."]);
    exit();
}

try {
    // School details
    $schStmt = $conn->prepare("SELECT name, type, region FROM schools WHERE id = ?");
    $schStmt->execute([$school_id]);
    $school = $schStmt->fetch(PDO::FETCH_ASSOC);

    // Dynamic counts
    $stuStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE school_id = ? AND role = 'student'");
    $stuStmt->execute([$school_id]);
    $totalStudents = (int)$stuStmt->fetchColumn();

    $tchStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE school_id = ? AND role = 'teacher'");
    $tchStmt->execute([$school_id]);
    $totalTeachers = (int)$tchStmt->fetchColumn();

    $year = date('Y');
    $clsStmt = $conn->prepare("SELECT COUNT(*) FROM classrooms WHERE school_id = ? AND academic_year = ? AND is_active = 1");
    $clsStmt->execute([$school_id, $year]);
    $totalClasses = (int)$clsStmt->fetchColumn();

    $parStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE school_id = ? AND role = 'parent'");
    $parStmt->execute([$school_id]);
    $totalParents = (int)$parStmt->fetchColumn();

    // Recent Activities
    $actStmt = $conn->prepare("SELECT full_name, role, created_at FROM users WHERE school_id = ? ORDER BY created_at DESC LIMIT 5");
    $actStmt->execute([$school_id]);
    $recentUsers = $actStmt->fetchAll(PDO::FETCH_ASSOC);

    $activities = [];
    foreach ($recentUsers as $u) {
        $activities[] = [
            "title" => ucfirst($u['role']) . " Registration",
            "desc" => $u['full_name'] . " was added to the system.",
            "time" => date('M d, H:i', strtotime($u['created_at']))
        ];
    }

    $subAllocStmt = $conn->prepare("SELECT COUNT(*) FROM teacher_subject_assignments WHERE school_id = ?");
    $subAllocStmt->execute([$school_id]);
    $totalSubAllocations = (int)$subAllocStmt->fetchColumn();

    $ttStmt = $conn->prepare("SELECT COUNT(*) FROM class_timetables WHERE school_id = ? AND academic_year = ?");
    $ttStmt->execute([$school_id, $year]);
    $totalTimetables = (int)$ttStmt->fetchColumn();

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
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
