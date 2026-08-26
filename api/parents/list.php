<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

$school_id = $_SESSION['school_id'] ?? $_GET['school_id'] ?? null;

if (!$school_id && $_SESSION['role'] === 'super_admin') {
    $stmt = $conn->query("SELECT id FROM schools LIMIT 1");
    $school_id = $stmt->fetchColumn();
}

try {
    $search = trim($_GET['search'] ?? '');
    $page   = max(1, intval($_GET['page'] ?? 1));
    $limit  = max(10, min(500, intval($_GET['limit'] ?? 25)));
    $offset = ($page - 1) * $limit;

    $queryWhere = "WHERE role = 'parent'";
    $params = [];

    if ($school_id) {
        $queryWhere .= " AND school_id = :school_id";
        $params[':school_id'] = $school_id;
    }

    if ($search !== '') {
        $queryWhere .= " AND (full_name LIKE :search OR phone LIKE :search OR email LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    $stmtCnt = $conn->prepare("SELECT COUNT(*) FROM users $queryWhere");
    $stmtCnt->execute($params);
    $total = (int)$stmtCnt->fetchColumn();
    $totalPages = max(1, ceil($total / $limit));

    $query = "SELECT id, full_name, phone, email, status, created_at FROM users $queryWhere ORDER BY created_at DESC LIMIT $offset, $limit";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $parents,
        "pagination" => [
            "total" => $total,
            "page" => $page,
            "limit" => $limit,
            "total_pages" => $totalPages
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
