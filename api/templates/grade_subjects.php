<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? 'get_grade_template';
$classId = $_GET['class_id'] ?? $input['class_id'] ?? '';

try {
    // GET: Fetch Grade Template Details & Subject Checklist
    if ($method === 'GET' && $action === 'get_grade_template') {
        if (!$classId) {
            echo json_encode(["success" => false, "message" => "class_id parameter is required."]);
            exit();
        }

        // 1. Fetch Class Template row
        $stmtC = $conn->prepare("SELECT * FROM academic_templates WHERE id = ? AND type = 'class'");
        $stmtC->execute([$classId]);
        $classTpl = $stmtC->fetch(PDO::FETCH_ASSOC);

        if (!$classTpl) {
            echo json_encode(["success" => false, "message" => "Class template not found."]);
            exit();
        }

        $levelCode = $classTpl['level_code'];
        $details   = json_decode($classTpl['details'] ?? '{}', true) ?? [];
        $assignedList = $details['assigned_subjects'] ?? [];

        $assignedMap = [];
        foreach ($assignedList as $as) {
            $code = is_array($as) ? ($as['subject_code'] ?? '') : $as;
            $isCore = is_array($as) ? intval($as['is_core'] ?? 1) : 1;
            if ($code) $assignedMap[$code] = $isCore;
        }

        // 2. Fetch all subject templates for this level_code
        $stmtS = $conn->prepare("
            SELECT id, name AS subject_name, code AS subject_code, level_code, description
            FROM academic_templates
            WHERE type = 'subject' AND (level_code = ? OR level_code IS NULL OR level_code = 'ALL')
            ORDER BY name ASC
        ");
        $stmtS->execute([$levelCode]);
        $allSubjects = $stmtS->fetchAll(PDO::FETCH_ASSOC);

        // Build checklist
        $subjectChecklist = [];
        foreach ($allSubjects as $sbj) {
            $code = $sbj['subject_code'];
            $isAssigned = isset($assignedMap[$code]);
            $isCore     = $isAssigned ? intval($assignedMap[$code]) : 1;

            $subjectChecklist[] = [
                'subject_code' => $code,
                'subject_name' => $sbj['subject_name'],
                'is_assigned'   => $isAssigned,
                'is_core'       => $isCore
            ];
        }

        echo json_encode([
            "success"           => true,
            "class_template"    => $classTpl,
            "assigned_subjects" => $assignedList,
            "subject_checklist" => $subjectChecklist
        ]);
        exit();
    }

    // POST: Save Grade Template Subjects & Real-Time Sync to Schools
    if ($method === 'POST' && $action === 'save_grade_template') {
        if (!$classId) {
            echo json_encode(["success" => false, "message" => "class_id parameter is required."]);
            exit();
        }

        $assignedSubjects = $input['assigned_subjects'] ?? []; // [{ subject_code, is_core }]

        // Fetch existing template details
        $stmtC = $conn->prepare("SELECT name, code, level_code, details FROM academic_templates WHERE id = ? AND type = 'class'");
        $stmtC->execute([$classId]);
        $classTpl = $stmtC->fetch(PDO::FETCH_ASSOC);

        if (!$classTpl) {
            echo json_encode(["success" => false, "message" => "Class template not found."]);
            exit();
        }

        $cName     = $classTpl['name'];
        $levelCode = $classTpl['level_code'];

        $details = json_decode($classTpl['details'] ?? '{}', true) ?? [];
        $details['assigned_subjects'] = $assignedSubjects;
        $details['updated_at']        = date('Y-m-d H:i:s');

        $jsonDetails = json_encode($details);

        $conn->beginTransaction();

        // 1. Update Master Academic Template
        $stmtUpd = $conn->prepare("UPDATE academic_templates SET details = ? WHERE id = ? AND type = 'class'");
        $stmtUpd->execute([$jsonDetails, $classId]);

        // 2. REAL-TIME SYNCHRONIZATION Across All Active School Tenants
        $stmtG = $conn->prepare("SELECT id FROM grades WHERE name = ? LIMIT 1");
        $stmtG->execute([$cName]);
        $gradeId = $stmtG->fetchColumn();

        if ($gradeId) {
            // Find all schools operating this level
            $stmtSch = $conn->prepare("SELECT school_id FROM school_education_levels WHERE level_code = ? AND status = 'active'");
            $stmtSch->execute([$levelCode]);
            $activeSchools = $stmtSch->fetchAll(PDO::FETCH_COLUMN);

            $insGS = $conn->prepare("
                INSERT INTO grade_subjects (school_id, academic_year, grade_id, subject_code, subject_name, is_core)
                VALUES (?, '" . date('Y') . "', ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE subject_name = VALUES(subject_name), is_core = VALUES(is_core)
            ");

            foreach ($activeSchools as $schId) {
                foreach ($assignedSubjects as $as) {
                    $code   = is_array($as) ? ($as['subject_code'] ?? '') : $as;
                    $isCore = is_array($as) ? intval($as['is_core'] ?? 1) : 1;

                    if ($code) {
                        $stmtN = $conn->prepare("SELECT name FROM academic_templates WHERE type = 'subject' AND code = ? LIMIT 1");
                        $stmtN->execute([$code]);
                        $sbjName = $stmtN->fetchColumn() ?: $code;

                        $insGS->execute([$schId, $gradeId, $code, $sbjName, $isCore]);
                    }
                }
            }
        }

        $conn->commit();

        echo json_encode([
            "success" => true,
            "message" => "Grade template curriculum updated and real-time synchronized across all active school tenants."
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
