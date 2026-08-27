<?php
require_once 'config/db.php';

try {
    // Add class_id to users table for students
    $sql = "ALTER TABLE users ADD COLUMN class_id VARCHAR(36) DEFAULT NULL AFTER school_id;
            ALTER TABLE users ADD FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL;";

    $conn->exec($sql);
    echo "Column 'class_id' added to users successfully.\n";

} catch(PDOException $e) {
    // Ignore if column already exists
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column 'class_id' already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
