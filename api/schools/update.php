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

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$id = trim($input['id'] ?? '');
$name = trim($input['name'] ?? '');
$type = trim($input['type'] ?? '');
$region = trim($input['region'] ?? '');
$status = trim($input['status'] ?? '');

if (empty($id)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "School ID is required."]);
    exit();
}

try {
    $existing = $conn->prepare("SELECT name, type, region, status FROM schools WHERE id = ?");
    $existing->execute([$id]);
    $curr = $existing->fetch(PDO::FETCH_ASSOC);

    if (!$curr) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "School not found."]);
        exit();
    }

    $name = !empty($name) ? $name : $curr['name'];
    $type = !empty($type) ? $type : ($curr['type'] ?: 'Secondary');
    $region = !empty($region) ? $region : ($curr['region'] ?: '');
    $status = !empty($status) ? $status : ($curr['status'] ?: 'active');

    $stmt = $conn->prepare("UPDATE schools SET name = ?, type = ?, region = ?, status = ? WHERE id = ?");
    $stmt->execute([$name, $type, $region, $status, $id]);

    echo json_encode([
        "success" => true, 
        "message" => "School status updated successfully to " . ucfirst($status) . ".",
        "status" => $status
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error: " . $e->getMessage()]);
}
?>
