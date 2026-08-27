<?php
require_once __DIR__ . '/../../config/db.php';

try {
    // 1. Table for Subject Grading Scales
    $conn->exec("CREATE TABLE IF NOT EXISTS grading_scales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        level_type ENUM('O-Level', 'A-Level', 'Primary', 'Nursery') NOT NULL,
        min_mark INT NOT NULL,
        max_mark INT NOT NULL,
        grade VARCHAR(5) NOT NULL,
        points INT NOT NULL,
        remark VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Table for Overall Division Scales
    $conn->exec("CREATE TABLE IF NOT EXISTS division_scales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        level_type ENUM('O-Level', 'A-Level', 'Primary', 'Nursery') NOT NULL,
        min_points INT NOT NULL,
        max_points INT NOT NULL,
        division_name VARCHAR(20) NOT NULL,
        remark VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Truncate tables for fresh clean default seed
    $conn->exec("TRUNCATE TABLE grading_scales");
    $conn->exec("TRUNCATE TABLE division_scales");

    // 3. Seed Default Grading Scales
    $stmtGrading = $conn->prepare("INSERT INTO grading_scales (level_type, min_mark, max_mark, grade, points, remark) VALUES (?, ?, ?, ?, ?, ?)");
    
    $gradingData = [
        // O-Level
        ['O-Level', 75, 100, 'A', 1, 'Excellent'],
        ['O-Level', 65, 74, 'B', 2, 'Very Good'],
        ['O-Level', 45, 64, 'C', 3, 'Good'],
        ['O-Level', 30, 44, 'D', 4, 'Satisfactory'],
        ['O-Level', 0, 29, 'F', 7, 'Fail'],

        // A-Level
        ['A-Level', 80, 100, 'A', 1, 'Excellent'],
        ['A-Level', 70, 79, 'B', 2, 'Very Good'],
        ['A-Level', 60, 69, 'C', 3, 'Good'],
        ['A-Level', 50, 59, 'D', 4, 'Satisfactory'],
        ['A-Level', 40, 49, 'E', 5, 'Sufficient'],
        ['A-Level', 35, 39, 'S', 6, 'Subsidiary'],
        ['A-Level', 0, 34, 'F', 7, 'Fail'],

        // Primary
        ['Primary', 81, 100, 'A', 1, 'Excellent'],
        ['Primary', 61, 80, 'B', 2, 'Very Good'],
        ['Primary', 41, 60, 'C', 3, 'Average'],
        ['Primary', 21, 40, 'D', 4, 'Satisfactory'],
        ['Primary', 0, 20, 'E', 5, 'Fail']
    ];

    foreach ($gradingData as $g) {
        $stmtGrading->execute($g);
    }

    // 4. Seed Default Division Scales
    $stmtDivision = $conn->prepare("INSERT INTO division_scales (level_type, min_points, max_points, division_name, remark) VALUES (?, ?, ?, ?, ?)");

    $divisionData = [
        // O-Level Divisions
        ['O-Level', 7, 17, 'Division I', 'Distinction'],
        ['O-Level', 18, 21, 'Division II', 'Merit'],
        ['O-Level', 22, 25, 'Division III', 'Credit'],
        ['O-Level', 26, 34, 'Division IV', 'Pass'],
        ['O-Level', 35, 49, 'Division 0', 'Fail'],

        // A-Level Divisions
        ['A-Level', 3, 9, 'Division I', 'Distinction'],
        ['A-Level', 10, 12, 'Division II', 'Merit'],
        ['A-Level', 13, 17, 'Division III', 'Credit'],
        ['A-Level', 18, 19, 'Division IV', 'Pass'],
        ['A-Level', 20, 21, 'Division 0', 'Fail'],

        // Primary Grades Aggregates
        ['Primary', 5, 10, 'Grade A', 'High Distinction'],
        ['Primary', 11, 15, 'Grade B', 'Distinction'],
        ['Primary', 16, 20, 'Grade C', 'Average Pass'],
        ['Primary', 21, 25, 'Grade D', 'Marginal Pass']
    ];

    foreach ($divisionData as $d) {
        $stmtDivision->execute($d);
    }

    echo json_encode(["success" => true, "message" => "Grading and Division Scale tables successfully created and seeded."]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
