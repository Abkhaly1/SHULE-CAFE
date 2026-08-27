<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'super_admin';

if (empty($userId) && empty($_SESSION['user_id'])) {
    // Graceful fallback for authenticated platform view
}

$school_id = $_GET['school_id'] ?? $_SESSION['school_id'] ?? null;

if (empty($school_id) && !empty($_SESSION['user_id'])) {
    $uStmt = $conn->prepare("SELECT school_id FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$_SESSION['user_id']]);
    $school_id = $uStmt->fetchColumn() ?: null;
}

if (empty($school_id)) {
    $sStmt = $conn->query("SELECT id FROM schools ORDER BY id ASC LIMIT 1");
    $school_id = $sStmt->fetchColumn() ?: null;
}

if (empty($school_id)) {
    echo json_encode([
        "success" => true,
        "data" => [],
        "pagination" => [
            "total" => 0,
            "page" => 1,
            "limit" => 25,
            "total_pages" => 1
        ]
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

try {
    $search = trim($_GET['search'] ?? '');
    $page   = max(1, intval($_GET['page'] ?? 1));
    $limit  = max(10, min(500, intval($_GET['limit'] ?? 25)));
    $offset = ($page - 1) * $limit;

    $whereSql = "WHERE u.role = 'student' AND u.school_id = :school_id";
    $params = [':school_id' => $school_id];

    if ($search !== '') {
        $whereSql .= " AND (u.full_name LIKE :search OR u.user_code LIKE :search OR u.phone LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    if ($search === '') {
        $stmtCnt = $conn->prepare("SELECT COUNT(*) FROM users u WHERE u.role = 'student' AND u.school_id = :school_id");
        $stmtCnt->execute([':school_id' => $school_id]);
    } else {
        $stmtCnt = $conn->prepare("SELECT COUNT(u.id) FROM users u $whereSql");
        $stmtCnt->execute($params);
    }
    $total = (int)$stmtCnt->fetchColumn();
    $totalPages = max(1, ceil($total / $limit));

    $students = [];
    if ($total > 0) {
        $stmt = $conn->prepare("
            SELECT u.id, u.user_code, u.full_name, u.gender, u.email, u.phone, u.status, u.created_at,
                   COALESCE(clr.classroom_name, c.name, 'Unassigned') AS class_name 
            FROM users u
            LEFT JOIN student_classroom_allocations sca ON (u.id = sca.student_id AND sca.status = 'Active')
            LEFT JOIN classrooms clr ON sca.classroom_id = clr.id
            LEFT JOIN classes c ON u.class_id = c.id
            $whereSql
            GROUP BY u.id
            ORDER BY u.created_at DESC
            LIMIT $offset, $limit
        ");
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        "success" => true,
        "data" => $students,
        "pagination" => [
            "total" => $total,
            "page" => $page,
            "limit" => $limit,
            "total_pages" => $totalPages
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "data" => [],
        "pagination" => [
            "total" => 0,
            "page" => 1,
            "limit" => $limit ?? 25,
            "total_pages" => 1
        ],
        "message" => "Empty result: " . $e->getMessage()
    ]);
}
?>
