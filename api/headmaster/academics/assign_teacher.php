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
$subjectCode = trim($input['subject_code'] ?? '');
$teacherId = !empty($input['teacher_id']) ? trim($input['teacher_id']) : null;

$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId && $_SESSION['role'] === 'super_admin') {
    $firstSchool = $conn->query("SELECT id FROM schools LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $schoolId = $firstSchool['id'] ?? null;
}

if (empty($classStreamId) || empty($subjectCode) || !$schoolId) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Class stream and subject code are required."]);
    exit();
}

try {
    $stmt = $conn->prepare("
        INSERT INTO teacher_subject_assignments (school_id, academic_year_id, class_stream_id, subject_code, teacher_id) 
        VALUES (?, '" . date('Y') . "', ?, ?, ?) 
        ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id)
    ");
    $stmt->execute([$schoolId, $classStreamId, $subjectCode, $teacherId]);

    $isAssigned = !empty($teacherId);

    echo json_encode([
        "success" => true,
        "status" => $isAssigned ? 'assigned' : 'unassigned',
        "message" => $isAssigned ? "Subject teacher assigned successfully." : "Subject teacher unassigned."
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
