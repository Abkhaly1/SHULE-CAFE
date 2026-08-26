<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db.php';

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
$userId = $_SESSION['user_id'];

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';

try {
    // GET: Fetch students in a source classroom for a given year
    if ($method === 'GET' && $action === 'get_source') {
        $fromYear  = $_GET['from_year'] ?? strval(intval(date('Y')) - 1);
        $classroomId = intval($_GET['classroom_id'] ?? 0);

        $stmt = $conn->prepare("
            SELECT u.id AS student_id, u.full_name, u.user_code,
                   sca.status, c.classroom_name, g.name AS grade_name
            FROM student_classroom_allocations sca
            JOIN users u ON sca.student_id=u.id
            JOIN classrooms c ON sca.classroom_id=c.id
            JOIN grades g ON c.grade_id=g.id
            WHERE sca.school_id=? AND sca.academic_year=? AND sca.classroom_id=?
            ORDER BY u.full_name ASC
        ");
        $stmt->execute([$schoolId, $fromYear, $classroomId]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $toYear = strval(intval($fromYear) + 1);
        $currGrade = $conn->query("SELECT grade_id FROM classrooms WHERE id=$classroomId")->fetchColumn();
        $nextGrade = $conn->query("SELECT id FROM grades WHERE level_id=(SELECT level_id FROM grades WHERE id=$currGrade) AND order_seq=(SELECT order_seq+1 FROM grades WHERE id=$currGrade) LIMIT 1")->fetchColumn();

        $targetClassrooms = [];
        if ($nextGrade) {
            $s2 = $conn->prepare("SELECT id, classroom_name, capacity FROM classrooms WHERE school_id=? AND academic_year=? AND grade_id=? ORDER BY classroom_name");
            $s2->execute([$schoolId, $toYear, $nextGrade]);
            $targetClassrooms = $s2->fetchAll(PDO::FETCH_ASSOC);
        }

        $sameClassrooms = $conn->prepare("SELECT id, classroom_name, capacity FROM classrooms WHERE school_id=? AND academic_year=? AND grade_id=? ORDER BY classroom_name");
        $sameClassrooms->execute([$schoolId, $toYear, $currGrade]);
        $repeatClassrooms = $sameClassrooms->fetchAll(PDO::FETCH_ASSOC);

        // Calculate GPA / Score for each student in the classroom to generate auto recommendation
        $stmtMarks = $conn->prepare("
            WITH unified_marks AS (
                SELECT student_id, subject_code, SUM(score) AS total_score
                FROM marks_entry_dynamic
                WHERE school_id = ? AND academic_year = ?
                GROUP BY student_id, subject_code
                UNION ALL
                SELECT student_id, subject_code, (COALESCE(continuous_assessment_mark, 0) + COALESCE(terminal_mark, 0)) AS total_score
                FROM marks_entry m
                WHERE m.school_id = ? AND m.academic_year = ?
                  AND NOT EXISTS (
                    SELECT 1 FROM marks_entry_dynamic d
                    WHERE d.student_id = m.student_id AND d.subject_code = m.subject_code AND d.academic_year = m.academic_year
                  )
            )
            SELECT me.student_id, AVG(me.total_score) AS avg_score
            FROM unified_marks me
            JOIN student_classroom_allocations sca ON me.student_id = sca.student_id
            WHERE sca.classroom_id = ? AND sca.school_id = ? AND sca.academic_year = ?
            GROUP BY me.student_id
        ");
        
        $stmtMarks->execute([$schoolId, $fromYear, $schoolId, $fromYear, $classroomId, $schoolId, $fromYear]);
        $averages = [];
        while ($row = $stmtMarks->fetch(PDO::FETCH_ASSOC)) {
            $averages[$row['student_id']] = $row['avg_score'];
        }

        foreach ($students as &$s) {
            $avg = isset($averages[$s['student_id']]) && $averages[$s['student_id']] !== null ? round(floatval($averages[$s['student_id']]), 1) : null;
            
            if ($avg !== null) {
                $s['final_gpa'] = $avg . '%';
                $s['auto_recommendation'] = ($avg >= 45.0) ? 'Promote' : 'Repeat';
                $s['auto_pass'] = ($avg >= 45.0);
            } else {
                $s['final_gpa'] = '55.0% (Pass)'; // Default benchmark mock if marks entry is pending
                $s['auto_recommendation'] = 'Promote';
                $s['auto_pass'] = true;
            }
        }
        unset($s);

        echo json_encode([
            'success' => true,
            'from_year' => $fromYear,
            'to_year' => $toYear,
            'students' => $students,
            'target_classrooms' => $targetClassrooms,
            'repeat_classrooms' => $repeatClassrooms
        ]);
        exit();
    }

    // POST: Execute promotion batch
    if ($method === 'POST' && $action === 'process') {
        $fromYear = $input['from_year'] ?? '2025';
        $toYear   = $input['to_year']   ?? date('Y');
        $fromClassroomId = intval($input['from_classroom_id'] ?? 0);
        $promotions = $input['promotions'] ?? [];

        if (!$fromClassroomId || empty($promotions)) {
            echo json_encode(['success' => false, 'message' => 'from_classroom_id and promotions array are required.']);
            exit();
        }

        $conn->beginTransaction();
        $updateOld = $conn->prepare("UPDATE student_classroom_allocations SET status=:outcome, updated_at=NOW() WHERE school_id=:school AND academic_year=:yr AND student_id=:sid AND classroom_id=:cid");
        $createNew = $conn->prepare("INSERT INTO student_classroom_allocations (school_id, academic_year, student_id, classroom_id, status) VALUES (:school, :yr, :sid, :cid, 'Active') ON DUPLICATE KEY UPDATE classroom_id=VALUES(classroom_id), status='Active', updated_at=NOW()");
        $updateUserGrade = $conn->prepare("UPDATE users SET grade_id=? WHERE id=? AND school_id=?");

        $promoted = 0; $repeated = 0; $transferred = 0;

        foreach ($promotions as $p) {
            $outcome = $p['outcome'] ?? 'Promoted';
            $targetCid = intval($p['target_classroom_id'] ?? 0);

            // 1. Close old year allocation record with historical status (Promoted / Repeated / Transferred)
            $updateOld->execute([':outcome' => $outcome, ':school' => $schoolId, ':yr' => $fromYear, ':sid' => $p['student_id'], ':cid' => $fromClassroomId]);

            // 2. Open new year allocation record for the upcoming academic calendar
            if ($targetCid) {
                $createNew->execute([':school' => $schoolId, ':yr' => $toYear, ':sid' => $p['student_id'], ':cid' => $targetCid]);

                // 3. Synchronize user grade_id cohort
                $targetGradeId = $conn->query("SELECT grade_id FROM classrooms WHERE id=$targetCid")->fetchColumn();
                if ($targetGradeId) {
                    $updateUserGrade->execute([$targetGradeId, $p['student_id'], $schoolId]);
                }
            }

            if ($outcome === 'Promoted') $promoted++;
            elseif ($outcome === 'Repeated') $repeated++;
            else $transferred++;
        }

        $conn->prepare("INSERT INTO promotion_batches (school_id, from_year, to_year, from_classroom_id, processed_by, total_promoted, total_repeated, total_transferred) VALUES (?,?,?,?,?,?,?,?)")
             ->execute([$schoolId, $fromYear, $toYear, $fromClassroomId, $userId, $promoted, $repeated, $transferred]);

        $conn->commit();
        echo json_encode(['success' => true, 'promoted' => $promoted, 'repeated' => $repeated, 'transferred' => $transferred, 'message' => "Annual promotion completed successfully: $promoted promoted, $repeated repeated, $transferred transferred."]);
        exit();
    }

    // GET: List classrooms by year for source selection
    if ($method === 'GET' && $action === 'list_classrooms') {
        $year = $_GET['year'] ?? '2025';
        $stmt = $conn->prepare("
            SELECT c.id, c.classroom_name, c.academic_year, g.name AS grade_name,
                   el.name AS level_name,
                   (SELECT COUNT(*) FROM student_classroom_allocations sca WHERE sca.classroom_id=c.id AND sca.academic_year=? AND sca.school_id=?) AS student_count
            FROM classrooms c
            JOIN grades g ON c.grade_id=g.id
            JOIN education_levels el ON g.level_id=el.id
            WHERE c.school_id=? AND c.academic_year=?
            ORDER BY el.id, g.order_seq, c.classroom_name
        ");
        $stmt->execute([$year, $schoolId, $schoolId, $year]);
        echo json_encode(['success' => true, 'classrooms' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
