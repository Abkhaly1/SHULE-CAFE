<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/db.php';
require_once 'TimetableEngine.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized."]);
    exit();
}

$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId && ($_SESSION['role'] ?? '') === 'super_admin') {
    $row = $conn->query("SELECT id FROM schools LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $schoolId = $row['id'] ?? null;
}

$input = json_decode(file_get_contents('php://input'), true);

$classStreamId = trim($input['class_stream_id'] ?? '');
$dayOfWeek     = trim($input['day_of_week'] ?? '');
$periodId      = intval($input['period_id'] ?? 0);
$subjectCode   = trim($input['subject_code'] ?? '');
$teacherId     = trim($input['teacher_id'] ?? '');
$academicYear  = trim($input['academic_year'] ?? date('Y'));
$action        = trim($input['action'] ?? 'save'); // 'save' or 'clear'

if (!$schoolId || !$classStreamId || !$dayOfWeek || !$periodId) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Class stream, day, and period are required."]);
    exit();
}

$engine = new TimetableEngine($conn);

try {
    if ($action === 'clear') {
        // Remove a specific slot
        $stmt = $conn->prepare("
            DELETE FROM class_timetables
            WHERE school_id = ? AND academic_year_id = ? AND class_stream_id = ? AND day_of_week = ? AND period_id = ?
        ");
        $stmt->execute([$schoolId, $academicYear, $classStreamId, $dayOfWeek, $periodId]);
        echo json_encode(["success" => true, "message" => "Period slot cleared."]);
        exit();
    }

    if (empty($subjectCode) || empty($teacherId)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Subject and teacher are required to save a slot."]);
        exit();
    }

    // Run through collision-detection engine
    $result = $engine->schedulePeriodSlot($schoolId, $academicYear, $dayOfWeek, $periodId, $classStreamId, $subjectCode, $teacherId);

    if (!$result['success']) {
        // Return 409 Conflict with full details
        http_response_code(409);
        echo json_encode([
            "success"       => false,
            "conflict_type" => $result['conflict_type'],
            "message"       => $result['message']
        ]);
        exit();
    }

    echo json_encode(["success" => true, "message" => $result['message']]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
