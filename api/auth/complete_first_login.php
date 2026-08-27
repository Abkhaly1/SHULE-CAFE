<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized access. Please log in first."]);
    exit();
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents("php://input"), true) ?? [];

$newPassword = trim($input['new_password'] ?? '');
$confirmPassword = trim($input['confirm_password'] ?? '');
$phone = trim($input['phone'] ?? '');
$email = trim($input['email'] ?? '');
$gender = trim($input['gender'] ?? '');

try {
    // Fetch Current User Info
    $stmt = $conn->prepare("SELECT id, user_code, full_name, email, phone, role, password_hash FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "User account not found."]);
        exit();
    }

    $isStudent = ($user['role'] === 'student');

    // Validation: Passwords, Phone, and Gender are always required. Email is required for non-students.
    if (empty($newPassword) || empty($confirmPassword) || empty($phone) || empty($gender) || (!$isStudent && empty($email))) {
        http_response_code(400);
        $msg = $isStudent 
            ? "New Password, Confirmation, Phone, and Gender are required." 
            : "All fields (New Password, Phone, Email, Gender) are required.";
        echo json_encode(["success" => false, "message" => $msg]);
        exit();
    }

    if ($newPassword !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "New password and confirmation password do not match."]);
        exit();
    }

    if (!in_array($gender, ['Male', 'Female'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Please select a valid Gender (Male or Female)."]);
        exit();
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Please enter a valid Email address."]);
        exit();
    }

    $userCode = trim($user['user_code'] ?? '');
    $fullName = trim($user['full_name'] ?? '');

    // ── STAGE 1: PASSWORD VALIDATION RULES ──────────────────────────────────

    // Rule 1: Cannot be initial password (Reg Code)
    if (!empty($userCode) && strtolower($newPassword) === strtolower($userCode)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "New password cannot be your initial Registration ID / Staff ID."]);
        exit();
    }

    // Rule 2: Cannot contain full name or any part of full name (>2 chars)
    $nameParts = array_filter(explode(' ', $fullName), function($part) { return strlen(trim($part)) >= 3; });
    foreach ($nameParts as $part) {
        if (stripos($newPassword, trim($part)) !== false) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "New password cannot contain your name or name parts ('" . htmlspecialchars($part) . "')."]);
            exit();
        }
    }

    // Rule 3: Cannot contain phone number
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    if (!empty($cleanPhone) && strlen($cleanPhone) >= 6 && strpos($newPassword, $cleanPhone) !== false) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "New password cannot contain your phone number."]);
        exit();
    }

    // Rule 4: Cannot contain email address or email prefix
    if (!empty($email)) {
        $emailPrefix = strtolower(explode('@', $email)[0]);
        if (!empty($emailPrefix) && strlen($emailPrefix) >= 3 && stripos($newPassword, $emailPrefix) !== false) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "New password cannot contain your email username."]);
            exit();
        }
    }

    // Rule 5: Strong Password Complexity Requirement (Min 8 chars, 1 upper, 1 lower, 1 digit, 1 special char)
    if (strlen($newPassword) < 8) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Password must be at least 8 characters long."]);
        exit();
    }

    if (!preg_match('/[A-Z]/', $newPassword) || 
        !preg_match('/[a-z]/', $newPassword) || 
        !preg_match('/[0-9]/', $newPassword) || 
        !preg_match('/[\W_]/', $newPassword)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Password must be strong: include uppercase, lowercase, number, and special character (e.g. @, #, $, !)."]);
        exit();
    }

    // ── STAGE 2: UNIQUE EMAIL & PHONE CHECK ────────────────────────────────
    $cleanEmail = !empty($email) ? $email : null;
    if ($cleanEmail) {
        $stmtCheckEmail = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmtCheckEmail->execute([$cleanEmail, $userId]);
        if ($stmtCheckEmail->fetch()) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "This Email address is already registered to another user account."]);
            exit();
        }
    }

    $stmtCheckPhone = $conn->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
    $stmtCheckPhone->execute([$phone, $userId]);
    if ($stmtCheckPhone->fetch()) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "This Phone number is already registered to another user account."]);
        exit();
    }

    // ── STAGE 3: UPDATE USER PROFILE & PASSWORD ──────────────────────────────
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);

    $stmtUpdate = $conn->prepare("
        UPDATE users
        SET password_hash = ?,
            phone = ?,
            email = ?,
            gender = ?,
            is_password_changed = 1,
            first_login_completed = 1,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmtUpdate->execute([$newHash, $phone, $cleanEmail, $gender, $userId]);

    // Update Session
    $_SESSION['email'] = $cleanEmail;
    $_SESSION['phone'] = $phone;

    $userRole = $user['role'];
    if ($userRole === 'school_admin') {
        $userRole = 'tenant_admin';
    }
    $_SESSION['role'] = $userRole;

    // Determine Dashboard Redirect URL
    $dashboardMap = [
        'super_admin'      => '../super-admin/dashboard.html',
        'regional_officer' => '../regional/dashboard.html',
        'tenant_admin'     => '../headmaster/dashboard.html',
        'school_admin'     => '../headmaster/dashboard.html',
        'headmaster'       => '../headmaster/dashboard.html',
        'teacher'          => '../teacher/dashboard.html',
        'student'          => '../student/dashboard.html',
        'parent'           => '../parent/dashboard.html',
        'guardian'         => '../parent/dashboard.html'
    ];

    $redirectUrl = $dashboardMap[$user['role']] ?? '../student/dashboard.html';

    echo json_encode([
        "success" => true,
        "message" => "First-time profile and password setup completed successfully!",
        "redirect_url" => $redirectUrl,
        "role" => $userRole
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
