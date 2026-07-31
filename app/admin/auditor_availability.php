<?php
// File: admin/auditor_availability.php
require_once __DIR__ . '/../includes/auth.php';

$user = ehs_require_role(['super_admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars(ehs_csrf_token()) ?>">
<title>Update Auditor Availability — <?= htmlspecialchars(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= ehs_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/calendar.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/management.css') ?>">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.21/index.global.min.js"></script>
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
            &middot; <a href="<?= ehs_url('admin/auditors.php') ?>">Auditors</a>
            &middot; <a href="<?= ehs_url('logout.php') ?>">Log out</a>
        </span>
    </header>

    <div class="back-nav">
        <button onclick="history.back()" class="btn btn-ghost-light btn-small back-btn">&larr; Back</button>
    </div>

    <main class="page">
        <h1>Update Auditor Availability</h1>
        <p class="field-hint">
            Super admin override — use this when an auditor can't update their own
            calendar. Every change here is logged with your name and theirs.
        </p>

        <div class="filter-bar">
            <select id="auditor-select" style="min-width:220px;">
                <option value="">Select an auditor…</option>
            </select>
        </div>

        <div id="editor-area" class="hidden">
            <p class="page-hint">
                Click a date to select it, <kbd>Ctrl</kbd>/<kbd>Cmd</kbd>+click to add more dates,
                or click-drag across a range. Then apply a status to every selected date at once.
            </p>

            <div class="legend">
                <span class="legend-item"><i class="dot dot-available"></i> Available</span>
                <span class="legend-item"><i class="dot dot-unavailable"></i> Unavailable</span>
                <span class="legend-item"><i class="dot dot-tentative"></i> Tentative</span>
                <span class="legend-item"><i class="dot dot-holiday"></i> Holiday</span>
                <span class="legend-item"><i class="dot dot-weekend"></i> Weekend</span>
            </div>

            <div id="calendar"></div>
        </div>
        <div id="no-selection-note" class="mgmt-empty">Select an auditor above to view or edit their calendar.</div>
    </main>

    <!-- Selection toolbar -->
    <div id="selection-bar" class="selection-bar hidden">
        <span id="selection-count">0 dates selected</span>
        <button id="apply-btn" class="btn btn-primary btn-small">Apply availability</button>
        <button id="clear-selection-btn" class="btn btn-ghost btn-small">Clear</button>
    </div>

    <!-- Bulk update modal -->
    <div id="modal-backdrop" class="modal-backdrop hidden">
        <div class="modal">
            <h2>Set availability</h2>
            <p id="modal-date-summary" class="modal-subtitle"></p>
            <p class="field-hint">On behalf of: <strong id="modal-auditor-name"></strong></p>

            <label>Session</label>
            <div class="segmented">
                <label><input type="radio" name="session" value="FULL_DAY" checked> Full day</label>
                <label><input type="radio" name="session" value="AM"> AM only</label>
                <label><input type="radio" name="session" value="PM"> PM only</label>
            </div>

            <label>Status</label>
            <div class="segmented">
                <label><input type="radio" name="status" value="available" checked> Available</label>
                <label><input type="radio" name="status" value="unavailable"> Unavailable</label>
                <label><input type="radio" name="status" value="tentative"> Tentative</label>
            </div>

            <label for="note">Note (optional)</label>
            <input type="text" id="note" maxlength="255" placeholder="e.g. on leave, personal work">

            <div class="modal-actions">
                <button id="modal-cancel" class="btn btn-ghost">Cancel</button>
                <button id="modal-save" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>

    <div id="toast" class="toast hidden"></div>

    <script>window.EHS_BASE_URL = "<?= addslashes(ehs_url()) ?>";</script>
    <script src="<?= ehs_url('assets/js/session_guard.js') ?>"></script>
    <script src="<?= ehs_url('assets/js/admin_availability.js') ?>"></script>
</body>
</html>
