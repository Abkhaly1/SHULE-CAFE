<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

$sessionRole = $_SESSION['role'] ?? '';
$sessionSchoolId = $_SESSION['school_id'] ?? null;

$input = json_decode(file_get_contents('php://input'), true);

$full_name = mb_strtoupper(trim($input['full_name'] ?? ''), 'UTF-8');
$email = trim($input['email'] ?? '') ?: null;
$phone = trim($input['phone'] ?? '');
$role = trim($input['role'] ?? 'teacher');
$class_id = trim($input['class_id'] ?? '') ?: null;
$department = trim($input['department'] ?? '') ?: null;
$user_code = trim($input['user_code'] ?? '') ?: null;
$gender = trim($input['gender'] ?? '') ?: null;

// Role permissions check
if ($sessionRole === 'super_admin') {
    $allowedRoles = ['regional_officer', 'super_admin', 'tenant_admin', 'school_admin'];
    $school_id = trim($input['school_id'] ?? '') ?: null;
    if (!in_array($role, $allowedRoles)) {
        http_response_code(400);
        echo json_encode([
            "success" => false, 
            "message" => "Super Admins create Regional Officers and School Administrators directly."
        ]);
        exit();
    }
} else if (in_array($sessionRole, ['tenant_admin', 'school_admin', 'headmaster'])) {
    $allowedRoles = ['teacher', 'student', 'parent', 'guardian'];
    $school_id = $sessionSchoolId;
    if (!in_array($role, $allowedRoles)) {
        http_response_code(400);
        echo json_encode([
            "success" => false, 
            "message" => "Headmasters can only register Teachers, Students, or Parents for their school."
        ]);
        exit();
    }
} else {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

if (empty($full_name) || empty($phone)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Full Name and Phone Number are required."]);
    exit();
}

// Auto generate User Code (Student ID / Teacher ID) if empty
if (empty($user_code)) {
    $prefix = ($role === 'student') ? 'STD' : (($role === 'teacher') ? 'TCH' : 'USR');
    $cntStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE school_id = ? AND role = ?");
    $cntStmt->execute([$school_id, $role]);
    $nextSeq = (int)$cntStmt->fetchColumn() + 1;
    $user_code = sprintf("%s/%s/%03d", $prefix, date('Y'), $nextSeq);
}

function generateStandardTempPassword() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789#@!';
    $pwd = 'Shule#' . date('Y') . '@';
    for ($i = 0; $i < 4; $i++) {
        $pwd .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $pwd;
}

$tempPassword = generateStandardTempPassword();

function generateUuidV4() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

try {
    $chk = $conn->prepare("SELECT id FROM users WHERE phone = ? OR (email IS NOT NULL AND email = ?)");
    $chk->execute([$phone, $email]);
    if ($chk->fetch()) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "A user with this phone number or email already exists."]);
        exit();
    }

    $id = generateUuidV4();
    $hashed_password = password_hash($tempPassword, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("INSERT INTO users (id, school_id, user_code, class_id, department, role, email, phone, password_hash, temp_password, is_password_changed, full_name, gender, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'active')");
    $stmt->execute([$id, $school_id, $user_code, $class_id, $department, $role, $email, $phone, $hashed_password, $tempPassword, $full_name, $gender]);

    echo json_encode([
        "success" => true, 
        "message" => ucfirst($role) . " registered successfully.", 
        "id" => $id,
        "credentials" => [
            "user_code" => $user_code,
            "name" => $full_name,
            "email" => $email,
            "phone" => $phone,
            "department" => $department,
            "temp_password" => $tempPassword,
            "is_password_changed" => false
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error: " . $e->getMessage()]);
}
?>
