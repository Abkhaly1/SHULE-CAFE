<?php
require_once __DIR__ . '/config/db.php';

try {
    // 1. Create schools table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS schools (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            type ENUM('Primary', 'Secondary', 'High School', 'College') DEFAULT 'Secondary',
            region VARCHAR(100) DEFAULT NULL,
            status ENUM('active', 'suspended') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // 2. Add school_id to users if not exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'school_id'");
    if ($checkColumn->rowCount() === 0) {
        $conn->exec("ALTER TABLE users ADD COLUMN school_id VARCHAR(36) NULL AFTER id");
        $conn->exec("ALTER TABLE users ADD CONSTRAINT fk_user_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE");
        echo "Added school_id to users.\n";
    }

    echo "Schools table setup completed successfully.\n";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage() . "\n");
}
?>
