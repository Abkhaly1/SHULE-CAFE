<?php
require_once __DIR__ . '/../config/db.php';

class AutomatedSchedulerEngine {
    private $db;

    public function __construct($pdoInstance) {
        $this->db = $pdoInstance;
    }

    /**
     * Check if Teacher is occupied during exact day/period slot anywhere in the school (Cross-Level Verification)
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
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if Class Stream already has a subject scheduled during exact day/period slot
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
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Execute Conflict-Free Automated Scheduling Generator (Stage 5 Engine)
     */
    public function generateConflictFreeTimetable($schoolId, $academicYearId, $configId) {
        // 1. Fetch Config Details
        $stmtCfg = $this->db->prepare("SELECT * FROM timetable_configs WHERE id = ? AND school_id = ?");
        $stmtCfg->execute([$configId, $schoolId]);
        $config = $stmtCfg->fetch(PDO::FETCH_ASSOC);

        if (!$config) {
            return ['success' => false, 'message' => 'Timetable configuration not found.'];
        }

        $selectedGrades = json_decode($config['selected_grades'], true) ?? [];
        $opDays = json_decode($config['operational_days'], true) ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $levelCode = $config['level_code'];

        if (empty($selectedGrades)) {
            return ['success' => false, 'message' => 'No grades selected in configuration.'];
        }

        // 2. Fetch Non-Break Periods for this Level / School
        $stmtPeriods = $this->db->prepare("
            SELECT id, period_number, period_name, start_time, end_time
            FROM timetable_periods
            WHERE school_id = ? AND is_break = 0
            ORDER BY period_number ASC
        ");
        $stmtPeriods->execute([$schoolId]);
        $periods = $stmtPeriods->fetchAll(PDO::FETCH_ASSOC);

        if (empty($periods)) {
            return ['success' => false, 'message' => 'No operational period time-slots configured. Please complete Stage 3 time mapping.'];
        }

        // 3. Fetch Classrooms for Selected Grades
        $placeholders = implode(',', array_fill(0, count($selectedGrades), '?'));
        $stmtRooms = $this->db->prepare("
            SELECT c.id, c.classroom_name, c.grade_id, g.name AS grade_name
            FROM classrooms c
            JOIN grades g ON c.grade_id = g.id
            WHERE c.school_id = ? AND c.academic_year = ? AND c.grade_id IN ($placeholders)
            ORDER BY g.order_seq ASC, c.classroom_name ASC
        ");
        $stmtRooms->execute(array_merge([$schoolId, $academicYearId], $selectedGrades));
        $classrooms = $stmtRooms->fetchAll(PDO::FETCH_ASSOC);

        if (empty($classrooms)) {
            return ['success' => false, 'message' => 'No custom classroom streams found for the selected grades in Academic Year ' . $academicYearId];
        }

        // 4. Fetch Subject Frequencies for this Config
        $stmtFreq = $this->db->prepare("
            SELECT tsf.grade_id, tsf.subject_code, tsf.weekly_frequency, s.id AS subject_id, s.name AS subject_name
            FROM timetable_subject_frequencies tsf
            JOIN subjects s ON (s.school_id = tsf.school_id AND s.code = tsf.subject_code)
            WHERE tsf.config_id = ?
        ");
        $stmtFreq->execute([$configId]);
        $frequencies = $stmtFreq->fetchAll(PDO::FETCH_ASSOC);

        $gradeFrequenciesMap = [];
        foreach ($frequencies as $f) {
            $gradeFrequenciesMap[$f['grade_id']][] = $f;
        }

        // 5. Clear existing timetables for these classrooms
        $roomNames = array_column($classrooms, 'classroom_name');
        if (!empty($roomNames)) {
            $rPlaceholders = implode(',', array_fill(0, count($roomNames), '?'));
            $stmtDel = $this->db->prepare("
                DELETE FROM class_timetables 
                WHERE school_id = ? AND academic_year_id = ? AND class_stream_id IN ($rPlaceholders)
            ");
            $stmtDel->execute(array_merge([$schoolId, $academicYearId], $roomNames));
        }

        $scheduledCount = 0;
        $bottlenecks = [];
        $unresolvedSlots = 0;

        // Cache qualified teachers per subject id to speed up lookups & compute constraint score
        $stmtAllQual = $this->db->prepare("
            SELECT tsq.subject_id, tsq.teacher_id, u.full_name
            FROM teacher_subject_qualifications tsq
            JOIN users u ON tsq.teacher_id = u.id
            WHERE u.school_id = ? AND u.status = 'active'
        ");
        $stmtAllQual->execute([$schoolId]);
        $allQualRows = $stmtAllQual->fetchAll(PDO::FETCH_ASSOC);

        $teachersBySubject = [];
        foreach ($allQualRows as $qr) {
            $teachersBySubject[$qr['subject_id']][] = $qr;
        }

        // 6. Scheduling Loop: Stream by Stream
        foreach ($classrooms as $room) {
            $roomId = $room['classroom_name'];
            $gradeId = $room['grade_id'];
            $subjectReqs = $gradeFrequenciesMap[$gradeId] ?? [];

            if (empty($subjectReqs)) {
                $bottlenecks[] = [
                    'classroom' => $roomId,
                    'grade' => $room['grade_name'],
                    'issue' => 'No subject weekly frequencies configured for this grade tier in Stage 4.'
                ];
                continue;
            }

            // Build pool of required subject periods for this room
            $subjectPool = [];
            foreach ($subjectReqs as $sReq) {
                $sId = $sReq['subject_id'];
                $qualCount = count($teachersBySubject[$sId] ?? []);
                for ($i = 0; $i < intval($sReq['weekly_frequency']); $i++) {
                    $item = $sReq;
                    $item['qual_count'] = $qualCount;
                    $subjectPool[] = $item;
                }
            }

            // Sort pool: Most Constrained First (fewer qualified teachers first)
            usort($subjectPool, function($a, $b) {
                if ($a['qual_count'] === $b['qual_count']) {
                    return intval($b['weekly_frequency']) - intval($a['weekly_frequency']);
                }
                return $a['qual_count'] - $b['qual_count'];
            });

            // Track daily subject counts per room to avoid >2 of same subject per day
            $dailySubjectCount = [];

            // Phase 1: Primary Greedy Slot Placement (Strict max 1 period of same subject per day)
            foreach ($opDays as $day) {
                foreach ($periods as $p) {
                    $periodId = $p['id'];

                    if (empty($subjectPool)) break 2;

                    $assignedInSlot = false;
                    foreach ($subjectPool as $idx => $sCandidate) {
                        $sCode = $sCandidate['subject_code'];
                        $sId = $sCandidate['subject_id'];
                        $sName = $sCandidate['subject_name'];
                        $wFreq = intval($sCandidate['weekly_frequency']);

                        // Strict rule: Somo halijirudii mara zaidi ya moja ndani ya siku moja (Max 1 per day unless freq > opDays)
                        $maxAllowedPerDay = ($wFreq > count($opDays)) ? 2 : 1;
                        $daySubjKey = "{$day}_{$sCode}";
                        if (($dailySubjectCount[$daySubjKey] ?? 0) >= $maxAllowedPerDay) {
                            continue;
                        }

                        $qualTeachers = $teachersBySubject[$sId] ?? [];

                        if (empty($qualTeachers)) {
                            $bottlenecks[] = [
                                'classroom' => $roomId,
                                'grade' => $room['grade_name'],
                                'subject' => "$sName ($sCode)",
                                'issue' => 'No active teachers qualified to teach this subject in Teacher Allocation module.'
                            ];
                            unset($subjectPool[$idx]);
                            $subjectPool = array_values($subjectPool);
                            break;
                        }

                        $chosenTeacher = null;
                        foreach ($qualTeachers as $tCandidate) {
                            $tId = $tCandidate['teacher_id'];
                            $tConflict = $this->checkTeacherConflict($tId, $day, $periodId, $academicYearId, $schoolId);
                            if (!$tConflict) {
                                $chosenTeacher = $tCandidate;
                                break;
                            }
                        }

                        if ($chosenTeacher) {
                            $stmtIns = $this->db->prepare("
                                INSERT INTO class_timetables (school_id, academic_year_id, day_of_week, period_id, class_stream_id, subject_code, teacher_id)
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE subject_code = VALUES(subject_code), teacher_id = VALUES(teacher_id)
                            ");
                            $stmtIns->execute([
                                $schoolId,
                                $academicYearId,
                                $day,
                                $periodId,
                                $roomId,
                                $sCode,
                                $chosenTeacher['teacher_id']
                            ]);

                            $scheduledCount++;
                            $dailySubjectCount[$daySubjKey] = ($dailySubjectCount[$daySubjKey] ?? 0) + 1;
                            unset($subjectPool[$idx]);
                            $subjectPool = array_values($subjectPool);
                            $assignedInSlot = true;
                            break;
                        }
                    }
                }
            }

            // Phase 2: Backtracking & Ripple Swap Pass for remaining unscheduled subjects in this room
            if (!empty($subjectPool)) {
                foreach ($subjectPool as $idx => $sCandidate) {
                    $sCode = $sCandidate['subject_code'];
                    $sId = $sCandidate['subject_id'];
                    $wFreq = intval($sCandidate['weekly_frequency']);
                    $maxAllowedPerDay = ($wFreq > count($opDays)) ? 2 : 1;

                    $qualTeachers = $teachersBySubject[$sId] ?? [];
                    if (empty($qualTeachers)) continue;

                    $swapResolved = false;

                    // Search all days and periods for a slot where we can swap or fit
                    foreach ($opDays as $day) {
                        // Check if day already has max allowed periods of this subject in current room
                        $stmtDayCheck = $this->db->prepare("
                            SELECT COUNT(*) FROM class_timetables
                            WHERE school_id = ? AND academic_year_id = ? AND class_stream_id = ? AND day_of_week = ? AND subject_code = ?
                        ");
                        $stmtDayCheck->execute([$schoolId, $academicYearId, $roomId, $day, $sCode]);
                        if ($stmtDayCheck->fetchColumn() >= $maxAllowedPerDay) {
                            continue; // Skip day to preserve single-period daily limit
                        }

                        foreach ($periods as $p) {
                            $periodId = $p['id'];

                            // Check if current room is free at (day, periodId)
                            $roomConf = $this->checkClassConflict($roomId, $day, $periodId, $academicYearId, $schoolId);
                            if ($roomConf) continue; // Room is already busy in this slot

                            // Try to find a teacher and see if their conflicting classroom can move
                            foreach ($qualTeachers as $tCandidate) {
                                $tId = $tCandidate['teacher_id'];
                                $tConf = $this->checkTeacherConflict($tId, $day, $periodId, $academicYearId, $schoolId);

                                if (!$tConf) {
                                    // Teacher is free! Assign directly
                                    $stmtIns = $this->db->prepare("
                                        INSERT INTO class_timetables (school_id, academic_year_id, day_of_week, period_id, class_stream_id, subject_code, teacher_id)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)
                                    ");
                                    $stmtIns->execute([$schoolId, $academicYearId, $day, $periodId, $roomId, $sCode, $tId]);
                                    $scheduledCount++;
                                    unset($subjectPool[$idx]);
                                    $swapResolved = true;
                                    break 3;
                                } else {
                                    // Teacher is busy at (day, periodId) in another classroom ($otherRoom)
                                    $otherRoom = $tConf['class_stream_id'];
                                    
                                    // Look for an alternative slot (altDay, altPeriod) where BOTH $otherRoom and Teacher $tId are free
                                    foreach ($opDays as $altDay) {
                                        foreach ($periods as $altP) {
                                            $altPeriodId = $altP['id'];
                                            if ($altDay === $day && $altPeriodId === $periodId) continue;

                                            $otherConfAlt = $this->checkClassConflict($otherRoom, $altDay, $altPeriodId, $academicYearId, $schoolId);
                                            $teacherConfAlt = $this->checkTeacherConflict($tId, $altDay, $altPeriodId, $academicYearId, $schoolId);

                                            if (!$otherConfAlt && !$teacherConfAlt) {
                                                // RIPPLE SWAP MATCH! Move $otherRoom slot from (day, periodId) to (altDay, altPeriodId)
                                                $stmtUpdateOther = $this->db->prepare("
                                                    UPDATE class_timetables
                                                    SET day_of_week = ?, period_id = ?
                                                    WHERE id = ?
                                                ");
                                                $stmtUpdateOther->execute([$altDay, $altPeriodId, $tConf['id']]);

                                                // Now assign (day, periodId) to current room with Teacher $tId
                                                $stmtIns = $this->db->prepare("
                                                    INSERT INTO class_timetables (school_id, academic_year_id, day_of_week, period_id, class_stream_id, subject_code, teacher_id)
                                                    VALUES (?, ?, ?, ?, ?, ?, ?)
                                                ");
                                                $stmtIns->execute([$schoolId, $academicYearId, $day, $periodId, $roomId, $sCode, $tId]);

                                                $scheduledCount++;
                                                unset($subjectPool[$idx]);
                                                $swapResolved = true;
                                                break 5;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                $subjectPool = array_values($subjectPool);
            }

            // Report any remaining unresolved bottlenecks (if teachers are physically insufficient)
            if (!empty($subjectPool)) {
                $unmetSubjects = array_count_values(array_column($subjectPool, 'subject_code'));
                foreach ($unmetSubjects as $unmetCode => $unmetFreq) {
                    $unresolvedSlots += $unmetFreq;
                    $bottlenecks[] = [
                        'classroom' => $roomId,
                        'grade' => $room['grade_name'],
                        'subject' => $unmetCode,
                        'issue' => "Could not schedule $unmetFreq period(s) due to tight teacher availability limits."
                    ];
                }
            }
        }

        return [
            'success' => true,
            'scheduled_slots_count' => $scheduledCount,
            'unresolved_slots_count' => $unresolvedSlots,
            'bottlenecks' => $bottlenecks,
            'message' => "Automated conflict-free scheduling engine finished with $scheduledCount slots scheduled."
        ];
    }
}
?>
