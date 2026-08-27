<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['tenant_admin', 'school_admin', 'headmaster', 'super_admin']) || empty($_SESSION['school_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->full_name) || empty($data->phone) || empty($data->password) || empty($data->student_id)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "All fields including Student are required."]);
    exit();
}

try {
    $conn->beginTransaction();

    $phone = trim($data->phone);
    
    // Check if phone exists
    $check = $conn->prepare("SELECT id FROM users WHERE phone = ?");
    $check->execute([$phone]);
    $existing_user = $check->fetch();

    $parent_id = null;

    if ($existing_user) {
        $parent_id = $existing_user['id'];
        // Ideally we would check if role is parent, but for now we reuse the account if it exists
    } else {
        $parent_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        $hash = password_hash($data->password, PASSWORD_BCRYPT);
        
        $stmt = $conn->prepare("INSERT INTO users (id, school_id, full_name, phone, password_hash, role) VALUES (?, ?, ?, ?, ?, 'parent')");
        $stmt->execute([
            $parent_id,
            $_SESSION['school_id'],
            trim($data->full_name),
            $phone,
            $hash
        ]);
    }

    // Link parent to student
    // Ensure student belongs to this school
    $student_check = $conn->prepare("SELECT id FROM users WHERE id = ? AND school_id = ? AND role = 'student'");
    $student_check->execute([$data->student_id, $_SESSION['school_id']]);
    if (!$student_check->fetch()) {
        $conn->rollBack();
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid student selected."]);
        exit();
    }

    // Check if link already exists
    $link_check = $conn->prepare("SELECT parent_id FROM parent_student WHERE parent_id = ? AND student_id = ?");
    $link_check->execute([$parent_id, $data->student_id]);
    if (!$link_check->fetch()) {
        $link_stmt = $conn->prepare("INSERT INTO parent_student (parent_id, student_id) VALUES (?, ?)");
        $link_stmt->execute([$parent_id, $data->student_id]);
    }

    $conn->commit();

    echo json_encode(["success" => true, "message" => "Parent registered and linked successfully."]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error: " . $e->getMessage()]);
}
?>
