<?php
require_once __DIR__ . '/includes/auth.php';

ehs_logout();
header('Location: ' . ehs_url('login.php'));
exit;
