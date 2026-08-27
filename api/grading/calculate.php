<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';
require_once 'GradingManager.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$levelType = trim($input['level_type'] ?? 'O-Level');
$marks = $input['marks'] ?? [];

if (empty($marks) || !is_array($marks)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Subject marks array is required."]);
    exit();
}

try {
    $calculator = new GradingManager($conn);
    $performance = $calculator->calculateStudentPerformance($levelType, $marks);

    echo json_encode([
        "success" => true,
        "performance" => $performance
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Calculation error: " . $e->getMessage()]);
}
?>
