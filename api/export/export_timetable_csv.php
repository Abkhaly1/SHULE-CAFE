<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

$schoolId = $_SESSION['school_id'] ?? 'SCH-001';
$year = $_GET['year'] ?? date('Y');

// Fetch Timetable Config
$stmtC = $conn->prepare("SELECT operational_days FROM timetable_configs WHERE school_id = ? AND academic_year_id = ? LIMIT 1");
$stmtC->execute([$schoolId, $year]);
$config = $stmtC->fetch(PDO::FETCH_ASSOC);
$operationalDays = $config ? json_decode($config['operational_days'], true) : ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

// Fetch Periods
$stmtP = $conn->prepare("
    SELECT id, period_number, period_name, start_time, end_time, is_break 
    FROM timetable_periods 
    WHERE school_id = ? 
    ORDER BY period_number ASC
");
$stmtP->execute([$schoolId]);
$periods = $stmtP->fetchAll(PDO::FETCH_ASSOC);

// Fetch Timetable Slots
$sql = "
    SELECT ct.day_of_week, ct.period_id, ct.class_stream_id, ct.subject_code,
           COALESCE(sas.subject_name, s.name, ct.subject_code) AS subject_name,
           u.full_name AS teacher_name
    FROM class_timetables ct
    LEFT JOIN subjects s ON (ct.school_id = s.school_id AND ct.subject_code = s.code)
    LEFT JOIN school_approved_subjects sas ON (ct.school_id = sas.school_id AND ct.subject_code = sas.subject_code)
    LEFT JOIN users u ON ct.teacher_id = u.id
    WHERE ct.school_id = ? AND ct.academic_year_id = ?
";
$stmtTt = $conn->prepare($sql);
$stmtTt->execute([$schoolId, $year]);
$slots = $stmtTt->fetchAll(PDO::FETCH_ASSOC);

$matrix = [];
foreach ($slots as $s) {
    $matrix[$s['class_stream_id']][$s['day_of_week']][$s['period_id']] = $s;
}

// HTTP Headers for CSV Download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="Master_Timetable_' . $year . '.csv"');

$output = fopen('php://output', 'w');

// Enforce explicit UTF-8 byte order mark (BOM) for Swahili phonetic compatibility in Excel/tools
fprintf($output, "\xEF\xBB\xBF");

if (empty($matrix)) {
    fputcsv($output, ['Message']);
    fputcsv($output, ['No Timetable Data Found.']);
} else {
    ksort($matrix);

    foreach ($matrix as $streamId => $streamData) {
        // Stream Header Block
        fputcsv($output, ['--- CLASS STREAM ---', strtoupper($streamId)]);
        
        // Column Headers
        $headers = array_merge(['Period / Time'], array_map('strtoupper', $operationalDays));
        fputcsv($output, $headers);

        // Body Rows
        foreach ($periods as $p) {
            $periodLabel = date('H:i', strtotime($p['start_time'])) . ' - ' . date('H:i', strtotime($p['end_time']));

            if ($p['is_break']) {
                $row = array_merge([$periodLabel], array_fill(0, count($operationalDays), strtoupper($p['period_name'])));
                fputcsv($output, $row);
                continue;
            }

            $row = [$periodLabel];
            foreach ($operationalDays as $day) {
                $slot = $streamData[$day][$p['id']] ?? null;
                if ($slot) {
                    $row[] = $slot['subject_name'] . " (" . $slot['teacher_name'] . ")";
                } else {
                    $row[] = '-';
                }
            }
            fputcsv($output, $row);
        }
        
        // Blank row between stream blocks
        fputcsv($output, []);
    }
}

fclose($output);
exit();
?>
