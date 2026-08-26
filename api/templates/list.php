<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

$type = $_GET['type'] ?? null;
$levelCode = $_GET['level_code'] ?? null;

try {
    $where = [];
    $params = [];

    if ($type) {
        $where[] = "type = ?";
        $params[] = $type;
    }

    if ($levelCode) {
        $where[] = "level_code = ?";
        $params[] = $levelCode;
    }

    $sql = "SELECT * FROM academic_templates";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY type ASC, name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "data" => $templates]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error: " . $e->getMessage()]);
}
?>
