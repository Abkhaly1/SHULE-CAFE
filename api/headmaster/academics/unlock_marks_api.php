<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['headmaster', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access Restricted: Only Headmasters or Super Admins can override score locks.']);
    exit();
}

$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId && $role === 'super_admin') {
    $row = $conn->query('SELECT id FROM schools LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $schoolId = $row['id'] ?? null;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';
$year = $input['year'] ?? date('Y');
$term = $input['term'] ?? 'Term 1';
$classroomId = intval($input['classroom_id'] ?? 0);
$subjectCode = $input['subject_code'] ?? '';

try {
    if ($action === 'unlock') {
        if (!$classroomId || empty($subjectCode)) {
            echo json_encode(['success' => false, 'message' => 'classroom_id and subject_code are required.']);
            exit();
        }

        $stmt = $conn->prepare("
            INSERT INTO marks_entry_locks (school_id, academic_year, term, classroom_id, subject_code, is_locked, unlocked_by, updated_at)
            VALUES (?, ?, ?, ?, ?, 0, ?, NOW())
            ON DUPLICATE KEY UPDATE is_locked = 0, unlocked_by = VALUES(unlocked_by), updated_at = NOW()
        ");
        $stmt->execute([$schoolId, $year, $term, $classroomId, $subjectCode, $_SESSION['user_id']]);

        echo json_encode(['success' => true, 'message' => 'Score sheet unlocked successfully for teacher edit window.']);
        exit();
    }

    if ($action === 'list_locks') {
        $stmt = $conn->prepare("
            SELECT mel.id, mel.academic_year, mel.term, mel.classroom_id, mel.subject_code, mel.is_locked, mel.locked_at,
                   c.classroom_name, u.full_name AS locked_by_name
            FROM marks_entry_locks mel
            JOIN classrooms c ON mel.classroom_id = c.id
            LEFT JOIN users u ON mel.locked_by = u.id
            WHERE mel.school_id = ? AND mel.academic_year = ? AND mel.term = ? AND mel.is_locked = 1
            ORDER BY c.classroom_name ASC, mel.subject_code ASC
        ");
        $stmt->execute([$schoolId, $year, $term]);
        echo json_encode(['success' => true, 'locks' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
