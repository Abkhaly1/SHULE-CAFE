<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

$teacher_id = $_GET['id'] ?? null;
if (!$teacher_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Teacher ID is required."]);
    exit();
}

try {
    // 1. Fetch Teacher Info
    $stmtT = $conn->prepare("SELECT id, user_code, full_name, gender, email, phone, department, status, created_at FROM users WHERE id = ?");
    $stmtT->execute([$teacher_id]);
    $teacher = $stmtT->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Teacher not found."]);
        exit();
    }

    // 2. Fetch Class Guider (Form Master / Class Teacher) Responsibility
    $stmtCM = $conn->prepare("
        SELECT c.classroom_name, g.name AS grade_name
        FROM class_teachers ct
        JOIN classrooms c ON ct.class_stream_id = c.id
        JOIN grades g ON c.grade_id = g.id
        WHERE ct.teacher_id = ?
        LIMIT 1
    ");
    $stmtCM->execute([$teacher_id]);
    $classTeacherRole = $stmtCM->fetch(PDO::FETCH_ASSOC);

    // 3. Fetch Master Subject Qualifications (Step 2 Data)
    $stmtQual = $conn->prepare("
        SELECT tsq.subject_id, s.code AS subject_code, s.name AS subject_name, COALESCE(sas.level_code, s.level_type, 'O-LEVEL') AS level_code
        FROM teacher_subject_qualifications tsq
        JOIN subjects s ON tsq.subject_id = s.id
        LEFT JOIN school_approved_subjects sas ON (sas.school_id = s.school_id AND sas.subject_code = s.code)
        WHERE tsq.teacher_id = ?
        ORDER BY level_code ASC, s.name ASC
    ");
    $stmtQual->execute([$teacher_id]);
    $qualifications = $stmtQual->fetchAll(PDO::FETCH_ASSOC);

    // 4. Fetch Physical Classroom Stream Assignments
    $stmtAssign = $conn->prepare("
        SELECT class_name, subject_code, subject_name, level_code
        FROM (
            SELECT c.classroom_name AS class_name, s.code AS subject_code, s.name AS subject_name, COALESCE(sas.level_code, s.level_type, 'O-LEVEL') AS level_code
            FROM teacher_classroom_assignments tca
            JOIN classrooms c ON tca.classroom_id = c.id
            JOIN subjects s ON tca.subject_id = s.id
            LEFT JOIN school_approved_subjects sas ON (sas.school_id = c.school_id AND sas.subject_code = s.code)
            WHERE tca.teacher_id = :tid

            UNION DISTINCT

            SELECT tsa.class_stream_id AS class_name, tsa.subject_code, COALESCE(sas.subject_name, s.name, tsa.subject_code) AS subject_name, COALESCE(sas.level_code, 'O-LEVEL') AS level_code
            FROM teacher_subject_assignments tsa
            LEFT JOIN subjects s ON (tsa.school_id = s.school_id AND tsa.subject_code = s.code)
            LEFT JOIN school_approved_subjects sas ON (tsa.school_id = sas.school_id AND tsa.subject_code = sas.subject_code)
            WHERE tsa.teacher_id = :tid
        ) AS combined
        ORDER BY class_name ASC, subject_name ASC
    ");
    $stmtAssign->execute([':tid' => $teacher_id]);
    $streamAssignments = $stmtAssign->fetchAll(PDO::FETCH_ASSOC);

    // Build unique class and subject summary lists
    $allSubjectNames = array_unique(array_merge(
        array_column($qualifications, 'subject_name'),
        array_column($streamAssignments, 'subject_name')
    ));
    $allClassNames = array_unique(array_column($streamAssignments, 'class_name'));

    echo json_encode([
        "success" => true,
        "teacher" => $teacher,
        "class_teacher_of" => $classTeacherRole ? ($classTeacherRole['grade_name'] . ' ' . $classTeacherRole['classroom_name']) : 'None (Subject Teacher Only)',
        "assigned_classes_count" => count($allClassNames),
        "assigned_subjects_summary" => implode(', ', $allSubjectNames) ?: 'None Assigned',
        "qualifications" => $qualifications,
        "assignments" => $streamAssignments
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
