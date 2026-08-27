<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

try {
    // Total Schools
    $stmt = $conn->query("SELECT COUNT(id) as count FROM schools");
    $total_schools = $stmt->fetch()['count'];

    // Total Students
    $stmt = $conn->query("SELECT COUNT(id) as count FROM users WHERE role = 'student'");
    $total_students = $stmt->fetch()['count'];

    // Total Teachers
    $stmt = $conn->query("SELECT COUNT(id) as count FROM users WHERE role = 'teacher'");
    $total_teachers = $stmt->fetch()['count'];

    // Total Parents
    $stmt = $conn->query("SELECT COUNT(id) as count FROM users WHERE role = 'parent'");
    $total_parents = $stmt->fetch()['count'];

    // Recent Activities (Latest Schools & Users)
    $stmtAct = $conn->query("
        (SELECT CONCAT('New School Provisioned: ', name) AS title, CONCAT(type, ' School • Region: ', COALESCE(region, 'N/A')) AS subtitle, created_at FROM schools ORDER BY created_at DESC LIMIT 3)
        UNION ALL
        (SELECT CONCAT('User Registered: ', full_name) AS title, CONCAT('Role: ', UPPER(role), ' • Phone: ', COALESCE(phone, 'N/A')) AS subtitle, created_at FROM users WHERE role IN ('tenant_admin', 'regional_officer') ORDER BY created_at DESC LIMIT 3)
        ORDER BY created_at DESC
        LIMIT 6
    ");
    $activities = $stmtAct->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true, 
        "data" => [
            "schools"   => intval($total_schools),
            "students"  => intval($total_students),
            "teachers"  => intval($total_teachers),
            "parents"   => intval($total_parents),
            "activities" => $activities
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error: " . $e->getMessage()]);
}
?>
