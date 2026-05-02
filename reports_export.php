<?php
declare(strict_types=1);

require_once __DIR__ . '/require_admin.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/reports_helpers.php';
require_once __DIR__ . '/lib/fpdf.php';

$format = strtolower(trim((string) ($_GET['format'] ?? '')));
if ($format !== 'csv' && $format !== 'pdf') {
    header('HTTP/1.1 400 Bad Request');
    echo 'Invalid format. Use csv or pdf.';
    exit;
}

$filters = reports_parse_filters($_GET);

if ($filters['report'] === 'donor') {
    $rows = reports_fetch_donor_rows($conn, $filters);
    $summary = reports_donor_summary($rows);
} else {
    $invData = reports_fetch_inventory_data($conn, $filters);
}

$conn->close();

$stamp = date('Y-m-d_His');
$safeUser = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) ($_SESSION['user'] ?? 'admin'));

/**
 * @param mixed $s
 */
function reports_pdf_cell_str($s): string
{
    $t = (string) $s;
    $o = @iconv('UTF-8', 'windows-1252//TRANSLIT', $t);
    return $o !== false ? $o : $t;
}

if ($format === 'csv') {
    $fname = $filters['report'] === 'donor'
        ? "donor-report_{$stamp}.csv"
        : "inventory-report_{$stamp}.csv";

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    if ($filters['report'] === 'donor') {
        fputcsv($out, ['EVSU-OCC BDMS — Donor activity report']);
        fputcsv($out, ['Generated', date('c'), 'User', $safeUser]);
        fputcsv($out, ['Date from', $filters['date_from'], 'Date to', $filters['date_to']]);
        fputcsv($out, ['Blood type filter', $filters['blood_type'] ?: 'All', 'Classification', $filters['classification'] ?: 'All']);
        fputcsv($out, ['Total donors', (string) $summary['total']]);
        fputcsv($out, []);
        fputcsv($out, ['Name', 'Email', 'Contact', 'Classification', 'Blood type', 'Gender', 'Age', 'Collection date', 'Donation date', '# Donations', 'Status']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['name'] ?? '',
                $r['email'] ?? '',
                $r['contact_number'] ?? '',
                $r['classification'] ?? '',
                $r['blood_type'] ?? '',
                $r['gender'] ?? '',
                (string) ($r['age'] ?? ''),
                $r['collection_date'] ?? '',
                $r['donation_date'] ?? '',
                (string) ($r['number_of_donations'] ?? ''),
                $r['donation_status'] ?? '',
            ]);
        }
    } else {
        fputcsv($out, ['EVSU-OCC BDMS — Blood inventory report']);
        fputcsv($out, ['Generated', date('c'), 'User', $safeUser]);
        fputcsv($out, ['Date from', $filters['date_from'], 'Date to', $filters['date_to']]);
        fputcsv($out, ['Blood type filter', $filters['blood_type'] ?: 'All', 'Classification', $filters['classification'] ?: 'All']);
        fputcsv($out, ['Total available ml', (string) $invData['totals']['available_ml'], 'Total expired ml', (string) $invData['totals']['expired_ml']]);
        fputcsv($out, []);
        fputcsv($out, ['Section', 'Blood type', 'Available ml', 'Expired ml', 'Donor rows', 'Donations vol ml', 'Donation events']);
        foreach ($invData['stock_by_type'] as $s) {
            fputcsv($out, [
                'Current stock',
                $s['blood_type'],
                (string) $s['ml_available'],
                (string) $s['ml_expired'],
                (string) $s['donor_rows'],
                '',
                '',
            ]);
        }
        foreach ($invData['donations_period'] as $d) {
            fputcsv($out, [
                'Donations in period',
                $d['blood_type'],
                '',
                '',
                '',
                (string) $d['total_ml'],
                (string) $d['donation_count'],
            ]);
        }
    }

    fclose($out);
    exit;
}

// PDF
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->SetAuthor('EVSU-OCC BDMS');
$pdf->SetCreator('EVSU-OCC BDMS');
$pdf->SetTitle($filters['report'] === 'donor' ? 'Donor report' : 'Inventory report');

$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 14);
$title = $filters['report'] === 'donor' ? 'Donor activity report' : 'Blood inventory report';
$pdf->Cell(0, 10, reports_pdf_cell_str($title), 0, 1);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 6, reports_pdf_cell_str('Generated ' . date('Y-m-d H:i') . ' — User: ' . $safeUser), 0, 1);
$pdf->Cell(0, 6, reports_pdf_cell_str('Period ' . $filters['date_from'] . ' to ' . $filters['date_to']), 0, 1);
$pdf->Cell(
    0,
    6,
    reports_pdf_cell_str(
        'Filters — Blood: ' . ($filters['blood_type'] ?: 'All') . ', Classification: ' . ($filters['classification'] ?: 'All')
    ),
    0,
    1
);
$pdf->Ln(4);

if ($filters['report'] === 'donor') {
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(50, 7, 'Name', 1);
    $pdf->Cell(38, 7, 'Contact', 1);
    $pdf->Cell(22, 7, 'Class', 1);
    $pdf->Cell(14, 7, 'Blood', 1);
    $pdf->Cell(24, 7, 'Collection', 1);
    $pdf->Cell(16, 7, '#', 1);
    $pdf->Cell(22, 7, 'Status', 1);
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 7);
    foreach ($rows as $r) {
        if ($pdf->GetY() > 185) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(50, 7, 'Name', 1);
            $pdf->Cell(38, 7, 'Contact', 1);
            $pdf->Cell(22, 7, 'Class', 1);
            $pdf->Cell(14, 7, 'Blood', 1);
            $pdf->Cell(24, 7, 'Collection', 1);
            $pdf->Cell(16, 7, '#', 1);
            $pdf->Cell(22, 7, 'Status', 1);
            $pdf->Ln();
            $pdf->SetFont('Arial', '', 7);
        }
        $nm = (string) ($r['name'] ?? '');
        if (function_exists('mb_substr')) {
            $nm = mb_strlen($nm) > 40 ? mb_substr($nm, 0, 37) . '...' : $nm;
        } elseif (strlen($nm) > 40) {
            $nm = substr($nm, 0, 37) . '...';
        }
        $pdf->Cell(50, 6, reports_pdf_cell_str($nm), 1);
        $pdf->Cell(38, 6, reports_pdf_cell_str((string) ($r['contact_number'] ?? '')), 1);
        $pdf->Cell(22, 6, reports_pdf_cell_str((string) ($r['classification'] ?? '')), 1);
        $pdf->Cell(14, 6, reports_pdf_cell_str((string) ($r['blood_type'] ?? '')), 1);
        $pdf->Cell(24, 6, reports_pdf_cell_str((string) ($r['collection_date'] ?? '')), 1);
        $pdf->Cell(16, 6, reports_pdf_cell_str((string) ($r['number_of_donations'] ?? '')), 1);
        $st = (string) ($r['donation_status'] ?? '');
        if (function_exists('mb_substr')) {
            $st = mb_strlen($st) > 14 ? mb_substr($st, 0, 11) . '...' : $st;
        } elseif (strlen($st) > 14) {
            $st = substr($st, 0, 11) . '...';
        }
        $pdf->Cell(22, 6, reports_pdf_cell_str($st), 1);
        $pdf->Ln();
    }
    $pdf->Ln(6);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, reports_pdf_cell_str('Total donors: ' . $summary['total']), 0, 1);
} else {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, reports_pdf_cell_str('Totals — Available ml: ' . $invData['totals']['available_ml'] . ' — Expired ml: ' . $invData['totals']['expired_ml']), 0, 1);
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 7, reports_pdf_cell_str('Current stock by blood type'), 0, 1);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(28, 6, 'Blood', 1);
    $pdf->Cell(32, 6, 'Available ml', 1);
    $pdf->Cell(32, 6, 'Expired ml', 1);
    $pdf->Cell(28, 6, 'Donor rows', 1);
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 8);
    foreach ($invData['stock_by_type'] as $s) {
        if ($pdf->GetY() > 185) {
            $pdf->AddPage();
        }
        $pdf->Cell(28, 6, reports_pdf_cell_str($s['blood_type']), 1);
        $pdf->Cell(32, 6, (string) $s['ml_available'], 1);
        $pdf->Cell(32, 6, (string) $s['ml_expired'], 1);
        $pdf->Cell(28, 6, (string) $s['donor_rows'], 1);
        $pdf->Ln();
    }
    $pdf->Ln(4);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 7, reports_pdf_cell_str('Donations recorded in period'), 0, 1);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(28, 6, 'Blood', 1);
    $pdf->Cell(40, 6, 'Volume ml', 1);
    $pdf->Cell(40, 6, 'Events', 1);
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 8);
    foreach ($invData['donations_period'] as $d) {
        if ($pdf->GetY() > 185) {
            $pdf->AddPage();
        }
        $pdf->Cell(28, 6, reports_pdf_cell_str($d['blood_type']), 1);
        $pdf->Cell(40, 6, (string) $d['total_ml'], 1);
        $pdf->Cell(40, 6, (string) $d['donation_count'], 1);
        $pdf->Ln();
    }
}

$outName = $filters['report'] === 'donor' ? "donor-report-{$stamp}.pdf" : "inventory-report-{$stamp}.pdf";
$pdf->Output('D', $outName);
exit;
