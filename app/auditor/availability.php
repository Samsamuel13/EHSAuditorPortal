<?php
require_once __DIR__ . '/../includes/auth.php';

// Any authenticated role can use their own availability calendar.
$user = ehs_require_role(['super_admin', 'admin', 'auditor']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars(ehs_csrf_token()) ?>">
<title>My Availability — <?= htmlspecialchars(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= ehs_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/calendar.css') ?>">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.21/index.global.min.js"></script>
</head>
<body>
    <header class="topbar" style="background: <?= htmlspecialchars($user['color_hex']) ?>;">
        <span class="topbar-brand">
            <img src="<?= ehs_url('assets/img/logo.png') ?>" alt="EHS Universal" class="topbar-logo">
            <span class="topbar-subtitle">Auditor Scheduler</span>
        </span>
        <span class="topbar-user">
            <?= htmlspecialchars($user['name']) ?>
            &middot; <a href="<?= ehs_url('auditor/index.php') ?>">Dashboard</a>
            &middot; <a href="<?= ehs_url('auditor/day_schedule.php') ?>">My Day Schedule</a>
            &middot; <a href="<?= ehs_url('auditor/profile.php') ?>">My Account</a>
            &middot; <a href="<?= ehs_url('logout.php') ?>">Log out</a>
        </span>
    </header>

    <div class="back-nav">
        <button onclick="history.back()" class="btn btn-ghost-light btn-small back-btn">&larr; Back</button>
    </div>

    <main class="page">
        <h1>My Availability</h1>
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

    <!-- Toast -->
    <div id="toast" class="toast hidden"></div>

    <script>window.EHS_BASE_URL = "<?= addslashes(ehs_url()) ?>";</script>
    <script src="<?= ehs_url('assets/js/session_guard.js') ?>"></script>
    <script src="<?= ehs_url('assets/js/availability.js') ?>"></script>
</body>
</html>
