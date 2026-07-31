<?php
// File: api/export_pdf.php
/**
 * /api/export_pdf.php?month=YYYY-MM
 * Streams a landscape PDF of the same month grid used for the Excel export —
 * useful for sharing with management who just want a quick printable view.
 *
 * Requires Dompdf: `composer require dompdf/dompdf`
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/export_data.php';

$user = ehs_require_role(['super_admin', 'admin'], true);

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    ehs_json_error('Export dependencies are not installed. Run "composer require dompdf/dompdf" on the server.', 500);
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
$monthLabel = date('F Y', strtotime($monthStart));

function ehs_pdf_escape(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 8px; }
    h1 { font-size: 14px; margin-bottom: 4px; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ccc; padding: 3px 4px; text-align: left; vertical-align: top; }
    th { background: #f3f4f6; }
    tr.weekend td { background: #DEEAF6; }
    tr.holiday td { background: #E2EFD9; }
    .date-col { width: 60px; white-space: nowrap; }
    .day-col { width: 45px; white-space: nowrap; }
</style>
</head>
<body>
    <h1><?= ehs_pdf_escape(APP_NAME) ?> — Schedule for <?= ehs_pdf_escape($monthLabel) ?></h1>
    <table>
        <thead>
            <tr>
                <th class="date-col">Date</th>
                <th class="day-col">Day</th>
                <?php foreach ($grid['auditors'] as $auditor): ?>
                    <th><?= ehs_pdf_escape($auditor['name']) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grid['days'] as $day): ?>
                <?php $rowClass = $day['holiday_name'] ? 'holiday' : ($day['is_weekend'] ? 'weekend' : ''); ?>
                <tr class="<?= $rowClass ?>">
                    <td class="date-col"><?= ehs_pdf_escape(date('j M Y', strtotime($day['date']))) ?></td>
                    <td class="day-col"><?= ehs_pdf_escape(substr($day['weekday_name'], 0, 3)) ?></td>
                    <?php foreach ($grid['auditors'] as $auditor): ?>
                        <?php $entries = $grid['cells'][$day['date']][$auditor['id']] ?? []; ?>
                        <td><?= ehs_pdf_escape(implode(' / ', $entries)) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
<?php
$html = ob_get_clean();

$options = new \Dompdf\Options();
$options->set('isRemoteEnabled', false);
$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A3', 'landscape');
$dompdf->render();

ehs_log_activity($user['id'], 'export_pdf', 'system', null, $month);

$filename = 'EHS_Universal_Schedule_' . $month . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
