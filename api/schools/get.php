<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

// Auth Check for Super Admin or Tenant Admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

$id = $_GET['id'] ?? null;

if (empty($id)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "School ID is required."]);
    exit();
}

try {
    // Fetch school info with headmaster details and credential tracking
    $stmt = $conn->prepare("
        SELECT 
            s.id, 
            s.name, 
            s.type, 
            s.region, 
            s.status,
            s.created_at,
            u.id as headmaster_id,
            u.full_name as headmaster_name,
            u.email as headmaster_email,
            u.phone as headmaster_phone,
            u.temp_password as headmaster_temp_password,
            u.is_password_changed as headmaster_is_password_changed
        FROM schools s
        LEFT JOIN users u ON u.school_id = s.id AND u.role = 'tenant_admin'
        WHERE s.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $school = $stmt->fetch();

    if (!$school) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "School not found."]);
        exit();
    }

    // Counts
    $teacher_stmt = $conn->prepare("SELECT COUNT(id) as count FROM users WHERE school_id = ? AND role = 'teacher'");
    $teacher_stmt->execute([$id]);
    $total_teachers = (int)$teacher_stmt->fetch()['count'];

    $student_stmt = $conn->prepare("SELECT COUNT(id) as count FROM users WHERE school_id = ? AND role = 'student'");
    $student_stmt->execute([$id]);
    $total_students = (int)$student_stmt->fetch()['count'];

    $class_stmt = $conn->prepare("SELECT COUNT(id) as count FROM classes WHERE school_id = ?");
    $class_stmt->execute([$id]);
    $total_classes = (int)$class_stmt->fetch()['count'];

    $school['total_teachers'] = $total_teachers;
    $school['total_students'] = $total_students;
    $school['total_classes'] = $total_classes;

    echo json_encode(["success" => true, "data" => $school]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error: " . $e->getMessage()]);
}
?>
