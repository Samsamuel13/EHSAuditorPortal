<?php
// File: invoice-system/services.php — services catalogue (title, standard wording, default rate) and categories.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/inv_helpers.php';
require_once __DIR__ . '/includes/inv_page.php';

$user = ehs_require_role(INV_ROLES);

inv_page_start('Services', 'inv_home', $user, 'services');
?>
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2 gap-2">
            <h5 class="mb-0">Services catalogue</h5>
            <button class="btn btn-primary btn-sm" id="btn-new-service"><i class="bi bi-plus-lg"></i> New service</button>
        </div>
        <p class="small text-muted">A service fills a new invoice line with its title (printed in bold), standard wording and default rate.
            Everything stays editable on the invoice. Changing a service never changes invoices already created.</p>

        <div class="card shadow-sm mb-4">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Code</th><th>Title</th><th>Category</th><th>Entity</th><th class="text-end">Default rate S$</th><th>Qty rule</th><th>Used</th><th></th></tr></thead>
                    <tbody id="svc-body"></tbody>
                </table>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2 gap-2">
            <h6 class="mb-0">Categories <span class="small text-muted fw-normal">(printed as "Category:" on the invoice)</span></h6>
            <form class="d-flex gap-2" id="cat-form" novalidate autocomplete="off">
                <input class="form-control form-control-sm" name="name" maxlength="100" placeholder="New category name" aria-label="New category name">
                <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap"><i class="bi bi-plus-lg"></i> Add</button>
            </form>
        </div>
        <div class="card shadow-sm" style="max-width:720px">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr><th>Name</th><th class="text-end">Order</th><th>Invoices</th><th></th></tr></thead>
                <tbody id="cat-body"></tbody>
            </table>
        </div>

        <div class="modal fade" id="svc-modal" tabindex="-1" aria-labelledby="svc-title" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <form class="modal-content" id="svc-form" novalidate autocomplete="off">
                    <div class="modal-header">
                        <h6 class="modal-title" id="svc-title">Service</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id"><input type="hidden" name="row_hash">
                        <div class="row g-2">
                            <div class="col-12 col-md-4">
                                <label class="form-label small mb-0" for="sv-code">Code <span class="text-danger">*</span></label>
                                <input class="form-control form-control-sm text-uppercase" id="sv-code" name="code" maxlength="30">
                                <div class="invalid-feedback" data-for="code"></div>
                            </div>
                            <div class="col-12 col-md-8">
                                <label class="form-label small mb-0" for="sv-title">Title (bold on the invoice) <span class="text-danger">*</span></label>
                                <input class="form-control form-control-sm" id="sv-title" name="title" maxlength="255">
                                <div class="invalid-feedback" data-for="title"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small mb-0" for="sv-wording">Standard wording</label>
                                <textarea class="form-control form-control-sm" id="sv-wording" name="standard_wording" rows="4" maxlength="4000"></textarea>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small mb-0" for="sv-rate">Default rate S$</label>
                                <input class="form-control form-control-sm" id="sv-rate" name="default_rate" inputmode="decimal" maxlength="13">
                                <div class="invalid-feedback" data-for="default_rate"></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small mb-0" for="sv-cat">Category</label>
                                <select class="form-select form-select-sm" id="sv-cat" name="category_id"></select>
                                <div class="invalid-feedback" data-for="category_id"></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small mb-0" for="sv-ent">Entity</label>
                                <select class="form-select form-select-sm" id="sv-ent" name="entity_code"></select>
                                <div class="invalid-feedback" data-for="entity_code"></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small mb-0" for="sv-sort">Sort order</label>
                                <input type="number" class="form-control form-control-sm" id="sv-sort" name="sort_order" min="0" max="100000">
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="sv-qty" name="qty_from_entities">
                                    <label class="form-check-label small" for="sv-qty">Quantity = number of audited entities on the line (e.g. RM Audit)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm me-auto d-none" id="svc-toggle"></button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save</button>
                    </div>
                </form>
            </div>
        </div>
<?php
inv_page_end(['invoice-system/assets/js/inv_services.js']);
