<?php
/**
 * ============================================================================
 * SHULE CAFE - ADVANCED SCHOOL MANAGEMENT PLATFORM
 * ============================================================================
 * @package    ShuleCafe
 * @subpackage Examinations & Academic Marks Engine
 * @author     SHULE CAFE Platform Engineering Team
 * @copyright  (c) 2026 SHULE CAFE. All Rights Reserved.
 * @license    Proprietary Software License. Unauthorized duplication or redistribution is strictly prohibited.
 * ============================================================================
 * English-Only Directive: 100% English code, comments, responses, and error messages.
 */

session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../grading/GradingManager.php';

// Auth check
$userId = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';
$schoolId = $_SESSION['school_id'] ?? $_GET['school_id'] ?? null;

if (empty($schoolId) && !empty($userId)) {
    $uStmt = $conn->prepare("SELECT school_id, role FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$userId]);
    $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
    if ($uRow) {
        $schoolId = $uRow['school_id'];
        if (empty($role)) $role = $uRow['role'];
    }
}

if (empty($schoolId)) {
    $sStmt = $conn->query("SELECT id FROM schools ORDER BY id ASC LIMIT 1");
    $schoolId = $sStmt->fetchColumn() ?: null;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';

try {
    // Self-healing table migrations
    $conn->exec("
        CREATE TABLE IF NOT EXISTS `marks_entry_locks` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `school_id` VARCHAR(36) NOT NULL,
            `academic_year` VARCHAR(10) NOT NULL,
            `term` VARCHAR(20) NOT NULL,
            `classroom_id` INT NOT NULL,
            `subject_code` VARCHAR(50) NOT NULL,
            `is_locked` TINYINT(1) DEFAULT 0,
            `locked_by` VARCHAR(36) DEFAULT NULL,
            `locked_at` DATETIME DEFAULT NULL,
            `unlocked_by` VARCHAR(36) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_lock_target` (`school_id`, `academic_year`, `term`, `classroom_id`, `subject_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS `marks_entry_dynamic` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `school_id` VARCHAR(36) NOT NULL,
            `academic_year` VARCHAR(10) NOT NULL,
            `term` VARCHAR(20) NOT NULL,
            `student_id` VARCHAR(36) NOT NULL,
            `subject_code` VARCHAR(50) NOT NULL,
            `assessment_type_id` VARCHAR(50) NOT NULL,
            `score` DECIMAL(5,2) DEFAULT 0.00,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_student_assessment` (`school_id`, `academic_year`, `term`, `student_id`, `subject_code`, `assessment_type_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $gradingManager = new GradingManager($conn);

    // ────────────────────────────────────────────────────────────────────────
    // 1. GET SCOPES (Levels, Grades, Classrooms, Subjects, Years, Terms, Types)
    // ────────────────────────────────────────────────────────────────────────
    if ($action === 'get_scopes') {
        // Education Levels for this school
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

        if (empty($levels)) {
            $stmtAllLevels = $conn->query("SELECT id, name, code FROM education_levels ORDER BY id ASC");
            $levels = $stmtAllLevels->fetchAll(PDO::FETCH_ASSOC);
        }

        // Grades
        $stmtGrades = $conn->prepare("
            SELECT DISTINCT g.id, g.name, g.level_id, el.name AS level_name, el.code AS level_code
            FROM grades g
            JOIN education_levels el ON g.level_id = el.id
            JOIN classrooms c ON c.grade_id = g.id
            WHERE c.school_id = ?
            ORDER BY g.id ASC
        ");
        $stmtGrades->execute([$schoolId]);
        $grades = $stmtGrades->fetchAll(PDO::FETCH_ASSOC);

        if (empty($grades)) {
            $stmtAllGrades = $conn->query("SELECT g.id, g.name, g.level_id, el.name AS level_name, el.code AS level_code FROM grades g JOIN education_levels el ON g.level_id = el.id ORDER BY g.id ASC");
            $grades = $stmtAllGrades->fetchAll(PDO::FETCH_ASSOC);
        }

        // Classrooms / Streams
        $stmtClassrooms = $conn->prepare("
            SELECT c.id, c.classroom_name, c.grade_id, g.name AS grade_name, g.level_id, el.name AS level_name
            FROM classrooms c
            JOIN grades g ON c.grade_id = g.id
            JOIN education_levels el ON g.level_id = el.id
            WHERE c.school_id = ?
            ORDER BY g.id ASC, c.classroom_name ASC
        ");
        $stmtClassrooms->execute([$schoolId]);
        $classrooms = $stmtClassrooms->fetchAll(PDO::FETCH_ASSOC);

        // Subjects
        $stmtSubj = $conn->prepare("
            SELECT id, name, code 
            FROM subjects 
            WHERE school_id = ? OR school_id IS NULL OR school_id = '' 
            ORDER BY name ASC
        ");
        $stmtSubj->execute([$schoolId]);
        $subjects = $stmtSubj->fetchAll(PDO::FETCH_ASSOC);

        if (empty($subjects)) {
            $stmtAllSubj = $conn->query("SELECT id, name, code FROM subjects ORDER BY name ASC");
            $subjects = $stmtAllSubj->fetchAll(PDO::FETCH_ASSOC);
        }
        if (empty($subjects)) {
            $subjects = [
                ['id' => 's1', 'code' => 'MATH', 'name' => 'Basic Mathematics'],
                ['id' => 's2', 'code' => 'ENG', 'name' => 'English Language'],
                ['id' => 's3', 'code' => 'KISW', 'name' => 'Kiswahili'],
                ['id' => 's4', 'code' => 'PHY', 'name' => 'Physics'],
                ['id' => 's5', 'code' => 'CHEM', 'name' => 'Chemistry'],
                ['id' => 's6', 'code' => 'BIO', 'name' => 'Biology'],
                ['id' => 's7', 'code' => 'GEO', 'name' => 'Geography'],
                ['id' => 's8', 'code' => 'HIST', 'name' => 'History'],
                ['id' => 's9', 'code' => 'CIV', 'name' => 'Civics']
            ];
        }

        // Active Assessment Types
        $stmtTypes = $conn->prepare("
            SELECT id, name, weight_percent, is_terminal, term, academic_year 
            FROM assessment_types 
            WHERE school_id = ? AND is_archived = 0 
            ORDER BY academic_year DESC, is_terminal ASC, name ASC
        ");
        $stmtTypes->execute([$schoolId]);
        $allTypes = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);

        if (empty($allTypes)) {
            $allTypes = [
                ['id' => 'ca', 'name' => 'Continuous Assessment (CA)', 'weight_percent' => 30.00, 'is_terminal' => 0, 'term' => 'Term 1'],
                ['id' => 'terminal', 'name' => 'Terminal Examination', 'weight_percent' => 70.00, 'is_terminal' => 1, 'term' => 'Term 1'],
                ['id' => 'test1', 'name' => 'Monthly Test 1', 'weight_percent' => 15.00, 'is_terminal' => 0, 'term' => 'Term 1'],
                ['id' => 'midterm', 'name' => 'Midterm Assessment', 'weight_percent' => 15.00, 'is_terminal' => 0, 'term' => 'Term 1']
            ];
        }

        // Academic Years list
        $currentYear = intval(date('Y'));
        $years = [strval($currentYear), strval($currentYear - 1), strval($currentYear - 2)];

        echo json_encode([
            'success' => true,
            'levels' => $levels,
            'grades' => $grades,
            'classrooms' => $classrooms,
            'subjects' => $subjects,
            'assessment_types' => $allTypes,
            'years' => $years,
            'terms' => ['Term 1', 'Term 2']
        ]);
        exit();
    }

    // ────────────────────────────────────────────────────────────────────────
    // 2. GET ENTRY SHEET (Supports Single Subject & Multi-Subject Grid)
    // ────────────────────────────────────────────────────────────────────────
    if ($action === 'get_entry_sheet') {
        $classroomId = intval($_GET['classroom_id'] ?? 0);
        $subjectCode = trim($_GET['subject_code'] ?? 'all');
        $year = trim($_GET['year'] ?? date('Y'));
        $term = trim($_GET['term'] ?? 'Term 1');
        $assessmentTypeId = trim($_GET['assessment_type_id'] ?? '');

        if (!$classroomId) {
            echo json_encode(['success' => false, 'message' => 'Classroom ID is required.']);
            exit();
        }

        // Fetch classroom and education level details
        $stmtC = $conn->prepare("
            SELECT c.id, c.classroom_name, c.grade_id, g.name AS grade_name, el.name AS level_name, el.code AS level_code
            FROM classrooms c
            JOIN grades g ON c.grade_id = g.id
            JOIN education_levels el ON g.level_id = el.id
            WHERE c.id = ? AND c.school_id = ? LIMIT 1
        ");
        $stmtC->execute([$classroomId, $schoolId]);
        $classroom = $stmtC->fetch(PDO::FETCH_ASSOC);

        if (!$classroom) {
            echo json_encode(['success' => false, 'message' => 'Classroom not found.']);
            exit();
        }

        $levelType = $classroom['level_name'] ?: 'O-Level';
        if (stripos($levelType, 'Primary') !== false) {
            $levelTypeKey = 'Primary';
        } elseif (stripos($levelType, 'A-Level') !== false || stripos($levelType, 'High') !== false) {
            $levelTypeKey = 'A-Level';
        } else {
            $levelTypeKey = 'O-Level';
        }

        // Fetch grading scale
        $stmtScales = $conn->prepare("SELECT min_mark, max_mark, grade, remark, points FROM grading_scales WHERE level_type = :ltype ORDER BY min_mark DESC");
        $stmtScales->execute([':ltype' => $levelTypeKey]);
        $scales = $stmtScales->fetchAll(PDO::FETCH_ASSOC);
        if (empty($scales)) {
            $stmtScales->execute([':ltype' => 'O-Level']);
            $scales = $stmtScales->fetchAll(PDO::FETCH_ASSOC);
        }

        // Fetch all available subjects
        $stmtSubjs = $conn->prepare("
            SELECT id, name, code 
            FROM subjects 
            WHERE school_id = ? OR school_id IS NULL OR school_id = '' 
            ORDER BY name ASC
        ");
        $stmtSubjs->execute([$schoolId]);
        $allSubjects = $stmtSubjs->fetchAll(PDO::FETCH_ASSOC);

        if (empty($allSubjects)) {
            $stmtAllSubj = $conn->query("SELECT id, name, code FROM subjects ORDER BY name ASC");
            $allSubjects = $stmtAllSubj->fetchAll(PDO::FETCH_ASSOC);
        }
        if (empty($allSubjects)) {
            $allSubjects = [
                ['id' => 's1', 'code' => 'MATH', 'name' => 'Basic Mathematics'],
                ['id' => 's2', 'code' => 'ENG', 'name' => 'English Language'],
                ['id' => 's3', 'code' => 'KISW', 'name' => 'Kiswahili'],
                ['id' => 's4', 'code' => 'PHY', 'name' => 'Physics'],
                ['id' => 's5', 'code' => 'CHEM', 'name' => 'Chemistry'],
                ['id' => 's6', 'code' => 'BIO', 'name' => 'Biology'],
                ['id' => 's7', 'code' => 'GEO', 'name' => 'Geography'],
                ['id' => 's8', 'code' => 'HIST', 'name' => 'History'],
                ['id' => 's9', 'code' => 'CIV', 'name' => 'Civics']
            ];
        }

        // Fetch assessment types configured for this school & term
        $stmtTypes = $conn->prepare("
            SELECT id, name, weight_percent, is_terminal, academic_year 
            FROM assessment_types 
            WHERE school_id = ? AND is_archived = 0 AND (term = ? OR term IS NULL OR term = '')
            ORDER BY academic_year DESC, is_terminal ASC, name ASC
        ");
        $stmtTypes->execute([$schoolId, $term]);
        $assessmentTypes = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);

        if (empty($assessmentTypes)) {
            $assessmentTypes = [
                ['id' => '1', 'name' => 'Continuous Assessment (CA)', 'weight_percent' => 30.00, 'is_terminal' => 0],
                ['id' => '2', 'name' => 'Terminal Examination', 'weight_percent' => 70.00, 'is_terminal' => 1]
            ];
        }

        if (empty($assessmentTypeId)) {
            $assessmentTypeId = $assessmentTypes[0]['id'] ?? '1';
        }

        // Fetch lock status for classroom
        $stmtLocks = $conn->prepare("
            SELECT subject_code, is_locked, locked_at, locked_by
            FROM marks_entry_locks
            WHERE school_id = ? AND classroom_id = ? AND academic_year = ? AND term = ? AND is_locked = 1
        ");
        $stmtLocks->execute([$schoolId, $classroomId, $year, $term]);
        $lockRows = $stmtLocks->fetchAll(PDO::FETCH_ASSOC);
        $locksMap = [];
        foreach ($lockRows as $lr) {
            $locksMap[$lr['subject_code']] = $lr;
        }

        $isSingleSubject = (!empty($subjectCode) && $subjectCode !== 'all');
        $isLocked = $isSingleSubject ? isset($locksMap[$subjectCode]) : false;

        // Student Roster for this classroom
        $stmtRoster = $conn->prepare("
            SELECT u.id AS student_id, u.full_name, u.user_code, u.gender
            FROM student_classroom_allocations sca
            JOIN users u ON sca.student_id = u.id
            WHERE sca.classroom_id = ? AND sca.school_id = ? AND sca.status = 'Active'
            ORDER BY u.full_name ASC
        ");
        $stmtRoster->execute([$classroomId, $schoolId]);
        $students = $stmtRoster->fetchAll(PDO::FETCH_ASSOC);

        // Fetch dynamic marks entered for these students in this year/term
        $stmtMarks = $conn->prepare("
            SELECT student_id, subject_code, assessment_type_id, score
            FROM marks_entry_dynamic
            WHERE school_id = ? AND academic_year = ? AND term = ?
        ");
        $stmtMarks->execute([$schoolId, $year, $term]);
        $dynamicMarks = $stmtMarks->fetchAll(PDO::FETCH_ASSOC);

        // marksMap[student_id][subject_code][assessment_type_id] = score
        $marksMap = [];
        foreach ($dynamicMarks as $dm) {
            $marksMap[$dm['student_id']][$dm['subject_code']][$dm['assessment_type_id']] = floatval($dm['score']);
        }

        // Build composite roster with totals and adaptive grading
        $roster = [];
        foreach ($students as $stu) {
            $sid = $stu['student_id'];
            $stuAllSubjects = $marksMap[$sid] ?? [];

            // Multi-subject score dictionary: subject_code => active assessment score
            $subjectScores = [];
            $totalMarks = 0.0;
            $subjectGradesMap = [];

            foreach ($allSubjects as $sub) {
                $sc = $sub['code'];
                $scoreVal = isset($stuAllSubjects[$sc][$assessmentTypeId]) ? floatval($stuAllSubjects[$sc][$assessmentTypeId]) : null;
                $subjectScores[$sc] = $scoreVal;

                if ($scoreVal !== null) {
                    $totalMarks += $scoreVal;
                    $subjectGradesMap[$sc] = $scoreVal;
                }
            }

            // Single-subject breakdown if single subject mode
            $singleSubMarks = $stuAllSubjects[$subjectCode] ?? [];
            $singleSubTotal = 0.0;
            foreach ($singleSubMarks as $v) $singleSubTotal += floatval($v);

            $perf = $gradingManager->calculateStudentPerformance($levelTypeKey, $subjectGradesMap);

            $stu['subject_scores'] = $subjectScores;
            $stu['single_subject_marks'] = $singleSubMarks;
            $stu['current_score'] = $isSingleSubject ? ($singleSubMarks[$assessmentTypeId] ?? null) : null;
            $stu['total_score'] = $isSingleSubject ? round($singleSubTotal, 2) : round($totalMarks, 2);
            $stu['points'] = $perf['total_points'];
            $stu['division'] = $perf['division'];
            $stu['remark'] = $perf['remark'];

            // Grade letter for single subject mode
            if ($isSingleSubject) {
                $gradeLetter = 'F';
                $gradeRemark = 'Fail';
                $gradePoints = 7;
                foreach ($scales as $sc) {
                    if ($stu['total_score'] >= floatval($sc['min_mark']) && $stu['total_score'] <= floatval($sc['max_mark'])) {
                        $gradeLetter = $sc['grade'];
                        $gradeRemark = $sc['remark'];
                        $gradePoints = intval($sc['points'] ?? 1);
                        break;
                    }
                }
                $stu['grade'] = $gradeLetter;
                $stu['grade_remark'] = $gradeRemark;
                $stu['points'] = $gradePoints;
            }

            $roster[] = $stu;
        }

        // Calculate competition rank in class
        usort($roster, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
        $currentRank = 1;
        foreach ($roster as $idx => &$rStu) {
            if ($idx > 0 && $rStu['total_score'] < $roster[$idx - 1]['total_score']) {
                $currentRank = $idx + 1;
            }
            $rStu['rank_position'] = $rStu['total_score'] > 0 ? $currentRank : '-';
        }
        unset($rStu);

        // Sort back alphabetically by student full_name for data entry convenience
        usort($roster, fn($a, $b) => strcasecmp($a['full_name'], $b['full_name']));

        echo json_encode([
            'success' => true,
            'classroom' => $classroom,
            'subject_code' => $subjectCode,
            'subjects' => $allSubjects,
            'year' => $year,
            'term' => $term,
            'level_type' => $levelTypeKey,
            'is_locked' => $isLocked,
            'locks_map' => $locksMap,
            'grading_scales' => $scales,
            'assessment_types' => $assessmentTypes,
            'active_assessment_type_id' => $assessmentTypeId,
            'roster' => $roster
        ]);
        exit();
    }

    // ────────────────────────────────────────────────────────────────────────
    // 3. SAVE MARKS BATCH (Supports Single Subject & Multi-Subject Grid)
    // ────────────────────────────────────────────────────────────────────────
    if ($action === 'save_marks_batch') {
        $classroomId = intval($input['classroom_id'] ?? 0);
        $subjectCode = trim($input['subject_code'] ?? 'all');
        $year = trim($input['year'] ?? date('Y'));
        $term = trim($input['term'] ?? 'Term 1');
        $assessmentTypeId = trim($input['assessment_type_id'] ?? '');
        $marksList = $input['marks'] ?? [];
        $isMultiSubject = !empty($input['is_multi_subject']) || ($subjectCode === 'all');

        if (!$classroomId || empty($assessmentTypeId) || !is_array($marksList)) {
            echo json_encode(['success' => false, 'message' => 'Classroom, Assessment Type, and Marks entries are required.']);
            exit();
        }

        // Fetch max weight limit for assessment type
        $stmtWeight = $conn->prepare("SELECT weight_percent FROM assessment_types WHERE id = ? AND school_id = ? LIMIT 1");
        $stmtWeight->execute([$assessmentTypeId, $schoolId]);
        $maxWeight = floatval($stmtWeight->fetchColumn() ?: 100.00);

        // Fetch locked subjects
        $stmtLocks = $conn->prepare("
            SELECT subject_code 
            FROM marks_entry_locks 
            WHERE school_id = ? AND classroom_id = ? AND academic_year = ? AND term = ? AND is_locked = 1
        ");
        $stmtLocks->execute([$schoolId, $classroomId, $year, $term]);
        $lockedSubjs = $stmtLocks->fetchAll(PDO::FETCH_COLUMN);
        $lockedMap = array_flip($lockedSubjs);

        $stmtSave = $conn->prepare("
            INSERT INTO marks_entry_dynamic (school_id, academic_year, term, student_id, subject_code, assessment_type_id, score, updated_at)
            VALUES (:sch, :yr, :trm, :sid, :subj, :atid, :score, NOW())
            ON DUPLICATE KEY UPDATE score = VALUES(score), updated_at = NOW()
        ");

        $stmtDel = $conn->prepare("
            DELETE FROM marks_entry_dynamic 
            WHERE school_id = ? AND academic_year = ? AND term = ? AND student_id = ? AND subject_code = ? AND assessment_type_id = ?
        ");

        $savedCount = 0;
        $conn->beginTransaction();

        foreach ($marksList as $entry) {
            $studentId = $entry['student_id'] ?? '';
            if (empty($studentId)) continue;

            if ($isMultiSubject && isset($entry['subject_scores']) && is_array($entry['subject_scores'])) {
                // Multi-subject row: scores: { MATH: 85, ENG: 70, ... }
                foreach ($entry['subject_scores'] as $sCode => $sVal) {
                    if (isset($lockedMap[$sCode])) continue; // Skip locked subjects

                    if ($sVal === '' || $sVal === null) {
                        $stmtDel->execute([$schoolId, $year, $term, $studentId, $sCode, $assessmentTypeId]);
                        $savedCount++;
                        continue;
                    }

                    $numScore = floatval($sVal);
                    if ($numScore < 0) $numScore = 0.0;
                    if ($maxWeight > 0 && $numScore > $maxWeight) $numScore = $maxWeight;

                    $stmtSave->execute([
                        ':sch' => $schoolId,
                        ':yr' => $year,
                        ':trm' => $term,
                        ':sid' => $studentId,
                        ':subj' => $sCode,
                        ':atid' => $assessmentTypeId,
                        ':score' => $numScore
                    ]);
                    $savedCount++;
                }
            } else {
                // Single-subject row
                $curSubCode = $entry['subject_code'] ?? $subjectCode;
                if (isset($lockedMap[$curSubCode])) continue;

                $scoreVal = $entry['score'] ?? null;
                if ($scoreVal === '' || $scoreVal === null) {
                    $stmtDel->execute([$schoolId, $year, $term, $studentId, $curSubCode, $assessmentTypeId]);
                    $savedCount++;
                    continue;
                }

                $numScore = floatval($scoreVal);
                if ($numScore < 0) $numScore = 0.0;
                if ($maxWeight > 0 && $numScore > $maxWeight) $numScore = $maxWeight;

                $stmtSave->execute([
                    ':sch' => $schoolId,
                    ':yr' => $year,
                    ':trm' => $term,
                    ':sid' => $studentId,
                    ':subj' => $curSubCode,
                    ':atid' => $assessmentTypeId,
                    ':score' => $numScore
                ]);
                $savedCount++;
            }
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => "Successfully recorded $savedCount assessment marks.",
            'saved_count' => $savedCount
        ]);
        exit();
    }

    // ────────────────────────────────────────────────────────────────────────
    // 4. GET BROADSHEET RECORDS (Class Broadsheet, Divisions, Positions)
    // ────────────────────────────────────────────────────────────────────────
    if ($action === 'get_broadsheet_records') {
        $classroomId = intval($_GET['classroom_id'] ?? 0);
        $gradeId = intval($_GET['grade_id'] ?? 0);
        $year = trim($_GET['year'] ?? date('Y'));
        $term = trim($_GET['term'] ?? 'Term 1');
        $examTypeId = trim($_GET['exam_type_id'] ?? 'all'); // 'all' or specific assessment_type_id

        if (!$classroomId && !$gradeId) {
            echo json_encode(['success' => false, 'message' => 'Please select a classroom or grade.']);
            exit();
        }

        // Classroom & Level information
        $whereClass = $classroomId ? "c.id = :cid" : "c.grade_id = :gid";
        $paramsClass = $classroomId ? [':cid' => $classroomId, ':sch' => $schoolId] : [':gid' => $gradeId, ':sch' => $schoolId];

        $stmtInfo = $conn->prepare("
            SELECT c.id AS classroom_id, c.classroom_name, g.id AS grade_id, g.name AS grade_name, el.name AS level_name, el.code AS level_code
            FROM classrooms c
            JOIN grades g ON c.grade_id = g.id
            JOIN education_levels el ON g.level_id = el.id
            WHERE $whereClass AND c.school_id = :sch
            LIMIT 1
        ");
        $stmtInfo->execute($paramsClass);
        $classInfo = $stmtInfo->fetch(PDO::FETCH_ASSOC);

        if (!$classInfo) {
            echo json_encode(['success' => false, 'message' => 'Classroom/Grade records not found.']);
            exit();
        }

        $levelTypeKey = 'O-Level';
        if (stripos($classInfo['level_name'], 'Primary') !== false) {
            $levelTypeKey = 'Primary';
        } elseif (stripos($classInfo['level_name'], 'A-Level') !== false || stripos($classInfo['level_name'], 'High') !== false) {
            $levelTypeKey = 'A-Level';
        }

        // Fetch subjects assigned in this classroom or grade
        $stmtSubjs = $conn->prepare("
            SELECT DISTINCT s.code, s.name 
            FROM subjects s
            ORDER BY s.name ASC
        ");
        $stmtSubjs->execute();
        $allSubjects = $stmtSubjs->fetchAll(PDO::FETCH_ASSOC);

        if (empty($allSubjects)) {
            $allSubjects = [
                ['code' => 'MATH', 'name' => 'Basic Mathematics'],
                ['code' => 'ENG', 'name' => 'English Language'],
                ['code' => 'KISW', 'name' => 'Kiswahili'],
                ['code' => 'PHY', 'name' => 'Physics'],
                ['code' => 'CHEM', 'name' => 'Chemistry'],
                ['code' => 'BIO', 'name' => 'Biology'],
                ['code' => 'GEO', 'name' => 'Geography'],
                ['code' => 'HIST', 'name' => 'History'],
                ['code' => 'CIV', 'name' => 'Civics']
            ];
        }

        // Fetch students in this class/grade
        $whereStudents = $classroomId ? "sca.classroom_id = :cid" : "c.grade_id = :gid";
        $stmtStudents = $conn->prepare("
            SELECT u.id AS student_id, u.full_name, u.user_code, u.gender, c.id AS classroom_id, c.classroom_name
            FROM student_classroom_allocations sca
            JOIN users u ON sca.student_id = u.id
            JOIN classrooms c ON sca.classroom_id = c.id
            WHERE $whereStudents AND sca.school_id = :sch AND sca.status = 'Active'
            ORDER BY c.classroom_name ASC, u.full_name ASC
        ");
        $stmtStudents->execute($paramsClass);
        $students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

        // Fetch all marks for these students in this year/term
        $stmtMarks = $conn->prepare("
            SELECT m.student_id, m.subject_code, m.assessment_type_id, m.score
            FROM marks_entry_dynamic m
            JOIN student_classroom_allocations sca ON (m.student_id = sca.student_id AND sca.school_id = m.school_id)
            JOIN classrooms c ON sca.classroom_id = c.id
            WHERE $whereStudents AND m.school_id = :sch AND m.academic_year = :yr AND m.term = :trm
        ");
        $paramsMarks = array_merge($paramsClass, [':yr' => $year, ':trm' => $term]);
        $stmtMarks->execute($paramsMarks);
        $marksRows = $stmtMarks->fetchAll(PDO::FETCH_ASSOC);

        // Aggregate marks per student per subject
        $marksMatrix = [];
        $activeSubjectsPresent = [];

        foreach ($marksRows as $mr) {
            $sid = $mr['student_id'];
            $scode = $mr['subject_code'];
            $atid = $mr['assessment_type_id'];
            $score = floatval($mr['score']);

            if ($examTypeId !== 'all' && strval($atid) !== strval($examTypeId)) {
                continue;
            }

            if (!isset($marksMatrix[$sid][$scode])) {
                $marksMatrix[$sid][$scode] = 0.0;
            }
            $marksMatrix[$sid][$scode] += $score;
            $activeSubjectsPresent[$scode] = true;
        }

        // Filter subject list to only subjects that have evaluated marks or are core
        $evaluatedSubjects = [];
        foreach ($allSubjects as $sub) {
            if (isset($activeSubjectsPresent[$sub['code']])) {
                $evaluatedSubjects[] = $sub;
            }
        }
        if (empty($evaluatedSubjects)) {
            $evaluatedSubjects = array_slice($allSubjects, 0, 8);
        }

        // Compute performance for each student using GradingManager
        $broadsheetRows = [];
        $divisionCounts = ['Division I' => 0, 'Division II' => 0, 'Division III' => 0, 'Division IV' => 0, 'Division 0' => 0, 'Pass' => 0, 'Fail' => 0];
        $totalEvaluatedScoreSum = 0.0;
        $totalEvaluatedSubjectEntries = 0;
        $subjectScoresAccumulator = [];

        foreach ($students as $stu) {
            $sid = $stu['student_id'];
            $studentSubjectMarks = $marksMatrix[$sid] ?? [];

            $totalMarks = 0.0;
            $subjectGrades = [];

            foreach ($evaluatedSubjects as $sub) {
                $sc = $sub['code'];
                $mark = isset($studentSubjectMarks[$sc]) ? round(floatval($studentSubjectMarks[$sc]), 1) : null;
                if ($mark !== null) {
                    $totalMarks += $mark;
                    $gData = $gradingManager->getSubjectGrade($levelTypeKey, $mark);
                    $subjectGrades[$sc] = [
                        'mark' => $mark,
                        'grade' => $gData['grade'],
                        'points' => $gData['points'],
                        'remark' => $gData['remark']
                    ];

                    if (!isset($subjectScoresAccumulator[$sc])) {
                        $subjectScoresAccumulator[$sc] = ['total' => 0, 'count' => 0, 'passed' => 0, 'name' => $sub['name']];
                    }
                    $subjectScoresAccumulator[$sc]['total'] += $mark;
                    $subjectScoresAccumulator[$sc]['count']++;
                    if ($mark >= 45.0) $subjectScoresAccumulator[$sc]['passed']++;
                    $totalEvaluatedScoreSum += $mark;
                    $totalEvaluatedSubjectEntries++;
                } else {
                    $subjectGrades[$sc] = null;
                }
            }

            // Division & Points Calculation
            $perf = $gradingManager->calculateStudentPerformance($levelTypeKey, $studentSubjectMarks);
            $division = $perf['division'];
            $points = $perf['total_points'];

            if (isset($divisionCounts[$division])) {
                $divisionCounts[$division]++;
            }

            $stu['subject_scores'] = $subjectGrades;
            $stu['total_marks'] = round($totalMarks, 1);
            $stu['average_mark'] = count($studentSubjectMarks) > 0 ? round($totalMarks / count($studentSubjectMarks), 1) : 0.0;
            $stu['points'] = $points;
            $stu['division'] = $division;
            $stu['remark'] = $perf['remark'];
            $stu['subjects_count'] = count($studentSubjectMarks);

            $broadsheetRows[] = $stu;
        }

        // Rank Calculation (Position in Class Stream and Position in Grade)
        usort($broadsheetRows, fn($a, $b) => $b['total_marks'] <=> $a['total_marks']);
        $currRank = 1;
        foreach ($broadsheetRows as $idx => &$bRow) {
            if ($idx > 0 && $bRow['total_marks'] < $broadsheetRows[$idx - 1]['total_marks']) {
                $currRank = $idx + 1;
            }
            $bRow['position'] = $bRow['total_marks'] > 0 ? $currRank : '-';
        }
        unset($bRow);

        // Subject comparison metrics
        $subjectRankings = [];
        foreach ($subjectScoresAccumulator as $scCode => $data) {
            $avg = $data['count'] > 0 ? round($data['total'] / $data['count'], 1) : 0;
            $passRate = $data['count'] > 0 ? round(($data['passed'] / $data['count']) * 100, 1) : 0;
            $subjectRankings[] = [
                'code' => $scCode,
                'name' => $data['name'],
                'average' => $avg,
                'evaluated_count' => $data['count'],
                'pass_rate_percent' => $passRate
            ];
        }
        usort($subjectRankings, fn($a, $b) => $b['average'] <=> $a['average']);

        // Overall Class Pass Rate
        $passedStudents = 0;
        foreach ($broadsheetRows as $r) {
            if (!in_array($r['division'], ['Division 0', 'Fail']) && $r['total_marks'] > 0) {
                $passedStudents++;
            }
        }
        $classPassRate = count($broadsheetRows) > 0 ? round(($passedStudents / count($broadsheetRows)) * 100, 1) : 0;

        echo json_encode([
            'success' => true,
            'class_info' => $classInfo,
            'year' => $year,
            'term' => $term,
            'level_type' => $levelTypeKey,
            'subjects' => $evaluatedSubjects,
            'broadsheet' => $broadsheetRows,
            'division_summary' => $divisionCounts,
            'total_students' => count($broadsheetRows),
            'class_pass_rate_percent' => $classPassRate,
            'subject_rankings' => $subjectRankings,
            'best_subject' => !empty($subjectRankings) ? $subjectRankings[0] : null
        ]);
        exit();
    }

    // ────────────────────────────────────────────────────────────────────────
    // 5. GET CLASSROOM & SUBJECT ANALYTICS
    // ────────────────────────────────────────────────────────────────────────
    if ($action === 'get_class_analytics') {
        $classroomId = intval($_GET['classroom_id'] ?? 0);
        $year = trim($_GET['year'] ?? date('Y'));
        $term = trim($_GET['term'] ?? 'Term 1');

        if (!$classroomId) {
            echo json_encode(['success' => false, 'message' => 'Classroom ID is required.']);
            exit();
        }

        // Fetch classroom details
        $stmtC = $conn->prepare("
            SELECT c.id, c.classroom_name, g.name AS grade_name, el.name AS level_name
            FROM classrooms c
            JOIN grades g ON c.grade_id = g.id
            JOIN education_levels el ON g.level_id = el.id
            WHERE c.id = ? AND c.school_id = ? LIMIT 1
        ");
        $stmtC->execute([$classroomId, $schoolId]);
        $classDetails = $stmtC->fetch(PDO::FETCH_ASSOC);

        if (!$classDetails) {
            echo json_encode(['success' => false, 'message' => 'Classroom not found.']);
            exit();
        }

        $levelTypeKey = (stripos($classDetails['level_name'], 'Primary') !== false) ? 'Primary' : ((stripos($classDetails['level_name'], 'A-Level') !== false) ? 'A-Level' : 'O-Level');

        // Fetch student gender cohorts
        $stmtGenders = $conn->prepare("
            SELECT u.gender, COUNT(*) as count
            FROM student_classroom_allocations sca
            JOIN users u ON sca.student_id = u.id
            WHERE sca.classroom_id = ? AND sca.school_id = ? AND sca.status = 'Active'
            GROUP BY u.gender
        ");
        $stmtGenders->execute([$classroomId, $schoolId]);
        $genders = $stmtGenders->fetchAll(PDO::FETCH_KEY_PAIR);

        // Fetch scores breakdown by gender
        $stmtGenderPerf = $conn->prepare("
            SELECT u.gender, COALESCE(SUM(m.score),0) as total_marks, COUNT(DISTINCT u.id) as students_evaluated,
                   SUM(CASE WHEN m.score >= 45 THEN 1 ELSE 0 END) as passed_entries,
                   COUNT(m.id) as total_entries
            FROM student_classroom_allocations sca
            JOIN users u ON sca.student_id = u.id
            LEFT JOIN marks_entry_dynamic m ON (m.student_id = u.id AND m.academic_year = ? AND m.term = ? AND m.school_id = sca.school_id)
            WHERE sca.classroom_id = ? AND sca.school_id = ? AND sca.status = 'Active'
            GROUP BY u.gender
        ");
        $stmtGenderPerf->execute([$year, $term, $classroomId, $schoolId]);
        $genderPerf = $stmtGenderPerf->fetchAll(PDO::FETCH_ASSOC);

        $genderAnalytics = [];
        foreach ($genderPerf as $gp) {
            $gName = !empty($gp['gender']) ? $gp['gender'] : 'Unknown';
            $tEntries = intval($gp['total_entries']);
            $pEntries = intval($gp['passed_entries']);
            $passRate = $tEntries > 0 ? round(($pEntries / $tEntries) * 100, 1) : 0;
            $avgScore = $tEntries > 0 ? round(floatval($gp['total_marks']) / $tEntries, 1) : 0;

            $genderAnalytics[] = [
                'gender' => $gName,
                'students_count' => intval($gp['students_evaluated']),
                'total_entries' => $tEntries,
                'passed_entries' => $pEntries,
                'pass_rate_percent' => $passRate,
                'average_score' => $avgScore
            ];
        }

        // Fetch top performers leaderboard
        $stmtTop = $conn->prepare("
            SELECT u.id, u.full_name, u.user_code, u.gender, COALESCE(SUM(m.score), 0) AS total_score, COUNT(DISTINCT m.subject_code) as subjects_count
            FROM student_classroom_allocations sca
            JOIN users u ON sca.student_id = u.id
            LEFT JOIN marks_entry_dynamic m ON (m.student_id = u.id AND m.academic_year = ? AND m.term = ? AND m.school_id = sca.school_id)
            WHERE sca.classroom_id = ? AND sca.school_id = ? AND sca.status = 'Active'
            GROUP BY u.id
            HAVING total_score > 0
            ORDER BY total_score DESC
            LIMIT 10
        ");
        $stmtTop->execute([$year, $term, $classroomId, $schoolId]);
        $topLeaders = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

        foreach ($topLeaders as $rankIdx => &$leader) {
            $leader['position'] = $rankIdx + 1;
            $leader['average'] = $leader['subjects_count'] > 0 ? round(floatval($leader['total_score']) / intval($leader['subjects_count']), 1) : 0;
        }
        unset($leader);

        // Subject performance list
        $stmtSubjPerf = $conn->prepare("
            SELECT m.subject_code, s.name as subject_name,
                   AVG(m.score) as average_score,
                   COUNT(m.id) as total_entries,
                   SUM(CASE WHEN m.score >= 45 THEN 1 ELSE 0 END) as pass_entries,
                   MAX(m.score) as highest_score,
                   MIN(m.score) as lowest_score
            FROM marks_entry_dynamic m
            JOIN student_classroom_allocations sca ON (m.student_id = sca.student_id AND sca.school_id = m.school_id)
            LEFT JOIN subjects s ON m.subject_code = s.code
            WHERE sca.classroom_id = ? AND m.school_id = ? AND m.academic_year = ? AND m.term = ?
            GROUP BY m.subject_code
            ORDER BY average_score DESC
        ");
        $stmtSubjPerf->execute([$classroomId, $schoolId, $year, $term]);
        $subjMetrics = $stmtSubjPerf->fetchAll(PDO::FETCH_ASSOC);

        foreach ($subjMetrics as &$sm) {
            $t = intval($sm['total_entries']);
            $p = intval($sm['pass_entries']);
            $sm['average_score'] = round(floatval($sm['average_score']), 1);
            $sm['pass_rate_percent'] = $t > 0 ? round(($p / $t) * 100, 1) : 0;
        }
        unset($sm);

        echo json_encode([
            'success' => true,
            'classroom' => $classDetails,
            'year' => $year,
            'term' => $term,
            'gender_counts' => $genders,
            'gender_analytics' => $genderAnalytics,
            'top_performers' => $topLeaders,
            'subject_performance' => $subjMetrics
        ]);
        exit();
    }

    // ────────────────────────────────────────────────────────────────────────
    // 6. GET APPROVAL & SUBMISSION STATUS
    // ────────────────────────────────────────────────────────────────────────
    if ($action === 'get_approval_status') {
        $year = trim($_GET['year'] ?? date('Y'));
        $term = trim($_GET['term'] ?? 'Term 1');

        // Fetch all classrooms
        $stmtC = $conn->prepare("
            SELECT c.id, c.classroom_name, g.name AS grade_name, el.name AS level_name
            FROM classrooms c
            JOIN grades g ON c.grade_id = g.id
            JOIN education_levels el ON g.level_id = el.id
            WHERE c.school_id = ?
            ORDER BY g.id ASC, c.classroom_name ASC
        ");
        $stmtC->execute([$schoolId]);
        $classrooms = $stmtC->fetchAll(PDO::FETCH_ASSOC);

        // Fetch all subjects
        $stmtS = $conn->prepare("SELECT code, name FROM subjects WHERE school_id = ? OR school_id IS NULL ORDER BY name ASC");
        $stmtS->execute([$schoolId]);
        $subjects = $stmtS->fetchAll(PDO::FETCH_ASSOC);

        if (empty($subjects)) {
            $subjects = [
                ['code' => 'MATH', 'name' => 'Basic Mathematics'],
                ['code' => 'ENG', 'name' => 'English Language'],
                ['code' => 'KISW', 'name' => 'Kiswahili'],
                ['code' => 'PHY', 'name' => 'Physics'],
                ['code' => 'CHEM', 'name' => 'Chemistry'],
                ['code' => 'BIO', 'name' => 'Biology'],
                ['code' => 'GEO', 'name' => 'Geography'],
                ['code' => 'HIST', 'name' => 'History'],
                ['code' => 'CIV', 'name' => 'Civics']
            ];
        }

        // Fetch student count per classroom
        $stmtCount = $conn->prepare("
            SELECT classroom_id, COUNT(*) as student_count
            FROM student_classroom_allocations
            WHERE school_id = ? AND status = 'Active'
            GROUP BY classroom_id
        ");
        $stmtCount->execute([$schoolId]);
        $studentCounts = $stmtCount->fetchAll(PDO::FETCH_KEY_PAIR);

        // Fetch marks submission entry counts grouped by classroom and subject
        $stmtSubmissions = $conn->prepare("
            SELECT sca.classroom_id, m.subject_code, COUNT(DISTINCT m.student_id) as students_entered, COUNT(m.id) as total_marks_count
            FROM marks_entry_dynamic m
            JOIN student_classroom_allocations sca ON (m.student_id = sca.student_id AND sca.school_id = m.school_id)
            WHERE m.school_id = ? AND m.academic_year = ? AND m.term = ?
            GROUP BY sca.classroom_id, m.subject_code
        ");
        $stmtSubmissions->execute([$schoolId, $year, $term]);
        $submissionRows = $stmtSubmissions->fetchAll(PDO::FETCH_ASSOC);

        $submissionMap = [];
        foreach ($submissionRows as $sub) {
            $submissionMap[$sub['classroom_id']][$sub['subject_code']] = [
                'students_entered' => intval($sub['students_entered']),
                'total_marks' => intval($sub['total_marks_count'])
            ];
        }

        // Fetch locks
        $stmtLocks = $conn->prepare("
            SELECT classroom_id, subject_code, is_locked, locked_at, u.full_name AS locked_by_name
            FROM marks_entry_locks mel
            LEFT JOIN users u ON mel.locked_by = u.id
            WHERE mel.school_id = ? AND mel.academic_year = ? AND mel.term = ?
        ");
        $stmtLocks->execute([$schoolId, $year, $term]);
        $lockRows = $stmtLocks->fetchAll(PDO::FETCH_ASSOC);

        $lockMap = [];
        foreach ($lockRows as $lr) {
            $lockMap[$lr['classroom_id']][$lr['subject_code']] = $lr;
        }

        // Build composite matrix
        $matrix = [];
        $totalSheets = 0;
        $completedSheets = 0;
        $inProgressSheets = 0;
        $pendingSheets = 0;
        $lockedSheets = 0;

        foreach ($classrooms as $cls) {
            $cid = $cls['id'];
            $stCount = intval($studentCounts[$cid] ?? 0);
            if ($stCount === 0) continue; // Skip empty classrooms

            $classItem = [
                'classroom_id' => $cid,
                'classroom_name' => $cls['classroom_name'],
                'grade_name' => $cls['grade_name'],
                'level_name' => $cls['level_name'],
                'students_count' => $stCount,
                'subjects' => []
            ];

            foreach ($subjects as $sub) {
                $scode = $sub['code'];
                $subData = $submissionMap[$cid][$scode] ?? ['students_entered' => 0, 'total_marks' => 0];
                $lockData = $lockMap[$cid][$scode] ?? null;
                $isLocked = !empty($lockData) && intval($lockData['is_locked']) === 1;

                $entered = $subData['students_entered'];
                $percentage = $stCount > 0 ? round(($entered / $stCount) * 100, 0) : 0;

                $status = 'Pending';
                if ($percentage >= 100) {
                    $status = 'Completed';
                    $completedSheets++;
                } elseif ($percentage > 0) {
                    $status = 'In-Progress';
                    $inProgressSheets++;
                } else {
                    $pendingSheets++;
                }

                if ($isLocked) {
                    $lockedSheets++;
                }
                $totalSheets++;

                $classItem['subjects'][] = [
                    'subject_code' => $scode,
                    'subject_name' => $sub['name'],
                    'students_entered' => $entered,
                    'total_students' => $stCount,
                    'completion_percent' => $percentage,
                    'status' => $status,
                    'is_locked' => $isLocked,
                    'locked_details' => $lockData
                ];
            }

            $matrix[] = $classItem;
        }

        echo json_encode([
            'success' => true,
            'year' => $year,
            'term' => $term,
            'summary' => [
                'total_sheets' => $totalSheets,
                'completed_sheets' => $completedSheets,
                'in_progress_sheets' => $inProgressSheets,
                'pending_sheets' => $pendingSheets,
                'locked_sheets' => $lockedSheets
            ],
            'matrix' => $matrix
        ]);
        exit();
    }

    // ────────────────────────────────────────────────────────────────────────
    // 7. TOGGLE LOCK / UNLOCK
    // ────────────────────────────────────────────────────────────────────────
    if ($action === 'toggle_lock') {
        $classroomId = intval($input['classroom_id'] ?? 0);
        $subjectCode = trim($input['subject_code'] ?? '');
        $year = trim($input['year'] ?? date('Y'));
        $term = trim($input['term'] ?? 'Term 1');
        $lockState = intval($input['lock_state'] ?? 1); // 1 = lock, 0 = unlock

        if (!$classroomId || empty($subjectCode)) {
            echo json_encode(['success' => false, 'message' => 'Classroom ID and Subject Code are required.']);
            exit();
        }

        $stmt = $conn->prepare("
            INSERT INTO marks_entry_locks (school_id, academic_year, term, classroom_id, subject_code, is_locked, locked_by, locked_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                is_locked = VALUES(is_locked),
                locked_by = VALUES(locked_by),
                locked_at = VALUES(locked_at),
                updated_at = NOW()
        ");
        $stmt->execute([$schoolId, $year, $term, $classroomId, $subjectCode, $lockState, $userId]);

        $stateLabel = $lockState ? 'locked (read-only finalization mode)' : 'unlocked for mark editing';
        echo json_encode([
            'success' => true,
            'message' => "Scoresheet successfully $stateLabel.",
            'is_locked' => ($lockState === 1)
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action requested.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
