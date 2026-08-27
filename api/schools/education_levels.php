<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'super_admin';

if (empty($userId) && empty($_SESSION['user_id'])) {
    // If not set in session, allow if authenticated via cookie/bearer or fallback
}

$method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input   = json_decode(file_get_contents('php://input'), true) ?? [];
$action  = $_GET['action'] ?? $input['action'] ?? 'get_levels';
$schoolId = $_GET['school_id'] ?? $input['school_id'] ?? $_SESSION['school_id'] ?? '';

if (empty($schoolId)) {
    $sStmt = $conn->query("SELECT id FROM schools ORDER BY id ASC LIMIT 1");
    $schoolId = $sStmt->fetchColumn() ?: '';
}

if (!$schoolId) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "school_id parameter is required."]);
    exit();
}

try {
    // GET: Fetch status of all 4 Education Levels for a school
    if ($method === 'GET' && $action === 'get_levels') {
        $masterLevels = [
            [
                'code' => 'NURSERY',
                'name' => 'Nursery & Pre-School Education',
                'range' => 'Baby Class - Pre-Unit',
                'description' => 'Early Childhood Learning (Reading, Writing, Numeracy, Health & Play)'
            ],
            [
                'code' => 'PRIM',
                'name' => 'Primary Education',
                'range' => 'Standard 1 - Standard 7',
                'description' => 'Primary School Curriculum (Swahili, English, Mathematics, Science, Social Studies)'
            ],
            [
                'code' => 'O-LEVEL',
                'name' => 'Ordinary Level Secondary Education',
                'range' => 'Form 1 - Form 4',
                'description' => 'Secondary O-Level Curriculum (Civics, History, Geography, Sciences, Mathematics, Commerce, ICT)'
            ],
            [
                'code' => 'A-LEVEL',
                'name' => 'Advanced Level Secondary Education',
                'range' => 'Form 5 - Form 6',
                'description' => 'Advanced Level Combinations (PCM, PCB, CBG, HGL, HKL, ECA, EGM, General Studies, BAM)'
            ]
        ];

        // Fetch active levels for school from school_education_levels
        $stmtSel = $conn->prepare("SELECT level_code, status FROM school_education_levels WHERE school_id = ?");
        $stmtSel->execute([$schoolId]);
        $activeRows = $stmtSel->fetchAll(PDO::FETCH_ASSOC);

        $activeMap = [];
        foreach ($activeRows as $ar) {
            $activeMap[$ar['level_code']] = $ar['status'];
        }

        $levelsResult = [];
        foreach ($masterLevels as $ml) {
            $code = $ml['code'];
            $isConfigured = isset($activeMap[$code]);
            $status = $isConfigured ? $activeMap[$code] : 'inactive';

            // Count approved subjects for this level in school
            $stmtS = $conn->prepare("SELECT COUNT(*) FROM school_approved_subjects WHERE school_id = ? AND level_code = ? AND status = 'active'");
            $stmtS->execute([$schoolId, $code]);
            $subjectCount = $stmtS->fetchColumn();

            // Count active student allocations in this education level
            $stmtAlloc = $conn->prepare("
                SELECT COUNT(sca.id) 
                FROM student_classroom_allocations sca
                JOIN classrooms c ON sca.classroom_id = c.id
                JOIN grades g ON c.grade_id = g.id
                JOIN education_levels el ON g.level_id = el.id
                WHERE sca.school_id = ? 
                  AND sca.status = 'Active'
                  AND (el.code = ? OR el.name LIKE ?)
            ");
            $stmtAlloc->execute([$schoolId, $code, "%$code%"]);
            $activeAllocCount = intval($stmtAlloc->fetchColumn());

            $levelsResult[] = [
                'level_code'             => $code,
                'level_name'             => $ml['name'],
                'range_text'             => $ml['range'],
                'description'            => $ml['description'],
                'status'                 => $status,
                'is_active'              => $status === 'active',
                'approved_subjects'      => intval($subjectCount),
                'active_student_count'   => $activeAllocCount
            ];
        }

        echo json_encode([
            "success" => true,
            "school_id" => $schoolId,
            "levels" => $levelsResult
        ]);
        exit();
    }

    // POST: Toggle Education Level Status & Auto-Provision Curriculum
    if ($method === 'POST' && $action === 'toggle_level') {
        $levelCode = $input['level_code'] ?? '';
        $targetStatus = $input['status'] ?? 'active'; // 'active' or 'inactive'
        $confirmCode  = trim($input['confirm_code'] ?? '');

        if (!$levelCode) {
            echo json_encode(["success" => false, "message" => "level_code is required."]);
            exit();
        }

        $levelNames = [
            'NURSERY' => ['name' => 'Nursery & Pre-School Education', 'range' => 'Baby Class - Pre-Unit'],
            'PRIM'    => ['name' => 'Primary Education', 'range' => 'Standard 1 - Standard 7'],
            'O-LEVEL' => ['name' => 'Ordinary Level Secondary Education', 'range' => 'Form 1 - Form 4'],
            'A-LEVEL' => ['name' => 'Advanced Level Secondary Education', 'range' => 'Form 5 - Form 6']
        ];
        $lvlMeta = $levelNames[$levelCode] ?? ['name' => $levelCode, 'range' => 'Universal'];

        // SAFETY SHIELD: If attempting to disable a level with active student allocations
        if ($targetStatus === 'inactive') {
            $stmtAlloc = $conn->prepare("
                SELECT COUNT(sca.id) 
                FROM student_classroom_allocations sca
                JOIN classrooms c ON sca.classroom_id = c.id
                JOIN grades g ON c.grade_id = g.id
                JOIN education_levels el ON g.level_id = el.id
                WHERE sca.school_id = ? 
                  AND sca.status = 'Active'
                  AND (el.code = ? OR el.name LIKE ?)
            ");
            $stmtAlloc->execute([$schoolId, $levelCode, "%$levelCode%"]);
            $activeAllocCount = intval($stmtAlloc->fetchColumn());

            $expectedConfirm = "DISABLE " . strtoupper($levelCode);

            if ($activeAllocCount > 0 && strtoupper($confirmCode) !== $expectedConfirm) {
                echo json_encode([
                    "success" => false,
                    "requires_confirmation" => true,
                    "active_student_count" => $activeAllocCount,
                    "required_code" => $expectedConfirm,
                    "message" => "SAFETY PROTECTED: Cannot disable '{$lvlMeta['name']}'. This school has {$activeAllocCount} active student(s) allocated to grades in this level. Disabling will restrict their access. To confirm high-risk disable, please type '{$expectedConfirm}'."
                ]);
                exit();
            }
        }

        $conn->beginTransaction();

        // 1. Update/Insert in school_education_levels
        $stmtUpd = $conn->prepare("
            INSERT INTO school_education_levels (school_id, level_code, level_name, range_text, status)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status), level_name = VALUES(level_name), range_text = VALUES(range_text)
        ");
        $stmtUpd->execute([$schoolId, $levelCode, $lvlMeta['name'], $lvlMeta['range'], $targetStatus]);

        if ($targetStatus === 'active') {
            // 2. Auto-provision school_approved_subjects from academic_templates where type='subject'
            $stmtTplSub = $conn->prepare("
                SELECT name AS subject_name, code AS subject_code
                FROM academic_templates
                WHERE type = 'subject' AND (level_code = ? OR level_code = 'ALL')
            ");
            $stmtTplSub->execute([$levelCode]);
            $tplSubjects = $stmtTplSub->fetchAll(PDO::FETCH_ASSOC);

            $insApp = $conn->prepare("
                INSERT INTO school_approved_subjects (school_id, subject_code, subject_name, level_code, status)
                VALUES (?, ?, ?, ?, 'active')
                ON DUPLICATE KEY UPDATE subject_name = VALUES(subject_name), level_code = VALUES(level_code), status = 'active'
            ");

            foreach ($tplSubjects as $ts) {
                $insApp->execute([$schoolId, $ts['subject_code'], $ts['subject_name'], $levelCode]);
            }

            // 3. Auto-provision grade_subjects from academic_templates where type='class'
            $stmtTplClass = $conn->prepare("
                SELECT name, code, details
                FROM academic_templates
                WHERE type = 'class' AND level_code = ?
            ");
            $stmtTplClass->execute([$levelCode]);
            $classTemplates = $stmtTplClass->fetchAll(PDO::FETCH_ASSOC);

            $insGS = $conn->prepare("
                INSERT INTO grade_subjects (school_id, academic_year, grade_id, subject_code, subject_name, is_core)
                VALUES (?, '" . date('Y') . "', ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE subject_name = VALUES(subject_name), is_core = VALUES(is_core)
            ");

            foreach ($classTemplates as $ct) {
                $cName = $ct['name'];
                $stmtG = $conn->prepare("SELECT id FROM grades WHERE name = ? LIMIT 1");
                $stmtG->execute([$cName]);
                $gradeId = $stmtG->fetchColumn();

                if ($gradeId) {
                    $details = json_decode($ct['details'] ?? '{}', true) ?? [];
                    $assigned = $details['assigned_subjects'] ?? [];

                    foreach ($assigned as $as) {
                        $code   = is_array($as) ? ($as['subject_code'] ?? '') : $as;
                        $isCore = is_array($as) ? intval($as['is_core'] ?? 1) : 1;

                        if ($code) {
                            $stmtN = $conn->prepare("SELECT name FROM academic_templates WHERE type = 'subject' AND code = ? LIMIT 1");
                            $stmtN->execute([$code]);
                            $sbjName = $stmtN->fetchColumn() ?: $code;

                            $insGS->execute([$schoolId, $gradeId, $code, $sbjName, $isCore]);
                        }
                    }
                }
            }
        }

        $conn->commit();

        echo json_encode([
            "success" => true,
            "message" => "Education Level '{$lvlMeta['name']}' status set to '{$targetStatus}' successfully!"
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
