<?php
// File: invoice-system/quotation_edit.php — create / edit a DRAFT quotation, preview, "Finalise and send".
// Sent quotations are never edited here: they redirect to quotation_view.php (use Revise there).
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

$id = null;
$revisionOf = null;
if (isset($_GET['id'])) {
    $id = inv_int_or_null($_GET['id'], 1, 4294967295);
    $q = $id ? inv_fetch_quote($db, $id) : null;
    if (!$q) {
        http_response_code(404);
        die('Quotation not found.');
    }
    if ($q['status'] !== 'draft') {
        header('Location: ' . ehs_url('invoice-system/quotation_view.php?id=' . $id));
        exit;
    }
    if ($q['revision_of_id'] !== null) {
        $revisionOf = ['id' => (int) $q['revision_of_id'], 'label' => $q['base_no'] . '-R' . (int) $q['revision_no']];
    }
}

$year = (int) date('Y');
$entities = [];
foreach (inv_get_entities($db) as $code => $e) {
    $texts = [];
    foreach (INV_QUOTE_TEXT_DEFAULTS as $f => [$src]) $texts[$f] = $e[$src] ?? null;
    $entities[] = [
        'code' => $code, 'name' => $e['display_name'], 'active' => (int) $e['is_active'] === 1,
        'valid_days' => (int) ($e['quote_valid_days'] ?? 45), 'texts' => $texts,
        'gst_registered' => (int) $e['gst_registered'] === 1 && (int) $e['gst_status_confirmed'] === 1,
        'blocking' => count(inv_quote_readiness($db, $code, $year)),
    ];
}
$services = $db->query(
    'SELECT id, code, title, standard_wording, default_rate, entity_code, qty_from_entities, is_active
     FROM inv_services ORDER BY sort_order, title'
)->fetchAll();

$boot = [
    'id' => $id, 'today' => inv_today(), 'entities' => $entities, 'services' => $services,
    'lookup' => inv_cm_lookup_status($db)['available'], 'revision' => $revisionOf, 'max_lines' => INV_QUOTE_MAX_LINES,
];

inv_page_start($id ? 'Draft quotation #' . $id : 'New quotation', 'inv_home', $user, 'quotations');
?>
        <script type="application/json" id="inv-boot"><?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2 gap-2">
            <h5 class="mb-0" id="page-title"><?= $id ? 'Draft quotation #' . (int) $id : 'New quotation' ?>
                <span class="badge text-bg-secondary align-middle">Draft</span>
                <?php if ($revisionOf): ?><span class="badge text-bg-info align-middle">Revision <?= inv_h($revisionOf['label']) ?></span><?php endif; ?>
                <span class="badge text-bg-warning align-middle d-none" id="dirty-badge">Unsaved changes</span>
            </h5>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-danger<?= $id ? '' : ' d-none' ?>" id="btn-delete"><i class="bi bi-trash"></i> Delete draft</button>
                <button type="button" class="btn btn-sm btn-outline-secondary<?= $id ? '' : ' d-none' ?>" id="btn-duplicate"><i class="bi bi-copy"></i> Duplicate</button>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-preview"><i class="bi bi-file-earmark-pdf"></i> Preview PDF</button>
                <button type="button" class="btn btn-sm btn-primary" id="btn-save"><i class="bi bi-save"></i> Save draft</button>
                <button type="button" class="btn btn-sm btn-success" id="btn-finalise"><i class="bi bi-send-check"></i> Finalise and send…</button>
            </div>
        </div>
        <?php if ($revisionOf): ?>
        <div class="alert alert-info small py-2">This draft revises
            <a href="<?= inv_h(ehs_url('invoice-system/quotation_view.php?id=' . $revisionOf['id'])) ?>">quotation #<?= (int) $revisionOf['id'] ?></a>.
            Finalising it numbers it <strong class="font-monospace"><?= inv_h($revisionOf['label']) ?></strong> and marks the earlier version superseded.</div>
        <?php endif; ?>

        <div id="check-panel" class="mb-3"></div>

        <form id="q-form" novalidate autocomplete="off" onsubmit="return false">
        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><strong>Quotation</strong></div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-12 col-md-4">
                                <label class="form-label small mb-0" for="f-entity">Issuing entity <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="f-entity" name="entity_code"<?= $revisionOf ? ' disabled' : '' ?>></select>
                                <div class="invalid-feedback" data-for="entity_code"></div>
                            </div>
                            <div class="col-12 col-md-8 position-relative">
                                <label class="form-label small mb-0" for="f-party-q">Addressed to (party) <span class="text-danger">*</span></label>
                                <input type="hidden" name="party_id" id="f-party-id">
                                <input type="search" class="form-control form-control-sm" id="f-party-q" placeholder="Type 2+ letters to find a party" maxlength="100">
                                <div class="list-group position-absolute w-100 shadow-sm small" id="party-results" style="z-index:20"></div>
                                <div class="invalid-feedback" data-for="party_id"></div>
                                <div class="form-text"><a href="<?= inv_h(ehs_url('invoice-system/parties.php')) ?>" target="_blank" rel="noopener">Add a new party</a> (opens in a new tab).</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small mb-0" for="f-date">Quotation date</label>
                                <input type="date" class="form-control form-control-sm" id="f-date" name="quote_date">
                                <div class="invalid-feedback" data-for="quote_date"></div>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label small mb-0" for="f-days">Valid (days)</label>
                                <input type="number" class="form-control form-control-sm" id="f-days" name="valid_days" min="1" max="365" step="1">
                                <div class="invalid-feedback" data-for="valid_days"></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small mb-0" for="f-until">Valid until</label>
                                <input type="text" class="form-control form-control-sm" id="f-until" readonly tabindex="-1">
                                <div class="form-text">Applies only while sent and awaiting a reply.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small mb-0" for="f-subject">"Ref:" subject (printed in bold) <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text text-truncate" id="ref-prefix-show" style="max-width:55%"></span>
                                    <input class="form-control" id="f-subject" name="subject" maxlength="500" placeholder="e.g. ISO 9001:2015, ISO 14001:2015 and ISO 45001:2018 (IAS Accreditation)">
                                </div>
                                <div class="invalid-feedback d-block" data-for="subject"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small mb-0" for="f-scope">Scope (printed after "Scope:"; leave blank to omit)</label>
                                <textarea class="form-control form-control-sm" id="f-scope" name="scope" rows="2" maxlength="2000"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
                        <strong>Price table</strong>
                        <div class="d-flex flex-wrap gap-2">
                            <select class="form-select form-select-sm" id="add-service" aria-label="Service for the new line" style="max-width:240px"></select>
                            <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" id="btn-add-line"><i class="bi bi-plus-lg"></i> Item</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" id="btn-add-auto" title="Row that adds up the item rows above it (since the previous sum row)"><i class="bi bi-calculator"></i> Sum row (auto)</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" id="btn-add-manual" title="Row with a price you type (e.g. a package price for the cycle)"><i class="bi bi-pencil"></i> Sum row (manual)</button>
                        </div>
                    </div>
                    <div class="card-body" id="lines"></div>
                    <div class="invalid-feedback d-block px-3 pb-2" data-for="lines"></div>
                    <div class="card-footer bg-white small text-muted">Special Rate left blank prints the Rate. Sum rows are printed right-aligned, are not added to totals and are never invoiced. "Year" groups lines for later conversion (e.g. surveillance years).</div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <strong>Addressee</strong>
                        <button type="button" class="btn btn-sm btn-link p-0" id="btn-refill" title="Copy again from the party record">Reset from party</button>
                    </div>
                    <div class="card-body">
                        <label class="form-label small mb-0" for="f-bn">Name (printed, bold)</label>
                        <input class="form-control form-control-sm mb-2" id="f-bn" name="bill_to_name" maxlength="200">
                        <div class="invalid-feedback" data-for="bill_to_name"></div>
                        <label class="form-label small mb-0" for="f-ba">Address</label>
                        <textarea class="form-control form-control-sm mb-2" id="f-ba" name="bill_to_address" rows="3" maxlength="1000"></textarea>
                        <label class="form-label small mb-0" for="f-bat">Attn (printed only if filled)</label>
                        <input class="form-control form-control-sm mb-2" id="f-bat" name="bill_to_attention" maxlength="150">
                        <label class="form-label small mb-0" for="f-be">Email (not printed)</label>
                        <input class="form-control form-control-sm" id="f-be" name="bill_to_email" maxlength="150" inputmode="email">
                        <div class="invalid-feedback" data-for="bill_to_email"></div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><strong>Totals</strong> <span class="small text-muted">(item rows only; not printed)</span></div>
                    <div class="card-body small">
                        <table class="table table-sm mb-0">
                            <tr><td>Rate total</td><td class="text-end" id="t-list">$0.00</td></tr>
                            <tr class="fw-bold"><td>Special Rate total</td><td class="text-end" id="t-special">$0.00</td></tr>
                        </table>
                        <div class="text-muted mt-1" id="t-gst"></div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <strong>Printed texts</strong>
                        <button type="button" class="btn btn-sm btn-link p-0" id="btn-texts-reset">Reset from entity defaults</button>
                    </div>
                    <div class="card-body">
                        <label class="form-label small mb-0" for="t-ref_prefix">"Ref:" fixed text</label>
                        <input class="form-control form-control-sm mb-2 q-text" id="t-ref_prefix" name="ref_prefix" maxlength="255">
                        <label class="form-label small mb-0" for="t-intro">Introduction</label>
                        <textarea class="form-control form-control-sm mb-2 q-text" id="t-intro" name="intro" rows="3" maxlength="2000"></textarea>
                        <label class="form-label small mb-0" for="t-terms_text">Terms paragraph <span class="text-muted">({valid_days} = validity)</span></label>
                        <textarea class="form-control form-control-sm mb-2 q-text" id="t-terms_text" name="terms_text" rows="4" maxlength="2000"></textarea>
                        <label class="form-label small mb-0" for="t-payment_terms">Payment terms <span class="text-muted">(one bullet per line)</span></label>
                        <textarea class="form-control form-control-sm mb-2 q-text" id="t-payment_terms" name="payment_terms" rows="3" maxlength="2000"></textarea>
                        <label class="form-label small mb-0" for="t-closing_text">Closing paragraph <span class="text-muted">(**bold**)</span></label>
                        <textarea class="form-control form-control-sm mb-2 q-text" id="t-closing_text" name="closing_text" rows="4" maxlength="2000"></textarea>
                        <div class="form-text">No signature is printed: computer generated (the entity's footer text is shown instead). A stamp, if set in Settings, is added when the quotation is finalised, never on draft previews.</div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><strong>Internal notes</strong> <span class="small text-muted">(never printed)</span></div>
                    <div class="card-body"><textarea class="form-control form-control-sm" name="internal_notes" id="f-notes" rows="3" maxlength="2000"></textarea></div>
                </div>
            </div>
        </div>
        </form>

        <template id="tpl-item">
            <div class="border rounded p-2 mb-3 q-line">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong class="small">Row <span class="ln-no"></span> · <span class="ln-kind">Item</span></strong>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary ln-up" title="Move up" aria-label="Move row up"><i class="bi bi-arrow-up"></i></button>
                        <button type="button" class="btn btn-outline-secondary ln-down" title="Move down" aria-label="Move row down"><i class="bi bi-arrow-down"></i></button>
                        <button type="button" class="btn btn-outline-danger ln-remove" title="Remove row" aria-label="Remove row"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-12 col-md-5 ln-item-only">
                        <label class="form-label small mb-0">Service</label>
                        <select class="form-select form-select-sm ln-service"></select>
                    </div>
                    <div class="col-12 col-md-7 ln-title-col">
                        <label class="form-label small mb-0">Description of activity <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm ln-title" maxlength="255">
                        <div class="invalid-feedback ln-err-title"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-0">Extra wording <span class="text-muted">(optional, smaller text)</span></label>
                        <textarea class="form-control form-control-sm ln-desc" rows="2" maxlength="4000"></textarea>
                    </div>
                    <div class="col-12 ln-item-only">
                        <label class="form-label small mb-0">Audited entities <span class="text-muted">(optional; printed as "For: 1) …")</span></label>
                        <div class="ln-ents"></div>
                        <div class="position-relative mt-1">
                            <div class="input-group input-group-sm">
                                <input type="search" class="form-control ln-ent-q" maxlength="200" placeholder="Add a company: type a name, press Enter">
                                <button type="button" class="btn btn-outline-secondary ln-ent-add">Add</button>
                            </div>
                            <div class="list-group position-absolute w-100 shadow-sm small ln-ent-results" style="z-index:20"></div>
                        </div>
                    </div>
                    <div class="col-3 col-md-2 ln-item-only">
                        <label class="form-label small mb-0">Qty</label>
                        <input class="form-control form-control-sm ln-qty" inputmode="decimal" maxlength="8">
                        <div class="form-text ln-qty-auto d-none">= entities</div>
                        <div class="invalid-feedback ln-err-qty"></div>
                    </div>
                    <div class="col-4 col-md-3 ln-price">
                        <label class="form-label small mb-0">Rate S$</label>
                        <input class="form-control form-control-sm ln-rate" inputmode="decimal" maxlength="13">
                        <div class="invalid-feedback ln-err-rate"></div>
                    </div>
                    <div class="col-5 col-md-3 ln-price">
                        <label class="form-label small mb-0">Special Rate S$</label>
                        <input class="form-control form-control-sm ln-special" inputmode="decimal" maxlength="13" placeholder="blank = Rate">
                        <div class="invalid-feedback ln-err-special_rate"></div>
                    </div>
                    <div class="col-4 col-md-2 ln-item-only">
                        <label class="form-label small mb-0">Year</label>
                        <select class="form-select form-select-sm ln-year">
                            <option value="">—</option>
                            <?php for ($y = 1; $y <= 10; $y++): ?><option value="<?= $y ?>"><?= $y ?></option><?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-8 col-md-2 ms-auto text-end">
                        <label class="form-label small mb-0">Amount / Special</label>
                        <div class="fw-semibold ln-amount pt-1 small">$0.00</div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Finalise confirmation -->
        <div class="modal fade" id="fin-modal" tabindex="-1" aria-labelledby="fin-title" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title" id="fin-title">Finalise and send quotation</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body small" id="fin-summary"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success btn-sm" id="btn-fin-confirm"><i class="bi bi-send-check"></i> Finalise now</button>
                    </div>
                </div>
            </div>
        </div>
<?php
inv_page_end(['invoice-system/assets/js/inv_quotation_edit.js']);