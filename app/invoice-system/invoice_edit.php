<?php
// File: invoice-system/invoice_edit.php — create / edit a DRAFT invoice, preview, issue.
// Issued invoices are never edited here: they redirect to invoice_view.php.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/inv_helpers.php';
require_once __DIR__ . '/includes/inv_invoice.php';
require_once __DIR__ . '/includes/inv_page.php';

$user = ehs_require_role(INV_ROLES);
$db = get_db();

$id = null;
if (isset($_GET['id'])) {
    $id = inv_int_or_null($_GET['id'], 1, 4294967295);
    $inv = $id ? inv_fetch_invoice($db, $id) : null;
    if (!$inv) {
        http_response_code(404);
        die('Invoice not found.');
    }
    if ($inv['status'] !== 'draft') {
        header('Location: ' . ehs_url('invoice-system/invoice_view.php?id=' . $id));
        exit;
    }
}

// Draft created from a quotation (Stage 3B; column exists only after migration 004). inv_ tables only.
$fromQuote = null;
if ($id && array_key_exists('quotation_id', $inv) && $inv['quotation_id'] !== null) {
    $qs = $db->prepare('SELECT id, quote_no FROM inv_quotations WHERE id = :q_id');
    $qs->execute(['q_id' => (int) $inv['quotation_id']]);
    $fromQuote = $qs->fetch() ?: null;
}

$year = (int) date('Y');
$entities = [];
foreach (inv_get_entities($db) as $code => $e) {
    $entities[] = [
        'code' => $code, 'name' => $e['display_name'], 'active' => (int) $e['is_active'] === 1,
        'default_terms' => (int) $e['default_terms_days'], 'gst_rate_h' => inv_entity_gst_rate_h($e),
        'no_gst_label' => $e['no_gst_label'], 'blocking' => count(inv_entity_readiness($db, $code, 'invoice', $year)),
    ];
}
$categories = $db->query('SELECT id, name, is_active FROM inv_categories ORDER BY sort_order, name')->fetchAll();
$services = $db->query(
    'SELECT id, code, title, standard_wording, default_rate, category_id, entity_code, qty_from_entities, is_active
     FROM inv_services ORDER BY sort_order, title'
)->fetchAll();

$boot = [
    'id' => $id, 'today' => inv_today(), 'entities' => $entities, 'categories' => $categories, 'services' => $services,
    'lookup' => inv_cm_lookup_status($db)['available'],
];

inv_page_start($id ? 'Draft #' . $id : 'New invoice', 'inv_home', $user, 'invoices');
?>
        <script type="application/json" id="inv-boot"><?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2 gap-2">
            <h5 class="mb-0" id="page-title"><?= $id ? 'Draft invoice #' . (int) $id : 'New invoice' ?>
                <span class="badge text-bg-secondary align-middle">Draft</span>
                <span class="badge text-bg-warning align-middle d-none" id="dirty-badge">Unsaved changes</span>
            </h5>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-danger<?= $id ? '' : ' d-none' ?>" id="btn-delete"><i class="bi bi-trash"></i> Delete draft</button>
                <button type="button" class="btn btn-sm btn-outline-secondary<?= $id ? '' : ' d-none' ?>" id="btn-duplicate"><i class="bi bi-copy"></i> Duplicate</button>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-preview"><i class="bi bi-file-earmark-pdf"></i> Preview PDF</button>
                <button type="button" class="btn btn-sm btn-primary" id="btn-save"><i class="bi bi-save"></i> Save draft</button>
                <button type="button" class="btn btn-sm btn-success" id="btn-issue"><i class="bi bi-send-check"></i> Issue…</button>
            </div>
        </div>

        <?php if ($fromQuote): ?>
        <div class="alert alert-info small py-2"><i class="bi bi-link-45deg"></i> Created from quotation
            <a class="font-monospace" href="<?= inv_h(ehs_url('invoice-system/quotation_view.php?id=' . (int) $fromQuote['id'])) ?>"><?= inv_h($fromQuote['quote_no']) ?></a>.
            Deleting this draft makes its quotation lines available to invoice again.</div>
        <?php endif; ?>
        <div id="issue-panel" class="mb-3"></div>

        <form id="inv-form" novalidate autocomplete="off" onsubmit="return false">
        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><strong>Invoice</strong></div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-12 col-md-4">
                                <label class="form-label small mb-0" for="f-entity">Issuing entity <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="f-entity" name="entity_code"></select>
                                <div class="invalid-feedback" data-for="entity_code"></div>
                            </div>
                            <div class="col-12 col-md-8 position-relative">
                                <label class="form-label small mb-0" for="f-party-q">Billed party <span class="text-danger">*</span></label>
                                <input type="hidden" name="party_id" id="f-party-id">
                                <input type="search" class="form-control form-control-sm" id="f-party-q" placeholder="Type 2+ letters to find a party" maxlength="100">
                                <div class="list-group position-absolute w-100 shadow-sm small" id="party-results" style="z-index:20"></div>
                                <div class="invalid-feedback" data-for="party_id"></div>
                                <div class="form-text"><a href="<?= inv_h(ehs_url('invoice-system/parties.php')) ?>" target="_blank" rel="noopener">Add a new party</a> (opens in a new tab).</div>
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label small mb-0" for="f-category">Category <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="f-category" name="category_id"></select>
                                <div class="invalid-feedback" data-for="category_id"></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small mb-0" for="f-date">Invoice date</label>
                                <input type="date" class="form-control form-control-sm" id="f-date" name="invoice_date">
                                <div class="invalid-feedback" data-for="invoice_date"></div>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label small mb-0" for="f-terms">Terms (days)</label>
                                <input type="number" class="form-control form-control-sm" id="f-terms" name="payment_terms_days" min="0" max="365" step="1">
                                <div class="invalid-feedback" data-for="payment_terms_days"></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small mb-0" for="f-due">Due date</label>
                                <input type="text" class="form-control form-control-sm" id="f-due" readonly tabindex="-1">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label small mb-0" for="f-ref">Your Ref (printed only if filled)</label>
                                <input class="form-control form-control-sm" id="f-ref" name="customer_ref" maxlength="100">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <strong>Lines</strong>
                        <div class="d-flex gap-2">
                            <select class="form-select form-select-sm" id="add-service" aria-label="Service for the new line" style="max-width:260px"></select>
                            <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" id="btn-add-line"><i class="bi bi-plus-lg"></i> Add line</button>
                        </div>
                    </div>
                    <div class="card-body" id="lines"></div>
                    <div class="invalid-feedback d-block px-3 pb-2" data-for="lines"></div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <strong>Bill to</strong>
                        <button type="button" class="btn btn-sm btn-link p-0" id="btn-refill" title="Copy again from the party record">Reset from party</button>
                    </div>
                    <div class="card-body">
                        <label class="form-label small mb-0" for="f-bn">Name (printed)</label>
                        <input class="form-control form-control-sm mb-2" id="f-bn" name="bill_to_name" maxlength="200">
                        <div class="invalid-feedback" data-for="bill_to_name"></div>
                        <label class="form-label small mb-0" for="f-ba">Billing address</label>
                        <textarea class="form-control form-control-sm mb-2" id="f-ba" name="bill_to_address" rows="3" maxlength="1000"></textarea>
                        <label class="form-label small mb-0" for="f-bat">Attn (printed only if filled)</label>
                        <input class="form-control form-control-sm mb-2" id="f-bat" name="bill_to_attention" maxlength="150">
                        <label class="form-label small mb-0" for="f-be">Accounts email</label>
                        <input class="form-control form-control-sm" id="f-be" name="bill_to_email" maxlength="150" inputmode="email">
                        <div class="invalid-feedback" data-for="bill_to_email"></div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><strong>Totals</strong> <span class="small text-muted">(recalculated by the server on save)</span></div>
                    <div class="card-body small">
                        <table class="table table-sm mb-2">
                            <tr><td>SUB-TOTAL</td><td class="text-end" id="t-sub">$0.00</td></tr>
                            <tr><td id="t-gst-label">NO GST</td><td class="text-end" id="t-gst">-</td></tr>
                            <tr class="fw-bold"><td>TOTAL (SGD)</td><td class="text-end" id="t-total">$0.00</td></tr>
                        </table>
                        <div class="text-muted" id="t-words"></div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><strong>Internal notes</strong> <span class="small text-muted">(never printed)</span></div>
                    <div class="card-body"><textarea class="form-control form-control-sm" name="internal_notes" id="f-notes" rows="3" maxlength="2000"></textarea></div>
                </div>
            </div>
        </div>
        </form>

        <template id="tpl-line">
            <div class="border rounded p-2 mb-3 inv-line">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong class="small">Line <span class="ln-no"></span></strong>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary ln-up" title="Move up" aria-label="Move line up"><i class="bi bi-arrow-up"></i></button>
                        <button type="button" class="btn btn-outline-secondary ln-down" title="Move down" aria-label="Move line down"><i class="bi bi-arrow-down"></i></button>
                        <button type="button" class="btn btn-outline-danger ln-remove" title="Remove line" aria-label="Remove line"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-12 col-md-5">
                        <label class="form-label small mb-0">Service</label>
                        <select class="form-select form-select-sm ln-service"></select>
                    </div>
                    <div class="col-12 col-md-7">
                        <label class="form-label small mb-0">Title (bold on the invoice) <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm ln-title" maxlength="255">
                        <div class="invalid-feedback ln-err-title"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-0">Wording</label>
                        <textarea class="form-control form-control-sm ln-desc" rows="3" maxlength="4000"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-0">Audited entities <span class="text-muted">(printed as "For: 1) … 2) …")</span></label>
                        <div class="ln-ents"></div>
                        <div class="position-relative mt-1">
                            <div class="input-group input-group-sm">
                                <input type="search" class="form-control ln-ent-q" maxlength="200" placeholder="Add a company: type a name, press Enter (or pick from the client directory)">
                                <button type="button" class="btn btn-outline-secondary ln-ent-add">Add</button>
                                <button type="button" class="btn btn-outline-secondary ln-ent-paste-toggle" title="Paste a list">Paste list</button>
                            </div>
                            <div class="list-group position-absolute w-100 shadow-sm small ln-ent-results" style="z-index:20"></div>
                        </div>
                        <div class="ln-paste d-none mt-1">
                            <textarea class="form-control form-control-sm ln-paste-text" rows="4" placeholder="One company per line. Leading numbering like &quot;1)&quot; is removed."></textarea>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-1 ln-paste-apply">Add these</button>
                        </div>
                    </div>
                    <div class="col-4 col-md-3">
                        <label class="form-label small mb-0">Qty</label>
                        <input class="form-control form-control-sm ln-qty" inputmode="decimal" maxlength="8">
                        <div class="form-text ln-qty-auto d-none">= number of entities</div>
                        <div class="invalid-feedback ln-err-qty"></div>
                    </div>
                    <div class="col-4 col-md-3">
                        <label class="form-label small mb-0">Rate S$</label>
                        <input class="form-control form-control-sm ln-rate" inputmode="decimal" maxlength="13">
                        <div class="invalid-feedback ln-err-rate"></div>
                    </div>
                    <div class="col-4 col-md-3 ms-auto text-end">
                        <label class="form-label small mb-0">Amount S$</label>
                        <div class="fw-semibold ln-amount pt-1">$0.00</div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Issue confirmation -->
        <div class="modal fade" id="issue-modal" tabindex="-1" aria-labelledby="issue-title" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title" id="issue-title">Issue invoice</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body small" id="issue-summary"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success btn-sm" id="btn-issue-confirm"><i class="bi bi-send-check"></i> Issue now</button>
                    </div>
                </div>
            </div>
        </div>
<?php
inv_page_end(['invoice-system/assets/js/inv_invoice_edit.js']);
