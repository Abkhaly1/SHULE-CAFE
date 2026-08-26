<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['tenant_admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId && ($_SESSION['role'] ?? '') === 'super_admin') {
    $stmt = $conn->query("SELECT id FROM schools LIMIT 1");
    $schoolId = $stmt->fetchColumn();
}

if (!$schoolId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'School ID is required.']);
    exit();
}

$action = $_GET['action'] ?? 'overview';
$year   = $_GET['year']   ?? date('Y');

try {
    // 1. OVERVIEW ANALYTICS (Whole School, Levels, Grades, & Chronic Absentees)
    if ($action === 'overview') {
        
        // Fetch all attendance records for the school/year across daily_attendance_details and student_attendance
        $stmtDetails = $conn->prepare("
            SELECT dad.status, u.grade_id, g.name AS grade_name, el.name AS level_name, dad.student_id
            FROM daily_attendance_details dad
            JOIN daily_attendance da ON dad.daily_attendance_id = da.id
            JOIN users u ON dad.student_id = u.id
            LEFT JOIN grades g ON u.grade_id = g.id
            LEFT JOIN education_levels el ON g.level_id = el.id
            WHERE u.school_id = ? AND (da.academic_year_id = ? OR YEAR(da.attendance_date) = ?)
        ");
        $stmtDetails->execute([$schoolId, $year, $year]);
        $rows1 = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

        $stmtSa = $conn->prepare("
            SELECT sa.status, u.grade_id, g.name AS grade_name, el.name AS level_name, sa.student_id
            FROM student_attendance sa
            JOIN users u ON sa.student_id = u.id
            LEFT JOIN grades g ON u.grade_id = g.id
            LEFT JOIN education_levels el ON g.level_id = el.id
            WHERE sa.school_id = ? AND (sa.academic_year = ? OR YEAR(sa.attendance_date) = ?)
        ");
        $stmtSa->execute([$schoolId, $year, $year]);
        $rows2 = $stmtSa->fetchAll(PDO::FETCH_ASSOC);

        $allRows = array_merge($rows1, $rows2);

        $totalRecords = count($allRows);
        $totalPresent = 0;
        $totalAbsent = 0;
        $totalExcused = 0;

        $levelStats = [];
        $gradeStats = [];
        $studentAbsences = [];

        foreach ($allRows as $r) {
            $status = $r['status'] ?? 'Present';
            $level  = $r['level_name'] ?: 'Ordinary Secondary';
            $grade  = $r['grade_name'] ?: 'General Grade';
            $sid    = $r['student_id'];

            if (!isset($levelStats[$level])) {
                $levelStats[$level] = ['present' => 0, 'total' => 0];
            }
            if (!isset($gradeStats[$grade])) {
                $gradeStats[$grade] = ['present' => 0, 'total' => 0];
            }
            if (!isset($studentAbsences[$sid])) {
                $studentAbsences[$sid] = ['absent_count' => 0, 'total' => 0, 'excused_count' => 0];
            }

            $levelStats[$level]['total']++;
            $gradeStats[$grade]['total']++;
            $studentAbsences[$sid]['total']++;

            if ($status === 'Present') {
                $totalPresent++;
                $levelStats[$level]['present']++;
                $gradeStats[$grade]['present']++;
            } elseif ($status === 'Absent') {
                $totalAbsent++;
                $studentAbsences[$sid]['absent_count']++;
            } elseif ($status === 'Excused') {
                $totalExcused++;
                $studentAbsences[$sid]['excused_count']++;
            }
        }

        $overallRate = $totalRecords > 0 ? round(($totalPresent / $totalRecords) * 100, 1) : 100.0;

        // Process Level Analytics
        $levelAnalytics = [];
        foreach ($levelStats as $lvlName => $st) {
            $rate = $st['total'] > 0 ? round(($st['present'] / $st['total']) * 100, 1) : 100.0;
            $levelAnalytics[] = [
                'level_name' => $lvlName,
                'total_records' => $st['total'],
                'present_count' => $st['present'],
                'rate' => $rate
            ];
        }

        // Process Grade Analytics
        $gradeAnalytics = [];
        foreach ($gradeStats as $grdName => $st) {
            $rate = $st['total'] > 0 ? round(($st['present'] / $st['total']) * 100, 1) : 100.0;
            $gradeAnalytics[] = [
                'grade_name' => $grdName,
                'total_records' => $st['total'],
                'present_count' => $st['present'],
                'rate' => $rate
            ];
        }

        // Identify Top Chronic Absentees (Most Absent Students)
        $chronicIds = [];
        foreach ($studentAbsences as $sid => $info) {
            if ($info['absent_count'] > 0) {
                $chronicIds[$sid] = $info['absent_count'];
            }
        }
        arsort($chronicIds);
        $topChronicIds = array_slice(array_keys($chronicIds), 0, 10);

        $chronicAbsentees = [];
        if (!empty($topChronicIds)) {
            $inClause = implode(',', array_fill(0, count($topChronicIds), '?'));
            $stmtChronic = $conn->prepare("
                SELECT u.id, u.full_name, u.user_code, g.name AS grade_name, c.classroom_name
                FROM users u
                LEFT JOIN student_classroom_allocations sca ON u.id = sca.student_id AND sca.academic_year = ?
                LEFT JOIN classrooms c ON sca.classroom_id = c.id
                LEFT JOIN grades g ON c.grade_id = g.id
                WHERE u.id IN ($inClause) AND u.school_id = ?
            ");
            $params = array_merge([$year], $topChronicIds, [$schoolId]);
            $stmtChronic->execute($params);
            $absentUserProfiles = $stmtChronic->fetchAll(PDO::FETCH_ASSOC);

            foreach ($absentUserProfiles as $up) {
                $sid = $up['id'];
                $absCount = $studentAbsences[$sid]['absent_count'] ?? 0;
                $totCount = $studentAbsences[$sid]['total'] ?? 1;
                $presCount = $totCount - $absCount;
                $rate = round(($presCount / $totCount) * 100, 1);

                $chronicAbsentees[] = [
                    'student_id' => $sid,
                    'full_name'  => $up['full_name'],
                    'user_code'  => $up['user_code'] ?: 'N/A',
                    'grade_name' => $up['grade_name'] ?: 'General Grade',
                    'classroom_name' => $up['classroom_name'] ?: 'Unassigned Stream',
                    'absent_count' => $absCount,
                    'total_sessions' => $totCount,
                    'attendance_rate' => $rate
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'year' => $year,
            'overall' => [
                'rate' => $overallRate,
                'total_records' => $totalRecords,
                'total_present' => $totalPresent,
                'total_absent'  => $totalAbsent,
                'total_excused' => $totalExcused
            ],
            'level_analytics' => $levelAnalytics,
            'grade_analytics' => $gradeAnalytics,
            'chronic_absentees' => $chronicAbsentees
        ]);
        exit();
    }

    // 2. LIST ACADEMIC YEARS
    if ($action === 'years') {
        $stmtYears = $conn->prepare("
            SELECT DISTINCT academic_year FROM (
                SELECT da.academic_year_id AS academic_year FROM daily_attendance da JOIN classrooms c ON da.classroom_id = c.id WHERE c.school_id = ?
                UNION
                SELECT sca.academic_year FROM student_classroom_allocations sca WHERE sca.school_id = ?
                UNION
                SELECT '" . date('Y') . "' AS academic_year
            ) AS combined ORDER BY academic_year DESC
        ");
        $stmtYears->execute([$schoolId, $schoolId]);
        $years = $stmtYears->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode(['success' => true, 'years' => $years]);
        exit();
    }

    // 3. LIST CLASSROOMS FOR A YEAR
    if ($action === 'classrooms') {
        $stmtCls = $conn->prepare("
            SELECT c.id, c.classroom_name, g.name AS grade_name, el.name AS level_name,
                   (SELECT COUNT(*) FROM student_classroom_allocations sca WHERE sca.classroom_id = c.id AND sca.academic_year = ?) AS student_count
            FROM classrooms c
            JOIN grades g ON c.grade_id = g.id
            JOIN education_levels el ON g.level_id = el.id
            WHERE c.school_id = ? AND c.academic_year = ?
            ORDER BY el.id, g.order_seq, c.classroom_name ASC
        ");
        $stmtCls->execute([$year, $schoolId, $year]);
        $classrooms = $stmtCls->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'year' => $year, 'classrooms' => $classrooms]);
        exit();
    }

    // 4. CLASSROOM ROSTER WITH ATTENDANCE STATS & PAGINATION
    if ($action === 'classroom_roster') {
        $classroomId = intval($_GET['classroom_id'] ?? 0);
        $search      = trim($_GET['search'] ?? '');
        $page        = max(1, intval($_GET['page'] ?? 1));
        $limit       = max(10, min(200, intval($_GET['limit'] ?? 25)));
        $offset      = ($page - 1) * $limit;

        if (!$classroomId) {
            echo json_encode(['success' => false, 'message' => 'Classroom ID is required.']);
            exit();
        }

        // Count total students matching filter
        $whereSql = "WHERE sca.school_id = :school_id AND sca.classroom_id = :classroom_id AND sca.academic_year = :year";
        $paramsCount = [':school_id' => $schoolId, ':classroom_id' => $classroomId, ':year' => $year];

        if ($search !== '') {
            $whereSql .= " AND (u.full_name LIKE :search OR u.user_code LIKE :search)";
            $paramsCount[':search'] = '%' . $search . '%';
        }

        $stmtCnt = $conn->prepare("
            SELECT COUNT(*)
            FROM student_classroom_allocations sca
            JOIN users u ON sca.student_id = u.id
            $whereSql
        ");
        $stmtCnt->execute($paramsCount);
        $totalStudents = (int)$stmtCnt->fetchColumn();
        $totalPages = max(1, ceil($totalStudents / $limit));

        // Fetch paginated students in this classroom
        $stmtRoster = $conn->prepare("
            SELECT u.id AS student_id, u.full_name, u.user_code, u.gender
            FROM student_classroom_allocations sca
            JOIN users u ON sca.student_id = u.id
            $whereSql
            ORDER BY u.full_name ASC
            LIMIT $offset, $limit
        ");
        $stmtRoster->execute($paramsCount);
        $students = $stmtRoster->fetchAll(PDO::FETCH_ASSOC);

        // Fetch attendance stats for each student in the page
        $rosterData = [];
        foreach ($students as $s) {
            $sid = $s['student_id'];

            // Query daily_attendance_details
            $s1 = $conn->prepare("
                SELECT dad.status
                FROM daily_attendance_details dad
                JOIN daily_attendance da ON dad.daily_attendance_id = da.id
                WHERE da.classroom_id = ? AND dad.student_id = ?
            ");
            $s1->execute([$classroomId, $sid]);
            $res1 = $s1->fetchAll(PDO::FETCH_COLUMN);

            // Query student_attendance
            $s2 = $conn->prepare("
                SELECT status FROM student_attendance WHERE school_id = ? AND student_id = ?
            ");
            $s2->execute([$schoolId, $sid]);
            $res2 = $s2->fetchAll(PDO::FETCH_COLUMN);

            $allStatuses = array_merge($res1, $res2);
            $totalSess = count($allStatuses);
            $pres = 0; $abs = 0; $exc = 0;

            foreach ($allStatuses as $st) {
                if ($st === 'Present') $pres++;
                elseif ($st === 'Absent') $abs++;
                elseif ($st === 'Excused') $exc++;
            }

            $rate = $totalSess > 0 ? round(($pres / $totalSess) * 100, 1) : 100.0;

            $rosterData[] = [
                'student_id'    => $sid,
                'full_name'     => $s['full_name'],
                'user_code'     => $s['user_code'] ?: 'N/A',
                'gender'        => $s['gender'] ?: 'N/A',
                'total_sessions'=> $totalSess,
                'present_count' => $pres,
                'absent_count'  => $abs,
                'excused_count' => $exc,
                'attendance_rate' => $rate
            ];
        }

        echo json_encode([
            'success' => true,
            'classroom_id' => $classroomId,
            'roster' => $rosterData,
            'pagination' => [
                'total' => $totalStudents,
                'page'  => $page,
                'limit' => $limit,
                'total_pages' => $totalPages
            ]
        ]);
        exit();
    }

    // 5. INDIVIDUAL STUDENT ATTENDANCE DRILLDOWN TIMELINE
    if ($action === 'student_timeline') {
        $studentId = $_GET['student_id'] ?? '';

        if (!$studentId) {
            echo json_encode(['success' => false, 'message' => 'Student ID is required.']);
            exit();
        }

        // Fetch student profile
        $stmtProf = $conn->prepare("
            SELECT u.id, u.full_name, u.user_code, u.gender, u.email, u.phone,
                   c.classroom_name, g.name AS grade_name
            FROM users u
            LEFT JOIN student_classroom_allocations sca ON u.id = sca.student_id AND sca.academic_year = ?
            LEFT JOIN classrooms c ON sca.classroom_id = c.id
            LEFT JOIN grades g ON c.grade_id = g.id
            WHERE u.id = ? AND u.school_id = ?
        ");
        $stmtProf->execute([$year, $studentId, $schoolId]);
        $profile = $stmtProf->fetch(PDO::FETCH_ASSOC);

        // Fetch attendance logs from daily_attendance_details
        $stmtLog1 = $conn->prepare("
            SELECT da.attendance_date AS date, 'Daily Roll Call' AS session_type, c.classroom_name,
                   dad.status, u.full_name AS teacher_name, da.general_remarks AS remarks
            FROM daily_attendance_details dad
            JOIN daily_attendance da ON dad.daily_attendance_id = da.id
            LEFT JOIN classrooms c ON da.classroom_id = c.id
            LEFT JOIN users u ON da.recorded_by_teacher_id = u.id
            WHERE dad.student_id = ?
            ORDER BY da.attendance_date DESC
        ");
        $stmtLog1->execute([$studentId]);
        $logs1 = $stmtLog1->fetchAll(PDO::FETCH_ASSOC);

        // Fetch attendance logs from student_attendance
        $stmtLog2 = $conn->prepare("
            SELECT sa.attendance_date AS date, 'Subject Class Period' AS session_type, 'Assigned Class' AS classroom_name,
                   sa.status, 'Class Teacher' AS teacher_name, sa.remarks
            FROM student_attendance sa
            WHERE sa.student_id = ? AND sa.school_id = ?
            ORDER BY sa.attendance_date DESC
        ");
        $stmtLog2->execute([$studentId, $schoolId]);
        $logs2 = $stmtLog2->fetchAll(PDO::FETCH_ASSOC);

        $allLogs = array_merge($logs1, $logs2);

        // Compute individual summary
        $totalSessions = count($allLogs);
        $presentCount = 0; $absentCount = 0; $excusedCount = 0;

        foreach ($allLogs as $l) {
            if ($l['status'] === 'Present') $presentCount++;
            elseif ($l['status'] === 'Absent') $absentCount++;
            elseif ($l['status'] === 'Excused') $excusedCount++;
        }

        $rate = $totalSessions > 0 ? round(($presentCount / $totalSessions) * 100, 1) : 100.0;

        echo json_encode([
            'success' => true,
            'profile' => $profile,
            'summary' => [
                'total_sessions' => $totalSessions,
                'present_count'  => $presentCount,
                'absent_count'   => $absentCount,
                'excused_count'  => $excusedCount,
                'attendance_rate'=> $rate
            ],
            'timeline' => $allLogs
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
