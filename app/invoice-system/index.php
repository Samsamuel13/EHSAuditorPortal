<?php
// File: invoice-system/index.php — module overview: issuing readiness and quick links.
// The ageing dashboard arrives in Stage 4. Stage 3B adds quotation tiles (when migration 004 is applied).
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/inv_helpers.php';
require_once __DIR__ . '/includes/inv_invoice.php';
require_once __DIR__ . '/includes/inv_page.php';

$user = ehs_require_role(INV_ROLES);
$db = get_db();

$year = (int) date('Y');
$today = inv_today();
$entities = inv_get_entities($db);
$moduleProblems = inv_module_problems($db);
$partyCounts = $db->query('SELECT SUM(is_active = 1) AS active, SUM(is_active = 0) AS inactive FROM inv_parties')->fetch();

$c = $db->prepare(
    "SELECT
        SUM(status = 'draft') AS drafts,
        SUM(status IN ('issued','partially_paid')) AS open_n,
        SUM(status IN ('issued','partially_paid') AND due_date < :o_today) AS overdue_n,
        SUM(status <> 'draft' AND invoice_date >= :m_start) AS issued_month
     FROM inv_invoices"
);
$c->execute(['o_today' => $today, 'm_start' => date('Y-m-01')]);
$counts = $c->fetch();

// Quotations (Stage 3B; only once migration 004 is applied).
$qm = null;
if (inv_table_exists($db, 'inv_quotations')) {
    require_once __DIR__ . '/includes/inv_quote.php';
    $qm = inv_quote_metrics($db);
    $qd = (int) $db->query("SELECT COUNT(*) FROM inv_quotations WHERE status = 'draft'")->fetchColumn();
}

inv_page_start('Overview', 'inv_home', $user, 'overview');
?>
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
            <h5 class="mb-0">Invoice System</h5>
            <div class="d-flex gap-2">
                <?php if ($qm !== null): ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= inv_h(ehs_url('invoice-system/quotation_edit.php')) ?>"><i class="bi bi-plus-lg"></i> New quotation</a>
                <?php endif; ?>
                <a class="btn btn-sm btn-primary" href="<?= inv_h(ehs_url('invoice-system/invoice_edit.php')) ?>"><i class="bi bi-plus-lg"></i> New invoice</a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= inv_h(ehs_url('invoice-system/invoices.php')) ?>"><i class="bi bi-receipt"></i> All invoices</a>
            </div>
        </div>

        <?php if ($moduleProblems): ?>
        <div class="alert alert-danger small">
            <strong><i class="bi bi-exclamation-octagon"></i> Issuing is blocked for every entity until this is fixed:</strong>
            <ul class="mb-0">
                <?php foreach ($moduleProblems as $p): ?><li><?= inv_h($p['message']) ?></li><?php endforeach; ?>
            </ul>
            <div class="mt-1">See Settings → Environment check for details.</div>
        </div>
        <?php endif; ?>

        <div class="row g-3 mb-3">
            <?php
            $tiles = [
                ['Drafts', (int) $counts['drafts'], 'draft', 'text-secondary'],
                ['Open (issued, unpaid)', (int) $counts['open_n'], 'open', 'text-primary'],
                ['Overdue', (int) $counts['overdue_n'], 'overdue', 'text-danger'],
                ['Issued this month', (int) $counts['issued_month'], 'all', 'text-body'],
            ];
            foreach ($tiles as [$label, $n, $filter, $cls]): ?>
            <div class="col-6 col-lg-3">
                <a class="card shadow-sm text-decoration-none h-100" href="<?= inv_h(ehs_url('invoice-system/invoices.php') . '?status=' . $filter) ?>">
                    <div class="card-body py-2">
                        <div class="small text-muted"><?= inv_h($label) ?></div>
                        <div class="fs-4 fw-semibold <?= $cls ?>"><?= $n ?></div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($qm !== null): ?>
        <div class="row g-3 mb-3">
            <?php
            $qtiles = [
                ['Open quotations', (int) $qm['open_count'] . ' · S' . inv_money_display(inv_decimal_to_cents($qm['open_value'])), 'open', 'text-primary'],
                ['Quotations expiring in 7 days', (string) (int) $qm['expiring_7d'], 'open', $qm['expiring_7d'] ? 'text-warning' : 'text-body'],
                ['Accepted, not yet invoiced', 'S' . inv_money_display(inv_decimal_to_cents($qm['accepted_not_invoiced'])), 'accepted', 'text-success'],
                ['Quotation drafts', (string) $qd, 'draft', 'text-secondary'],
            ];
            foreach ($qtiles as [$label, $val, $filter, $cls]): ?>
            <div class="col-6 col-lg-3">
                <a class="card shadow-sm text-decoration-none h-100" href="<?= inv_h(ehs_url('invoice-system/quotations.php') . '?status=' . $filter) ?>">
                    <div class="card-body py-2">
                        <div class="small text-muted"><?= inv_h($label) ?></div>
                        <div class="fs-5 fw-semibold <?= $cls ?>"><?= inv_h($val) ?></div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="row g-3 mb-3">
            <?php foreach ($entities as $code => $e):
                $inv = inv_entity_readiness($db, $code, 'invoice', $year);
                $ready = count($inv) === 0 && !$moduleProblems;
            ?>
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100 border-<?= $ready ? 'success' : 'warning' ?>">
                    <div class="card-header d-flex justify-content-between align-items-center bg-white">
                        <strong><?= inv_h($e['legal_name']) ?></strong>
                        <?php if ($ready): ?>
                            <span class="badge text-bg-success"><i class="bi bi-check-circle"></i> Ready to issue invoices (<?= $year ?>)</span>
                        <?php else: ?>
                            <span class="badge text-bg-warning"><i class="bi bi-lock"></i> Issuing blocked</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body small">
                        <?php if ($inv): ?>
                            <p class="mb-1 fw-semibold">Invoices: <?= count($inv) ?> item(s) to resolve</p>
                            <ul class="mb-0">
                                <?php foreach ($inv as $p): ?><li><?= inv_h($p['message']) ?></li><?php endforeach; ?>
                            </ul>
                        <?php elseif ($moduleProblems): ?>
                            <p class="mb-0">Entity settings are complete; see the module problems above.</p>
                        <?php else: ?>
                            <p class="mb-0 text-success">All settings confirmed for <?= $year ?>. Drafts and previews are always available.</p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-white">
                        <a class="btn btn-sm btn-outline-primary" href="<?= inv_h(ehs_url('invoice-system/settings.php') . '#entity-' . rawurlencode($code)) ?>">
                            <i class="bi bi-gear"></i> Open <?= inv_h($e['display_name']) ?> settings
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-3">
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small">Billed parties</div>
                        <div class="fs-4 fw-semibold"><?= (int) ($partyCounts['active'] ?? 0) ?> <span class="fs-6 text-muted">active</span></div>
                        <div class="small text-muted"><?= (int) ($partyCounts['inactive'] ?? 0) ?> inactive</div>
                        <a class="btn btn-sm btn-outline-primary mt-2" href="<?= inv_h(ehs_url('invoice-system/parties.php')) ?>"><i class="bi bi-people"></i> Manage parties</a>
                        <a class="btn btn-sm btn-outline-primary mt-2" href="<?= inv_h(ehs_url('invoice-system/services.php')) ?>"><i class="bi bi-list-check"></i> Services</a>
                    </div>
                </div>
            </div>
        </div>
<?php
inv_page_end();
