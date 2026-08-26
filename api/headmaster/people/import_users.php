<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId && ($_SESSION['role'] ?? '') === 'super_admin') {
    $row = $conn->query("SELECT id FROM schools LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $schoolId = $row['id'] ?? null;
}

if (!$schoolId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No active school tenant context found.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';

// Helper UUID generator
function generateUuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

try {
    // 1. GET: CSV Sample Template
    if ($method === 'GET' && $action === 'template') {
        $role = $_GET['role'] ?? 'teacher';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $role . '_import_template.csv"');
        
        if ($role === 'student') {
            echo "Full Name,Reg Code\n";
            echo "Baraka Juma Mussa,STD/2026/001\n";
            echo "Amani Hassan Juma,STD/2026/002\n";
            echo "Neema Charles Kimaro,STD/2026/003\n";
        } else {
            echo "Full Name,Reg Code,Department\n";
            echo "Mr. Baraka Test,TCH/2026/001,Mathematics\n";
            echo "Ms. Asha Juma,TCH/2026/002,Sciences\n";
            echo "Mr. David John,TCH/2026/003,Languages\n";
        }
        exit();
    }

    // 2. POST: Detect Duplicates & Conflict Analysis
    if ($method === 'POST' && $action === 'detect_duplicates') {
        $role = $input['role'] ?? 'teacher';
        $rows = $input['rows'] ?? []; // [{full_name, user_code, department}]

        if (empty($rows)) {
            echo json_encode(['success' => false, 'message' => 'No user rows provided for duplicate analysis.']);
            exit();
        }

        $parsedRows = [];
        $conflicts = [];

        // Pre-fetch all existing users in this school
        $stmtExisting = $conn->prepare("SELECT id, user_code, full_name, role, status FROM users WHERE school_id = ?");
        $stmtExisting->execute([$schoolId]);
        $existingUsers = $stmtExisting->fetchAll(PDO::FETCH_ASSOC);

        $existingByCode = [];
        $existingByName = [];
        foreach ($existingUsers as $eu) {
            if (!empty($eu['user_code'])) {
                $existingByCode[strtolower(trim($eu['user_code']))] = $eu;
            }
            if (!empty($eu['full_name'])) {
                $existingByName[strtolower(trim($eu['full_name']))] = $eu;
            }
        }

        $seenInFileCodes = [];
        $seenInFileNames = [];

        foreach ($rows as $index => $row) {
            $name = trim($row['full_name'] ?? '');
            $code = trim($row['user_code'] ?? '');
            $dept = trim($row['department'] ?? 'Academics');

            if (empty($name) || empty($code)) continue;

            $lowerName = strtolower($name);
            $lowerCode = strtolower($code);

            $matchByCode = $existingByCode[$lowerCode] ?? null;
            $matchByName = $existingByName[$lowerName] ?? null;

            $inFileCodeRow = $seenInFileCodes[$lowerCode] ?? null;
            $inFileNameRow = $seenInFileNames[$lowerName] ?? null;

            $hasConflict = false;
            $conflictDetails = [];

            if ($inFileCodeRow) {
                $hasConflict = true;
                $conflictDetails = [
                    'type' => 'in_file_duplicate',
                    'message' => "Reg Code ({$code}) is duplicated within this file (Line #{$inFileCodeRow})."
                ];
            } else if ($inFileNameRow) {
                $hasConflict = true;
                $conflictDetails = [
                    'type' => 'in_file_duplicate',
                    'message' => "Name '{$name}' is duplicated within this file (Line #{$inFileNameRow})."
                ];
            } else if ($matchByCode && $matchByName && $matchByCode['id'] === $matchByName['id']) {
                $hasConflict = true;
                $conflictDetails = [
                    'type' => 'exact_user_exists',
                    'existing_id' => $matchByCode['id'],
                    'existing_name' => $matchByCode['full_name'],
                    'existing_code' => $matchByCode['user_code'],
                    'message' => "An account with Reg Code ({$code}) and Name '{$name}' already exists in the system."
                ];
            } else if ($matchByCode) {
                $hasConflict = true;
                $conflictDetails = [
                    'type' => 'code_conflict',
                    'existing_id' => $matchByCode['id'],
                    'existing_name' => $matchByCode['full_name'],
                    'existing_code' => $matchByCode['user_code'],
                    'message' => "Reg Code ({$code}) is already used by '{$matchByCode['full_name']}'."
                ];
            } else if ($matchByName) {
                $hasConflict = true;
                $conflictDetails = [
                    'type' => 'name_conflict',
                    'existing_id' => $matchByName['id'],
                    'existing_name' => $matchByName['full_name'],
                    'existing_code' => $matchByName['user_code'],
                    'message' => "Name '{$name}' matches existing user with Reg Code ({$matchByName['user_code']})."
                ];
            }

            if (!$inFileCodeRow) $seenInFileCodes[$lowerCode] = $index + 1;
            if (!$inFileNameRow) $seenInFileNames[$lowerName] = $index + 1;

            $parsedRow = [
                'row_index' => $index + 1,
                'full_name' => $name,
                'user_code' => $code,
                'department' => $dept,
                'has_conflict' => $hasConflict,
                'conflict' => $conflictDetails
            ];

            $parsedRows[] = $parsedRow;
            if ($hasConflict) {
                $conflicts[] = $parsedRow;
            }
        }

        echo json_encode([
            'success' => true,
            'role' => $role,
            'total_rows' => count($parsedRows),
            'conflict_count' => count($conflicts),
            'parsed_rows' => $parsedRows,
            'conflicts' => $conflicts
        ]);
        exit();
    }

    // 1b. GET: List Classrooms for Allocation Selection
    if ($method === 'GET' && $action === 'list_classrooms') {
        $stmtC = $conn->prepare("
            SELECT c.id, c.classroom_name, c.capacity, g.name AS grade_name,
                   (SELECT COUNT(*) FROM student_classroom_allocations sca WHERE sca.classroom_id = c.id AND sca.status = 'Active') AS filled_count
            FROM classrooms c
            JOIN grades g ON c.grade_id = g.id
            WHERE c.school_id = ?
            ORDER BY g.order_seq ASC, c.classroom_name ASC
        ");
        $stmtC->execute([$schoolId]);
        $classrooms = $stmtC->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'classrooms' => $classrooms,
            'count' => count($classrooms)
        ]);
        exit();
    }

    // 3. POST: Execute Import Batch
    if ($method === 'POST' && $action === 'execute_import') {
        $role = $input['role'] ?? 'teacher';
        $rows = $input['rows'] ?? [];
        $duplicateHandling = $input['duplicate_handling'] ?? 'skip'; // 'skip' or 'update'
        $classroomId = intval($input['classroom_id'] ?? 0);

        if (empty($rows)) {
            echo json_encode(['success' => false, 'message' => 'No rows provided for execution.']);
            exit();
        }

        $conn->beginTransaction();

        $insertedCount = 0;
        $updatedCount  = 0;
        $skippedCount  = 0;
        $allocatedCount = 0;

        $stmtIns = $conn->prepare("
            INSERT INTO users (id, school_id, user_code, full_name, department, password_hash, is_password_changed, first_login_completed, role, status)
            VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, 'active')
        ");

        $stmtUpd = $conn->prepare("
            UPDATE users SET full_name = ?, department = ?, updated_at = NOW()
            WHERE id = ? AND school_id = ?
        ");

        $stmtAlloc = $conn->prepare("
            INSERT INTO student_classroom_allocations (school_id, academic_year, student_id, classroom_id, status)
            VALUES (?, '" . date('Y') . "', ?, ?, 'Active')
            ON DUPLICATE KEY UPDATE classroom_id = VALUES(classroom_id), status = 'Active', updated_at = NOW()
        ");

        $stmtFindCode = $conn->prepare("SELECT id FROM users WHERE school_id = ? AND LOWER(user_code) = LOWER(?) LIMIT 1");
        $stmtFindName = $conn->prepare("SELECT id FROM users WHERE school_id = ? AND LOWER(full_name) = LOWER(?) LIMIT 1");

        foreach ($rows as $r) {
            $name = trim($r['full_name'] ?? '');
            $code = trim($r['user_code'] ?? '');
            $dept = trim($r['department'] ?? 'Academics');

            if (empty($name) || empty($code)) {
                $skippedCount++;
                continue;
            }

            // Check if existing user
            $existingId = null;
            $stmtFindCode->execute([$schoolId, $code]);
            $existingId = $stmtFindCode->fetchColumn();

            if (!$existingId) {
                $stmtFindName->execute([$schoolId, $name]);
                $existingId = $stmtFindName->fetchColumn();
            }

            $targetStudentId = null;

            if ($existingId) {
                if ($duplicateHandling === 'update') {
                    $stmtUpd->execute([$name, $dept, $existingId, $schoolId]);
                    $updatedCount++;
                    $targetStudentId = $existingId;
                } else {
                    $skippedCount++;
                }
            } else {
                // New User Creation
                $userId = generateUuid();
                $initialPasswordHash = password_hash($code, PASSWORD_BCRYPT);

                $stmtIns->execute([$userId, $schoolId, $code, $name, $dept, $initialPasswordHash, $role]);
                $insertedCount++;
                $targetStudentId = $userId;
            }

            // If classroom selected & role is student, allocate student to classroom
            if ($targetStudentId && $role === 'student' && $classroomId > 0) {
                $stmtAlloc->execute([$schoolId, $targetStudentId, $classroomId]);
                $allocatedCount++;
            }
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'role' => $role,
            'summary' => [
                'total_processed' => count($rows),
                'inserted'        => $insertedCount,
                'updated'         => $updatedCount,
                'skipped'         => $skippedCount,
                'allocated'       => $allocatedCount
            ],
            'message' => "Import complete: {$insertedCount} account(s) created, {$updatedCount} updated, {$skippedCount} skipped."
        ]);
        exit();
    }

    // 4. GET: List Classrooms for active tenant school & academic year
    if ($method === 'GET' && $action === 'list_classrooms') {
        $year = $_GET['year'] ?? date('Y');
        $stmtC = $conn->prepare("
            SELECT c.id, CONCAT(g.name, ' - ', c.classroom_name) AS classroom_name, c.grade_id 
            FROM classrooms c
            JOIN grades g ON c.grade_id = g.id
            WHERE c.school_id = ? AND c.academic_year = ? AND c.is_active = 1
            ORDER BY g.id ASC, c.classroom_name ASC
        ");
        $stmtC->execute([$schoolId, $year]);
        $classrooms = $stmtC->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'classrooms' => $classrooms, 'year' => $year]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown import action.']);

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
