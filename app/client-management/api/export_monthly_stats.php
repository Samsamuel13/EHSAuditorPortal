<?php
/**
 * client-management/api/export_monthly_stats.php
 *
 * GET ?format=xlsx|csv|pdf &month=YYYY-MM (or date_from=&date_to=)
 *     &scheme_category=&consultant=&entity=&client_status=
 * -> streams the month-wise micro-stats report (same numbers the
 *    "Monthly Stats" tab on renewal_dashboard.php shows) as a file.
 *
 * Summary vs. detail (2026-09 drill-down feature — confirmed split):
 *   xlsx -> exactly 4 detail sheets, each the actual client/certification
 *           rows behind that section's numbers (TRIMMED 2026-09, confirmed):
 *           Due — Surveillance 1, Due — Surveillance 2, Due — Recert-Expiry,
 *           New Clients. No Summary sheet and no detail sheets for Certs
 *           Issued/Renewed/Expired, by-Scheme/Status/Consultant, Follow-up
 *           Activity, or Followups Actioned — those metrics are unchanged
 *           on the "Monthly Stats" tab itself (cards + inline breakdown/
 *           detail panels), this trim is xlsx-export-only.
 *   csv  -> summary only, deliberately (unaffected by the 2026-09 xlsx
 *           trim above). A CSV is one flat table; the
 *           detail rows behind different metrics have different, unrelated
 *           column shapes (a client row vs. a certification row vs. a
 *           follow-up-activity row), so forcing them into one CSV would be
 *           unreadable rather than useful. Row-level detail is the xlsx's
 *           job for this export.
 *   pdf  -> summary only, deliberately, for print-length/readability —
 *           row-level detail for a large date range could run to many
 *           pages. Use the xlsx for detail rows.
 *
 * NEW FILE — justified per the "extend, don't create silently" preference:
 * every other export in this module (export_xlsx.php, export_audit_extract.php,
 * export_client_pdf.php) is already its own file, separate from the
 * dashboard page it exports from, so this follows that exact existing
 * pattern rather than bolting export/streaming logic into
 * renewal_dashboard.php or its API file.
 *
 * Reuses cm_build_monthly_stats() (includes/cm_helpers.php) — the SAME
 * function api/renewal_dashboard.php's ?view=monthly_stats branch calls —
 * so an exported file can never disagree with what the on-screen tab shows.
 * Reuses PhpSpreadsheet (xlsx/csv) and Dompdf (pdf), the module's existing
 * approved export dependencies — no new libraries introduced.
 *
 * AUTH: gated identically to every other export endpoint in this module —
 * ehs_require_role(['super_admin','admin'], true) runs before any query or
 * output. Hitting this file directly without a valid session returns a 401
 * JSON error and produces no file, exactly like export_xlsx.php /
 * export_audit_extract.php / export_client_pdf.php.
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

$format = strtolower(trim((string) ($_GET['format'] ?? 'xlsx')));
if (!in_array($format, ['xlsx', 'csv', 'pdf'], true)) {
    cm_json_error('format must be one of: xlsx, csv, pdf.', 422);
}

cm_auto_expire_overdue_certifications($db);

$stats = cm_build_monthly_stats($db, [
    'month' => $_GET['month'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'scheme_category' => $_GET['scheme_category'] ?? '',
    'consultant' => $_GET['consultant'] ?? '',
    'entity' => $_GET['entity'] ?? '',
    'client_status' => $_GET['client_status'] ?? '',
]);

$rangeLabel = $stats['range']['label'];
$filterLines = [];
foreach ($stats['filters_applied'] as $k => $v) {
    if ($v !== '') $filterLines[] = ucwords(str_replace('_', ' ', $k)) . ': ' . $v;
}
$filterSummary = $filterLines ? implode(' | ', $filterLines) : 'No filters applied';

/**
 * Flattens the nested $stats payload into one summary sheet's worth of
 * [Section, Metric, Value] rows — shared by the xlsx and csv branches below
 * so their content can't drift apart from each other.
 */
function cm_monthly_stats_rows(array $stats): array
{
    $rows = [];
    $rows[] = ['Summary', 'New clients onboarded', $stats['new_clients']];
    $rows[] = ['Summary', 'Certifications issued', $stats['certifications_issued']];
    $rows[] = ['Summary', 'Certifications renewed', $stats['certifications_renewed']];
    $rows[] = ['Summary', 'Certifications expired', $stats['certifications_expired']];
    $rows[] = ['Summary', 'Avg time-to-renewal (days)', $stats['avg_time_to_renewal_days'] ?? '—'];
    $rows[] = ['Summary', 'Follow-up activity logged (total)', $stats['followup_activity']['total']];
    $rows[] = ['Summary', 'Renewal follow-ups actioned (total)', $stats['followups_actioned']['total']];
    $rows[] = ['Summary', 'Avg days-to-resolution on a follow-up', $stats['followups_actioned']['avg_days_to_resolution'] ?? '—'];

    foreach ($stats['by_scheme_type'] as $k => $v) $rows[] = ['Certifications issued — by scheme type', $k, $v];
    foreach ($stats['by_status'] as $k => $v) $rows[] = ['Certifications issued — by status', $k, $v];
    foreach ($stats['by_consultant'] as $k => $v) $rows[] = ['Certifications issued — by consultant', $k, $v];
    foreach ($stats['followup_activity']['by_type'] as $k => $v) $rows[] = ['Follow-up activity — by type', $k, $v];
    foreach ($stats['followups_actioned']['by_stage'] as $k => $v) $rows[] = ['Follow-ups actioned — by stage', $k, $v];
    foreach ($stats['followups_actioned']['by_resolution'] as $k => $v) $rows[] = ['Follow-ups actioned — by resolution', $k, $v];

    foreach ($stats['due_breakdown'] as $breakdown) {
        $label = $breakdown['label'];
        $rows[] = ["Due this range — {$label}", 'Due (total)', $breakdown['due_total']];
        $rows[] = ["Due this range — {$label}", 'Done', $breakdown['done']];
        $rows[] = ["Due this range — {$label}", 'Incomplete — overdue', $breakdown['incomplete_overdue']];
        $rows[] = ["Due this range — {$label}", 'Incomplete — not yet due', $breakdown['incomplete_pending']];
    }
    return $rows;
}

$dataRows = cm_monthly_stats_rows($stats);

cm_log_activity($user['id'], 'export_monthly_stats', 'system', null, "Exported monthly stats ({$format}) for {$rangeLabel} — {$filterSummary}");

if ($format === 'csv') {
    $filename = 'cm_monthly_stats_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Client Management — Monthly Stats']);
    fputcsv($out, ['Range', $rangeLabel]);
    fputcsv($out, ['Filters', $filterSummary]);
    fputcsv($out, []);
    fputcsv($out, ['Section', 'Metric', 'Value']);
    foreach ($dataRows as $r) fputcsv($out, $r);
    fclose($out);
    exit;
}

if ($format === 'xlsx') {
    // TRIMMED 2026-09 (confirmed): the xlsx export is now exactly 4 sheets
    // — Due–Surveillance 1, Due–Surveillance 2, Due–Recert-Expiry, New
    // Clients — each the actual detail rows behind that metric, same as
    // the fuller export before this trim. Everything else that WAS in this
    // file (the Summary sheet, Certs Issued/Renewed/Expired, by-Scheme/
    // Status/Consultant, Follow-up Activity, Followups Actioned) is gone
    // from the xlsx ONLY — none of those were touched on-screen; the
    // Monthly Stats tab's cards and breakdown panels for all of them are
    // unchanged and still fully there. CSV and PDF are also unaffected —
    // both were already summary-only and stay that way.
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

    $baseDetailArgs = [
        'month' => $_GET['month'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'scheme_category' => $_GET['scheme_category'] ?? '',
        'consultant' => $_GET['consultant'] ?? '',
        'entity' => $_GET['entity'] ?? '',
        'client_status' => $_GET['client_status'] ?? '',
    ];

    /** Writes one detail sheet from a cm_build_monthly_stats_detail_all() result. $cols maps row key => column header, in display order. */
    function cm_write_detail_sheet(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, string $title, array $result, array $cols): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(substr($title, 0, 31)); // Excel's 31-char sheet-name limit
        $colLetters = [];
        $i = 0;
        foreach ($cols as $key => $label) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $colLetters[$key] = $col;
            $sheet->setCellValue($col . '1', $label);
            $sheet->getColumnDimension($col)->setWidth(22);
            $i++;
        }
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($cols));
        $sheet->getStyle('A1:' . $lastCol . '1')->getFont()->setBold(true);
        $sheet->getStyle('A1:' . $lastCol . '1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFE9EDF5');
        $sheet->freezePane('A2');

        $rowNum = 2;
        foreach ($result['rows'] as $row) {
            foreach ($cols as $key => $label) {
                $sheet->setCellValue($colLetters[$key] . $rowNum, $row[$key] ?? '');
            }
            $rowNum++;
        }
        if (empty($result['rows'])) {
            $sheet->setCellValue('A2', 'No rows for this range/filter combination.');
        } elseif (!empty($result['truncated'])) {
            $sheet->setCellValue('A' . ($rowNum + 1), "Note: this sheet is capped at 5000 rows; {$result['total_count']} total rows matched — narrow the date range or filters to see the rest.");
        }
    }

    $dueBreakdownSheetTitles = ['surveillance_1' => 'Due — Surveillance 1', 'surveillance_2' => 'Due — Surveillance 2', 'recertification' => 'Due — Recert-Expiry'];
    foreach ($dueBreakdownSheetTitles as $milestoneKey => $sheetTitle) {
        cm_write_detail_sheet(
            $spreadsheet, $sheetTitle,
            cm_build_monthly_stats_detail_all($db, array_merge($baseDetailArgs, ['metric' => 'due_breakdown', 'milestone' => $milestoneKey])),
            ['company_name' => 'Company', 'consultant' => 'Consultant', 'scheme_name' => 'Scheme', 'cert_status' => 'Cert Status', 'milestone_date' => 'Due Date', 'completed_at' => 'Completed At']
        );
    }

    cm_write_detail_sheet($spreadsheet, 'New Clients', cm_build_monthly_stats_detail_all($db, array_merge($baseDetailArgs, ['metric' => 'new_clients'])),
        ['client_id' => 'Client ID', 'company_name' => 'Company', 'entity' => 'Entity', 'consultant' => 'Consultant', 'status' => 'Status', 'industry_sector' => 'Industry', 'first_cert_date' => 'First Certification Date']);

    // Spreadsheet::__construct() always creates one blank default sheet;
    // createSheet() above only ADDS to it, so without this it would leave
    // a stray empty "Worksheet" tab as a 5th sheet in an export that's
    // supposed to be exactly 4.
    $spreadsheet->removeSheetByIndex(0);
    $spreadsheet->setActiveSheetIndex(0);

    $filename = 'cm_monthly_stats_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// --- PDF ---
function cm_pdf_escape2(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1f2937; }
    h1 { font-size: 16px; margin-bottom: 2px; color: #283891; }
    .subtitle { color: #6b7280; margin-bottom: 4px; }
    .filters { color: #6b7280; margin-bottom: 16px; font-size: 9px; }
    h2 { font-size: 11px; color: #283891; margin-top: 16px; margin-bottom: 4px; border-bottom: 1px solid #ccc; padding-bottom: 2px; }
    table.stats { border-collapse: collapse; width: 100%; margin-bottom: 8px; }
    table.stats th, table.stats td { border: 1px solid #ccc; padding: 3px 6px; text-align: left; }
    table.stats th { background: #f3f4f6; }
    .footer-note { margin-top: 20px; font-size: 8px; color: #9ca3af; }
</style>
</head>
<body>
    <h1>Client Management — Monthly Stats</h1>
    <div class="subtitle">Range: <?= cm_pdf_escape2($rangeLabel) ?> — generated <?= date('j M Y, g:i A') ?></div>
    <div class="filters">Filters: <?= cm_pdf_escape2($filterSummary) ?></div>

    <?php
    // Group the flat rows back into sections for the PDF, in the same
    // order cm_monthly_stats_rows() produced them.
    $sections = [];
    foreach ($dataRows as $r) {
        $sections[$r[0]][] = [$r[1], $r[2]];
    }
    ?>
    <?php foreach ($sections as $sectionTitle => $rows): ?>
        <h2><?= cm_pdf_escape2($sectionTitle) ?></h2>
        <table class="stats">
            <?php foreach ($rows as $row): ?>
                <tr><td><?= cm_pdf_escape2((string) $row[0]) ?></td><td><?= cm_pdf_escape2((string) $row[1]) ?></td></tr>
            <?php endforeach; ?>
        </table>
    <?php endforeach; ?>

    <div class="footer-note">EHS Universal — Client Management. Figures reflect the filters and range shown above at the time of export.</div>
</body>
</html>
<?php
$html = ob_get_clean();

$options = new \Dompdf\Options();
$options->set('isRemoteEnabled', false);
$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'cm_monthly_stats_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;