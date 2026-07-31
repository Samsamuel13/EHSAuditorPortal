<?php
// File: admin/profile.php
require_once __DIR__ . '/../includes/auth.php';

$user = ehs_require_role(['super_admin', 'admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars(ehs_csrf_token()) ?>">
<title>My Account — <?= htmlspecialchars(APP_NAME) ?></title>
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
        <h1>My Account</h1>

        <div class="mgmt-table-wrap" style="padding: 24px; max-width: 420px;">
            <p><strong><?= htmlspecialchars($user['name']) ?></strong><br>
            <span class="field-hint"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $user['role']))) ?></span></p>

            <h3 style="margin-top:24px;">Change password</h3>
            <p class="field-hint">
                To change your name, email, username, or role, use
                <?php if ($user['role'] === 'super_admin'): ?>
                    <a href="<?= ehs_url('admin/users.php') ?>">User Accounts</a>.
                <?php else: ?>
                    ask a super admin.
                <?php endif; ?>
            </p>

            <div id="alert-box" class="alert alert-error hidden"></div>

            <label for="current-password">Current password</label>
            <input type="password" id="current-password" autocomplete="current-password">

            <label for="new-password">New password</label>
            <input type="password" id="new-password" autocomplete="new-password" placeholder="Minimum 8 characters">

            <label for="confirm-password">Confirm new password</label>
            <input type="password" id="confirm-password" autocomplete="new-password">

            <button id="save-btn" class="btn btn-primary" style="margin-top:20px;">Update password</button>
        </div>
    </main>

    <div id="toast" class="toast hidden"></div>

    <script>window.EHS_BASE_URL = "<?= addslashes(ehs_url()) ?>";</script>
    <script src="<?= ehs_url('assets/js/session_guard.js') ?>"></script>
    <script src="<?= ehs_url('assets/js/profile.js') ?>"></script>
</body>
</html>
