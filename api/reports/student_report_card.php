<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId && ($_SESSION['role'] ?? '') === 'super_admin') {
    $row = $conn->query('SELECT id FROM schools LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $schoolId = $row['id'] ?? null;
}

$action    = $_GET['action']    ?? 'get_timeline';
$studentId = $_GET['student_id'] ?? '';
$year      = $_GET['year']       ?? date('Y');

if (empty($studentId)) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required.']);
    exit();
}

// Fetch student profile info
$stmtStu = $conn->prepare("SELECT id, full_name, user_code, phone, email, status FROM users WHERE id=? AND school_id=?");
$stmtStu->execute([$studentId, $schoolId]);
$student = $stmtStu->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo json_encode(['success' => false, 'message' => 'Student record not found.']);
    exit();
}

try {
    // ── ACTION 1: GET ACADEMIC HISTORY TIMELINE ───────────────────────
    if ($action === 'get_timeline') {
        $stmtHistory = $conn->prepare("
            SELECT
                sca.academic_year,
                sca.status AS year_status,
                c.id AS classroom_id,
                c.classroom_name,
                c.capacity,
                g.name AS grade_name,
                g.id AS grade_id
            FROM student_classroom_allocations sca
            JOIN classrooms c ON sca.classroom_id = c.id
            JOIN grades g ON c.grade_id = g.id
            WHERE sca.student_id = ? AND sca.school_id = ?
            ORDER BY sca.academic_year DESC
        ");
        $stmtHistory->execute([$studentId, $schoolId]);
        $timeline = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success'  => true,
            'student'  => $student,
            'timeline' => $timeline
        ]);
        exit();
    }

    // ── ACTION 2: GET HISTORICAL REPORT CARD DATA ────────────────────
    if ($action === 'get_report') {
        // Fetch allocation details for target year
        $stmtAlloc = $conn->prepare("
            SELECT
                sca.academic_year,
                sca.status AS year_status,
                c.id AS classroom_id,
                c.classroom_name,
                c.capacity,
                g.name AS grade_name,
                g.id AS grade_id
            FROM student_classroom_allocations sca
            JOIN classrooms c ON sca.classroom_id = c.id
            JOIN grades g ON c.grade_id = g.id
            WHERE sca.student_id = ? AND sca.school_id = ? AND sca.academic_year = ?
        ");
        $stmtAlloc->execute([$studentId, $schoolId, $year]);
        $alloc = $stmtAlloc->fetch(PDO::FETCH_ASSOC);

        if (!$alloc) {
            // Fallback header info if allocation not found for selected year
            $alloc = [
                'academic_year' => $year,
                'year_status'   => 'Unallocated',
                'classroom_name'=> 'Unassigned',
                'grade_name'    => 'Unassigned'
            ];
        }

        // Fetch Marks from new dynamic table
        $stmtMarks = $conn->prepare("
            SELECT
                m.subject_code,
                COALESCE(s.name, m.subject_code) AS subject_name,
                m.assessment_type_id,
                m.score
            FROM marks_entry_dynamic m
            LEFT JOIN subjects s ON m.subject_code = s.code
            WHERE m.student_id = ? AND m.school_id = ? AND m.academic_year = ?
        ");
        $stmtMarks->execute([$studentId, $schoolId, $year]);
        $dynamicMarks = $stmtMarks->fetchAll(PDO::FETCH_ASSOC);

        $groupedMarks = [];
        foreach ($dynamicMarks as $dm) {
            $sc = $dm['subject_code'];
            if (!isset($groupedMarks[$sc])) {
                $groupedMarks[$sc] = [
                    'subject_code' => $sc,
                    'subject_name' => $dm['subject_name'],
                    'ca_mark'      => 0,
                    'terminal_mark'=> 0,
                    'total_score'  => 0
                ];
            }
            if ($dm['assessment_type_id'] === 'terminal') {
                $groupedMarks[$sc]['terminal_mark'] += floatval($dm['score']);
            } else {
                $groupedMarks[$sc]['ca_mark'] += floatval($dm['score']);
            }
            $groupedMarks[$sc]['total_score'] += floatval($dm['score']);
        }

        // Fallback to legacy marks_entry if no dynamic marks exist
        if (empty($groupedMarks)) {
            $stmtLegacy = $conn->prepare("SELECT m.subject_code, COALESCE(s.name, m.subject_code) AS subject_name, m.continuous_assessment_mark AS ca_mark, m.terminal_mark, (m.continuous_assessment_mark + m.terminal_mark) AS total_score FROM marks_entry m LEFT JOIN subjects s ON m.subject_code = s.code WHERE m.student_id = ? AND m.school_id = ? AND m.academic_year = ? ORDER BY subject_name ASC");
            $stmtLegacy->execute([$studentId, $schoolId, $year]);
            $legacyMarks = $stmtLegacy->fetchAll(PDO::FETCH_ASSOC);
            foreach ($legacyMarks as $lm) {
                $groupedMarks[$lm['subject_code']] = [
                    'subject_code'  => $lm['subject_code'],
                    'subject_name'  => $lm['subject_name'],
                    'ca_mark'       => floatval($lm['ca_mark']),
                    'terminal_mark' => floatval($lm['terminal_mark']),
                    'total_score'   => floatval($lm['total_score'])
                ];
            }
        }

        require_once __DIR__ . '/../grading/GradingManager.php';
        $gradingManager = new GradingManager($conn);

        $marks = array_values($groupedMarks);
        usort($marks, function($a, $b) { return strcmp($a['subject_name'], $b['subject_name']); });

        // Fetch dynamic education level
        $levelType = 'O-Level';
        if ($alloc) {
            $stmtLevel = $conn->prepare("
                SELECT el.name AS level_type
                FROM classrooms c
                JOIN grades g ON c.grade_id = g.id
                JOIN education_levels el ON g.level_id = el.id
                WHERE c.id = ? LIMIT 1
            ");
            $stmtLevel->execute([$alloc['classroom_id']]);
            if ($res = $stmtLevel->fetchColumn()) {
                $levelType = $res;
            }
        }
        $levelType = $gradingManager->normalizeLevelType($levelType);

        $subjectMarksMap = [];
        foreach ($marks as $m) {
            $subjectMarksMap[$m['subject_code']] = floatval($m['total_score']);
        }

        $perf = $gradingManager->calculateStudentPerformance($levelType, $subjectMarksMap);

        $processedMarks = [];
        foreach ($marks as $m) {
            $sc = $m['subject_code'];
            $gData = $gradingManager->getSubjectGrade($levelType, $m['total_score']);
            $m['grade'] = $gData['grade'];
            $m['points'] = $gData['points'];
            $m['remark'] = $gData['remark'];
            $processedMarks[] = $m;
        }

        $subjectCount = count($processedMarks);

        // Read-only safety lock rule: archived years cannot be modified
        $isReadOnly = ($year !== date('Y')) || ($alloc['year_status'] !== 'Active');

        echo json_encode([
            'success'      => true,
            'student'      => $student,
            'allocation'   => $alloc,
            'year'         => $year,
            'level_type'   => $levelType,
            'is_read_only' => $isReadOnly,
            'summary'      => [
                'total_score'  => round($perf['total_evaluated_marks'] ?? 0, 1),
                'subject_cnt'  => $subjectCount,
                'gpa_avg'      => $perf['average_score'],
                'total_points' => $perf['total_points'],
                'division'     => $perf['division'],
                'remark'       => $perf['remark']
            ],
            'marks'        => $processedMarks
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
