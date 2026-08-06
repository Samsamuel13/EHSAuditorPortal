<?php
// File: client-management/renewal_dashboard.php
require_once __DIR__ . '/../includes/auth.php';

$user = ehs_require_role(['super_admin', 'admin']);
$isSuperAdmin = $user['role'] === 'super_admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars(ehs_csrf_token()) ?>">
<meta name="cm-is-super-admin" content="<?= $isSuperAdmin ? '1' : '0' ?>">
<title>Renewal Dashboard — Client Management</title>
<link rel="stylesheet" href="<?= ehs_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/calendar.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/dashboard.css') ?>">
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
            &middot; <a href="<?= ehs_url('client-management/import.php') ?>">Bulk Import</a>
            &middot; <a href="<?= ehs_url('admin/index.php') ?>">Scheduler Dashboard</a>
            &middot; <a href="<?= ehs_url('logout.php') ?>">Log out</a>
        </span>
    </header>

    <main class="page">
        <h1>Renewal Dashboard</h1>

        <div class="filter-bar">
            <input type="text" id="filter-client-name" placeholder="Search client name...">
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
        </div>

        <div class="dash-grid" style="grid-template-columns: repeat(3, 1fr);">
            <div class="dash-card" id="widget-near" style="cursor:pointer;">
                <h3 id="widget-near-title">Expiring soon</h3>
                <div class="stat-row"><span class="stat-number" id="count-near">—</span><span class="stat-label">certifications</span></div>
            </div>
            <div class="dash-card" id="widget-far" style="cursor:pointer;">
                <h3 id="widget-far-title">Expiring later</h3>
                <div class="stat-row"><span class="stat-number" id="count-far">—</span><span class="stat-label">certifications</span></div>
            </div>
            <div class="dash-card" id="widget-overdue" style="cursor:pointer;">
                <h3>Overdue / Expired</h3>
                <div class="stat-row"><span class="stat-number" id="count-overdue">—</span><span class="stat-label">certifications</span></div>
            </div>
        </div>

        <?php if ($isSuperAdmin): ?>
        <div class="cm-section-placeholder" style="text-align:left; margin-top:16px;">
            <strong>Alert thresholds (days)</strong> — Super Admin only. Controls the widget/bucket boundaries above.
            <div style="display:flex; gap:10px; align-items:center; margin-top:10px;">
                <input type="number" id="t1" min="1" style="width:80px;">
                <span>/</span>
                <input type="number" id="t2" min="1" style="width:80px;">
                <span>/</span>
                <input type="number" id="t3" min="1" style="width:80px;">
                <button id="save-thresholds" class="btn btn-primary btn-small" style="width:auto;">Save thresholds</button>
            </div>
        </div>
        <?php endif; ?>

        <h2 style="font-size:1.05rem; margin-top:24px;" id="list-title">All certifications with an expiry date</h2>
        <div class="mgmt-table-wrap">
            <table class="mgmt-table">
                <thead><tr>
                    <th>Client</th>
                    <th>Scheme</th>
                    <th>Cert #</th>
                    <th>Next Due</th>
                    <th>Status</th>
                    <th>Responsible</th>
                </tr></thead>
                <tbody id="results-body"></tbody>
            </table>
            <div id="results-empty-state" class="mgmt-empty hidden">No certifications match these filters.</div>
        </div>
    </main>

    <div id="toast" class="toast hidden"></div>
    <script>window.EHS_BASE_URL = "<?= addslashes(ehs_url()) ?>";</script>
    <script src="<?= ehs_url('assets/js/session_guard.js') ?>"></script>
    <script src="<?= ehs_url('client-management/assets/js/cm_renewal_dashboard.js') ?>"></script>
</body>
</html>