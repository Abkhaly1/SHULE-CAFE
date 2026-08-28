<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) && !isset($_GET['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;
$schoolId = $_SESSION['school_id'] ?? $_GET['school_id'] ?? null;

if (empty($schoolId) && !empty($userId)) {
    $uStmt = $conn->prepare("SELECT school_id FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$userId]);
    $schoolId = $uStmt->fetchColumn() ?: null;
}

if (empty($schoolId)) {
    $row = $conn->query('SELECT id FROM schools ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $schoolId = $row['id'] ?? null;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';
$year = trim($_GET['year'] ?? $input['year'] ?? date('Y'));
$term = trim($_GET['term'] ?? $input['term'] ?? 'Term 1');
if (in_array(strtolower($term), ['1', 'term1', 'term 1', 'first term', 'first'])) $term = 'Term 1';
if (in_array(strtolower($term), ['2', 'term2', 'term 2', 'second term', 'second'])) $term = 'Term 2';

try {
    // Self-healing table migrations
    $conn->exec("
        CREATE TABLE IF NOT EXISTS `student_report_comments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `school_id` VARCHAR(36) NOT NULL,
            `academic_year` VARCHAR(10) NOT NULL,
            `term` VARCHAR(20) NOT NULL,
            `student_id` VARCHAR(36) NOT NULL,
            `form_master_id` VARCHAR(36) DEFAULT NULL,
            `conduct_comment` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_report_comment` (`school_id`, `academic_year`, `term`, `student_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 1. INDIVIDUAL 360° STUDENT PROGRESS REPORT CARD (TASK 4.1)
    if ($action === 'student_report_card') {
        $studentId = trim($_GET['student_id'] ?? $input['student_id'] ?? '');
        if (empty($studentId)) {
            echo json_encode(['success' => false, 'message' => 'student_id is required.']);
            exit();
        }

        // Student details & classroom
        $stmtStudent = $conn->prepare("
            SELECT u.id, u.full_name, u.user_code, u.gender, COALESCE(c.classroom_name, 'Grade-Wide') AS classroom_name, COALESCE(c.id, 0) AS classroom_id, COALESCE(g.name, 'Secondary') AS grade_name, COALESCE(el.name, 'O-Level') AS level_type
            FROM users u
            LEFT JOIN student_classroom_allocations sca ON (sca.student_id = u.id)
            LEFT JOIN classrooms c ON sca.classroom_id = c.id
            LEFT JOIN grades g ON (c.grade_id = g.id OR u.grade_id = g.id)
            LEFT JOIN education_levels el ON g.level_id = el.id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmtStudent->execute([$studentId]);
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

        if (empty($scales)) {
            $scales = [
                ['min_mark' => 75, 'max_mark' => 100, 'grade' => 'A', 'points' => 1, 'remark' => 'Excellent'],
                ['min_mark' => 65, 'max_mark' => 74.99, 'grade' => 'B', 'points' => 2, 'remark' => 'Very Good'],
                ['min_mark' => 45, 'max_mark' => 64.99, 'grade' => 'C', 'points' => 3, 'remark' => 'Good'],
                ['min_mark' => 30, 'max_mark' => 44.99, 'grade' => 'D', 'points' => 4, 'remark' => 'Satisfactory'],
                ['min_mark' => 0,  'max_mark' => 29.99, 'grade' => 'F', 'points' => 5, 'remark' => 'Fail']
            ];
        }

        // Helper to process marks for a specific term
        $processTermMarks = function($targetTerm) use ($conn, $schoolId, $studentId, $year, $scales, $levelType) {
            $stmtM = $conn->prepare("
                SELECT me.subject_code, COALESCE(s.name, me.subject_code) AS subject_name,
                       COALESCE(me.score, me.raw_score, 0) AS score
                FROM marks_entry_dynamic me
                LEFT JOIN subjects s ON me.subject_code = s.code
                WHERE me.student_id = ? AND me.academic_year = ? AND me.term = ?
                ORDER BY subject_name ASC
            ");
            $stmtM->execute([$studentId, $year, $targetTerm]);
            $rawRows = $stmtM->fetchAll(PDO::FETCH_ASSOC);

            $items = [];
            $totalScore = 0;
            $totalPoints = 0;

            foreach ($rawRows as $r) {
                $score = floatval($r['score']);
                $totalScore += $score;

                $mGrade = 'F';
                $mRemark = 'Fail';
                $mPoints = 5;

                foreach ($scales as $sc) {
                    if ($score >= floatval($sc['min_mark']) && $score <= floatval($sc['max_mark'])) {
                        $mGrade = $sc['grade'];
                        $mRemark = $sc['remark'];
                        $mPoints = intval($sc['points']);
                        break;
                    }
                }

                $totalPoints += $mPoints;
                $items[] = [
                    'subject_code' => $r['subject_code'],
                    'subject_name' => $r['subject_name'],
                    'score' => round($score, 1),
                    'grade' => $mGrade,
                    'points' => $mPoints,
                    'remark' => $mRemark
                ];
            }

            $subCount = count($items);
            $avg = $subCount > 0 ? round($totalScore / $subCount, 2) : 0;

            // Division calculation
            $stmtDiv = $conn->prepare("SELECT division_name, remark FROM division_scales WHERE level_type = ? AND ? BETWEEN min_points AND max_points LIMIT 1");
            $stmtDiv->execute([$levelType, $totalPoints]);
            $divRow = $stmtDiv->fetch(PDO::FETCH_ASSOC);
            $division = $divRow ? $divRow['division_name'] : ($subCount > 0 ? ($avg >= 45 ? 'Division III' : 'Division IV') : '-');

            return [
                'term' => $targetTerm,
                'has_marks' => ($subCount > 0),
                'subjects' => $items,
                'summary' => [
                    'total_score' => round($totalScore, 1),
                    'average_score' => $avg,
                    'total_points' => $totalPoints,
                    'division' => $division,
                    'subject_count' => $subCount
                ]
            ];
        };

        $t1Data = $processTermMarks('Term 1');
        $t2Data = $processTermMarks('Term 2');

        // Annual Cumulative Metrics
        $annualAvg = 0;
        if ($t1Data['has_marks'] && $t2Data['has_marks']) {
            $annualAvg = round(($t1Data['summary']['average_score'] + $t2Data['summary']['average_score']) / 2, 2);
        } elseif ($t1Data['has_marks']) {
            $annualAvg = $t1Data['summary']['average_score'];
        } elseif ($t2Data['has_marks']) {
            $annualAvg = $t2Data['summary']['average_score'];
        }

        $activeTermData = ($term === 'Term 2') ? $t2Data : $t1Data;

        // Fetch Form Master Comment
        $conductComment = 'Demonstrates good behavior and consistent academic effort.';
        try {
            $stmtComment = $conn->prepare("SELECT conduct_comment FROM student_report_comments WHERE academic_year = ? AND term = ? AND student_id = ? LIMIT 1");
            $stmtComment->execute([$year, $term, $studentId]);
            $cRes = $stmtComment->fetchColumn();
            if (!empty($cRes)) $conductComment = $cRes;
        } catch (Exception $ce) {}

        echo json_encode([
            'success' => true,
            'student' => $student,
            'year' => $year,
            'term' => $term,
            'term1_results' => $t1Data,
            'term2_results' => $t2Data,
            'annual_summary' => [
                'term1_avg' => $t1Data['summary']['average_score'],
                'term2_avg' => $t2Data['summary']['average_score'],
                'annual_average' => $annualAvg,
                'overall_points' => $activeTermData['summary']['total_points'],
                'overall_division' => $activeTermData['summary']['division']
            ],
            // Backwards compatibility keys
            'subject_marks' => $activeTermData['subjects'],
            'summary' => $activeTermData['summary'],
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
        $stmt->execute([$schoolId, $year, $term, $studentId, $userId, $comment]);

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
                SELECT COUNT(*) AS total_entries,
                       SUM(CASE WHEN me.score >= 45 THEN 1 ELSE 0 END) AS pass_entries,
                       AVG(me.score) AS avg_score
                FROM marks_entry_dynamic me
                JOIN student_classroom_allocations sca ON me.student_id = sca.student_id
                WHERE sca.classroom_id = ? AND me.academic_year = ? AND me.term = ?
            ");
            $stmtStats->execute([$cid, $year, $term]);
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
            SELECT CASE WHEN LOWER(u.gender) IN ('m', 'male') THEN 'Male' ELSE 'Female' END AS normalized_gender,
                   COUNT(me.subject_code) AS total_entries,
                   SUM(CASE WHEN me.score >= 45 THEN 1 ELSE 0 END) AS pass_entries,
                   AVG(me.score) AS avg_score
            FROM marks_entry_dynamic me
            JOIN users u ON me.student_id = u.id
            LEFT JOIN student_classroom_allocations sca ON me.student_id = sca.student_id
            LEFT JOIN classrooms c ON sca.classroom_id = c.id
            WHERE me.academic_year = ? AND me.term = ? AND (? = 0 OR c.grade_id = ? OR u.grade_id = ?)
            GROUP BY CASE WHEN LOWER(u.gender) IN ('m', 'male') THEN 'Male' ELSE 'Female' END
        ");
        $stmtGender->execute([$year, $term, $gradeId, $gradeId, $gradeId]);
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
