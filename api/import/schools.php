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
$rows = $input['rows'] ?? [];

if (empty($rows) || !is_array($rows)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "No rows provided for import."]);
    exit();
}

function generateUuidV4() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$successCount = 0;
$skippedCount = 0;

try {
    $stmt = $conn->prepare("INSERT INTO schools (id, name, type, region, status) VALUES (?, ?, ?, ?, 'active')");
    $userStmt = $conn->prepare("INSERT INTO users (id, school_id, role, phone, password_hash, full_name, status) VALUES (?, ?, 'tenant_admin', ?, ?, ?, 'active')");
    
    foreach ($rows as $row) {
        $name = trim($row['name'] ?? $row['School Name'] ?? $row[0] ?? '');
        $type = trim($row['type'] ?? $row['Type'] ?? $row[1] ?? 'Secondary');
        $region = trim($row['region'] ?? $row['Region'] ?? $row[2] ?? '');
        $hmName = trim($row['headmaster_name'] ?? $row[3] ?? '');
        $hmPhone = trim($row['headmaster_phone'] ?? $row[4] ?? '');

        if (empty($name)) {
            $skippedCount++;
            continue;
        }

        $schoolId = generateUuidV4();
        $stmt->execute([$schoolId, $name, $type, $region]);

        if (!empty($hmName) && !empty($hmPhone)) {
            $userId = generateUuidV4();
            $pwd = password_hash('ShuleAdmin@2026', PASSWORD_BCRYPT);
            $userStmt->execute([$userId, $schoolId, $hmPhone, $pwd, $hmName]);
        }

        $successCount++;
    }

    echo json_encode([
        "success" => true,
        "message" => "Import completed.",
        "imported" => $successCount,
        "skipped" => $skippedCount
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error: " . $e->getMessage()]);
}
?>
