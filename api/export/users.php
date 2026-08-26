<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized access.');
}

$role = $_GET['role'] ?? 'student';
$format = strtolower($_GET['format'] ?? 'csv');
$sessionSchoolId = isset($_SESSION['school_id']) ? trim((string) $_SESSION['school_id']) : null;
if ($sessionSchoolId === '') {
    $sessionSchoolId = null;
}
$hasSchoolFilter = $sessionSchoolId !== null && $sessionSchoolId !== '';

function normalizeGender($gender) {
    $value = trim((string) $gender);
    if ($value === '') {
        return 'N/A';
    }

    $normalized = strtolower($value);
    $map = [
        'm' => 'Male',
        'male' => 'Male',
        'man' => 'Male',
        'boy' => 'Male',
        'f' => 'Female',
        'female' => 'Female',
        'woman' => 'Female',
        'girl' => 'Female',
        'other' => 'Other',
        'non-binary' => 'Other',
        'prefer not to say' => 'Prefer not to say',
        'prefer_not_to_say' => 'Prefer not to say',
        'unknown' => 'Unknown',
        'n/a' => 'N/A',
        'na' => 'N/A',
        'null' => 'N/A',
    ];

    if (isset($map[$normalized])) {
        return $map[$normalized];
    }

    return ucfirst($value);
}

$schoolName = 'Platform Wide System';
if ($hasSchoolFilter) {
    $sStmt = $conn->prepare('SELECT name FROM schools WHERE id = ?');
    $sStmt->execute([$sessionSchoolId]);
    $schoolName = $sStmt->fetchColumn() ?: 'SHULE CAFE School';
}

$roleTitles = [
    'student' => 'STUDENTS OFFICIAL ROSTER EXPORT',
    'teacher' => 'TEACHERS & STAFF OFFICIAL DIRECTORY EXPORT',
    'parent' => 'PARENTS & GUARDIANS DIRECTORY EXPORT'
];

$reportTitle = $roleTitles[$role] ?? 'USERS EXPORT REPORT';
$filename = sprintf(
    '%s_%s_%s.%s',
    strtolower(str_replace(' ', '_', $schoolName)),
    $role,
    date('Y_m_d'),
    $format === 'excel' ? 'xlsx' : ($format === 'pdf' ? 'pdf' : 'csv')
);

function getUserQuery(string $role, ?string $schoolId, array &$headers, array &$widths) {
    $schoolFilter = ($schoolId !== null && $schoolId !== '') ? 'AND u.school_id = ?' : '';

    if ($role === 'teacher') {
        $headers = ['S/N', 'Staff ID', 'Full Name', 'Gender', 'Department', 'Phone', 'Email'];
        $widths = [10, 28, 72, 18, 42, 40, 60];
        return 'SELECT u.user_code, u.full_name, u.gender, u.department, u.phone, u.email FROM users u WHERE u.role = \'teacher\' ' . $schoolFilter . ' ORDER BY u.full_name ASC';
    }

    if ($role === 'parent') {
        $headers = ['S/N', 'Full Name', 'Gender', 'Phone', 'Email'];
        $widths = [10, 96, 20, 60, 68];
        return 'SELECT u.full_name, u.gender, u.phone, u.email FROM users u WHERE u.role = \'parent\' ' . $schoolFilter . ' ORDER BY u.full_name ASC';
    }

    $headers = ['S/N', 'Student ID', 'Full Name', 'Gender', 'Class Level', 'Guardian Phone', 'Email'];
    $widths = [10, 30, 72, 18, 32, 38, 64];
    return 'SELECT u.user_code, u.full_name, u.gender, u.phone, u.email, c.name as class_name FROM users u LEFT JOIN classes c ON u.class_id = c.id WHERE u.role = \'student\' ' . $schoolFilter . ' ORDER BY u.full_name ASC';
}

if ($format === 'pdf') {
    require_once __DIR__ . '/fpdf/fpdf.php';

    class ExportPDF extends FPDF {
        public $reportTitle = '';
        public $reportSubTitle = '';
        public $headers = [];
        public $widths = [];

        function Header() {
            $this->SetFillColor(12, 60, 128);
            $this->Rect(0, 0, $this->GetPageWidth(), 34, 'F');
            $this->SetXY(0, 8);
            $this->SetTextColor(255);
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 8, $this->reportTitle, 0, 1, 'C');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 6, $this->reportSubTitle, 0, 1, 'C');
            $this->Ln(4);
            $this->SetTextColor(0);
        }

        function TableHeader() {
            $this->SetFont('Arial', 'B', 9);
            $this->SetFillColor(244, 246, 249);
            $this->SetDrawColor(186);
            $this->SetTextColor(33);
            foreach ($this->headers as $i => $header) {
                $this->Cell($this->widths[$i], 10, $header, 1, 0, 'C', true);
            }
            $this->Ln();
        }

        function GetLeftMargin() {
            return $this->lMargin;
        }

        function GetRightMargin() {
            return $this->rMargin;
        }

        function Footer() {
            $this->SetY(-12);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' / {nb}', 0, 0, 'C');
        }

        function AcceptPageBreak() {
            $this->TableHeader();
            return true;
        }
    }

    $pdfHeaders = [];
    $pdfWidths = [];
    $sql = getUserQuery($role, $sessionSchoolId, $pdfHeaders, $pdfWidths);

    $pdf = new ExportPDF('L', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->SetMargins(14, 14, 14);
    $pdf->SetAutoPageBreak(true, 24);
    $pdf->reportTitle = $reportTitle;
    $pdf->reportSubTitle = 'School: ' . $schoolName . ' | Generated: ' . date('Y-m-d H:i:s');
    $pdf->headers = $pdfHeaders;
    $pdf->widths = $pdfWidths;

    $countSql = 'SELECT COUNT(*) FROM users u WHERE u.role = ? ' . ($hasSchoolFilter ? 'AND u.school_id = ?' : '');
    $countParams = $hasSchoolFilter ? [$role, $sessionSchoolId] : [$role];
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($countParams);
    $recordCount = (int) $countStmt->fetchColumn();

    $genderSql = 'SELECT
            SUM(CASE WHEN UPPER(TRIM(COALESCE(u.gender, \'\'))) IN (\'M\', \'MALE\') THEN 1 ELSE 0 END) AS male,
            SUM(CASE WHEN UPPER(TRIM(COALESCE(u.gender, \'\'))) IN (\'F\', \'FEMALE\') THEN 1 ELSE 0 END) AS female
        FROM users u
        WHERE u.role = ? ' . ($hasSchoolFilter ? 'AND u.school_id = ?' : '');
    $genderStmt = $conn->prepare($genderSql);
    $genderStmt->execute($countParams);
    $genderCounts = $genderStmt->fetch(PDO::FETCH_ASSOC);
    $maleCount = (int) ($genderCounts['male'] ?? 0);
    $femaleCount = (int) ($genderCounts['female'] ?? 0);
    $otherCount = max(0, $recordCount - $maleCount - $femaleCount);
    $genderLabel = 'M: ' . $maleCount . ' | F: ' . $femaleCount;
    if ($otherCount > 0) {
        $genderLabel .= ' | O: ' . $otherCount;
    }

    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 9);

    $panelValues = [
        ['Export Type', ucfirst($role)],
        ['Total Records', number_format($recordCount)],
        ['Gender Breakdown', $genderLabel],
        ['School', strlen($schoolName) > 30 ? substr($schoolName, 0, 30) . '...' : $schoolName]
    ];
    $pageWidth = $pdf->GetPageWidth() - 28;
    $gap = 5;
    $panelWidth = floor(($pageWidth - 3 * $gap) / 4);
    $panelHeight = 22;
    $panelX = 14;
    $panelY = $pdf->GetY();

    foreach ($panelValues as $panel) {
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetDrawColor(210, 216, 230);
        $pdf->Rect($panelX, $panelY, $panelWidth, $panelHeight, 'DF');
        $pdf->SetXY($panelX + 4, $panelY + 4);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell($panelWidth - 8, 5, $panel[0], 0, 2);
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell($panelWidth - 8, 5, $panel[1], 0, 'L');
        $panelX += $panelWidth + $gap;
    }
    $pdf->SetY($panelY + $panelHeight + 10);

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->TableHeader();
    $pdf->SetFont('Arial', '', 9);

    $stmt = $conn->prepare($sql);
    $stmt->execute($hasSchoolFilter ? [$sessionSchoolId] : []);

    $fill = false;
    $index = 1;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $fill = !$fill;
        $pdf->SetFillColor($fill ? 248 : 255, $fill ? 249 : 255, $fill ? 252 : 255);
        $gender = normalizeGender($row['gender'] ?? '');

        if ($role === 'teacher') {
            $values = [
                $index,
                $row['user_code'] ?? 'N/A',
                $row['full_name'] ?? 'N/A',
                $gender,
                $row['department'] ?? 'N/A',
                $row['phone'] ?? 'N/A',
                $row['email'] ?? 'N/A'
            ];
        } elseif ($role === 'parent') {
            $values = [
                $index,
                $row['full_name'] ?? 'N/A',
                $gender,
                $row['phone'] ?? 'N/A',
                $row['email'] ?? 'N/A'
            ];
        } else {
            $values = [
                $index,
                $row['user_code'] ?? 'N/A',
                $row['full_name'] ?? 'N/A',
                $gender,
                $row['class_name'] ?? 'N/A',
                $row['phone'] ?? 'N/A',
                $row['email'] ?? 'N/A'
            ];
        }

        foreach ($values as $i => $value) {
            $pdf->Cell($pdfWidths[$i], 9, substr($value, 0, 45), 1, 0, 'L', $fill);
        }
        $pdf->Ln();
        $index++;
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $pdf->Output('I', $filename);
    exit();
}

if ($format === 'excel') {
    require_once __DIR__ . '/xlsxwriter.class.php';
    $writer = new XLSXWriter();

    if ($role === 'teacher') {
        $writer->writeSheetHeader('Users', [
            'S/N' => 'string',
            'Staff ID' => 'string',
            'Full Name' => 'string',
            'Gender' => 'string',
            'Department' => 'string',
            'Phone' => 'string',
            'Email' => 'string'
        ]);
        $stmt = $conn->prepare(
            'SELECT u.user_code, u.full_name, u.gender, u.department, u.phone, u.email FROM users u WHERE u.role = \'teacher\' ' . ($hasSchoolFilter ? 'AND u.school_id = ?' : '') . ' ORDER BY u.full_name ASC'
        );
    } elseif ($role === 'parent') {
        $writer->writeSheetHeader('Users', [
            'S/N' => 'string',
            'Full Name' => 'string',
            'Gender' => 'string',
            'Phone' => 'string',
            'Email' => 'string'
        ]);
        $stmt = $conn->prepare(
            'SELECT u.full_name, u.gender, u.phone, u.email FROM users u WHERE u.role = \'parent\' ' . ($hasSchoolFilter ? 'AND u.school_id = ?' : '') . ' ORDER BY u.full_name ASC'
        );
    } else {
        $writer->writeSheetHeader('Users', [
            'S/N' => 'string',
            'Student ID' => 'string',
            'Full Name' => 'string',
            'Gender' => 'string',
            'Class Level' => 'string',
            'Guardian Phone' => 'string',
            'Email' => 'string'
        ]);
        $stmt = $conn->prepare(
            'SELECT u.user_code, u.full_name, u.gender, u.phone, u.email, c.name as class_name FROM users u LEFT JOIN classes c ON u.class_id = c.id WHERE u.role = \'student\' ' . ($hasSchoolFilter ? 'AND u.school_id = ?' : '') . ' ORDER BY u.full_name ASC'
        );
    }

    $params = $hasSchoolFilter ? [$sessionSchoolId] : [];
    $stmt->execute($params);

    $sn = 1;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($role === 'teacher') {
            $writer->writeSheetRow('Users', [
                $sn,
                $row['user_code'] ?? 'TCH/' . date('Y') . '/' . sprintf('%03d', $sn),
                $row['full_name'] ?? 'N/A',
                normalizeGender($row['gender'] ?? ''),
                $row['department'] ?? 'N/A',
                $row['phone'] ?? 'N/A',
                $row['email'] ?? 'N/A'
            ]);
        } elseif ($role === 'parent') {
            $writer->writeSheetRow('Users', [
                $sn,
                $row['full_name'] ?? 'N/A',
                normalizeGender($row['gender'] ?? ''),
                $row['phone'] ?? 'N/A',
                $row['email'] ?? 'N/A'
            ]);
        } else {
            $writer->writeSheetRow('Users', [
                $sn,
                $row['user_code'] ?? 'STD/' . date('Y') . '/' . sprintf('%03d', $sn),
                $row['full_name'] ?? 'N/A',
                normalizeGender($row['gender'] ?? ''),
                $row['class_name'] ?? 'N/A',
                $row['phone'] ?? 'N/A',
                $row['email'] ?? 'N/A'
            ]);
        }
        $sn++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $writer->writeToStdOut();
    exit();
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$output = fopen('php://output', 'w');

fputcsv($output, ['========================================================================================']);
fputcsv($output, ['SHULE CAFE - OFFICIAL SCHOOL DATA EXPORT REPORT']);
fputcsv($output, ['SCHOOL NAME:', $schoolName]);
fputcsv($output, ['REPORT TITLE:', $reportTitle]);
fputcsv($output, ['GENERATED DATE:', date('Y-m-d H:i:s')]);
fputcsv($output, ['GENERATED BY:', $_SESSION['email'] ?? 'School Headmaster']);
fputcsv($output, ['========================================================================================']);
fputcsv($output, []);

try {
    if ($role === 'teacher') {
        fputcsv($output, ['S/N', 'Staff ID', 'Full Name', 'Gender', 'Department', 'Phone', 'Email']);
        $stmt = $conn->prepare(
            'SELECT u.user_code, u.full_name, u.gender, u.department, u.phone, u.email FROM users u WHERE u.role = \'teacher\' ' . ($hasSchoolFilter ? 'AND u.school_id = ?' : '') . ' ORDER BY u.full_name ASC'
        );
    } elseif ($role === 'parent') {
        fputcsv($output, ['S/N', 'Full Name', 'Gender', 'Phone', 'Email']);
        $stmt = $conn->prepare(
            'SELECT u.full_name, u.gender, u.phone, u.email FROM users u WHERE u.role = \'parent\' ' . ($hasSchoolFilter ? 'AND u.school_id = ?' : '') . ' ORDER BY u.full_name ASC'
        );
    } else {
        fputcsv($output, ['S/N', 'Student ID', 'Full Name', 'Gender', 'Class Level', 'Guardian Phone', 'Email']);
        $stmt = $conn->prepare(
            'SELECT u.user_code, u.full_name, u.gender, u.phone, u.email, c.name as class_name FROM users u LEFT JOIN classes c ON u.class_id = c.id WHERE u.role = \'student\' ' . ($hasSchoolFilter ? 'AND u.school_id = ?' : '') . ' ORDER BY u.full_name ASC'
        );
    }

    $params = $hasSchoolFilter ? [$sessionSchoolId] : [];
    $stmt->execute($params);

    $sn = 1;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($role === 'teacher') {
            fputcsv($output, [
                $sn,
                $row['user_code'] ?? 'TCH/' . date('Y') . '/' . sprintf('%03d', $sn),
                $row['full_name'] ?? 'N/A',
                normalizeGender($row['gender'] ?? ''),
                $row['department'] ?? 'N/A',
                $row['phone'] ?? 'N/A',
                $row['email'] ?? 'N/A'
            ]);
        } elseif ($role === 'parent') {
            fputcsv($output, [
                $sn,
                $row['full_name'] ?? 'N/A',
                normalizeGender($row['gender'] ?? ''),
                $row['phone'] ?? 'N/A',
                $row['email'] ?? 'N/A'
            ]);
        } else {
            fputcsv($output, [
                $sn,
                $row['user_code'] ?? 'STD/' . date('Y') . '/' . sprintf('%03d', $sn),
                $row['full_name'] ?? 'N/A',
                normalizeGender($row['gender'] ?? ''),
                $row['class_name'] ?? 'N/A',
                $row['phone'] ?? 'N/A',
                $row['email'] ?? 'N/A'
            ]);
        }
        $sn++;
    }

    fputcsv($output, []);
    fputcsv($output, ['TOTAL REGISTERED RECORDS:', ($sn - 1)]);
} catch (PDOException $e) {
    fputcsv($output, ['Error exporting data: ' . $e->getMessage()]);
}

fclose($output);
exit();
