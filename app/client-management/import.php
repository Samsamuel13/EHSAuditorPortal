<?php
// File: client-management/import.php
require_once __DIR__ . '/../includes/auth.php';

$user = ehs_require_role(['super_admin', 'admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars(ehs_csrf_token()) ?>">
<title>Bulk Import — Client Management</title>
<link rel="stylesheet" href="<?= ehs_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/calendar.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/management.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('client-management/assets/css/cm.css') ?>">
</head>
<body>
    <header class="topbar">
        <span class="topbar-brand">
            <img src="<?= ehs_url('assets/img/logo.png') ?>" alt="EHS Universal" class="topbar-logo">
            <span class="topbar-subtitle">Client Management</span>
        </span>
        <span class="topbar-user">
            <?= htmlspecialchars($user['name']) ?>
            &middot; <a href="<?= ehs_url('client-management/index.php') ?>">Client Directory</a>
            &middot; <a href="<?= ehs_url('client-management/renewal_dashboard.php') ?>">Renewal Dashboard</a>
            &middot; <a href="<?= ehs_url('admin/index.php') ?>">Scheduler Dashboard</a>
            &middot; <a href="<?= ehs_url('logout.php') ?>">Log out</a>
        </span>
    </header>

    <main class="page">
        <h1>Bulk Import</h1>
        <p style="color:var(--muted); font-size:0.9rem; max-width:640px;">
            Import client and certification records from a CSV or Excel file — one row per
            client+certification (repeat the company name/UEN for a client with several
            certifications; leave <code>scheme_type_name</code> blank to import a client with no
            certification yet). Nothing is written to the database until you review the preview
            below and click "Commit valid rows".
        </p>

        <div class="cm-section-placeholder" style="text-align:left;">
            <a href="<?= ehs_url('client-management/api/import_template.php') ?>" class="btn btn-ghost-light btn-small" style="width:auto;">
                ⬇ Download import template (.xlsx)
            </a>
        </div>

        <div class="mgmt-toolbar" style="margin-top:20px;">
            <input type="file" id="import-file" accept=".csv,.xlsx,.xls">
            <button id="preview-btn" class="btn btn-primary btn-small">Preview</button>
        </div>

        <div id="summary-box" class="cm-section-placeholder hidden" style="text-align:left;"></div>

        <div class="mgmt-table-wrap hidden" id="preview-table-wrap">
            <table class="mgmt-table">
                <thead><tr>
                    <th>Row</th><th>Status</th><th>Company</th><th>Scheme</th><th>Cert #</th><th>Notes</th>
                </tr></thead>
                <tbody id="preview-body"></tbody>
            </table>
        </div>

        <div class="mgmt-toolbar hidden" id="commit-toolbar" style="margin-top:16px;">
            <span id="commit-hint" style="color:var(--muted); font-size:0.85rem;"></span>
            <button id="commit-btn" class="btn btn-primary btn-small">Commit valid rows</button>
        </div>

        <div id="commit-result-box" class="cm-section-placeholder hidden" style="text-align:left; margin-top:16px;"></div>
    </main>

    <div id="toast" class="toast hidden"></div>
    <script>window.EHS_BASE_URL = "<?= addslashes(ehs_url()) ?>";</script>
    <script src="<?= ehs_url('assets/js/session_guard.js') ?>"></script>
    <script src="<?= ehs_url('client-management/assets/js/cm_import.js') ?>"></script>
</body>
</html>