<?php
// File: client-management/index.php — Client Directory
require_once __DIR__ . '/../includes/auth.php';

// Auditors have no access to this module by default.
$user = ehs_require_role(['super_admin', 'admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars(ehs_csrf_token()) ?>">
<title>Client Management — <?= htmlspecialchars(APP_NAME) ?></title>
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
            &middot; <a href="<?= ehs_url('client-management/renewal_dashboard.php') ?>">Renewal Dashboard</a>
            &middot; <a href="<?= ehs_url('client-management/import.php') ?>">Bulk Import</a>
            &middot; <a href="<?= ehs_url('admin/index.php') ?>">Scheduler Dashboard</a>
            &middot; <a href="<?= ehs_url('logout.php') ?>">Log out</a>
        </span>
    </header>

    <main class="page">
        <h1>Client Directory</h1>

        <div class="filter-bar">
            <input type="text" id="filter-q" placeholder="Search name or UEN...">
            <input type="text" id="filter-industry" placeholder="Industry sector">
            <select id="filter-status">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
                <option value="withdrawn">Withdrawn</option>
                <option value="blacklisted">Blacklisted</option>
            </select>
            <select id="filter-scheme">
                <option value="">All scheme types</option>
            </select>
            <select id="filter-expiring">
                <option value="">Any expiry</option>
                <option value="30">Expiring in 30 days</option>
                <option value="60">Expiring in 60 days</option>
                <option value="90">Expiring in 90 days</option>
            </select>
            <button id="filter-clear" class="btn btn-ghost-light btn-small" style="width:auto;">Clear</button>
        </div>

        <div class="mgmt-toolbar">
            <span id="results-count"></span>
            <span>
                <button id="export-btn" class="btn btn-ghost-light btn-small" style="width:auto; margin-right:8px;">⬇ Export to Excel</button>
                <button id="add-btn" class="btn btn-primary btn-small">+ Add client</button>
            </span>
        </div>

        <div class="mgmt-table-wrap">
            <table class="mgmt-table">
                <thead><tr>
                    <th>Company Name</th>
                    <th>UEN</th>
                    <th>Industry</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th></th>
                </tr></thead>
                <tbody id="clients-body"></tbody>
            </table>
            <div id="empty-state" class="mgmt-empty hidden">No clients match these filters.</div>
        </div>

        <div class="pagination-bar" id="pagination-bar"></div>
    </main>

    <div id="modal-backdrop" class="modal-backdrop hidden">
        <div class="modal modal-wide">
            <h2 id="modal-title">Add client</h2>

            <div class="modal-row">
                <div>
                    <label for="f-company-name">Company name *</label>
                    <input type="text" id="f-company-name" maxlength="200">
                </div>
                <div>
                    <label for="f-uen">UEN / Registration No.</label>
                    <input type="text" id="f-uen" maxlength="50">
                </div>
            </div>

            <div class="modal-row">
                <div>
                    <label for="f-industry">Industry sector</label>
                    <input type="text" id="f-industry" maxlength="100">
                </div>
                <div>
                    <label for="f-status">Status</label>
                    <select id="f-status">
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="withdrawn">Withdrawn</option>
                        <option value="blacklisted">Blacklisted</option>
                    </select>
                </div>
            </div>

            <label for="f-address">Address</label>
            <input type="text" id="f-address" maxlength="255">

            <div class="modal-row">
                <div>
                    <label for="f-contact-person">Contact person</label>
                    <input type="text" id="f-contact-person" maxlength="150">
                </div>
                <div>
                    <label for="f-contact-designation">Designation</label>
                    <input type="text" id="f-contact-designation" maxlength="100">
                </div>
            </div>

            <div class="modal-row">
                <div>
                    <label for="f-phone">Phone</label>
                    <input type="text" id="f-phone" maxlength="30">
                </div>
                <div>
                    <label for="f-email">Email</label>
                    <input type="email" id="f-email" maxlength="150">
                </div>
            </div>

            <label for="f-website">Website</label>
            <input type="text" id="f-website" maxlength="255">

            <label for="f-notes">Notes</label>
            <textarea id="f-notes" rows="3"></textarea>

            <div id="first-cert-section">
                <hr style="margin:16px 0; border:none; border-top:1px solid #e5e7eb;">
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <strong>Add a certification now? (optional)</strong>
                    <label style="font-weight:normal; font-size:0.85rem;">
                        <input type="checkbox" id="fc-enable"> Yes, add one
                    </label>
                </div>

                <div id="fc-fields" class="hidden" style="margin-top:10px;">
                    <div class="modal-row">
                        <div>
                            <label for="fc-scheme-type">Scheme type *</label>
                            <select id="fc-scheme-type"></select>
                        </div>
                        <div>
                            <label for="fc-accreditation-body">Accreditation body</label>
                            <input type="text" id="fc-accreditation-body" maxlength="100">
                        </div>
                    </div>
                    <div class="modal-row">
                        <div>
                            <label for="fc-cert-number">Certificate number</label>
                            <input type="text" id="fc-cert-number" maxlength="100">
                        </div>
                        <div>
                            <label for="fc-status">Status</label>
                            <select id="fc-status">
                                <option value="pending">Pending</option>
                                <option value="active" selected>Active</option>
                                <option value="expired">Expired</option>
                                <option value="suspended">Suspended</option>
                                <option value="withdrawn">Withdrawn</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-row">
                        <div>
                            <label for="fc-issue-date">1st Certification</label>
                            <input type="date" id="fc-issue-date">
                        </div>
                        <div>
                            <label for="fc-surv1-date">Surveillance 1</label>
                            <input type="date" id="fc-surv1-date">
                        </div>
                    </div>
                    <div class="modal-row">
                        <div>
                            <label for="fc-surv2-date">Surveillance 2</label>
                            <input type="date" id="fc-surv2-date">
                        </div>
                        <div>
                            <label for="fc-expiry-date">Recertification</label>
                            <input type="date" id="fc-expiry-date">
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-actions">
                <button id="modal-cancel" class="btn btn-ghost">Cancel</button>
                <button id="modal-save" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>

    <div id="toast" class="toast hidden"></div>
    <script>window.EHS_BASE_URL = "<?= addslashes(ehs_url()) ?>";</script>
    <script src="<?= ehs_url('assets/js/session_guard.js') ?>"></script>
    <script src="<?= ehs_url('client-management/assets/js/cm_clients.js') ?>"></script>
</body>
</html>