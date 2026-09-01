<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId && ($_SESSION['role'] ?? '') === 'super_admin') {
    $firstSchool = $conn->query("SELECT id FROM schools LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $schoolId = $firstSchool['id'] ?? null;
}

if (!$schoolId) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "School context missing."]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? 'get_grade_subjects';
$year   = $_GET['year']   ?? $input['year']   ?? date('Y');

try {
    // GET: Fetch subjects for a specific grade_id or level_code
    if ($method === 'GET' && ($action === 'get_grade_subjects' || $action === 'get_level_subjects')) {
        $gradeId = intval($_GET['grade_id'] ?? 0);
        $levelParam = trim($_GET['code'] ?? $_GET['level_code'] ?? '');

        if (!$gradeId && $levelParam) {
            $stmtFirstG = $conn->prepare("
                SELECT g.id, g.name AS grade_name, g.level_id, el.name AS level_name, el.code AS level_code
                FROM grades g
                JOIN education_levels el ON g.level_id = el.id
                WHERE el.code = ? OR el.name LIKE ?
                ORDER BY g.id ASC LIMIT 1
            ");
            $stmtFirstG->execute([$levelParam, "%$levelParam%"]);
            $grade = $stmtFirstG->fetch(PDO::FETCH_ASSOC);
            if ($grade) {
                $gradeId = intval($grade['id']);
            }
        }

        if (!$gradeId) {
            $gradeId = 1; // Default to Form 1
        }

        // 1. Fetch grade info & education level code
        $stmtG = $conn->prepare("
            SELECT g.id, g.name AS grade_name, g.level_id, el.name AS level_name, el.code AS level_code
            FROM grades g
            JOIN education_levels el ON g.level_id = el.id
            WHERE g.id = ?
        ");
        $stmtG->execute([$gradeId]);
        $grade = $stmtG->fetch(PDO::FETCH_ASSOC);

        if (!$grade) {
            echo json_encode(["success" => false, "message" => "Grade tier not found."]);
            exit();
        }

        $levelCode = $grade['level_code'];
        $levelId   = $grade['level_id'];
        $gradeName = $grade['grade_name'];

        // 2. Fetch Master Subject Templates for this Education Level ONLY (All available national subjects)
        $stmtS = $conn->prepare("
            SELECT code AS subject_code, name AS subject_name, level_code, details
            FROM academic_templates
            WHERE type = 'subject' AND (level_code = ? OR level_code = 'ALL')
            ORDER BY name ASC
        ");
        $stmtS->execute([$levelCode]);
        $levelSubjects = $stmtS->fetchAll(PDO::FETCH_ASSOC);

        // Fallback to school_approved_subjects if academic_templates is empty
        if (empty($levelSubjects)) {
            $stmtApp = $conn->prepare("
                SELECT subject_code, subject_name, level_code
                FROM school_approved_subjects
                WHERE school_id = ? AND (level_code = ? OR level_code = 'ALL') AND status = 'active'
                ORDER BY subject_name ASC
            ");
            $stmtApp->execute([$schoolId, $levelCode]);
            $levelSubjects = $stmtApp->fetchAll(PDO::FETCH_ASSOC);
        }

        // 3. Fetch subjects currently assigned in grade_subjects for this school & year & grade
        $stmtGS = $conn->prepare("
            SELECT subject_code, subject_name, is_core
            FROM grade_subjects
            WHERE school_id = ? AND academic_year = ? AND grade_id = ?
            ORDER BY is_core DESC, subject_name ASC
        ");
        $stmtGS->execute([$schoolId, $year, $gradeId]);
        $dbAssigned = $stmtGS->fetchAll(PDO::FETCH_ASSOC);

        // If no subjects for this grade yet, check if any other grade in the SAME level has configured subjects
        if (empty($dbAssigned)) {
            $stmtLevelAssigned = $conn->prepare("
                SELECT DISTINCT gs.subject_code, gs.subject_name, gs.is_core
                FROM grade_subjects gs
                JOIN grades g ON gs.grade_id = g.id
                WHERE gs.school_id = ? AND gs.academic_year = ? AND g.level_id = ?
                ORDER BY gs.is_core DESC, gs.subject_name ASC
            ");
            $stmtLevelAssigned->execute([$schoolId, $year, $levelId]);
            $dbAssigned = $stmtLevelAssigned->fetchAll(PDO::FETCH_ASSOC);
        }

        // Fallback to school_approved_subjects
        if (empty($dbAssigned)) {
            $stmtAppActive = $conn->prepare("
                SELECT subject_code, subject_name, 1 AS is_core
                FROM school_approved_subjects
                WHERE school_id = ? AND (level_code = ? OR level_code = 'ALL') AND status = 'active'
                ORDER BY subject_name ASC
            ");
            $stmtAppActive->execute([$schoolId, $levelCode]);
            $dbAssigned = $stmtAppActive->fetchAll(PDO::FETCH_ASSOC);
        }

        $dbAssignedMap = [];
        foreach ($dbAssigned as $da) {
            $dbAssignedMap[$da['subject_code']] = intval($da['is_core']);
        }

        // Fetch Class Template defaults as initial suggestion if completely unconfigured
        $tplAssignedMap = [];
        if (empty($dbAssigned)) {
            $stmtTpl = $conn->prepare("
                SELECT details FROM academic_templates
                WHERE type = 'class' AND (name = ? OR code = ?) AND (level_code = ? OR level_code IS NULL)
                LIMIT 1
            ");
            $stmtTpl->execute([$gradeName, $gradeName, $levelCode]);
            $rawDetails = $stmtTpl->fetchColumn();
            $tplDetails = json_decode($rawDetails ?? '{}', true) ?? [];
            foreach (($tplDetails['assigned_subjects'] ?? []) as $tas) {
                $code = is_array($tas) ? ($tas['subject_code'] ?? '') : $tas;
                $isCore = is_array($tas) ? intval($tas['is_core'] ?? 1) : 1;
                if ($code) $tplAssignedMap[$code] = $isCore;
            }
        }

        $useTplDefault = empty($dbAssigned) && !empty($tplAssignedMap);

        // Filter & build checklist
        $subjectChecklist = [];
        $finalAssigned    = [];

        foreach ($levelSubjects as $ls) {
            $code = $ls['subject_code'];

            if ($useTplDefault) {
                $isAssigned = isset($tplAssignedMap[$code]);
                $isCore     = $isAssigned ? intval($tplAssignedMap[$code]) : 1;
            } else {
                $isAssigned = isset($dbAssignedMap[$code]);
                $isCore     = $isAssigned ? intval($dbAssignedMap[$code]) : 1;
            }

            $details = json_decode($ls['details'] ?? '{}', true) ?? [];
            $abbr = $details['abbr'] ?? $code;
            $courseCode = $details['course_code'] ?? $code;
            $category = $details['category'] ?? '';

            $item = [
                'subject_code' => $code,
                'subject_name' => $ls['subject_name'],
                'abbr'         => $abbr,
                'course_code'  => $courseCode,
                'category'     => $category,
                'is_assigned'  => $isAssigned,
                'is_core'      => $isCore,
                'is_subsidiary'=> !empty($details['is_subsidiary']),
                'is_principal' => !empty($details['is_principal'])
            ];

            $subjectChecklist[] = $item;

            if ($isAssigned) {
                $finalAssigned[] = [
                    'subject_code' => $code,
                    'subject_name' => $ls['subject_name'],
                    'abbr'         => $abbr,
                    'course_code'  => $courseCode,
                    'category'     => $category,
                    'is_core'      => $isCore,
                    'is_subsidiary'=> !empty($details['is_subsidiary']),
                    'is_principal' => !empty($details['is_principal'])
                ];
            }
        }

        // Fetch sibling grades in the same education level
        $stmtSiblings = $conn->prepare("SELECT id, name FROM grades WHERE level_id = ? ORDER BY id ASC");
        $stmtSiblings->execute([$levelId]);
        $siblingGrades = $stmtSiblings->fetchAll(PDO::FETCH_ASSOC);

        // Fetch combinations if A-Level
        $combinations = [];
        if ($levelCode === 'A-LEVEL') {
            $stmtCmb = $conn->prepare("
                SELECT id, name, code, description, details
                FROM academic_templates
                WHERE type = 'combination' AND level_code = 'A-LEVEL'
                ORDER BY code ASC
            ");
            $stmtCmb->execute();
            $rawCmbs = $stmtCmb->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rawCmbs as $rc) {
                $cDetails = json_decode($rc['details'] ?? '{}', true) ?? [];
                $combinations[] = [
                    'id' => $rc['id'],
                    'name' => $rc['name'],
                    'code' => $rc['code'],
                    'abbr' => $cDetails['abbr'] ?? $rc['code'],
                    'course_code' => $cDetails['course_code'] ?? '',
                    'stream' => $cDetails['stream'] ?? '',
                    'principals' => $cDetails['principals'] ?? [],
                    'subsidiaries' => $cDetails['subsidiaries'] ?? [],
                    'career_pathways' => $cDetails['career_pathways'] ?? []
                ];
            }
        }

        echo json_encode([
            "success"            => true,
            "grade"              => $grade,
            "sibling_grades"     => $siblingGrades,
            "combinations"       => $combinations,
            "academic_year"      => $year,
            "assigned_subjects"  => $finalAssigned,
            "subject_checklist"  => $subjectChecklist
        ]);
        exit();
    }

    // POST: Save assigned subjects for a grade or entire education level
    if ($method === 'POST' && ($action === 'save_grade_subjects' || $action === 'save_level_subjects')) {
        $gradeId          = intval($input['grade_id'] ?? 0);
        $levelParam       = trim($input['level_code'] ?? '');
        $assignedSubjects = $input['assigned_subjects'] ?? []; // [{ subject_code, is_core }]
        $applyToAllGrades = !isset($input['apply_to_all_level_grades']) || !empty($input['apply_to_all_level_grades']);

        if (!$gradeId && $levelParam) {
            $stmtFirstG = $conn->prepare("
                SELECT g.id, g.name AS grade_name, g.level_id, el.name AS level_name, el.code AS level_code
                FROM grades g
                JOIN education_levels el ON g.level_id = el.id
                WHERE el.code = ? OR el.name LIKE ?
                ORDER BY g.id ASC LIMIT 1
            ");
            $stmtFirstG->execute([$levelParam, "%$levelParam%"]);
            $grade = $stmtFirstG->fetch(PDO::FETCH_ASSOC);
            if ($grade) {
                $gradeId = intval($grade['id']);
            }
        }

        if (!$gradeId) {
            echo json_encode(["success" => false, "message" => "grade_id or level_code is required."]);
            exit();
        }

        // Fetch grade info & education level
        $stmtG = $conn->prepare("
            SELECT g.id, g.name AS grade_name, g.level_id, el.name AS level_name, el.code AS level_code
            FROM grades g
            JOIN education_levels el ON g.level_id = el.id
            WHERE g.id = ?
        ");
        $stmtG->execute([$gradeId]);
        $grade = $stmtG->fetch(PDO::FETCH_ASSOC);

        if (!$grade) {
            echo json_encode(["success" => false, "message" => "Grade tier not found."]);
            exit();
        }

        $levelId   = $grade['level_id'];
        $levelCode = $grade['level_code'];

        $targetGradeIds = [$gradeId];
        if ($applyToAllGrades) {
            $stmtAllG = $conn->prepare("SELECT id FROM grades WHERE level_id = ?");
            $stmtAllG->execute([$levelId]);
            $targetGradeIds = $stmtAllG->fetchAll(PDO::FETCH_COLUMN);
        }

        $conn->beginTransaction();

        $stmtDel = $conn->prepare("DELETE FROM grade_subjects WHERE school_id = ? AND academic_year = ? AND grade_id = ?");
        $stmtIns = $conn->prepare("
            INSERT INTO grade_subjects (school_id, academic_year, grade_id, subject_code, subject_name, is_core)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $insApp = $conn->prepare("
            INSERT INTO school_approved_subjects (school_id, subject_code, subject_name, level_code, status)
            VALUES (?, ?, ?, ?, 'active')
            ON DUPLICATE KEY UPDATE status = 'active', subject_name = VALUES(subject_name)
        ");

        $savedSubjectsMap = [];

        foreach ($targetGradeIds as $gid) {
            $stmtDel->execute([$schoolId, $year, $gid]);

            foreach ($assignedSubjects as $as) {
                $code   = is_array($as) ? ($as['subject_code'] ?? '') : $as;
                $isCore = is_array($as) ? intval($as['is_core'] ?? 1) : 1;

                if (!$code) continue;

                // Fetch subject name from academic_templates or school_approved_subjects
                $stmtN = $conn->prepare("SELECT name FROM academic_templates WHERE type = 'subject' AND code = ? LIMIT 1");
                $stmtN->execute([$code]);
                $sbjName = $stmtN->fetchColumn();

                if (!$sbjName) {
                    $stmtN2 = $conn->prepare("SELECT subject_name FROM school_approved_subjects WHERE school_id = ? AND subject_code = ? LIMIT 1");
                    $stmtN2->execute([$schoolId, $code]);
                    $sbjName = $stmtN2->fetchColumn() ?: $code;
                }

                $stmtIns->execute([$schoolId, $year, $gid, $code, $sbjName, $isCore]);
                $insApp->execute([$schoolId, $code, $sbjName, $levelCode]);

                $savedSubjectsMap[$code] = $sbjName;
            }
        }

        $conn->commit();

        $gradeCount = count($targetGradeIds);
        $subjectCount = count($savedSubjectsMap);
        $message = $applyToAllGrades 
            ? "Curriculum updated successfully! {$subjectCount} subjects applied across all {$gradeCount} grades in {$grade['level_name']}."
            : "Curriculum for {$grade['grade_name']} updated successfully with {$subjectCount} subjects.";

        echo json_encode([
            "success" => true,
            "saved_grades_count" => $gradeCount,
            "saved_subjects_count" => $subjectCount,
            "message" => $message
        ]);
        exit();
    }

    echo json_encode(["success" => false, "message" => "Invalid action."]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
