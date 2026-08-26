<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $name    = trim($_GET['name'] ?? '');
    $nectaNo = trim($_GET['necta_no'] ?? '');

    $nameAvailable = true;
    $nameMessage   = '';

    $nectaAvailable = true;
    $nectaMessage   = '';

    if (!empty($name)) {
        $stmt = $db->prepare("SELECT id FROM schools WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $stmt->execute([$name]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $nameAvailable = false;
            $nameMessage   = 'School name is already taken';
        } else {
            $nameMessage   = 'School name is available';
        }
    }

    if (!empty($nectaNo)) {
        try { $db->exec("ALTER TABLE schools ADD COLUMN necta_no VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}

        $stmt2 = $db->prepare("SELECT id FROM schools WHERE LOWER(necta_no) = LOWER(?) AND necta_no != '' LIMIT 1");
        $stmt2->execute([$nectaNo]);
        if ($stmt2->fetch(PDO::FETCH_ASSOC)) {
            $nectaAvailable = false;
            $nectaMessage   = 'NECTA registration number is already registered to another school';
        } else {
            $nectaAvailable = true;
            $nectaMessage   = 'NECTA registration number is available';
        }
    }
    
    echo json_encode([
        'success' => true,
        'available' => ($nameAvailable && $nectaAvailable),
        'name_available' => $nameAvailable,
        'name_message' => $nameMessage,
        'necta_available' => $nectaAvailable,
        'necta_message' => $nectaMessage
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => true, 'available' => true, 'name_available' => true, 'necta_available' => true, 'error' => $e->getMessage()]);
}
