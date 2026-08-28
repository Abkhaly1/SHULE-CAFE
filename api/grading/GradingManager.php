<?php
/**
 * ====================================================================
 * SHULE CAFE - PROPRIETARY ACADEMIC GRADING & DIVISION ENGINE
 * Copyright (c) 2026 SHULE CAFE. All Rights Reserved.
 * 100% English Directive Enforced
 * ====================================================================
 */

class GradingManager {
    private $db;

    public function __construct(PDO $databaseConnection) {
        $this->db = $databaseConnection;
    }

    /**
     * Normalize education level type string to standard keys
     */
    public function normalizeLevelType($levelType) {
        $str = strtolower(trim($levelType ?? ''));
        if (stripos($str, 'primary') !== false || stripos($str, 'std') !== false) {
            return 'Primary';
        }
        if (stripos($str, 'a-level') !== false || stripos($str, 'advanced') !== false || stripos($str, 'high') !== false || stripos($str, 'form 5') !== false || stripos($str, 'form 6') !== false) {
            return 'A-Level';
        }
        if (stripos($str, 'nursery') !== false || stripos($str, 'kindergarten') !== false || stripos($str, 'pre') !== false) {
            return 'Nursery';
        }
        return 'O-Level';
    }

    /**
     * 1. Get single subject grade, points, and remark from score
     */
    public function getSubjectGrade($levelType, $mark) {
        $levelType = $this->normalizeLevelType($levelType);
        $mark = max(0, min(100, floatval($mark)));

        try {
            $stmt = $this->db->prepare("
                SELECT grade, points, remark 
                FROM grading_scales 
                WHERE level_type = :level_type 
                AND :mark BETWEEN min_mark AND max_mark 
                ORDER BY min_mark DESC
                LIMIT 1
            ");
            $stmt->execute([
                ':level_type' => $levelType,
                ':mark' => $mark
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return [
                    'grade' => $result['grade'],
                    'points' => (int)$result['points'],
                    'remark' => $result['remark']
                ];
            }
        } catch (Exception $e) {}

        // National Fallback Default Scales
        if ($levelType === 'A-Level') {
            if ($mark >= 80) return ['grade' => 'A', 'points' => 1, 'remark' => 'Excellent'];
            if ($mark >= 70) return ['grade' => 'B', 'points' => 2, 'remark' => 'Very Good'];
            if ($mark >= 60) return ['grade' => 'C', 'points' => 3, 'remark' => 'Good'];
            if ($mark >= 50) return ['grade' => 'D', 'points' => 4, 'remark' => 'Satisfactory'];
            if ($mark >= 40) return ['grade' => 'E', 'points' => 5, 'remark' => 'Sufficient'];
            if ($mark >= 35) return ['grade' => 'S', 'points' => 6, 'remark' => 'Subsidiary'];
            return ['grade' => 'F', 'points' => 7, 'remark' => 'Fail'];
        } elseif ($levelType === 'Primary') {
            if ($mark >= 81) return ['grade' => 'A', 'points' => 1, 'remark' => 'Excellent'];
            if ($mark >= 61) return ['grade' => 'B', 'points' => 2, 'remark' => 'Very Good'];
            if ($mark >= 41) return ['grade' => 'C', 'points' => 3, 'remark' => 'Average Pass'];
            if ($mark >= 21) return ['grade' => 'D', 'points' => 4, 'remark' => 'Marginal Pass'];
            return ['grade' => 'E', 'points' => 5, 'remark' => 'Fail'];
        } elseif ($levelType === 'Nursery') {
            if ($mark >= 80) return ['grade' => 'A', 'points' => 1, 'remark' => 'Excellent'];
            if ($mark >= 60) return ['grade' => 'B', 'points' => 2, 'remark' => 'Good'];
            if ($mark >= 40) return ['grade' => 'C', 'points' => 3, 'remark' => 'Satisfactory'];
            return ['grade' => 'D', 'points' => 4, 'remark' => 'Needs Improvement'];
        } else {
            // O-Level
            if ($mark >= 75) return ['grade' => 'A', 'points' => 1, 'remark' => 'Excellent'];
            if ($mark >= 65) return ['grade' => 'B', 'points' => 2, 'remark' => 'Very Good'];
            if ($mark >= 45) return ['grade' => 'C', 'points' => 3, 'remark' => 'Good'];
            if ($mark >= 30) return ['grade' => 'D', 'points' => 4, 'remark' => 'Satisfactory'];
            return ['grade' => 'F', 'points' => 5, 'remark' => 'Fail'];
        }
    }

    /**
     * 2. Calculate Division from total aggregate points
     */
    public function calculateDivision($levelType, $totalPoints) {
        $levelType = $this->normalizeLevelType($levelType);
        $totalPoints = (int)$totalPoints;

        try {
            $stmt = $this->db->prepare("
                SELECT division_name, remark 
                FROM division_scales 
                WHERE level_type = :level_type 
                AND :total_points BETWEEN min_points AND max_points 
                LIMIT 1
            ");
            $stmt->execute([
                ':level_type' => $levelType,
                ':total_points' => $totalPoints
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return $result;
            }
        } catch (Exception $e) {}

        // Fallback Default Division Maps
        if ($levelType === 'A-Level') {
            if ($totalPoints >= 3 && $totalPoints <= 9) return ['division_name' => 'Division I', 'remark' => 'Distinction'];
            if ($totalPoints >= 10 && $totalPoints <= 12) return ['division_name' => 'Division II', 'remark' => 'Merit'];
            if ($totalPoints >= 13 && $totalPoints <= 17) return ['division_name' => 'Division III', 'remark' => 'Credit'];
            if ($totalPoints >= 18 && $totalPoints <= 19) return ['division_name' => 'Division IV', 'remark' => 'Pass'];
            return ['division_name' => 'Division 0', 'remark' => 'Fail'];
        } elseif ($levelType === 'Primary') {
            if ($totalPoints >= 5 && $totalPoints <= 10) return ['division_name' => 'Grade A', 'remark' => 'High Distinction'];
            if ($totalPoints >= 11 && $totalPoints <= 15) return ['division_name' => 'Grade B', 'remark' => 'Distinction'];
            if ($totalPoints >= 16 && $totalPoints <= 20) return ['division_name' => 'Grade C', 'remark' => 'Average Pass'];
            if ($totalPoints >= 21 && $totalPoints <= 25) return ['division_name' => 'Grade D', 'remark' => 'Marginal Pass'];
            return ['division_name' => 'Grade E', 'remark' => 'Fail'];
        } elseif ($levelType === 'Nursery') {
            return ['division_name' => 'Pass', 'remark' => 'Satisfactory Performance'];
        } else {
            // O-Level (NECTA standard: 7-17 Div I, 18-21 Div II, 22-25 Div III, 26-34 Div IV, 35+ Div 0)
            if ($totalPoints >= 7 && $totalPoints <= 17) return ['division_name' => 'Division I', 'remark' => 'Distinction'];
            if ($totalPoints >= 18 && $totalPoints <= 21) return ['division_name' => 'Division II', 'remark' => 'Merit'];
            if ($totalPoints >= 22 && $totalPoints <= 25) return ['division_name' => 'Division III', 'remark' => 'Credit'];
            if ($totalPoints >= 26 && $totalPoints <= 34) return ['division_name' => 'Division IV', 'remark' => 'Pass'];
            return ['division_name' => 'Division 0', 'remark' => 'Fail'];
        }
    }

    /**
     * 3. Comprehensive Performance & Division Calculation
     * Applies official curriculum rules:
     * - O-Level: Best 7 subjects
     * - A-Level: Best 3 Principal subjects
     * - Primary: Best 5 subjects
     * - Nursery: All subjects average
     */
    public function calculateStudentPerformance($levelType, array $subjectMarks) {
        $levelType = $this->normalizeLevelType($levelType);
        $evaluatedSubjects = [];
        $passCountDOrBetter = 0; // O-Level: passes of Grade D or better (A, B, C, D)
        $principalPassCount = 0; // A-Level: Principal passes (A, B, C, D, E)
        $totalScoreSum = 0.0;

        foreach ($subjectMarks as $subjectCode => $mark) {
            if ($mark === null || $mark === '') continue;
            $markVal = floatval($mark);
            $totalScoreSum += $markVal;

            $gradeData = $this->getSubjectGrade($levelType, $markVal);
            $evaluated = [
                'subject' => $subjectCode,
                'mark' => $markVal,
                'grade' => $gradeData['grade'],
                'points' => (int)$gradeData['points'],
                'remark' => $gradeData['remark']
            ];

            // Count O-Level passes (A, B, C, D)
            if (in_array($gradeData['grade'], ['A', 'B', 'C', 'D'])) {
                $passCountDOrBetter++;
            }

            // Count A-Level Principal passes (A, B, C, D, E)
            if (in_array($gradeData['grade'], ['A', 'B', 'C', 'D', 'E'])) {
                $principalPassCount++;
            }

            $evaluatedSubjects[] = $evaluated;
        }

        $subCount = count($evaluatedSubjects);
        $averageScore = $subCount > 0 ? round($totalScoreSum / $subCount, 2) : 0.0;

        if ($subCount === 0) {
            return [
                'level_type' => $levelType,
                'total_points' => 0,
                'division' => '-',
                'remark' => 'No recorded evaluation',
                'average_score' => 0,
                'top_subjects_count' => 0,
                'all_subjects' => [],
                'best_subjects' => []
            ];
        }

        // Sort subjects by points ASC (Best performance first: 1 pt is better than 5 pts)
        usort($evaluatedSubjects, function($a, $b) {
            if ($a['points'] === $b['points']) {
                return $b['mark'] <=> $a['mark']; // Tie-breaker: higher mark first
            }
            return $a['points'] <=> $b['points'];
        });

        $totalPoints = 0;
        $bestSubjects = [];

        if ($levelType === 'A-Level') {
            // A-Level: Take top 3 principal combination subjects
            $topN = min(3, $subCount);
            $bestSubjects = array_slice($evaluatedSubjects, 0, $topN);
            foreach ($bestSubjects as $s) {
                $totalPoints += $s['points'];
            }

            $rawDivision = $this->calculateDivision('A-Level', $totalPoints);
            $finalDivisionName = $rawDivision['division_name'];
            $remark = $rawDivision['remark'];

            // A-Level Business Logic: Requires 3 Principal Passes (A-E) for Div I, II, III
            if (in_array($finalDivisionName, ['Division I', 'Division II', 'Division III'])) {
                if ($principalPassCount < 3) {
                    $finalDivisionName = ($totalPoints <= 19 && $principalPassCount >= 1) ? 'Division IV' : 'Division 0';
                    $remark = ($finalDivisionName === 'Division IV') ? 'Pass (Subsidiary Penalty)' : 'Fail';
                }
            }

        } elseif ($levelType === 'Primary') {
            // Primary: Take top 5 core subjects
            $topN = min(5, $subCount);
            $bestSubjects = array_slice($evaluatedSubjects, 0, $topN);
            foreach ($bestSubjects as $s) {
                $totalPoints += $s['points'];
            }

            $rawDivision = $this->calculateDivision('Primary', $totalPoints);
            $finalDivisionName = $rawDivision['division_name'];
            $remark = $rawDivision['remark'];

        } elseif ($levelType === 'Nursery') {
            $bestSubjects = $evaluatedSubjects;
            $finalDivisionName = ($averageScore >= 40) ? 'Pass' : 'Needs Support';
            $remark = ($averageScore >= 80) ? 'Excellent Progress' : (($averageScore >= 60) ? 'Good Progress' : 'Developing');

        } else {
            // O-Level: Take top 7 best subjects (NECTA Standard)
            $topN = min(7, $subCount);
            $bestSubjects = array_slice($evaluatedSubjects, 0, $topN);
            foreach ($bestSubjects as $s) {
                $totalPoints += $s['points'];
            }

            // If student sat for fewer than 7 subjects (e.g. 5 subjects), scale up minimum requirements
            if ($subCount < 7) {
                // Pad missing subjects with 5 points (Fail) per missing subject to reflect 7-subject standard
                $missingCount = 7 - $subCount;
                $totalPoints += ($missingCount * 5);
            }

            $rawDivision = $this->calculateDivision('O-Level', $totalPoints);
            $finalDivisionName = $rawDivision['division_name'];
            $remark = $rawDivision['remark'];

            // O-Level Business Logic: Must have at least two 'D' passes or better for Div I, II, III
            if (in_array($finalDivisionName, ['Division I', 'Division II', 'Division III'])) {
                if ($passCountDOrBetter < 2) {
                    $finalDivisionName = ($totalPoints <= 34) ? 'Division IV' : 'Division 0';
                    $remark = 'Pass (Minimum 2 D Passes Requirement Not Met)';
                }
            }
        }

        return [
            'level_type' => $levelType,
            'total_points' => $totalPoints,
            'division' => $finalDivisionName,
            'remark' => $remark,
            'average_score' => $averageScore,
            'total_evaluated_marks' => $totalScoreSum,
            'top_subjects_count' => count($bestSubjects),
            'all_subjects' => $evaluatedSubjects,
            'best_subjects' => $bestSubjects,
            'passed_count' => $passCountDOrBetter
        ];
    }
}
?>
