<?php
/**
 * SHULE CAFE - Automatic Data Loss Prevention & Backup Engine
 * Protects database against accidental data loss, corruption, or hardware failures.
 */

if (!file_exists(__DIR__ . '/../../backups')) {
    mkdir(__DIR__ . '/../../backups', 0755, true);
}

/**
 * Log Security Event or Suspicious Tampering Attempt
 */
function logSecurityEvent($conn, $eventType, $description, $schoolId = null, $userId = null) {
    try {
        $ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
        $sid = $schoolId ?? $_SESSION['school_id'] ?? null;
        $uid = $userId ?? $_SESSION['user_id'] ?? null;

        $stmt = $conn->prepare("
            INSERT INTO security_audit_logs (school_id, user_id, ip_address, event_type, description, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$sid, $uid, $ip, $eventType, $description, $ua]);
    } catch (Exception $e) {}
}

/**
 * Generate Automated Database Backup Dump
 */
function triggerAutoBackup($conn) {
    try {
        $backupDir = __DIR__ . '/../../backups';
        $filename = 'shule_cafe_auto_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupDir . '/' . $filename;

        // Fetch all tables
        $tables = [];
        $stmt = $conn->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $dump = "-- SHULE CAFE AUTOMATED DATA PROTECTION BACKUP DUMP\n";
        $dump .= "-- Generated At: " . date('Y-m-d H:i:s') . "\n\n";
        $dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $row = $conn->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            $dump .= "\n\n" . $row[1] . ";\n\n";

            $rowsStmt = $conn->query("SELECT * FROM `$table`");
            while ($r = $rowsStmt->fetch(PDO::FETCH_ASSOC)) {
                $keys = array_map(function($k) { return "`$k`"; }, array_keys($r));
                $vals = array_map(function($v) use ($conn) {
                    if ($v === null) return "NULL";
                    return $conn->quote($v);
                }, array_values($r));

                $dump .= "INSERT INTO `$table` (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $vals) . ");\n";
            }
        }

        $dump .= "\n\nSET FOREIGN_KEY_CHECKS=1;\n";

        file_put_contents($filepath, $dump);

        // Prune old backups older than 14 days
        $files = glob($backupDir . '/*.sql');
        $now = time();
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 14 * 86400) {
                    unlink($file);
                }
            }
        }

        return ["success" => true, "file" => $filename];
    } catch (Exception $e) {
        return ["success" => false, "message" => $e->getMessage()];
    }
}
?>
