<?php
$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create DB and USE
    $pdo->exec("CREATE DATABASE IF NOT EXISTS shule_cafe");
    $pdo->exec("USE shule_cafe");

    // 1. Create Schools
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schools (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            type ENUM('Primary', 'Secondary', 'High School', 'College') DEFAULT 'Secondary',
            region VARCHAR(100) DEFAULT NULL,
            status ENUM('active', 'suspended') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2. Create Classes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS classes (
            id VARCHAR(36) PRIMARY KEY,
            school_id VARCHAR(36) NOT NULL,
            name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 3. Create Users
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id VARCHAR(36) PRIMARY KEY,
            school_id VARCHAR(36) DEFAULT NULL,
            class_id VARCHAR(36) DEFAULT NULL,
            full_name VARCHAR(100) NOT NULL,
            phone VARCHAR(20) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('super_admin', 'tenant_admin', 'regional_officer', 'teacher', 'student', 'parent', 'guardian') NOT NULL,
            status ENUM('active', 'suspended', 'locked') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
            FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Alter ENUM column on existing users table if needed
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'tenant_admin', 'regional_officer', 'teacher', 'student', 'parent', 'guardian') NOT NULL");

    // 4. Create Subjects
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subjects (
            id VARCHAR(36) PRIMARY KEY,
            school_id VARCHAR(36) NOT NULL,
            name VARCHAR(100) NOT NULL,
            code VARCHAR(20) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 5. Create Parent_Student mapping
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS parent_student (
            parent_id VARCHAR(36) NOT NULL,
            student_id VARCHAR(36) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (parent_id, student_id),
            FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Insert Super Admin
    $phone = '+255700000000';
    $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    
    if (!$stmt->fetch()) {
        $id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        $hash = password_hash('ShuleAdmin@2026', PASSWORD_BCRYPT);
        
        $insert = $pdo->prepare("INSERT INTO users (id, full_name, phone, password_hash, role) VALUES (?, ?, ?, ?, 'super_admin')");
        $insert->execute([$id, 'System Administrator', $phone, $hash]);
        echo "Super Admin created successfully.\n";
    } else {
        echo "Database setup completed. Super Admin exists.\n";
    }

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage() . "\n");
}
?>

