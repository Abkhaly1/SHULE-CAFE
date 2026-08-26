<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

// Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access. Only Super Admins can delete school tenants."]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed."]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$schoolId = trim($input['school_id'] ?? $input['id'] ?? '');
$confirmName = trim($input['confirm_school_name'] ?? '');

if (!$schoolId) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "school_id parameter is required."]);
    exit();
}

try {
    // 1. Fetch school details
    $stmtS = $conn->prepare("SELECT id, name FROM schools WHERE id = ?");
    $stmtS->execute([$schoolId]);
    $school = $stmtS->fetch(PDO::FETCH_ASSOC);

    if (!$school) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "School tenant not found."]);
        exit();
    }

    $actualName = trim($school['name']);

    // SAFETY SHIELD: Require exact name match
    if (mb_strtolower($confirmName) !== mb_strtolower($actualName)) {
        http_response_code(422);
        echo json_encode([
            "success" => false,
            "requires_name_match" => true,
            "expected_name" => $actualName,
            "message" => "SAFETY PROTECTED: To permanently delete this school, you must type the exact school name ('{$actualName}')."
        ]);
        exit();
    }

    $conn->beginTransaction();

    // 2. Cascade delete tenant records
    $conn->prepare("DELETE FROM student_classroom_allocations WHERE school_id = ?")->execute([$schoolId]);
    $conn->prepare("DELETE FROM classrooms WHERE school_id = ?")->execute([$schoolId]);
    $conn->prepare("DELETE FROM school_approved_subjects WHERE school_id = ?")->execute([$schoolId]);
    $conn->prepare("DELETE FROM grade_subjects WHERE school_id = ?")->execute([$schoolId]);
    $conn->prepare("DELETE FROM school_education_levels WHERE school_id = ?")->execute([$schoolId]);
    $conn->prepare("DELETE FROM users WHERE school_id = ?")->execute([$schoolId]);
    $conn->prepare("DELETE FROM schools WHERE id = ?")->execute([$schoolId]);

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "School tenant '{$actualName}' and all associated records deleted permanently."
    ]);
    exit();

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
