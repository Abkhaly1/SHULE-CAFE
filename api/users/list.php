<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

$role = $_GET['role'] ?? null;
$search = $_GET['search'] ?? null;

try {
    $sql = "
        SELECT 
            u.id, 
            u.full_name, 
            u.email,
            u.phone, 
            u.role, 
            u.status, 
            u.created_at,
            s.name as school_name
        FROM users u
        LEFT JOIN schools s ON u.school_id = s.id
        WHERE 1=1
    ";
    
    $params = [];

    if ($role) {
        $sql .= " AND u.role = ?";
        $params[] = $role;
    }

    if ($search) {
        $sql .= " AND (u.full_name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $sql .= " ORDER BY u.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    $users = $stmt->fetchAll();

    echo json_encode(["success" => true, "data" => $users]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error: " . $e->getMessage()]);
}
?>
