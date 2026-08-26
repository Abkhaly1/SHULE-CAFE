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

$subjectId = $input['subject_id'] ?? null;
$subjectCode = $input['subject_code'] ?? null;
$status = trim($input['status'] ?? 'active');

$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId && $_SESSION['role'] === 'super_admin') {
    $firstSchool = $conn->query("SELECT id FROM schools LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $schoolId = $firstSchool['id'] ?? null;
}

if (!$subjectCode || !$schoolId) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Subject code and school ID are required."]);
    exit();
}

try {
    $stmt = $conn->prepare("UPDATE school_approved_subjects SET status = ? WHERE school_id = ? AND subject_code = ?");
    $stmt->execute([$status, $schoolId, $subjectCode]);

    echo json_encode([
        "success" => true,
        "message" => "Subject status updated to " . strtoupper($status) . "."
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
