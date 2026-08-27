<?php
// 🚀 High-Performance Output Compression for High Concurrency
if (!headers_sent() && !ob_get_level() && extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
    if (!ob_start("ob_gzhandler")) {
        ob_start();
    }
}

require_once __DIR__ . '/auth_guard.php';

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
if (!headers_sent()) {
    header("Access-Control-Allow-Origin: " . $origin);
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 🛡️ Global API Security Enforcement (Anti-Bot, Anti-Hacker, Authentication)
$currentScript = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['SCRIPT_NAME'] ?? '');
$publicEndpoints = [
    '/api/auth/login.php',
    '/api/auth/check_account.php',
    '/api/schools/register.php',
    '/api/schools/check_name.php',
    '/api/system/backup.php' // Handled by its own internal CLI/SuperAdmin auth
];

$isPublic = (php_sapi_name() === 'cli');
if (!$isPublic) {
    foreach ($publicEndpoints as $publicPath) {
        if (strpos($currentScript, ltrim($publicPath, '/')) !== false) {
            $isPublic = true;
            break;
        }
    }
}

if (!$isPublic) {
    // Enforce robust protection on all other endpoints
    requireAuth();
}

require_once __DIR__ . '/database.php';

try {
    $conn = Database::getInstance()->getConnection();
} catch(Exception $exception) {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Database connection error: " . $exception->getMessage()]);
    exit();
}
?>
