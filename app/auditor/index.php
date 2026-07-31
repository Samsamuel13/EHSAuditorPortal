<?php
require_once __DIR__ . '/../includes/auth.php';

// Role middleware: only auditors may view this page (admins have their own dashboard).
$user = ehs_require_role(['auditor']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars(ehs_csrf_token()) ?>">
<title>My Dashboard — <?= htmlspecialchars(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= ehs_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/calendar.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/dashboard.css') ?>">
</head>
<body>
    <header class="topbar" style="background: <?= htmlspecialchars($user['color_hex']) ?>;">
        <span class="topbar-brand">
            <img src="<?= ehs_url('assets/img/logo.png') ?>" alt="EHS Universal" class="topbar-logo">
            <span class="topbar-subtitle">Auditor Scheduler</span>
        </span>
        <span class="topbar-user">
            <?= htmlspecialchars($user['name']) ?>
            &middot; <a href="<?= ehs_url('auditor/availability.php') ?>">My Availability</a>
            &middot; <a href="<?= ehs_url('auditor/day_schedule.php') ?>">My Day Schedule</a>
            &middot; <a href="<?= ehs_url('auditor/profile.php') ?>">My Account</a>
            &middot; <a href="<?= ehs_url('logout.php') ?>">Log out</a>
        </span>
    </header>

    <main class="page">
        <h1>My Dashboard</h1>
        <p>Welcome, <strong><?= htmlspecialchars($user['name']) ?></strong>.</p>
        <p><a href="<?= ehs_url('auditor/availability.php') ?>" class="btn btn-primary" style="width:auto; display:inline-block;">Update my availability →</a></p>

        <div class="dash-grid">
            <div class="dash-card span-2">
                <h3>Upcoming assignments</h3>
                <div id="upcoming-list"></div>
            </div>

            <div class="dash-card">
                <h3>My availability completeness</h3>
                <div id="availability-summary"></div>
            </div>
        </div>
    </main>

    <div id="toast" class="toast hidden"></div>

    <script>window.EHS_BASE_URL = "<?= addslashes(ehs_url()) ?>";</script>
    <script src="<?= ehs_url('assets/js/session_guard.js') ?>"></script>
    <script src="<?= ehs_url('assets/js/dashboard_auditor.js') ?>"></script>
</body>
</html>
