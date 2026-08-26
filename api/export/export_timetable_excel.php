<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

$schoolId = $_SESSION['school_id'] ?? 'SCH-001';
$year = $_GET['year'] ?? date('Y');

require_once 'xlsxwriter.class.php';

// Fetch School Info
$stmtS = $conn->prepare("SELECT name FROM schools WHERE id = ?");
$stmtS->execute([$schoolId]);
$schoolName = $stmtS->fetchColumn() ?: 'SHULE CAFE Master Timetable';

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

$writer = new XLSXWriter();

// Styles setup
$headerStyle = array(
    'font' => 'Arial',
    'font-size' => 11,
    'font-style' => 'bold',
    'fill' => '#047857', // Corporate Green
    'color' => '#FFFFFF',
    'halign' => 'center',
    'valign' => 'center',
    'border' => 'left,right,top,bottom',
    'border-style' => 'thin',
    'border-color' => '#03543F'
);

$titleStyle = array(
    'font' => 'Arial',
    'font-size' => 14,
    'font-style' => 'bold',
    'fill' => '#0F172A', // Dark Navy Banner
    'color' => '#FFFFFF',
    'halign' => 'center',
    'valign' => 'center'
);

$dataStyle = array(
    'font' => 'Arial',
    'font-size' => 9,
    'halign' => 'center',
    'valign' => 'center',
    'border' => 'left,right,top,bottom',
    'border-style' => 'thin',
    'border-color' => '#CBD5E1'
);

$breakStyle = array(
    'font' => 'Arial',
    'font-size' => 10,
    'font-style' => 'bold',
    'fill' => '#F1F5F9',
    'color' => '#475569',
    'halign' => 'center',
    'valign' => 'center',
    'border' => 'left,right,top,bottom',
    'border-style' => 'thin'
);

if (empty($matrix)) {
    $writer->writeSheetHeader('Sheet1', array('Message' => 'string'));
    $writer->writeSheetRow('Sheet1', array('No Timetable Data Found.'));
} else {
    ksort($matrix);

    foreach ($matrix as $streamId => $streamData) {
        $sheetName = strtoupper(substr($streamId, 0, 30)); // Max sheet name length is 31
        
        // Define Column Headers & Types
        $colHeaders = array('Time Slot' => 'string');
        $colWidths = array(22);
        
        foreach ($operationalDays as $day) {
            $colHeaders[$day] = 'string';
            $colWidths[] = 30; // Auto-fit cell scaling
        }

        // Set column widths explicitly
        $writer->writeSheetHeader($sheetName, $colHeaders, array('suppress_header' => true, 'widths' => $colWidths));

        // Title Row
        $writer->writeSheetRow($sheetName, array(strtoupper($schoolName) . " - MASTER TIMETABLE (" . strtoupper($streamId) . ")"), $titleStyle);
        
        // Header Row
        $headerRowVals = array('PERIOD / TIME');
        foreach ($operationalDays as $day) {
            $headerRowVals[] = strtoupper($day);
        }
        $writer->writeSheetRow($sheetName, $headerRowVals, $headerStyle);

        // Body Rows
        foreach ($periods as $p) {
            $periodLabel = date('H:i', strtotime($p['start_time'])) . ' - ' . date('H:i', strtotime($p['end_time']));

            if ($p['is_break']) {
                $breakRow = array($periodLabel);
                for ($i = 0; $i < count($operationalDays); $i++) {
                    $breakRow[] = strtoupper($p['period_name']);
                }
                $writer->writeSheetRow($sheetName, $breakRow, $breakStyle);
                continue;
            }

            $rowVals = array($periodLabel);
            foreach ($operationalDays as $day) {
                $slot = $streamData[$day][$p['id']] ?? null;
                if ($slot) {
                    $rowVals[] = $slot['subject_name'] . "\n(" . $slot['teacher_name'] . ")";
                } else {
                    $rowVals[] = '-';
                }
            }
            $writer->writeSheetRow($sheetName, $rowVals, $dataStyle);
        }
    }
}

// Stream to Browser
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Master_Timetable_' . $year . '.xlsx"');
header('Cache-Control: max-age=0');

if (ob_get_length()) {
    ob_end_clean();
}
$writer->writeToStdOut();
exit();
?>
