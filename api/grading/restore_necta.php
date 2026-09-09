<?php
/**
 * SHULE CAFE Enterprise
 * API: Restore Official NECTA Statutory Grading & Division Standards
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
$levelType = trim($input['level_type'] ?? 'O-Level');

// Normalize level type name
$validLevels = ['O-Level', 'A-Level', 'Primary', 'Nursery'];
if (!in_array($levelType, $validLevels)) {
    // Map from codes if supplied
    $codeMap = [
        'O-LEVEL' => 'O-Level',
        'A-LEVEL' => 'A-Level',
        'PRIM' => 'Primary',
        'NURSERY' => 'Nursery'
    ];
    $levelType = $codeMap[$levelType] ?? 'O-Level';
}

try {
    $conn->beginTransaction();

    // 1. Delete existing rules for this specific level
    $delG = $conn->prepare("DELETE FROM grading_scales WHERE level_type = ?");
    $delG->execute([$levelType]);

    $delD = $conn->prepare("DELETE FROM division_scales WHERE level_type = ?");
    $delD->execute([$levelType]);

    // 2. Insert Official Statutory Benchmarks
    $insG = $conn->prepare("
        INSERT INTO grading_scales (level_type, min_mark, max_mark, grade, points, remark, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    $insD = $conn->prepare("
        INSERT INTO division_scales (level_type, min_points, max_points, division_name, remark, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");

    if ($levelType === 'O-Level') {
        // NECTA CSEE Standard Scales
        $grades = [
            ['min' => 75, 'max' => 100, 'grade' => 'A', 'points' => 1, 'remark' => 'Distinction'],
            ['min' => 65, 'max' => 74,  'grade' => 'B', 'points' => 2, 'remark' => 'Very Good'],
            ['min' => 45, 'max' => 64,  'grade' => 'C', 'points' => 3, 'remark' => 'Good'],
            ['min' => 30, 'max' => 44,  'grade' => 'D', 'points' => 4, 'remark' => 'Satisfactory'],
            ['min' => 0,  'max' => 29,  'grade' => 'F', 'points' => 5, 'remark' => 'Fail'],
        ];
        $divisions = [
            ['min' => 7,  'max' => 17, 'name' => 'Division I',   'remark' => 'Distinction (7 - 17 Pts)'],
            ['min' => 18, 'max' => 21, 'name' => 'Division II',  'remark' => 'Merit (18 - 21 Pts)'],
            ['min' => 22, 'max' => 25, 'name' => 'Division III', 'remark' => 'Credit (22 - 25 Pts)'],
            ['min' => 26, 'max' => 33, 'name' => 'Division IV',  'remark' => 'Pass (26 - 33 Pts)'],
            ['min' => 34, 'max' => 50, 'name' => 'Division 0',   'remark' => 'Fail (34 - 50 Pts)'],
        ];
    } elseif ($levelType === 'A-Level') {
        // NECTA ACSEE Standard Scales
        $grades = [
            ['min' => 80, 'max' => 100, 'grade' => 'A', 'points' => 1, 'remark' => 'Distinction'],
            ['min' => 70, 'max' => 79,  'grade' => 'B', 'points' => 2, 'remark' => 'Very Good'],
            ['min' => 60, 'max' => 69,  'grade' => 'C', 'points' => 3, 'remark' => 'Good'],
            ['min' => 50, 'max' => 59,  'grade' => 'D', 'points' => 4, 'remark' => 'Average'],
            ['min' => 40, 'max' => 49,  'grade' => 'E', 'points' => 5, 'remark' => 'Pass'],
            ['min' => 35, 'max' => 39,  'grade' => 'S', 'points' => 6, 'remark' => 'Subsidiary Pass'],
            ['min' => 0,  'max' => 34,  'grade' => 'F', 'points' => 7, 'remark' => 'Fail'],
        ];
        $divisions = [
            ['min' => 3,  'max' => 9,  'name' => 'Division I',   'remark' => 'First Class (3 - 9 Pts)'],
            ['min' => 10, 'max' => 12, 'name' => 'Division II',  'remark' => 'Second Class (10 - 12 Pts)'],
            ['min' => 13, 'max' => 17, 'name' => 'Division III', 'remark' => 'Third Class (13 - 17 Pts)'],
            ['min' => 18, 'max' => 19, 'name' => 'Division IV',  'remark' => 'Subsidiary Pass (18 - 19 Pts)'],
            ['min' => 20, 'max' => 21, 'name' => 'Division 0',   'remark' => 'Fail (20 - 21 Pts)'],
        ];
    } elseif ($levelType === 'Primary') {
        // National Primary PSLE Standard
        $grades = [
            ['min' => 81, 'max' => 100, 'grade' => 'A', 'points' => 1, 'remark' => 'Distinction'],
            ['min' => 61, 'max' => 80,  'grade' => 'B', 'points' => 2, 'remark' => 'Very Good'],
            ['min' => 41, 'max' => 60,  'grade' => 'C', 'points' => 3, 'remark' => 'Good'],
            ['min' => 21, 'max' => 40,  'grade' => 'D', 'points' => 4, 'remark' => 'Poor'],
            ['min' => 0,  'max' => 20,  'grade' => 'E', 'points' => 5, 'remark' => 'Fail'],
        ];
        $divisions = [
            ['min' => 41, 'max' => 50, 'name' => 'Band A', 'remark' => 'High Distinction Band'],
            ['min' => 31, 'max' => 40, 'name' => 'Band B', 'remark' => 'Merit Band'],
            ['min' => 21, 'max' => 30, 'name' => 'Band C', 'remark' => 'Satisfactory Band'],
            ['min' => 11, 'max' => 20, 'name' => 'Band D', 'remark' => 'Basic Pass Band'],
            ['min' => 0,  'max' => 10, 'name' => 'Band E', 'remark' => 'Unclassified Band'],
        ];
    } else {
        // Early Childhood / Nursery
        $grades = [
            ['min' => 80, 'max' => 100, 'grade' => 'A', 'points' => 1, 'remark' => 'Exceeding Expectations'],
            ['min' => 65, 'max' => 79,  'grade' => 'B', 'points' => 2, 'remark' => 'Meeting Expectations'],
            ['min' => 50, 'max' => 64,  'grade' => 'C', 'points' => 3, 'remark' => 'Approaching Expectations'],
            ['min' => 0,  'max' => 49,  'grade' => 'D', 'points' => 4, 'remark' => 'Needs Further Support'],
        ];
        $divisions = [
            ['min' => 1, 'max' => 2, 'name' => 'Stage 1', 'remark' => 'Advanced Developmental Mastery'],
            ['min' => 3, 'max' => 4, 'name' => 'Stage 2', 'remark' => 'Foundation Literacy & Play Mastery'],
        ];
    }

    foreach ($grades as $g) {
        $insG->execute([$levelType, $g['min'], $g['max'], $g['grade'], $g['points'], $g['remark']]);
    }

    foreach ($divisions as $d) {
        $insD->execute([$levelType, $d['min'], $d['max'], $d['name'], $d['remark']]);
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Statutory NECTA grading rules and division scales successfully restored for {$levelType}."
    ]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error restoring NECTA rules: " . $e->getMessage()]);
}
