<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

$levelType = $_GET['level_type'] ?? 'O-Level';

// Normalize levelType
if ($levelType === 'PRIM' || strtolower($levelType) === 'primary') $levelType = 'Primary';
else if ($levelType === 'O-LEVEL' || strtolower($levelType) === 'o-level') $levelType = 'O-Level';
else if ($levelType === 'A-LEVEL' || strtolower($levelType) === 'a-level') $levelType = 'A-Level';
else if ($levelType === 'NURSERY' || strtolower($levelType) === 'nursery') $levelType = 'Nursery';

try {
    // 1. Fetch Subject Grading Scales
    $stmtGrading = $conn->prepare("SELECT * FROM grading_scales WHERE level_type = ? ORDER BY min_mark DESC");
    $stmtGrading->execute([$levelType]);
    $gradingScales = $stmtGrading->fetchAll(PDO::FETCH_ASSOC);

    // If empty, auto-seed standard scales
    if (empty($gradingScales)) {
        if ($levelType === 'A-Level') {
            $defaultG = [
                ['A-Level', 'A', 80, 100, 1, 'Excellent'],
                ['A-Level', 'B', 70, 79, 2, 'Very Good'],
                ['A-Level', 'C', 60, 69, 3, 'Good'],
                ['A-Level', 'D', 50, 59, 4, 'Satisfactory'],
                ['A-Level', 'E', 40, 49, 5, 'Pass'],
                ['A-Level', 'S', 35, 39, 6, 'Subsidiary Pass'],
                ['A-Level', 'F', 0, 34, 7, 'Fail']
            ];
        } else if ($levelType === 'Primary' || $levelType === 'Nursery') {
            $defaultG = [
                [$levelType, 'A', 81, 100, 1, 'Bora Sana (Excellent)'],
                [$levelType, 'B', 61, 80, 2, 'Bora (Very Good)'],
                [$levelType, 'C', 41, 60, 3, 'Wastani (Average)'],
                [$levelType, 'D', 21, 40, 4, 'Dhaifu (Poor)'],
                [$levelType, 'E', 0, 20, 5, 'Mbaya Sana (Very Poor)']
            ];
        } else {
            $defaultG = [
                ['O-Level', 'A', 75, 100, 1, 'Distinction'],
                ['O-Level', 'B', 65, 74, 2, 'Merit'],
                ['O-Level', 'C', 45, 64, 3, 'Credit'],
                ['O-Level', 'D', 30, 44, 4, 'Pass'],
                ['O-Level', 'F', 0, 29, 5, 'Fail']
            ];
        }

        $insG = $conn->prepare("INSERT INTO grading_scales (level_type, grade, min_mark, max_mark, points, remark) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($defaultG as $dg) {
            $insG->execute($dg);
        }
        $stmtGrading->execute([$levelType]);
        $gradingScales = $stmtGrading->fetchAll(PDO::FETCH_ASSOC);
    }

    // 2. Fetch Division Scales
    $stmtDivision = $conn->prepare("SELECT * FROM division_scales WHERE level_type = ? ORDER BY min_points ASC");
    $stmtDivision->execute([$levelType]);
    $divisionScales = $stmtDivision->fetchAll(PDO::FETCH_ASSOC);

    // If empty, auto-seed standard division scales
    if (empty($divisionScales)) {
        if ($levelType === 'A-Level') {
            $defaultD = [
                ['A-Level', 'Division I', 3, 9, 'First Class Honors'],
                ['A-Level', 'Division II', 10, 12, 'Second Class'],
                ['A-Level', 'Division III', 13, 17, 'Third Class'],
                ['A-Level', 'Division IV', 18, 19, 'Fourth Class Pass'],
                ['A-Level', 'Division 0', 20, 21, 'Failed']
            ];
        } else if ($levelType === 'Primary' || $levelType === 'Nursery') {
            $defaultD = [
                [$levelType, 'Grade A', 1, 1, 'Distinction Tier'],
                [$levelType, 'Grade B', 2, 2, 'Merit Tier'],
                [$levelType, 'Grade C', 3, 3, 'Average Pass Tier'],
                [$levelType, 'Grade D', 4, 4, 'Below Average Tier'],
                [$levelType, 'Grade E', 5, 5, 'Repeat Tier']
            ];
        } else {
            $defaultD = [
                ['O-Level', 'Division I', 7, 17, 'Distinction Pass'],
                ['O-Level', 'Division II', 18, 21, 'Credit Pass'],
                ['O-Level', 'Division III', 22, 25, 'Pass'],
                ['O-Level', 'Division IV', 26, 33, 'Marginal Pass'],
                ['O-Level', 'Division 0', 34, 35, 'Fail']
            ];
        }

        $insD = $conn->prepare("INSERT INTO division_scales (level_type, division, min_points, max_points, description) VALUES (?, ?, ?, ?, ?)");
        foreach ($defaultD as $dd) {
            $insD->execute($dd);
        }
        $stmtDivision->execute([$levelType]);
        $divisionScales = $stmtDivision->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        "success" => true,
        "level_type" => $levelType,
        "grading_scales" => $gradingScales,
        "division_scales" => $divisionScales
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
