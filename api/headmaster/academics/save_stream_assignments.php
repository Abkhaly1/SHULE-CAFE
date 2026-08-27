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
$formMasterId = !empty($input['form_master_id']) ? trim($input['form_master_id']) : null;
$subjectTeacherMappings = $input['subject_teachers'] ?? []; // Map of subject_code => array of teacher_ids

$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId && $_SESSION['role'] === 'super_admin') {
    $firstSchool = $conn->query("SELECT id FROM schools LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $schoolId = $firstSchool['id'] ?? null;
}

if (empty($classStreamId) || !$schoolId) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Class stream ID and school context are required."]);
    exit();
}

try {
    $conn->beginTransaction();

    // 1. Update Class Teacher (Form Master)
    if (!empty($formMasterId)) {
        $stmtFm = $conn->prepare("
            INSERT INTO class_teachers (school_id, academic_year_id, class_stream_id, teacher_id) 
            VALUES (?, '" . date('Y') . "', ?, ?) 
            ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id)
        ");
        $stmtFm->execute([$schoolId, $classStreamId, $formMasterId]);
    } else {
        $stmtDelFm = $conn->prepare("DELETE FROM class_teachers WHERE school_id = ? AND academic_year_id = '" . date('Y') . "' AND class_stream_id = ?");
        $stmtDelFm->execute([$schoolId, $classStreamId]);
    }

    // 2. Process Subject Teacher Assignments
    if (!empty($subjectTeacherMappings) && is_array($subjectTeacherMappings)) {
        $clearStmt = $conn->prepare("DELETE FROM teacher_subject_assignments WHERE school_id = ? AND academic_year_id = '" . date('Y') . "' AND class_stream_id = ? AND subject_code = ?");
        $insertStmt = $conn->prepare("INSERT INTO teacher_subject_assignments (school_id, academic_year_id, class_stream_id, subject_code, teacher_id) VALUES (?, '" . date('Y') . "', ?, ?, ?)");

        foreach ($subjectTeacherMappings as $subCode => $tIds) {
            // Clear existing for this subject in stream
            $clearStmt->execute([$schoolId, $classStreamId, $subCode]);

            if (is_array($tIds)) {
                foreach ($tIds as $tId) {
                    if (!empty($tId)) {
                        $insertStmt->execute([$schoolId, $classStreamId, $subCode, $tId]);
                    }
                }
            } else if (!empty($tIds)) {
                $insertStmt->execute([$schoolId, $classStreamId, $subCode, $tIds]);
            }
        }
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Class stream setup and teacher assignments saved successfully."
    ]);

} catch (PDOException $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
