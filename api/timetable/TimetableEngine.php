<?php
require_once __DIR__ . '/../config/db.php';

class TimetableEngine {
    private $db;

    public function __construct($pdoInstance) {
        $this->db = $pdoInstance;
    }

    /**
     * Constraint 1: Check if Teacher is already occupied during exact day/period slot
     */
    public function checkTeacherConflict($teacherId, $dayOfWeek, $periodId, $academicYearId = null, $schoolId = null) {
        if ($academicYearId === null) $academicYearId = date('Y');
        $stmt = $this->db->prepare("
            SELECT ct.id, ct.class_stream_id, sas.subject_name
            FROM class_timetables ct
            LEFT JOIN school_approved_subjects sas ON (ct.school_id = sas.school_id AND ct.subject_code = sas.subject_code)
            WHERE ct.teacher_id = :teacher_id 
              AND ct.day_of_week = :day 
              AND ct.period_id = :period_id 
              AND ct.academic_year_id = :year_id
              AND (:school_id IS NULL OR ct.school_id = :school_id)
            LIMIT 1
        ");
        $stmt->execute([
            ':teacher_id' => $teacherId,
            ':day' => $dayOfWeek,
            ':period_id' => $periodId,
            ':year_id' => $academicYearId,
            ':school_id' => $schoolId
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC); // Returns existing booking or false
    }

    /**
     * Constraint 2: Check if Class Stream already has a subject scheduled during exact day/period slot
     */
    public function checkClassConflict($classStreamId, $dayOfWeek, $periodId, $academicYearId = null, $schoolId = null) {
        if ($academicYearId === null) $academicYearId = date('Y');
        $stmt = $this->db->prepare("
            SELECT ct.id, ct.subject_code, u.full_name as teacher_name
            FROM class_timetables ct
            LEFT JOIN users u ON ct.teacher_id = u.id
            WHERE ct.class_stream_id = :stream_id 
              AND ct.day_of_week = :day 
              AND ct.period_id = :period_id 
              AND ct.academic_year_id = :year_id
              AND (:school_id IS NULL OR ct.school_id = :school_id)
            LIMIT 1
        ");
        $stmt->execute([
            ':stream_id' => $classStreamId,
            ':day' => $dayOfWeek,
            ':period_id' => $periodId,
            ':year_id' => $academicYearId,
            ':school_id' => $schoolId
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC); // Returns existing booking or false
    }

    /**
     * Insert/Schedule a Period Slot with Collision Checks
     */
    public function schedulePeriodSlot($schoolId, $academicYearId, $dayOfWeek, $periodId, $classStreamId, $subjectCode, $teacherId) {
        // 1. Verify Teacher Collision
        $teacherConflict = $this->checkTeacherConflict($teacherId, $dayOfWeek, $periodId, $academicYearId, $schoolId);
        if ($teacherConflict) {
            return [
                'success' => false,
                'conflict_type' => 'teacher',
                'message' => "Teacher conflict: Assigned teacher is already teaching in {$teacherConflict['class_stream_id']} during this period slot."
            ];
        }

        // 2. Verify Class Stream Collision
        $classConflict = $this->checkClassConflict($classStreamId, $dayOfWeek, $periodId, $academicYearId, $schoolId);
        if ($classConflict) {
            return [
                'success' => false,
                'conflict_type' => 'class_stream',
                'message' => "Class conflict: {$classStreamId} already has {$classConflict['subject_code']} scheduled during this period slot."
            ];
        }

        // 3. Write Schedule Slot
        $stmt = $this->db->prepare("
            INSERT INTO class_timetables (school_id, academic_year_id, day_of_week, period_id, class_stream_id, subject_code, teacher_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE subject_code = VALUES(subject_code), teacher_id = VALUES(teacher_id)
        ");
        $stmt->execute([$schoolId, $academicYearId, $dayOfWeek, $periodId, $classStreamId, $subjectCode, $teacherId]);

        return [
            'success' => true,
            'message' => "Period slot successfully scheduled with zero collisions."
        ];
    }
}
?>
