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
<title>Clients — <?= htmlspecialchars(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= ehs_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/calendar.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/management.css') ?>">
</head>
<body>
            <?php $activePage = 'clients'; require __DIR__ . '/../includes/sidebar_nav.php'; ?>
    <div class="back-nav">
        <button onclick="history.back()" class="btn btn-ghost-light btn-small back-btn">&larr; Back</button>
    </div>

        <main class="app-main page">
        <h1>Clients</h1>

        <div class="mgmt-toolbar">
            <span></span>
            <button id="add-btn" class="btn btn-primary btn-small">+ Add client</button>
        </div>

        <div class="mgmt-table-wrap">
            <table class="mgmt-table">
                <thead><tr><th>Name</th><th>Notes</th><th></th></tr></thead>
                <tbody id="clients-body"></tbody>
            </table>
            <div id="empty-state" class="mgmt-empty hidden">No clients yet.</div>
        </div>
    </main>
    </div>

    <div id="modal-backdrop" class="modal-backdrop hidden">
        <div class="modal">
            <h2 id="modal-title">Add client</h2>
            <label for="client-name">Name</label>
            <input type="text" id="client-name" maxlength="150">
            <label for="client-notes">Notes</label>
            <textarea id="client-notes" rows="3"></textarea>
            <div class="modal-actions">
                <button id="modal-cancel" class="btn btn-ghost">Cancel</button>
                <button id="modal-save" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>

    <div id="confirm-backdrop" class="modal-backdrop hidden">
        <div class="modal modal-narrow">
            <h2>Delete this client?</h2>
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
    <script src="<?= ehs_url('assets/js/clients.js') ?>"></script>
</body>
</html>