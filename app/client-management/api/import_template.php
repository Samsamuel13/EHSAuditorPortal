<?php
/**
 * client-management/api/import_template.php
 *
 * GET -> streams a .xlsx template for the bulk import tool: one header row,
 * one example row, and a second reference sheet listing the scheme type
 * names currently configured (pulled live from cm_scheme_types) so the
 * person filling it in knows exactly what's valid.
 *
 * Uses PhpSpreadsheet exactly the way the scheduling system's
 * api/export_xlsx.php already does — this is a shared third-party library
 * via Composer, not shared application PHP, so it doesn't conflict with
 * the module's isolation requirement.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/cm_helpers.php';

ehs_require_role(['super_admin', 'admin'], true);

$vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    cm_json_error('Import/export dependencies are not installed. Run "composer install" on the server.', 500);
}
require_once $vendorAutoload;

$db = get_db();

$headers = [
    'company_name', 'uen_registration_no', 'industry_sector', 'address', 'contact_person',
    'contact_designation', 'phone', 'email', 'website', 'client_status',
    'scheme_type_name', 'accreditation_body', 'certificate_number',
    'issue_date', 'surveillance_1_date', 'surveillance_2_date', 'expiry_date',
    'cycle_stage', 'cert_status', 'responsible_person_name', 'notes',
];

$example = [
    'Acme Manufacturing Pte Ltd', '201234567A', 'Manufacturing', '1 Example Ave, Singapore 123456',
    'Jane Tan', 'HSE Manager', '+65 9123 4567', 'jane.tan@acme.example', 'https://acme.example', 'active',
    'ISO 9001', 'JAS-ANZ', 'CERT-2026-0001', '2026-01-15', '2027-01-15', '2028-01-15', '2029-01-14',
    'initial', 'active', 'John Lim', 'Migrated from legacy Excel planner',
];

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Clients_Certifications');

foreach ($headers as $i => $h) {
    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
    $sheet->setCellValue($col . '1', $h);
    $sheet->setCellValue($col . '2', $example[$i]);
    $sheet->getColumnDimension($col)->setWidth(20);
}
$sheet->getStyle('A1:U1')->getFont()->setBold(true);
$sheet->freezePane('A2');

// One row per client + certification combination — a client with 3
// certifications appears as 3 rows with the same company_name/uen, so
// re-running an export from the old Excel planner naturally becomes one
// row per audit-scheme line. Leave scheme_type_name blank to import a
// client with no certification yet.
$sheet->setCellValue('A4', 'Notes:');
$sheet->setCellValue('A5', '- One row per client+certification. Repeat company_name/uen for a client with multiple certifications.');
$sheet->setCellValue('A6', '- Leave scheme_type_name blank to import a client record with no certification yet.');
$sheet->setCellValue('A7', '- Dates must be YYYY-MM-DD.');
$sheet->setCellValue('A8', '- scheme_type_name must exactly match a name on the "Valid Scheme Types" sheet.');
$sheet->getStyle('A4')->getFont()->setBold(true);

// --- Reference sheet: valid scheme type names, pulled live from the DB ---
$refSheet = $spreadsheet->createSheet();
$refSheet->setTitle('Valid Scheme Types');
$refSheet->setCellValue('A1', 'category');
$refSheet->setCellValue('B1', 'name');
$refSheet->getStyle('A1:B1')->getFont()->setBold(true);

$stmt = $db->query('SELECT category, name FROM cm_scheme_types ORDER BY category, name');
$row = 2;
foreach ($stmt->fetchAll() as $st) {
    $refSheet->setCellValue('A' . $row, $st['category']);
    $refSheet->setCellValue('B' . $row, $st['name']);
    $row++;
}
$refSheet->getColumnDimension('A')->setWidth(14);
$refSheet->getColumnDimension('B')->setWidth(24);

$spreadsheet->setActiveSheetIndex(0);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="cm_import_template.xlsx"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit;