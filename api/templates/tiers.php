<?php
/**
 * SHULE CAFE Enterprise
 * API: Education Tiers Overview with Dynamic Curriculum Metrics
 */

session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

try {
    // 1. Fetch all Education Level tiers
    $stmt = $conn->query("
        SELECT id, name, code, level_code, description, details, status, created_at, updated_at 
        FROM academic_templates 
        WHERE type = 'level' 
        ORDER BY 
            CASE code
                WHEN 'NURSERY' THEN 1
                WHEN 'PRIM' THEN 2
                WHEN 'O-LEVEL' THEN 3
                WHEN 'A-LEVEL' THEN 4
                ELSE 5
            END ASC,
            name ASC
    ");
    $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Aggregate counts for each tier
    $tierList = [];
    foreach ($tiers as $t) {
        $lCode = $t['code'];

        // Classes count and range
        $stmtClasses = $conn->prepare("
            SELECT count(*) as total, GROUP_CONCAT(name ORDER BY code SEPARATOR ', ') as class_names 
            FROM academic_templates 
            WHERE type = 'class' AND level_code = ?
        ");
        $stmtClasses->execute([$lCode]);
        $classInfo = $stmtClasses->fetch(PDO::FETCH_ASSOC);

        // Subjects count
        $stmtSubjects = $conn->prepare("
            SELECT count(*) as total 
            FROM academic_templates 
            WHERE type = 'subject' AND (level_code = ? OR level_code = 'ALL')
        ");
        $stmtSubjects->execute([$lCode]);
        $subCount = (int)$stmtSubjects->fetchColumn();

        // Combinations count
        $stmtComb = $conn->prepare("
            SELECT count(*) as total 
            FROM academic_templates 
            WHERE type = 'combination' AND level_code = ?
        ");
        $stmtComb->execute([$lCode]);
        $combCount = (int)$stmtComb->fetchColumn();

        $details = json_decode($t['details'] ?? '{}', true) ?: [];

        $tierList[] = [
            'id' => $t['id'],
            'name' => $t['name'],
            'code' => $t['code'],
            'level_code' => $t['level_code'],
            'description' => $t['description'],
            'curriculum_type' => $details['curriculum_type'] ?? ($lCode . ' Curriculum Standards'),
            'abbr' => $details['abbr'] ?? $lCode,
            'classes_count' => (int)($classInfo['total'] ?? 0),
            'classes_summary' => $classInfo['class_names'] ?? 'None configured',
            'subjects_count' => $subCount,
            'combinations_count' => $combCount,
            'status' => $t['status'] ?? 'active'
        ];
    }

    echo json_encode([
        'success' => true,
        'tiers' => $tierList
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
