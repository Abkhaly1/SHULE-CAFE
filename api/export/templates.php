<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Unauthorized access.");
}

$type = $_GET['type'] ?? null;
$format = strtolower($_GET['format'] ?? 'csv');
$filename = 'shule_cafe_templates_export_' . date('Y-m-d') . ($format === 'excel' ? '.xlsx' : ($format === 'pdf' ? '.pdf' : '.csv'));

if ($format === 'pdf') {
    require_once 'fpdf/fpdf.php';
    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'SHULE CAFE Templates Export', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'L');
    $pdf->Ln(4);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(230, 236, 242);
    $headers = ['ID', 'Type', 'Name', 'Code', 'Education Level', 'Description', 'Status', 'Created At'];
    $widths = [25, 25, 45, 30, 35, 55, 24, 35];
    foreach ($headers as $i => $header) {
        $pdf->Cell($widths[$i], 8, $header, 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 8);

    try {
        $sql = "SELECT * FROM academic_templates";
        $params = [];
        if ($type) {
            $sql .= " WHERE type = ?";
            $params[] = $type;
        }
        $sql .= " ORDER BY type ASC, name ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        $levelNames = [
            'PRIM' => 'Primary Education',
            'O-LEVEL' => 'Ordinary Level (O-Level)',
            'A-LEVEL' => 'Advanced Level (A-Level)'
        ];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $values = [
                $row['id'],
                ucfirst($row['type']),
                $row['name'],
                $row['code'] ?: 'N/A',
                $levelNames[$row['level_code']] ?? ($row['level_code'] ?: 'General / All'),
                $row['description'] ?: '-',
                ucfirst($row['status']),
                $row['created_at']
            ];
            foreach ($values as $i => $value) {
                $pdf->Cell($widths[$i], 7, substr($value, 0, 32), 1);
            }
            $pdf->Ln();
        }
    } catch (PDOException $e) {
        $pdf->Cell(0, 7, 'Error exporting data.', 1);
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $pdf->Output('I', $filename);
    exit();
}

if ($format === 'excel') {
    require_once 'xlsxwriter.class.php';
    $writer = new XLSXWriter();
    $writer->writeSheetHeader('Templates', [
        'ID' => 'string',
        'Type' => 'string',
        'Name' => 'string',
        'Code' => 'string',
        'Education Level' => 'string',
        'Description' => 'string',
        'Status' => 'string',
        'Created At' => 'string'
    ]);

    try {
        $sql = "SELECT * FROM academic_templates";
        $params = [];
        if ($type) {
            $sql .= " WHERE type = ?";
            $params[] = $type;
        }
        $sql .= " ORDER BY type ASC, name ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        $levelNames = [
            'PRIM' => 'Primary Education',
            'O-LEVEL' => 'Ordinary Level (O-Level)',
            'A-LEVEL' => 'Advanced Level (A-Level)'
        ];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $writer->writeSheetRow('Templates', [
                $row['id'],
                ucfirst($row['type']),
                $row['name'],
                $row['code'] ?: 'N/A',
                $levelNames[$row['level_code']] ?? ($row['level_code'] ?: 'General / All'),
                $row['description'] ?: '-',
                ucfirst($row['status']),
                $row['created_at']
            ]);
        }
    } catch (PDOException $e) {
        // If export fails, return an empty Excel file.
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $writer->writeToStdOut();
    exit();
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);
$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Type', 'Name', 'Code', 'Education Level', 'Description', 'Status', 'Created At']);

try {
    $sql = "SELECT * FROM academic_templates";
    $params = [];
    if ($type) {
        $sql .= " WHERE type = ?";
        $params[] = $type;
    }
    $sql .= " ORDER BY type ASC, name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $levelNames = [
        'PRIM' => 'Primary Education',
        'O-LEVEL' => 'Ordinary Level (O-Level)',
        'A-LEVEL' => 'Advanced Level (A-Level)'
    ];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            ucfirst($row['type']),
            $row['name'],
            $row['code'] ?: 'N/A',
            $levelNames[$row['level_code']] ?? ($row['level_code'] ?: 'General / All'),
            $row['description'] ?: '-',
            ucfirst($row['status']),
            $row['created_at']
        ]);
    }
} catch (PDOException $e) {
    fputcsv($output, ['Error exporting data']);
}

fclose($output);
exit();
?>
