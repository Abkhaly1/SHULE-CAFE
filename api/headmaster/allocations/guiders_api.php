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
    // 1. GET: Fetch Class Guider Analytics & Classroom Roster (including Undivided Grades)
    if ($method === 'GET') {
        $levelId = intval($_GET['level_id'] ?? 0);
        $gradeId = intval($_GET['grade_id'] ?? 0);

        // Fetch active grades for this school's education levels
        $stmtAllGrades = $conn->prepare("
            SELECT g.id AS grade_id, g.name AS grade_name, g.order_seq,
                   el.id AS level_id, el.name AS level_name, el.code AS level_code
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
        $stmtAllGrades->execute([$schoolId]);
        $allSchoolGrades = $stmtAllGrades->fetchAll(PDO::FETCH_ASSOC);

        if (empty($allSchoolGrades)) {
            $stmtAllGrades = $conn->query("
                SELECT g.id AS grade_id, g.name AS grade_name, g.order_seq,
                       el.id AS level_id, el.name AS level_name, el.code AS level_code
                FROM grades g
                JOIN education_levels el ON g.level_id = el.id
                ORDER BY el.id, g.order_seq
            ");
            $allSchoolGrades = $stmtAllGrades->fetchAll(PDO::FETCH_ASSOC);
        }

        // Fetch existing classroom streams for school & year
        $stmtClassrooms = $conn->prepare("
            SELECT c.id AS classroom_id, c.classroom_name, c.capacity, c.academic_year,
                   g.id AS grade_id, g.name AS grade_name, g.order_seq,
                   el.id AS level_id, el.name AS level_name, el.code AS level_code,
                   ct.teacher_id AS guider_id,
                   u.full_name AS guider_name, u.user_code AS guider_code, u.phone AS guider_phone, u.email AS guider_email,
                   0 AS is_undivided
            FROM classrooms c
            JOIN grades g ON c.grade_id = g.id
            JOIN education_levels el ON g.level_id = el.id
            LEFT JOIN class_teachers ct ON (
                (ct.class_stream_id = CAST(c.id AS CHAR) OR ct.class_stream_id = c.classroom_name)
                AND ct.school_id = c.school_id 
                AND ct.academic_year_id = c.academic_year
            )
            LEFT JOIN users u ON ct.teacher_id = u.id
            WHERE c.school_id = ? AND c.academic_year = ?
            ORDER BY el.id, g.order_seq, c.classroom_name
        ");
        $stmtClassrooms->execute([$schoolId, $year]);
        $existingRooms = $stmtClassrooms->fetchAll(PDO::FETCH_ASSOC);

        $roomsByGrade = [];
        foreach ($existingRooms as $r) {
            $roomsByGrade[$r['grade_id']][] = $r;
        }

        $stmtGradeTeacher = $conn->prepare("
            SELECT ct.teacher_id AS guider_id,
                   u.full_name AS guider_name, u.user_code AS guider_code, u.phone AS guider_phone, u.email AS guider_email
            FROM class_teachers ct
            JOIN users u ON ct.teacher_id = u.id
            WHERE ct.school_id = ? AND ct.academic_year_id = ? AND ct.grade_id = ? 
              AND (ct.class_stream_id = 'GRADE_WIDE' OR ct.class_stream_id IS NULL OR ct.class_stream_id = '')
            LIMIT 1
        ");

        $classrooms = [];
        foreach ($allSchoolGrades as $g) {
            $gid = $g['grade_id'];
            if ($gradeId && $gid !== $gradeId) continue;
            if ($levelId && $g['level_id'] !== $levelId) continue;

            if (!empty($roomsByGrade[$gid])) {
                foreach ($roomsByGrade[$gid] as $rm) {
                    $classrooms[] = $rm;
                }
            } else {
                // Undivided Grade Cohort (no classrooms divided yet)
                $stmtGradeTeacher->execute([$schoolId, $year, $gid]);
                $gt = $stmtGradeTeacher->fetch(PDO::FETCH_ASSOC);

                $classrooms[] = [
                    'classroom_id' => 0,
                    'is_undivided' => 1,
                    'classroom_name' => 'Whole Grade (Undivided Cohort)',
                    'capacity' => 0,
                    'academic_year' => $year,
                    'grade_id' => $gid,
                    'grade_name' => $g['grade_name'],
                    'order_seq' => $g['order_seq'],
                    'level_id' => $g['level_id'],
                    'level_name' => $g['level_name'],
                    'level_code' => $g['level_code'],
                    'guider_id' => $gt ? $gt['guider_id'] : null,
                    'guider_name' => $gt ? $gt['guider_name'] : null,
                    'guider_code' => $gt ? $gt['guider_code'] : null,
                    'guider_phone' => $gt ? $gt['guider_phone'] : null,
                    'guider_email' => $gt ? $gt['guider_email'] : null
                ];
            }
        }

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
                $label = !empty($room['is_undivided']) 
                    ? ($room['grade_name'] . ' (Undivided Grade Cohort)')
                    : ($room['grade_name'] . ' ' . $room['classroom_name']);
                $unassignedRooms[] = [
                    'classroom_id' => $room['classroom_id'],
                    'grade_id'     => $room['grade_id'],
                    'is_undivided' => $room['is_undivided'] ?? 0,
                    'room_name'    => $label,
                    'level_code'   => $room['level_code']
                ];
            }
        }

        // Fetch List of Active Teachers and Headmasters for selection
        $stmtT = $conn->prepare("SELECT id, full_name, user_code, phone, department, role FROM users WHERE school_id = ? AND role IN ('teacher', 'headmaster', 'tenant_admin') AND status = 'active' ORDER BY full_name");
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

    // 2. POST: Assign or Change Class Guider / Grade Teacher
    if ($method === 'POST' && $action === 'assign_guider') {
        $classroomId = intval($input['classroom_id'] ?? 0);
        $gradeId     = intval($input['grade_id'] ?? 0);
        $isUndivided = !empty($input['is_undivided']) || ($classroomId === 0 && $gradeId > 0);
        $teacherId   = !empty($input['teacher_id']) ? trim($input['teacher_id']) : null;

        if ($isUndivided && $gradeId > 0) {
            // Assign / Unassign Grade Teacher for an undivided grade cohort
            $stmtGName = $conn->prepare("SELECT name FROM grades WHERE id = ?");
            $stmtGName->execute([$gradeId]);
            $gName = $stmtGName->fetchColumn() ?: "Grade #$gradeId";

            if ($teacherId) {
                $stmt = $conn->prepare("
                    INSERT INTO class_teachers (school_id, academic_year_id, grade_id, class_stream_id, teacher_id)
                    VALUES (?, ?, ?, 'GRADE_WIDE', ?)
                    ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id)
                ");
                $stmt->execute([$schoolId, $year, $gradeId, $teacherId]);

                $stmtName = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
                $stmtName->execute([$teacherId]);
                $tName = $stmtName->fetchColumn() ?: 'Teacher';

                echo json_encode([
                    'success' => true,
                    'message' => "{$tName} is now assigned as Grade Teacher managing {$gName} (Undivided Cohort)."
                ]);
            } else {
                $stmt = $conn->prepare("DELETE FROM class_teachers WHERE school_id = ? AND academic_year_id = ? AND grade_id = ? AND (class_stream_id = 'GRADE_WIDE' OR class_stream_id IS NULL OR class_stream_id = '')");
                $stmt->execute([$schoolId, $year, $gradeId]);

                echo json_encode([
                    'success' => true,
                    'message' => "Grade Teacher unassigned from {$gName}."
                ]);
            }
            exit();
        }

        if (!$classroomId) {
            echo json_encode(['success' => false, 'message' => 'Classroom ID or Grade ID is required.']);
            exit();
        }

        // Classroom stream assignment
        $stmtRoom = $conn->prepare("SELECT c.classroom_name, c.grade_id, g.name AS grade_name FROM classrooms c JOIN grades g ON c.grade_id = g.id WHERE c.id = ?");
        $stmtRoom->execute([$classroomId]);
        $roomInfo = $stmtRoom->fetch(PDO::FETCH_ASSOC);
        $roomLabel = $roomInfo ? ($roomInfo['grade_name'] . ' ' . $roomInfo['classroom_name']) : 'Classroom';
        $rGradeId = $roomInfo ? intval($roomInfo['grade_id']) : $gradeId;

        if ($teacherId) {
            $stmt = $conn->prepare("
                INSERT INTO class_teachers (school_id, academic_year_id, grade_id, class_stream_id, teacher_id)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id), grade_id = VALUES(grade_id)
            ");
            $stmt->execute([$schoolId, $year, $rGradeId, strval($classroomId), $teacherId]);

            $stmtName = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
            $stmtName->execute([$teacherId]);
            $tName = $stmtName->fetchColumn() ?: 'Teacher';

            echo json_encode([
                'success' => true,
                'message' => "{$tName} is now assigned as Class Guider for {$roomLabel}."
            ]);
        } else {
            $stmt = $conn->prepare("DELETE FROM class_teachers WHERE school_id = ? AND academic_year_id = ? AND (class_stream_id = ? OR class_stream_id = ?)");
            $stmt->execute([$schoolId, $year, strval($classroomId), $roomInfo ? $roomInfo['classroom_name'] : '']);

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
