<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/AutomatedSchedulerEngine.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId && ($_SESSION['role'] ?? '') === 'super_admin') {
    $first = $conn->query("SELECT id FROM schools LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $schoolId = $first['id'] ?? null;
}

if (!$schoolId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'School context missing.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? 'status';
$year = $_GET['year'] ?? $input['year'] ?? date('Y');

function ensureTimetableTablesExist($conn) {
    $q1 = "CREATE TABLE IF NOT EXISTS timetable_configs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id VARCHAR(36) NOT NULL,
        academic_year_id VARCHAR(20) NOT NULL,
        level_code VARCHAR(50) NOT NULL,
        selected_grades TEXT NOT NULL,
        operational_days TEXT NOT NULL,
        periods_per_day INT NOT NULL DEFAULT 8,
        breaks_count INT NOT NULL DEFAULT 2,
        total_weekly_capacity INT NOT NULL DEFAULT 40,
        is_published TINYINT(1) NOT NULL DEFAULT 0,
        status ENUM('draft', 'active') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_school_year_level (school_id, academic_year_id, level_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    try {
        $conn->exec("ALTER TABLE timetable_configs ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 0");
    } catch (Exception $e) {}

    $q2 = "CREATE TABLE IF NOT EXISTS timetable_subject_frequencies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        config_id INT NOT NULL,
        school_id VARCHAR(36) NOT NULL,
        academic_year_id VARCHAR(20) NOT NULL,
        grade_id INT NOT NULL,
        subject_code VARCHAR(50) NOT NULL,
        weekly_frequency INT NOT NULL DEFAULT 4,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_config_grade_subject (config_id, grade_id, subject_code),
        FOREIGN KEY (config_id) REFERENCES timetable_configs(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $q3 = "CREATE TABLE IF NOT EXISTS timetable_periods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id VARCHAR(36) NOT NULL,
        level_type ENUM('O-Level', 'A-Level', 'Primary', 'Nursery') NOT NULL DEFAULT 'O-Level',
        period_number INT NOT NULL,
        period_name VARCHAR(50) DEFAULT 'Period',
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        is_break BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_school_level_period (school_id, level_type, period_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $q4 = "CREATE TABLE IF NOT EXISTS class_timetables (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id VARCHAR(36) NOT NULL,
        academic_year_id VARCHAR(20),
        day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
        period_id INT NOT NULL,
        class_stream_id VARCHAR(50) NOT NULL,
        subject_code VARCHAR(50) NOT NULL,
        teacher_id VARCHAR(36) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_class_slot (school_id, academic_year_id, day_of_week, period_id, class_stream_id),
        UNIQUE KEY unique_teacher_slot (school_id, academic_year_id, day_of_week, period_id, teacher_id),
        FOREIGN KEY (period_id) REFERENCES timetable_periods(id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    try {
        $conn->exec("ALTER TABLE class_timetables MODIFY day_of_week VARCHAR(20) NOT NULL");
    } catch (Exception $e) {}

    foreach ([$q1, $q2, $q3, $q4] as $q) {
        try { $conn->exec($q); } catch (PDOException $e) {}
    }
}

try {
    ensureTimetableTablesExist($conn);

    // 1. Status Check: Check if active master timetable config exists for current year
    if ($method === 'GET' && $action === 'status') {
        $stmt = $conn->prepare("SELECT * FROM timetable_configs WHERE school_id = ? AND academic_year_id = ? LIMIT 1");
        $stmt->execute([$schoolId, $year]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($config) {
            $config['selected_grades'] = json_decode($config['selected_grades'], true) ?? [];
            $config['operational_days'] = json_decode($config['operational_days'], true) ?? [];
        }

        echo json_encode([
            'success' => true,
            'has_active_config' => !empty($config),
            'config' => $config ?: null
        ]);
        exit();
    }

    // 2. Wizard Data: Fetch Education Levels, Grades, Subjects, Classrooms
    if ($method === 'GET' && $action === 'get_wizard_data') {
        // Education Levels
        $stmtL = $conn->prepare("SELECT * FROM school_education_levels WHERE school_id = ? AND status = 'active' ORDER BY id ASC");
        $stmtL->execute([$schoolId]);
        $levels = $stmtL->fetchAll(PDO::FETCH_ASSOC);

        // System Grades
        $stmtG = $conn->prepare("
            SELECT g.id, g.name AS grade_name, g.order_seq, el.code AS level_code, el.name AS level_name
            FROM grades g
            JOIN education_levels el ON g.level_id = el.id
            ORDER BY el.id ASC, g.order_seq ASC
        ");
        $stmtG->execute();
        $grades = $stmtG->fetchAll(PDO::FETCH_ASSOC);

        // Approved Subjects per school (only status = 'active')
        $stmtS = $conn->prepare("
            SELECT sas.subject_code AS code, sas.subject_name AS name, sas.level_code
            FROM school_approved_subjects sas
            WHERE sas.school_id = ? AND sas.status = 'active'
            ORDER BY sas.level_code ASC, sas.subject_name ASC
        ");
        $stmtS->execute([$schoolId]);
        $approvedSubjects = $stmtS->fetchAll(PDO::FETCH_ASSOC);

        if (empty($approvedSubjects)) {
            $stmtS2 = $conn->prepare("
                SELECT s.code, s.name, COALESCE(s.level_type, 'O-LEVEL') AS level_code
                FROM subjects s
                ORDER BY s.name ASC
            ");
            $stmtS2->execute();
            $approvedSubjects = $stmtS2->fetchAll(PDO::FETCH_ASSOC);
        }

        // Grade Subjects mapping for 2026 (strictly for this school)
        $stmtGS = $conn->prepare("
            SELECT gs.grade_id, gs.subject_code, gs.subject_name
            FROM grade_subjects gs
            WHERE gs.school_id = ? AND gs.academic_year = ?
            ORDER BY gs.is_core DESC, gs.subject_name ASC
        ");
        $stmtGS->execute([$schoolId, $year]);
        $gradeSubjects = $stmtGS->fetchAll(PDO::FETCH_ASSOC);

        $gradeSubjMap = [];
        foreach ($gradeSubjects as $gs) {
            $gradeSubjMap[$gs['grade_id']][] = [
                'code' => $gs['subject_code'],
                'name' => $gs['subject_name'],
                'subject_code' => $gs['subject_code'],
                'subject_name' => $gs['subject_name']
            ];
        }

        echo json_encode([
            'success' => true,
            'education_levels' => $levels,
            'grades' => $grades,
            'approved_subjects' => $approvedSubjects,
            'grade_subjects_map' => $gradeSubjMap
        ]);
        exit();
    }

    // 3. Save Config (Stages 1 to 4)
    if ($method === 'POST' && $action === 'save_config') {
        $levelCode = $input['level_code'] ?? 'O-LEVEL';
        $selectedGrades = $input['selected_grades'] ?? []; // array of grade_id ints
        $operationalDays = $input['operational_days'] ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $periodsPerDay = intval($input['periods_per_day'] ?? 8);
        $breaksCount = intval($input['breaks_count'] ?? 2);
        $timeMatrix = $input['time_matrix'] ?? []; // array of period objects
        $frequencies = $input['frequencies'] ?? []; // array of { grade_id, subject_code, weekly_frequency }

        if (empty($selectedGrades)) {
            echo json_encode(['success' => false, 'message' => 'Please select at least one target grade cohort.']);
            exit();
        }

        $totalCapacity = count($operationalDays) * $periodsPerDay;

        $conn->beginTransaction();
        try {
            // Save or Update master config
            $stmtC = $conn->prepare("
                INSERT INTO timetable_configs (school_id, academic_year_id, level_code, selected_grades, operational_days, periods_per_day, breaks_count, total_weekly_capacity, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ON DUPLICATE KEY UPDATE 
                    selected_grades = VALUES(selected_grades),
                    operational_days = VALUES(operational_days),
                    periods_per_day = VALUES(periods_per_day),
                    breaks_count = VALUES(breaks_count),
                    total_weekly_capacity = VALUES(total_weekly_capacity),
                    status = 'active'
            ");
            $stmtC->execute([
                $schoolId,
                $year,
                $levelCode,
                json_encode($selectedGrades),
                json_encode($operationalDays),
                $periodsPerDay,
                $breaksCount,
                $totalCapacity
            ]);

            // Retrieve config_id
            $stmtGetC = $conn->prepare("SELECT id FROM timetable_configs WHERE school_id = ? AND academic_year_id = ? AND level_code = ? LIMIT 1");
            $stmtGetC->execute([$schoolId, $year, $levelCode]);
            $configId = $stmtGetC->fetchColumn();

            // Save Time Matrix into timetable_periods
            if (!empty($timeMatrix)) {
                $stmtDelP = $conn->prepare("DELETE FROM timetable_periods WHERE school_id = ? AND level_type = ?");
                $stmtDelP->execute([$schoolId, $levelCode]);

                $stmtInsP = $conn->prepare("
                    INSERT INTO timetable_periods (school_id, level_type, period_number, period_name, start_time, end_time, is_break)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($timeMatrix as $p) {
                    $stmtInsP->execute([
                        $schoolId,
                        $levelCode,
                        intval($p['period_number']),
                        $p['period_name'] ?? ('Period ' . $p['period_number']),
                        $p['start_time'] ?? '08:00',
                        $p['end_time'] ?? '08:40',
                        !empty($p['is_break']) ? 1 : 0
                    ]);
                }
            }

            // Save Subject Frequencies into timetable_subject_frequencies
            if (!empty($frequencies)) {
                $stmtDelF = $conn->prepare("DELETE FROM timetable_subject_frequencies WHERE config_id = ?");
                $stmtDelF->execute([$configId]);

                $stmtInsF = $conn->prepare("
                    INSERT INTO timetable_subject_frequencies (config_id, school_id, academic_year_id, grade_id, subject_code, weekly_frequency)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                foreach ($frequencies as $f) {
                    if (!empty($f['grade_id']) && !empty($f['subject_code'])) {
                        $stmtInsF->execute([
                            $configId,
                            $schoolId,
                            $year,
                            intval($f['grade_id']),
                            $f['subject_code'],
                            intval($f['weekly_frequency'] ?? 4)
                        ]);
                    }
                }
            }

            $conn->commit();
            echo json_encode([
                'success' => true,
                'config_id' => $configId,
                'total_weekly_capacity' => $totalCapacity,
                'message' => 'Timetable setup wizard configuration saved successfully.'
            ]);
            exit();

        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to save configuration: ' . $e->getMessage()]);
            exit();
        }
    }

    // 4. Generate Automated Timetable (Stage 5 Execution)
    if ($method === 'POST' && $action === 'generate_automated_timetable') {
        $configId = intval($input['config_id'] ?? 0);
        if (!$configId) {
            $stmtC = $conn->prepare("SELECT id FROM timetable_configs WHERE school_id = ? AND academic_year_id = ? ORDER BY id DESC LIMIT 1");
            $stmtC->execute([$schoolId, $year]);
            $configId = $stmtC->fetchColumn();
        }

        if (!$configId) {
            echo json_encode(['success' => false, 'message' => 'No configuration ID available. Please complete wizard Stage 1-4 first.']);
            exit();
        }

        $engine = new AutomatedSchedulerEngine($conn);
        $result = $engine->generateConflictFreeTimetable($schoolId, $year, $configId);

        echo json_encode($result);
        exit();
    }

    // 5. Get Timetable Matrix for Display
    if ($method === 'GET' && $action === 'get_timetable_matrix') {
        $streamId = $_GET['stream_id'] ?? '';
        
        // Fetch Periods
        $stmtP = $conn->prepare("
            SELECT id, period_number, period_name, start_time, end_time, is_break 
            FROM timetable_periods 
            WHERE school_id = ? 
            ORDER BY period_number ASC
        ");
        $stmtP->execute([$schoolId]);
        $periods = $stmtP->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Timetable Slots
        $sql = "
            SELECT ct.id, ct.day_of_week, ct.period_id, ct.class_stream_id, ct.subject_code,
                   COALESCE(sas.subject_name, s.name, ct.subject_code) AS subject_name,
                   u.full_name AS teacher_name
            FROM class_timetables ct
            LEFT JOIN subjects s ON (ct.school_id = s.school_id AND ct.subject_code = s.code)
            LEFT JOIN school_approved_subjects sas ON (ct.school_id = sas.school_id AND ct.subject_code = sas.subject_code)
            LEFT JOIN users u ON ct.teacher_id = u.id
            WHERE ct.school_id = ? AND ct.academic_year_id = ?
        ";
        $params = [$schoolId, $year];
        if ($streamId) {
            $sql .= " AND ct.class_stream_id = ?";
            $params[] = $streamId;
        }

        $stmtTt = $conn->prepare($sql);
        $stmtTt->execute($params);
        $slots = $stmtTt->fetchAll(PDO::FETCH_ASSOC);

        // Group matrix by day -> period_id -> stream
        $matrix = [];
        foreach ($slots as $s) {
            $matrix[$s['class_stream_id']][$s['day_of_week']][$s['period_id']] = $s;
        }

        echo json_encode([
            'success' => true,
            'periods' => $periods,
            'matrix' => $matrix,
            'total_scheduled_slots' => count($slots)
        ]);
        exit();
    }

    // 6. Save Layout as Draft (Stage 7)
    if ($method === 'POST' && $action === 'save_draft') {
        $stmt = $conn->prepare("UPDATE timetable_configs SET is_published = 0 WHERE school_id = ? AND academic_year_id = ?");
        $stmt->execute([$schoolId, $year]);
        echo json_encode([
            'success' => true,
            'is_published' => 0,
            'message' => 'Timetable layout saved as DRAFT. Changes locked from teacher/student portals.'
        ]);
        exit();
    }

    // 7. Publish Timetable Live (Stage 7)
    if ($method === 'POST' && $action === 'publish_live') {
        $stmt = $conn->prepare("UPDATE timetable_configs SET is_published = 1 WHERE school_id = ? AND academic_year_id = ?");
        $stmt->execute([$schoolId, $year]);
        echo json_encode([
            'success' => true,
            'is_published' => 1,
            'message' => '🚀 Timetable is now LIVE and PUBLISHED shuleni!'
        ]);
        exit();
    }

    // 8. Dynamic Drag-and-Drop Slot Swap & Collision Validation (Stage 6)
    if ($method === 'POST' && $action === 'swap_slot') {
        $fromDay       = $input['from_day'] ?? '';
        $fromPeriodId  = intval($input['from_period_id'] ?? 0);
        $toDay         = $input['to_day'] ?? '';
        $toPeriodId    = intval($input['to_period_id'] ?? 0);
        $classStreamId = $input['class_stream_id'] ?? '';

        if (!$fromDay || !$fromPeriodId || !$toDay || !$toPeriodId || !$classStreamId) {
            echo json_encode(['success' => false, 'message' => 'Missing slot parameters.']);
            exit();
        }

        // Fetch source slot record directly from database
        $stmtSrc = $conn->prepare("
            SELECT * FROM class_timetables
            WHERE school_id = ? AND academic_year_id = ?
              AND day_of_week = ? AND period_id = ? AND class_stream_id = ?
            LIMIT 1
        ");
        $stmtSrc->execute([$schoolId, $year, $fromDay, $fromPeriodId, $classStreamId]);
        $sourceSlot = $stmtSrc->fetch(PDO::FETCH_ASSOC);

        if (!$sourceSlot) {
            echo json_encode(['success' => false, 'message' => 'Source slot not found.']);
            exit();
        }

        $teacherId = $sourceSlot['teacher_id'];

        // Check 1: Teacher Collision Scanning for source teacher in target slot
        if (!empty($teacherId)) {
            $stmtChk = $conn->prepare("
                SELECT ct.class_stream_id, COALESCE(c.classroom_name, ct.class_stream_id) AS class_name,
                       u.full_name AS teacher_name, ct.subject_code
                FROM class_timetables ct
                LEFT JOIN classrooms c ON (ct.class_stream_id = c.id OR ct.class_stream_id = c.classroom_name)
                LEFT JOIN users u ON ct.teacher_id = u.id
                WHERE ct.school_id = ? AND ct.academic_year_id = ?
                  AND ct.day_of_week = ? AND ct.period_id = ?
                  AND ct.teacher_id = ?
                  AND ct.class_stream_id != ?
                LIMIT 1
            ");
            $stmtChk->execute([$schoolId, $year, $toDay, $toPeriodId, $teacherId, $classStreamId]);
            $conflict = $stmtChk->fetch(PDO::FETCH_ASSOC);

            if ($conflict) {
                $tName = $conflict['teacher_name'] ?: 'Teacher';
                $cName = $conflict['class_name'] ?: 'another class';
                echo json_encode([
                    'success' => false,
                    'conflict' => true,
                    'message' => "Invalid Action: Teacher {$tName} is already assigned to {$cName} during this period slot."
                ]);
                exit();
            }
        }

        // Fetch target slot record directly from database
        $stmtTgt = $conn->prepare("
            SELECT * FROM class_timetables
            WHERE school_id = ? AND academic_year_id = ?
              AND day_of_week = ? AND period_id = ? AND class_stream_id = ?
            LIMIT 1
        ");
        $stmtTgt->execute([$schoolId, $year, $toDay, $toPeriodId, $classStreamId]);
        $targetSlot = $stmtTgt->fetch(PDO::FETCH_ASSOC);

        // Perform slot swap atomically
        $conn->beginTransaction();
        try {
            if ($targetSlot) {
                // Check if swapping target teacher into source slot creates a collision
                if (!empty($targetSlot['teacher_id'])) {
                    $stmtTgtChk = $conn->prepare("
                        SELECT ct.class_stream_id, COALESCE(c.classroom_name, ct.class_stream_id) AS class_name,
                               u.full_name AS teacher_name
                        FROM class_timetables ct
                        LEFT JOIN classrooms c ON (ct.class_stream_id = c.id OR ct.class_stream_id = c.classroom_name)
                        LEFT JOIN users u ON ct.teacher_id = u.id
                        WHERE ct.school_id = ? AND ct.academic_year_id = ?
                          AND ct.day_of_week = ? AND ct.period_id = ?
                          AND ct.teacher_id = ?
                          AND ct.class_stream_id != ?
                        LIMIT 1
                    ");
                    $stmtTgtChk->execute([$schoolId, $year, $fromDay, $fromPeriodId, $targetSlot['teacher_id'], $classStreamId]);
                    $tgtConflict = $stmtTgtChk->fetch(PDO::FETCH_ASSOC);

                    if ($tgtConflict) {
                        $conn->rollBack();
                        $tName = $tgtConflict['teacher_name'] ?: 'Teacher';
                        $cName = $tgtConflict['class_name'] ?: 'another class';
                        echo json_encode([
                            'success' => false,
                            'conflict' => true,
                            'message' => "Invalid Action: Swapping would place {$tName} in collision with {$cName} during source slot."
                        ]);
                        exit();
                    }
                }

                // Step 1: Move target slot out to source position FIRST
                $stmtSwapBack = $conn->prepare("
                    UPDATE class_timetables
                    SET day_of_week = ?, period_id = ?
                    WHERE id = ?
                ");
                $stmtSwapBack->execute([$fromDay, $fromPeriodId, $targetSlot['id']]);
            }

            // Step 2: Move source slot to target position SECOND
            $stmtMove = $conn->prepare("
                UPDATE class_timetables
                SET day_of_week = ?, period_id = ?
                WHERE id = ?
            ");
            $stmtMove->execute([$toDay, $toPeriodId, $sourceSlot['id']]);

            $conn->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Slot swapped cleanly without conflicts.'
            ]);
            exit();

        } catch (PDOException $ex) {
            if ($conn->inTransaction()) $conn->rollBack();
            $raw = $ex->getMessage();
            $friendly = 'Invalid Action: Timetable slot conflict detected.';

            if (strpos($raw, '1062') !== false || strpos($raw, 'unique_teacher_slot') !== false) {
                $friendly = 'Invalid Action: The selected teacher is already assigned to another classroom during this period slot.';
            } else if (strpos($raw, '1265') !== false || strpos($raw, 'day_of_week') !== false) {
                $friendly = 'Invalid Action: The selected day or period slot is invalid for this classroom timetable.';
            }

            echo json_encode([
                'success' => false,
                'conflict' => true,
                'message' => $friendly
            ]);
            exit();
        }
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);

} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    http_response_code(500);

    $raw = $e->getMessage();
    $friendly = 'System encountered an unexpected timetable issue. Please try again.';

    if (strpos($raw, '1062') !== false || strpos($raw, 'unique_teacher_slot') !== false) {
        $friendly = 'Teacher Conflict: Selected teacher is already assigned to another classroom during this period slot.';
    } else if (strpos($raw, '1265') !== false || strpos($raw, 'day_of_week') !== false) {
        $friendly = 'Invalid Day: Selected day slot is invalid.';
    }

    echo json_encode(['success' => false, 'message' => $friendly]);
}
?>
