<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
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
$full_name = trim($input['full_name'] ?? '');
$phone = trim($input['phone'] ?? '');
$email = trim($input['email'] ?? '') ?: null;
$status = trim($input['status'] ?? 'active');

if (empty($id) || empty($full_name) || empty($phone)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "User ID, Full Name, and Phone Number are required."]);
    exit();
}

$sessionRole = $_SESSION['role'] ?? '';
$sessionSchoolId = $_SESSION['school_id'] ?? null;

try {
    $stmt = $conn->prepare("SELECT id, school_id, role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "User not found."]);
        exit();
    }

    if ($sessionRole === 'tenant_admin') {
        if ($user['school_id'] !== $sessionSchoolId) {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Access denied."]);
            exit();
        }
    } else if ($sessionRole !== 'super_admin') {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Unauthorized access."]);
        exit();
    }

    $updStmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, email = ?, status = ? WHERE id = ?");
    $updStmt->execute([$full_name, $phone, $email, $status, $id]);

    echo json_encode(["success" => true, "message" => "User updated successfully."]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
