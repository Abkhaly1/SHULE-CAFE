<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

$reason = $_GET['reason'] ?? $_POST['reason'] ?? 'manual_backup';

// Auth check: allow periodic_daily_cron or super_admin
if ($reason !== 'periodic_daily_cron' && php_sapi_name() !== 'cli') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Unauthorized access."]);
        exit();
    }
}

function get_backup_dir() {
    $dir = dirname(__DIR__, 2) . '/backups/';
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * Execute Full Database Backup
 */
function create_database_backup($reason = 'manual_backup') {
    global $conn;

    $backupDir = get_backup_dir();
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "shule_cafe_backup_{$timestamp}_{$reason}.sql";
    $filepath = $backupDir . $filename;

    try {
        $tables = [];
        $stmt = $conn->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $sqlDump = "-- SHULE CAFE Database Backup\n";
        $sqlDump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sqlDump .= "-- Reason: " . htmlspecialchars($reason) . "\n\n";
        $sqlDump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            // Table Structure
            $createStmt = $conn->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            $sqlDump .= "-- Table structure for `$table` --\n";
            $sqlDump .= "DROP TABLE IF EXISTS `$table`;\n";
            $sqlDump .= $createStmt[1] . ";\n\n";

            // Table Data
            $rows = $conn->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) > 0) {
                $sqlDump .= "-- Dumping data for `$table` --\n";
                foreach ($rows as $row) {
                    $keys = array_map(function($k) { return "`$k`"; }, array_keys($row));
                    $values = array_map(function($v) use ($conn) {
                        if ($v === null) return "NULL";
                        return $conn->quote($v);
                    }, array_values($row));

                    $sqlDump .= "INSERT INTO `$table` (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ");\n";
                }
                $sqlDump .= "\n";
            }
        }

        $sqlDump .= "SET FOREIGN_KEY_CHECKS=1;\n";

        file_put_contents($filepath, $sqlDump);
        clearstatcache();

        $size = filesize($filepath);

        // Keep last 30 backups, remove older
        $files = glob($backupDir . '*.sql');
        if ($files && count($files) > 30) {
            usort($files, function($a, $b) { return filemtime($a) - filemtime($b); });
            while (count($files) > 30) {
                $oldFile = array_shift($files);
                @unlink($oldFile);
            }
        }

        return [
            "success" => true,
            "filename" => $filename,
            "filepath" => $filepath,
            "size" => $size,
            "formatted_size" => round($size / 1024, 2) . ' KB',
            "timestamp" => date('Y-m-d H:i:s')
        ];

    } catch (Exception $e) {
        return [
            "success" => false,
            "message" => "Backup failed: " . $e->getMessage()
        ];
    }
}

// Handle GET / POST requests
$action = $_GET['action'] ?? $_POST['action'] ?? 'create';

$backupDir = get_backup_dir();

if ($action === 'list') {
    $files = glob($backupDir . '*.sql');
    $backups = [];

    if ($files) {
        foreach ($files as $file) {
            clearstatcache(true, $file);
            $sz = filesize($file);
            $backups[] = [
                "filename" => basename($file),
                "size" => $sz,
                "formatted_size" => round($sz / 1024, 2) . ' KB',
                "created_at" => date('Y-m-d H:i:s', filemtime($file))
            ];
        }

        usort($backups, function($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });
    }

    echo json_encode(["success" => true, "data" => $backups]);
    exit();
}

$reason = $_GET['reason'] ?? 'manual_admin';
$result = create_database_backup($reason);
echo json_encode($result);
?>
