<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'] ?? null;
$role   = $_SESSION['role'] ?? '';

if (empty($userId)) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT 
            u.id AS student_id,
            u.full_name AS student_name,
            u.user_code,
            u.gender,
            COALESCE(c.classroom_name, 'Unassigned') AS classroom_name,
            COALESCE(g.name, 'Secondary') AS grade_name,
            ps.created_at AS linked_at
        FROM parent_student ps
        JOIN users u ON ps.student_id = u.id
        LEFT JOIN student_classroom_allocations sca ON sca.student_id = u.id AND sca.status = 'Active'
        LEFT JOIN classrooms c ON sca.classroom_id = c.id
        LEFT JOIN grades g ON c.grade_id = g.id
        WHERE ps.parent_id = ?
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$userId]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If parent is not directly mapped in parent_student, search by user email/phone match
    if (empty($children)) {
        $uStmt = $conn->prepare("SELECT email, phone, school_id FROM users WHERE id = ? LIMIT 1");
        $uStmt->execute([$userId]);
        $parentUser = $uStmt->fetch(PDO::FETCH_ASSOC);

        if ($parentUser) {
            $matchStmt = $conn->prepare("
                SELECT 
                    u.id AS student_id,
                    u.full_name AS student_name,
                    u.user_code,
                    u.gender,
                    COALESCE(c.classroom_name, 'Unassigned') AS classroom_name,
                    COALESCE(g.name, 'Secondary') AS grade_name,
                    pp.created_at AS linked_at
                FROM parent_profiles pp
                JOIN users u ON pp.student_id = u.id
                LEFT JOIN student_classroom_allocations sca ON sca.student_id = u.id AND sca.status = 'Active'
                LEFT JOIN classrooms c ON sca.classroom_id = c.id
                LEFT JOIN grades g ON c.grade_id = g.id
                WHERE (pp.guardian_phone = ? OR pp.guardian_email = ?) AND u.school_id = ?
            ");
            $matchStmt->execute([$parentUser['phone'], $parentUser['email'], $parentUser['school_id']]);
            $children = $matchStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    echo json_encode([
        "success" => true,
        "children" => $children
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error: " . $e->getMessage()]);
}
?>
