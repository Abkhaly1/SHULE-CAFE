<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Access denied. Only Super Admin can manage grading scales."]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$scaleCategory = trim($input['category'] ?? ''); // 'grading' or 'division'
$id = !empty($input['id']) ? intval($input['id']) : null;
$levelType = trim($input['level_type'] ?? 'O-Level');
$remark = trim($input['remark'] ?? '');

if (empty($scaleCategory) || empty($levelType)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Category and level type are required."]);
    exit();
}

try {
    if ($scaleCategory === 'grading') {
        $minMark = intval($input['min_mark'] ?? 0);
        $maxMark = intval($input['max_mark'] ?? 100);
        $grade = trim($input['grade'] ?? '');
        $points = intval($input['points'] ?? 1);

        if (empty($grade)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Grade letter is required."]);
            exit();
        }

        if ($id) {
            $stmt = $conn->prepare("UPDATE grading_scales SET level_type = ?, min_mark = ?, max_mark = ?, grade = ?, points = ?, remark = ? WHERE id = ?");
            $stmt->execute([$levelType, $minMark, $maxMark, $grade, $points, $remark, $id]);
            $msg = "Subject grade scale updated successfully.";
        } else {
            $stmt = $conn->prepare("INSERT INTO grading_scales (level_type, min_mark, max_mark, grade, points, remark) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$levelType, $minMark, $maxMark, $grade, $points, $remark]);
            $msg = "Subject grade scale created successfully.";
        }

    } else if ($scaleCategory === 'division') {
        $minPoints = intval($input['min_points'] ?? 0);
        $maxPoints = intval($input['max_points'] ?? 49);
        $divisionName = trim($input['division_name'] ?? '');

        if (empty($divisionName)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Division name is required."]);
            exit();
        }

        if ($id) {
            $stmt = $conn->prepare("UPDATE division_scales SET level_type = ?, min_points = ?, max_points = ?, division_name = ?, remark = ? WHERE id = ?");
            $stmt->execute([$levelType, $minPoints, $maxPoints, $divisionName, $remark, $id]);
            $msg = "Division scale updated successfully.";
        } else {
            $stmt = $conn->prepare("INSERT INTO division_scales (level_type, min_points, max_points, division_name, remark) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$levelType, $minPoints, $maxPoints, $divisionName, $remark]);
            $msg = "Division scale created successfully.";
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid scale category."]);
        exit();
    }

    echo json_encode(["success" => true, "message" => $msg]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
