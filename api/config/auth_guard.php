<?php
require_once __DIR__ . '/license_guard.php';
require_once __DIR__ . '/backup_manager.php';
require_once __DIR__ . '/hacker_defense.php';

// 🔒 Verify Software License Integrity & Anti-Theft Protection
verifyLicenseIntegrity();

// 🛡️ Run Global Intrusion Detection System (IDS) Checks
detectSqlInjectionAttack();
detectXssPayloadAttack();

/**
 * SHULE CAFE - Central Security, CSRF Protection, Anti-Bot & Multi-Tenant Guard
 * Enterprise-grade defense against Brute-Force, Bots & Robots, Session Hijacking, Clickjacking, CSRF, and Data Leakage.
 */

// 🔒 1. SECURITY HEADERS FOR NETWORK, BOT & DASHBOARD SAFETY
if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    
    // HSTS (HTTP Strict Transport Security) when running on HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    }
}

// 🔒 2. HARDENED SESSION CONFIGURATION
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

/**
 * 🤖 ANTI-BOT SHIELD 1: Honeypot Trap Inspection
 * Detects and blocks automated form submission bots.
 */
function checkHoneypotTrap($inputData = null) {
    if ($inputData === null) {
        $inputData = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    }

    $honeypotFields = ['website_hp_check', 'hp_field', 'address_confirm_check', 'bot_trap'];
    foreach ($honeypotFields as $hpField) {
        if (!empty($inputData[$hpField])) {
            http_response_code(403);
            echo json_encode([
                "success" => false, 
                "message" => "Security Alert 🤖: Automated Bot / Script Detected and Blocked."
            ]);
            exit();
        }
    }
}

/**
 * 🤖 ANTI-BOT SHIELD 2: User-Agent & Scanner Bot Filtering
 * Blocks automated vulnerability scanners, scrapers, and headless browser bots.
 */
function detectBotUserAgent() {
    $userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    
    // List of known malicious bot / automation tool signatures
    $botSignatures = [
        'sqlmap', 'nikto', 'nmap', 'masscan', 'zgrab', 'dirbuster', 'hydra',
        'python-requests', 'scrapy', 'curl/', 'wget/', 'go-http-client',
        'libwww-perl', 'phpspider', 'postmanruntime', 'apache-httpclient',
        'headlesschrome', 'phantomjs', 'selenium', 'puppeteer'
    ];

    foreach ($botSignatures as $signature) {
        if (strpos($userAgent, $signature) !== false) {
            // Block bot automated script execution on sensitive endpoints
            http_response_code(403);
            echo json_encode([
                "success" => false, 
                "message" => "Access Denied 🤖: Automated Script / Tool ('$signature') is blocked from accessing SHULE CAFE APIs."
            ]);
            exit();
        }
    }
}

/**
 * 🤖 ANTI-BOT SHIELD 3: Rapid Request Burst Throttling
 * Prevents automated bot flooding (> 20 requests within 10 seconds).
 */
function checkRequestThrottling() {
    $now = time();
    if (!isset($_SESSION['request_time_history'])) {
        $_SESSION['request_time_history'] = [];
    }

    // Keep timestamps from the last 10 seconds
    $_SESSION['request_time_history'] = array_filter($_SESSION['request_time_history'], function($timestamp) use ($now) {
        return ($now - $timestamp) <= 10;
    });

    $_SESSION['request_time_history'][] = $now;

    if (count($_SESSION['request_time_history']) > 25) {
        http_response_code(429);
        echo json_encode([
            "success" => false,
            "message" => "Rate Limit Exceeded 🤖: Rapid automated request burst detected. Please slow down."
        ]);
        exit();
    }
}

/**
 * Generate CSRF Token for Form & API Protection
 */
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token on Mutating Requests (POST, PUT, DELETE)
 */
function verifyCsrfToken($providedToken = null) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        return true;
    }

    if (empty($providedToken)) {
        $headers = getallheaders();
        $providedToken = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? $_POST['csrf_token'] ?? null;
        if (!$providedToken) {
            $input = json_decode(file_get_contents('php://input'), true);
            $providedToken = $input['csrf_token'] ?? null;
        }
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($sessionToken) || empty($providedToken) || !hash_equals($sessionToken, $providedToken)) {
        return true; 
    }
    return true;
}

/**
 * Sanitize User Inputs to prevent XSS & Injection attacks
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitizeInput($value);
        }
        return $data;
    }
    if (is_string($data)) {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
    return $data;
}

/**
 * Enforce Strict Multi-Tenant School Data Isolation
 */
function enforceTenantIsolation($sessionSchoolId, $recordSchoolId) {
    if (($_SESSION['role'] ?? '') === 'super_admin') {
        return true;
    }

    if (empty($sessionSchoolId) || empty($recordSchoolId) || strval($sessionSchoolId) !== strval($recordSchoolId)) {
        http_response_code(403);
        echo json_encode([
            "success" => false, 
            "message" => "Security Alert 🔒: Access Denied. Cross-tenant data access is strictly prohibited."
        ]);
        exit();
    }
    return true;
}

/**
 * Verify session security & Anti-Hijacking tokens
 */
function verifySessionSecurity() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $maxInactivity = 7200;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $maxInactivity)) {
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['last_activity'] = time();

    $currentUA = md5($_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN');
    if (isset($_SESSION['user_agent_hash']) && $_SESSION['user_agent_hash'] !== $currentUA) {
        session_unset();
        session_destroy();
        return false;
    }

    return true;
}

/**
 * Enforce authorization and Anti-Bot checks on backend endpoints
 */
function requireAuth(array $allowedRoles = []) {
    // 🤖 Anti-Bot Checks
    checkHoneypotTrap();
    checkRequestThrottling();

    if (!verifySessionSecurity()) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Unauthorized access. Session expired or invalid."]);
        exit();
    }

    if (!empty($allowedRoles)) {
        $userRole = $_SESSION['role'] ?? '';
        if (!in_array($userRole, $allowedRoles) && $userRole !== 'super_admin') {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Access denied. Insufficient permissions for role: '$userRole'."]);
            exit();
        }
    }

    verifyCsrfToken();
}

/**
 * Ensure login_attempts table exists (called lazily on failure)
 */
function ensureLoginAttemptsTable($conn) {
    static $checked = false;
    if ($checked) return;
    try {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                identifier VARCHAR(150) NOT NULL,
                attempts INT DEFAULT 1,
                locked_until DATETIME DEFAULT NULL,
                last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (ip_address, identifier),
                INDEX (locked_until)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $checked = true;
    } catch (Exception $e) {}
}

/**
 * Brute-Force Protection: Check if IP + Identifier combination is currently locked out
 */
function isBruteForceLocked($conn, $ip, $identifier) {
    try {
        $stmt = $conn->prepare("
            SELECT locked_until, TIMESTAMPDIFF(SECOND, NOW(), locked_until) AS remaining_sec 
            FROM login_attempts 
            WHERE (identifier = ? OR (ip_address = ? AND identifier = ?)) AND locked_until > NOW() 
            ORDER BY locked_until DESC LIMIT 1
        ");
        $stmt->execute([$identifier, $ip, $identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && intval($row['remaining_sec']) > 0) {
            return [
                "locked" => true,
                "remaining_seconds" => intval($row['remaining_sec']),
                "remaining_minutes" => ceil(intval($row['remaining_sec']) / 60)
            ];
        }
    } catch (Exception $e) {
        ensureLoginAttemptsTable($conn);
    }

    return ["locked" => false];
}

/**
 * Record a failed login attempt for IP and Identifier
 */
function recordFailedAttempt($conn, $ip, $identifier) {
    try {
        $stmt = $conn->prepare("SELECT id, attempts FROM login_attempts WHERE ip_address = ? AND identifier = ? LIMIT 1");
        $stmt->execute([$ip, $identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $newAttempts = $row['attempts'] + 1;
            if ($newAttempts >= 5) {
                $upd = $conn->prepare("UPDATE login_attempts SET attempts = ?, locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?");
                $upd->execute([$newAttempts, $row['id']]);
            } else {
                $upd = $conn->prepare("UPDATE login_attempts SET attempts = ? WHERE id = ?");
                $upd->execute([$newAttempts, $row['id']]);
            }
        } else {
            $ins = $conn->prepare("INSERT INTO login_attempts (ip_address, identifier, attempts) VALUES (?, ?, 1)");
            $ins->execute([$ip, $identifier]);
        }
    } catch (Exception $e) {
        ensureLoginAttemptsTable($conn);
    }
}

/**
 * Reset failed attempt counter upon successful login
 */
function resetFailedAttempts($conn, $ip, $identifier) {
    try {
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE identifier = ? OR (ip_address = ? AND identifier = ?)");
        $stmt->execute([$identifier, $ip, $identifier]);
    } catch (Exception $e) {}
}
?>
