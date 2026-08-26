<?php

class GradingManager {
    private $db;

    public function __construct(PDO $databaseConnection) {
        $this->db = $databaseConnection;
    }

    /**
     * 1. Kutafuta Grade ya somo moja kwa kutumia Alama (Marks)
     */
    public function getSubjectGrade($levelType, $mark) {
        $mark = max(0, min(100, (int)$mark));
        $stmt = $this->db->prepare("
            SELECT grade, points, remark 
            FROM grading_scales 
            WHERE level_type = :level_type 
            AND :mark BETWEEN min_mark AND max_mark 
            LIMIT 1
        ");
        $stmt->execute([
            ':level_type' => $levelType,
            ':mark' => $mark
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            // Fallback for unexpected scale gap
            return [
                'grade' => 'F',
                'points' => 7,
                'remark' => 'Fail'
            ];
        }

        return $result;
    }

    /**
     * 2. Kutafuta Division ya Jumla kwa kutumia Total Points zilizopatikana
     */
    public function calculateDivision($levelType, $totalPoints) {
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

        if (!$result) {
            return [
                'division_name' => 'Division 0',
                'remark' => 'Fail'
            ];
        }

        return $result;
    }

    /**
     * 3. Overall Student Division Calculator with Full Business Rules (Top 7 for O-Level, Top 3 for A-Level)
     */
    public function calculateStudentPerformance($levelType, array $subjectMarks) {
        $evaluatedSubjects = [];
        $passCountDOrBetter = 0; // O-Level rule: At least 2 D grades or better
        $principalPassCount = 0; // A-Level rule: Principal passes (Grade A to E)

        foreach ($subjectMarks as $subjectCode => $mark) {
            $gradeData = $this->getSubjectGrade($levelType, $mark);
            $evaluated = [
                'subject' => $subjectCode,
                'mark' => (int)$mark,
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

        // Sort subjects by points ASC (Best performance first: 1 pt is better than 4 pts)
        usort($evaluatedSubjects, function($a, $b) {
            return $a['points'] <=> $b['points'];
        });

        $totalPoints = 0;
        $bestSubjects = [];

        if ($levelType === 'A-Level') {
            // A-Level: Take top 3 principal combination subjects
            $bestSubjects = array_slice($evaluatedSubjects, 0, 3);
            foreach ($bestSubjects as $s) {
                $totalPoints += $s['points'];
            }

            $rawDivision = $this->calculateDivision('A-Level', $totalPoints);
            $finalDivisionName = $rawDivision['division_name'];
            $remark = $rawDivision['remark'];

            // A-Level Business Logic Rule: Must have 3 Principal Passes (A-E) for Div I, II, III
            if (in_array($finalDivisionName, ['Division I', 'Division II', 'Division III'])) {
                if ($principalPassCount < 3) {
                    // Downgrade to Division IV or Division 0 if principal passes requirement not met
                    $finalDivisionName = ($totalPoints <= 19) ? 'Division IV' : 'Division 0';
                    $remark = ($finalDivisionName === 'Division IV') ? 'Pass (Subsidiary/Fail Penalty)' : 'Fail';
                }
            }

        } else {
            // O-Level: Take top 7 best subjects
            $bestSubjects = array_slice($evaluatedSubjects, 0, 7);
            foreach ($bestSubjects as $s) {
                $totalPoints += $s['points'];
            }

            $rawDivision = $this->calculateDivision('O-Level', $totalPoints);
            $finalDivisionName = $rawDivision['division_name'];
            $remark = $rawDivision['remark'];

            // O-Level Business Logic Rule: Must have at least two 'D' grades or better for Div I, II, III
            if (in_array($finalDivisionName, ['Division I', 'Division II', 'Division III'])) {
                if ($passCountDOrBetter < 2) {
                    $finalDivisionName = ($totalPoints <= 34) ? 'Division IV' : 'Division 0';
                    $remark = 'Pass (Minimum 2 D Grades Requirement Not Met)';
                }
            }
        }

        return [
            'level_type' => $levelType,
            'total_points' => $totalPoints,
            'division' => $finalDivisionName,
            'remark' => $remark,
            'top_subjects_count' => count($bestSubjects),
            'all_subjects' => $evaluatedSubjects,
            'best_subjects' => $bestSubjects
        ];
    }
}
?>
