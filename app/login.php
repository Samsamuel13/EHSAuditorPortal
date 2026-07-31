<?php
require_once __DIR__ . '/includes/auth.php';

// Already logged in? Send them straight to their dashboard.
if (ehs_is_logged_in()) {
    ehs_redirect_to_dashboard(ehs_current_user()['role']);
    exit;
}

function ehs_redirect_to_dashboard(string $role): void
{
    if ($role === 'super_admin' || $role === 'admin') {
        header('Location: ' . ehs_url('admin/index.php'));
    } else {
        header('Location: ' . ehs_url('auditor/index.php'));
    }
    exit;
}

$error = '';

// --- very simple brute-force throttle (per session) ---
const MAX_ATTEMPTS   = 5;
const LOCKOUT_SECONDS = 60;

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_locked_until'] = 0;
}

$locked = $_SESSION['login_locked_until'] > time();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {
    ehs_verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $user = ehs_attempt_login($username, $password);

        if ($user) {
            $_SESSION['login_attempts'] = 0;
            ehs_redirect_to_dashboard($user['role']);
            exit;
        }

        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= MAX_ATTEMPTS) {
            $_SESSION['login_locked_until'] = time() + LOCKOUT_SECONDS;
            $_SESSION['login_attempts'] = 0;
        }
        // Deliberately generic — never reveal whether the username exists.
        $error = 'Invalid username or password.';
    }
} elseif ($locked) {
    $error = 'Too many failed attempts. Please try again in a minute.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in — <?= htmlspecialchars(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= ehs_url('assets/css/style.css') ?>">
</head>
<body class="auth-page">
    <main class="auth-card">
        <h1><?= htmlspecialchars(APP_NAME) ?></h1>
        <p class="auth-subtitle">Sign in to manage schedules and availability</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= ehs_url('login.php') ?>" novalidate>
            <?= ehs_csrf_field() ?>

            <label for="username">Username</label>
            <input type="text" id="username" name="username" autocomplete="username" required autofocus>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>

            <button type="submit" class="btn btn-primary" <?= $locked ? 'disabled' : '' ?>>Sign in</button>
        </form>
    </main>
</body>
</html>
