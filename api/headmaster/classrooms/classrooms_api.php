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
$action = $_GET['action'] ?? $input['action'] ?? '';
$year = $_GET['year'] ?? $input['year'] ?? date('Y');

try {
    // GET: Return grades with their classrooms for the school
    if ($method === 'GET') {
        $stmtG = $conn->prepare("
            SELECT g.id, g.name AS grade_name, g.order_seq, el.name AS level_name, el.id AS level_id 
            FROM grades g 
            JOIN education_levels el ON g.level_id = el.id 
            JOIN school_education_levels sel ON (
                UPPER(REPLACE(el.name, '-', '')) = UPPER(REPLACE(sel.level_code, '-', ''))
                OR UPPER(el.code) = UPPER(sel.level_code)
                OR UPPER(sel.level_code) LIKE CONCAT('%', UPPER(el.name), '%')
            )
            WHERE sel.school_id = ? AND sel.status = 'active'
            ORDER BY el.id, g.order_seq
        ");
        $stmtG->execute([$schoolId]);
        $grades = $stmtG->fetchAll(PDO::FETCH_ASSOC);

        // Fallback if no school_education_levels configured yet
        if (empty($grades)) {
            $stmtG = $conn->query("SELECT g.id, g.name AS grade_name, g.order_seq, el.name AS level_name, el.id AS level_id FROM grades g JOIN education_levels el ON g.level_id = el.id ORDER BY el.id, g.order_seq");
            $grades = $stmtG->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmtC = $conn->prepare("
            SELECT c.id, c.grade_id, c.classroom_name, c.is_active, c.created_at,
            (SELECT COUNT(*) FROM student_classroom_allocations sca WHERE sca.classroom_id=c.id AND sca.academic_year=? AND sca.status='Active') AS enrolled_count
            FROM classrooms c 
            WHERE c.school_id=? AND c.academic_year=? 
            ORDER BY c.classroom_name ASC
        ");
        $stmtC->execute([$year, $schoolId, $year]);
        $classrooms = $stmtC->fetchAll(PDO::FETCH_ASSOC);

        $classroomMap = [];
        foreach ($classrooms as $c) {
            $classroomMap[$c['grade_id']][] = $c;
        }

        foreach ($grades as &$g) {
            $g['classrooms'] = $classroomMap[$g['id']] ?? [];
            $g['classroom_count'] = count($g['classrooms']);
        }

        echo json_encode(['success' => true, 'grades' => $grades, 'year' => $year]);
        exit();
    }

    // POST: Save classrooms batch for a grade (No capacity requirement)
    if ($method === 'POST' && $action === 'save_classrooms') {
        $gradeId = intval($input['grade_id'] ?? 0);
        $names = $input['classroom_names'] ?? [];

        if (!$gradeId || empty($names)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'grade_id and classroom_names are required.']);
            exit();
        }

        $conn->beginTransaction();
        $saved = 0; $skipped = 0;
        $stmtIns = $conn->prepare("INSERT INTO classrooms (school_id, academic_year, grade_id, classroom_name, capacity) VALUES (?,?,?,?,0) ON DUPLICATE KEY UPDATE is_active=1, updated_at=NOW()");

        foreach ($names as $i => $name) {
            $name = trim($name);
            if (empty($name)) { $skipped++; continue; }
            $stmtIns->execute([$schoolId, $year, $gradeId, $name]);
            $saved++;
        }
        $conn->commit();
        echo json_encode(['success' => true, 'saved' => $saved, 'skipped' => $skipped, 'message' => "$saved classroom stream(s) saved successfully."]);
        exit();
    }

    // POST: Delete a classroom (Layer 3 Safety Valve)
    if ($method === 'POST' && $action === 'delete_classroom') {
        $classroomId = intval($input['classroom_id'] ?? 0);
        if (!$classroomId) {
            echo json_encode(['success' => false, 'message' => 'classroom_id is required.']);
            exit();
        }

        // Fetch classroom name
        $stmtC = $conn->prepare("SELECT classroom_name FROM classrooms WHERE id=? AND school_id=?");
        $stmtC->execute([$classroomId, $schoolId]);
        $room = $stmtC->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            echo json_encode(['success' => false, 'message' => 'Classroom not found.']);
            exit();
        }

        $cName = $room['classroom_name'];

        // 1. Scan active student allocations
        $stmtStu = $conn->prepare("SELECT COUNT(*) FROM student_classroom_allocations WHERE classroom_id=? AND status='Active'");
        $stmtStu->execute([$classroomId]);
        $studentCount = intval($stmtStu->fetchColumn());

        // 2. Scan active timetable slots
        $stmtTt = $conn->prepare("SELECT COUNT(*) FROM class_timetables WHERE school_id=? AND class_stream_id=?");
        $stmtTt->execute([$schoolId, $cName]);
        $timetableCount = intval($stmtTt->fetchColumn());

        // 3. Block deletion if any dependent records exist
        if ($studentCount > 0 || $timetableCount > 0) {
            $reasons = [];
            if ($studentCount > 0) $reasons[] = "$studentCount active student(s)";
            if ($timetableCount > 0) $reasons[] = "$timetableCount timetable period(s)";
            $reasonStr = implode(" and ", $reasons);

            echo json_encode([
                'success' => false,
                'message' => "Cannot delete classroom '$cName'. It currently contains $reasonStr. Please reallocate students and clear timetables first."
            ]);
            exit();
        }

        // 4. Safe deletion
        $conn->prepare("DELETE FROM classrooms WHERE id=? AND school_id=?")->execute([$classroomId, $schoolId]);
        echo json_encode(['success' => true, 'message' => "Classroom '$cName' deleted successfully."]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
