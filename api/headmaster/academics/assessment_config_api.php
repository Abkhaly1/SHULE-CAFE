<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db.php';

$userId = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;
$schoolId = $_SESSION['school_id'] ?? $_GET['school_id'] ?? null;

if (empty($schoolId) && !empty($userId)) {
    $uStmt = $conn->prepare("SELECT school_id FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$userId]);
    $schoolId = $uStmt->fetchColumn() ?: null;
}
if (empty($schoolId)) {
    $sStmt = $conn->query("SELECT id FROM schools ORDER BY id ASC LIMIT 1");
    $schoolId = $sStmt->fetchColumn() ?: null;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';
$year = $_GET['year'] ?? $input['year'] ?? date('Y');
$term = $_GET['term'] ?? $input['term'] ?? 'Term 1';

try {
    if ($method === 'GET' && $action === 'compare_terms') {
        $stmtT1 = $conn->prepare("SELECT name, weight_percent, is_terminal FROM assessment_types WHERE school_id = ? AND term = 'Term 1' ORDER BY is_terminal ASC, name ASC");
        $stmtT1->execute([$schoolId]);
        $t1 = $stmtT1->fetchAll(PDO::FETCH_ASSOC);

        $stmtT2 = $conn->prepare("SELECT name, weight_percent, is_terminal FROM assessment_types WHERE school_id = ? AND term = 'Term 2' ORDER BY is_terminal ASC, name ASC");
        $stmtT2->execute([$schoolId]);
        $t2 = $stmtT2->fetchAll(PDO::FETCH_ASSOC);

        $formatSummary = function($rows) {
            if (empty($rows)) return "Default (30.00% CA + 70.00% Terminal)";
            $parts = [];
            foreach ($rows as $r) {
                $parts[] = floatval($r['weight_percent']) . "% " . $r['name'];
            }
            return implode(" + ", $parts);
        };

        $t1Sum = $formatSummary($t1);
        $t2Sum = $formatSummary($t2);
        $isIdentical = (!empty($t1) && !empty($t2) && json_encode($t1) === json_encode($t2));

        echo json_encode([
            'success' => true,
            'term1' => ['types' => $t1, 'summary' => $t1Sum, 'has_policy' => !empty($t1)],
            'term2' => ['types' => $t2, 'summary' => $t2Sum, 'has_policy' => !empty($t2)],
            'is_identical' => $isIdentical
        ]);
        exit();
    }

    if ($method === 'GET') {
        // Fetch latest established policy for this term across all years (Global Policy Inheritance)
        $stmt = $conn->prepare("
            SELECT id, name, weight_percent, is_terminal, academic_year, created_at
            FROM assessment_types
            WHERE school_id = ? AND term = ?
            ORDER BY academic_year DESC, created_at DESC, is_terminal ASC, name ASC
        ");
        $stmt->execute([$schoolId, $term]);
        $allTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $types = [];
        $hasSaved = false;
        if (!empty($allTypes)) {
            $hasSaved = true;
            $latestYear = $allTypes[0]['academic_year'];
            foreach ($allTypes as $t) {
                if ($t['academic_year'] === $latestYear) {
                    $types[] = $t;
                }
            }
        }

        // Calculate total weight
        $totalWeight = 0;
        foreach ($types as $t) {
            $totalWeight += floatval($t['weight_percent']);
        }

        // Default profile if empty
        if (empty($types)) {
            $types = [
                ['id' => null, 'name' => 'Continuous Assessment (CA)', 'weight_percent' => 30.00, 'is_terminal' => 0],
                ['id' => null, 'name' => 'Terminal Exam', 'weight_percent' => 70.00, 'is_terminal' => 1]
            ];
            $totalWeight = 100.00;
        }

        echo json_encode([
            'success' => true,
            'year' => $year,
            'term' => $term,
            'has_saved_policy' => $hasSaved,
            'assessment_types' => $types,
            'total_weight' => round($totalWeight, 2),
            'is_valid_policy' => (round($totalWeight, 2) === 100.00)
        ]);
        exit();
    }

    if ($method === 'POST' && $action === 'save_categories') {
        $categories = $input['categories'] ?? [];
        if (empty($categories)) {
            echo json_encode(['success' => false, 'message' => 'Categories array is required.']);
            exit();
        }

        $sumWeight = 0;
        foreach ($categories as $c) {
            $sumWeight += floatval($c['weight_percent'] ?? 0);
        }

        if (round($sumWeight, 2) !== 100.00) {
            echo json_encode([
                'success' => false,
                'message' => "Invalid policy: Total weight load must equal 100%. Current sum: " . round($sumWeight, 2) . "%."
            ]);
            exit();
        }

        $conn->beginTransaction();
        
        // Check if there are existing marks for this term and year
        $stmtCheckMarks = $conn->prepare("
            SELECT COUNT(*) FROM marks_entry_dynamic 
            WHERE school_id = ? AND academic_year = ? AND term = ?
        ");
        $stmtCheckMarks->execute([$schoolId, $year, $term]);
        $hasExistingMarks = (int)$stmtCheckMarks->fetchColumn() > 0;
        
        $warningMessage = "";

        if ($hasExistingMarks) {
            // Soft delete (archive) existing types to preserve historical scores
            $stmtArchive = $conn->prepare("UPDATE assessment_types SET is_archived = 1 WHERE school_id = ? AND academic_year = ? AND term = ?");
            $stmtArchive->execute([$schoolId, $year, $term]);
            $warningMessage = " However, because marks were already entered for this term, the old configuration was archived to preserve existing student scores.";
        } else {
            // Clear existing for this year and term (no marks entered yet, safe to hard delete)
            $stmtDel = $conn->prepare("DELETE FROM assessment_types WHERE school_id = ? AND academic_year = ? AND term = ?");
            $stmtDel->execute([$schoolId, $year, $term]);
        }

        $stmtIns = $conn->prepare("
            INSERT INTO assessment_types (school_id, academic_year, term, name, weight_percent, is_terminal, is_archived)
            VALUES (?, ?, ?, ?, ?, ?, 0)
        ");

        $saved = 0;
        foreach ($categories as $c) {
            $name = trim($c['name'] ?? '');
            if (empty($name)) continue;
            $weight = floatval($c['weight_percent'] ?? 0);
            $isTerminal = !empty($c['is_terminal']) ? 1 : 0;

            $stmtIns->execute([$schoolId, $year, $term, $name, $weight, $isTerminal]);
            $saved++;
        }

        $conn->commit();
        echo json_encode([
            'success' => true,
            'saved_count' => $saved,
            'message' => "Assessment weight policy saved successfully for $term ($year) totaling 100% load." . $warningMessage
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
