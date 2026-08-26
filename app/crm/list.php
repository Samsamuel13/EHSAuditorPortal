<?php
// File: crm/list.php — List/filter view
require_once __DIR__ . '/../includes/auth.php';

$user = ehs_require_role(['super_admin', 'admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars(ehs_csrf_token()) ?>">
<title>CRM — Lead List</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= ehs_url('crm/assets/css/crm.css') ?>">
</head>
<body class="bg-light">
    <header class="crm-topbar">
        <span>
            <img src="<?= ehs_url('assets/img/logo.png') ?>" alt="EHS Universal" class="crm-topbar-logo">
            <strong>CRM — Lead Pipeline</strong>
        </span>
        <span>
            <?= htmlspecialchars($user['name']) ?>
            <a href="<?= ehs_url('crm/index.php') ?>"><i class="bi bi-kanban"></i> Board</a>
            <a href="<?= ehs_url('crm/list.php') ?>"><i class="bi bi-list-ul"></i> List</a>
            <a href="<?= ehs_url('admin/index.php') ?>"><i class="bi bi-calendar3"></i> Scheduler</a>
            <a href="<?= ehs_url('client-management/index.php') ?>"><i class="bi bi-building"></i> Client Mgmt</a>
            <a href="<?= ehs_url('logout.php') ?>"><i class="bi bi-box-arrow-right"></i> Log out</a>
        </span>
    </header>

    <main class="container-fluid py-3">
        <h5 class="mb-3">Leads</h5>

        <div class="row g-2 mb-3">
            <div class="col-auto"><input type="text" class="form-control form-control-sm" id="f-q" placeholder="Search company/contact/email"></div>
            <div class="col-auto">
                <select class="form-select form-select-sm" id="f-stage">
                    <option value="">All stages</option>
                    <option value="enquiry">Enquiry</option>
                    <option value="lead">Lead</option>
                    <option value="quotation">Quotation</option>
                    <option value="negotiation">Negotiation</option>
                    <option value="awarded">Awarded</option>
                    <option value="lost">Lost</option>
                    <option value="on_hold">On Hold</option>
                </select>
            </div>
            <div class="col-auto">
                <select class="form-select form-select-sm" id="f-source">
                    <option value="">All sources</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="referral">Referral</option>
                    <option value="website">Website</option>
                    <option value="cold_call">Cold Call</option>
                    <option value="exhibition">Exhibition</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="col-auto"><select class="form-select form-select-sm" id="f-owner"><option value="">All owners</option></select></div>
            <div class="col-auto"><input type="date" class="form-control form-control-sm" id="f-date-from" title="Created from"></div>
            <div class="col-auto"><input type="date" class="form-control form-control-sm" id="f-date-to" title="Created to"></div>
            <div class="col-auto"><button class="btn btn-outline-secondary btn-sm" id="f-clear">Clear</button></div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover bg-white align-middle" id="leads-table">
                <thead class="table-light">
                    <tr>
                        <th data-sort="company_name" style="cursor:pointer;">Company <i class="bi bi-arrow-down-up small"></i></th>
                        <th>Contact</th>
                        <th data-sort="stage" style="cursor:pointer;">Stage <i class="bi bi-arrow-down-up small"></i></th>
                        <th>Source</th>
                        <th>Owner</th>
                        <th data-sort="created_at" style="cursor:pointer;">Created <i class="bi bi-arrow-down-up small"></i></th>
                        <th data-sort="updated_at" style="cursor:pointer;">Updated <i class="bi bi-arrow-down-up small"></i></th>
                    </tr>
                </thead>
                <tbody id="leads-body"></tbody>
            </table>
            <div id="leads-empty" class="text-muted p-3 d-none">No leads match these filters.</div>
        </div>
    </main>

    <div id="crm-toast" class="toast align-items-center text-white bg-dark border-0 position-fixed bottom-0 end-0 m-3" role="alert" style="z-index:1080;">
        <div class="d-flex">
            <div class="toast-body" id="crm-toast-body"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>

    <script>
        window.EHS_BASE_URL = "<?= addslashes(ehs_url()) ?>";
        window.CRM_CSRF_TOKEN = "<?= addslashes(ehs_csrf_token()) ?>";
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ehs_url('crm/assets/js/crm_common.js') ?>"></script>
    <script src="<?= ehs_url('crm/assets/js/crm_list.js') ?>"></script>
</body>
</html>
