<?php
// File: invoice-system/quotation_view.php — a finalised (sent) quotation and everything after it:
// acceptance record, revise, reject / cancel, conversion of accepted lines into invoices.
// Drafts redirect to the editor. URLs use the numeric id only (quote numbers contain "/").
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/inv_helpers.php';
require_once __DIR__ . '/includes/inv_quote.php';
require_once __DIR__ . '/includes/inv_page.php';

$user = ehs_require_role(INV_ROLES);
$db = get_db();

if (!inv_table_exists($db, 'inv_quotations')) {
    inv_page_start('Quotations', 'inv_home', $user, 'quotations');
    echo '<div class="alert alert-warning">Quotations are not installed yet. Import <code>db/inv_migration_004.sql</code> (README section 1C).</div>';
    inv_page_end();
    exit;
}

$id = inv_int_or_null($_GET['id'] ?? null, 1, 4294967295);
$q = $id ? inv_fetch_quote($db, $id) : null;
if (!$q) {
    http_response_code(404);
    die('Quotation not found.');
}
if ($q['status'] === 'draft') {
    header('Location: ' . ehs_url('invoice-system/quotation_edit.php?id=' . $id));
    exit;
}

$lines = inv_fetch_quote_lines($db, $id);
$snap = json_decode((string) $q['entity_snapshot'], true) ?: [];
$disp = inv_quote_display_status($q);
$badge = ['sent' => 'text-bg-primary', 'expired' => 'text-bg-warning', 'accepted' => 'text-bg-success', 'rejected' => 'text-bg-danger',
          'cancelled' => 'text-bg-dark', 'superseded' => 'text-bg-light border'][$disp] ?? 'text-bg-light';
$d = fn(?string $ymd) => $ymd ? inv_pdf_date($ymd) : '';
$ts = function (?string $t): string {
    if (!$t || !preg_match('/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})/', $t, $m)) return (string) $t;
    return "$m[3]/$m[2]/$m[1] $m[4]:$m[5]";
};
$m = fn($dec) => inv_money_display(inv_decimal_to_cents($dec));
$pdfUrl = ehs_url('invoice-system/api/quotation_pdf.php?id=' . $id);

$invStmt = $db->prepare('SELECT id, invoice_no, status, invoice_date, total FROM inv_invoices WHERE quotation_id = :i_q ORDER BY id');
$invStmt->execute(['i_q' => $id]);
$invoices = $invStmt->fetchAll();
$invById = [];
foreach ($invoices as $iv) $invById[(int) $iv['id']] = $iv;

$revs = [];
if ($q['base_no'] !== null) {
    $rv = $db->prepare('SELECT id, quote_no, revision_no, status, quote_date, valid_until FROM inv_quotations
                        WHERE entity_code = :r_entity AND base_no = :r_base ORDER BY revision_no, id');
    $rv->execute(['r_entity' => $q['entity_code'], 'r_base' => $q['base_no']]);
    $revs = $rv->fetchAll();
}
$draftRev = $db->prepare('SELECT id FROM inv_quotations WHERE revision_of_id = :d_of AND status = \'draft\'');
$draftRev->execute(['d_of' => $id]);
$draftRevId = $draftRev->fetchColumn();

$items = array_values(array_filter($lines, fn($l) => $l['line_type'] === 'item'));
$open = array_values(array_filter($items, fn($l) => $l['invoiced_invoice_id'] === null));
$anyInvoiced = count($open) < count($items);
$categories = $db->query('SELECT id, name FROM inv_categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();

$boot = ['id' => $id, 'row_hash' => inv_row_hash($q), 'quote_no' => $q['quote_no'], 'quote_date' => $q['quote_date'], 'today' => inv_today(),
         'max_mb' => (int) (INV_SIGNED_MAX_BYTES / 1048576)];

inv_page_start('Quotation ' . $q['quote_no'], 'inv_home', $user, 'quotations');
?>
        <script type="application/json" id="inv-boot"><?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
        <?php if (isset($_GET['sent'])): ?>
        <div class="alert alert-success small"><i class="bi bi-check-circle"></i> Quotation <strong><?= inv_h($q['quote_no']) ?></strong> was finalised, numbered and locked. Download the PDF below and send it to the client.</div>
        <?php endif; ?>

        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
            <h5 class="mb-0">Quotation <span class="font-monospace"><?= inv_h($q['quote_no']) ?></span>
                <span class="badge <?= $badge ?> align-middle"><?= inv_h(INV_QUOTE_STATUS_LABELS[$disp] ?? $disp) ?></span>
            </h5>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-outline-primary" href="<?= inv_h($pdfUrl . '&inline=1') ?>" target="_blank" rel="noopener"><i class="bi bi-eye"></i> View PDF</a>
                <a class="btn btn-sm btn-primary" href="<?= inv_h($pdfUrl) ?>"><i class="bi bi-download"></i> Download PDF</a>
                <?php if ($q['status'] === 'sent'): ?>
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#accept-modal"><i class="bi bi-hand-thumbs-up"></i> Mark accepted…</button>
                    <?php if ($draftRevId): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= inv_h(ehs_url('invoice-system/quotation_edit.php?id=' . (int) $draftRevId)) ?>"><i class="bi bi-pencil"></i> Open revision draft</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-revise"><i class="bi bi-pencil-square"></i> Revise</button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-close="reject"><i class="bi bi-hand-thumbs-down"></i> Rejected…</button>
                <?php endif; ?>
                <?php if ($q['status'] === 'accepted' && $open): ?>
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#convert-modal"><i class="bi bi-receipt"></i> Convert to invoice…</button>
                <?php endif; ?>
                <?php if (in_array($q['status'], ['sent', 'accepted'], true) && !$anyInvoiced): ?>
                    <button type="button" class="btn btn-sm btn-outline-dark" data-close="cancel"><i class="bi bi-x-circle"></i> Cancel…</button>
                <?php endif; ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-duplicate"><i class="bi bi-copy"></i> Duplicate as new draft</button>
            </div>
        </div>
        <?php if ($disp === 'expired'): ?>
        <div class="alert alert-warning small"><i class="bi bi-hourglass-bottom"></i> Validity ended on <?= inv_h($d($q['valid_until'])) ?> and no reply was recorded. You can still record a (late) acceptance, revise it, or mark it rejected.</div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="row g-3 small">
                            <div class="col-12 col-md-6">
                                <div class="text-muted">Addressed to</div>
                                <div class="fw-semibold"><?= inv_h($q['bill_to_name']) ?></div>
                                <div><?= nl2br(inv_h($q['bill_to_address'])) ?></div>
                                <?php if ($q['bill_to_attention']): ?><div>Attn: <?= inv_h($q['bill_to_attention']) ?></div><?php endif; ?>
                                <?php if ($q['bill_to_email']): ?><div class="text-muted"><?= inv_h($q['bill_to_email']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-12 col-md-6">
                                <table class="table table-sm mb-0">
                                    <tr><td class="text-muted">Issued by</td><td><?= inv_h($snap['legal_name'] ?? $q['entity_code']) ?></td></tr>
                                    <tr><td class="text-muted">Date</td><td><?= inv_h($d($q['quote_date'])) ?></td></tr>
                                    <tr><td class="text-muted">Valid</td><td><?= (int) $q['valid_days'] ?> days, until <?= inv_h($d($q['valid_until'])) ?><?= $q['status'] === 'accepted' ? ' <span class="text-muted">(accepted: no expiry)</span>' : '' ?></td></tr>
                                    <tr><td class="text-muted">Stamp</td><td><?= !empty($snap['stamp_printed']) ? 'Printed' : 'Not printed (none configured)' ?></td></tr>
                                </table>
                            </div>
                            <div class="col-12">
                                <div>Ref: <?= inv_h($q['ref_prefix']) ?> <strong><?= inv_h($q['subject']) ?></strong></div>
                                <?php if ($q['scope']): ?><div><strong>Scope</strong>: <?= nl2br(inv_h($q['scope'])) ?></div><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="table-responsive">
                        <table class="table table-sm align-top mb-0">
                            <thead class="table-light"><tr><th>Description of activity</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Special Rate</th><th>Year</th><th>Invoiced</th></tr></thead>
                            <tbody>
                            <?php foreach ($lines as $l): $isItem = $l['line_type'] === 'item'; ?>
                                <tr class="<?= $isItem ? '' : 'table-light' ?>">
                                    <td class="small<?= $isItem ? '' : ' text-end' ?>">
                                        <?= inv_h($l['title']) ?>
                                        <?php if ($l['description']): ?><div class="text-muted"><?= nl2br(inv_h($l['description'])) ?></div><?php endif; ?>
                                        <?php if ($l['entities']): ?>
                                            <div class="mt-1">For:</div>
                                            <ol class="mb-0 ps-4"><?php foreach ($l['entities'] as $e): ?><li><?= inv_h($e['company_name']) ?></li><?php endforeach; ?></ol>
                                        <?php endif; ?>
                                        <?php if (!$isItem): ?><div class="text-muted fst-italic"><?= $l['line_type'] === 'auto_sum' ? 'sum row (automatic)' : 'sum row (manual price)' ?></div><?php endif; ?>
                                    </td>
                                    <td class="text-end small"><?= $isItem ? inv_h(inv_qty_display(inv_decimal_to_cents($l['qty']))) : '' ?></td>
                                    <td class="text-end small"><?= inv_h($m($l['amount'])) ?></td>
                                    <td class="text-end small"><?= inv_h($m($l['special_amount'])) ?><?= $isItem && $l['special_rate'] === null ? '<div class="text-muted">(= Rate)</div>' : '' ?></td>
                                    <td class="small"><?= $l['cycle_year'] !== null ? (int) $l['cycle_year'] : '' ?></td>
                                    <td class="small">
                                        <?php if (!$isItem): ?>
                                            <span class="text-muted">—</span>
                                        <?php elseif ($l['invoiced_invoice_id'] !== null): $iv = $invById[(int) $l['invoiced_invoice_id']] ?? null; ?>
                                            <a href="<?= inv_h(ehs_url('invoice-system/' . (($iv['status'] ?? '') === 'draft' ? 'invoice_edit.php' : 'invoice_view.php') . '?id=' . (int) $l['invoiced_invoice_id'])) ?>">
                                                <?= $iv && $iv['invoice_no'] ? inv_h($iv['invoice_no']) : 'Draft #' . (int) $l['invoiced_invoice_id'] ?></a>
                                        <?php else: ?>
                                            <span class="text-muted">Not yet</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot class="small">
                                <tr class="fw-semibold"><td class="text-end">Totals (item rows; not printed)</td><td></td>
                                    <td class="text-end"><?= inv_h($m($q['total_list'])) ?></td><td class="text-end"><?= inv_h($m($q['total_special'])) ?></td><td colspan="2"></td></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <?php if ($q['status'] === 'accepted' || $q['accepted_on'] !== null): ?>
                <div class="card shadow-sm mb-3 border-success">
                    <div class="card-header bg-white"><strong>Acceptance</strong></div>
                    <div class="card-body small">
                        <div>Accepted on <strong><?= inv_h($d($q['accepted_on'])) ?></strong> by <strong><?= inv_h($q['accepted_by_name']) ?></strong></div>
                        <?php if ($q['acceptance_note']): ?><div class="mt-1"><?= nl2br(inv_h($q['acceptance_note'])) ?></div><?php endif; ?>
                        <div class="text-muted mt-1">Recorded <?= inv_h($ts($q['accepted_recorded_at'])) ?> by <?= inv_h($q['accepted_recorded_by_name']) ?></div>
                        <?php if ($q['signed_file']): ?>
                            <a class="btn btn-sm btn-outline-success mt-2" href="<?= inv_h($pdfUrl . '&signed=1') ?>"><i class="bi bi-paperclip"></i> Download signed copy</a>
                            <div class="text-muted mt-1">Uploaded as: <?= inv_h($q['signed_original_name']) ?></div>
                        <?php else: ?>
                            <div class="text-muted mt-1">No signed copy uploaded.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (in_array($q['status'], ['rejected', 'cancelled', 'superseded'], true)): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><strong><?= inv_h(INV_QUOTE_STATUS_LABELS[$q['status']]) ?></strong></div>
                    <div class="card-body small">
                        <div><?= inv_h($ts($q['closed_at'])) ?> by <?= inv_h($q['closed_by_name']) ?></div>
                        <?php if ($q['closed_reason']): ?><div class="mt-1"><?= nl2br(inv_h($q['closed_reason'])) ?></div><?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($invoices): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><strong>Invoices from this quotation</strong></div>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($invoices as $iv): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <a href="<?= inv_h(ehs_url('invoice-system/' . ($iv['status'] === 'draft' ? 'invoice_edit.php' : 'invoice_view.php') . '?id=' . (int) $iv['id'])) ?>">
                                <?= $iv['invoice_no'] ? '<span class="font-monospace">' . inv_h($iv['invoice_no']) . '</span>' : 'Draft #' . (int) $iv['id'] ?></a>
                            <span><?= inv_h(INV_STATUS_LABELS[$iv['status']] ?? $iv['status']) ?> · <?= inv_h($m($iv['total'])) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if (count($revs) > 1): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><strong>Versions</strong></div>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($revs as $r): $rd = inv_quote_display_status($r); ?>
                        <li class="list-group-item d-flex justify-content-between<?= (int) $r['id'] === $id ? ' active' : '' ?>">
                            <a class="<?= (int) $r['id'] === $id ? 'text-white' : '' ?>" href="<?= inv_h(ehs_url('invoice-system/quotation_view.php?id=' . (int) $r['id'])) ?>"><span class="font-monospace"><?= inv_h($r['quote_no']) ?></span></a>
                            <span><?= inv_h(INV_QUOTE_STATUS_LABELS[$rd] ?? $rd) ?> · <?= inv_h($d($r['quote_date'])) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><strong>Record</strong></div>
                    <div class="card-body small">
                        <div>Finalised and sent <?= inv_h($ts($q['sent_at'])) ?> by <?= inv_h($q['sent_by_name']) ?></div>
                        <div>Created by <?= inv_h($q['created_by_name']) ?></div>
                        <div class="mt-2 text-muted">Stored PDF SHA-256:</div>
                        <div class="font-monospace text-break" style="font-size:.75rem"><?= inv_h($q['pdf_sha256']) ?></div>
                        <?php if ($q['internal_notes']): ?>
                            <div class="mt-2 text-muted">Internal notes</div>
                            <div><?= nl2br(inv_h($q['internal_notes'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accept -->
        <div class="modal fade" id="accept-modal" tabindex="-1" aria-labelledby="accept-title" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" id="accept-form" novalidate onsubmit="return false">
                    <div class="modal-header">
                        <h6 class="modal-title" id="accept-title">Record acceptance of <?= inv_h($q['quote_no']) ?></h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body small">
                        <label class="form-label mb-0" for="a-on">Acceptance date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control form-control-sm mb-2" id="a-on" name="accepted_on" required>
                        <div class="invalid-feedback" data-for="accepted_on"></div>
                        <label class="form-label mb-0" for="a-by">Accepted by (client signatory) <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm mb-2" id="a-by" name="accepted_by" maxlength="150" required>
                        <div class="invalid-feedback" data-for="accepted_by"></div>
                        <label class="form-label mb-0" for="a-note">Note</label>
                        <textarea class="form-control form-control-sm mb-2" id="a-note" name="note" rows="2" maxlength="1000"></textarea>
                        <label class="form-label mb-0" for="a-file">Signed copy (optional): PDF, JPG or PNG, max <?= (int) (INV_SIGNED_MAX_BYTES / 1048576) ?> MB</label>
                        <input type="file" class="form-control form-control-sm" id="a-file" name="signed_copy" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
                        <div class="invalid-feedback" data-for="signed_copy"></div>
                        <div class="form-text">Stored privately and only downloadable by signed-in users with access to this module.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm" id="btn-accept"><i class="bi bi-hand-thumbs-up"></i> Record acceptance</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reject / cancel -->
        <div class="modal fade" id="close-modal" tabindex="-1" aria-labelledby="close-title" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" id="close-form" novalidate onsubmit="return false">
                    <div class="modal-header">
                        <h6 class="modal-title" id="close-title"></h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body small">
                        <p id="close-help" class="mb-2"></p>
                        <label class="form-label mb-0" for="c-reason">Reason</label>
                        <textarea class="form-control form-control-sm" id="c-reason" name="reason" rows="2" maxlength="500"></textarea>
                        <div class="invalid-feedback" data-for="reason"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Back</button>
                        <button type="submit" class="btn btn-danger btn-sm" id="btn-close-confirm">Confirm</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Convert -->
        <div class="modal fade" id="convert-modal" tabindex="-1" aria-labelledby="convert-title" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" id="convert-form" novalidate onsubmit="return false">
                    <div class="modal-header">
                        <h6 class="modal-title" id="convert-title">Convert lines of <?= inv_h($q['quote_no']) ?> to a draft invoice</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body small">
                        <p class="mb-2">Tick the lines to bill now. They are copied into <strong>one new draft invoice</strong> at the Special Rate (or the Rate where no special rate was given). Each line can be invoiced once; lines you leave unticked stay available (for example next year's surveillance).</p>
                        <?php
                        $byYear = [];
                        foreach ($open as $l) $byYear[$l['cycle_year'] === null ? 'Other' : 'Year ' . (int) $l['cycle_year']][] = $l;
                        ksort($byYear);
                        foreach ($byYear as $label => $ls): ?>
                            <div class="fw-semibold mt-2"><?= inv_h($label) ?></div>
                            <?php foreach ($ls as $l): ?>
                            <div class="form-check">
                                <input class="form-check-input cv-line" type="checkbox" value="<?= (int) $l['id'] ?>" id="cv-<?= (int) $l['id'] ?>">
                                <label class="form-check-label" for="cv-<?= (int) $l['id'] ?>"><?= inv_h($l['title']) ?>
                                    <span class="text-muted">· S<?= inv_h($m($l['special_amount'])) ?><?= $l['entities'] ? ' · ' . count($l['entities']) . ' audited entities' : '' ?></span></label>
                            </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <div class="row g-2 mt-2">
                            <div class="col-12 col-md-6">
                                <label class="form-label mb-0" for="cv-cat">Invoice category</label>
                                <select class="form-select form-select-sm" id="cv-cat">
                                    <option value="">Choose later in the invoice</option>
                                    <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"><?= inv_h($c['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm" id="btn-convert"><i class="bi bi-receipt"></i> Create draft invoice</button>
                    </div>
                </form>
            </div>
        </div>
<?php
inv_page_end(['invoice-system/assets/js/inv_quotation_view.js']);
