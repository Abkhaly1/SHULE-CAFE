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

$type = trim($input['type'] ?? '');
$name = trim($input['name'] ?? '');
$code = trim($input['code'] ?? '');
$level_code = trim($input['level_code'] ?? '');
$description = trim($input['description'] ?? '');

if (empty($type) || empty($name)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Type and Name are required."]);
    exit();
}

function generateUuidV4() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

try {
    $conn->beginTransaction();

    $id = generateUuidV4();
    $stmt = $conn->prepare("INSERT INTO academic_templates (id, type, name, code, level_code, description, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
    $stmt->execute([$id, $type, $name, $code, $level_code ?: null, $description]);

    // Real-Time Synchronization for New Subject Templates
    if ($type === 'subject' && $code) {
        $stmtSch = $conn->prepare("SELECT school_id FROM school_education_levels WHERE (level_code = ? OR ? = 'ALL') AND status = 'active'");
        $stmtSch->execute([$level_code, $level_code]);
        $activeSchools = $stmtSch->fetchAll(PDO::FETCH_COLUMN);

        $insApp = $conn->prepare("
            INSERT INTO school_approved_subjects (school_id, subject_code, subject_name, level_code, status)
            VALUES (?, ?, ?, ?, 'active')
            ON DUPLICATE KEY UPDATE subject_name = VALUES(subject_name), status = 'active'
        ");

        foreach ($activeSchools as $schId) {
            $insApp->execute([$schId, $code, $name, $level_code]);
        }
    }

    $conn->commit();

    echo json_encode(["success" => true, "message" => "Academic template created & real-time synchronized to active school portals.", "id" => $id]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error: " . $e->getMessage()]);
}
?>
