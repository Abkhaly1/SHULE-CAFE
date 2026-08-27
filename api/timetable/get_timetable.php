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

$levelId   = intval($_GET['level_id']   ?? 0);
$gradeId   = intval($_GET['grade_id']   ?? 0);
$streamId  = intval($_GET['stream_id']  ?? 0);
$year      = $_GET['academic_year']     ?? date('Y');

try {
    // 1. Education Levels (only registered active levels for this school)
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

    // 3. Streams for selected grade
    $streams = [];
    if ($gradeId) {
        $s = $conn->prepare("SELECT id, name, stream_type FROM class_streams WHERE school_id=? AND grade_id=? ORDER BY name");
        $s->execute([$schoolId, $gradeId]);
        $streams = $s->fetchAll(PDO::FETCH_ASSOC);
    }

    // 4. Period slots for the selected level (level_type derived from level name)
    $periods = [];
    $levelType = 'O-Level';
    if ($levelId) {
        $levelName = $conn->query("SELECT name FROM education_levels WHERE id=$levelId")->fetchColumn();
        $levelType = $levelName ?: 'O-Level';
        $s = $conn->prepare("SELECT id, period_number, period_name, start_time, end_time, is_break FROM timetable_periods WHERE school_id=? AND level_type=? ORDER BY period_number");
        $s->execute([$schoolId, $levelType]);
        $periods = $s->fetchAll(PDO::FETCH_ASSOC);
    }

    // 5. Stream subjects (filtered to this stream room)
    $streamSubjects = [];
    $streamName = '';
    if ($streamId) {
        $streamName = $conn->query("SELECT name FROM class_streams WHERE id=$streamId")->fetchColumn() ?: '';
        $s = $conn->prepare("SELECT subject_code, subject_name, is_core FROM stream_subjects WHERE school_id=? AND class_stream_id=? AND academic_year_id=? ORDER BY is_core DESC, subject_name");
        $s->execute([$schoolId, $streamId, $year]);
        $streamSubjects = $s->fetchAll(PDO::FETCH_ASSOC);
    }

    // 6. Available teachers (with their assigned subjects for this stream)
    $teachers = [];
    if ($schoolId) {
        $s = $conn->prepare("
            SELECT u.id, u.full_name, u.user_code,
                   GROUP_CONCAT(DISTINCT tsa.subject_code ORDER BY tsa.subject_code SEPARATOR ',') AS teaches
            FROM users u
            LEFT JOIN teacher_subject_assignments tsa ON (tsa.teacher_id=u.id AND tsa.school_id=? AND tsa.class_stream_id=?)
            WHERE u.school_id=? AND u.role IN ('teacher','tenant_admin','super_admin') AND u.status='active'
            GROUP BY u.id ORDER BY u.full_name
        ");
        $s->execute([$schoolId, $streamName, $schoolId]);
        $teachers = $s->fetchAll(PDO::FETCH_ASSOC);
    }

    // 7. Existing timetable for stream (keyed by day → period_id)
    $timetable = [];
    if ($streamId && $streamName) {
        $s = $conn->prepare("
            SELECT ct.day_of_week, ct.period_id, ct.subject_code, ct.teacher_id,
                   u.full_name AS teacher_name, sas.subject_name
            FROM class_timetables ct
            LEFT JOIN users u ON ct.teacher_id=u.id
            LEFT JOIN school_approved_subjects sas ON (ct.school_id=sas.school_id AND ct.subject_code=sas.subject_code)
            WHERE ct.school_id=? AND ct.academic_year_id=? AND ct.class_stream_id=?
        ");
        $s->execute([$schoolId, $year, $streamName]);
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $timetable[$row['day_of_week']][$row['period_id']] = $row;
        }
    }

    echo json_encode([
        "success"        => true,
        "levels"         => $levels,
        "grades"         => $grades,
        "streams"        => $streams,
        "periods"        => $periods,
        "stream_subjects"=> $streamSubjects,
        "stream_name"    => $streamName,
        "level_type"     => $levelType,
        "teachers"       => $teachers,
        "timetable"      => $timetable,
        "days"           => ["Monday","Tuesday","Wednesday","Thursday","Friday"]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success"=>false,"message"=>$e->getMessage()]);
}
?>
