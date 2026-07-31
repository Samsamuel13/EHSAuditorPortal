<?php
// File: admin/day_schedule.php
require_once __DIR__ . '/../includes/auth.php';

$user = ehs_require_role(['super_admin', 'admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars(ehs_csrf_token()) ?>">
<title>My Day Schedule — <?= htmlspecialchars(APP_NAME) ?></title>
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
            <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['role']) ?>)
            &middot; <a href="<?= ehs_url('admin/index.php') ?>">Dashboard</a>
            &middot; <a href="<?= ehs_url('admin/schedule.php') ?>">Master Schedule</a>
            &middot; <a href="<?= ehs_url('logout.php') ?>">Log out</a>
        </span>
    </header>

    <div class="back-nav">
        <button onclick="history.back()" class="btn btn-ghost-light btn-small back-btn">&larr; Back</button>
    </div>

    <main class="page">
        <h1>My Day Schedule</h1>
        <p class="field-hint">
            Personal tasks and meetings — separate from formal audits. Only visible to you.
        </p>

        <div class="mgmt-toolbar">
            <label style="margin:0;">Date:
                <input type="date" id="day-picker">
            </label>
            <div style="display:flex; gap:6px;">
                <button id="prev-day-btn" class="btn btn-ghost-light btn-small">← Prev day</button>
                <button id="today-btn" class="btn btn-ghost-light btn-small">Today</button>
                <button id="next-day-btn" class="btn btn-ghost-light btn-small">Next day →</button>
            </div>
        </div>

        <div class="mgmt-table-wrap" style="padding: 16px; max-width: 560px;">
            <div id="items-list"></div>
            <div id="empty-state" class="mgmt-empty hidden">Nothing scheduled for this day.</div>

            <hr style="margin: 16px 0; border: none; border-top: 1px solid #eef0f2;">

            <div class="modal-row">
                <div>
                    <label for="new-time-label">Time (optional)</label>
                    <input type="text" id="new-time-label" placeholder="e.g. 11:00 AM or AM" maxlength="50">
                </div>
                <div style="flex: 2;">
                    <label for="new-title">What</label>
                    <input type="text" id="new-title" placeholder="e.g. NUS meeting" maxlength="255">
                </div>
            </div>
            <button id="add-item-btn" class="btn btn-primary btn-small" style="margin-top:12px;">+ Add item</button>
        </div>
    </main>

    <div id="toast" class="toast hidden"></div>

    <script>window.EHS_BASE_URL = "<?= addslashes(ehs_url()) ?>";</script>
    <script src="<?= ehs_url('assets/js/session_guard.js') ?>"></script>
    <script src="<?= ehs_url('assets/js/day_schedule.js') ?>"></script>
</body>
</html>
