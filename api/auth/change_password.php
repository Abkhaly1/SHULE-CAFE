<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized access. Please log in."]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$currentPassword = trim($input['current_password'] ?? '');
$newPassword = trim($input['new_password'] ?? '');
$confirmPassword = trim($input['confirm_password'] ?? '');

if (empty($currentPassword) || empty($newPassword)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Current password and New password are required."]);
    exit();
}

if (strlen($newPassword) < 8) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "New password must be at least 8 characters long."]);
    exit();
}

if (!empty($confirmPassword) && $newPassword !== $confirmPassword) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "New password and confirmation do not match."]);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT id, password_hash FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "User record not found."]);
        exit();
    }

    if (!password_verify($currentPassword, $user['password_hash'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Incorrect current temporary password."]);
        exit();
    }

    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $upd = $conn->prepare("UPDATE users SET password_hash = ?, temp_password = NULL, is_password_changed = 1 WHERE id = ?");
    $upd->execute([$newHash, $_SESSION['user_id']]);

    echo json_encode(["success" => true, "message" => "Password changed and account secured successfully!"]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error: " . $e->getMessage()]);
}
?>
