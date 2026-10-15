<?php
// File: invoice-system/quotations.php — quotation list with filters and overview metrics (Stage 3B).
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/inv_helpers.php';
require_once __DIR__ . '/includes/inv_page.php';

$user = ehs_require_role(INV_ROLES);
$db = get_db();
$entities = inv_get_entities($db);
$installed = inv_table_exists($db, 'inv_quotations');

$statusFilters = [
    'all' => 'All', 'draft' => 'Drafts', 'open' => 'Sent, awaiting reply', 'expired' => 'Expired (sent, past validity)',
    'accepted' => 'Accepted', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'superseded' => 'Superseded',
];
$initial = isset($_GET['status']) && array_key_exists($_GET['status'], $statusFilters) ? $_GET['status'] : 'all';

inv_page_start('Quotations', 'inv_home', $user, 'quotations');
?>
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
            <h5 class="mb-0">Quotations</h5>
            <?php if ($installed): ?>
            <a class="btn btn-primary btn-sm" href="<?= inv_h(ehs_url('invoice-system/quotation_edit.php')) ?>"><i class="bi bi-plus-lg"></i> New quotation</a>
            <?php endif; ?>
        </div>

        <?php if (!$installed): ?>
            <div class="alert alert-warning">Quotations are not installed yet. Import <code>db/inv_migration_004.sql</code> (README section 1C).</div>
        <?php else: ?>
        <p class="small text-muted mb-3">Client quotations are issued here and converted into invoices when accepted. The CRM module's quotation feature is unchanged; use this one for quotations you intend to invoice.</p>

        <div class="row g-2 mb-3" id="metrics"></div>

        <form class="row g-2 mb-3" id="filters" autocomplete="off" onsubmit="return false">
            <div class="col-12 col-md-4 col-xl-3">
                <input type="search" class="form-control form-control-sm" name="q" maxlength="100" placeholder="Number, party or subject" aria-label="Search">
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
            <div class="col-6 col-md-auto"><input type="date" class="form-control form-control-sm" name="date_from" aria-label="Quotation date from" title="Quotation date from"></div>
            <div class="col-6 col-md-auto"><input type="date" class="form-control form-control-sm" name="date_to" aria-label="Quotation date to" title="Quotation date to"></div>
        </form>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Number</th><th>Date</th><th>Entity</th><th>Addressed to</th><th>Subject</th>
                            <th class="text-end">Special total S$</th><th>Valid until</th><th>Invoiced</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="q-body"></tbody>
                </table>
            </div>
            <div id="q-empty" class="text-muted p-3 d-none">No quotations match these filters.</div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center small">
                <span id="q-count" class="text-muted"></span>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" id="pg-prev" disabled>&laquo; Prev</button>
                    <button class="btn btn-outline-secondary" id="pg-next" disabled>Next &raquo;</button>
                </div>
            </div>
        </div>
        <?php endif; ?>
<?php
inv_page_end($installed ? ['invoice-system/assets/js/inv_quotations.js'] : []);
