<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$classStreamId = trim($input['class_stream_id'] ?? '');
$teacherId = !empty($input['teacher_id']) ? trim($input['teacher_id']) : null;

$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId && $_SESSION['role'] === 'super_admin') {
    $firstSchool = $conn->query("SELECT id FROM schools LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $schoolId = $firstSchool['id'] ?? null;
}

if (empty($classStreamId) || !$schoolId) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Class stream ID is required."]);
    exit();
}

try {
    if ($teacherId) {
        $stmt = $conn->prepare("
            INSERT INTO class_teachers (school_id, academic_year_id, class_stream_id, teacher_id) 
            VALUES (?, '" . date('Y') . "', ?, ?) 
            ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id)
        ");
        $stmt->execute([$schoolId, $classStreamId, $teacherId]);
        $msg = "Form Master assigned successfully.";
    } else {
        $stmt = $conn->prepare("DELETE FROM class_teachers WHERE school_id = ? AND academic_year_id = '" . date('Y') . "' AND class_stream_id = ?");
        $stmt->execute([$schoolId, $classStreamId]);
        $msg = "Form Master unassigned.";
    }

    echo json_encode(["success" => true, "message" => $msg]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
