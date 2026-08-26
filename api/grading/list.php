<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

$levelType = $_GET['level_type'] ?? 'O-Level';

try {
    // 1. Fetch Subject Grading Scales
    $stmtGrading = $conn->prepare("SELECT * FROM grading_scales WHERE level_type = ? ORDER BY min_mark DESC");
    $stmtGrading->execute([$levelType]);
    $gradingScales = $stmtGrading->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Division Scales
    $stmtDivision = $conn->prepare("SELECT * FROM division_scales WHERE level_type = ? ORDER BY min_points ASC");
    $stmtDivision->execute([$levelType]);
    $divisionScales = $stmtDivision->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "level_type" => $levelType,
        "grading_scales" => $gradingScales,
        "division_scales" => $divisionScales
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
