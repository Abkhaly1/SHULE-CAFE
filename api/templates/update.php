<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$id = trim($input['id'] ?? '');
$name = trim($input['name'] ?? '');
$code = trim($input['code'] ?? '');
$level_code = trim($input['level_code'] ?? '');
$description = trim($input['description'] ?? '');
$status = trim($input['status'] ?? 'active');

if (empty($id) || empty($name)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "ID and Name are required."]);
    exit();
}

try {
    $existingStmt = $conn->prepare("SELECT type, details FROM academic_templates WHERE id = ?");
    $existingStmt->execute([$id]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

    $detailsArr = [];
    if (!empty($existing['details'])) {
        $detailsArr = json_decode($existing['details'], true) ?: [];
    }

    if (isset($input['details'])) {
        if (is_array($input['details'])) {
            $detailsArr = array_merge($detailsArr, $input['details']);
        } elseif (is_string($input['details'])) {
            $parsed = json_decode($input['details'], true);
            if (is_array($parsed)) $detailsArr = array_merge($detailsArr, $parsed);
        }
    }

    if (isset($input['course_code'])) $detailsArr['course_code'] = trim($input['course_code']);
    if (isset($input['abbr'])) $detailsArr['abbr'] = trim($input['abbr']);
    if (isset($input['category'])) $detailsArr['category'] = trim($input['category']);
    $detailsJson = !empty($detailsArr) ? json_encode($detailsArr) : null;

    $stmt = $conn->prepare("UPDATE academic_templates SET name = ?, code = ?, level_code = ?, description = ?, details = ?, status = ? WHERE id = ?");
    $stmt->execute([$name, $code, $level_code ?: null, $description, $detailsJson, $status, $id]);

    // If subject, synchronize update to school_approved_subjects
    if (($existing['type'] ?? '') === 'subject' && $code) {
        $updApp = $conn->prepare("UPDATE school_approved_subjects SET subject_name = ?, status = ? WHERE subject_code = ?");
        $updApp->execute([$name, $status, $code]);
    }

    echo json_encode(["success" => true, "message" => "Academic template updated successfully."]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error: " . $e->getMessage()]);
}
?>
