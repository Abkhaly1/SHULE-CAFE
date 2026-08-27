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

$allowedRoles = ['super_admin', 'regional_officer', 'tenant_admin', 'school_admin', 'headmaster', 'teacher', 'student', 'parent', 'guardian'];

try {
    $stmt = $conn->prepare("INSERT INTO users (id, school_id, role, phone, password_hash, full_name, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");

    foreach ($rows as $idx => $row) {
        $fullName = trim($row['full_name'] ?? $row['Name'] ?? $row['name'] ?? $row[0] ?? '');
        $phone = trim($row['phone'] ?? $row['Phone'] ?? $row[1] ?? '');
        $role = trim($row['role'] ?? $row['Role'] ?? $row[2] ?? 'regional_officer');
        $password = trim($row['password'] ?? $row['Password'] ?? $row[3] ?? 'Pass1234');
        $schoolId = trim($row['school_id'] ?? $row[4] ?? '') ?: null;

        if (empty($fullName) || empty($phone)) {
            $skippedCount++;
            continue;
        }

        // Validate and normalize role
        if (!in_array($role, $allowedRoles)) {
            $role = 'regional_officer';
        } else if ($role === 'school_admin' || $role === 'headmaster') {
            $role = 'tenant_admin';
        }

        // Check if phone exists
        $checkStmt->execute([$phone]);
        if ($checkStmt->fetch()) {
            $skippedCount++;
            continue;
        }

        $id = generateUuidV4();
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $stmt->execute([$id, $schoolId, $role, $phone, $hashedPassword, $fullName]);
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
