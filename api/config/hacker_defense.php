<?php
/**
 * SHULE CAFE - Intrusion Detection & Anti-Hacker Shield (IDS)
 * Defense against SQL Injection, Cross-Site Scripting (XSS), Malicious Shell Uploads, and Exploits.
 */

// Disable raw PHP error leakage to prevent information gathering by attackers
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

if (!file_exists(__DIR__ . '/../../logs')) {
    mkdir(__DIR__ . '/../../logs', 0755, true);
}

/**
 * 🛡️ HACKER DEFENSE 1: SQL Injection Pattern Inspection
 */
function detectSqlInjectionAttack($conn = null) {
    $rawInput = file_get_contents('php://input');
    $queryStr = $_SERVER['QUERY_STRING'] ?? '';
    $inputString = strtolower($rawInput . ' ' . $queryStr . ' ' . json_encode($_POST) . ' ' . json_encode($_GET));

    // Malicious SQL patterns
    $sqlPatterns = [
        'union select', 'union all select', 'information_schema',
        'drop table', 'drop database', 'truncate table',
        'or 1=1', 'or\'1\'=\'1\'', 'or "1"="1"',
        'into outfile', 'into dumpfile', 'load_file(',
        'benchmark(', 'sleep(', 'waitfor delay'
    ];

    foreach ($sqlPatterns as $pattern) {
        if (strpos($inputString, $pattern) !== false) {
            if ($conn) {
                require_once __DIR__ . '/backup_manager.php';
                logSecurityEvent($conn, 'SQL_INJECTION_BLOCKED', "Blocked SQL Injection pattern ('$pattern') in request payload.");
            }

            http_response_code(403);
            echo json_encode([
                "success" => false,
                "message" => "Security Shield 🛡️: Malicious SQL Injection pattern detected and blocked."
            ]);
            exit();
        }
    }
}

/**
 * 🛡️ HACKER DEFENSE 2: XSS & Script Payload Inspection
 */
function detectXssPayloadAttack($conn = null) {
    $rawInput = file_get_contents('php://input');
    $queryStr = $_SERVER['QUERY_STRING'] ?? '';
    $inputString = strtolower($rawInput . ' ' . $queryStr . ' ' . json_encode($_POST) . ' ' . json_encode($_GET));

    // Malicious XSS patterns
    $xssPatterns = [
        '<script', '</script>', 'javascript:', 'vbscript:',
        'onload=', 'onerror=', 'onclick=', 'onmouseover=',
        'document.cookie', 'document.location', 'window.location',
        'eval(', 'alert(', 'prompt('
    ];

    foreach ($xssPatterns as $pattern) {
        if (strpos($inputString, $pattern) !== false) {
            if ($conn) {
                require_once __DIR__ . '/backup_manager.php';
                logSecurityEvent($conn, 'XSS_ATTACK_BLOCKED', "Blocked malicious XSS script payload ('$pattern').");
            }

            http_response_code(403);
            echo json_encode([
                "success" => false,
                "message" => "Security Shield 🛡️: Malicious Script / XSS payload detected and blocked."
            ]);
            exit();
        }
    }
}

/**
 * 🛡️ HACKER DEFENSE 3: File Upload Security & Shell Execution Shield
 */
function verifyUploadedFileSecurity($fileArray, $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'csv', 'xlsx']) {
    if (empty($fileArray) || !isset($fileArray['name']) || empty($fileArray['name'])) {
        return ["valid" => true];
    }

    $fileName = strtolower($fileArray['name']);
    $fileTmp = $fileArray['tmp_name'];
    $fileSize = $fileArray['size'];
    $ext = pathinfo($fileName, PATHINFO_EXTENSION);

    // 1. Extension Restriction
    if (!in_array($ext, $allowedExtensions)) {
        return [
            "valid" => false,
            "message" => "File Upload Security 🔒: Extension '.$ext' is forbidden. Allowed: " . implode(', ', $allowedExtensions)
        ];
    }

    // 2. Double Extension / PHP Script Upload Protection (.php.png, .phtml, .sh, .exe)
    $forbiddenSubstrings = ['.php', '.phtml', '.php3', '.php4', '.php5', '.phps', '.phar', '.exe', '.sh', '.py', '.js', '.htaccess'];
    foreach ($forbiddenSubstrings as $badExt) {
        if (strpos($fileName, $badExt) !== false) {
            return [
                "valid" => false,
                "message" => "Security Alert 🔒: Executable file upload attempt blocked."
            ];
        }
    }

    // 3. File Size Cap (Max 10MB)
    if ($fileSize > 10 * 1024 * 1024) {
        return [
            "valid" => false,
            "message" => "File Upload Security: File size exceeds maximum 10MB limit."
        ];
    }

    // 4. Generate Safe Random Cryptographic Filename
    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;

    return [
        "valid" => true,
        "safe_filename" => $safeName,
        "extension" => $ext
    ];
}

/**
 * 🛡️ HACKER DEFENSE 4: Secure Error Log Handler (Hides Traceback from Attackers)
 */
function logSystemError($exception, $customMsg = "System processing error") {
    $refId = 'SEC-' . strtoupper(substr(md5(uniqid()), 0, 8));
    $logMsg = "[" . date('Y-m-d H:i:s') . "] [Ref: $refId] " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine() . "\n";
    
    file_put_contents(__DIR__ . '/../../logs/security_errors.log', $logMsg, FILE_APPEND);

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "$customMsg. Reference Code: $refId",
        "error_reference" => $refId
    ]);
    exit();
}
?>
