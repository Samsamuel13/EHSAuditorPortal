<?php
// File: admin/activity_log.php
require_once __DIR__ . '/../includes/auth.php';
$user = ehs_require_role(['super_admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity Log — <?= htmlspecialchars(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= ehs_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/calendar.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/management.css') ?>">
</head>
<body>
    <header class="topbar">
        <span class="topbar-brand">
            <img src="<?= ehs_url('assets/img/logo.png') ?>" alt="EHS Universal" class="topbar-logo">
            <span class="topbar-subtitle">Auditor Scheduler</span>
        </span>
        <span class="topbar-user">
            <?= htmlspecialchars($user['name']) ?> (super admin)
            &middot; <a href="<?= ehs_url('admin/index.php') ?>">Dashboard</a>
            &middot; <a href="<?= ehs_url('admin/users.php') ?>">User Accounts</a>
            &middot; <a href="<?= ehs_url('logout.php') ?>">Log out</a>
        </span>
    </header>

    <div class="back-nav">
        <button onclick="history.back()" class="btn btn-ghost-light btn-small back-btn">&larr; Back</button>
    </div>

    <main class="page">
        <h1>Activity Log</h1>
        <p class="field-hint">A read-only audit trail of who changed what, when. Super admin only.</p>

        <div class="filter-bar">
            <select id="filter-user"><option value="">All users</option></select>
            <select id="filter-action"><option value="">All actions</option></select>
            <select id="filter-entity"><option value="">All entity types</option></select>
            <input type="date" id="filter-start" title="From date">
            <input type="date" id="filter-end" title="To date">
            <button id="filter-clear-btn" class="btn btn-ghost-light btn-small">Clear filters</button>
        </div>

        <div class="mgmt-table-wrap">
            <table class="mgmt-table">
                <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th></tr></thead>
                <tbody id="log-body"></tbody>
            </table>
            <div id="empty-state" class="mgmt-empty hidden">No matching activity found.</div>
        </div>

        <div class="pagination-bar">
            <button id="prev-page-btn" class="btn btn-ghost-light btn-small">← Previous</button>
            <span id="page-info"></span>
            <button id="next-page-btn" class="btn btn-ghost-light btn-small">Next →</button>
        </div>
    </main>

    <script>window.EHS_BASE_URL = "<?= addslashes(ehs_url()) ?>";</script>
    <script src="<?= ehs_url('assets/js/session_guard.js') ?>"></script>
    <script src="<?= ehs_url('assets/js/activity_log.js') ?>"></script>
</body>
</html>
