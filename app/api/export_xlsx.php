<?php
// File: api/export_xlsx.php
/**
 * /api/export_xlsx.php?month=YYYY-MM
 * Streams an .xlsx download shaped like the original Excel planner:
 * row 1 = auditor names, row 2 = their approved schemes, then one row per
 * date with weekend rows shaded blue and holiday rows shaded green — the
 * same two fill colors found in the source workbook.
 *
 * Requires PhpSpreadsheet: `composer require phpoffice/phpspreadsheet`
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/export_data.php';

$user = ehs_require_role(['super_admin', 'admin'], true);

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    ehs_json_error('Export dependencies are not installed. Run "composer require phpoffice/phpspreadsheet" on the server.', 500);
}
require_once $vendorAutoload;

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    ehs_json_error('month must be in YYYY-MM format.', 422);
}

$monthStart = $month . '-01';
$monthEnd = date('Y-m-01', strtotime($monthStart . ' +1 month'));

$db = get_db();
$grid = ehs_build_month_grid($db, $monthStart, $monthEnd);

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(date('M Y', strtotime($monthStart)));

// --- header rows ---
$sheet->setCellValue('A1', 'Date');
$sheet->setCellValue('B1', 'Day');
$sheet->setCellValue('A2', '');
$sheet->setCellValue('B2', 'Approve Schemes');

$col = 3; // column C onward = auditors
foreach ($grid['auditors'] as $auditor) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
    $sheet->setCellValue($colLetter . '1', $auditor['name']);
    $sheet->setCellValue($colLetter . '2', $auditor['scheme_codes']);
    $sheet->getStyle($colLetter . '1')->getFont()->setBold(true);
    $col++;
}
$sheet->getStyle('A1:B1')->getFont()->setBold(true);

// Colors matched to the original workbook's weekend/holiday shading.
$weekendFill = 'FFDEEAF6';
$holidayFill = 'FFE2EFD9';

$row = 3;
foreach ($grid['days'] as $day) {
    $sheet->setCellValue('A' . $row, date('j F Y', strtotime($day['date'])));
    $sheet->setCellValue('B' . $row, $day['weekday_name']);

    $col = 3;
    foreach ($grid['auditors'] as $auditor) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $entries = $grid['cells'][$day['date']][$auditor['id']] ?? [];
        $sheet->setCellValue($colLetter . $row, implode(' / ', $entries));
        $col++;
    }

    $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col - 1);
    $rangeForRow = 'A' . $row . ':' . $lastColLetter . $row;

    if ($day['holiday_name']) {
        $sheet->getStyle($rangeForRow)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB($holidayFill);
        $sheet->getComment('A' . $row)->getText()->createTextRun($day['holiday_name']);
    } elseif ($day['is_weekend']) {
        $sheet->getStyle($rangeForRow)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB($weekendFill);
    }

    $row++;
}

// --- column widths ---
$sheet->getColumnDimension('A')->setWidth(16);
$sheet->getColumnDimension('B')->setWidth(11);
for ($c = 3; $c < $col; $c++) {
    $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
    $sheet->getColumnDimension($letter)->setWidth(22);
}
$sheet->freezePane('C3');

ehs_log_activity($user['id'], 'export_xlsx', 'system', null, $month);

$filename = 'EHS_Universal_Schedule_' . $month . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit;
