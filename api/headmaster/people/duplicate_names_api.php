<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$schoolId = $_SESSION['school_id'] ?? $_GET['school_id'] ?? null;

if (empty($schoolId) && !empty($_SESSION['user_id'])) {
    $uStmt = $conn->prepare("SELECT school_id FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$_SESSION['user_id']]);
    $schoolId = $uStmt->fetchColumn() ?: null;
}

if (empty($schoolId)) {
    $sStmt = $conn->query("SELECT id FROM schools ORDER BY id ASC LIMIT 1");
    $schoolId = $sStmt->fetchColumn() ?: null;
}

if (empty($schoolId)) {
    echo json_encode([
        'success' => true,
        'role' => $_GET['role'] ?? 'all',
        'group_count' => 0,
        'groups' => []
    ]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$role = $_GET['role'] ?? 'all'; // 'teacher', 'student', or 'all'

try {
    if ($method === 'GET') {
        $checkName = trim($_GET['name'] ?? '');
        if ($checkName !== '') {
            $stmtSingle = $conn->prepare("
                SELECT id, user_code, full_name, role, gender, phone, email, status
                FROM users
                WHERE school_id = ? AND LOWER(TRIM(full_name)) = LOWER(TRIM(?))
            ");
            $stmtSingle->execute([$schoolId, $checkName]);
            $existingUsers = $stmtSingle->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'name'    => $checkName,
                'exists'  => count($existingUsers) > 0,
                'count'   => count($existingUsers),
                'users'   => $existingUsers
            ]);
            exit();
        }

        // Find all full names that appear more than once in this school
        $queryGroup = "
            SELECT LOWER(TRIM(full_name)) AS norm_name, full_name, COUNT(*) AS dup_count
            FROM users
            WHERE school_id = ?
        ";
        $params = [$schoolId];

        if ($role === 'teacher') {
            $queryGroup .= " AND role = 'teacher'";
        } else if ($role === 'student') {
            $queryGroup .= " AND role = 'student'";
        } else {
            $queryGroup .= " AND role IN ('teacher', 'student')";
        }

        $queryGroup .= " GROUP BY LOWER(TRIM(full_name)) HAVING COUNT(*) > 1 ORDER BY dup_count DESC, full_name ASC";

        $stmtG = $conn->prepare($queryGroup);
        $stmtG->execute($params);
        $nameGroups = $stmtG->fetchAll(PDO::FETCH_ASSOC);

        $resultGroups = [];

        $stmtDetails = $conn->prepare("
            SELECT u.id, u.user_code, u.full_name, u.role, u.gender, u.phone, u.email, u.department, u.status, u.created_at,
                   c.classroom_name, g.name AS grade_name
            FROM users u
            LEFT JOIN classrooms c ON (u.class_id = c.id OR u.class_id = CAST(c.id AS CHAR))
            LEFT JOIN grades g ON c.grade_id = g.id
            WHERE u.school_id = ? AND LOWER(TRIM(u.full_name)) = ?
            ORDER BY u.created_at ASC
        ");

        foreach ($nameGroups as $group) {
            $stmtDetails->execute([$schoolId, $group['norm_name']]);
            $users = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

            $resultGroups[] = [
                'full_name' => $group['full_name'],
                'count' => intval($group['dup_count']),
                'users' => $users
            ];
        }

        echo json_encode([
            'success' => true,
            'role' => $role,
            'group_count' => count($resultGroups),
            'groups' => $resultGroups
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Invalid method.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
