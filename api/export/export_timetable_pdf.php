<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Only allow logged-in users
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

$schoolId = $_SESSION['school_id'] ?? 'SCH-001';
$year = $_GET['year'] ?? date('Y');

require_once 'fpdf/fpdf.php';

// Fetch School Name for header
$stmtS = $conn->prepare("SELECT name FROM schools WHERE id = ?");
$stmtS->execute([$schoolId]);
$schoolData = $stmtS->fetch(PDO::FETCH_ASSOC);
$schoolName = $schoolData['name'] ?? 'International Academy';
$schoolAddress = 'P.O BOX 1234, Dar es Salaam';

class SchoolPDF extends FPDF {
    public $schoolName;
    public $schoolAddress;

    function Header() {
        // Institutional Metadata
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 8, $this->schoolName, 0, 1, 'C');
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, $this->schoolAddress, 0, 1, 'C');
        
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(0, 5, 'Ministry of Education and Vocational Training', 0, 1, 'C');
        
        // Solid colored accent divider rule
        $this->SetDrawColor(4, 120, 87); // #047857 Green
        $this->SetLineWidth(1);
        $this->Line(10, $this->GetY() + 2, 287, $this->GetY() + 2); // Landscape line
        $this->Ln(8);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128);
        // Generation timestamp
        $timestamp = date('Y-m-d H:i:s');
        $this->Cell(0, 10, 'Generated on: ' . $timestamp, 0, 0, 'L');
        // Pagination
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'R');
    }
}

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

$pdf = new SchoolPDF('L', 'mm', 'A4'); // Landscape for timetable
$pdf->schoolName = strtoupper($schoolName);
$pdf->schoolAddress = $schoolAddress;
$pdf->AliasNbPages();

if (empty($matrix)) {
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'No Timetable Data Found.', 0, 1, 'C');
} else {
    $numDays = count($operationalDays);
    $periodColWidth = 35;
    $dayColWidth = 242 / ($numDays > 0 ? $numDays : 1);

    ksort($matrix);

    foreach ($matrix as $streamId => $streamData) {
        $pdf->AddPage();
        
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 10, "Master Timetable - Stream: " . strtoupper($streamId), 0, 1, 'C');
        $pdf->Ln(2);

        // Table Header
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(4, 120, 87); // Dark Green
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetLineWidth(0.3);

        // Header Row
        $pdf->Cell($periodColWidth, 10, 'TIME', 1, 0, 'C', true);
        foreach ($operationalDays as $day) {
            $pdf->Cell($dayColWidth, 10, strtoupper($day), 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Table Body
        $pdf->SetTextColor(0, 0, 0);
        foreach ($periods as $p) {
            $periodLabel = date('H:i', strtotime($p['start_time'])) . ' - ' . date('H:i', strtotime($p['end_time']));
            
            if ($p['is_break']) {
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->SetFillColor(241, 245, 249); // Light Gray
                $pdf->Cell($periodColWidth, 8, $periodLabel, 1, 0, 'C', true);
                $pdf->Cell(242, 8, strtoupper($p['period_name']), 1, 1, 'C', true);
                continue;
            }

            // Normal Period
            $pdf->SetFont('Arial', '', 8);
            
            $rowHeight = 12;
            $startX = $pdf->GetX();
            $startY = $pdf->GetY();
            
            $pageHeight = $pdf->GetPageHeight();
            $bottomMargin = 15;
            if ($startY + $rowHeight > $pageHeight - $bottomMargin) {
                $pdf->AddPage();
                $startY = $pdf->GetY();
                $startX = $pdf->GetX();
            }

            $pdf->SetFillColor(255, 255, 255);
            
            // Draw Time Cell
            $pdf->SetXY($startX, $startY);
            $pdf->Cell($periodColWidth, $rowHeight, $periodLabel, 1, 0, 'C');

            $currX = $startX + $periodColWidth;
            foreach ($operationalDays as $day) {
                $pdf->SetXY($currX, $startY);
                $slot = $streamData[$day][$p['id']] ?? null;
                
                // Draw Cell Border
                $pdf->Cell($dayColWidth, $rowHeight, '', 1, 0, 'C');
                
                if ($slot) {
                    $pdf->SetXY($currX, $startY + 1);
                    $pdf->SetFont('Arial', 'B', 8);
                    $pdf->Cell($dayColWidth, 5, utf8_decode(substr($slot['subject_name'], 0, 20)), 0, 2, 'C');
                    $pdf->SetFont('Arial', '', 7);
                    $pdf->SetTextColor(100, 116, 139);
                    $pdf->Cell($dayColWidth, 5, utf8_decode(substr($slot['teacher_name'], 0, 25)), 0, 0, 'C');
                    $pdf->SetTextColor(0, 0, 0);
                }
                
                $currX += $dayColWidth;
            }
            $pdf->Ln($rowHeight);
        }
    }
}

// Clean output buffer before PDF generation
if (ob_get_length()) {
    ob_end_clean();
}
$pdf->Output('I', 'Master_Timetable.pdf');
?>
