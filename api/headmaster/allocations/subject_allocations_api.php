<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId && ($_SESSION['role'] ?? '') === 'super_admin') {
    $row = $conn->query("SELECT id FROM schools LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $schoolId = $row['id'] ?? null;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? 'analytics';
$year = $_GET['year'] ?? $input['year'] ?? date('Y');

try {
    // 1. GET: School-wide Subject Allocation Analytics & Per-Subject Summary Table
    if ($method === 'GET' && $action === 'analytics') {
        // Total Stream-Subject slots created
        $stmtTotal = $conn->prepare("
            SELECT COUNT(*) FROM stream_subjects
            WHERE school_id = ? AND academic_year_id = ?
        ");
        $stmtTotal->execute([$schoolId, $year]);
        $totalPeriodSlots = intval($stmtTotal->fetchColumn());

        // Covered Stream-Subject slots (with at least 1 assigned teacher)
        $stmtCovered = $conn->prepare("
            SELECT COUNT(DISTINCT class_stream_id, subject_code)
            FROM teacher_subject_assignments
            WHERE school_id = ? AND academic_year_id = ? AND teacher_id IS NOT NULL AND teacher_id != ''
        ");
        $stmtCovered->execute([$schoolId, $year]);
        $coveredPeriodSlots = intval($stmtCovered->fetchColumn());
        $unstaffedPeriodSlots = max(0, $totalPeriodSlots - $coveredPeriodSlots);

        // Per-Subject Analytics Table
        $stmtSubjects = $conn->prepare("
            SELECT s.code AS subject_code, s.name AS subject_name,
                   el.code AS level_code,
                   COUNT(DISTINCT ss.class_stream_id) AS total_assigned_streams,
                   COUNT(DISTINCT tsa.teacher_id) AS assigned_teachers_count,
                   GROUP_CONCAT(DISTINCT u.full_name SEPARATOR ', ') AS teacher_names_list
            FROM subjects s
            LEFT JOIN education_levels el ON UPPER(el.code) = UPPER(s.level_code)
            LEFT JOIN stream_subjects ss ON (ss.subject_code = s.code AND ss.school_id = ? AND ss.academic_year_id = ?)
            LEFT JOIN teacher_subject_assignments tsa ON (tsa.class_stream_id = ss.class_stream_id AND tsa.subject_code = s.code AND tsa.school_id = ? AND tsa.academic_year_id = ? AND tsa.teacher_id IS NOT NULL)
            LEFT JOIN users u ON tsa.teacher_id = u.id
            GROUP BY s.code, s.name, el.code
            ORDER BY s.name ASC
        ");
        $stmtSubjects->execute([$schoolId, $year, $schoolId, $year]);
        $perSubjectAnalytics = $stmtSubjects->fetchAll(PDO::FETCH_ASSOC);

        // Unstaffed Subject Periods List for Alert Banner
        $stmtUnstaffed = $conn->prepare("
            SELECT ss.subject_code, ss.subject_name, c.classroom_name, g.name AS grade_name
            FROM stream_subjects ss
            JOIN classrooms c ON ss.class_stream_id = c.id
            JOIN grades g ON c.grade_id = g.id
            WHERE ss.school_id = ? AND ss.academic_year_id = ?
              AND NOT EXISTS (
                  SELECT 1 FROM teacher_subject_assignments tsa
                  WHERE tsa.school_id = ss.school_id
                    AND tsa.academic_year_id = ss.academic_year_id
                    AND tsa.class_stream_id = ss.class_stream_id
                    AND tsa.subject_code = ss.subject_code
                    AND tsa.teacher_id IS NOT NULL AND tsa.teacher_id != ''
              )
            ORDER BY g.order_seq, c.classroom_name, ss.subject_name
        ");
        $stmtUnstaffed->execute([$schoolId, $year]);
        $unstaffedList = $stmtUnstaffed->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'analytics' => [
                'total_period_slots'     => $totalPeriodSlots,
                'covered_period_slots'   => $coveredPeriodSlots,
                'unstaffed_period_slots' => $unstaffedPeriodSlots,
                'unstaffed_list'         => $unstaffedList
            ],
            'per_subject_summary' => $perSubjectAnalytics
        ]);
        exit();
    }

    // 2. GET: Stream Subjects & Multi-Teacher Assignments for Matrix Editing
    if ($method === 'GET' && $action === 'stream_subjects') {
        $streamId = trim($_GET['stream_id'] ?? '');
        if (!$streamId) {
            echo json_encode(['success' => false, 'message' => 'stream_id is required.']);
            exit();
        }

        // Fetch subjects for this stream
        $stmtS = $conn->prepare("
            SELECT ss.subject_code, ss.subject_name, ss.is_core
            FROM stream_subjects ss
            WHERE ss.school_id = ? AND ss.class_stream_id = ? AND ss.academic_year_id = ?
            ORDER BY ss.is_core DESC, ss.subject_name ASC
        ");
        $stmtS->execute([$schoolId, $streamId, $year]);
        $subjects = $stmtS->fetchAll(PDO::FETCH_ASSOC);

        // Fetch assigned teacher IDs for each subject (multiple rows possible for co-teaching)
        $stmtA = $conn->prepare("
            SELECT tsa.subject_code, tsa.teacher_id, u.full_name, u.user_code
            FROM teacher_subject_assignments tsa
            JOIN users u ON tsa.teacher_id = u.id
            WHERE tsa.school_id = ? AND tsa.class_stream_id = ? AND tsa.academic_year_id = ? AND tsa.teacher_id IS NOT NULL
        ");
        $stmtA->execute([$schoolId, $streamId, $year]);
        $assignmentsRaw = $stmtA->fetchAll(PDO::FETCH_ASSOC);

        $assignments = [];
        foreach ($assignmentsRaw as $row) {
            $assignments[$row['subject_code']][] = [
                'id' => $row['teacher_id'],
                'name' => $row['full_name'],
                'code' => $row['user_code']
            ];
        }

        // Fetch Teachers list
        $stmtT = $conn->prepare("SELECT id, full_name, user_code FROM users WHERE school_id = ? AND role IN ('teacher','tenant_admin') AND status = 'active' ORDER BY full_name");
        $stmtT->execute([$schoolId]);
        $teachers = $stmtT->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'subjects' => $subjects,
            'assignments' => $assignments,
            'teachers' => $teachers
        ]);
        exit();
    }

    // 3. POST: Save Co-Teaching Subject Period Teachers (Multiple Teachers per Subject)
    if ($method === 'POST' && $action === 'save_stream_teachers') {
        $streamId    = trim($input['class_stream_id'] ?? '');
        $subjectCode = trim($input['subject_code'] ?? '');
        $teacherIds  = $input['teacher_ids'] ?? []; // Array of teacher IDs or single ID

        if (!is_array($teacherIds)) {
            $teacherIds = !empty($teacherIds) ? [$teacherIds] : [];
        }

        if (!$streamId || !$subjectCode) {
            echo json_encode(['success' => false, 'message' => 'class_stream_id and subject_code are required.']);
            exit();
        }

        $conn->beginTransaction();

        // Clear existing assignments for this stream+subject slot
        $stmtDel = $conn->prepare("DELETE FROM teacher_subject_assignments WHERE school_id = ? AND academic_year_id = ? AND class_stream_id = ? AND subject_code = ?");
        $stmtDel->execute([$schoolId, $year, $streamId, $subjectCode]);

        // Insert selected teachers
        $count = 0;
        $stmtIns = $conn->prepare("INSERT INTO teacher_subject_assignments (school_id, academic_year_id, class_stream_id, subject_code, teacher_id) VALUES (?, ?, ?, ?, ?)");
        foreach ($teacherIds as $tid) {
            $tid = trim($tid);
            if (empty($tid)) continue;
            $stmtIns->execute([$schoolId, $year, $streamId, $subjectCode, $tid]);
            $count++;
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'assigned_count' => $count,
            'message' => $count > 0 ? "Saved {$count} teacher(s) for {$subjectCode}." : "Unassigned teachers for {$subjectCode}."
        ]);
        exit();
    }

    // 4. POST: Batch Assign Teacher across an entire Education Level
    if ($method === 'POST' && $action === 'batch_level_assign') {
        $teacherId   = trim($input['teacher_id'] ?? '');
        $subjectCode = trim($input['subject_code'] ?? '');
        $levelId     = intval($input['level_id'] ?? 0);
        $streamIds   = $input['stream_ids'] ?? []; // Optional explicit array of stream IDs, or all streams in level

        if (!$teacherId || !$subjectCode || !$levelId) {
            echo json_encode(['success' => false, 'message' => 'teacher_id, subject_code, and level_id are required.']);
            exit();
        }

        // If no explicit stream_ids sent, find all classroom streams under this education level
        if (empty($streamIds)) {
            $stmtRooms = $conn->prepare("
                SELECT c.id FROM classrooms c
                JOIN grades g ON c.grade_id = g.id
                WHERE c.school_id = ? AND c.academic_year = ? AND g.level_id = ?
            ");
            $stmtRooms->execute([$schoolId, $year, $levelId]);
            $streamIds = $stmtRooms->fetchAll(PDO::FETCH_COLUMN);
        }

        if (empty($streamIds)) {
            echo json_encode(['success' => false, 'message' => 'No active classroom streams found for this Education Level.']);
            exit();
        }

        $conn->beginTransaction();
        $stmtIns = $conn->prepare("
            INSERT INTO teacher_subject_assignments (school_id, academic_year_id, class_stream_id, subject_code, teacher_id)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id)
        ");

        $assignedStreams = 0;
        foreach ($streamIds as $sid) {
            $stmtIns->execute([$schoolId, $year, $sid, $subjectCode, $teacherId]);
            $assignedStreams++;
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'assigned_streams' => $assignedStreams,
            'message' => "Successfully assigned teacher across {$assignedStreams} classroom stream(s) in this Education Level."
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
