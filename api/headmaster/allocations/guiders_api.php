<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId && ($_SESSION['role'] ?? '') === 'super_admin') {
    $row = $conn->query("SELECT id FROM schools LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $schoolId = $row['id'] ?? null;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';
$year = $_GET['year'] ?? $input['year'] ?? date('Y');

try {
    // 1. GET: Fetch Class Guider Analytics & Classroom Roster
    if ($method === 'GET') {
        $levelId = intval($_GET['level_id'] ?? 0);
        $gradeId = intval($_GET['grade_id'] ?? 0);

        // Fetch all classroom streams for school & year
        $query = "
            SELECT c.id AS classroom_id, c.classroom_name, c.capacity, c.academic_year,
                   g.id AS grade_id, g.name AS grade_name, g.order_seq,
                   el.id AS level_id, el.name AS level_name, el.code AS level_code,
                   ct.teacher_id AS guider_id,
                   u.full_name AS guider_name, u.user_code AS guider_code, u.phone AS guider_phone, u.email AS guider_email
            FROM classrooms c
            JOIN grades g ON c.grade_id = g.id
            JOIN education_levels el ON g.level_id = el.id
            LEFT JOIN class_teachers ct ON (ct.class_stream_id = c.id AND ct.school_id = c.school_id AND ct.academic_year_id = c.academic_year)
            LEFT JOIN users u ON ct.teacher_id = u.id
            WHERE c.school_id = ? AND c.academic_year = ?
        ";

        $params = [$schoolId, $year];
        if ($levelId) {
            $query .= " AND el.id = ?";
            $params[] = $levelId;
        }
        if ($gradeId) {
            $query .= " AND g.id = ?";
            $params[] = $gradeId;
        }

        $query .= " ORDER BY el.id, g.order_seq, c.classroom_name";

        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $classrooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Compute Analytics
        $totalClassrooms = count($classrooms);
        $assignedCount = 0;
        $unassignedCount = 0;
        $unassignedRooms = [];

        foreach ($classrooms as $room) {
            if (!empty($room['guider_id'])) {
                $assignedCount++;
            } else {
                $unassignedCount++;
                $unassignedRooms[] = [
                    'classroom_id' => $room['classroom_id'],
                    'room_name'    => $room['grade_name'] . ' ' . $room['classroom_name'],
                    'level_code'   => $room['level_code']
                ];
            }
        }

        // Fetch List of Active Teachers for dropdown selection
        $stmtT = $conn->prepare("SELECT id, full_name, user_code, phone, department FROM users WHERE school_id = ? AND role IN ('teacher','tenant_admin') AND status = 'active' ORDER BY full_name");
        $stmtT->execute([$schoolId]);
        $teachers = $stmtT->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'analytics' => [
                'total_classrooms'  => $totalClassrooms,
                'assigned_count'    => $assignedCount,
                'unassigned_count'  => $unassignedCount,
                'unassigned_rooms'  => $unassignedRooms
            ],
            'classrooms' => $classrooms,
            'teachers'   => $teachers,
            'academic_year' => $year
        ]);
        exit();
    }

    // 2. POST: Assign or Change Class Guider
    if ($method === 'POST' && $action === 'assign_guider') {
        $classroomId = trim($input['classroom_id'] ?? '');
        $teacherId   = !empty($input['teacher_id']) ? trim($input['teacher_id']) : null;

        if (!$classroomId) {
            echo json_encode(['success' => false, 'message' => 'Classroom ID is required.']);
            exit();
        }

        // Fetch classroom name for clean response message
        $stmtRoom = $conn->prepare("SELECT c.classroom_name, g.name AS grade_name FROM classrooms c JOIN grades g ON c.grade_id = g.id WHERE c.id = ?");
        $stmtRoom->execute([$classroomId]);
        $roomInfo = $stmtRoom->fetch(PDO::FETCH_ASSOC);
        $roomLabel = $roomInfo ? ($roomInfo['grade_name'] . ' ' . $roomInfo['classroom_name']) : 'Classroom';

        if ($teacherId) {
            $stmt = $conn->prepare("
                INSERT INTO class_teachers (school_id, academic_year_id, class_stream_id, teacher_id)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id)
            ");
            $stmt->execute([$schoolId, $year, $classroomId, $teacherId]);

            // Fetch assigned teacher full name
            $stmtName = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
            $stmtName->execute([$teacherId]);
            $tName = $stmtName->fetchColumn() ?: 'Teacher';

            echo json_encode([
                'success' => true,
                'message' => "{$tName} is now assigned as Class Guider for {$roomLabel}."
            ]);
        } else {
            $stmt = $conn->prepare("DELETE FROM class_teachers WHERE school_id = ? AND academic_year_id = ? AND class_stream_id = ?");
            $stmt->execute([$schoolId, $year, $classroomId]);

            echo json_encode([
                'success' => true,
                'message' => "Class Guider unassigned from {$roomLabel}."
            ]);
        }
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
