<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/TeacherAllocationEngine.php';

use Headmaster\Academics\TeacherAllocationEngine;

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId && ($_SESSION['role'] ?? '') === 'super_admin') {
    $first = $conn->query("SELECT id FROM schools LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $schoolId = $first['id'] ?? null;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? 'directory';
$year = $_GET['year'] ?? $input['year'] ?? date('Y');

$engine = new TeacherAllocationEngine($conn);

try {
    // 1. Directory: list teachers with simple counts and qualified subjects list
    if ($method === 'GET' && $action === 'directory') {
        $stmt = $conn->prepare("SELECT id, full_name, user_code, department FROM users WHERE school_id = ? AND role IN ('teacher','tenant_admin') AND status = 'active' ORDER BY full_name ASC");
        $stmt->execute([$schoolId]);
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch qualifications for each teacher
        foreach ($teachers as &$t) {
            $ts = $conn->prepare("
                SELECT DISTINCT COALESCE(s.name, s.code, sas.subject_name, tsq.subject_id) AS subject_name, COALESCE(s.code, sas.subject_code, tsq.subject_id) AS subject_code
                FROM teacher_subject_qualifications tsq
                LEFT JOIN subjects s ON tsq.subject_id = s.id
                LEFT JOIN school_approved_subjects sas ON (tsq.subject_id = sas.subject_code AND sas.school_id = ?)
                WHERE tsq.teacher_id = ?
            ");
            $ts->execute([$schoolId, $t['id']]);
            $quals = $ts->fetchAll(PDO::FETCH_ASSOC);

            $t['qualified_subjects'] = $quals;
            $t['qualified_subjects_count'] = count($quals);

            $tc = $conn->prepare("SELECT COUNT(*) FROM teacher_classroom_assignments WHERE teacher_id = ? AND academic_year_id = ?");
            $tc->execute([$t['id'], $year]);
            $t['classroom_assignments_count'] = intval($tc->fetchColumn());
        }

        echo json_encode(['success' => true, 'teachers' => $teachers]);
        exit();
    }

    // 2. Profile: teacher details, qualifications, classroom assignments, implicit grade/classroom map
    if ($method === 'GET' && $action === 'profile') {
        $teacherId = $_GET['teacher_id'] ?? '';
        if (!$teacherId) { echo json_encode(['success'=>false,'message'=>'teacher_id required']); exit(); }

        $stmt = $conn->prepare("SELECT id, full_name, user_code, department, email FROM users WHERE id = ? AND school_id = ?");
        $stmt->execute([$teacherId, $schoolId]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$teacher) { echo json_encode(['success'=>false,'message'=>'Teacher not found']); exit(); }

        $qual = $engine->getTeacherQualifications($teacherId, $schoolId);
        $classAssign = $engine->getClassroomAssignments($teacherId, $year, $schoolId);
        $implicit = $engine->getImplicitGradesAndAvailableClassrooms($teacherId, $year, $schoolId);

        // Fetch active education levels for this school
        $stmtSel = $conn->prepare("SELECT level_code, level_name FROM school_education_levels WHERE school_id = ? AND status = 'active' ORDER BY id ASC");
        $stmtSel->execute([$schoolId]);
        $activeSchoolLevels = $stmtSel->fetchAll(PDO::FETCH_ASSOC);

        // Master subjects strictly registered in the school's active Academic Curriculum for 2026 for assigned grades
        $stmtSubj = $conn->prepare("
            SELECT DISTINCT COALESCE(s.id, gs.subject_code) AS id, gs.subject_code AS code, gs.subject_name AS name, COALESCE(el.code, sas.level_code, s.level_type, 'O-LEVEL') AS level_code
            FROM grade_subjects gs
            JOIN grades g ON gs.grade_id = g.id
            JOIN education_levels el ON g.level_id = el.id
            LEFT JOIN subjects s ON (s.school_id = gs.school_id AND s.code = gs.subject_code)
            LEFT JOIN school_approved_subjects sas ON (sas.school_id = gs.school_id AND sas.subject_code = gs.subject_code)
            WHERE gs.school_id = ? AND gs.academic_year = ?
            ORDER BY level_code ASC, gs.subject_name ASC
        ");
        $stmtSubj->execute([$schoolId, $year]);
        $allSubjects = $stmtSubj->fetchAll(PDO::FETCH_ASSOC);

        // Fallback: If no grade_subjects configured yet for academic year, use active school_approved_subjects
        if (empty($allSubjects)) {
            $stmtSubj2 = $conn->prepare("
                SELECT DISTINCT COALESCE(s.id, sas.subject_code) AS id, sas.subject_code AS code, sas.subject_name AS name, sas.level_code
                FROM school_approved_subjects sas
                LEFT JOIN subjects s ON (s.school_id = sas.school_id AND s.code = sas.subject_code)
                WHERE sas.school_id = ? AND sas.status = 'active'
                ORDER BY sas.level_code ASC, sas.subject_name ASC
            ");
            $stmtSubj2->execute([$schoolId]);
            $allSubjects = $stmtSubj2->fetchAll(PDO::FETCH_ASSOC);
        }

        // Classrooms created for the academic year
        $stmtRooms = $conn->prepare("
            SELECT c.id AS classroom_id, c.classroom_name, c.grade_id, g.name AS grade_name, el.code AS level_code
            FROM classrooms c
            JOIN grades g ON c.grade_id = g.id
            JOIN education_levels el ON g.level_id = el.id
            WHERE c.school_id = ? AND c.academic_year = ?
            ORDER BY g.order_seq ASC, c.classroom_name ASC
        ");
        $stmtRooms->execute([$schoolId, $year]);
        $allRooms = $stmtRooms->fetchAll(PDO::FETCH_ASSOC);

        // Grade subjects mapping
        $stmtGS = $conn->prepare("
            SELECT gs.grade_id, gs.subject_code, g.name AS grade_name
            FROM grade_subjects gs
            JOIN grades g ON gs.grade_id = g.id
            WHERE gs.school_id = ? AND gs.academic_year = ?
        ");
        $stmtGS->execute([$schoolId, $year]);
        $gradeSubjects = $stmtGS->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'teacher' => $teacher,
            'qualifications' => $qual,
            'classroom_assignments' => $classAssign,
            'implicit_map' => $implicit,
            'active_school_levels' => $activeSchoolLevels,
            'all_subjects' => $allSubjects,
            'all_rooms' => $allRooms,
            'grade_subjects' => $gradeSubjects
        ]);
        exit();
    }

    // 3. POST: add qualification
    if ($method === 'POST' && $action === 'add_qualification') {
        $teacherId = $input['teacher_id'] ?? '';
        $subjectCode = trim($input['subject_code'] ?? '');
        if (!$teacherId || !$subjectCode) { echo json_encode(['success'=>false,'message'=>'teacher_id and subject_code required']); exit(); }

        $stmtS = $conn->prepare("SELECT id FROM subjects WHERE school_id = ? AND code = ? LIMIT 1");
        $stmtS->execute([$schoolId, $subjectCode]);
        $subjectRow = $stmtS->fetch(PDO::FETCH_ASSOC);

        if (!$subjectRow) {
            $stmtN = $conn->prepare("SELECT subject_name FROM grade_subjects WHERE school_id = ? AND subject_code = ? LIMIT 1");
            $stmtN->execute([$schoolId, $subjectCode]);
            $subName = $stmtN->fetchColumn();

            if (!$subName) {
                $stmtN2 = $conn->prepare("SELECT subject_name FROM school_approved_subjects WHERE school_id = ? AND subject_code = ? LIMIT 1");
                $stmtN2->execute([$schoolId, $subjectCode]);
                $subName = $stmtN2->fetchColumn() ?: $subjectCode;
            }

            $stmtInsS = $conn->prepare("INSERT INTO subjects (id, school_id, code, name) VALUES (UUID(), ?, ?, ?)");
            $stmtInsS->execute([$schoolId, $subjectCode, $subName]);

            $stmtFetchId = $conn->prepare("SELECT id FROM subjects WHERE school_id = ? AND code = ? LIMIT 1");
            $stmtFetchId->execute([$schoolId, $subjectCode]);
            $subjectId = $stmtFetchId->fetchColumn();
        } else {
            $subjectId = $subjectRow['id'];
        }

        $ok = $engine->addQualification($teacherId, $subjectId);
        echo json_encode(['success'=> (bool)$ok, 'message'=> $ok ? 'Qualification added.' : 'No change.']);
        exit();
    }

    // 4. POST: save_qualifications (batch save for Step 2)
    if ($method === 'POST' && $action === 'save_qualifications') {
        $teacherId = $input['teacher_id'] ?? '';
        $subjectCodes = $input['subject_codes'] ?? [];
        if (!$teacherId) { echo json_encode(['success'=>false,'message'=>'teacher_id required']); exit(); }

        $conn->beginTransaction();
        try {
            // Remove existing qualifications for teacher
            $del = $conn->prepare("DELETE FROM teacher_subject_qualifications WHERE teacher_id = ?");
            $del->execute([$teacherId]);

            if (!empty($subjectCodes)) {
                $insQual = $conn->prepare("INSERT IGNORE INTO teacher_subject_qualifications (teacher_id, subject_id) VALUES (?, ?)");

                foreach ($subjectCodes as $code) {
                    if (!$code) continue;

                    $stmtS = $conn->prepare("SELECT id FROM subjects WHERE school_id = ? AND code = ? LIMIT 1");
                    $stmtS->execute([$schoolId, $code]);
                    $subId = $stmtS->fetchColumn();

                    if (!$subId) {
                        $stmtN = $conn->prepare("SELECT subject_name FROM grade_subjects WHERE school_id = ? AND subject_code = ? LIMIT 1");
                        $stmtN->execute([$schoolId, $code]);
                        $subName = $stmtN->fetchColumn();

                        if (!$subName) {
                            $stmtN2 = $conn->prepare("SELECT subject_name FROM school_approved_subjects WHERE school_id = ? AND subject_code = ? LIMIT 1");
                            $stmtN2->execute([$schoolId, $code]);
                            $subName = $stmtN2->fetchColumn() ?: $code;
                        }

                        $stmtInsS = $conn->prepare("INSERT INTO subjects (id, school_id, code, name) VALUES (UUID(), ?, ?, ?)");
                        $stmtInsS->execute([$schoolId, $code, $subName]);

                        $stmtFetchId = $conn->prepare("SELECT id FROM subjects WHERE school_id = ? AND code = ? LIMIT 1");
                        $stmtFetchId->execute([$schoolId, $code]);
                        $subId = $stmtFetchId->fetchColumn();
                    }

                    if ($subId) {
                        $insQual->execute([$teacherId, $subId]);
                    }
                }
            }
            $conn->commit();
            echo json_encode(['success'=>true, 'message'=>'Master subject qualifications saved successfully.']);
            exit();
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['success'=>false, 'message'=>'Failed saving qualifications: '.$e->getMessage()]);
            exit();
        }
    }

    // 5. POST: remove qualification
    if ($method === 'POST' && $action === 'remove_qualification') {
        $teacherId = $input['teacher_id'] ?? '';
        $subjectCode = trim($input['subject_code'] ?? '');
        if (!$teacherId || !$subjectCode) { echo json_encode(['success'=>false,'message'=>'teacher_id and subject_code required']); exit(); }
        $stmtS = $conn->prepare("SELECT id FROM subjects WHERE school_id = ? AND code = ? LIMIT 1");
        $stmtS->execute([$schoolId, $subjectCode]);
        $subjectRow = $stmtS->fetch(PDO::FETCH_ASSOC);
        if (!$subjectRow) { echo json_encode(['success'=>false,'message'=>'Subject not found for this school']); exit(); }
        $subjectId = $subjectRow['id'];
        $ok = $engine->removeQualification($teacherId, $subjectId);
        echo json_encode(['success'=> (bool)$ok, 'message'=> $ok ? 'Qualification removed.' : 'No change.']);
        exit();
    }

    // Helper: Sync legacy allocations from teacher_subject_assignments into teacher_subject_qualifications and teacher_classroom_assignments
    function syncLegacyAllocations($conn, $schoolId, $year) {
        try {
            $stmt = $conn->prepare("
                SELECT tsa.teacher_id, tsa.subject_code, tsa.class_stream_id, s.id AS subject_id, c.id AS classroom_id
                FROM teacher_subject_assignments tsa
                LEFT JOIN subjects s ON (s.school_id = tsa.school_id AND s.code = tsa.subject_code)
                LEFT JOIN classrooms c ON (c.school_id = tsa.school_id AND c.academic_year = tsa.academic_year_id AND c.classroom_name = tsa.class_stream_id)
                WHERE tsa.school_id = ? AND tsa.academic_year_id = ? AND tsa.teacher_id IS NOT NULL
            ");
            $stmt->execute([$schoolId, $year]);
            $legacy = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($legacy as $row) {
                if ($row['teacher_id'] && $row['subject_id']) {
                    $insQ = $conn->prepare("INSERT IGNORE INTO teacher_subject_qualifications (teacher_id, subject_id) VALUES (?, ?)");
                    $insQ->execute([$row['teacher_id'], $row['subject_id']]);
                }
                if ($row['teacher_id'] && $row['subject_id'] && $row['classroom_id']) {
                    $insC = $conn->prepare("INSERT INTO teacher_classroom_assignments (academic_year_id, teacher_id, subject_id, classroom_id) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP");
                    $insC->execute([$year, $row['teacher_id'], $row['subject_id'], $row['classroom_id']]);
                }
            }
        } catch (Exception $e) {
            // Ignore sync errors gracefully
        }
    }

    // Run auto-sync for legacy data
    syncLegacyAllocations($conn, $schoolId, $year);

    // 6. POST: save_classroom_assignments (batch save for Step 3 - dual syncs both tables)
    if ($method === 'POST' && $action === 'save_classroom_assignments') {
        $teacherId = $input['teacher_id'] ?? '';
        $academicYearId = $input['academic_year_id'] ?? $year;
        $assignments = $input['assignments'] ?? []; // array of { subject_id, classroom_id }
        if (!$teacherId) { echo json_encode(['success'=>false,'message'=>'teacher_id required']); exit(); }

        $conn->beginTransaction();
        try {
            // 1. Delete existing classroom assignments for this teacher in this year in teacher_classroom_assignments
            $del1 = $conn->prepare("DELETE FROM teacher_classroom_assignments WHERE teacher_id = ? AND academic_year_id = ?");
            $del1->execute([$teacherId, $academicYearId]);

            // 2. Unassign teacher from teacher_subject_assignments for this school & year
            $del2 = $conn->prepare("UPDATE teacher_subject_assignments SET teacher_id = NULL WHERE teacher_id = ? AND academic_year_id = ? AND school_id = ?");
            $del2->execute([$teacherId, $academicYearId, $schoolId]);

            if (!empty($assignments)) {
                $ins1 = $conn->prepare("INSERT INTO teacher_classroom_assignments (academic_year_id, teacher_id, subject_id, classroom_id) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP");

                foreach ($assignments as $a) {
                    if (!empty($a['subject_id']) && !empty($a['classroom_id'])) {
                        $sId = intval($a['subject_id']);
                        $cId = intval($a['classroom_id']);

                        $ins1->execute([$academicYearId, $teacherId, $sId, $cId]);

                        // Resolve subject_code and classroom_name for dual sync
                        $stSub = $conn->prepare("SELECT code FROM subjects WHERE id = ?");
                        $stSub->execute([$sId]);
                        $subCode = $stSub->fetchColumn();

                        $stRoom = $conn->prepare("SELECT classroom_name FROM classrooms WHERE id = ?");
                        $stRoom->execute([$cId]);
                        $roomName = $stRoom->fetchColumn();

                        if ($subCode && $roomName) {
                            $chk = $conn->prepare("SELECT id FROM teacher_subject_assignments WHERE school_id = ? AND academic_year_id = ? AND class_stream_id = ? AND subject_code = ? LIMIT 1");
                            $chk->execute([$schoolId, $academicYearId, $roomName, $subCode]);
                            $existingId = $chk->fetchColumn();

                            if ($existingId) {
                                $upd = $conn->prepare("UPDATE teacher_subject_assignments SET teacher_id = ? WHERE id = ?");
                                $upd->execute([$teacherId, $existingId]);
                            } else {
                                $ins2 = $conn->prepare("INSERT INTO teacher_subject_assignments (school_id, academic_year_id, class_stream_id, subject_code, teacher_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                                $ins2->execute([$schoolId, $academicYearId, $roomName, $subCode, $teacherId]);
                            }
                        }
                    }
                }
            }
            $conn->commit();
            echo json_encode(['success'=>true, 'message'=>'Classroom stream assignments committed successfully.']);
            exit();
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['success'=>false, 'message'=>'Failed saving classroom assignments: '.$e->getMessage()]);
            exit();
        }
    }

    // 7. POST: save classroom assignment (single row)
    if ($method === 'POST' && $action === 'save_classroom_assignment') {
        $teacherId = $input['teacher_id'] ?? '';
        $subjectId = intval($input['subject_id'] ?? 0);
        $subjectCode = trim($input['subject_code'] ?? '');
        $classroomId = intval($input['classroom_id'] ?? 0);
        $academicYearId = $input['academic_year_id'] ?? $year;

        // If subject_id not provided, try resolving by subject_code for this school
        if (!$subjectId && $subjectCode) {
            $stmtS = $conn->prepare("SELECT id FROM subjects WHERE school_id = ? AND code = ? LIMIT 1");
            $stmtS->execute([$schoolId, $subjectCode]);
            $sr = $stmtS->fetch(PDO::FETCH_ASSOC);
            if ($sr) { $subjectId = $sr['id']; }
        }

        if (!$teacherId || !$subjectId || !$classroomId) { echo json_encode(['success'=>false,'message'=>'teacher_id, subject_id (or subject_code), classroom_id required']); exit(); }
        $ok = $engine->saveClassroomAssignment($academicYearId, $teacherId, $subjectId, $classroomId);
        echo json_encode(['success'=> (bool)$ok, 'message'=> $ok ? 'Classroom assignment saved.' : 'Failed to save.']);
        exit();
    }

    // 8. POST: remove classroom assignment
    if ($method === 'POST' && $action === 'remove_classroom_assignment') {
        $teacherId = $input['teacher_id'] ?? '';
        $subjectId = intval($input['subject_id'] ?? 0);
        $subjectCode = trim($input['subject_code'] ?? '');
        $classroomId = intval($input['classroom_id'] ?? 0);
        $academicYearId = $input['academic_year_id'] ?? $year;

        if (!$subjectId && $subjectCode) {
            $stmtS = $conn->prepare("SELECT id FROM subjects WHERE school_id = ? AND code = ? LIMIT 1");
            $stmtS->execute([$schoolId, $subjectCode]);
            $sr = $stmtS->fetch(PDO::FETCH_ASSOC);
            if ($sr) { $subjectId = $sr['id']; }
        }

        if (!$teacherId || !$subjectId || !$classroomId) { echo json_encode(['success'=>false,'message'=>'teacher_id, subject_id (or subject_code), classroom_id required']); exit(); }
        $ok = $engine->removeClassroomAssignment($academicYearId, $teacherId, $subjectId, $classroomId);
        echo json_encode(['success'=> (bool)$ok, 'message'=> $ok ? 'Removed assignment.' : 'No change.']);
        exit();
    }

    // 9. POST: rollover assignments from one year to another
    if ($method === 'POST' && $action === 'rollover') {
        $from = $input['from_year'] ?? '';
        $to = $input['to_year'] ?? '';
        $teacherIds = $input['teacher_ids'] ?? [];
        if (!$from || !$to) { echo json_encode(['success'=>false,'message'=>'from_year and to_year required']); exit(); }
        $ok = $engine->rolloverAssignments($from, $to, $teacherIds);
        echo json_encode(['success'=> (bool)$ok, 'message'=> $ok ? 'Rollover completed.' : 'Rollover failed.']);
        exit();
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
}

?>
