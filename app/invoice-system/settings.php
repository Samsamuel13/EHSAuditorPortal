<?php
// File: invoice-system/settings.php — issuing entities, readiness and number series.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/inv_helpers.php';
require_once __DIR__ . '/includes/inv_page.php';

$user = ehs_require_role(INV_ROLES);
$db = get_db();
$paper = (string) ($db->query("SELECT cfg_value FROM inv_config WHERE cfg_key = 'pdf_page_size'")->fetchColumn() ?: 'A4');

inv_page_start('Settings', 'inv_settings', $user, 'settings');
?>
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2 gap-2">
            <h5 class="mb-0">Settings: issuing entities</h5>
            <span class="small text-muted" id="lookup-status"></span>
        </div>
        <p class="small text-muted mb-3">
            Values shown as <span class="badge text-bg-warning">TBC</span> are placeholders. You can save partial details at any time;
            issuing stays blocked for an entity until nothing is TBC, GST status is confirmed and the number series is confirmed.
            Drafts and previews remain available. Quotations have their own readiness list (texts, footer, prefix and quotation number series).
        </p>

        <ul class="nav nav-tabs" id="entity-tabs" role="tablist"></ul>
        <div class="tab-content bg-white border border-top-0 rounded-bottom p-3" id="entity-panes">
            <div class="text-muted small">Loading…</div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-12 col-xl-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white"><strong>Module options</strong></div>
                    <div class="card-body small">
                        <form id="options-form" class="row g-2 align-items-end" novalidate>
                            <div class="col-8">
                                <label class="form-label mb-0" for="opt-paper">PDF paper size (new PDFs)</label>
                                <select class="form-select form-select-sm" id="opt-paper" name="pdf_page_size" data-current="<?= inv_h($paper) ?>">
                                    <option value="A4">A4 (default)</option>
                                    <option value="letter">US Letter (as sample #26301)</option>
                                </select>
                                <div class="invalid-feedback" data-for="pdf_page_size"></div>
                            </div>
                            <div class="col-4"><button type="submit" class="btn btn-sm btn-primary w-100">Save</button></div>
                            <div class="col-12 form-text">Issued invoices keep their stored PDF. The PDF storage folder is set by SQL only (see README).</div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-8">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <strong><i class="bi bi-hdd-stack"></i> Environment check <span class="text-muted fw-normal">(read-only)</span></strong>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="env-refresh"><i class="bi bi-arrow-clockwise"></i> Re-check</button>
                    </div>
                    <div class="card-body small" id="env-body"><span class="text-muted">Checking…</span></div>
                </div>
            </div>
        </div>

        <!-- Confirm number series modal -->
        <div class="modal fade" id="series-modal" tabindex="-1" aria-labelledby="series-modal-title" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" id="series-form" novalidate>
                    <div class="modal-header">
                        <h6 class="modal-title" id="series-modal-title">Confirm number series</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body small">
                        <input type="hidden" name="entity_code">
                        <input type="hidden" name="doc_type">
                        <input type="hidden" name="period_year">
                        <p id="series-context" class="mb-2"></p>
                        <div class="alert alert-warning py-2">
                            Assumed format: <strong>[prefix] + YY + <span id="series-digits"></span>-digit sequence</strong>, restarting each calendar year,
                            separate per entity. Enter the <strong>next unused</strong> number exactly as your records show. Nothing is assumed automatically.
                        </div>
                        <label class="form-label" for="sf-next_value">Next sequence number</label>
                        <input type="number" class="form-control" id="sf-next_value" name="next_value" min="1" step="1" required>
                        <div class="invalid-feedback" data-for="next_value"></div>
                        <div class="form-text" id="series-max"></div>
                        <div class="mt-2">Next document number will be: <strong class="font-monospace fs-6" id="series-preview">—</strong></div>
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" id="sf-acknowledge" name="acknowledge">
                            <label class="form-check-label" for="sf-acknowledge">
                                I confirm this format and that this is the next unused number for this entity, document type and year.
                            </label>
                            <div class="invalid-feedback" data-for="acknowledge">Required.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="btn-series-save">Confirm number</button>
                    </div>
                </form>
            </div>
        </div>
<?php
inv_page_end(['invoice-system/assets/js/inv_settings.js', 'invoice-system/assets/js/inv_settings_env.js']);