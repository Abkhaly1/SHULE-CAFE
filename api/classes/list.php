<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tenant_admin' || empty($_SESSION['school_id'])) {
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
    $year = $_GET['year'] ?? date('Y');
    $stmt = $conn->prepare("
        SELECT c.id, CONCAT(g.name, ' - ', c.classroom_name) AS name, c.created_at 
        FROM classrooms c
        JOIN grades g ON c.grade_id = g.id
        WHERE c.school_id = ? AND c.academic_year = ? AND c.is_active = 1
        ORDER BY g.id ASC, c.classroom_name ASC
    ");
    $stmt->execute([$_SESSION['school_id'], $year]);
    
    $classes = $stmt->fetchAll();
    
    echo json_encode(["success" => true, "data" => $classes, "year" => $year]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error: " . $e->getMessage()]);
}
?>
