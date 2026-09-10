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
        $stmtClass = $conn->prepare("SELECT id, classroom_name, grade_id FROM classrooms WHERE id = ? AND school_id = ? LIMIT 1");
        $stmtClass->execute([$classroomId, $schoolId]);
        $classroom = $stmtClass->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'roster' => $roster,
            'count' => count($roster),
            'classroom' => $classroom
        ]);
        exit();
    }

    // POST: Assign students to classroom (Unrestricted Capacity Allocation with User Sync)
    if ($method === 'POST' && $action === 'assign') {
        $classroomId = intval($input['classroom_id'] ?? 0);
        $studentIds  = $input['student_ids'] ?? [];

        if (!$classroomId || empty($studentIds)) {
            echo json_encode(['success' => false, 'message' => 'Classroom and students are required.']);
            exit();
        }

        $stmtClass = $conn->prepare("SELECT classroom_name, grade_id FROM classrooms WHERE id = ? AND school_id = ? LIMIT 1");
        $stmtClass->execute([$classroomId, $schoolId]);
        $classroom = $stmtClass->fetch(PDO::FETCH_ASSOC);

        if (!$classroom) {
            echo json_encode(['success' => false, 'message' => 'Classroom stream not found.']);
            exit();
        }

        $cName = $classroom['classroom_name'] ?? 'Classroom Stream';
        $gradeId = intval($classroom['grade_id'] ?? 0);

        $conn->beginTransaction();
        $stmtAlloc = $conn->prepare("
            INSERT INTO student_classroom_allocations (school_id, academic_year, student_id, classroom_id, status, allocated_at, updated_at)
            VALUES (?, ?, ?, ?, 'Active', NOW(), NOW())
            ON DUPLICATE KEY UPDATE classroom_id = VALUES(classroom_id), status = 'Active', updated_at = NOW()
        ");

        $stmtSyncUser = $conn->prepare("UPDATE users SET class_id = ?, grade_id = IF(? > 0, ?, grade_id), updated_at = NOW() WHERE id = ? AND school_id = ?");

        $assigned = 0;
        foreach ($studentIds as $sid) {
            $stmtAlloc->execute([$schoolId, $year, $sid, $classroomId]);
            $stmtSyncUser->execute([$classroomId, $gradeId, $gradeId, $sid, $schoolId]);
            $assigned++;
        }
        $conn->commit();

        echo json_encode([
            'success' => true,
            'assigned' => $assigned,
            'message' => "Successfully allocated {$assigned} student(s) to '{$cName}'."
        ]);
        exit();
    }

    // POST: Remove student from classroom & sync user profile
    if ($method === 'POST' && $action === 'remove') {
        $studentId   = $input['student_id']   ?? '';
        $classroomId = intval($input['classroom_id'] ?? 0);

        $conn->beginTransaction();
        $conn->prepare("DELETE FROM student_classroom_allocations WHERE school_id = ? AND academic_year = ? AND student_id = ? AND classroom_id = ?")->execute([$schoolId, $year, $studentId, $classroomId]);
        $conn->prepare("UPDATE users SET class_id = NULL, updated_at = NOW() WHERE id = ? AND school_id = ? AND class_id = ?")->execute([$studentId, $schoolId, $classroomId]);
        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Student removed from classroom successfully.']);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
