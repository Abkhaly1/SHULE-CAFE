<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'regional_officer') {
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

    echo json_encode([
        "success" => true, 
        "data" => [
            "schools" => $total_schools,
            "students" => $total_students,
            "teachers" => $total_teachers,
            "parents" => $total_parents
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error: " . $e->getMessage()]);
}
?>
