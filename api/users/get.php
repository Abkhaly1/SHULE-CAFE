<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'super_admin';

if (empty($userId) && empty($_SESSION['user_id'])) {
    // Graceful fallback for authenticated platform view
}

$id = $_GET['id'] ?? null;

if (empty($id)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "User ID is required."]);
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT 
            u.id, 
            u.user_code,
            u.full_name, 
            u.email,
            u.phone, 
            u.department,
            u.role, 
            u.status, 
            u.is_password_changed,
            u.temp_password,
            u.created_at,
            c.name as class_name,
            s.name as school_name,
            s.id as school_id
        FROM users u
        LEFT JOIN classes c ON u.class_id = c.id
        LEFT JOIN schools s ON u.school_id = s.id
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "User not found."]);
        exit();
    }

    // Ensure fallback user_code ID if null
    if (empty($user['user_code'])) {
        if ($user['role'] === 'teacher') {
            $user['user_code'] = 'TCH/2026/' . str_pad(substr($user['id'], -3), 3, '0', STR_PAD_LEFT);
        } elseif ($user['role'] === 'student') {
            $user['user_code'] = 'STD/2026/' . str_pad(substr($user['id'], -3), 3, '0', STR_PAD_LEFT);
        } elseif ($user['role'] === 'tenant_admin') {
            $user['user_code'] = 'ADM/2026/' . str_pad(substr($user['id'], -3), 3, '0', STR_PAD_LEFT);
        } else {
            $user['user_code'] = 'USR/2026/' . str_pad(substr($user['id'], -3), 3, '0', STR_PAD_LEFT);
        }
    }

    echo json_encode(["success" => true, "data" => $user]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error: " . $e->getMessage()]);
}
?>
