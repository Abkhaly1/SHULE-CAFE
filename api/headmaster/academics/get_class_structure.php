<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) { http_response_code(403); echo json_encode(["success"=>false,"message"=>"Unauthorized."]); exit(); }

$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId && ($_SESSION['role'] ?? '') === 'super_admin') {
    $row = $conn->query("SELECT id FROM schools LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $schoolId = $row['id'] ?? null;
}

$gradeId   = intval($_GET['grade_id']   ?? 0);
$streamId  = intval($_GET['stream_id']  ?? 0);
$levelId   = intval($_GET['level_id']   ?? 0);

try {
    // 1. Education levels (only registered active levels for this school)
    $stmtL = $conn->prepare("
        SELECT DISTINCT el.id, el.name, el.code
        FROM education_levels el
        JOIN school_education_levels sel ON (
            UPPER(REPLACE(el.name, '-', '')) = UPPER(REPLACE(sel.level_code, '-', ''))
            OR UPPER(el.code) = UPPER(sel.level_code)
            OR UPPER(sel.level_code) LIKE CONCAT('%', UPPER(el.name), '%')
        )
        WHERE sel.school_id = ? AND sel.status = 'active'
        ORDER BY el.id
    ");
    $stmtL->execute([$schoolId]);
    $levels = $stmtL->fetchAll(PDO::FETCH_ASSOC);
    if (empty($levels)) {
        $levels = $conn->query("SELECT id, name, code FROM education_levels ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    }

    // 2. Grades for selected level
    $grades = [];
    if ($levelId) {
        $s = $conn->prepare("SELECT id, name, order_seq FROM grades WHERE level_id=? ORDER BY order_seq");
        $s->execute([$levelId]);
        $grades = $s->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. Classrooms (Streams) for selected grade created by Headmaster
    $streams = [];
    if ($gradeId) {
        $s = $conn->prepare("SELECT id, classroom_name AS name, 'standard' AS stream_type, capacity FROM classrooms WHERE school_id=? AND grade_id=? AND academic_year='" . date('Y') . "' ORDER BY classroom_name");
        $s->execute([$schoolId, $gradeId]);
        $streams = $s->fetchAll(PDO::FETCH_ASSOC);

        // Fallback to class_streams table if no classrooms set up yet
        if (empty($streams)) {
            $s = $conn->prepare("SELECT id, name, stream_type, capacity FROM class_streams WHERE school_id=? AND grade_id=? ORDER BY name");
            $s->execute([$schoolId, $gradeId]);
            $streams = $s->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // 4. Stream subjects for selected stream (only mapped subjects for this room)
    $streamSubjects = [];
    if ($streamId) {
        $s = $conn->prepare("
            SELECT ss.subject_code, ss.subject_name, ss.is_core
            FROM stream_subjects ss
            WHERE ss.school_id=? AND ss.class_stream_id=? AND ss.academic_year_id='" . date('Y') . "'
            ORDER BY ss.is_core DESC, ss.subject_name ASC
        ");
        $s->execute([$schoolId, $streamId]);
        $streamSubjects = $s->fetchAll(PDO::FETCH_ASSOC);
    }

    // 5. Teachers for dropdowns
    $teachers = $conn->prepare("SELECT id, full_name, user_code FROM users WHERE school_id=? AND role IN ('teacher','tenant_admin') AND status='active' ORDER BY full_name");
    $teachers->execute([$schoolId]);
    $teacherList = $teachers->fetchAll(PDO::FETCH_ASSOC);

    // 6. Teacher assignments for this stream
    $assignments = [];
    if ($streamId) {
        $streamName = $conn->query("SELECT name FROM class_streams WHERE id=$streamId")->fetchColumn();
        $s = $conn->prepare("
            SELECT tsa.subject_code,
                   GROUP_CONCAT(tsa.teacher_id SEPARATOR ',') as teacher_ids,
                   GROUP_CONCAT(u.full_name SEPARATOR ', ')   as teacher_names
            FROM teacher_subject_assignments tsa
            LEFT JOIN users u ON tsa.teacher_id=u.id
            WHERE tsa.school_id=? AND tsa.class_stream_id=? AND tsa.academic_year_id='" . date('Y') . "'
            GROUP BY tsa.subject_code
        ");
        $s->execute([$schoolId, $streamId]);
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $assignments[$row['subject_code']] = $row;
        }
    }

    echo json_encode([
        "success"         => true,
        "levels"          => $levels,
        "grades"          => $grades,
        "streams"         => $streams,
        "stream_subjects" => $streamSubjects,
        "teachers"        => $teacherList,
        "assignments"     => $assignments,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success"=>false,"message"=>$e->getMessage()]);
}
?>
