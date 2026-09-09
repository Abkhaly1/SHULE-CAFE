<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/id_generator.php';

// Only Super Admin can create Regional Officers
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

$data = json_decode(file_get_contents("php://input"));

if (empty($data->full_name) || empty($data->phone) || empty($data->password)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "All fields are required."]);
    exit();
}

try {
    $phone = trim($data->phone);
    $region = trim($data->region ?? 'MJINI MAGHARIBI');

    $check = $conn->prepare("SELECT id FROM users WHERE phone = ?");
    $check->execute([$phone]);
    if ($check->fetch()) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Phone number already registered."]);
        exit();
    }

    $user_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    $hash = password_hash($data->password, PASSWORD_BCRYPT);
    
    // Generate Option A Systematic Regional Officer ID: SC/REG-{REGION}/OFF-{SEQ}
    $user_code = generateShuleCafeUserId($conn, null, 'regional_officer', $region);

    // regional_officer doesn't belong to a specific school (school_id is NULL)
    $stmt = $conn->prepare("INSERT INTO users (id, school_id, user_code, full_name, phone, department, password_hash, role, status) VALUES (?, NULL, ?, ?, ?, ?, ?, 'regional_officer', 'active')");
    $stmt->execute([
        $user_id,
        $user_code,
        trim($data->full_name),
        $phone,
        $region,
        $hash
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Regional Officer registered successfully.",
        "user_code" => $user_code
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error: " . $e->getMessage()]);
}
?>
