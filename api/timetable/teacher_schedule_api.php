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
    $first = $conn->query("SELECT id FROM schools LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $schoolId = $first['id'] ?? null;
}

$teacherId = $_GET['teacher_id'] ?? $_SESSION['user_id'];
$year = $_GET['year'] ?? date('Y');
$viewClassroom = $_GET['view_classroom'] ?? '0'; // 1 to view homeroom class timetable

try {
    // 1. Check if active master timetable config is published
    $stmtC = $conn->prepare("SELECT * FROM timetable_configs WHERE school_id = ? AND academic_year_id = ? ORDER BY id DESC LIMIT 1");
    $stmtC->execute([$schoolId, $year]);
    $config = $stmtC->fetch(PDO::FETCH_ASSOC);

    $isPublished = intval($config['is_published'] ?? 0);

    // 2. Fetch Periods
    $stmtP = $conn->prepare("SELECT * FROM timetable_periods WHERE school_id = ? ORDER BY period_number ASC");
    $stmtP->execute([$schoolId]);
    $periods = $stmtP->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Class Teacher / Form Master Role
    $stmtCM = $conn->prepare("
        SELECT c.id AS classroom_id, c.classroom_name, g.name AS grade_name
        FROM class_teachers ct
        JOIN classrooms c ON ct.class_stream_id = c.id
        JOIN grades g ON c.grade_id = g.id
        WHERE ct.teacher_id = ?
        LIMIT 1
    ");
    $stmtCM->execute([$teacherId]);
    $homeroom = $stmtCM->fetch(PDO::FETCH_ASSOC);

    // 4. Fetch Timetable Slots
    $slots = [];
    if ($isPublished) {
        if ($viewClassroom === '1' && $homeroom) {
            // Fetch Homeroom Class Timetable
            $stmtSlots = $conn->prepare("
                SELECT ct.id, ct.day_of_week, ct.period_id, ct.class_stream_id, ct.subject_code,
                       COALESCE(c.classroom_name, ct.class_stream_id) AS class_name,
                       COALESCE(sas.subject_name, s.name, ct.subject_code) AS subject_name,
                       u.full_name AS teacher_name
                FROM class_timetables ct
                LEFT JOIN classrooms c ON ct.class_stream_id = c.id
                LEFT JOIN subjects s ON (ct.school_id = s.school_id AND ct.subject_code = s.code)
                LEFT JOIN school_approved_subjects sas ON (ct.school_id = sas.school_id AND ct.subject_code = sas.subject_code)
                LEFT JOIN users u ON ct.teacher_id = u.id
                WHERE ct.school_id = ? AND ct.academic_year_id = ? AND ct.class_stream_id = ?
            ");
            $stmtSlots->execute([$schoolId, $year, $homeroom['classroom_id']]);
            $slots = $stmtSlots->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Fetch Teacher's Individual Schedule
            $stmtSlots = $conn->prepare("
                SELECT ct.id, ct.day_of_week, ct.period_id, ct.class_stream_id, ct.subject_code,
                       COALESCE(c.classroom_name, ct.class_stream_id) AS class_name,
                       COALESCE(sas.subject_name, s.name, ct.subject_code) AS subject_name,
                       u.full_name AS teacher_name
                FROM class_timetables ct
                LEFT JOIN classrooms c ON ct.class_stream_id = c.id
                LEFT JOIN subjects s ON (ct.school_id = s.school_id AND ct.subject_code = s.code)
                LEFT JOIN school_approved_subjects sas ON (ct.school_id = sas.school_id AND ct.subject_code = sas.subject_code)
                LEFT JOIN users u ON ct.teacher_id = u.id
                WHERE ct.school_id = ? AND ct.academic_year_id = ? AND ct.teacher_id = ?
            ");
            $stmtSlots->execute([$schoolId, $year, $teacherId]);
            $slots = $stmtSlots->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    echo json_encode([
        "success" => true,
        "is_published" => $isPublished,
        "homeroom" => $homeroom ? ($homeroom['grade_name'] . ' ' . $homeroom['classroom_name']) : null,
        "homeroom_id" => $homeroom ? $homeroom['classroom_id'] : null,
        "view_type" => ($viewClassroom === '1' && $homeroom) ? 'classroom' : 'personal',
        "periods" => $periods,
        "slots" => $slots
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
