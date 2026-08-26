<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId && ($_SESSION['role'] ?? '') === 'super_admin') {
    $firstSchool = $conn->query("SELECT id FROM schools LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $schoolId = $firstSchool['id'] ?? null;
}

$method    = $_SERVER['REQUEST_METHOD'];
$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$action    = $_GET['action']    ?? $input['action']    ?? 'get_student_subjects';
$studentId = $_GET['student_id'] ?? $input['student_id'] ?? '';
$year      = $_GET['year']       ?? $input['year']       ?? date('Y');

if (!$studentId) {
    echo json_encode(["success" => false, "message" => "student_id parameter is required."]);
    exit();
}

try {
    // 1. Fetch student info and active grade_id
    $stmtStu = $conn->prepare("
        SELECT u.id, u.full_name, u.user_code, u.grade_id, g.name AS grade_name, c.classroom_name
        FROM users u
        LEFT JOIN grades g ON u.grade_id = g.id
        LEFT JOIN student_classroom_allocations sca ON (u.id = sca.student_id AND sca.academic_year = ?)
        LEFT JOIN classrooms c ON sca.classroom_id = c.id
        WHERE u.id = ? AND u.school_id = ?
    ");
    $stmtStu->execute([$year, $studentId, $schoolId]);
    $student = $stmtStu->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(["success" => false, "message" => "Student not found."]);
        exit();
    }

    $gradeId = intval($student['grade_id'] ?? 0);

    // Fallback if grade_id not on user row: get from classroom allocation
    if (!$gradeId) {
        $stmtC = $conn->prepare("
            SELECT c.grade_id, g.name AS grade_name
            FROM student_classroom_allocations sca
            JOIN classrooms c ON sca.classroom_id = c.id
            JOIN grades g ON c.grade_id = g.id
            WHERE sca.student_id = ? AND sca.academic_year = ? AND sca.school_id = ?
            LIMIT 1
        ");
        $stmtC->execute([$studentId, $year, $schoolId]);
        $cRow = $stmtC->fetch(PDO::FETCH_ASSOC);
        if ($cRow) {
            $gradeId = intval($cRow['grade_id']);
            $student['grade_name'] = $cRow['grade_name'];
        }
    }

    // 2. Fetch Master Subjects assigned to this Grade for the academic year
    $stmtGS = $conn->prepare("
        SELECT subject_code, subject_name, is_core
        FROM grade_subjects
        WHERE school_id = ? AND academic_year = ? AND grade_id = ?
        ORDER BY is_core DESC, subject_name ASC
    ");
    $stmtGS->execute([$schoolId, $year, $gradeId]);
    $gradeSubjects = $stmtGS->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch custom student subject enrollments for this year
    $stmtSE = $conn->prepare("
        SELECT subject_code, subject_name, is_custom_elective
        FROM student_subject_enrollments
        WHERE school_id = ? AND academic_year_id = ? AND student_id = ?
    ");
    $stmtSE->execute([$schoolId, $year, $studentId]);
    $customEnrollments = $stmtSE->fetchAll(PDO::FETCH_ASSOC);

    $enrolledMap = [];
    foreach ($customEnrollments as $ce) {
        $enrolledMap[$ce['subject_code']] = $ce;
    }

    // GET: Return student's active subject list & Grade pool checklist
    if ($method === 'GET' && $action === 'get_student_subjects') {
        $activeSubjects = [];
        $gradeSubjectPool = [];

        foreach ($gradeSubjects as $gs) {
            $code   = $gs['subject_code'];
            $isCore = intval($gs['is_core']) === 1;

            // Core subjects are active by default unless explicitly modified
            // Electives are active if in enrolledMap
            $isEnrolled = $isCore || isset($enrolledMap[$code]);

            $item = [
                'subject_code'       => $code,
                'subject_name'       => $gs['subject_name'],
                'is_core'            => $isCore,
                'is_enrolled'        => $isEnrolled,
                'is_custom_elective' => isset($enrolledMap[$code]) ? intval($enrolledMap[$code]['is_custom_elective']) : 0
            ];

            $gradeSubjectPool[] = $item;
            if ($isEnrolled) {
                $activeSubjects[] = $item;
            }
        }

        echo json_encode([
            "success"            => true,
            "student"            => $student,
            "academic_year"      => $year,
            "active_subjects"    => $activeSubjects,
            "grade_subject_pool" => $gradeSubjectPool
        ]);
        exit();
    }

    // POST: Update student elective enrollments
    if ($method === 'POST' && $action === 'update_student_subjects') {
        $selectedSubjectCodes = $input['subject_codes'] ?? []; // array of subject_codes

        $conn->beginTransaction();

        // Clear existing custom electives for this student & year
        $stmtDel = $conn->prepare("
            DELETE FROM student_subject_enrollments 
            WHERE school_id = ? AND academic_year_id = ? AND student_id = ?
        ");
        $stmtDel->execute([$schoolId, $year, $studentId]);

        // Insert new custom enrollments
        $stmtIns = $conn->prepare("
            INSERT INTO student_subject_enrollments (school_id, academic_year_id, grade_id, student_id, class_stream_id, subject_code, subject_name, is_custom_elective)
            VALUES (?, ?, ?, ?, 0, ?, ?, 1)
        ");

        $count = 0;
        foreach ($gradeSubjects as $gs) {
            $code = $gs['subject_code'];
            // If subject is selected by Form Master / Headmaster
            if (in_array($code, $selectedSubjectCodes)) {
                $stmtIns->execute([$schoolId, $year, $gradeId, $studentId, $code, $gs['subject_name']]);
                $count++;
            }
        }

        $conn->commit();

        echo json_encode([
            "success" => true,
            "updated" => $count,
            "message" => "Student subject enrollments updated successfully."
        ]);
        exit();
    }

    echo json_encode(["success" => false, "message" => "Invalid action."]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
