<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$id = trim($input['id'] ?? '');
$name = trim($input['name'] ?? '');
$code = trim($input['code'] ?? '');
$level_code = trim($input['level_code'] ?? '');
$description = trim($input['description'] ?? '');
$status = trim($input['status'] ?? 'active');

if (empty($id) || empty($name)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "ID and Name are required."]);
    exit();
}

try {
    $stmt = $conn->prepare("UPDATE academic_templates SET name = ?, code = ?, level_code = ?, description = ?, status = ? WHERE id = ?");
    $stmt->execute([$name, $code, $level_code ?: null, $description, $status, $id]);

    echo json_encode(["success" => true, "message" => "Academic template updated successfully."]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error: " . $e->getMessage()]);
}
?>
