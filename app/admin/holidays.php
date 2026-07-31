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
<title>Holidays — <?= htmlspecialchars(APP_NAME) ?></title>
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
        <h1>Holidays</h1>

        <div class="mgmt-toolbar">
            <label for="year-select" style="margin:0;">Year:
                <select id="year-select"></select>
            </label>
            <div>
                <button id="bulk-btn" class="btn btn-ghost-light btn-small">Bulk import</button>
                <button id="add-btn" class="btn btn-primary btn-small">+ Add holiday</button>
            </div>
        </div>

        <div class="mgmt-table-wrap">
            <table class="mgmt-table">
                <thead><tr><th>Date</th><th>Name</th><th>Type</th><th></th></tr></thead>
                <tbody id="holidays-body"></tbody>
            </table>
            <div id="empty-state" class="mgmt-empty hidden">No holidays recorded for this year.</div>
        </div>
    </main>

    <div id="modal-backdrop" class="modal-backdrop hidden">
        <div class="modal">
            <h2 id="modal-title">Add holiday</h2>
            <label for="holiday-date">Date</label>
            <input type="date" id="holiday-date">
            <label for="holiday-name">Name</label>
            <input type="text" id="holiday-name" maxlength="150">
            <label for="holiday-type">Type</label>
            <select id="holiday-type">
                <option value="public_holiday">Public holiday</option>
                <option value="company_holiday">Company holiday</option>
            </select>
            <div class="modal-actions">
                <button id="modal-cancel" class="btn btn-ghost">Cancel</button>
                <button id="modal-save" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>

    <div id="bulk-modal-backdrop" class="modal-backdrop hidden">
        <div class="modal modal-wide bulk-import-box">
            <h2>Bulk import holidays</h2>
            <p class="field-hint">One per line: <code>YYYY-MM-DD, Holiday name</code>. Optionally add
            <code>, company</code> at the end to mark it a company holiday (default is public holiday).
            Existing dates will be updated, not duplicated.</p>
            <textarea id="bulk-text" placeholder="2026-01-01, New Year's Day&#10;2026-12-25, Christmas Day"></textarea>
            <div class="modal-actions">
                <button id="bulk-cancel" class="btn btn-ghost">Cancel</button>
                <button id="bulk-save" class="btn btn-primary">Import</button>
            </div>
        </div>
    </div>

    <div id="confirm-backdrop" class="modal-backdrop hidden">
        <div class="modal modal-narrow">
            <h2>Delete this holiday?</h2>
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
    <script src="<?= ehs_url('assets/js/holidays.js') ?>"></script>
</body>
</html>
