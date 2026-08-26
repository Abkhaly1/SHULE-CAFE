<?php
require_once __DIR__ . '/../config/auth_guard.php';
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

// 🤖 Anti-Bot Protection 1: Scanner & Script Bot Detection
detectBotUserAgent();

// 🤖 Anti-Bot Protection 2: Honeypot Trap Inspection
checkHoneypotTrap((array)$data);

$identifier = !empty($data->email) ? trim($data->email) : (!empty($data->phone) ? trim($data->phone) : (!empty($data->username) ? trim($data->username) : (!empty($data->identifier) ? trim($data->identifier) : '')));
$password = !empty($data->password) ? $data->password : '';

if (empty($identifier) || empty($password)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Email/Phone and Password are required."]);
    exit();
}

$clientIp = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// 🔒 1. BRUTE-FORCE RATE LIMITING CHECK
$lockStatus = isBruteForceLocked($conn, $clientIp, $identifier);
if ($lockStatus['locked']) {
    http_response_code(429);
    echo json_encode([
        "success" => false,
        "message" => "Security Alert 🔒: Too many failed login attempts. Account temporarily locked for security. Please try again in " . $lockStatus['remaining_minutes'] . " minute(s)."
    ]);
    exit();
}

// Phone normalization (e.g. 0700000000 -> +255700000000)
$phoneAlt = $identifier;
if (strpos($identifier, '0') === 0) {
    $phoneAlt = '+255' . substr($identifier, 1);
} else if (strpos($identifier, '+255') === 0) {
    $phoneAlt = '0' . substr($identifier, 4);
}

try {
    $stmt = $conn->prepare("
        SELECT id, user_code, full_name, email, phone, gender, password_hash, is_password_changed, first_login_completed, role, status, school_id 
        FROM users 
        WHERE email = ? 
           OR phone = ? 
           OR phone = ? 
           OR user_code = ? 
           OR LOWER(full_name) = LOWER(?)
        LIMIT 1
    ");
    $stmt->execute([$identifier, $identifier, $phoneAlt, $identifier, $identifier]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // 2. Individual User Account Status Check
        if ($user['status'] !== 'active') {
            recordFailedAttempt($conn, $clientIp, $identifier);
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Your account is " . $user['status'] . ". Please contact platform administrator."]);
            exit();
        }

        // 3. School Tenant Suspension Check
        if (!empty($user['school_id']) && $user['role'] !== 'super_admin') {
            $schStmt = $conn->prepare("SELECT name, status FROM schools WHERE id = ? LIMIT 1");
            $schStmt->execute([$user['school_id']]);
            $school = $schStmt->fetch(PDO::FETCH_ASSOC);

            if ($school && $school['status'] === 'suspended') {
                recordFailedAttempt($conn, $clientIp, $identifier);
                http_response_code(403);
                echo json_encode([
                    "success" => false, 
                    "message" => "Access Denied 🔒: School tenant ('" . $school['name'] . "') is currently suspended by Platform Administrator. Logins disabled."
                ]);
                exit();
            }
        }

        // 4. Password Verification
        if (password_verify($password, $user['password_hash']) || password_verify(trim($password), $user['password_hash'])) {
            
            // Clear any prior failed attempts for this user/IP
            resetFailedAttempts($conn, $clientIp, $identifier);

            // Regenerate session ID to prevent Session Fixation attacks
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['school_id'] = $user['school_id'] ?? null;
            $_SESSION['last_activity'] = time();
            $_SESSION['user_agent_hash'] = md5($_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN');

            $requireFirstTimeSetup = ((int)$user['is_password_changed'] === 0 || (int)($user['first_login_completed'] ?? 0) === 0);

            echo json_encode([
                "success" => true,
                "message" => "Login successful.",
                "role" => $user['role'],
                "require_password_change" => $requireFirstTimeSetup,
                "require_first_time_setup" => $requireFirstTimeSetup,
                "user" => [
                    "id" => $user['id'],
                    "user_code" => $user['user_code'] ?? '',
                    "full_name" => $user['full_name'],
                    "email" => $user['email'] ?? '',
                    "phone" => $user['phone'] ?? '',
                    "gender" => $user['gender'] ?? '',
                    "role" => $user['role'],
                    "school_id" => $user['school_id'] ?? null,
                    "is_password_changed" => (bool)$user['is_password_changed'],
                    "first_login_completed" => (bool)($user['first_login_completed'] ?? 0)
                ]
            ]);
        } else {
            recordFailedAttempt($conn, $clientIp, $identifier);
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "Invalid phone number, email, registration code, or password."]);
        }
    } else {
        recordFailedAttempt($conn, $clientIp, $identifier);
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Invalid phone number, email, registration code, or password."]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error: " . $e->getMessage()]);
}
?>
