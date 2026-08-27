<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

if ($_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Access denied. Only Super Admins can delete template items."]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$id = trim($input['id'] ?? $_GET['id'] ?? '');

if (empty($id)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Template ID is required."]);
    exit();
}

try {
    $stmt = $conn->prepare("DELETE FROM academic_templates WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(["success" => true, "message" => "Template item deleted successfully."]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
