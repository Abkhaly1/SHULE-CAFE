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
    if ($method === 'GET' && ($action === 'get_full_structure' || $action === 'compare_terms')) {
        $stmtT1 = $conn->prepare("SELECT id, name, weight_percent, is_terminal FROM assessment_types WHERE school_id = ? AND term = 'Term 1' AND is_archived = 0 ORDER BY is_terminal ASC, id ASC");
        $stmtT1->execute([$schoolId]);
        $t1 = $stmtT1->fetchAll(PDO::FETCH_ASSOC);

        $stmtT2 = $conn->prepare("SELECT id, name, weight_percent, is_terminal FROM assessment_types WHERE school_id = ? AND term = 'Term 2' AND is_archived = 0 ORDER BY is_terminal ASC, id ASC");
        $stmtT2->execute([$schoolId]);
        $t2 = $stmtT2->fetchAll(PDO::FETCH_ASSOC);

        if (empty($t1)) {
            $t1 = [
                ['id' => null, 'name' => 'First Term Exam', 'weight_percent' => 100.00, 'is_terminal' => 1]
            ];
        }

        if (empty($t2)) {
            $t2 = [
                ['id' => null, 'name' => 'Second Term Exam', 'weight_percent' => 100.00, 'is_terminal' => 1]
            ];
        }

        $sumT1 = 0; foreach ($t1 as $r) $sumT1 += floatval($r['weight_percent']);
        $sumT2 = 0; foreach ($t2 as $r) $sumT2 += floatval($r['weight_percent']);

        echo json_encode([
            'success' => true,
            'year' => $year,
            'term1' => [
                'term' => 'Term 1',
                'categories' => $t1,
                'total_weight' => round($sumT1, 2),
                'is_valid' => (round($sumT1, 2) === 100.00)
            ],
            'term2' => [
                'term' => 'Term 2',
                'categories' => $t2,
                'total_weight' => round($sumT2, 2),
                'is_valid' => (round($sumT2, 2) === 100.00)
            ],
            'national_profiles' => [
                'primary' => [
                    'title' => 'Primary School Profile (Standard 1–7)',
                    'internal_cycle' => 'Term 1 (100%) & Term 2 (100%) Standalone Reports',
                    'national_milestones' => 'Standard 4 (SFNA) & Standard 7 (PSLE)'
                ],
                'o_level' => [
                    'title' => 'O-Level Secondary Profile (Form 1–4)',
                    'internal_cycle' => 'Form 1–4 Routine Terms (100%) Local Reports',
                    'national_milestones' => 'Form 1 Bypassed • Form 2 FTNA (20%), Form 3 Annual (30%), Form 4 Mock (40%), Project Portfolio (10%)'
                ],
                'a_level' => [
                    'title' => 'A-Level Secondary Profile (Form 5–6)',
                    'internal_cycle' => 'Form 5 & Form 6 Routine Terms (100%) Local Reports',
                    'national_milestones' => 'Form 5 T1 (10%), Form 5 Annual (20%), Form 6 T1 (20%), Form 6 Mock (40%), Project (10%)'
                ]
            ]
        ]);
        exit();
    }

    if ($method === 'POST' && $action === 'save_all_terms') {
        $term1Categories = $input['term1_categories'] ?? [];
        $term2Categories = $input['term2_categories'] ?? [];

        if (empty($term1Categories) || empty($term2Categories)) {
            echo json_encode(['success' => false, 'message' => 'Categories for both Term 1 and Term 2 are required.']);
            exit();
        }

        $sumT1 = 0; foreach ($term1Categories as $c) $sumT1 += floatval($c['weight_percent'] ?? 0);
        $sumT2 = 0; foreach ($term2Categories as $c) $sumT2 += floatval($c['weight_percent'] ?? 0);

        if (round($sumT1, 2) !== 100.00 || round($sumT2, 2) !== 100.00) {
            echo json_encode([
                'success' => false,
                'message' => "Invalid policy: Both Term 1 (sum: " . round($sumT1, 2) . "%) and Term 2 (sum: " . round($sumT2, 2) . "%) must equal 100% total weight."
            ]);
            exit();
        }

        $conn->beginTransaction();

        // Clear existing unarchived assessment types for both terms
        $stmtDel = $conn->prepare("DELETE FROM assessment_types WHERE school_id = ? AND academic_year = ? AND term IN ('Term 1', 'Term 2')");
        $stmtDel->execute([$schoolId, $year]);

        $stmtIns = $conn->prepare("
            INSERT INTO assessment_types (school_id, academic_year, term, name, weight_percent, is_terminal, is_archived)
            VALUES (?, ?, ?, ?, ?, ?, 0)
        ");

        $saved = 0;
        foreach ($term1Categories as $c) {
            $name = trim($c['name'] ?? '');
            if (empty($name)) continue;
            $stmtIns->execute([$schoolId, $year, 'Term 1', $name, floatval($c['weight_percent'] ?? 0), !empty($c['is_terminal']) ? 1 : 0]);
            $saved++;
        }

        foreach ($term2Categories as $c) {
            $name = trim($c['name'] ?? '');
            if (empty($name)) continue;
            $stmtIns->execute([$schoolId, $year, 'Term 2', $name, floatval($c['weight_percent'] ?? 0), !empty($c['is_terminal']) ? 1 : 0]);
            $saved++;
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'saved_count' => $saved,
            'message' => "Full annual assessment policy matrix successfully saved for both Term 1 and Term 2 (100% load validated)."
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

        // Default profile if empty - Standard First / Second Term Exam (100%)
        if (empty($types)) {
            $examName = ($term === 'Term 2') ? 'Second Term Exam' : 'First Term Exam';
            $types = [
                ['id' => null, 'name' => $examName, 'weight_percent' => 100.00, 'is_terminal' => 1]
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
