<?php
require_once __DIR__ . '/../includes/auth.php';
$user = ehs_require_role(['super_admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars(ehs_csrf_token()) ?>">
<title>User Accounts — <?= htmlspecialchars(APP_NAME) ?></title>
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
            &middot; <a href="<?= ehs_url('admin/activity_log.php') ?>">Activity Log</a>
            &middot; <a href="<?= ehs_url('admin/schedule.php') ?>">Master Schedule</a>
            &middot; <a href="<?= ehs_url('logout.php') ?>">Log out</a>
        </span>
    </header>

    <div class="back-nav">
        <button onclick="history.back()" class="btn btn-ghost-light btn-small back-btn">&larr; Back</button>
    </div>

    <main class="page">
        <h1>User Accounts</h1>
        <p class="field-hint">Super admin only. Create logins, assign roles, and reset passwords here.
        Day-to-day auditor profile edits (color, phone, schemes) live on the
        <a href="<?= ehs_url('admin/auditors.php') ?>">Auditors</a> screen.</p>

        <div class="mgmt-toolbar">
            <span></span>
            <button id="add-btn" class="btn btn-primary btn-small">+ Add user</button>
        </div>

        <div class="mgmt-table-wrap">
            <table class="mgmt-table">
                <thead><tr><th>Color</th><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead>
                <tbody id="users-body"></tbody>
            </table>
            <div id="empty-state" class="mgmt-empty hidden">No users found.</div>
        </div>
    </main>

    <div id="modal-backdrop" class="modal-backdrop hidden">
        <div class="modal modal-wide">
            <h2 id="modal-title">Add user</h2>

            <label for="user-name">Name</label>
            <input type="text" id="user-name" maxlength="100">

            <div class="modal-row">
                <div>
                    <label for="user-username">Username</label>
                    <input type="text" id="user-username" maxlength="50" autocomplete="off">
                </div>
                <div>
                    <label for="user-email">Email</label>
                    <input type="email" id="user-email" maxlength="150">
                </div>
            </div>

            <div class="modal-row">
                <div>
                    <label for="user-role">Role</label>
                    <select id="user-role">
                        <option value="auditor">Auditor</option>
                        <option value="admin">Admin</option>
                        <option value="super_admin">Super admin</option>
                    </select>
                </div>
                <div>
                    <label for="user-status">Status</label>
                    <select id="user-status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="modal-row">
                <div>
                    <label for="user-color">Color</label>
                    <input type="color" id="user-color" value="#3788d8">
                </div>
                <div>
                    <label for="user-phone">Phone</label>
                    <input type="text" id="user-phone" maxlength="30">
                </div>
            </div>

            <label for="user-password">Password <span id="password-hint" class="field-hint"></span></label>
            <input type="password" id="user-password" autocomplete="new-password" placeholder="Minimum 8 characters">

            <div class="modal-actions">
                <button id="modal-cancel" class="btn btn-ghost">Cancel</button>
                <button id="modal-save" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>

    <div id="confirm-backdrop" class="modal-backdrop hidden">
        <div class="modal modal-narrow">
            <h2>Delete this user?</h2>
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
    <script src="<?= ehs_url('assets/js/users.js') ?>"></script>
</body>
</html>
