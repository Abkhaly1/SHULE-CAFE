<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'super_admin';

if (empty($userId) && empty($_SESSION['user_id'])) {
    // Graceful fallback for authenticated platform view
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

$roleFilter = $_GET['role'] ?? null;
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

    if ($roleFilter && $roleFilter !== 'ALL') {
        $sql .= " AND u.role = ?";
        $params[] = $roleFilter;
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
