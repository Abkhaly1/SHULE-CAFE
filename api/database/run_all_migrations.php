<?php
/**
 * SHULE CAFE Enterprise - Master Database Migration Runner
 * Executes all system migrations and seeds initial database structures.
 */

header('Content-Type: text/plain; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';

try {
    $conn = Database::getInstance()->getConnection();
    echo "=== RUNNING ALL SHULE CAFE MIGRATIONS ===\n\n";

    // 1. Base Setup
    echo "[1/10] Running base setup...\n";
    require_once __DIR__ . '/../setup.php';

    // 2. Class Subdivision & Education Levels
    echo "\n[2/10] Running class subdivision & streams migration...\n";
    require_once __DIR__ . '/migrations/create_class_subdivision_schema.php';

    // 3. Classrooms & Student Allocations
    echo "\n[3/10] Running classrooms & student allocation migration...\n";
    require_once __DIR__ . '/migrations/create_classrooms_schema.php';

    // 4. Flexible Teacher Mapping & Class Teachers
    echo "\n[4/10] Running teacher mapping & class teachers migration...\n";
    require_once __DIR__ . '/migrations/create_flexible_teacher_mapping_tables.php';

    // 5. Grading Scales & Division Scales
    echo "\n[5/10] Running grading & division scales migration...\n";
    require_once __DIR__ . '/migrations/create_grading_and_division_tables.php';

    // 6. Headmaster Academics & Approved Subjects
    echo "\n[6/10] Running headmaster academics migration...\n";
    require_once __DIR__ . '/migrations/create_headmaster_academics_tables.php';

    // 7. Teacher Qualifications & Classrooms
    echo "\n[7/10] Running teacher qualifications migration...\n";
    require_once __DIR__ . '/migrations/create_teacher_allocation_tables.php';

    // 8. Timetable Configuration & Schedule Tables
    echo "\n[8/10] Running timetable configuration migration...\n";
    require_once __DIR__ . '/migrations/create_timetable_config_tables.php';

    // 9. Dynamic Marks Entry
    echo "\n[9/10] Running marks entry dynamic migration...\n";
    if (file_exists(__DIR__ . '/../../migrate_marks.php')) {
        require_once __DIR__ . '/../../migrate_marks.php';
    }

    // 10. Multi-Teacher Constraints Fix
    echo "\n[10/10] Running constraint updates...\n";
    require_once __DIR__ . '/migrations/fix_multi_teacher_unique_key.php';

    echo "\n=== ALL MIGRATIONS COMPLETED SUCCESSFULLY ===\n\n";
    echo "CURRENT TABLES IN DATABASE 'shule_cafe':\n";
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $i => $tbl) {
        $count = $conn->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
        echo sprintf(" %2d. %-35s (Rows: %d)\n", $i + 1, $tbl, $count);
    }

} catch (Exception $e) {
    echo "\n[ERROR] Migration failed: " . $e->getMessage() . "\n";
}
