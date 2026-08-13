<?php
/**
 * client-management/api/export_audit_extract.php
 *
 * GET ?month=last|this|next&stage=&scheme_category=&industry=&responsible_person_id=&q=
 * -> streams an .xlsx of the same rows shown in audit_extract.php, so
 *    Pandian Sir's "extract" request can be handed off as a file, not
 *    just viewed on screen. Mirrors export_xlsx.php's PhpSpreadsheet setup.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/cm_helpers.php';

$user = ehs_require_role(['super_admin', 'admin'], true);

$vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    cm_json_error('Export dependencies are not installed. Run "composer install" on the server.', 500);
}
require_once $vendorAutoload;

$db = get_db();

$monthKey = trim((string) ($_GET['month'] ?? 'this'));
if (!in_array($monthKey, ['last', 'this', 'next'], true)) {
    $monthKey = 'this';
}
$stage          = trim((string) ($_GET['stage'] ?? ''));
$schemeCategory = trim((string) ($_GET['scheme_category'] ?? ''));
$industry       = trim((string) ($_GET['industry'] ?? ''));
$responsibleId  = (int) ($_GET['responsible_person_id'] ?? 0);
$q              = trim((string) ($_GET['q'] ?? ''));

$offsetMap = ['last' => -1, 'this' => 0, 'next' => 1];
[$rangeStart, $rangeEnd, $rangeLabel] = cm_month_range($offsetMap[$monthKey]);

$milestoneCols = [
    'initial'         => ['col' => 'cert.issue_date',         'label' => '1st Certification'],
    'surveillance_1'  => ['col' => 'cert.surveillance_1_date', 'label' => 'Surveillance 1'],
    'surveillance_2'  => ['col' => 'cert.surveillance_2_date', 'label' => 'Surveillance 2'],
    'recertification' => ['col' => 'cert.expiry_date',         'label' => 'Recertification'],
];
if ($stage !== '' && !isset($milestoneCols[$stage])) {
    $stage = '';
}
$activeMilestones = $stage !== '' ? [$stage => $milestoneCols[$stage]] : $milestoneCols;

$where  = ["cert.status != 'withdrawn'"];
$params = [];

if ($q !== '') {
    $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
    $where[] = "c.company_name LIKE :q ESCAPE '\\\\'";
    $params['q'] = '%' . $escaped . '%';
}
if ($schemeCategory !== '' && in_array($schemeCategory, ['ISO', 'BizSafe', 'JASANZ', 'Other'], true)) {
    $where[] = 'st.category = :scheme_category';
    $params['scheme_category'] = $schemeCategory;
}
if ($industry !== '') {
    $where[] = 'c.industry_sector = :industry';
    $params['industry'] = $industry;
}
if ($responsibleId > 0) {
    $where[] = 'cert.responsible_person_id = :responsible_id';
    $params['responsible_id'] = $responsibleId;
}

// NOTE: unique placeholder per milestone — real (non-emulated) prepared
// statements reject the same named param appearing twice in one query.
$milestoneConds = [];
foreach ($activeMilestones as $key => $m) {
    $milestoneConds[] = "({$m['col']} BETWEEN :range_start_$key AND :range_end_$key)";
    $params["range_start_$key"] = $rangeStart;
    $params["range_end_$key"]   = $rangeEnd;
}
$where[] = '(' . implode(' OR ', $milestoneConds) . ')';

$whereSql = implode(' AND ', $where);
$sql = "
    SELECT cert.certificate_number, cert.status AS cert_status,
           cert.issue_date, cert.surveillance_1_date, cert.surveillance_2_date, cert.expiry_date,
           c.company_name, c.industry_sector, c.consultant, c.contact_person, c.phone, c.email,
           st.name AS scheme_name, st.category AS scheme_category,
           COALESCE(u.name, cert.responsible_person_name) AS responsible_person
    FROM cm_certifications cert
    JOIN cm_clients c ON c.id = cert.cm_client_id
    JOIN cm_scheme_types st ON st.id = cert.cm_scheme_type_id
    LEFT JOIN users u ON u.id = cert.responsible_person_id
    WHERE $whereSql
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Expand into one row per matching milestone, same as the on-screen extract.
$exportRows = [];
foreach ($rows as $cert) {
    foreach ($activeMilestones as $m) {
        $col = str_replace('cert.', '', $m['col']);
        $date = $cert[$col] ?? null;
        if ($date !== null && $date >= $rangeStart && $date <= $rangeEnd) {
            $exportRows[] = [
                $cert['company_name'], $cert['industry_sector'], $cert['consultant'],
                $cert['contact_person'], $cert['phone'], $cert['email'],
                $cert['scheme_name'], $cert['scheme_category'], $cert['certificate_number'],
                $m['label'], $date, $cert['cert_status'], $cert['responsible_person'],
            ];
        }
    }
}
usort($exportRows, fn($a, $b) => strcmp($a[10], $b[10])); // sort by milestone date

$headers = [
    'Company Name', 'Industry', 'Consultant', 'Contact Person', 'Phone', 'Email',
    'Scheme', 'Scheme Category', 'Certificate #', 'Audit Type Due', 'Due Date', 'Cert Status', 'Responsible Person',
];

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(substr('Audits ' . $rangeLabel, 0, 31));

foreach ($headers as $i => $h) {
    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
    $sheet->setCellValue($col . '1', $h);
    $sheet->getColumnDimension($col)->setWidth(18);
}
$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
$sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
$sheet->getStyle("A1:{$lastCol}1")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFE9EDF5');
$sheet->freezePane('A2');

$rowNum = 2;
foreach ($exportRows as $values) {
    foreach ($values as $i => $v) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
        $sheet->setCellValue($col . $rowNum, $v ?? '');
    }
    $rowNum++;
}

cm_log_activity($user['id'], 'export_audit_extract', 'system', null, "Exported audit extract for $rangeLabel (" . count($exportRows) . ' row(s))');

$filename = 'audit_extract_' . str_replace(' ', '_', $rangeLabel) . '_' . date('His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit;