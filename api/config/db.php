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
    '/api/system/backup.php' // Handled by its own internal CLI/SuperAdmin auth
];

$isPublic = false;
foreach ($publicEndpoints as $publicPath) {
    if (strpos($currentScript, ltrim($publicPath, '/')) !== false) {
        $isPublic = true;
        break;
    }
}

if (!$isPublic) {
    // Enforce robust protection on all other endpoints
    requireAuth();
}

$host = 'localhost';
$db_name = 'shule_cafe';
$username = 'root';
$password = '';

try {
    $conn = new PDO(
        "mysql:host=" . $host . ";dbname=" . $db_name . ";charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
} catch(PDOException $exception) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection error."]);
    exit();
}
?>
