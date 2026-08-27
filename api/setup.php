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
            school_code VARCHAR(50) UNIQUE DEFAULT NULL,
            name VARCHAR(150) NOT NULL,
            type ENUM('Primary', 'Secondary', 'High School', 'College') DEFAULT 'Secondary',
            necta_no VARCHAR(50) DEFAULT NULL,
            ownership_type VARCHAR(50) DEFAULT 'Private',
            gender_classification VARCHAR(50) DEFAULT 'Co-Education',
            region VARCHAR(100) DEFAULT NULL,
            district VARCHAR(100) DEFAULT NULL,
            ward_address VARCHAR(255) DEFAULT NULL,
            school_email VARCHAR(150) DEFAULT NULL,
            school_phone VARCHAR(50) DEFAULT NULL,
            status ENUM('active', 'suspended') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Alter migrations for existing schools table
    try { $pdo->exec("ALTER TABLE schools ADD COLUMN school_code VARCHAR(50) UNIQUE DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE schools ADD COLUMN necta_no VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE schools ADD COLUMN ownership_type VARCHAR(50) DEFAULT 'Private'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE schools ADD COLUMN gender_classification VARCHAR(50) DEFAULT 'Co-Education'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE schools ADD COLUMN district VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE schools ADD COLUMN ward_address VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE schools ADD COLUMN school_email VARCHAR(150) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE schools ADD COLUMN school_phone VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}

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
            user_code VARCHAR(50) DEFAULT NULL,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(150) DEFAULT NULL,
            phone VARCHAR(20) UNIQUE NOT NULL,
            gender VARCHAR(20) DEFAULT NULL,
            department VARCHAR(100) DEFAULT NULL,
            password_hash VARCHAR(255) NOT NULL,
            temp_password VARCHAR(100) DEFAULT NULL,
            role ENUM('super_admin', 'school_admin', 'tenant_admin', 'regional_officer', 'headmaster', 'teacher', 'student', 'parent', 'guardian') NOT NULL DEFAULT 'tenant_admin',
            status ENUM('active', 'suspended', 'locked') DEFAULT 'active',
            is_password_changed TINYINT DEFAULT 0,
            first_login_completed TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
            FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Alter migrations for existing users table
    try { $pdo->exec("ALTER TABLE users ADD COLUMN user_code VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(150) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN gender VARCHAR(20) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN department VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN temp_password VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN is_password_changed TINYINT DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN first_login_completed TINYINT DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'school_admin', 'tenant_admin', 'regional_officer', 'headmaster', 'teacher', 'student', 'parent', 'guardian') NOT NULL DEFAULT 'tenant_admin'"); } catch (Exception $e) {}

    // 3.1 Create Login Attempts table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            identifier VARCHAR(150) NOT NULL,
            attempts INT DEFAULT 1,
            locked_until DATETIME DEFAULT NULL,
            last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (ip_address, identifier),
            INDEX (locked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

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

    // 6. Create School Education Levels table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS school_education_levels (
            school_id VARCHAR(36) NOT NULL,
            level_code VARCHAR(20) NOT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (school_id, level_code),
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Insert Super Admin
    $phone = '+255700000000';
    $email = 'admin@shulecafe.com';
    $userCode = 'S/CAFE-ADMIN-0001';
    $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? OR email = ? OR user_code = ?");
    $stmt->execute([$phone, $email, $userCode]);
    
    if (!$stmt->fetch()) {
        $id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        $hash = password_hash('ShuleAdmin@2026', PASSWORD_BCRYPT);
        
        $insert = $pdo->prepare("INSERT INTO users (id, user_code, full_name, email, phone, password_hash, role, is_password_changed, first_login_completed) VALUES (?, ?, ?, ?, ?, ?, 'super_admin', 1, 1)");
        $insert->execute([$id, $userCode, 'System Administrator', $email, $phone, $hash]);
        echo "Super Admin created successfully (Email: admin@shulecafe.com | ID: S/CAFE-ADMIN-0001 | Pass: ShuleAdmin@2026).\n";
    } else {
        echo "Database setup completed. Super Admin exists.\n";
    }

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage() . "\n");
}
?>

