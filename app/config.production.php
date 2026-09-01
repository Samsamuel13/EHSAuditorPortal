<?php
// File: config.production.php
// Rename this to config.php ONLY on the production server
// (public_html/auditor_portal/config.php). Your local MAMP config.php is
// untouched — keep using that one for local development.

/**
 * config.php — environment-specific settings only.
 * No application logic lives here. Keep this file OUT of version control.
 */

// --- Database ---
define('DB_HOST', 'localhost');   // usually correct on shared hosting; change if your host gave you a different DB host
define('DB_NAME', 'ehsuser');
define('DB_USER', 'ehsuser');
define('DB_PASS', 'cCyG6R6PZ@z1');   // <-- fill this in yourself; never share it with me
define('DB_CHARSET', 'utf8mb4');

// --- App ---
define('APP_NAME', 'EHS Universal — Auditor Scheduler');
define('APP_TIMEZONE', 'Asia/Singapore');

// Base URL the app is served from — scheme + host + subfolder, NO trailing
// slash. Every generated link/redirect and every JS fetch() call is built
// from this constant.
define('URL_BASE', 'https://ehscertification.sg/auditor_portal');

// --- Session / security ---
// IMPORTANT: only set this to true once you've confirmed the site actually
// loads over HTTPS (padlock in the browser, no certificate warnings). If
// you set this to true before HTTPS is working, the login cookie will
// silently fail to be sent and nobody will be able to log in. If you're
// not 100% sure HTTPS is live yet, leave this as false for now — you can
// flip it the moment you've confirmed HTTPS works, no other changes needed.
define('SESSION_SECURE_COOKIE', false);
define('SESSION_LIFETIME_SECONDS', 8 * 60 * 60); // 8 hours
