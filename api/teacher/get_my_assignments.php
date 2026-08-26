<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

$teacherId = $_SESSION['user_id'];
$academicYearId = $_GET['academic_year'] ?? date('Y');

try {
    // 1. Fetch Subject Teacher Assignments (1 Teacher -> Many Subjects/Classes) - Combines legacy and new allocation engines
    $stmtSubjects = $conn->prepare("
        SELECT class_name, subject_code, subject_name, level_code, total_students
        FROM (
            SELECT COALESCE(c.classroom_name, tsa.class_stream_id) as class_name, tsa.subject_code, COALESCE(sas.subject_name, tsa.subject_code) AS subject_name, sas.level_code,
                   (SELECT COUNT(DISTINCT sca.student_id) 
                    FROM student_classroom_allocations sca 
                    JOIN classrooms c2 ON sca.classroom_id = c2.id 
                    WHERE (c2.classroom_name = tsa.class_stream_id OR CAST(c2.id AS CHAR) = tsa.class_stream_id) AND sca.status = 'Active') AS total_students
            FROM teacher_subject_assignments tsa
            LEFT JOIN classrooms c ON (tsa.class_stream_id = c.classroom_name OR tsa.class_stream_id = CAST(c.id AS CHAR))
            LEFT JOIN school_approved_subjects sas ON (tsa.school_id = sas.school_id AND tsa.subject_code = sas.subject_code)
            WHERE tsa.teacher_id = :teacher_id AND tsa.academic_year_id = :year_id

            UNION DISTINCT

            SELECT c.classroom_name as class_name, s.code as subject_code, s.name as subject_name, COALESCE(sas.level_code, s.level_type, 'O-LEVEL') as level_code,
                   (SELECT COUNT(DISTINCT sca.student_id) 
                    FROM student_classroom_allocations sca 
                    WHERE sca.classroom_id = c.id AND sca.status = 'Active') AS total_students
            FROM teacher_classroom_assignments tca
            JOIN classrooms c ON tca.classroom_id = c.id
            JOIN subjects s ON tca.subject_id = s.id
            LEFT JOIN school_approved_subjects sas ON (sas.school_id = c.school_id AND sas.subject_code = s.code)
            WHERE tca.teacher_id = :teacher_id AND tca.academic_year_id = :year_id
        ) AS combined_allocations
        ORDER BY class_name ASC, subject_name ASC
    ");
    $stmtSubjects->execute([
        ':teacher_id' => $teacherId,
        ':year_id' => $academicYearId
    ]);
    $taughtSubjects = $stmtSubjects->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Form Master Managed Class Stream (Dual Role Check)
    $stmtFormMaster = $conn->prepare("
        SELECT COALESCE(c.classroom_name, ct.class_stream_id) as managed_class_name
        FROM class_teachers ct
        LEFT JOIN classrooms c ON (ct.class_stream_id = c.classroom_name OR ct.class_stream_id = CAST(c.id AS CHAR))
        WHERE ct.teacher_id = :teacher_id AND ct.academic_year_id = :year_id
        LIMIT 1
    ");
    $stmtFormMaster->execute([
        ':teacher_id' => $teacherId,
        ':year_id' => $academicYearId
    ]);
    $managedClass = $stmtFormMaster->fetch(PDO::FETCH_ASSOC);

    // 3. Fetch Assessment Types (Latest Policy for the school)
    // We need the teacher's school_id. Let's fetch it from the user table.
    $stmtTeacherSchool = $conn->prepare("SELECT school_id FROM users WHERE id = ?");
    $stmtTeacherSchool->execute([$teacherId]);
    $tSchool = $stmtTeacherSchool->fetch(PDO::FETCH_ASSOC);
    $schoolId = $tSchool ? $tSchool['school_id'] : '';

    $stmtTypes = $conn->prepare("SELECT id, name, weight_percent, is_terminal, academic_year FROM assessment_types WHERE school_id = ? AND is_archived = 0 ORDER BY academic_year DESC, id ASC");
    $stmtTypes->execute([$schoolId]);
    $allTypes = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);
    $assessmentTypes = [];
    if (!empty($allTypes)) {
        $latestYr = $allTypes[0]['academic_year'];
        foreach ($allTypes as $t) {
            if ($t['academic_year'] === $latestYr) $assessmentTypes[] = $t;
        }
    } else {
        $assessmentTypes = [
            ['id' => 'ca', 'name' => 'CA Mark', 'weight_percent' => 40],
            ['id' => 'terminal', 'name' => 'Terminal', 'weight_percent' => 60]
        ];
    }

    // 4. Class Detail Roster & Academic Progress Tracker
    $classDetails = null;
    $targetClass   = $_GET['class_name'] ?? null;
    $targetSubject = $_GET['subject_code'] ?? null;

    if ($targetClass && $targetSubject) {
        // Check if explicit subject enrollments exist for this subject
        $stmtEnrCheck = $conn->prepare("SELECT COUNT(*) FROM student_subject_enrollments WHERE school_id = ? AND subject_code = ? AND academic_year_id = ?");
        $stmtEnrCheck->execute([$schoolId, $targetSubject, $academicYearId]);
        $hasEnrFilter = ((int)$stmtEnrCheck->fetchColumn() > 0);

        $enrJoin = "";
        if ($hasEnrFilter) {
            $enrJoin = "JOIN student_subject_enrollments sse ON (sse.student_id = u.id AND sse.subject_code = :subj AND sse.school_id = :sch)";
        }

        $stmtRoster = $conn->prepare("
            SELECT u.id AS student_id, u.full_name, u.user_code, u.gender
            FROM student_classroom_allocations sca
            JOIN users u ON sca.student_id = u.id
            JOIN classrooms c ON sca.classroom_id = c.id
            $enrJoin
            WHERE (c.classroom_name = :cname OR CAST(c.id AS CHAR) = :cname) AND sca.academic_year = :year AND sca.status = 'Active'
            ORDER BY u.full_name ASC
        ");
        
        $params = [':year' => $academicYearId, ':cname' => $targetClass];
        if ($hasEnrFilter) {
            $params[':subj'] = $targetSubject;
            $params[':sch'] = $schoolId;
        }
        $stmtRoster->execute($params);
        $students = $stmtRoster->fetchAll(PDO::FETCH_ASSOC);

        // Fetch dynamic marks
        $stmtMarks = $conn->prepare("
            SELECT student_id, assessment_type_id, score
            FROM marks_entry_dynamic
            WHERE subject_code = :subj AND academic_year = :year AND school_id = :school
        ");
        $stmtMarks->execute([':subj' => $targetSubject, ':year' => $academicYearId, ':school' => $schoolId]);
        $dynamicMarks = $stmtMarks->fetchAll(PDO::FETCH_ASSOC);

        $marksMap = [];
        foreach ($dynamicMarks as $dm) {
            $marksMap[$dm['student_id']][$dm['assessment_type_id']] = floatval($dm['score']);
        }

        // Fetch legacy marks as fallback
        $stmtLegacy = $conn->prepare("SELECT student_id, continuous_assessment_mark, terminal_mark FROM marks_entry WHERE subject_code = :subj AND academic_year = :year");
        $stmtLegacy->execute([':subj' => $targetSubject, ':year' => $academicYearId]);
        $legacyMarks = $stmtLegacy->fetchAll(PDO::FETCH_ASSOC);
        $legacyMap = [];
        foreach ($legacyMarks as $lm) {
            $legacyMap[$lm['student_id']] = [
                'ca' => floatval($lm['continuous_assessment_mark']),
                'terminal' => floatval($lm['terminal_mark'])
            ];
        }

        // Fetch dynamic grading scales
        $stmtLevel = $conn->prepare("
            SELECT el.name AS level_type
            FROM classrooms c
            JOIN grades g ON c.grade_id = g.id
            JOIN education_levels el ON g.level_id = el.id
            WHERE c.classroom_name = :cname AND c.school_id = :sch LIMIT 1
        ");
        $stmtLevel->execute([':cname' => $targetClass, ':sch' => $schoolId]);
        $levelType = $stmtLevel->fetchColumn() ?: 'O-Level';

        $stmtScales = $conn->prepare("SELECT min_mark, max_mark, grade, remark FROM grading_scales WHERE level_type = :ltype ORDER BY min_mark DESC");
        $stmtScales->execute([':ltype' => $levelType]);
        $scales = $stmtScales->fetchAll(PDO::FETCH_ASSOC);
        if (empty($scales)) {
            $stmtScales->execute([':ltype' => 'O-Level']);
            $scales = $stmtScales->fetchAll(PDO::FETCH_ASSOC);
        }

        $classDetails = [];
        foreach ($students as $stu) {
            $sid = $stu['student_id'];
            $stuMarks = $marksMap[$sid] ?? $legacyMap[$sid] ?? [];
            $totalMark = 0;
            foreach ($stuMarks as $score) {
                $totalMark += floatval($score);
            }

            $grade = '-';
            foreach ($scales as $sc) {
                if ($totalMark >= floatval($sc['min_mark']) && $totalMark <= floatval($sc['max_mark'])) {
                    $grade = $sc['grade'] . ' (' . $sc['remark'] . ')';
                    break;
                }
            }
            if ($grade === '-' && !empty($scales)) {
                // Fallback to lowest grade if didn't match (e.g. 0 marks)
                $lowest = end($scales);
                $grade = $lowest['grade'] . ' (' . $lowest['remark'] . ')';
            }

            $stu['marks'] = $stuMarks;
            $stu['total_mark'] = $totalMark;
            $stu['progress_grade'] = $grade;
            $classDetails[] = $stu;
        }

        // Sort by total_mark DESC
        usort($classDetails, function($a, $b) {
            return $b['total_mark'] <=> $a['total_mark'];
        });
    }

    echo json_encode([
        "success" => true,
        "teacher_id" => $teacherId,
        "academic_year" => $academicYearId,
        "is_form_master" => !empty($managedClass),
        "managed_class" => $managedClass ? $managedClass['managed_class_name'] : null,
        "taught_subjects" => $taughtSubjects,
        "assessment_types" => $assessmentTypes,
        "class_details" => $classDetails
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
