<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) { http_response_code(403); echo json_encode(["success"=>false,"message"=>"Unauthorized."]); exit(); }

$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId && ($_SESSION['role'] ?? '') === 'super_admin') {
    $row = $conn->query("SELECT id FROM schools LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $schoolId = $row['id'] ?? null;
}

$input      = json_decode(file_get_contents('php://input'), true);
$action     = $input['action']          ?? 'get';         // get | enroll | unenroll
$studentId  = $input['student_id']      ?? '';
$streamId   = intval($input['stream_id']  ?? 0);
$subjectCode = trim($input['subject_code'] ?? '');
$year       = $input['academic_year']   ?? date('Y');

try {
    if ($action === 'get') {
        // Return enrolled subjects for a specific student in their stream
        if (!$studentId) { echo json_encode(["success"=>false,"message"=>"student_id required."]); exit(); }

        // All stream subjects
        $stmtAll = $conn->prepare("
            SELECT ss.subject_code, ss.subject_name, ss.is_core
            FROM stream_subjects ss
            WHERE ss.school_id=? AND ss.class_stream_id=? AND ss.academic_year_id=?
            ORDER BY ss.is_core DESC, ss.subject_name ASC
        ");
        $stmtAll->execute([$schoolId, $streamId, $year]);
        $allSubjects = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

        // Which ones is this student enrolled in
        $stmtEnr = $conn->prepare("
            SELECT subject_code FROM student_subject_enrollments
            WHERE school_id=? AND student_id=? AND class_stream_id=? AND academic_year_id=?
        ");
        $stmtEnr->execute([$schoolId, $studentId, $streamId, $year]);
        $enrolled = array_column($stmtEnr->fetchAll(PDO::FETCH_ASSOC), 'subject_code');

        $result = array_map(fn($s) => array_merge($s, ['enrolled' => in_array($s['subject_code'], $enrolled)]), $allSubjects);
        echo json_encode(["success"=>true,"subjects"=>$result,"enrolled_count"=>count($enrolled)]);
        exit();
    }

    if ($action === 'enroll') {
        // Auto-enroll a student in ALL core subjects for a stream (on promotion/registration)
        if (!$studentId || !$streamId) { echo json_encode(["success"=>false,"message"=>"student_id and stream_id required."]); exit(); }

        $stmtCore = $conn->prepare("SELECT subject_code, subject_name FROM stream_subjects WHERE school_id=? AND class_stream_id=? AND is_core=1 AND academic_year_id=?");
        $stmtCore->execute([$schoolId, $streamId, $year]);
        $coreSubjects = $stmtCore->fetchAll(PDO::FETCH_ASSOC);

        $stmtIns = $conn->prepare("INSERT IGNORE INTO student_subject_enrollments (school_id, academic_year_id, student_id, class_stream_id, subject_code, subject_name) VALUES (?,?,?,?,?,?)");
        $count = 0;
        foreach ($coreSubjects as $sub) {
            $stmtIns->execute([$schoolId, $year, $studentId, $streamId, $sub['subject_code'], $sub['subject_name']]);
            $count++;
        }
        echo json_encode(["success"=>true,"message"=>"Student auto-enrolled in $count core subjects."]);
        exit();
    }

    if ($action === 'toggle') {
        // Toggle individual elective subject for a student
        if (!$studentId || !$streamId || !$subjectCode) { echo json_encode(["success"=>false,"message"=>"Missing required fields."]); exit(); }

        $existing = $conn->prepare("SELECT id FROM student_subject_enrollments WHERE school_id=? AND academic_year_id=? AND student_id=? AND class_stream_id=? AND subject_code=?");
        $existing->execute([$schoolId, $year, $studentId, $streamId, $subjectCode]);

        if ($existing->fetch()) {
            $conn->prepare("DELETE FROM student_subject_enrollments WHERE school_id=? AND academic_year_id=? AND student_id=? AND class_stream_id=? AND subject_code=?")
                 ->execute([$schoolId, $year, $studentId, $streamId, $subjectCode]);
            echo json_encode(["success"=>true,"status"=>"unenrolled","message"=>"Student unenrolled from $subjectCode."]);
        } else {
            $subName = $conn->prepare("SELECT subject_name FROM stream_subjects WHERE school_id=? AND class_stream_id=? AND subject_code=?");
            $subName->execute([$schoolId, $streamId, $subjectCode]);
            $name = $subName->fetchColumn() ?: $subjectCode;
            $conn->prepare("INSERT IGNORE INTO student_subject_enrollments (school_id, academic_year_id, student_id, class_stream_id, subject_code, subject_name) VALUES (?,?,?,?,?,?)")
                 ->execute([$schoolId, $year, $studentId, $streamId, $subjectCode, $name]);
            echo json_encode(["success"=>true,"status"=>"enrolled","message"=>"Student enrolled in $subjectCode."]);
        }
        exit();
    }

    echo json_encode(["success"=>false,"message"=>"Unknown action '$action'."]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success"=>false,"message"=>$e->getMessage()]);
}
?>
