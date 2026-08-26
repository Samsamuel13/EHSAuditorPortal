<?php
// File: admin/index.php
require_once __DIR__ . '/../includes/auth.php';

// Role middleware: only admins and super admins may view this page.
$user = ehs_require_role(['super_admin', 'admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars(ehs_csrf_token()) ?>">
<title>Admin Dashboard — <?= htmlspecialchars(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= ehs_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/calendar.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/dashboard.css') ?>">
</head>
<body>
    <header class="topbar">
        <span class="topbar-brand">
            <img src="<?= ehs_url('assets/img/logo.png') ?>" alt="EHS Universal" class="topbar-logo">
            <span class="topbar-subtitle">Auditor Scheduler</span>
        </span>
        <span class="topbar-user">
            <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['role']) ?>)
            &middot; <a href="<?= ehs_url('admin/schedule.php') ?>">Master Schedule</a>
            &middot; <a href="<?= ehs_url('admin/day_schedule.php') ?>">My Day Schedule</a>
            &middot; <a href="<?= ehs_url('client-management/index.php') ?>">Client Management</a>
            &middot; <a href="<?= ehs_url('crm/index.php') ?>">CRM</a>
            &middot; <a href="<?= ehs_url('admin/profile.php') ?>">My Account</a>
            &middot; <a href="<?= ehs_url('logout.php') ?>">Log out</a>
        </span>
    </header>

    <main class="page">
        <h1>Admin Dashboard</h1>
        <p>Logged in as <strong><?= htmlspecialchars($user['name']) ?></strong>.</p>
        <p><a href="<?= ehs_url('admin/schedule.php') ?>" class="btn btn-primary" style="width:auto; display:inline-block;">Open Master Schedule →</a></p>

        <h2 style="margin-top:32px; font-size:1.05rem;">Manage</h2>
        <div style="display:flex; flex-wrap:wrap; gap:10px;">
            <a href="<?= ehs_url('admin/auditors.php') ?>" class="btn btn-ghost-light btn-small" style="width:auto;">Auditors</a>
            <a href="<?= ehs_url('admin/schemes.php') ?>" class="btn btn-ghost-light btn-small" style="width:auto;">Schemes</a>
            <a href="<?= ehs_url('admin/clients.php') ?>" class="btn btn-ghost-light btn-small" style="width:auto;">Clients</a>
            <a href="<?= ehs_url('admin/holidays.php') ?>" class="btn btn-ghost-light btn-small" style="width:auto;">Holidays</a>
            <a href="<?= ehs_url('admin/export.php') ?>" class="btn btn-ghost-light btn-small" style="width:auto;">Export</a>
            <?php if ($user['role'] === 'super_admin'): ?>
                <a href="<?= ehs_url('admin/users.php') ?>" class="btn btn-ghost-light btn-small" style="width:auto;">User Accounts</a>
                <a href="<?= ehs_url('admin/activity_log.php') ?>" class="btn btn-ghost-light btn-small" style="width:auto;">Activity Log</a>
                <a href="<?= ehs_url('admin/auditor_availability.php') ?>" class="btn btn-ghost-light btn-small" style="width:auto;">Update Auditor Availability</a>
            <?php endif; ?>
        </div>

        <div class="dash-grid">
            <div class="dash-card">
                <h3 id="audits-month-title">Audits this month</h3>
                <div class="stat-row">
                    <span class="stat-number" id="audits-total">—</span>
                    <span class="stat-label">total</span>
                </div>
                <div id="audits-by-status" class="status-breakdown"></div>
            </div>

            <div class="dash-card">
                <h3>Pending confirmation</h3>
                <div id="pending-list"></div>
            </div>

            <div class="dash-card">
                <h3>Overdue completions</h3>
                <div id="overdue-completions-list"></div>
            </div>

            <div class="dash-card">
                <h3>Upcoming holidays</h3>
                <div id="holidays-list"></div>
            </div>

            <div class="dash-card span-2">
                <h3>Auditor utilization this month</h3>
                <div id="utilization-list"></div>
            </div>

            <div class="dash-card span-2">
                <h3>My upcoming assignments</h3>
                <div id="my-upcoming-list"></div>
            </div>
        </div>
    </main>

    <div id="toast" class="toast hidden"></div>

    <script>window.EHS_BASE_URL = "<?= addslashes(ehs_url()) ?>";</script>
    <script src="<?= ehs_url('assets/js/session_guard.js') ?>"></script>
    <script src="<?= ehs_url('assets/js/dashboard_admin.js') ?>"></script>
</body>
</html>
