<?php
// File: invoice-system/invoices.php — invoice list with filters and filter-aware totals.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/inv_helpers.php';
require_once __DIR__ . '/includes/inv_page.php';

$user = ehs_require_role(INV_ROLES);
$db = get_db();
$entities = inv_get_entities($db);

$statusFilters = [
    'all' => 'All', 'draft' => 'Drafts', 'open' => 'Open (issued + partially paid)', 'overdue' => 'Overdue',
    'issued' => 'Issued', 'partially_paid' => 'Partially paid', 'paid' => 'Paid', 'credited' => 'Credited', 'void' => 'Void',
];
$initial = isset($_GET['status']) && array_key_exists($_GET['status'], $statusFilters) ? $_GET['status'] : 'all';

inv_page_start('Invoices', 'inv_home', $user, 'invoices');
?>
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
            <h5 class="mb-0">Invoices</h5>
            <a class="btn btn-primary btn-sm" href="<?= inv_h(ehs_url('invoice-system/invoice_edit.php')) ?>"><i class="bi bi-plus-lg"></i> New invoice</a>
        </div>

        <form class="row g-2 mb-3" id="filters" autocomplete="off" onsubmit="return false">
            <div class="col-12 col-md-4 col-xl-3">
                <input type="search" class="form-control form-control-sm" name="q" maxlength="100" placeholder="Number, party or your ref" aria-label="Search">
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <select class="form-select form-select-sm" name="status" aria-label="Status">
                    <?php foreach ($statusFilters as $k => $label): ?>
                        <option value="<?= inv_h($k) ?>"<?= $k === $initial ? ' selected' : '' ?>><?= inv_h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select class="form-select form-select-sm" name="entity" aria-label="Issuing entity">
                    <option value="">All entities</option>
                    <?php foreach ($entities as $code => $e): ?>
                        <option value="<?= inv_h($code) ?>"><?= inv_h($e['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-auto"><input type="date" class="form-control form-control-sm" name="date_from" aria-label="Invoice date from" title="Invoice date from"></div>
            <div class="col-6 col-md-auto"><input type="date" class="form-control form-control-sm" name="date_to" aria-label="Invoice date to" title="Invoice date to"></div>
        </form>

        <div class="row g-2 mb-3" id="summary"></div>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Number</th><th>Date</th><th>Entity</th><th>Party</th><th>Category</th>
                            <th class="text-end">Total S$</th><th class="text-end">Balance S$</th><th>Due</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="inv-body"></tbody>
                </table>
            </div>
            <div id="inv-empty" class="text-muted p-3 d-none">No invoices match these filters.</div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center small">
                <span id="inv-count" class="text-muted"></span>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" id="pg-prev" disabled>&laquo; Prev</button>
                    <button class="btn btn-outline-secondary" id="pg-next" disabled>Next &raquo;</button>
                </div>
            </div>
        </div>
<?php
inv_page_end(['invoice-system/assets/js/inv_invoices.js']);
