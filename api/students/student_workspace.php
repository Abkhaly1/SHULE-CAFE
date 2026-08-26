<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/db.php';

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

$studentId     = $_GET['id'] ?? $_GET['student_id'] ?? $_SESSION['user_id'] ?? '';
$year          = $_GET['year'] ?? date('Y');
$action        = $_GET['action'] ?? 'dashboard';

// ── FAST ROUTE: my_subjects ──────────────────────────────────────────────────
// Returns the student's subjects and teacher for each, driven by class_timetables.
if ($action === 'my_subjects') {
    $sid = $_SESSION['user_id'] ?? '';
    if (empty($sid)) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    try {
        // 1. Find the student's active classroom for this year
        $stmtCR = $conn->prepare("
            SELECT c.classroom_name, c.id AS classroom_id
            FROM student_classroom_allocations sca
            JOIN classrooms c ON sca.classroom_id = c.id
            WHERE sca.student_id = ? AND sca.academic_year = ? AND sca.status = 'Active'
            LIMIT 1
        ");
        $stmtCR->execute([$sid, $year]);
        $classroom = $stmtCR->fetch(PDO::FETCH_ASSOC);

        if (!$classroom) {
            echo json_encode(['success' => true, 'classroom' => null, 'subjects' => [], 'message' => 'Not yet allocated to a classroom for this academic year.']);
            exit();
        }

        // 2. Get DISTINCT subjects taught in that classroom, with teacher info, from timetable
        $stmtSubj = $conn->prepare("
            SELECT DISTINCT
                ct.subject_code,
                COALESCE(s.name, ct.subject_code)      AS subject_name,
                COALESCE(s.level_type, 'O-Level')      AS level_type,
                COALESCE(s.is_core, 1)                  AS is_core,
                u.full_name                             AS teacher_name,
                u.id                                    AS teacher_id
            FROM class_timetables ct
            LEFT JOIN subjects s   ON (ct.subject_code = s.code AND s.school_id = ct.school_id)
            LEFT JOIN users    u   ON ct.teacher_id = u.id
            WHERE ct.school_id        = ?
              AND ct.academic_year_id = ?
              AND ct.class_stream_id  = ?
            ORDER BY subject_name ASC
        ");
        $stmtSubj->execute([$schoolId, $year, $classroom['classroom_name']]);
        $subjects = $stmtSubj->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success'   => true,
            'classroom' => $classroom['classroom_name'],
            'year'      => $year,
            'subjects'  => $subjects
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}
// ── END FAST ROUTE ───────────────────────────────────────────────────────────

if (empty($studentId)) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required.']);
    exit();
}

try {
    // 1. Fetch Student Profile & Parent Details
    $stmtProfile = $conn->prepare("
        SELECT
            u.id, u.full_name, u.gender, u.user_code, u.phone, u.email, u.status, u.created_at, u.grade_id,
            p.guardian_name, p.relation, p.guardian_phone, p.alternative_phone, p.guardian_email, p.home_address
        FROM users u
        LEFT JOIN parent_profiles p ON u.id = p.student_id AND u.school_id = p.school_id
        WHERE u.id = ? AND u.school_id = ? AND u.role = 'student'
    ");
    $stmtProfile->execute([$studentId, $schoolId]);
    $profile = $stmtProfile->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        echo json_encode(['success' => false, 'message' => 'Student profile not found.']);
        exit();
    }

    // Provide read-only fallback if parent is missing, without corrupting database
    if (empty($profile['guardian_name'])) {
        $parentSeed = [
            'guardian_name'     => 'Not Registered',
            'relation'          => '-',
            'guardian_phone'    => '-',
            'alternative_phone' => '-',
            'guardian_email'    => '-',
            'home_address'      => '-'
        ];
        $profile = array_merge($profile, $parentSeed);
    }

    // 2. Fetch Multi-Year Timeline History
    $stmtTimeline = $conn->prepare("
        SELECT
            sca.academic_year,
            sca.status AS year_status,
            c.id AS classroom_id,
            c.classroom_name,
            c.capacity,
            g.name AS grade_name,
            g.id AS grade_id
        FROM student_classroom_allocations sca
        JOIN classrooms c ON sca.classroom_id = c.id
        JOIN grades g ON c.grade_id = g.id
        WHERE sca.student_id = ? AND sca.school_id = ?
        ORDER BY sca.academic_year DESC
    ");
    $stmtTimeline->execute([$studentId, $schoolId]);
    $timeline = $stmtTimeline->fetchAll(PDO::FETCH_ASSOC);

    // Current year allocation
    $currentAlloc = null;
    foreach ($timeline as $t) {
        if ($t['academic_year'] === $year) {
            $currentAlloc = $t;
            break;
        }
    }
    if (!$currentAlloc && !empty($timeline)) {
        $currentAlloc = $timeline[0];
    }

    // 3. Fetch Assessment Types (Latest Policy)
    $stmtTypes = $conn->prepare("SELECT id, name, weight_percent, is_terminal, academic_year FROM assessment_types WHERE school_id = ? AND is_archived = 0 ORDER BY academic_year DESC, id ASC");
    $stmtTypes->execute([$schoolId]);
    $allTypes = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);
    $assessmentTypes = [];
    if (!empty($allTypes)) {
        $latestYr = $allTypes[0]['academic_year'];
        foreach ($allTypes as $t) {
            if ($t['academic_year'] === $latestYr) {
                // Distinguish terms if multiple exist for the year
                if (!empty($t['term'])) {
                    $t['name'] = $t['name'] . ' (' . $t['term'] . ')';
                }
                $assessmentTypes[] = $t;
            }
        }
    } else {
        $assessmentTypes = [
            ['id' => 'ca', 'name' => 'CA Mark', 'weight_percent' => 40],
            ['id' => 'terminal', 'name' => 'Terminal', 'weight_percent' => 60]
        ];
    }

    // Fetch Marks for Selected Academic Year from new dynamic table
    $stmtMarks = $conn->prepare("
        SELECT
            m.subject_code,
            COALESCE(s.name, m.subject_code) AS subject_name,
            m.assessment_type_id,
            m.score
        FROM marks_entry_dynamic m
        LEFT JOIN subjects s ON m.subject_code = s.code
        WHERE m.student_id = ? AND m.school_id = ? AND m.academic_year = ?
    ");
    $stmtMarks->execute([$studentId, $schoolId, $year]);
    $dynamicMarks = $stmtMarks->fetchAll(PDO::FETCH_ASSOC);

    // Group dynamic marks by subject
    $groupedMarks = [];
    foreach ($dynamicMarks as $dm) {
        $sc = $dm['subject_code'];
        if (!isset($groupedMarks[$sc])) {
            $groupedMarks[$sc] = [
                'subject_code' => $sc,
                'subject_name' => $dm['subject_name'],
                'scores' => [],
                'total_score' => 0
            ];
        }
        $groupedMarks[$sc]['scores'][$dm['assessment_type_id']] = floatval($dm['score']);
        $groupedMarks[$sc]['total_score'] += floatval($dm['score']);
    }

    $usedLegacyFallback = false;
    // Fallback to legacy marks_entry if no dynamic marks found (to preserve history)
    if (empty($groupedMarks)) {
        $usedLegacyFallback = true;
        $stmtLegacy = $conn->prepare("SELECT m.subject_code, COALESCE(s.name, m.subject_code) AS subject_name, m.continuous_assessment_mark, m.terminal_mark FROM marks_entry m LEFT JOIN subjects s ON m.subject_code = s.code WHERE m.student_id = ? AND m.school_id = ? AND m.academic_year = ?");
        $stmtLegacy->execute([$studentId, $schoolId, $year]);
        $legacyMarks = $stmtLegacy->fetchAll(PDO::FETCH_ASSOC);
        foreach ($legacyMarks as $lm) {
            $groupedMarks[$lm['subject_code']] = [
                'subject_code' => $lm['subject_code'],
                'subject_name' => $lm['subject_name'],
                'scores' => ['ca' => floatval($lm['continuous_assessment_mark']), 'terminal' => floatval($lm['terminal_mark'])],
                'total_score' => floatval($lm['continuous_assessment_mark']) + floatval($lm['terminal_mark'])
            ];
        }
    }

    if ($usedLegacyFallback) {
        $assessmentTypes = [
            ['id' => 'ca', 'name' => 'CA Mark', 'weight_percent' => 40],
            ['id' => 'terminal', 'name' => 'Terminal Exam', 'weight_percent' => 60]
        ];
    }

    // Fetch dynamic grading scale
    $levelType = 'O-Level';
    if ($currentAlloc) {
        $stmtLevel = $conn->prepare("
            SELECT el.name AS level_type
            FROM classrooms c
            JOIN grades g ON c.grade_id = g.id
            JOIN education_levels el ON g.level_id = el.id
            WHERE c.id = ? LIMIT 1
        ");
        $stmtLevel->execute([$currentAlloc['classroom_id']]);
        if ($res = $stmtLevel->fetchColumn()) {
            $levelType = $res;
        }
    }

    $stmtScales = $conn->prepare("SELECT min_mark, max_mark, grade, remark FROM grading_scales WHERE level_type = :ltype ORDER BY min_mark DESC");
    $stmtScales->execute([':ltype' => $levelType]);
    $scales = $stmtScales->fetchAll(PDO::FETCH_ASSOC);
    if (empty($scales)) {
        $stmtScales->execute([':ltype' => 'O-Level']);
        $scales = $stmtScales->fetchAll(PDO::FETCH_ASSOC);
    }

    $marks = array_values($groupedMarks);
    usort($marks, function($a, $b) { return strcmp($a['subject_name'], $b['subject_name']); });

    $totalSum = 0; $totalPoints = 0; $processedMarks = [];
    foreach ($marks as $m) {
        $score = floatval($m['total_score']);
        $totalSum += $score;

        $grade = '-';
        $remark = '-';
        $pts = 5; // Default worst points
        foreach ($scales as $idx => $sc) {
            if ($score >= floatval($sc['min_mark']) && $score <= floatval($sc['max_mark'])) {
                $grade = $sc['grade'];
                $remark = $sc['remark'];
                $pts = $idx + 1; // Basic point assumption based on order
                break;
            }
        }
        if ($grade === '-' && !empty($scales)) {
            $lowest = end($scales);
            $grade = $lowest['grade'];
            $remark = $lowest['remark'];
            $pts = count($scales);
        }

        $totalPoints += $pts;
        $m['grade']  = $grade;
        $m['points'] = $pts;
        $m['remark'] = $remark;
        $processedMarks[] = $m;
    }

    $subjectCount = count($processedMarks);
    $gpaAvg = $subjectCount > 0 ? round($totalSum / $subjectCount, 1) : 0;

    if ($totalPoints <= 17 && $subjectCount >= 5)    $division = 'Division I';
    elseif ($totalPoints <= 21 && $subjectCount >= 5) $division = 'Division II';
    elseif ($totalPoints <= 25 && $subjectCount >= 5) $division = 'Division III';
    elseif ($totalPoints <= 29 && $subjectCount >= 5) $division = 'Division IV';
    else $division = 'Division 0';

    $isReadOnly = ($year !== date('Y')) || (($currentAlloc['year_status'] ?? '') !== 'Active');

    // 4. Attendance Summary
    $stmtAtt = $conn->prepare("
        SELECT
            COUNT(CASE WHEN status = 'Present' THEN 1 END) AS days_present,
            COUNT(CASE WHEN status = 'Absent' THEN 1 END) AS days_absent,
            COUNT(CASE WHEN status = 'Excused' THEN 1 END) AS days_excused
        FROM student_attendance
        WHERE student_id = ? AND school_id = ? AND academic_year = ?
    ");
    $stmtAtt->execute([$studentId, $schoolId, $year]);
    $attendance = $stmtAtt->fetch(PDO::FETCH_ASSOC);

    if (!$attendance || (empty($attendance['days_present']) && empty($attendance['days_absent']) && empty($attendance['days_excused']))) {
        $attendance = [
            'days_present'   => 0,
            'days_absent'    => 0,
            'days_excused'   => 0,
            'attendance_pct' => 0
        ];
    } else {
        $totDays = intval($attendance['days_present']) + intval($attendance['days_absent']) + intval($attendance['days_excused']);
        $attendance['attendance_pct'] = $totDays > 0 ? round(($attendance['days_present'] / $totDays) * 100, 1) : 0;
    }

    // 5. Financial Ledger Summary — placeholder until finance module is implemented
    $financials = [
        'annual_tuition'   => 0,
        'scholarship_disc' => 0,
        'net_payable'      => 0,
        'total_paid'       => 0,
        'outstanding_bal'  => 0,
        'payment_status'   => 'N/A',
        'recent_receipts'  => []
    ];

    echo json_encode([
        'success'      => true,
        'year'         => $year,
        'is_read_only' => $isReadOnly,
        'profile'      => $profile,
        'timeline'     => $timeline,
        'allocation'   => $currentAlloc,
        'attendance'   => $attendance,
        'financials'   => $financials,
        'assessment_types' => $assessmentTypes,
        'academics'    => [
            'total_score'  => $totalSum,
            'gpa_avg'      => $gpaAvg,
            'total_points' => $totalPoints,
            'division'     => $division,
            'marks'        => $processedMarks
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
