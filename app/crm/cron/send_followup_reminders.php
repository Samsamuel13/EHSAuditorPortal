<?php
/**
 * crm/cron/send_followup_reminders.php
 *
 * Run on a schedule (e.g. daily via cron) — does NOT run on its own.
 * Example crontab entry (daily at 8am server time):
 *   0 8 * * * php /path/to/auditor_portal/crm/cron/send_followup_reminders.php >> /path/to/logs/crm_followup_cron.log 2>&1
 *
 * For each not-done follow-up whose due_date is today or already overdue,
 * emails the follow-up's owner (falls back to a configured admin address if
 * the owner has no email) and stamps reminder_sent_at so a re-run the same
 * day doesn't re-send. An overdue follow-up gets a reminder EVERY DAY it
 * stays open (reminder_sent_at is reset once due_date passes, unlike the
 * one-shot renewal-alert dedupe in client-management) — the intent here is
 * "don't let this go quiet", not "notify once and move on".
 *
 * Plain CLI script — does not go through auth.php (no browser session in a
 * cron job). Connects directly using config.php credentials, same pattern
 * as client-management/cron/send_renewal_reminders.php.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script is for command-line/cron use only.');
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../includes/crm_helpers.php';

try {
    $db = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    fwrite(STDERR, 'DB connection failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$fallbackEmail = 'admin@ehscertification.sg';
$today = date('Y-m-d');

$stmt = $db->prepare(
    "SELECT f.*, l.company_name, u.email AS owner_email
     FROM crm_followups f
     JOIN crm_leads l ON l.id = f.crm_lead_id
     LEFT JOIN users u ON u.id = f.owner_id
     WHERE f.done = 0
       AND f.due_date <= :today
       AND (f.reminder_sent_at IS NULL OR DATE(f.reminder_sent_at) < :today2)"
);
// Two placeholders bound to the same value — real prepared statements
// reject reusing one named param twice in a query.
$stmt->execute(['today' => $today, 'today2' => $today]);
$followups = $stmt->fetchAll();

$sentCount = 0;

foreach ($followups as $fu) {
    $to = $fu['owner_email'] ?: $fallbackEmail;
    $overdue = $fu['due_date'] < $today;

    $subject = ($overdue ? '[OVERDUE] ' : '[Follow-up Due Today] ')
        . $fu['company_name'] . ' — ' . ucfirst($fu['type']);

    $body = '<p><strong>' . htmlspecialchars($fu['company_name']) . '</strong></p>'
        . '<p>Follow-up (' . htmlspecialchars($fu['type']) . ') was due <strong>' . htmlspecialchars($fu['due_date']) . '</strong>'
        . ($overdue ? ' and is now overdue.' : ' — today.') . '</p>'
        . (!empty($fu['note']) ? '<p>Note: ' . htmlspecialchars($fu['note']) . '</p>' : '')
        . '<p><a href="https://ehscertification.sg/auditor_portal/crm/lead_detail.php?id=' . (int) $fu['crm_lead_id'] . '">View this lead in CRM</a></p>';

    $sent = crm_send_mail_cli($to, $subject, $body);

    $updateStmt = $db->prepare('UPDATE crm_followups SET reminder_sent_at = NOW() WHERE id = :id');
    $updateStmt->execute(['id' => $fu['id']]);

    if ($sent) {
        $sentCount++;
        echo "[sent] {$fu['company_name']} — {$fu['type']} due {$fu['due_date']} -> $to\n";
    } else {
        echo "[FAILED] {$fu['company_name']} — {$fu['type']} due {$fu['due_date']} (mail() returned false)\n";
    }
}

echo "\nDone. Sent: $sentCount, checked: " . count($followups) . "\n";

/** Minimal mail sender for the CLI context (crm_helpers.php's version needs no session, but keep the naming distinct so it's obvious this runs outside a request). */
function crm_send_mail_cli(string $to, string $subject, string $htmlBody): bool
{
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: EHS Universal — CRM <no-reply@ehscertification.sg>',
    ];
    $ok = @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
    if (!$ok) {
        error_log("crm_send_mail_cli: failed to send to $to — subject: $subject");
    }
    return $ok;
}
