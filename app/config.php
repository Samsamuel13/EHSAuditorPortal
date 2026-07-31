<?php
/**
 * config.php — environment-specific settings only.
 * No application logic lives here. Edit these values for your server,
 * then keep this file OUT of version control (add it to .gitignore).
 */

// --- Database ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'ehs_auditor_planner');
define('DB_USER', 'ehs_user_1');           // change for production
define('DB_PASS', 'P@ssw0rd123$');               // change for production
define('DB_CHARSET', 'utf8mb4');

// --- App ---
define('APP_NAME', 'EHS Universal — Auditor Scheduler');
define('APP_TIMEZONE', 'Asia/Singapore');

// Base URL the app is served from — include the scheme, host, port, and any
// subfolder, but NO trailing slash. Every generated link/redirect and every
// JS fetch() call is built from this, so the app works whether it's served
// at a domain root or (like local XAMPP/MAMP setups) under a subfolder.
// Examples:
//   Local:      'http://localhost:8888/auditor_portal/app'
//   Production: 'https://schedule.ehsuniversal.com'
define('URL_BASE', 'http://localhost:8888/auditor_portal/app');

// --- Session / security ---
// Set to true once the site is served over HTTPS (recommended in production).
define('SESSION_SECURE_COOKIE', false);
define('SESSION_LIFETIME_SECONDS', 8 * 60 * 60); // 8 hours
