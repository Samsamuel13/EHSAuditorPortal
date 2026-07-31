<?php
require_once __DIR__ . '/includes/auth.php';

if (!ehs_is_logged_in()) {
    header('Location: ' . ehs_url('login.php'));
    exit;
}

$role = ehs_current_user()['role'];
if ($role === 'super_admin' || $role === 'admin') {
    header('Location: ' . ehs_url('admin/index.php'));
} else {
    header('Location: ' . ehs_url('auditor/index.php'));
}
exit;
