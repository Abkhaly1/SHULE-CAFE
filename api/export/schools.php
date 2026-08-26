<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Unauthorized access.");
}

$format = strtolower($_GET['format'] ?? 'csv');
$filename = 'shule_cafe_schools_export_' . date('Y-m-d') . ($format === 'excel' ? '.xlsx' : ($format === 'pdf' ? '.pdf' : '.csv'));

if ($format === 'pdf') {
    require_once 'fpdf/fpdf.php';
    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'SHULE CAFE Schools Export', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'L');
    $pdf->Ln(4);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(230, 236, 242);
    $headers = ['ID', 'School Name', 'Type', 'Region', 'Headmaster Name', 'Headmaster Phone', 'Status', 'Created At'];
    $widths = [25, 55, 25, 35, 45, 35, 24, 40];
    foreach ($headers as $i => $header) {
        $pdf->Cell($widths[$i], 8, $header, 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 8);
    try {
        $stmt = $conn->query(
            "SELECT 
                s.id, 
                s.name, 
                s.type, 
                s.region, 
                u.full_name as headmaster_name,
                u.phone as headmaster_phone,
                s.status,
                s.created_at
            FROM schools s
            LEFT JOIN users u ON u.school_id = s.id AND u.role = 'tenant_admin'
            ORDER BY s.name ASC"
        );

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $values = [
                $row['id'],
                $row['name'],
                $row['type'],
                $row['region'] ?: 'N/A',
                $row['headmaster_name'] ?: 'N/A',
                $row['headmaster_phone'] ?: 'N/A',
                ucfirst($row['status']),
                $row['created_at']
            ];

            foreach ($values as $i => $value) {
                $pdf->Cell($widths[$i], 7, substr($value, 0, 30), 1);
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
    $writer->writeSheetHeader('Schools', [
        'ID' => 'string',
        'School Name' => 'string',
        'Type' => 'string',
        'Region' => 'string',
        'Headmaster Name' => 'string',
        'Headmaster Phone' => 'string',
        'Status' => 'string',
        'Created At' => 'string'
    ]);

    try {
        $stmt = $conn->query(
            "SELECT 
                s.id, 
                s.name, 
                s.type, 
                s.region, 
                u.full_name as headmaster_name,
                u.phone as headmaster_phone,
                s.status,
                s.created_at
            FROM schools s
            LEFT JOIN users u ON u.school_id = s.id AND u.role = 'tenant_admin'
            ORDER BY s.name ASC"
        );

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $writer->writeSheetRow('Schools', [
                $row['id'],
                $row['name'],
                $row['type'],
                $row['region'] ?: 'N/A',
                $row['headmaster_name'] ?: 'N/A',
                $row['headmaster_phone'] ?: 'N/A',
                ucfirst($row['status']),
                $row['created_at']
            ]);
        }
    } catch (PDOException $e) {
        // If export fails, fall back to an empty file.
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $writer->writeToStdOut();
    exit();
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

// Header row
fputcsv($output, ['ID', 'School Name', 'Type', 'Region', 'Headmaster Name', 'Headmaster Phone', 'Status', 'Created At']);

try {
    $stmt = $conn->query("
        SELECT 
            s.id, 
            s.name, 
            s.type, 
            s.region, 
            u.full_name as headmaster_name,
            u.phone as headmaster_phone,
            s.status,
            s.created_at
        FROM schools s
        LEFT JOIN users u ON u.school_id = s.id AND u.role = 'tenant_admin'
        ORDER BY s.name ASC
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['name'],
            $row['type'],
            $row['region'] ?: 'N/A',
            $row['headmaster_name'] ?: 'N/A',
            $row['headmaster_phone'] ?: 'N/A',
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
