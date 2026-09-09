<?php
/**
 * SHULE CAFE Enterprise
 * API: Real-Time Curriculum Synchronization to Active School Tenants
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$levelCode = trim($input['level_code'] ?? '');

if (empty($levelCode)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Level code is required."]);
    exit();
}

try {
    $conn->beginTransaction();

    // 1. Find all active schools offering this level
    $stmtSch = $conn->prepare("
        SELECT DISTINCT s.id, s.name 
        FROM schools s
        JOIN school_education_levels sel ON s.id = sel.school_id
        WHERE sel.level_code = ? AND sel.status = 'active' AND s.status = 'active'
    ");
    $stmtSch->execute([$levelCode]);
    $activeSchools = $stmtSch->fetchAll(PDO::FETCH_ASSOC);

    if (empty($activeSchools)) {
        // Fallback: search all active schools if level is ALL or no level binding
        if ($levelCode === 'ALL') {
            $stmtAll = $conn->query("SELECT id, name FROM schools WHERE status = 'active'");
            $activeSchools = $stmtAll->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // 2. Fetch master subjects for this level
    $stmtSub = $conn->prepare("
        SELECT code, name, level_code 
        FROM academic_templates 
        WHERE type = 'subject' AND (level_code = ? OR level_code = 'ALL') AND status = 'active'
    ");
    $stmtSub->execute([$levelCode]);
    $masterSubjects = $stmtSub->fetchAll(PDO::FETCH_ASSOC);

    // 3. Prepare bulk upsert for school_approved_subjects
    $insSub = $conn->prepare("
        INSERT INTO school_approved_subjects (school_id, subject_code, subject_name, level_code, status)
        VALUES (?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE subject_name = VALUES(subject_name), status = 'active'
    ");

    $currentYear = date('Y');
    $syncedSchoolsCount = 0;
    $totalSubjectsProvisioned = 0;

    foreach ($activeSchools as $sch) {
        $schId = $sch['id'];
        $syncedSchoolsCount++;

        foreach ($masterSubjects as $sbj) {
            $insSub->execute([$schId, $sbj['code'], $sbj['name'], $levelCode]);
            $totalSubjectsProvisioned++;
        }
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Curriculum successfully synchronized to {$syncedSchoolsCount} active school(s). Total subject bindings updated: {$totalSubjectsProvisioned}.",
        "synced_schools_count" => $syncedSchoolsCount,
        "subjects_count" => count($masterSubjects)
    ]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database sync error: " . $e->getMessage()]);
}
