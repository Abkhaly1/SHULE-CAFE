<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once 'config/db.php';

$allClasses = [
    ["id" => "tpl-cls-p0", "type" => "class", "name" => "Nursery / Pre-Primary", "code" => "PRE-PRIM", "level_code" => "PRIM", "description" => "Early Childhood & Nursery"],
    ["id" => "tpl-cls-p1", "type" => "class", "name" => "Standard 1", "code" => "STD1", "level_code" => "PRIM", "description" => "Primary Education Standard 1"],
    ["id" => "tpl-cls-p2", "type" => "class", "name" => "Standard 2", "code" => "STD2", "level_code" => "PRIM", "description" => "Primary Education Standard 2"],
    ["id" => "tpl-cls-p3", "type" => "class", "name" => "Standard 3", "code" => "STD3", "level_code" => "PRIM", "description" => "Primary Education Standard 3"],
    ["id" => "tpl-cls-p4", "type" => "class", "name" => "Standard 4", "code" => "STD4", "level_code" => "PRIM", "description" => "Primary Education Standard 4"],
    ["id" => "tpl-cls-p5", "type" => "class", "name" => "Standard 5", "code" => "STD5", "level_code" => "PRIM", "description" => "Primary Education Standard 5"],
    ["id" => "tpl-cls-p6", "type" => "class", "name" => "Standard 6", "code" => "STD6", "level_code" => "PRIM", "description" => "Primary Education Standard 6"],
    ["id" => "tpl-cls-p7", "type" => "class", "name" => "Standard 7", "code" => "STD7", "level_code" => "PRIM", "description" => "Primary Education Standard 7 Final Year"],

    ["id" => "tpl-cls-1", "type" => "class", "name" => "Form 1", "code" => "F1", "level_code" => "O-LEVEL", "description" => "First year of O-Level Secondary Education"],
    ["id" => "tpl-cls-2", "type" => "class", "name" => "Form 2", "code" => "F2", "level_code" => "O-LEVEL", "description" => "Second year of O-Level Secondary Education"],
    ["id" => "tpl-cls-3", "type" => "class", "name" => "Form 3", "code" => "F3", "level_code" => "O-LEVEL", "description" => "Third year of O-Level Secondary Education"],
    ["id" => "tpl-cls-4", "type" => "class", "name" => "Form 4", "code" => "F4", "level_code" => "O-LEVEL", "description" => "Final year of O-Level Secondary Education"],

    ["id" => "tpl-cls-5", "type" => "class", "name" => "Form 5", "code" => "F5", "level_code" => "A-LEVEL", "description" => "First year of A-Level High School"],
    ["id" => "tpl-cls-6", "type" => "class", "name" => "Form 6", "code" => "F6", "level_code" => "A-LEVEL", "description" => "Final year of A-Level High School"]
];

try {
    $stmt = $conn->prepare("INSERT INTO academic_templates (id, type, name, code, level_code, description, status) VALUES (?, ?, ?, ?, ?, ?, 'active') ON DUPLICATE KEY UPDATE name = VALUES(name), code = VALUES(code), level_code = VALUES(level_code), description = VALUES(description)");

    foreach ($allClasses as $c) {
        $stmt->execute([$c["id"], $c["type"], $c["name"], $c["code"], $c["level_code"], $c["description"]]);
    }

    echo json_encode(["success" => true, "message" => "All classes seeded successfully."]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
