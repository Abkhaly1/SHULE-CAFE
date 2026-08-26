<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
    exit();
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $_GET['action'] ?? $input['action'] ?? 'get';

try {
    // 1. GET USER PROFILE
    if ($method === 'GET' || $action === 'get') {
        $stmt = $conn->prepare("
            SELECT u.id, u.user_code, u.full_name, u.role, u.gender, u.email, u.phone, u.department, u.created_at,
                   s.name AS school_name
            FROM users u
            LEFT JOIN schools s ON u.school_id = s.id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User account not found.']);
            exit();
        }

        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
        exit();
    }

    // 2. UPDATE PROFILE (ONLY Email, Phone, Gender)
    if ($method === 'POST' && $action === 'update_profile') {
        $email  = trim($input['email'] ?? '');
        $phone  = trim($input['phone'] ?? '');
        $gender = trim($input['gender'] ?? '');

        // Validation: Gender must be Male or Female
        if (!in_array($gender, ['Male', 'Female'])) {
            echo json_encode(['success' => false, 'message' => 'Please select a valid gender (Male / Female).']);
            exit();
        }

        // Phone validation
        if (empty($phone)) {
            echo json_encode(['success' => false, 'message' => 'Phone number is required.']);
            exit();
        }

        // Check email uniqueness if email provided
        if (!empty($email)) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Invalid email address format.']);
                exit();
            }

            $stmtCheckEmail = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $stmtCheckEmail->execute([$email, $userId]);
            if ($stmtCheckEmail->fetch()) {
                echo json_encode(['success' => false, 'message' => 'This email address is already registered to another account.']);
                exit();
            }
        } else {
            $email = null;
        }

        // Check phone uniqueness
        $stmtCheckPhone = $conn->prepare("SELECT id FROM users WHERE phone = ? AND id != ? LIMIT 1");
        $stmtCheckPhone->execute([$phone, $userId]);
        if ($stmtCheckPhone->fetch()) {
            echo json_encode(['success' => false, 'message' => 'This phone number is already registered to another account.']);
            exit();
        }

        // Execute Update (Full Name & Reg Code are NOT modifiable!)
        $stmtUpd = $conn->prepare("
            UPDATE users 
            SET email = ?, phone = ?, gender = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmtUpd->execute([$email, $phone, $gender, $userId]);

        // Update session cache
        if (isset($_SESSION['user'])) {
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['phone'] = $phone;
            $_SESSION['user']['gender'] = $gender;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Profile details updated successfully!'
        ]);
        exit();
    }

    // 3. CHANGE PASSWORD
    if ($method === 'POST' && $action === 'change_password') {
        $currentPassword = $input['current_password'] ?? '';
        $newPassword     = $input['new_password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in both current password and new password.']);
            exit();
        }

        if ($newPassword !== $confirmPassword) {
            echo json_encode(['success' => false, 'message' => 'New password and confirmation password do not match.']);
            exit();
        }

        // Fetch stored password hash
        $stmtPass = $conn->prepare("SELECT password_hash, user_code, full_name, phone, email FROM users WHERE id = ?");
        $stmtPass->execute([$userId]);
        $userObj = $stmtPass->fetch(PDO::FETCH_ASSOC);

        if (!$userObj || !password_verify($currentPassword, $userObj['password_hash'])) {
            echo json_encode(['success' => false, 'message' => 'Current password entered is incorrect.']);
            exit();
        }

        // Enforce strong password rules
        if (strlen($newPassword) < 8) {
            echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters long.']);
            exit();
        }

        if ($newPassword === $userObj['user_code']) {
            echo json_encode(['success' => false, 'message' => 'New password cannot be identical to your Registration Code.']);
            exit();
        }

        if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword) || !preg_match('/[^a-zA-Z0-9]/', $newPassword)) {
            echo json_encode(['success' => false, 'message' => 'New password must contain uppercase, lowercase, numbers, and special characters.']);
            exit();
        }

        // Hash & update password
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmtUpdPass = $conn->prepare("UPDATE users SET password_hash = ?, is_password_changed = 1, updated_at = NOW() WHERE id = ?");
        $stmtUpdPass->execute([$newHash, $userId]);

        echo json_encode([
            'success' => true,
            'message' => 'Your password has been changed successfully!'
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
