<?php
/**
 * client-management/api/export_xlsx.php
 *
 * GET ?q=&industry=&status=&scheme_category=&scheme_type_id=&expiring_within_days=
 * -> streams a filtered .xlsx of clients + their certifications (one row per
 * client+certification, same shape as the bulk-import template, so a file
 * exported here could be re-imported elsewhere). Filters mirror
 * api/clients.php and api/renewal_dashboard.php for consistency.
 *
 * Uses PhpSpreadsheet, the same Composer dependency the scheduling system's
 * own api/export_xlsx.php already relies on.
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

$q              = trim((string) ($_GET['q'] ?? ''));
$industry       = trim((string) ($_GET['industry'] ?? ''));
$status         = trim((string) ($_GET['status'] ?? ''));
$schemeCategory = trim((string) ($_GET['scheme_category'] ?? ''));
$schemeTypeId   = (int) ($_GET['scheme_type_id'] ?? 0);
$expiringWithinDays = isset($_GET['expiring_within_days']) ? (int) $_GET['expiring_within_days'] : null;

$where  = [];
$params = [];

if ($q !== '') {
    $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
    $where[] = "(c.company_name LIKE :q ESCAPE '\\\\' OR c.uen_registration_no LIKE :q2 ESCAPE '\\\\')";
    $params['q'] = '%' . $escaped . '%';
    $params['q2'] = '%' . $escaped . '%';
}
if ($industry !== '') {
    $where[] = 'c.industry_sector = :industry';
    $params['industry'] = $industry;
}
if ($status !== '' && in_array($status, ['active', 'suspended', 'withdrawn', 'blacklisted'], true)) {
    $where[] = 'c.status = :status';
    $params['status'] = $status;
}
if ($schemeCategory !== '' && in_array($schemeCategory, ['ISO', 'BizSafe', 'JASANZ', 'Other'], true)) {
    $where[] = 'st.category = :scheme_category';
    $params['scheme_category'] = $schemeCategory;
}
if ($schemeTypeId > 0) {
    $where[] = 'st.id = :scheme_type_id';
    $params['scheme_type_id'] = $schemeTypeId;
}
if ($expiringWithinDays !== null && $expiringWithinDays >= 0) {
    $where[] = 'cert.expiry_date IS NOT NULL AND cert.expiry_date <= DATE_ADD(CURDATE(), INTERVAL :expiring_days DAY)';
    $params['expiring_days'] = $expiringWithinDays;
}

// LEFT JOIN certifications so a client with none still appears once with
// blank certification columns, rather than disappearing from the export.
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$sql = "
    SELECT
        c.company_name, c.uen_registration_no, c.industry_sector, c.address, c.contact_person,
        c.contact_designation, c.phone, c.email, c.website, c.status AS client_status,
        st.name AS scheme_type_name, cert.accreditation_body, cert.certificate_number,
        cert.issue_date, cert.expiry_date, cert.cycle_stage, cert.status AS cert_status,
        COALESCE(u.name, cert.responsible_person_name) AS responsible_person, cert.notes AS cert_notes
    FROM cm_clients c
    LEFT JOIN cm_certifications cert ON cert.cm_client_id = c.id
    LEFT JOIN cm_scheme_types st ON st.id = cert.cm_scheme_type_id
    LEFT JOIN users u ON u.id = cert.responsible_person_id
    $whereSql
    ORDER BY c.company_name, cert.expiry_date
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$headers = [
    'Company Name', 'UEN', 'Industry', 'Address', 'Contact Person', 'Designation', 'Phone', 'Email', 'Website', 'Client Status',
    'Scheme', 'Accreditation Body', 'Certificate #', 'Issue Date', 'Expiry Date', 'Cycle Stage', 'Cert Status', 'Responsible Person', 'Notes',
];

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Clients & Certifications');

foreach ($headers as $i => $h) {
    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
    $sheet->setCellValue($col . '1', $h);
    $sheet->getColumnDimension($col)->setWidth(18);
}
$sheet->getStyle('A1:S1')->getFont()->setBold(true);
$sheet->getStyle('A1:S1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFE9EDF5');
$sheet->freezePane('A2');

$rowNum = 2;
foreach ($rows as $r) {
    $values = [
        $r['company_name'], $r['uen_registration_no'], $r['industry_sector'], $r['address'], $r['contact_person'],
        $r['contact_designation'], $r['phone'], $r['email'], $r['website'], $r['client_status'],
        $r['scheme_type_name'], $r['accreditation_body'], $r['certificate_number'],
        $r['issue_date'], $r['expiry_date'], $r['cycle_stage'], $r['cert_status'], $r['responsible_person'], $r['cert_notes'],
    ];
    foreach ($values as $i => $v) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
        $sheet->setCellValue($col . $rowNum, $v ?? '');
    }
    $rowNum++;
}

cm_log_activity($user['id'], 'export_xlsx', 'system', null, 'Exported ' . count($rows) . ' row(s)');

$filename = 'cm_clients_certifications_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit;
