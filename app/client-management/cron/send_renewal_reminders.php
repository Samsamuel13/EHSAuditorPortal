<?php
/**
 * client-management/cron/send_renewal_reminders.php
 *
 * Run this on a schedule (e.g. daily via cron) — it does NOT run on its own.
 * Example crontab entry (once a day at 7am server time):
 *   0 7 * * * php /path/to/auditor_portal/client-management/cron/send_renewal_reminders.php >> /path/to/logs/cm_renewal_cron.log 2>&1
 *
 * For each active/pending/suspended certification (never 'withdrawn'), works
 * out which of the 4 cycle milestones (1st Certification, Surveillance 1,
 * Surveillance 2, Recertification) is next due, using the same
 * cm_certification_next_due() logic as the dashboard. If that date falls
 * within any of the configured alert thresholds (default 30/60/90 days,
 * Super-Admin-configurable on the Renewal Dashboard) — or is already
 * overdue — it emails the configured admin address and records a row in
 * cm_renewal_alerts keyed on (certification, milestone, threshold), so a
 * re-run later the same day (or the next day, before the next threshold is
 * crossed) never sends a duplicate for the same milestone+threshold.
 *
 * This is a plain CLI script, not a web endpoint — it does NOT go through
 * auth.php (there's no browser session in a cron job). It connects to the
 * database directly using the same config.php credentials as the rest of
 * the app.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script is for command-line/cron use only.');
}

// ============================================================
// DISABLED as of the 4-stage renewal follow-up build.
// ============================================================
// This script and its table (cm_renewal_alerts) are INTENTIONALLY left
// fully intact — nothing was deleted, per instruction. This early-exit is
// the ONLY change: it stops this script from actually sending anything,
// so it can't fire duplicate/overlapping notifications alongside the new
// cm_renewal_followup_actions system (client-management/api/renewal_followups.php),
// which covers the same "who needs a nudge" purpose with a materially
// different (and now the primary) 4-stage model.
//
// TO RE-ENABLE: delete/comment out the exit() line immediately below.
// You should ALSO remove or disable the actual cPanel Cron Jobs entry
// calling this script — this code-level guard is a safety net, but the
// real "off switch" is removing the cron trigger itself in cPanel, since
// I can't do that from here. If the crontab entry is still active, this
// guard just makes each run a harmless no-op instead of actually
// disabling the schedule.
exit(0);
// ============================================================

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

$today = date('Y-m-d');

$thresholds = cm_get_renewal_thresholds($db);
$notifyEmailStmt = $db->prepare("SELECT setting_value FROM cm_settings WHERE setting_key = 'renewal_notify_email' LIMIT 1");
$notifyEmailStmt->execute();
$notifyEmail = $notifyEmailStmt->fetchColumn() ?: 'admin@ehscertification.sg';

$stmt = $db->query(
    "SELECT cert.*, c.company_name, st.name AS scheme_name
     FROM cm_certifications cert
     JOIN cm_clients c ON c.id = cert.cm_client_id
     JOIN cm_scheme_types st ON st.id = cert.cm_scheme_type_id
     WHERE cert.status != 'withdrawn'
       AND (cert.surveillance_1_date IS NOT NULL OR cert.surveillance_2_date IS NOT NULL OR cert.expiry_date IS NOT NULL)"
);
$certs = $stmt->fetchAll();

$sentCount = 0;
$skippedCount = 0;

foreach ($certs as $cert) {
    $next = cm_certification_next_due($cert, $today);
    if ($next['date'] === null) continue;

    $daysUntil = (int) round((strtotime($next['date']) - strtotime($today)) / 86400);

    // Which threshold bucket does this fall into, if any? Overdue always
    // qualifies (bucket label 'overdue'); otherwise it must be at or inside
    // one of the configured day thresholds.
    $matchedThreshold = null;
    if ($next['overdue']) {
        $matchedThreshold = 'overdue';
    } else {
        foreach ($thresholds as $t) {
            if ($daysUntil <= $t) { $matchedThreshold = (string) $t; break; }
        }
    }
    if ($matchedThreshold === null) { $skippedCount++; continue; }

    // De-dupe key: one alert per (certification, milestone, threshold-bucket).
    // If the same milestone/threshold combination was already sent, skip —
    // this is what lets the cron run daily without spamming the same reminder.
    $dedupeKey = $cert['id'] . ':' . $next['label'] . ':' . $matchedThreshold;
    $existsStmt = $db->prepare(
        "SELECT id FROM cm_renewal_alerts
         WHERE cm_certification_id = :cert_id AND alert_threshold_days = :threshold AND status = 'sent'
         LIMIT 1"
    );
    // alert_threshold_days is an int column; store 0 for the 'overdue' bucket
    // since it has no fixed day count, and 9999+the real day count would be
    // meaningless — 0 is unambiguous since a real threshold is always > 0.
    $thresholdForStorage = $matchedThreshold === 'overdue' ? 0 : (int) $matchedThreshold;
    $existsStmt->execute(['cert_id' => $cert['id'], 'threshold' => $thresholdForStorage]);
    if ($existsStmt->fetch()) { $skippedCount++; continue; }

    $subject = ($next['overdue'] ? '[OVERDUE] ' : '[Renewal Reminder] ')
        . $cert['company_name'] . ' — ' . $next['label'] . ' (' . $cert['scheme_name'] . ')';

    $body = '<p><strong>' . htmlspecialchars($cert['company_name']) . '</strong> — ' . htmlspecialchars($cert['scheme_name']) . '</p>'
        . '<p>' . htmlspecialchars($next['label']) . ' is ' . ($next['overdue'] ? 'OVERDUE' : 'due') . ' on <strong>' . htmlspecialchars($next['date']) . '</strong>'
        . ($next['overdue'] ? '' : ' (' . $daysUntil . ' day(s) from now)') . '.</p>'
        . '<p>Certificate #: ' . htmlspecialchars($cert['certificate_number'] ?? '—') . '<br>'
        . 'Status: ' . htmlspecialchars(ucfirst($cert['status'])) . '</p>'
        . '<p><a href="https://ehscertification.sg/auditor_portal/client-management/client_detail.php?id=' . (int) $cert['cm_client_id'] . '">View this client in Client Management</a></p>';

    $sent = cm_send_mail($notifyEmail, $subject, $body);

    $insertStmt = $db->prepare(
        "INSERT INTO cm_renewal_alerts (cm_certification_id, alert_threshold_days, status)
         VALUES (:cert_id, :threshold, :status)"
    );
    $insertStmt->execute([
        'cert_id' => $cert['id'],
        'threshold' => $thresholdForStorage,
        'status' => $sent ? 'sent' : 'pending', // 'pending' if mail() failed, so a later run retries it
    ]);

    if ($sent) {
        $sentCount++;
        echo "[sent] {$cert['company_name']} — {$next['label']} due {$next['date']}\n";
    } else {
        echo "[FAILED] {$cert['company_name']} — {$next['label']} due {$next['date']} (mail() returned false, will retry next run)\n";
    }
}

echo "\nDone. Sent: $sentCount, skipped (already sent or not yet due): $skippedCount, checked: " . count($certs) . "\n";