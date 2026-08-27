<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId && ($_SESSION['role'] ?? '') === 'super_admin') {
    $row = $conn->query('SELECT id FROM schools LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $schoolId = $row['id'] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$gradeId = intval($_POST['grade_id'] ?? 0);
$year    = trim($_POST['academic_year'] ?? date('Y'));

if (!$gradeId) {
    echo json_encode(['success' => false, 'message' => 'Target Grade ID is required for import.']);
    exit();
}

// Fetch grade name
$stmtG = $conn->prepare("SELECT name FROM grades WHERE id=?");
$stmtG->execute([$gradeId]);
$gradeName = $stmtG->fetchColumn() ?: "Grade #$gradeId";

if (!isset($_FILES['student_csv']) || $_FILES['student_csv']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Please select a valid CSV file to upload.']);
    exit();
}

$fileTmp = $_FILES['student_csv']['tmp_name'];
$handle = fopen($fileTmp, "r");

if ($handle === false) {
    echo json_encode(['success' => false, 'message' => 'Could not read the uploaded CSV file.']);
    exit();
}

// Read header row
$header = fgetcsv($handle, 1000, ",");

$imported = 0;
$skipped  = 0;
$errors   = [];

$conn->beginTransaction();

try {
    // Prepare student insertion query with grade_id locking
    $stmtIns = $conn->prepare("
        INSERT INTO users (id, school_id, user_code, full_name, phone, email, password_hash, role, status, grade_id)
        VALUES (:id, :school_id, :user_code, :full_name, :phone, :email, :pass_hash, 'student', 'active', :grade_id)
    ");

    $defaultPassHash = password_hash('Student@123', PASSWORD_BCRYPT);
    $rowNum = 1;

    while (($row = fgetcsv($handle, 1000, ",")) !== false) {
        $rowNum++;
        // Skip empty rows
        if (empty(array_filter($row))) continue;

        $fullName = trim($row[0] ?? '');
        if (empty($fullName)) {
            $skipped++;
            continue;
        }

        $phone = trim($row[1] ?? '');
        $email = trim($row[2] ?? '');
        $code  = trim($row[3] ?? '');

        // Auto-generate phone if empty
        if (empty($phone)) {
            $phone = '+255' . rand(700000000, 799999999);
        }

        // Auto-generate student code if empty
        if (empty($code)) {
            $seq = sprintf("%03d", rand(1, 999));
            $code = "STD/$year/$seq";
        }

        // Check if phone or email already exists to avoid duplicate constraint errors
        $chk = $conn->prepare("SELECT COUNT(*) FROM users WHERE phone=? OR (email IS NOT NULL AND email!='' AND email=?)");
        $chk->execute([$phone, $email]);
        if ($chk->fetchColumn() > 0) {
            // Generate unique phone suffix
            $phone = '+255' . rand(700000000, 799999999);
        }

        $studentId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $stmtIns->execute([
            ':id'          => $studentId,
            ':school_id'   => $schoolId,
            ':user_code'   => $code,
            ':full_name'   => $fullName,
            ':phone'       => $phone,
            ':email'       => !empty($email) ? $email : null,
            ':pass_hash'   => $defaultPassHash,
            ':grade_id'    => $gradeId
        ]);

        $imported++;
    }

    $conn->commit();
    fclose($handle);

    echo json_encode([
        'success'  => true,
        'imported' => $imported,
        'skipped'  => $skipped,
        'grade'    => $gradeName,
        'message'  => "Successfully imported $imported student(s) and locked them to the $gradeName cohort!"
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    fclose($handle);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Import error: ' . $e->getMessage()]);
}
?>
