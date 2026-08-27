<?php
require_once 'config/db.php';

try {
    // Create classes table
    $sql = "CREATE TABLE IF NOT EXISTS classes (
        id VARCHAR(36) PRIMARY KEY,
        school_id VARCHAR(36) NOT NULL,
        name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $conn->exec($sql);
    echo "Table 'classes' created successfully.\n";

} catch(PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
?>
