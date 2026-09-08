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
<title>Auditors — <?= htmlspecialchars(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= ehs_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/calendar.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/management.css') ?>">
</head>
<body>
            <?php $activePage = 'auditors'; require __DIR__ . '/../includes/sidebar_nav.php'; ?>
    <div class="back-nav">
        <button onclick="history.back()" class="btn btn-ghost-light btn-small back-btn">&larr; Back</button>
    </div>

        <main class="app-main page">
        <h1>Auditors</h1>
        <p class="field-hint">
            This screen edits an auditor's operational profile (color, contact number, certified
            schemes, active/inactive). To create a new login, change a role, or reset a password,
            <?php if ($user['role'] === 'super_admin'): ?>
                use <a href="<?= ehs_url('admin/users.php') ?>">User Accounts</a>.
            <?php else: ?>
                ask a super admin to set up the account first.
            <?php endif; ?>
        </p>

        <div class="mgmt-table-wrap">
            <table class="mgmt-table">
                <thead><tr><th>Color</th><th>Name</th><th>Email</th><th>Phone</th><th>Schemes</th><th>Status</th><th></th></tr></thead>
                <tbody id="auditors-body"></tbody>
            </table>
            <div id="empty-state" class="mgmt-empty hidden">No auditors found.</div>
        </div>
    </main>
    </div>

    <div id="modal-backdrop" class="modal-backdrop hidden">
        <div class="modal modal-wide">
            <h2 id="modal-title">Edit auditor profile</h2>
            <p id="modal-name" style="font-weight:600;"></p>

            <label for="auditor-color">Color</label>
            <input type="color" id="auditor-color">

            <label for="auditor-phone">Phone</label>
            <input type="text" id="auditor-phone" maxlength="30">

            <label for="auditor-status">Status</label>
            <select id="auditor-status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>

            <label>Approved schemes</label>
            <div id="scheme-checkboxes" class="checkbox-grid checkbox-grid-scroll"></div>

            <div class="modal-actions">
                <button id="modal-cancel" class="btn btn-ghost">Cancel</button>
                <button id="modal-save" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>

    <div id="toast" class="toast hidden"></div>
    <script>window.EHS_BASE_URL = "<?= addslashes(ehs_url()) ?>";</script>
    <script src="<?= ehs_url('assets/js/session_guard.js') ?>"></script>
    <script src="<?= ehs_url('assets/js/auditors.js') ?>"></script>
</body>
</html>