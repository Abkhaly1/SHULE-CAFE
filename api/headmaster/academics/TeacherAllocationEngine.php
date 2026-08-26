<?php
namespace Headmaster\Academics;

require_once __DIR__ . '/../../config/db.php';

class TeacherAllocationEngine {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    // Returns teacher qualified subjects and available classrooms grouped by subject/grade
    public function getImplicitGradesAndAvailableClassrooms($teacherId, $academicYearId, $schoolId) {
        $sql = "
            SELECT tsq.id AS qualification_id, tsq.subject_id, s.code AS subject_code, s.name AS subject_name, COALESCE(sas.level_code, s.level_type) AS level_code, g.id AS grade_id, g.name AS grade_name,
                   c.id AS classroom_id, c.classroom_name,
                   EXISTS(
                       SELECT 1 FROM teacher_classroom_assignments tca
                       WHERE tca.teacher_id = :teacher_id
                         AND tca.subject_id = tsq.subject_id
                         AND tca.classroom_id = c.id
                         AND tca.academic_year_id = :year_id
                   ) AS is_currently_assigned
            FROM teacher_subject_qualifications tsq
            JOIN subjects s ON tsq.subject_id = s.id
            JOIN grade_subjects gs ON (gs.school_id = :school_id AND gs.academic_year = :year_id AND gs.subject_code = s.code)
            JOIN grades g ON gs.grade_id = g.id
            LEFT JOIN classrooms c ON c.grade_id = g.id AND c.school_id = :school_id AND c.academic_year = :year_id
            LEFT JOIN school_approved_subjects sas ON (sas.school_id = :school_id AND sas.subject_code = s.code)
            WHERE tsq.teacher_id = :teacher_id
            ORDER BY g.id ASC, s.name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':teacher_id' => $teacherId, ':year_id' => $academicYearId, ':school_id' => $schoolId]);
        return $stmt->fetchAll(
            \PDO::FETCH_ASSOC
        );
    }

    public function getTeacherQualifications($teacherId, $schoolId = null) {
        $sql = "
            SELECT tsq.id, tsq.teacher_id, tsq.subject_id, s.code AS subject_code, s.name AS subject_name, COALESCE(sas.level_code, s.level_type, 'O-LEVEL') AS level_code 
            FROM teacher_subject_qualifications tsq 
            JOIN subjects s ON tsq.subject_id = s.id 
            LEFT JOIN school_approved_subjects sas ON (sas.school_id = s.school_id AND sas.subject_code = s.code)
            WHERE tsq.teacher_id = ?
        ";
        $params = [$teacherId];
        if ($schoolId) {
            $sql .= " AND s.school_id = ?";
            $params[] = $schoolId;
        }
        $sql .= " ORDER BY level_code ASC, s.name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function addQualification($teacherId, int $subjectId) {
        $stmt = $this->db->prepare("INSERT IGNORE INTO teacher_subject_qualifications (teacher_id, subject_id) VALUES (?, ?)");
        return $stmt->execute([$teacherId, $subjectId]);
    }

    public function removeQualification($teacherId, int $subjectId) {
        $stmt = $this->db->prepare("DELETE FROM teacher_subject_qualifications WHERE teacher_id = ? AND subject_id = ?");
        return $stmt->execute([$teacherId, $subjectId]);
    }

    public function getClassroomAssignments($teacherId, $academicYearId, $schoolId = null) {
        $sql = "
            SELECT tca.*, s.code AS subject_code, s.name AS subject_name, c.classroom_name, COALESCE(sas.level_code, s.level_type, 'O-LEVEL') AS level_code
            FROM teacher_classroom_assignments tca 
            JOIN subjects s ON tca.subject_id = s.id 
            JOIN classrooms c ON tca.classroom_id = c.id 
            LEFT JOIN school_approved_subjects sas ON (sas.school_id = c.school_id AND sas.subject_code = s.code)
            WHERE tca.teacher_id = ? AND tca.academic_year_id = ?
        ";
        $params = [$teacherId, $academicYearId];
        if ($schoolId) {
            $sql .= " AND c.school_id = ?";
            $params[] = $schoolId;
        }
        $sql .= " ORDER BY s.name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function saveClassroomAssignment($academicYearId, $teacherId, $subjectId, $classroomId) {
        $stmt = $this->db->prepare("INSERT INTO teacher_classroom_assignments (academic_year_id, teacher_id, subject_id, classroom_id) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP");
        return $stmt->execute([$academicYearId, $teacherId, $subjectId, $classroomId]);
    }

    public function removeClassroomAssignment($academicYearId, $teacherId, $subjectId, $classroomId) {
        $stmt = $this->db->prepare("DELETE FROM teacher_classroom_assignments WHERE academic_year_id = ? AND teacher_id = ? AND subject_id = ? AND classroom_id = ?");
        return $stmt->execute([$academicYearId, $teacherId, $subjectId, $classroomId]);
    }

    // Clone assignments from one year to another for provided teacher ids (or all)
    public function rolloverAssignments($fromYear, $toYear, $teacherIds = []) {
        $teacherFilter = '';
        $params = [':from' => $fromYear, ':to' => $toYear];
        if (!empty($teacherIds)) {
            $placeholders = implode(',', array_fill(0, count($teacherIds), '?'));
            $teacherFilter = "AND tca.teacher_id IN ($placeholders)";
            $params = array_merge($params, $teacherIds);
        }

        // Insert distinct rows from old year to new year if not exists
        $sql = "INSERT IGNORE INTO teacher_classroom_assignments (academic_year_id, teacher_id, subject_id, classroom_id, created_at)
                SELECT :to, tca.teacher_id, tca.subject_id, tca.classroom_id, NOW()
                FROM teacher_classroom_assignments tca
                WHERE tca.academic_year_id = :from $teacherFilter";

        $stmt = $this->db->prepare($sql);
        // Build params array in correct order
        $execParams = [':to' => $toYear, ':from' => $fromYear];
        if (!empty($teacherIds)) {
            foreach ($teacherIds as $tid) $execParams[] = $tid;
        }
        return $stmt->execute($execParams);
    }
}

?>
