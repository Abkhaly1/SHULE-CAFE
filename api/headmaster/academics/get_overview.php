<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

$schoolId = $_SESSION['school_id'] ?? null;

if (!$schoolId && $_SESSION['role'] === 'super_admin') {
    $firstSchool = $conn->query("SELECT id FROM schools LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $schoolId = $firstSchool['id'] ?? null;
}

if (!$schoolId) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "School context missing."]);
    exit();
}

try {
    // 1. Fetch Registered Education Levels (only active for this school)
    $stmtLevels = $conn->prepare("SELECT * FROM school_education_levels WHERE school_id = ? AND status = 'active' ORDER BY id ASC");
    $stmtLevels->execute([$schoolId]);
    $levels = $stmtLevels->fetchAll(PDO::FETCH_ASSOC);

    $activeCodes = array_column($levels, 'level_code');

    // 2. Fetch School Approved Subjects (filtered by active registered education levels)
    $stmtSubjects = $conn->prepare("
        SELECT sas.* FROM school_approved_subjects sas
        JOIN school_education_levels sel ON (sas.school_id = sel.school_id AND (sas.level_code = sel.level_code OR sas.level_code = 'ALL'))
        WHERE sas.school_id = ? AND sel.status = 'active' AND sas.status = 'active'
        ORDER BY sas.level_code ASC, sas.subject_name ASC
    ");
    $stmtSubjects->execute([$schoolId]);
    $subjects = $stmtSubjects->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch System Grades (FILTERED STRICTLY BY SCHOOL REGISTERED EDUCATION LEVELS)
    $stmtGrades = $conn->prepare("
        SELECT g.id, g.level_id, g.name AS grade_name, g.order_seq, el.name AS level_name, sel.level_code
        FROM grades g
        JOIN education_levels el ON g.level_id = el.id
        JOIN school_education_levels sel ON (sel.school_id = ? AND sel.status = 'active' AND (
            sel.level_code = el.code
            OR (sel.level_code = 'O-LEVEL' AND el.code = 'O-LEVEL')
            OR (sel.level_code = 'A-LEVEL' AND el.code = 'A-LEVEL')
            OR (sel.level_code = 'PRIM' AND (el.code = 'PRIMARY' OR el.code = 'PRIM'))
            OR (sel.level_code = 'NURSERY' AND el.code = 'NURSERY')
        ))
        ORDER BY el.id ASC, g.order_seq ASC
    ");
    $stmtGrades->execute([$schoolId]);
    $grades = $stmtGrades->fetchAll(PDO::FETCH_ASSOC);

    // 4. Fetch Dynamic Headmaster-Created Classrooms for Academic Year 2026
    $stmtStreams = $conn->prepare("
        SELECT c.id AS classroom_db_id, c.classroom_name AS id, c.classroom_name AS name, UPPER(el.name) AS level, sel.level_code, g.id AS grade_id, g.name AS grade_name, c.capacity
        FROM classrooms c
        JOIN grades g ON c.grade_id = g.id
        JOIN education_levels el ON g.level_id = el.id
        JOIN school_education_levels sel ON (sel.school_id = c.school_id AND sel.status = 'active' AND (
            sel.level_code = el.code
            OR (sel.level_code = 'O-LEVEL' AND el.code = 'O-LEVEL')
            OR (sel.level_code = 'A-LEVEL' AND el.code = 'A-LEVEL')
            OR (sel.level_code = 'PRIM' AND (el.code = 'PRIMARY' OR el.code = 'PRIM'))
            OR (sel.level_code = 'NURSERY' AND el.code = 'NURSERY')
        ))
        WHERE c.school_id = ? AND c.academic_year = '" . date('Y') . "'
        ORDER BY el.id, g.order_seq, c.classroom_name ASC
    ");
    $stmtStreams->execute([$schoolId]);
    $streams = $stmtStreams->fetchAll(PDO::FETCH_ASSOC);

    // 5. Fetch Class Teachers (Form Masters)
    $stmtClassTeachers = $conn->prepare("
        SELECT ct.*, u.full_name as teacher_name, u.user_code
        FROM class_teachers ct
        JOIN users u ON ct.teacher_id = u.id
        WHERE ct.school_id = ? AND ct.academic_year_id = '" . date('Y') . "'
    ");
    $stmtClassTeachers->execute([$schoolId]);
    $classTeachers = $stmtClassTeachers->fetchAll(PDO::FETCH_ASSOC);

    $classTeachersMap = [];
    foreach ($classTeachers as $ct) {
        $classTeachersMap[$ct['class_stream_id']] = $ct;
    }

    // 6. Fetch Teacher Subject Assignments
    $stmtAssign = $conn->prepare("
        SELECT 
            tsa.class_stream_id, 
            tsa.subject_code, 
            sas.subject_name,
            GROUP_CONCAT(tsa.teacher_id SEPARATOR ',') AS teacher_ids,
            GROUP_CONCAT(u.full_name SEPARATOR ', ') AS assigned_teachers
        FROM teacher_subject_assignments tsa
        LEFT JOIN users u ON tsa.teacher_id = u.id
        LEFT JOIN school_approved_subjects sas ON (tsa.school_id = sas.school_id AND tsa.subject_code = sas.subject_code)
        WHERE tsa.school_id = ? AND tsa.academic_year_id = '" . date('Y') . "'
        GROUP BY tsa.class_stream_id, tsa.subject_code
        ORDER BY tsa.class_stream_id ASC, sas.subject_name ASC
    ");
    $stmtAssign->execute([$schoolId]);
    $assignments = $stmtAssign->fetchAll(PDO::FETCH_ASSOC);

    // 7. Fetch Available Teachers for Dropdowns
    $stmtTeachers = $conn->prepare("
        SELECT id, full_name, user_code, department 
        FROM users 
        WHERE school_id = ? AND role IN ('teacher', 'tenant_admin', 'super_admin') AND status = 'active'
        ORDER BY full_name ASC
    ");
    $stmtTeachers->execute([$schoolId]);
    $teachers = $stmtTeachers->fetchAll(PDO::FETCH_ASSOC);

    // 8. Fetch Grade Subjects Curriculum for 2026
    $stmtGS = $conn->prepare("
        SELECT gs.id, gs.grade_id, gs.subject_code, gs.subject_name, gs.is_core
        FROM grade_subjects gs
        JOIN grades g ON gs.grade_id = g.id
        JOIN education_levels el ON g.level_id = el.id
        JOIN school_education_levels sel ON (sel.school_id = gs.school_id AND sel.status = 'active' AND (
            sel.level_code = el.code
            OR (sel.level_code = 'O-LEVEL' AND el.code = 'O-LEVEL')
            OR (sel.level_code = 'A-LEVEL' AND el.code = 'A-LEVEL')
            OR (sel.level_code = 'PRIM' AND (el.code = 'PRIMARY' OR el.code = 'PRIM'))
            OR (sel.level_code = 'NURSERY' AND el.code = 'NURSERY')
        ))
        WHERE gs.school_id = ? AND gs.academic_year = '" . date('Y') . "'
        ORDER BY g.id, gs.is_core DESC, gs.subject_name ASC
    ");
    $stmtGS->execute([$schoolId]);
    $allGradeSubjects = $stmtGS->fetchAll(PDO::FETCH_ASSOC);

    $gradeSubjectsMap = [];
    foreach ($allGradeSubjects as $gs) {
        $gradeSubjectsMap[$gs['grade_id']][] = $gs;
    }

    echo json_encode([
        "success" => true,
        "school_id" => $schoolId,
        "education_levels" => $levels,
        "grades" => $grades,
        "grade_subjects" => $gradeSubjectsMap,
        "approved_subjects" => $subjects,
        "class_streams" => $streams,
        "class_teachers" => $classTeachersMap,
        "teacher_assignments" => $assignments,
        "teachers" => $teachers
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
