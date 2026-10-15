<?php
// File: invoice-system/invoice_view.php — read-only view of an issued (or later) invoice.
// Everything shown comes from the snapshot taken at issue; drafts redirect to the editor.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/inv_helpers.php';
require_once __DIR__ . '/includes/inv_invoice.php';
require_once __DIR__ . '/includes/inv_page.php';

$user = ehs_require_role(INV_ROLES);
$db = get_db();

$id = inv_int_or_null($_GET['id'] ?? null, 1, 4294967295);
$inv = $id ? inv_fetch_invoice($db, $id) : null;
if (!$inv) {
    http_response_code(404);
    die('Invoice not found.');
}
if ($inv['status'] === 'draft') {
    header('Location: ' . ehs_url('invoice-system/invoice_edit.php?id=' . $id));
    exit;
}

$lines = inv_fetch_lines($db, $id);
$led = inv_invoice_ledger($db, $id);
$totalC = inv_decimal_to_cents($inv['total']);
$balanceC = $inv['status'] === 'void' ? 0 : max(0, $totalC - $led['paid_c'] - $led['credited_c']);
$today = inv_today();
$overdue = in_array($inv['status'], INV_OPEN_STATUSES, true) && $inv['due_date'] < $today;
$snap = json_decode((string) $inv['entity_snapshot'], true) ?: [];
$badge = [
    'issued' => 'text-bg-primary', 'partially_paid' => 'text-bg-info', 'paid' => 'text-bg-success',
    'credited' => 'text-bg-light border', 'void' => 'text-bg-dark',
][$inv['status']] ?? 'text-bg-light';
$d = fn(?string $ymd) => $ymd ? inv_pdf_date($ymd) : '';
$pdfUrl = ehs_url('invoice-system/api/invoice_pdf.php?id=' . $id);
// Source quotation (Stage 3B; column exists only after migration 004). inv_ tables only.
$fromQuote = null;
if (array_key_exists('quotation_id', $inv) && $inv['quotation_id'] !== null) {
    $qs = $db->prepare('SELECT id, quote_no FROM inv_quotations WHERE id = :q_id');
    $qs->execute(['q_id' => (int) $inv['quotation_id']]);
    $fromQuote = $qs->fetch() ?: null;
}

inv_page_start('Invoice ' . $inv['invoice_no'], 'inv_home', $user, 'invoices');
?>
        <?php if (isset($_GET['issued'])): ?>
        <div class="alert alert-success small"><i class="bi bi-check-circle"></i> Invoice <strong><?= inv_h($inv['invoice_no']) ?></strong> was issued and its PDF stored.</div>
        <?php endif; ?>

        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
            <h5 class="mb-0">Invoice <span class="font-monospace"><?= inv_h($inv['invoice_no']) ?></span>
                <span class="badge <?= $badge ?> align-middle"><?= inv_h(INV_STATUS_LABELS[$inv['status']] ?? $inv['status']) ?></span>
                <?php if ($overdue): ?><span class="badge text-bg-danger align-middle">Overdue <?= (int) ((strtotime($today) - strtotime($inv['due_date'])) / 86400) ?> days</span><?php endif; ?>
            </h5>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-outline-primary" href="<?= inv_h($pdfUrl . '&inline=1') ?>" target="_blank" rel="noopener"><i class="bi bi-eye"></i> View PDF</a>
                <a class="btn btn-sm btn-primary" href="<?= inv_h($pdfUrl) ?>"><i class="bi bi-download"></i> Download PDF</a>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-duplicate" data-id="<?= (int) $id ?>"><i class="bi bi-copy"></i> Duplicate as new draft</button>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="row g-3 small">
                            <div class="col-12 col-md-6">
                                <div class="text-muted">Bill to</div>
                                <div class="fw-semibold"><?= inv_h($inv['bill_to_name']) ?></div>
                                <?php if ($inv['bill_to_attention']): ?><div>Attn: <?= inv_h($inv['bill_to_attention']) ?></div><?php endif; ?>
                                <div><?= nl2br(inv_h($inv['bill_to_address'])) ?></div>
                                <?php if ($inv['bill_to_email']): ?><div class="text-muted"><?= inv_h($inv['bill_to_email']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-12 col-md-6">
                                <table class="table table-sm mb-0">
                                    <tr><td class="text-muted">Issued by</td><td><?= inv_h($snap['legal_name'] ?? $inv['entity_code']) ?></td></tr>
                                    <tr><td class="text-muted">Date</td><td><?= inv_h($d($inv['invoice_date'])) ?></td></tr>
                                    <tr><td class="text-muted">Category</td><td><?= inv_h($inv['category_name']) ?></td></tr>
                                    <tr><td class="text-muted">Due date</td><td><?= inv_h($d($inv['due_date'])) ?> (<?= (int) $inv['payment_terms_days'] ?> days)</td></tr>
                                    <?php if ($inv['customer_ref']): ?><tr><td class="text-muted">Your Ref</td><td><?= inv_h($inv['customer_ref']) ?></td></tr><?php endif; ?>
                                    <?php if ($fromQuote): ?><tr><td class="text-muted">From quotation</td><td><a href="<?= inv_h(ehs_url('invoice-system/quotation_view.php?id=' . (int) $fromQuote['id'])) ?>" class="font-monospace"><?= inv_h($fromQuote['quote_no']) ?></a></td></tr><?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="table-responsive">
                        <table class="table table-sm align-top mb-0">
                            <thead class="table-light"><tr><th style="width:3rem">Item</th><th>Description</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Amount S$</th></tr></thead>
                            <tbody>
                            <?php foreach ($lines as $i => $l): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td class="small">
                                        <strong><?= inv_h($l['title']) ?></strong>
                                        <?php if ($l['description']): ?><div><?= nl2br(inv_h($l['description'])) ?></div><?php endif; ?>
                                        <?php if ($l['entities']): ?>
                                            <div class="mt-1">For:</div>
                                            <ol class="mb-0 ps-4"><?php foreach ($l['entities'] as $e): ?><li><?= inv_h($e['company_name']) ?></li><?php endforeach; ?></ol>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?= inv_h(inv_qty_display(inv_decimal_to_cents($l['qty']))) ?></td>
                                    <td class="text-end"><?= inv_h(inv_money_display(inv_decimal_to_cents($l['rate']))) ?></td>
                                    <td class="text-end"><?= inv_h(inv_money_display(inv_decimal_to_cents($l['amount']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot class="small">
                                <tr><td colspan="4" class="text-end">SUB-TOTAL</td><td class="text-end"><?= inv_h(inv_money_display(inv_decimal_to_cents($inv['subtotal']))) ?></td></tr>
                                <tr><td colspan="4" class="text-end"><?= inv_decimal_to_cents($inv['gst_rate']) > 0 ? 'GST ' . inv_h(rtrim(rtrim($inv['gst_rate'], '0'), '.')) . '%' : inv_h($snap['no_gst_label'] ?? 'NO GST') ?></td>
                                    <td class="text-end"><?= inv_decimal_to_cents($inv['gst_rate']) > 0 ? inv_h(inv_money_display(inv_decimal_to_cents($inv['gst_amount']))) : '-' ?></td></tr>
                                <tr class="fw-bold"><td colspan="4" class="text-end">TOTAL (SGD)</td><td class="text-end"><?= inv_h(inv_money_display($totalC)) ?></td></tr>
                                <tr><td colspan="5" class="fw-semibold"><?= inv_h($inv['amount_in_words']) ?></td></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><strong>Balance</strong></div>
                    <div class="card-body small">
                        <table class="table table-sm mb-2">
                            <tr><td>Total</td><td class="text-end"><?= inv_h(inv_money_display($totalC)) ?></td></tr>
                            <tr><td>Payments received</td><td class="text-end"><?= inv_h(inv_money_display($led['paid_c'])) ?></td></tr>
                            <tr><td>Credit notes</td><td class="text-end"><?= inv_h(inv_money_display($led['credited_c'])) ?></td></tr>
                            <tr class="fw-bold"><td>Balance</td><td class="text-end"><?= inv_h(inv_money_display($balanceC)) ?></td></tr>
                        </table>
                        <div class="text-muted">Payments, credit notes and void arrive in Stage 4.</div>
                    </div>
                </div>
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><strong>Record</strong></div>
                    <div class="card-body small">
                        <div>Issued <?= inv_h(invFormatServer($inv['issued_at'])) ?> by <?= inv_h($inv['issued_by_name']) ?></div>
                        <div>Created by <?= inv_h($inv['created_by_name']) ?></div>
                        <div class="mt-2 text-muted">Stored PDF SHA-256:</div>
                        <div class="font-monospace text-break" style="font-size:.75rem"><?= inv_h($inv['pdf_sha256']) ?></div>
                        <?php if ($inv['internal_notes']): ?>
                            <div class="mt-2 text-muted">Internal notes</div>
                            <div><?= nl2br(inv_h($inv['internal_notes'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <script>
        document.getElementById('btn-duplicate').addEventListener('click', async function () {
            try {
                const d = await invFetch(INV_API + '/invoices.php', { method: 'POST', body: { action: 'duplicate', id: Number(this.dataset.id) } });
                window.location.href = window.EHS_BASE_URL + '/invoice-system/invoice_edit.php?id=' + encodeURIComponent(d.invoice.id);
            } catch (e) { if (e.message !== 'session_expired') invToast(e.message, true); }
        });
        </script>
<?php
inv_page_end();

/** dd/mm/yyyy hh:mm from a DB timestamp. */
function invFormatServer(?string $ts): string
{
    if (!$ts || !preg_match('/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})/', $ts, $m)) return (string) $ts;
    return "$m[3]/$m[2]/$m[1] $m[4]:$m[5]";
}