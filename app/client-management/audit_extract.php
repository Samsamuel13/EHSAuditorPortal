<?php
// File: client-management/audit_extract.php
require_once __DIR__ . '/../includes/auth.php';

$user = ehs_require_role(['super_admin', 'admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars(ehs_csrf_token()) ?>">
<title>Audit Extract — Client Management</title>
<link rel="stylesheet" href="<?= ehs_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/calendar.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/dashboard.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/management.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('client-management/assets/css/cm.css') ?>">
</head>
<body>
            <?php $activePage = 'audit_extract'; require __DIR__ . '/../includes/sidebar_nav.php'; ?>
    <main class="app-main page">
        <h1>Audit Extract</h1>
        <p style="color:var(--muted); margin-top:-8px;">
            Clients requiring an audit (1st Certification, Surveillance 1/2, or Recertification)
            in the last, current, or next calendar month.
        </p>

        <div class="filter-bar">
            <input type="text" id="filter-client-name" placeholder="Search client name...">
            <select id="filter-stage">
                <option value="">All audit types</option>
                <option value="initial">1st Certification</option>
                <option value="surveillance_1">Surveillance 1</option>
                <option value="surveillance_2">Surveillance 2</option>
                <option value="recertification">Recertification</option>
            </select>
            <select id="filter-scheme-category">
                <option value="">All scheme categories</option>
                <option value="ISO">ISO</option>
                <option value="BizSafe">BizSafe</option>
                <option value="JASANZ">JAS-ANZ</option>
                <option value="Other">Other</option>
            </select>
            <input type="text" id="filter-industry" placeholder="Industry sector">
            <select id="filter-responsible">
                <option value="">All responsible persons</option>
            </select>
            <button id="filter-clear" class="btn btn-ghost-light btn-small" style="width:auto;">Clear</button>
            <button id="btn-export" class="btn btn-primary btn-small" style="width:auto;">Export to Excel</button>
        </div>

        <div class="dash-grid" style="grid-template-columns: repeat(3, 1fr);">
            <div class="dash-card" id="tab-last" style="cursor:pointer;">
                <h3 id="tab-last-title">Last Month</h3>
                <div class="stat-row"><span class="stat-number" id="count-last">—</span><span class="stat-label">certifications</span></div>
            </div>
            <div class="dash-card" id="tab-this" style="cursor:pointer;">
                <h3 id="tab-this-title">This Month</h3>
                <div class="stat-row"><span class="stat-number" id="count-this">—</span><span class="stat-label">certifications</span></div>
            </div>
            <div class="dash-card" id="tab-next" style="cursor:pointer;">
                <h3 id="tab-next-title">Next Month</h3>
                <div class="stat-row"><span class="stat-number" id="count-next">—</span><span class="stat-label">certifications</span></div>
            </div>
        </div>

        <h2 style="font-size:1.05rem; margin-top:24px;" id="list-title">This Month</h2>
        <div class="mgmt-table-wrap">
            <table class="mgmt-table">
                <thead><tr>
                    <th>Client</th>
                    <th>Scheme</th>
                    <th>Cert #</th>
                    <th>Audit Type</th>
                    <th>Due Date</th>
                    <th>Consultant</th>
                    <th>Responsible</th>
                </tr></thead>
                <tbody id="results-body"></tbody>
            </table>
            <div id="results-empty-state" class="mgmt-empty hidden">No certifications need an audit in this window.</div>
        </div>
    </main>
    </div>

    <div id="toast" class="toast hidden"></div>
    <script>window.EHS_BASE_URL = "<?= addslashes(ehs_url()) ?>";</script>
    <script src="<?= ehs_url('assets/js/session_guard.js') ?>"></script>
    <script src="<?= ehs_url('client-management/assets/js/cm_audit_extract.js') ?>"></script>
</body>
</html>