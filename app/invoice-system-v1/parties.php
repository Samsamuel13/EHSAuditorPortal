<?php
// File: invoice-system/parties.php — billed parties (customers / consultancies).
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/inv_helpers.php';
require_once __DIR__ . '/includes/inv_page.php';

$user = ehs_require_role(INV_ROLES);
$db = get_db();
$entities = inv_get_entities($db);
$lookup = inv_cm_lookup_status($db);

inv_page_start('Billed Parties', 'inv_parties', $user, 'parties');
?>
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
            <h5 class="mb-0">Billed Parties</h5>
            <button class="btn btn-primary btn-sm" id="btn-new-party"><i class="bi bi-plus-lg"></i> New party</button>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-12 col-sm-6 col-lg-4">
                <input type="search" class="form-control form-control-sm" id="f-q" maxlength="100" placeholder="Search name, UEN or accounts email" aria-label="Search parties">
            </div>
            <div class="col-auto">
                <select class="form-select form-select-sm" id="f-status" aria-label="Status filter">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="all">All</option>
                </select>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Party</th>
                            <th>Billing address</th>
                            <th>Attention</th>
                            <th>Accounts email</th>
                            <th class="text-end">Terms</th>
                            <th>Source</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="parties-body"></tbody>
                </table>
            </div>
            <div id="parties-empty" class="text-muted p-3 d-none">No parties match these filters.</div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center small">
                <span id="parties-count" class="text-muted"></span>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" id="pg-prev" disabled>&laquo; Prev</button>
                    <button class="btn btn-outline-secondary" id="pg-next" disabled>Next &raquo;</button>
                </div>
            </div>
        </div>

        <!-- Create / edit modal -->
        <div class="modal fade" id="party-modal" tabindex="-1" aria-labelledby="party-modal-title" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <form class="modal-content" id="party-form" novalidate autocomplete="off">
                    <div class="modal-header">
                        <h6 class="modal-title" id="party-modal-title">New party</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="lookup-block" class="border rounded p-2 mb-3 bg-light">
                            <?php if ($lookup['available']): ?>
                                <label class="form-label small fw-semibold mb-1" for="lookup-q">Copy from client directory (optional)</label>
                                <input type="search" class="form-control form-control-sm" id="lookup-q" maxlength="100" placeholder="Type 2+ letters of the company name or email">
                                <div class="list-group list-group-flush mt-1 small" id="lookup-results"></div>
                                <div class="form-text">Details are copied once and stay editable here. Nothing is written back to Client Management.
                                    Many consultancies are not in the client directory: just fill in the form below.</div>
                            <?php else: ?>
                                <div class="small text-muted"><i class="bi bi-info-circle"></i> The client directory lookup is not available on this database. Enter the party manually.</div>
                            <?php endif; ?>
                        </div>
                        <div id="source-note" class="alert alert-secondary py-1 small d-none"></div>
                        <div id="dup-warning" class="alert alert-warning small d-none"></div>

                        <input type="hidden" name="id">
                        <input type="hidden" name="row_hash">
                        <input type="hidden" name="source" value="manual">
                        <input type="hidden" name="source_client_id">

                        <div class="row g-2">
                            <div class="col-12 col-md-7">
                                <label class="form-label small" for="pf-display_name">Party name (printed on invoices) <span class="text-danger">*</span></label>
                                <input class="form-control form-control-sm" id="pf-display_name" name="display_name" maxlength="200" required>
                                <div class="invalid-feedback" data-for="display_name"></div>
                            </div>
                            <div class="col-12 col-md-5">
                                <label class="form-label small" for="pf-uen">UEN</label>
                                <input class="form-control form-control-sm" id="pf-uen" name="uen" maxlength="50">
                            </div>
                            <div class="col-12">
                                <label class="form-label small" for="pf-legal_name">Legal name (if different)</label>
                                <input class="form-control form-control-sm" id="pf-legal_name" name="legal_name" maxlength="200">
                            </div>
                            <div class="col-12">
                                <label class="form-label small" for="pf-billing_address">Billing address</label>
                                <textarea class="form-control form-control-sm" id="pf-billing_address" name="billing_address" rows="2" maxlength="1000"></textarea>
                                <div class="form-text">May be left blank; you will be reminded when creating an invoice.</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small" for="pf-attention">Attention</label>
                                <input class="form-control form-control-sm" id="pf-attention" name="attention" maxlength="150">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small" for="pf-phone">Phone</label>
                                <input class="form-control form-control-sm" id="pf-phone" name="phone" maxlength="100">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small" for="pf-accounts_email">Accounts email (for invoices and reminders)</label>
                                <input type="email" class="form-control form-control-sm" id="pf-accounts_email" name="accounts_email" maxlength="150">
                                <div class="invalid-feedback" data-for="accounts_email"></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small" for="pf-cc_emails">CC emails (comma-separated)</label>
                                <input class="form-control form-control-sm" id="pf-cc_emails" name="cc_emails" maxlength="500">
                                <div class="invalid-feedback" data-for="cc_emails"></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small" for="pf-payment_terms_days">Payment terms (days)</label>
                                <input type="number" class="form-control form-control-sm" id="pf-payment_terms_days" name="payment_terms_days" min="0" max="365" step="1" placeholder="Entity default">
                                <div class="invalid-feedback" data-for="payment_terms_days"></div>
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label small" for="pf-default_entity_code">Usual issuing entity</label>
                                <select class="form-select form-select-sm" id="pf-default_entity_code" name="default_entity_code">
                                    <option value="">No default</option>
                                    <?php foreach ($entities as $code => $e): ?>
                                        <option value="<?= inv_h($code) ?>"><?= inv_h($e['display_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback" data-for="default_entity_code"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small" for="pf-notes">Internal notes</label>
                                <textarea class="form-control form-control-sm" id="pf-notes" name="notes" rows="2" maxlength="2000"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-danger btn-sm me-auto d-none" id="btn-toggle-active"></button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-warning btn-sm d-none" id="btn-save-anyway">Save anyway</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="btn-save-party">Save</button>
                    </div>
                </form>
            </div>
        </div>
<?php
inv_page_end(['invoice-system/assets/js/inv_parties.js']);
