<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../../config/db.php';

$userId = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;
$schoolId = $_SESSION['school_id'] ?? $_GET['school_id'] ?? null;

if (empty($schoolId) && !empty($userId)) {
    $uStmt = $conn->prepare("SELECT school_id FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$userId]);
    $schoolId = $uStmt->fetchColumn() ?: null;
}
if (empty($schoolId)) {
    $sStmt = $conn->query("SELECT id FROM schools ORDER BY id ASC LIMIT 1");
    $schoolId = $sStmt->fetchColumn() ?: null;
}

if (!$schoolId) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "School context missing."]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';

try {
    // ── ACTION: ENROLL / TOGGLE EDUCATION LEVEL ──
    if ($method === 'POST' && ($action === 'enroll_level' || $action === 'toggle_level')) {
        $levelCode = strtoupper(trim($input['level_code'] ?? ''));
        $targetStatus = $input['status'] ?? 'active';

        if (empty($levelCode)) {
            echo json_encode(["success" => false, "message" => "Level code is required."]);
            exit();
        }

        // Standardize level code alias (PRIM -> PRIMARY)
        $dbCode = ($levelCode === 'PRIM') ? 'PRIMARY' : $levelCode;

        // Fetch level metadata from education_levels
        $lvlStmt = $conn->prepare("SELECT * FROM education_levels WHERE code = ? OR (code = 'PRIMARY' AND ? IN ('PRIM', 'PRIMARY')) LIMIT 1");
        $lvlStmt->execute([$dbCode, $levelCode]);
        $lvlMeta = $lvlStmt->fetch(PDO::FETCH_ASSOC);

        $lvlName = $lvlMeta['name'] ?? $levelCode;
        $rangeText = ($dbCode === 'A-LEVEL') ? 'Form 5 – Form 6' : 'Form 1 – Form 4';

        $conn->beginTransaction();

        $stmtSel = $conn->prepare("
            INSERT INTO school_education_levels (school_id, level_code, status, created_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ");
        $stmtSel->execute([$schoolId, $levelCode, $targetStatus]);

        // Auto-provision school approved subjects from academic_templates if activating
        if ($targetStatus === 'active') {
            $tplSubStmt = $conn->prepare("
                SELECT name AS subject_name, code AS subject_code
                FROM academic_templates
                WHERE type = 'subject' AND (level_code = ? OR level_code = 'ALL')
            ");
            $tplSubStmt->execute([$levelCode]);
            $tplSubjects = $tplSubStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($tplSubjects)) {
                $insApp = $conn->prepare("
                    INSERT INTO school_approved_subjects (school_id, subject_code, subject_name, level_code, status)
                    VALUES (?, ?, ?, ?, 'active')
                    ON DUPLICATE KEY UPDATE status = 'active', subject_name = VALUES(subject_name)
                ");
                foreach ($tplSubjects as $s) {
                    $insApp->execute([$schoolId, $s['subject_code'], $s['subject_name'], $levelCode]);
                }
            }
        }

        $conn->commit();

        echo json_encode([
            "success" => true,
            "message" => "Education level '$lvlName' has been successfully " . ($targetStatus === 'active' ? "enrolled and activated" : "deactivated") . "."
        ]);
        exit();
    }

    // ── 1. Fetch Registered Active Education Levels for this School ──
    $stmtLevels = $conn->prepare("
        SELECT sel.school_id, sel.level_code, sel.status, el.id AS level_id, COALESCE(el.name, sel.level_code) AS level_name,
        CASE 
            WHEN el.code = 'O-LEVEL' OR sel.level_code = 'O-LEVEL' THEN 'Form 1 – Form 4'
            WHEN el.code = 'A-LEVEL' OR sel.level_code = 'A-LEVEL' THEN 'Form 5 – Form 6'
            ELSE 'Form 1 – Form 4'
        END AS range_text
        FROM school_education_levels sel
        LEFT JOIN education_levels el ON sel.level_code = el.code
        WHERE sel.school_id = ? AND sel.status = 'active' AND sel.level_code IN ('O-LEVEL', 'A-LEVEL')
        ORDER BY el.id ASC
    ");
    $stmtLevels->execute([$schoolId]);
    $levels = $stmtLevels->fetchAll(PDO::FETCH_ASSOC);

    // If school has no entries in school_education_levels, auto-default to O-LEVEL
    if (empty($levels)) {
        $conn->prepare("INSERT IGNORE INTO school_education_levels (school_id, level_code, status) VALUES (?, 'O-LEVEL', 'active')")
             ->execute([$schoolId]);

        $stmtLevels->execute([$schoolId]);
        $levels = $stmtLevels->fetchAll(PDO::FETCH_ASSOC);
    }

    $activeCodes = array_column($levels, 'level_code');
    $activeCodesNorm = $activeCodes;

    // ── 2. Master Education Levels (Super Admin Global Catalog - O-Level & A-Level) ──
    $masterLevels = $conn->query("
        SELECT el.id, el.name AS level_name, el.code AS level_code,
        CASE 
            WHEN el.code = 'A-LEVEL' THEN 'Form 5 – Form 6'
            ELSE 'Form 1 – Form 4'
        END AS range_text
        FROM education_levels el
        WHERE el.code IN ('O-LEVEL', 'A-LEVEL')
        ORDER BY el.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($masterLevels as &$ml) {
        $ml['is_enrolled'] = in_array($ml['level_code'], $activeCodesNorm);
    }
    unset($ml);

    // ── 3. Fetch School Approved Subjects ──
    $stmtSubjects = $conn->prepare("
        SELECT sas.id, sas.subject_code, sas.subject_name, sas.level_code, sas.status
        FROM school_approved_subjects sas
        WHERE sas.school_id = ? AND sas.status = 'active'
        ORDER BY sas.level_code ASC, sas.subject_name ASC
    ");
    $stmtSubjects->execute([$schoolId]);
    $subjects = $stmtSubjects->fetchAll(PDO::FETCH_ASSOC);

    // Auto-seed from academic_templates if school_approved_subjects is empty
    if (empty($subjects)) {
        $inCodes = "'" . implode("','", array_map('addslashes', $activeCodesNorm)) . "', 'ALL'";
        $tplSub = $conn->query("
            SELECT name AS subject_name, code AS subject_code, level_code
            FROM academic_templates
            WHERE type = 'subject' AND level_code IN ($inCodes)
            ORDER BY level_code ASC, name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($tplSub)) {
            $insApp = $conn->prepare("
                INSERT INTO school_approved_subjects (school_id, subject_code, subject_name, level_code, status)
                VALUES (?, ?, ?, ?, 'active')
                ON DUPLICATE KEY UPDATE status = 'active', subject_name = VALUES(subject_name)
            ");
            foreach ($tplSub as $s) {
                $insApp->execute([$schoolId, $s['subject_code'], $s['subject_name'], $s['level_code']]);
            }
            // Re-fetch
            $stmtSubjects->execute([$schoolId]);
            $subjects = $stmtSubjects->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // ── 4. Fetch System Grades Filtered By Active Registered Education Levels ──
    $inCodes = "'" . implode("','", array_map('addslashes', $activeCodesNorm)) . "'";
    $grades = $conn->query("
        SELECT g.id, g.level_id, g.name AS grade_name, g.order_seq, el.name AS level_name, el.code AS level_code
        FROM grades g
        JOIN education_levels el ON g.level_id = el.id
        WHERE el.code IN ($inCodes) OR (el.code = 'PRIMARY' AND 'PRIM' IN ($inCodes))
        ORDER BY el.id ASC, g.order_seq ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ── 5. Fetch Grade Subjects Curriculum Map ──
    $stmtGS = $conn->prepare("
        SELECT gs.id, gs.grade_id, gs.subject_code, gs.subject_name, gs.is_core
        FROM grade_subjects gs
        JOIN grades g ON gs.grade_id = g.id
        WHERE gs.school_id = ?
        ORDER BY g.id, gs.is_core DESC, gs.subject_name ASC
    ");
    $stmtGS->execute([$schoolId]);
    $allGradeSubjects = $stmtGS->fetchAll(PDO::FETCH_ASSOC);

    $gradeSubjectsMap = [];
    foreach ($allGradeSubjects as $gs) {
        $gradeSubjectsMap[$gs['grade_id']][] = $gs;
    }

    // Auto-map template subjects for grades if grade_subjects is empty
    if (empty($allGradeSubjects)) {
        // Look up class templates in academic_templates
        $classTpls = $conn->query("
            SELECT name, details, level_code FROM academic_templates WHERE type = 'class'
        ")->fetchAll(PDO::FETCH_ASSOC);

        $tplDetailsMap = [];
        foreach ($classTpls as $ct) {
            $details = json_decode($ct['details'] ?? '{}', true);
            if (!empty($details['assigned_subjects'])) {
                $tplDetailsMap[strtoupper(trim($ct['name']))] = $details['assigned_subjects'];
            }
        }

        foreach ($grades as $g) {
            $gNameUpper = strtoupper(trim($g['grade_name']));
            if (isset($tplDetailsMap[$gNameUpper])) {
                $gradeSubjectsMap[$g['id']] = $tplDetailsMap[$gNameUpper];
            }
        }
    }

    echo json_encode([
        "success" => true,
        "school_id" => $schoolId,
        "education_levels" => $levels,
        "master_education_levels" => $masterLevels,
        "grades" => $grades,
        "grade_subjects" => $gradeSubjectsMap,
        "approved_subjects" => $subjects
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
