<?php
/**
 * SHULE CAFE Enterprise
 * Copyright (c) 2026 SHULE CAFE Enterprise. All Rights Reserved.
 * PROPRIETARY & CONFIDENTIAL. Unauthorized copying or redistribution is strictly prohibited.
 *
 * Global Daily Attendance & Monitoring API (Headmaster / School Admin)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../config/db.php';

// Authentication & Tenant Authorization Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['tenant_admin', 'school_admin', 'headmaster', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Headmaster credentials required.']);
    exit();
}

$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId && ($_SESSION['role'] ?? '') === 'super_admin') {
    $stmt = $conn->query("SELECT id FROM schools LIMIT 1");
    $schoolId = $stmt->fetchColumn();
}

if (!$schoolId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Active school tenant context is required.']);
    exit();
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'sheet');
$recordedBy = $_SESSION['user_id'];

try {
    // ─────────────────────────────────────────────────────────────────────────
    // 1. FILTERS: Returns Academic Years, Levels, Grades, Classrooms
    // ─────────────────────────────────────────────────────────────────────────
    if ($action === 'filters') {
        $currentYear = date('Y');

        // Academic Years
        $stmtYears = $conn->prepare("
            SELECT DISTINCT academic_year FROM classrooms WHERE school_id = ?
            UNION
            SELECT ? AS academic_year
            ORDER BY academic_year DESC
        ");
        $stmtYears->execute([$schoolId, $currentYear]);
        $years = $stmtYears->fetchAll(PDO::FETCH_COLUMN);

        // Active Education Levels for School
        $stmtLevels = $conn->prepare("
            SELECT DISTINCT el.id, el.name, el.code, sel.range_text
            FROM school_education_levels sel
            JOIN education_levels el ON (sel.level_code = el.code OR sel.level_name = el.name)
            WHERE sel.school_id = ? AND sel.status = 'active'
            ORDER BY el.id ASC
        ");
        $stmtLevels->execute([$schoolId]);
        $levels = $stmtLevels->fetchAll(PDO::FETCH_ASSOC);

        // If no school_education_levels active, fallback to distinct levels from classrooms/grades
        if (empty($levels)) {
            $stmtLevels = $conn->prepare("
                SELECT DISTINCT el.id, el.name, el.code
                FROM education_levels el
                JOIN grades g ON g.level_id = el.id
                JOIN classrooms c ON c.grade_id = g.id
                WHERE c.school_id = ?
                ORDER BY el.id ASC
            ");
            $stmtLevels->execute([$schoolId]);
            $levels = $stmtLevels->fetchAll(PDO::FETCH_ASSOC);
        }

        // Active Grades
        $stmtGrades = $conn->prepare("
            SELECT g.id, g.name, g.level_id, g.order_seq
            FROM grades g
            ORDER BY g.level_id ASC, g.order_seq ASC
        ");
        $stmtGrades->execute();
        $grades = $stmtGrades->fetchAll(PDO::FETCH_ASSOC);

        // Classrooms for School
        $stmtRooms = $conn->prepare("
            SELECT c.id, c.classroom_name, c.grade_id, c.academic_year, g.name AS grade_name, g.level_id,
                   (SELECT COUNT(*) FROM student_classroom_allocations sca WHERE sca.classroom_id = c.id AND sca.academic_year = c.academic_year AND sca.status = 'Active') AS student_count
            FROM classrooms c
            JOIN grades g ON c.grade_id = g.id
            WHERE c.school_id = ?
            ORDER BY g.level_id ASC, g.order_seq ASC, c.classroom_name ASC
        ");
        $stmtRooms->execute([$schoolId]);
        $classrooms = $stmtRooms->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'years' => $years,
            'levels' => $levels,
            'grades' => $grades,
            'classrooms' => $classrooms,
            'today' => date('Y-m-d')
        ]);
        exit();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. SHEET: Load Roster & Daily Attendance Statuses for Date & Classroom
    // ─────────────────────────────────────────────────────────────────────────
    if ($action === 'sheet') {
        $date        = trim($_GET['date'] ?? date('Y-m-d'));
        $year        = trim($_GET['year'] ?? date('Y'));
        $classroomId = intval($_GET['classroom_id'] ?? 0);
        $gradeId     = intval($_GET['grade_id'] ?? 0);
        $levelId     = intval($_GET['level_id'] ?? 0);
        $search      = trim($_GET['search'] ?? '');

        // Validate date format YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        // Build base student roster query
        $where = ["sca.school_id = :school_id", "sca.academic_year = :year", "sca.status = 'Active'"];
        $params = [':school_id' => $schoolId, ':year' => $year, ':att_date' => $date];

        if ($classroomId > 0) {
            $where[] = "c.id = :classroom_id";
            $params[':classroom_id'] = $classroomId;
        } elseif ($gradeId > 0) {
            $where[] = "c.grade_id = :grade_id";
            $params[':grade_id'] = $gradeId;
        } elseif ($levelId > 0) {
            $where[] = "g.level_id = :level_id";
            $params[':level_id'] = $levelId;
        }

        if (!empty($search)) {
            $where[] = "(u.full_name LIKE :search OR u.user_code LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $whereClause = implode(' AND ', $where);

        $stmtRoster = $conn->prepare("
            SELECT u.id AS student_id, u.full_name, u.user_code, u.gender,
                   c.id AS classroom_id, c.classroom_name,
                   g.id AS grade_id, g.name AS grade_name,
                   el.name AS level_name,
                   COALESCE(sa.status, 'Present') AS status,
                   sa.attendance_date,
                   sa.updated_at AS marked_at,
                   sa.recorded_by
            FROM student_classroom_allocations sca
            JOIN users u ON sca.student_id = u.id
            JOIN classrooms c ON sca.classroom_id = c.id
            JOIN grades g ON c.grade_id = g.id
            JOIN education_levels el ON g.level_id = el.id
            LEFT JOIN student_attendance sa ON (
                sa.student_id = u.id 
                AND sa.school_id = :school_id 
                AND sa.attendance_date = :att_date
            )
            WHERE $whereClause
            ORDER BY g.order_seq ASC, c.classroom_name ASC, u.full_name ASC
        ");
        $stmtRoster->execute($params);
        $roster = $stmtRoster->fetchAll(PDO::FETCH_ASSOC);

        // Check if attendance has already been officially recorded for this target
        $hasSavedRecords = false;
        foreach ($roster as $r) {
            if (!empty($r['attendance_date'])) {
                $hasSavedRecords = true;
                break;
            }
        }

        // Calculate Headcount Metrics
        $total   = count($roster);
        $present = 0;
        $absent  = 0;
        $late    = 0;
        $excused = 0;

        foreach ($roster as $r) {
            $st = $r['status'] ?? 'Present';
            if ($st === 'Present') $present++;
            elseif ($st === 'Absent') $absent++;
            elseif ($st === 'Late') $late++;
            elseif ($st === 'Excused') $excused++;
        }

        $rate = $total > 0 ? round((($present + $late) / $total) * 100, 1) : 100.0;

        $dateFormatted = date('l, F j, Y', strtotime($date));

        echo json_encode([
            'success' => true,
            'date' => $date,
            'date_formatted' => $dateFormatted,
            'academic_year' => $year,
            'classroom_id' => $classroomId,
            'grade_id' => $gradeId,
            'level_id' => $levelId,
            'has_saved_records' => $hasSavedRecords,
            'headcount' => [
                'total' => $total,
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'excused' => $excused,
                'rate' => $rate
            ],
            'roster' => $roster
        ]);
        exit();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. SAVE: Persist Attendance Records Atomically
    // ─────────────────────────────────────────────────────────────────────────
    if ($action === 'save') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
            exit();
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $date        = trim($input['date'] ?? date('Y-m-d'));
        $year        = trim($input['year'] ?? date('Y'));
        $classroomId = intval($input['classroom_id'] ?? 0);
        $records     = $input['records'] ?? []; // Array of { student_id: '...', status: '...', classroom_id: ... }

        if (empty($records)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No attendance records provided for saving.']);
            exit();
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid date format. Expected YYYY-MM-DD.']);
            exit();
        }

        $allowedStatuses = ['Present', 'Absent', 'Late', 'Excused'];

        $conn->beginTransaction();

        $stmtSave = $conn->prepare("
            INSERT INTO student_attendance (
                school_id, academic_year, classroom_id, student_id, attendance_date, status, recorded_by
            ) VALUES (
                :school_id, :academic_year, :classroom_id, :student_id, :attendance_date, :status, :recorded_by
            ) ON DUPLICATE KEY UPDATE
                classroom_id = VALUES(classroom_id),
                status = VALUES(status),
                recorded_by = VALUES(recorded_by),
                updated_at = NOW()
        ");

        $savedCount = 0;
        foreach ($records as $rec) {
            $sid = trim($rec['student_id'] ?? '');
            $cid = intval($rec['classroom_id'] ?? $classroomId);
            $status = trim($rec['status'] ?? 'Present');

            if (empty($sid)) continue;
            if (!in_array($status, $allowedStatuses)) {
                $status = 'Present';
            }

            $stmtSave->execute([
                ':school_id'       => $schoolId,
                ':academic_year'   => $year,
                ':classroom_id'    => $cid ?: null,
                ':student_id'      => $sid,
                ':attendance_date' => $date,
                ':status'          => $status,
                ':recorded_by'     => $recordedBy
            ]);
            $savedCount++;
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => "Successfully saved attendance for $savedCount students.",
            'date' => $date,
            'saved_count' => $savedCount
        ]);
        exit();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. MISSING: Identify Classrooms with Missing Attendance on Target Date
    // ─────────────────────────────────────────────────────────────────────────
    if ($action === 'missing') {
        $date = trim($_GET['date'] ?? date('Y-m-d'));
        $year = trim($_GET['year'] ?? date('Y'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        // Fetch all classrooms with total enrolled students and recorded count for target date
        $stmtMissing = $conn->prepare("
            SELECT c.id AS classroom_id, c.classroom_name, c.academic_year,
                   g.id AS grade_id, g.name AS grade_name,
                   el.name AS level_name,
                   (SELECT COUNT(*) FROM student_classroom_allocations sca WHERE sca.classroom_id = c.id AND sca.academic_year = :year AND sca.status = 'Active') AS enrolled_students,
                   (SELECT COUNT(*) FROM student_attendance sa WHERE sa.classroom_id = c.id AND sa.attendance_date = :att_date AND sa.school_id = :school_id) AS recorded_count,
                   (
                       SELECT u.full_name 
                       FROM class_teachers ct 
                       JOIN users u ON ct.teacher_id = u.id 
                       WHERE (ct.class_stream_id = CAST(c.id AS CHAR) OR ct.class_stream_id = c.classroom_name)
                       LIMIT 1
                   ) AS class_guider_name
            FROM classrooms c
            JOIN grades g ON c.grade_id = g.id
            JOIN education_levels el ON g.level_id = el.id
            WHERE c.school_id = :school_id AND c.academic_year = :year AND c.is_active = 1
            HAVING enrolled_students > 0 AND recorded_count = 0
            ORDER BY g.order_seq ASC, c.classroom_name ASC
        ");
        $stmtMissing->execute([
            ':school_id' => $schoolId,
            ':year'      => $year,
            ':att_date'  => $date
        ]);
        $missingRooms = $stmtMissing->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'date' => $date,
            'academic_year' => $year,
            'missing_count' => count($missingRooms),
            'missing_classrooms' => $missingRooms
        ]);
        exit();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. ANALYTICS: Quick Analytics Window Data (Levels, Grades, Top Absentees)
    // ─────────────────────────────────────────────────────────────────────────
    if ($action === 'analytics') {
        $date = trim($_GET['date'] ?? date('Y-m-d'));
        $year = trim($_GET['year'] ?? date('Y'));

        // Level breakdown
        $stmtLevels = $conn->prepare("
            SELECT el.name AS level_name,
                   COUNT(sa.id) AS total_records,
                   SUM(CASE WHEN sa.status = 'Present' THEN 1 ELSE 0 END) AS present_count,
                   SUM(CASE WHEN sa.status = 'Absent' THEN 1 ELSE 0 END) AS absent_count,
                   SUM(CASE WHEN sa.status = 'Late' THEN 1 ELSE 0 END) AS late_count,
                   SUM(CASE WHEN sa.status = 'Excused' THEN 1 ELSE 0 END) AS excused_count
            FROM student_attendance sa
            JOIN classrooms c ON sa.classroom_id = c.id
            JOIN grades g ON c.grade_id = g.id
            JOIN education_levels el ON g.level_id = el.id
            WHERE sa.school_id = ? AND sa.attendance_date = ?
            GROUP BY el.id, el.name
        ");
        $stmtLevels->execute([$schoolId, $date]);
        $levelData = $stmtLevels->fetchAll(PDO::FETCH_ASSOC);

        // Grade breakdown
        $stmtGrades = $conn->prepare("
            SELECT g.name AS grade_name,
                   COUNT(sa.id) AS total_records,
                   SUM(CASE WHEN sa.status = 'Present' THEN 1 ELSE 0 END) AS present_count,
                   SUM(CASE WHEN sa.status = 'Absent' THEN 1 ELSE 0 END) AS absent_count,
                   SUM(CASE WHEN sa.status = 'Late' THEN 1 ELSE 0 END) AS late_count,
                   SUM(CASE WHEN sa.status = 'Excused' THEN 1 ELSE 0 END) AS excused_count
            FROM student_attendance sa
            JOIN classrooms c ON sa.classroom_id = c.id
            JOIN grades g ON c.grade_id = g.id
            WHERE sa.school_id = ? AND sa.attendance_date = ?
            GROUP BY g.id, g.name, g.order_seq
            ORDER BY g.order_seq ASC
        ");
        $stmtGrades->execute([$schoolId, $date]);
        $gradeData = $stmtGrades->fetchAll(PDO::FETCH_ASSOC);

        // Frequent Absentees this year (students with >= 3 absences)
        $stmtChronic = $conn->prepare("
            SELECT u.id AS student_id, u.full_name, u.user_code, u.gender,
                   c.classroom_name, g.name AS grade_name,
                   COUNT(sa.id) AS absence_count
            FROM student_attendance sa
            JOIN users u ON sa.student_id = u.id
            JOIN classrooms c ON sa.classroom_id = c.id
            JOIN grades g ON c.grade_id = g.id
            WHERE sa.school_id = ? AND sa.academic_year = ? AND sa.status = 'Absent'
            GROUP BY u.id, u.full_name, u.user_code, u.gender, c.classroom_name, g.name
            HAVING absence_count >= 2
            ORDER BY absence_count DESC
            LIMIT 10
        ");
        $stmtChronic->execute([$schoolId, $year]);
        $chronicAbsentees = $stmtChronic->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'date' => $date,
            'academic_year' => $year,
            'levels' => $levelData,
            'grades' => $gradeData,
            'chronic_absentees' => $chronicAbsentees
        ]);
        exit();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. STUDENT HISTORY: Single Student Attendance Timeline
    // ─────────────────────────────────────────────────────────────────────────
    if ($action === 'student_history') {
        $studentId = trim($_GET['student_id'] ?? '');
        $year      = trim($_GET['year'] ?? date('Y'));

        if (empty($studentId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Student ID is required.']);
            exit();
        }

        // Student Profile info
        $stmtUser = $conn->prepare("
            SELECT u.id, u.full_name, u.user_code, u.gender,
                   c.classroom_name, g.name AS grade_name
            FROM users u
            LEFT JOIN student_classroom_allocations sca ON (sca.student_id = u.id AND sca.academic_year = ? AND sca.status = 'Active')
            LEFT JOIN classrooms c ON sca.classroom_id = c.id
            LEFT JOIN grades g ON c.grade_id = g.id
            WHERE u.id = ? AND u.school_id = ?
            LIMIT 1
        ");
        $stmtUser->execute([$year, $studentId, $schoolId]);
        $student = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Student not found.']);
            exit();
        }

        // Timeline of attendance
        $stmtLogs = $conn->prepare("
            SELECT attendance_date, status, created_at, updated_at
            FROM student_attendance
            WHERE student_id = ? AND school_id = ? AND (academic_year = ? OR YEAR(attendance_date) = ?)
            ORDER BY attendance_date DESC
            LIMIT 60
        ");
        $stmtLogs->execute([$studentId, $schoolId, $year, $year]);
        $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

        $totalDays = count($logs);
        $presentDays = 0;
        $absentDays = 0;
        $lateDays = 0;
        $excusedDays = 0;

        foreach ($logs as $l) {
            if ($l['status'] === 'Present') $presentDays++;
            elseif ($l['status'] === 'Absent') $absentDays++;
            elseif ($l['status'] === 'Late') $lateDays++;
            elseif ($l['status'] === 'Excused') $excusedDays++;
        }

        $rate = $totalDays > 0 ? round((($presentDays + $lateDays) / $totalDays) * 100, 1) : 100.0;

        $statsData = [
            'total' => $totalDays,
            'present' => $presentDays,
            'absent' => $absentDays,
            'late' => $lateDays,
            'excused' => $excusedDays,
            'rate' => $rate,
            'total_days' => $totalDays,
            'present_days' => $presentDays,
            'absent_days' => $absentDays,
            'late_days' => $lateDays,
            'excused_days' => $excusedDays
        ];

        echo json_encode([
            'success' => true,
            'student' => $student,
            'stats' => $statsData,
            'metrics' => $statsData,
            'history' => $logs,
            'timeline' => $logs
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action requested.']);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
