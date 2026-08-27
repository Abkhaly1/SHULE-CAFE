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
    // GET: Fetch subjects for a specific grade_id strictly based on Super Admin Grade Template
    if ($method === 'GET' && $action === 'get_grade_subjects') {
        $gradeId = intval($_GET['grade_id'] ?? 0);
        if (!$gradeId) {
            echo json_encode(["success" => false, "message" => "grade_id parameter is required."]);
            exit();
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
            echo json_encode(["success" => false, "message" => "Grade not found."]);
            exit();
        }

        $levelCode = $grade['level_code'];
        $gradeName = $grade['grade_name'];

        // 2. Fetch Super Admin Class Template for this exact Grade
        $stmtTpl = $conn->prepare("
            SELECT details FROM academic_templates
            WHERE type = 'class' AND (name = ? OR code = ?) AND (level_code = ? OR level_code IS NULL)
            LIMIT 1
        ");
        $stmtTpl->execute([$gradeName, $gradeName, $levelCode]);
        $rawDetails = $stmtTpl->fetchColumn();

        $tplDetails  = json_decode($rawDetails ?? '{}', true) ?? [];
        $tplAssigned = $tplDetails['assigned_subjects'] ?? [];

        $tplAssignedMap = [];
        foreach ($tplAssigned as $tas) {
            $code   = is_array($tas) ? ($tas['subject_code'] ?? '') : $tas;
            $isCore = is_array($tas) ? intval($tas['is_core'] ?? 1) : 1;
            if ($code) $tplAssignedMap[$code] = $isCore;
        }

        // 3. Fetch Master Subject Templates for this Education Level ONLY
        $stmtS = $conn->prepare("
            SELECT code AS subject_code, name AS subject_name, level_code
            FROM academic_templates
            WHERE type = 'subject' AND (level_code = ? OR level_code IS NULL OR level_code = 'ALL')
            ORDER BY name ASC
        ");
        $stmtS->execute([$levelCode]);
        $levelSubjects = $stmtS->fetchAll(PDO::FETCH_ASSOC);

        // Fallback to school_approved_subjects for this level_code if academic_templates is empty
        if (empty($levelSubjects)) {
            $stmtApp = $conn->prepare("
                SELECT subject_code, subject_name, level_code
                FROM school_approved_subjects
                WHERE school_id = ? AND (level_code = ? OR level_code IS NULL) AND status = 'active'
                ORDER BY subject_name ASC
            ");
            $stmtApp->execute([$schoolId, $levelCode]);
            $levelSubjects = $stmtApp->fetchAll(PDO::FETCH_ASSOC);
        }

        // 4. Fetch subjects currently assigned in grade_subjects for this school & year & grade
        $stmtGS = $conn->prepare("
            SELECT subject_code, subject_name, is_core
            FROM grade_subjects
            WHERE school_id = ? AND academic_year = ? AND grade_id = ?
            ORDER BY is_core DESC, subject_name ASC
        ");
        $stmtGS->execute([$schoolId, $year, $gradeId]);
        $dbAssigned = $stmtGS->fetchAll(PDO::FETCH_ASSOC);

        $dbAssignedMap = [];
        foreach ($dbAssigned as $da) {
            $dbAssignedMap[$da['subject_code']] = intval($da['is_core']);
        }

        // If DB has no assigned subjects for this grade yet, auto-initialize from Super Admin Grade Template details!
        $useTplDefault = empty($dbAssigned);

        // Filter & build checklist strictly for this level and grade
        $subjectChecklist = [];
        $finalAssigned    = [];

        foreach ($levelSubjects as $ls) {
            $code = $ls['subject_code'];

            if ($useTplDefault) {
                // Use Super Admin Template definition
                $isAssigned = isset($tplAssignedMap[$code]);
                $isCore     = $isAssigned ? intval($tplAssignedMap[$code]) : 1;
            } else {
                // Use School DB configuration
                $isAssigned = isset($dbAssignedMap[$code]);
                $isCore     = $isAssigned ? intval($dbAssignedMap[$code]) : (isset($tplAssignedMap[$code]) ? intval($tplAssignedMap[$code]) : 1);
            }

            $item = [
                'subject_code' => $code,
                'subject_name' => $ls['subject_name'],
                'is_assigned'   => $isAssigned,
                'is_core'       => $isCore
            ];

            $subjectChecklist[] = $item;

            if ($isAssigned) {
                $finalAssigned[] = [
                    'subject_code' => $code,
                    'subject_name' => $ls['subject_name'],
                    'is_core'       => $isCore
                ];
            }
        }

        echo json_encode([
            "success"            => true,
            "grade"              => $grade,
            "academic_year"      => $year,
            "assigned_subjects"  => $finalAssigned,
            "subject_checklist"  => $subjectChecklist
        ]);
        exit();
    }

    // POST: Save assigned subjects for a grade
    if ($method === 'POST' && $action === 'save_grade_subjects') {
        $gradeId          = intval($input['grade_id'] ?? 0);
        $assignedSubjects = $input['assigned_subjects'] ?? []; // [{ subject_code, is_core }]

        if (!$gradeId) {
            echo json_encode(["success" => false, "message" => "grade_id is required."]);
            exit();
        }

        $conn->beginTransaction();

        // 1. Clear existing assigned subjects for this grade & year
        $stmtDel = $conn->prepare("DELETE FROM grade_subjects WHERE school_id = ? AND academic_year = ? AND grade_id = ?");
        $stmtDel->execute([$schoolId, $year, $gradeId]);

        // 2. Insert updated subjects
        $stmtIns = $conn->prepare("
            INSERT INTO grade_subjects (school_id, academic_year, grade_id, subject_code, subject_name, is_core)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $savedCount = 0;
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

            $stmtIns->execute([$schoolId, $year, $gradeId, $code, $sbjName, $isCore]);
            $savedCount++;
        }

        $conn->commit();

        echo json_encode([
            "success" => true,
            "saved_count" => $savedCount,
            "message" => "Grade-level master subject curriculum updated successfully!"
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
