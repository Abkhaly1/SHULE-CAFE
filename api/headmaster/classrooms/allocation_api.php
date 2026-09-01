<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId && ($_SESSION['role'] ?? '') === 'super_admin') {
    $row = $conn->query('SELECT id FROM schools LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $schoolId = $row['id'] ?? null;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? 'get_pool';
$year   = $_GET['year']   ?? $input['year']   ?? date('Y');

try {
    // GET: Unassigned student pool for a grade or entire school
    if ($method === 'GET' && $action === 'get_pool') {
        $gradeId = intval($_GET['grade_id'] ?? 0);
        $stmt = $conn->prepare("
            SELECT u.id, u.full_name, u.user_code, u.phone, u.email, u.gender
            FROM users u
            WHERE u.school_id=? AND u.role='student' AND u.status='active'
            AND (? = 0 OR u.grade_id=? OR u.grade_id IS NULL)
            AND u.id NOT IN (
                SELECT sca.student_id FROM student_classroom_allocations sca
                WHERE sca.school_id=? AND sca.academic_year=? AND sca.status='Active'
            )
            ORDER BY u.full_name ASC
        ");
        $stmt->execute([$schoolId, $gradeId, $gradeId, $schoolId, $year]);
        $pool = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'pool' => $pool, 'count' => count($pool)]);
        exit();
    }

    // GET: Roster of students inside a classroom
    if ($method === 'GET' && $action === 'get_roster') {
        $classroomId = intval($_GET['classroom_id'] ?? 0);
        $stmt = $conn->prepare("
            SELECT u.id, u.full_name, u.user_code, u.gender, sca.id AS allocation_id, sca.status
            FROM student_classroom_allocations sca
            JOIN users u ON sca.student_id=u.id
            WHERE sca.school_id=? AND sca.classroom_id=? AND sca.academic_year=? AND sca.status='Active'
            ORDER BY u.full_name ASC
        ");
        $stmt->execute([$schoolId, $classroomId, $year]);
        $roster = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $classroom = $conn->query("SELECT id, classroom_name FROM classrooms WHERE id=$classroomId")->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'roster' => $roster, 'count' => count($roster), 'classroom' => $classroom]);
        exit();
    }

    // POST: Assign students to classroom (Unrestricted Capacity Allocation)
    if ($method === 'POST' && $action === 'assign') {
        $classroomId = intval($input['classroom_id'] ?? 0);
        $studentIds  = $input['student_ids'] ?? [];
        if (!$classroomId || empty($studentIds)) {
            echo json_encode(['success' => false, 'message' => 'classroom_id and student_ids are required.']);
            exit();
        }

        $classroom = $conn->query("SELECT classroom_name FROM classrooms WHERE id=$classroomId")->fetch(PDO::FETCH_ASSOC);
        $cName = $classroom['classroom_name'] ?? 'Classroom Stream';

        $conn->beginTransaction();
        $stmt = $conn->prepare("INSERT INTO student_classroom_allocations (school_id, academic_year, student_id, classroom_id, status) VALUES (?,?,?,?,'Active') ON DUPLICATE KEY UPDATE classroom_id=VALUES(classroom_id), status='Active', updated_at=NOW()");
        $assigned = 0;
        foreach ($studentIds as $sid) {
            $stmt->execute([$schoolId, $year, $sid, $classroomId]);
            $assigned++;
        }
        $conn->commit();
        echo json_encode(['success' => true, 'assigned' => $assigned, 'message' => "$assigned student(s) allocated to '$cName' successfully."]);
        exit();
    }

    // POST: Remove student from classroom
    if ($method === 'POST' && $action === 'remove') {
        $studentId   = $input['student_id']   ?? '';
        $classroomId = intval($input['classroom_id'] ?? 0);
        $conn->prepare("DELETE FROM student_classroom_allocations WHERE school_id=? AND academic_year=? AND student_id=? AND classroom_id=?")->execute([$schoolId, $year, $studentId, $classroomId]);
        echo json_encode(['success' => true, 'message' => 'Student removed from classroom.']);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
