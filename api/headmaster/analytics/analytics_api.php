<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once '../../config/db.php';

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

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';
$year = $_GET['year'] ?? $input['year'] ?? date('Y');
$term = $_GET['term'] ?? $input['term'] ?? 'Term 1';

try {
    // 1. INDIVIDUAL 360° STUDENT PROGRESS REPORT CARD (TASK 4.1)
    if ($action === 'student_report_card') {
        $studentId = $_GET['student_id'] ?? $input['student_id'] ?? '';
        if (empty($studentId)) {
            echo json_encode(['success' => false, 'message' => 'student_id is required.']);
            exit();
        }

        // Student details & classroom
        $stmtStudent = $conn->prepare("
            SELECT u.id, u.full_name, u.user_code, u.gender, c.classroom_name, c.id AS classroom_id, g.name AS grade_name, el.name AS level_type
            FROM users u
            LEFT JOIN student_classroom_allocations sca ON (sca.student_id = u.id AND sca.academic_year = ?)
            LEFT JOIN classrooms c ON sca.classroom_id = c.id
            LEFT JOIN grades g ON c.grade_id = g.id
            LEFT JOIN education_levels el ON g.level_id = el.id
            WHERE u.id = ? AND u.school_id = ?
            LIMIT 1
        ");
        $stmtStudent->execute([$year, $studentId, $schoolId]);
        $student = $stmtStudent->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student record not found.']);
            exit();
        }

        $levelType = $student['level_type'] ?: 'O-Level';

        // Fetch grading scale rules
        $stmtScales = $conn->prepare("SELECT min_mark, max_mark, grade, remark, points FROM grading_scales WHERE level_type = ? ORDER BY min_mark DESC");
        $stmtScales->execute([$levelType]);
        $scales = $stmtScales->fetchAll(PDO::FETCH_ASSOC);

        // Fetch assessment types for dynamic table headers
        $stmtTypes = $conn->prepare("SELECT id, name, weight_percent, is_terminal, term, academic_year FROM assessment_types WHERE school_id = ? AND academic_year = ? ORDER BY id ASC");
        $stmtTypes->execute([$schoolId, $year]);
        $allTypes = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);
        $assessmentTypes = [];
        if (!empty($allTypes)) {
            foreach ($allTypes as $t) {
                if (!empty($t['term'])) {
                    $t['name'] = $t['name'] . ' (' . $t['term'] . ')';
                }
                $assessmentTypes[] = $t;
            }
        }

        // Fetch dynamic marks
        $stmtMarks = $conn->prepare("
            SELECT me.subject_code, COALESCE(s.name, me.subject_code) AS subject_name,
                   me.assessment_type_id, me.score
            FROM marks_entry_dynamic me
            LEFT JOIN subjects s ON me.subject_code = s.code
            WHERE me.school_id = ? AND me.student_id = ? AND me.academic_year = ? AND me.term = ?
        ");
        $stmtMarks->execute([$schoolId, $studentId, $year, $term]);
        $dynamicMarks = $stmtMarks->fetchAll(PDO::FETCH_ASSOC);

        $groupedMarks = [];
        foreach ($dynamicMarks as $dm) {
            $sc = $dm['subject_code'];
            if (!isset($groupedMarks[$sc])) {
                $groupedMarks[$sc] = [
                    'subject_code' => $sc,
                    'subject_name' => $dm['subject_name'],
                    'scores' => [],
                    'total_score' => 0
                ];
            }
            $groupedMarks[$sc]['scores'][$dm['assessment_type_id']] = floatval($dm['score']);
            $groupedMarks[$sc]['total_score'] += floatval($dm['score']);
        }

        // Fallback to legacy marks_entry if no dynamic marks found
        if (empty($groupedMarks)) {
            $stmtLegacy = $conn->prepare("
                SELECT me.subject_code, COALESCE(s.name, me.subject_code) AS subject_name,
                       COALESCE(me.continuous_assessment_mark, 0) AS ca_mark,
                       COALESCE(me.terminal_mark, 0) AS terminal_mark
                FROM marks_entry me
                LEFT JOIN subjects s ON me.subject_code = s.code
                WHERE me.school_id = ? AND me.student_id = ? AND me.academic_year = ? AND me.term = ?
            ");
            $stmtLegacy->execute([$schoolId, $studentId, $year, $term]);
            $legacyMarks = $stmtLegacy->fetchAll(PDO::FETCH_ASSOC);
            foreach ($legacyMarks as $lm) {
                $groupedMarks[$lm['subject_code']] = [
                    'subject_code' => $lm['subject_code'],
                    'subject_name' => $lm['subject_name'],
                    'scores' => ['ca' => floatval($lm['ca_mark']), 'terminal' => floatval($lm['terminal_mark'])],
                    'total_score' => floatval($lm['ca_mark']) + floatval($lm['terminal_mark'])
                ];
            }
            if (!empty($groupedMarks)) {
                $assessmentTypes = [
                    ['id' => 'ca', 'name' => 'CA Mark', 'weight_percent' => 40],
                    ['id' => 'terminal', 'name' => 'Terminal Exam', 'weight_percent' => 60]
                ];
            }
        }

        $subjectMarks = array_values($groupedMarks);
        usort($subjectMarks, function($a, $b) { return strcmp($a['subject_name'], $b['subject_name']); });

        $totalPoints = 0;
        $totalScores = 0;
        $subjectCount = count($subjectMarks);

        foreach ($subjectMarks as &$m) {
            $total = floatval($m['total_score']);
            $m['total_score'] = round($total, 2);
            $totalScores += $total;

            $mGrade = 'F';
            $mRemark = 'Fail';
            $mPoints = 7;

            foreach ($scales as $sc) {
                if ($total >= floatval($sc['min_mark']) && $total <= floatval($sc['max_mark'])) {
                    $mGrade = $sc['grade'];
                    $mRemark = $sc['remark'];
                    $mPoints = intval($sc['points']);
                    break;
                }
            }

            $m['grade'] = $mGrade;
            $m['remark'] = $mRemark;
            $m['points'] = $mPoints;
            $totalPoints += $mPoints;
        }

        // Calculate division result from division_scales
        $stmtDiv = $conn->prepare("SELECT division_name, remark FROM division_scales WHERE level_type = ? AND ? BETWEEN min_points AND max_points LIMIT 1");
        $stmtDiv->execute([$levelType, $totalPoints]);
        $divRow = $stmtDiv->fetch(PDO::FETCH_ASSOC);
        $divisionResult = $divRow ? $divRow['division_name'] : 'Division IV';

        $gpa = $subjectCount > 0 ? round($totalScores / $subjectCount, 2) : 0;

        // Fetch Form Master Comment
        $stmtComment = $conn->prepare("SELECT conduct_comment FROM student_report_comments WHERE school_id = ? AND academic_year = ? AND term = ? AND student_id = ? LIMIT 1");
        $stmtComment->execute([$schoolId, $year, $term, $studentId]);
        $conductComment = $stmtComment->fetchColumn() ?: 'Demonstrates good behavior and consistent academic effort.';

        echo json_encode([
            'success' => true,
            'student' => $student,
            'year' => $year,
            'term' => $term,
            'assessment_types' => $assessmentTypes,
            'subject_marks' => $subjectMarks,
            'summary' => [
                'total_points' => $totalPoints,
                'division' => $divisionResult,
                'gpa_average' => $gpa,
                'subject_count' => $subjectCount
            ],
            'conduct_comment' => $conductComment
        ]);
        exit();
    }

    // SAVE FORM MASTER CONDUCT COMMENT
    if ($action === 'save_conduct_comment') {
        $studentId = $input['student_id'] ?? '';
        $comment = trim($input['conduct_comment'] ?? '');

        if (empty($studentId)) {
            echo json_encode(['success' => false, 'message' => 'student_id is required.']);
            exit();
        }

        $stmt = $conn->prepare("
            INSERT INTO student_report_comments (school_id, academic_year, term, student_id, form_master_id, conduct_comment)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE conduct_comment = VALUES(conduct_comment), form_master_id = VALUES(form_master_id), updated_at = NOW()
        ");
        $stmt->execute([$schoolId, $year, $term, $studentId, $_SESSION['user_id'], $comment]);

        echo json_encode(['success' => true, 'message' => 'Form Master conduct comment saved successfully.']);
        exit();
    }

    // 2. CLASSROOM STREAM PERFORMANCE LEDGER (TASK 4.2)
    if ($action === 'classroom_ledger') {
        $classroomId = intval($_GET['classroom_id'] ?? $input['classroom_id'] ?? 0);
        $streamName = $_GET['stream'] ?? $input['stream'] ?? '';

        if (!$classroomId && !empty($streamName)) {
            $stmtC = $conn->prepare("SELECT id FROM classrooms WHERE (classroom_name = :cname OR CAST(id AS CHAR) = :cname) AND school_id = :sch LIMIT 1");
            $stmtC->execute([':cname' => $streamName, ':sch' => $schoolId]);
            $classroomId = intval($stmtC->fetchColumn());
        }

        if (!$classroomId) {
            echo json_encode(['success' => false, 'message' => 'classroom_id or valid stream required.']);
            exit();
        }

        // Fetch classroom name
        $stmtC = $conn->prepare("SELECT classroom_name FROM classrooms WHERE id = ? AND school_id = ?");
        $stmtC->execute([$classroomId, $schoolId]);
        $cname = $stmtC->fetchColumn();

        // Fetch students in room
        $stmtS = $conn->prepare("
            SELECT u.id AS student_id, u.full_name, u.user_code, u.gender
            FROM student_classroom_allocations sca
            JOIN users u ON sca.student_id = u.id
            WHERE sca.classroom_id = ? AND sca.school_id = ? AND sca.academic_year = ? AND sca.status = 'Active'
            ORDER BY u.full_name ASC
        ");
        $stmtS->execute([$classroomId, $schoolId, $year]);
        $students = $stmtS->fetchAll(PDO::FETCH_ASSOC);

        // Fetch distinct subjects evaluated in room
        $stmtSubj = $conn->prepare("
            WITH unified_marks AS (
                SELECT student_id, subject_code, school_id, academic_year, term
                FROM marks_entry_dynamic
                GROUP BY student_id, subject_code, school_id, academic_year, term
                UNION ALL
                SELECT student_id, subject_code, school_id, academic_year, term
                FROM marks_entry m
                WHERE NOT EXISTS (
                    SELECT 1 FROM marks_entry_dynamic d
                    WHERE d.student_id = m.student_id AND d.subject_code = m.subject_code AND d.academic_year = m.academic_year AND d.term = m.term
                )
            )
            SELECT DISTINCT me.subject_code, COALESCE(s.name, me.subject_code) AS subject_name
            FROM unified_marks me
            JOIN student_classroom_allocations sca ON me.student_id = sca.student_id
            LEFT JOIN subjects s ON me.subject_code = s.code
            WHERE sca.classroom_id = ? AND me.school_id = ? AND me.academic_year = ? AND me.term = ?
            ORDER BY me.subject_code ASC
        ");
        $stmtSubj->execute([$classroomId, $schoolId, $year, $term]);
        $subjects = $stmtSubj->fetchAll(PDO::FETCH_ASSOC);

        // Matrix map: student_id => [ subject_code => total_score ]
        $matrixMap = [];
        $stmtAllMarks = $conn->prepare("
            WITH unified_marks AS (
                SELECT student_id, subject_code, school_id, academic_year, term, SUM(score) AS total_score
                FROM marks_entry_dynamic
                GROUP BY student_id, subject_code, school_id, academic_year, term
                UNION ALL
                SELECT student_id, subject_code, school_id, academic_year, term, (COALESCE(continuous_assessment_mark, 0) + COALESCE(terminal_mark, 0)) AS total_score
                FROM marks_entry m
                WHERE NOT EXISTS (
                    SELECT 1 FROM marks_entry_dynamic d
                    WHERE d.student_id = m.student_id AND d.subject_code = m.subject_code AND d.academic_year = m.academic_year AND d.term = m.term
                )
            )
            SELECT me.student_id, me.subject_code, me.total_score
            FROM unified_marks me
            JOIN student_classroom_allocations sca ON me.student_id = sca.student_id
            WHERE sca.classroom_id = ? AND me.school_id = ? AND me.academic_year = ? AND me.term = ?
        ");
        $stmtAllMarks->execute([$classroomId, $schoolId, $year, $term]);
        $allMarks = $stmtAllMarks->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allMarks as $m) {
            $matrixMap[$m['student_id']][$m['subject_code']] = round(floatval($m['total_score']), 2);
        }

        // Attach scores & failing counts to students
        foreach ($students as &$s) {
            $s['scores'] = $matrixMap[$s['student_id']] ?? [];
            $failingCount = 0;
            $totalSum = 0;
            foreach ($s['scores'] as $scoreVal) {
                $totalSum += $scoreVal;
                if ($scoreVal < 45.0) $failingCount++;
            }
            $s['failing_count'] = $failingCount;
            $s['total_aggregate'] = round($totalSum, 2);
        }

        echo json_encode([
            'success' => true,
            'classroom_name' => $cname,
            'year' => $year,
            'term' => $term,
            'subjects' => $subjects,
            'students' => $students
        ]);
        exit();
    }

    // 3. COMPARATIVE GRADE-WIDE ANALYTICS BOARD (TASK 4.3)
    if ($action === 'comparative_analytics') {
        $gradeId = intval($_GET['grade_id'] ?? $input['grade_id'] ?? 0);

        // Fetch streams in grade or all streams if grade_id = 0
        $stmtStreams = $conn->prepare("
            SELECT c.id AS classroom_id, c.classroom_name, g.name AS grade_name
            FROM classrooms c
            JOIN grades g ON c.grade_id = g.id
            WHERE c.school_id = ? AND c.academic_year = ? AND (? = 0 OR c.grade_id = ?)
            ORDER BY g.order_seq ASC, c.classroom_name ASC
        ");
        $stmtStreams->execute([$schoolId, $year, $gradeId, $gradeId]);
        $streams = $stmtStreams->fetchAll(PDO::FETCH_ASSOC);

        // Stream Comparison: Stream => Pass Rate % and Average Score
        $streamAnalytics = [];
        foreach ($streams as $st) {
            $cid = $st['classroom_id'];
            $stmtStats = $conn->prepare("
                WITH unified_marks AS (
                    SELECT student_id, subject_code, school_id, academic_year, term, SUM(score) AS total_score
                    FROM marks_entry_dynamic
                    GROUP BY student_id, subject_code, school_id, academic_year, term
                    UNION ALL
                    SELECT student_id, subject_code, school_id, academic_year, term, (COALESCE(continuous_assessment_mark, 0) + COALESCE(terminal_mark, 0)) AS total_score
                    FROM marks_entry m
                    WHERE NOT EXISTS (
                        SELECT 1 FROM marks_entry_dynamic d
                        WHERE d.student_id = m.student_id AND d.subject_code = m.subject_code AND d.academic_year = m.academic_year AND d.term = m.term
                    )
                )
                SELECT COUNT(*) AS total_entries,
                       SUM(CASE WHEN me.total_score >= 45 THEN 1 ELSE 0 END) AS pass_entries,
                       AVG(me.total_score) AS avg_score
                FROM unified_marks me
                JOIN student_classroom_allocations sca ON me.student_id = sca.student_id
                WHERE sca.classroom_id = ? AND me.school_id = ? AND me.academic_year = ? AND me.term = ?
            ");
            $stmtStats->execute([$cid, $schoolId, $year, $term]);
            $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

            $tot = intval($stats['total_entries']);
            $pass = intval($stats['pass_entries']);
            $passRate = $tot > 0 ? round(($pass / $tot) * 100, 1) : 0;
            $avgScore = round(floatval($stats['avg_score']), 1);

            $streamAnalytics[] = [
                'classroom_id' => $cid,
                'classroom_name' => $st['classroom_name'],
                'grade_name' => $st['grade_name'],
                'total_entries' => $tot,
                'pass_entries' => $pass,
                'pass_rate_percent' => $passRate,
                'average_score' => $avgScore
            ];
        }

        // Gender Comparison: Male vs Female pass rates and averages across the school / grade
        $stmtGender = $conn->prepare("
            WITH unified_marks AS (
                SELECT student_id, subject_code, school_id, academic_year, term, SUM(score) AS total_score
                FROM marks_entry_dynamic
                GROUP BY student_id, subject_code, school_id, academic_year, term
                UNION ALL
                SELECT student_id, subject_code, school_id, academic_year, term, (COALESCE(continuous_assessment_mark, 0) + COALESCE(terminal_mark, 0)) AS total_score
                FROM marks_entry m
                WHERE NOT EXISTS (
                    SELECT 1 FROM marks_entry_dynamic d
                    WHERE d.student_id = m.student_id AND d.subject_code = m.subject_code AND d.academic_year = m.academic_year AND d.term = m.term
                )
            )
            SELECT CASE WHEN LOWER(u.gender) IN ('m', 'male') THEN 'Male' ELSE 'Female' END AS normalized_gender,
                   COUNT(me.subject_code) AS total_entries,
                   SUM(CASE WHEN me.total_score >= 45 THEN 1 ELSE 0 END) AS pass_entries,
                   AVG(me.total_score) AS avg_score
            FROM unified_marks me
            JOIN users u ON me.student_id = u.id
            JOIN student_classroom_allocations sca ON me.student_id = sca.student_id
            JOIN classrooms c ON sca.classroom_id = c.id
            WHERE me.school_id = ? AND me.academic_year = ? AND me.term = ? AND (? = 0 OR c.grade_id = ?)
            GROUP BY CASE WHEN LOWER(u.gender) IN ('m', 'male') THEN 'Male' ELSE 'Female' END
        ");
        $stmtGender->execute([$schoolId, $year, $term, $gradeId, $gradeId]);
        $genderRows = $stmtGender->fetchAll(PDO::FETCH_ASSOC);

        $genderAnalytics = [];
        foreach ($genderRows as $gr) {
            $gLabel = ($gr['normalized_gender'] === 'Male') ? 'Male (Boys)' : 'Female (Girls)';
            $tot = intval($gr['total_entries']);
            $pass = intval($gr['pass_entries']);
            $passRate = $tot > 0 ? round(($pass / $tot) * 100, 1) : 0;
            $avgScore = round(floatval($gr['avg_score']), 1);

            $genderAnalytics[] = [
                'gender' => $gLabel,
                'total_entries' => $tot,
                'pass_entries' => $pass,
                'pass_rate_percent' => $passRate,
                'average_score' => $avgScore
            ];
        }

        echo json_encode([
            'success' => true,
            'year' => $year,
            'term' => $term,
            'stream_analytics' => $streamAnalytics,
            'gender_analytics' => $genderAnalytics
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
