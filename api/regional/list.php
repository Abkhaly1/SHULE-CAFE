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

try {
    $stmt = $conn->prepare("
        SELECT id, full_name, phone, status, created_at
        FROM users
        WHERE role = 'regional_officer'
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    
    $officers = $stmt->fetchAll();
    
    echo json_encode(["success" => true, "data" => $officers]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error: " . $e->getMessage()]);
}
?>
