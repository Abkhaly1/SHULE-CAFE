<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Access denied. Only Super Admin can delete grading scales."]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$id = !empty($input['id']) ? intval($input['id']) : null;
$category = trim($input['category'] ?? ''); // 'grading' or 'division'

if (!$id || empty($category)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Scale ID and category are required."]);
    exit();
}

try {
    $table = ($category === 'division') ? 'division_scales' : 'grading_scales';
    $stmt = $conn->prepare("DELETE FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(["success" => true, "message" => "Scale entry deleted successfully."]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
