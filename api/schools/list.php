<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'super_admin';

if (empty($userId) && empty($_SESSION['user_id'])) {
    // Graceful fallback for authenticated platform view
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

    $whereClause = "";
    $params = [];

    // Enforce Regional Jurisdiction Lock
    if ($role === 'regional_officer' && !empty($userId)) {
        $rStmt = $conn->prepare("SELECT department FROM users WHERE id = ? LIMIT 1");
        $rStmt->execute([$userId]);
        $assignedRegion = trim($rStmt->fetchColumn() ?: '');
        if (!empty($assignedRegion)) {
            $whereClause = "WHERE UPPER(s.region) = UPPER(?)";
            $params[] = $assignedRegion;
        }
    }

    $stmt = $conn->prepare("
        SELECT 
            s.id, 
            s.name, 
            s.type, 
            s.region, 
            s.status,
            u.full_name as headmaster_name,
            u.phone as headmaster_phone
        FROM schools s
        LEFT JOIN users u ON u.school_id = s.id AND u.role IN ('headmaster', 'tenant_admin')
        $whereClause
        ORDER BY s.created_at DESC
    ");
    $stmt->execute($params);
    $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(["success" => true, "data" => $schools]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error: " . $e->getMessage()]);
}
?>
