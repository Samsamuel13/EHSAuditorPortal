<?php
/**
 * client-management/cron/auto_expire_certifications.php
 *
 * Run this on a schedule (e.g. daily via cron) — it does NOT run on its own.
 * Example crontab entry (once a day, just after midnight server time):
 *   5 0 * * * php /path/to/auditor_portal/client-management/cron/auto_expire_certifications.php >> /path/to/logs/cm_auto_expire_cron.log 2>&1
 *
 * Flips any certification still marked 'active' or 'pending' to 'expired'
 * once its Recertification date (expiry_date) has passed. This is a
 * BACKSTOP — the same check also runs live, inline, every time the
 * Renewal Dashboard or a client's certification list is loaded (see
 * cm_auto_expire_overdue_certifications() in cm_helpers.php), so in
 * practice this cron mostly matters on days nobody opens either page.
 * Never touches 'suspended' or 'withdrawn' — those are explicit manual
 * decisions, not something date math should silently override.
 *
 * Plain CLI script — does not go through auth.php (no browser session in
 * a cron job). Connects directly using config.php credentials, same
 * pattern as send_renewal_reminders.php.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script is for command-line/cron use only.');
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../includes/cm_helpers.php';

try {
    $db = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements, same as includes/db.php
        ]
    );
} catch (PDOException $e) {
    fwrite(STDERR, 'DB connection failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$changed = cm_auto_expire_overdue_certifications($db);

echo date('Y-m-d H:i:s') . " — auto-expire check complete. Certifications flipped to 'expired': $changed\n";