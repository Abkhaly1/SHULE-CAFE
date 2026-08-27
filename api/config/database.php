<?php
/**
 * SHULE CAFE Enterprise
 * Copyright (c) 2026 SHULE CAFE Enterprise. All Rights Reserved.
 * PROPRIETARY & CONFIDENTIAL. Unauthorized copying or redistribution is strictly prohibited.
 */

class Database {
    private static $instance = null;
    private $pdo = null;

    private function connect() {
        $host = '127.0.0.1';
        $db_name = 'shule_cafe';
        $username = 'root';
        $password = '';

        try {
            $this->pdo = new PDO(
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
        } catch (PDOException $e) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            http_response_code(200);
            echo json_encode(["success" => false, "message" => "Database Connection Error: " . $e->getMessage()]);
            exit();
        }
    }

    private function __construct() {
        $this->connect();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        if ($this->pdo === null) {
            $this->connect();
        }
        return $this->pdo;
    }
}
