<?php
/**
 * crm/api/quotation_pdf.php?id=X
 *
 * Streams one quotation version as a PDF. Uses Dompdf, the same Composer
 * dependency and rendering pattern as
 * client-management/api/export_client_pdf.php — no second PDF library.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/crm_helpers.php';

$user = ehs_require_role(['super_admin', 'admin'], true);

$vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    crm_json_error('Export dependencies are not installed. Run "composer install" on the server.', 500);
}
require_once $vendorAutoload;

$db = get_db();
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(422); die('A valid quotation id is required.'); }

$stmt = $db->prepare(
    'SELECT q.*, l.company_name, l.contact_person, l.email AS lead_email, l.phone AS lead_phone
     FROM crm_quotations q JOIN crm_leads l ON l.id = q.crm_lead_id
     WHERE q.id = :id LIMIT 1'
);
$stmt->execute(['id' => $id]);
$quotation = $stmt->fetch();
if (!$quotation) { http_response_code(404); die('Quotation not found.'); }

$itemStmt = $db->prepare('SELECT * FROM crm_quotation_items WHERE crm_quotation_id = :id ORDER BY sort_order ASC, id ASC');
$itemStmt->execute(['id' => $id]);
$items = $itemStmt->fetchAll();

function crm_pdf_escape(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
function crm_pdf_money(string $currency, $amount): string
{
    return crm_pdf_escape($currency) . ' ' . number_format((float) $amount, 2);
}

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1f2937; }
    h1 { font-size: 18px; margin-bottom: 2px; color: #283891; }
    .subtitle { color: #6b7280; margin-bottom: 18px; }
    .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    .info-grid td { padding: 3px 6px; vertical-align: top; }
    .info-grid .label { color: #6b7280; width: 110px; }
    table.items { border-collapse: collapse; width: 100%; margin-bottom: 10px; }
    table.items th, table.items td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; }
    table.items th { background: #f3f4f6; }
    table.items td.num, table.items th.num { text-align: right; }
    table.totals { width: 260px; margin-left: auto; border-collapse: collapse; }
    table.totals td { padding: 3px 7px; }
    table.totals .total-row td { font-weight: bold; border-top: 2px solid #283891; }
    .footer-note { margin-top: 24px; font-size: 8px; color: #9ca3af; }
</style>
</head>
<body>
    <h1>Quotation <?= crm_pdf_escape($quotation['quote_number']) ?></h1>
    <div class="subtitle">EHS Universal — generated <?= date('j M Y, g:i A') ?></div>

    <table class="info-grid">
        <tr><td class="label">Client</td><td><?= crm_pdf_escape($quotation['company_name']) ?></td>
            <td class="label">Status</td><td><?= crm_pdf_escape(ucfirst($quotation['status'])) ?></td></tr>
        <tr><td class="label">Contact</td><td><?= crm_pdf_escape($quotation['contact_person'] ?? '—') ?></td>
            <td class="label">Valid Until</td><td><?= crm_pdf_escape($quotation['valid_until'] ?? '—') ?></td></tr>
        <tr><td class="label">Email</td><td><?= crm_pdf_escape($quotation['lead_email'] ?? '—') ?></td>
            <td class="label">Phone</td><td><?= crm_pdf_escape($quotation['lead_phone'] ?? '—') ?></td></tr>
    </table>

    <table class="items">
        <thead><tr><th>Description</th><th class="num">Qty</th><th class="num">Unit Price</th><th class="num">Line Total</th></tr></thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= crm_pdf_escape($item['description']) ?></td>
                    <td class="num"><?= crm_pdf_escape((string) rtrim(rtrim(number_format((float) $item['qty'], 2), '0'), '.')) ?></td>
                    <td class="num"><?= crm_pdf_money($quotation['currency'], $item['unit_price']) ?></td>
                    <td class="num"><?= crm_pdf_money($quotation['currency'], $item['line_total']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="num"><?= crm_pdf_money($quotation['currency'], $quotation['subtotal']) ?></td></tr>
        <tr><td>Tax (<?= crm_pdf_escape((string) $quotation['tax_percent']) ?>%)</td><td class="num"><?= crm_pdf_money($quotation['currency'], $quotation['tax_amount']) ?></td></tr>
        <tr class="total-row"><td>Total</td><td class="num"><?= crm_pdf_money($quotation['currency'], $quotation['total']) ?></td></tr>
    </table>

    <?php if (!empty($quotation['notes'])): ?>
        <p style="margin-top:16px;"><strong>Notes:</strong><br><?= nl2br(crm_pdf_escape($quotation['notes'])) ?></p>
    <?php endif; ?>

    <div class="footer-note">EHS Universal — CRM / Lead Pipeline. This quotation is version <?= (int) $quotation['version'] ?> for this lead.</div>
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

crm_log_activity($user['id'], 'export_quotation_pdf', 'crm_lead', (int) $quotation['crm_lead_id'], $quotation['quote_number']);

$filename = 'quotation_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $quotation['quote_number']) . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
