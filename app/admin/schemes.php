<?php
require_once __DIR__ . '/../includes/auth.php';
$user = ehs_require_role(['super_admin', 'admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars(ehs_csrf_token()) ?>">
<title>Schemes — <?= htmlspecialchars(APP_NAME) ?></title>
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
            <?= htmlspecialchars($user['name']) ?>
            &middot; <a href="<?= ehs_url('admin/index.php') ?>">Dashboard</a>
            &middot; <a href="<?= ehs_url('admin/schedule.php') ?>">Master Schedule</a>
            &middot; <a href="<?= ehs_url('logout.php') ?>">Log out</a>
        </span>
    </header>

    <div class="back-nav">
        <button onclick="history.back()" class="btn btn-ghost-light btn-small back-btn">&larr; Back</button>
    </div>

    <main class="page">
        <h1>Certification Schemes</h1>

        <div class="mgmt-toolbar">
            <span></span>
            <button id="add-btn" class="btn btn-primary btn-small">+ Add scheme</button>
        </div>

        <div class="mgmt-table-wrap">
            <table class="mgmt-table">
                <thead><tr><th>Name</th><th>Code</th><th></th></tr></thead>
                <tbody id="schemes-body"></tbody>
            </table>
            <div id="empty-state" class="mgmt-empty hidden">No schemes yet.</div>
        </div>
    </main>

    <div id="modal-backdrop" class="modal-backdrop hidden">
        <div class="modal">
            <h2 id="modal-title">Add scheme</h2>
            <label for="scheme-name">Name</label>
            <input type="text" id="scheme-name" maxlength="100" placeholder="e.g. ISO 45001">
            <label for="scheme-code">Code</label>
            <input type="text" id="scheme-code" maxlength="20" placeholder="e.g. 45001">
            <div class="modal-actions">
                <button id="modal-cancel" class="btn btn-ghost">Cancel</button>
                <button id="modal-save" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>

    <div id="confirm-backdrop" class="modal-backdrop hidden">
        <div class="modal modal-narrow">
            <h2>Delete this scheme?</h2>
            <p id="confirm-message"></p>
            <div class="modal-actions">
                <button id="confirm-cancel" class="btn btn-ghost">Cancel</button>
                <button id="confirm-ok" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>

    <div id="toast" class="toast hidden"></div>
    <script>window.EHS_BASE_URL = "<?= addslashes(ehs_url()) ?>";</script>
    <script src="<?= ehs_url('assets/js/session_guard.js') ?>"></script>
    <script src="<?= ehs_url('assets/js/schemes.js') ?>"></script>
</body>
</html>
