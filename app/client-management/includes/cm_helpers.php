<?php
/**
 * client-management/includes/cm_helpers.php
 *
 * Small helpers used only by this module's pages/API endpoints. This is a
 * deliberate copy of the JSON-response pattern in the scheduling system's
 * includes/api.php rather than a require of that file — the isolation
 * requirement for this module is "no shared PHP files/classes beyond the
 * common auth/session check", so this module brings its own copy instead
 * of depending on scheduling-system code that could change independently.
 *
 * Every page/endpoint in this module should:
 *   require_once __DIR__ . '/../../includes/auth.php';   // shared login/session/users only
 *   require_once __DIR__ . '/cm_helpers.php';             // this module's own helpers
 */

function cm_json_response($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function cm_json_error(string $message, int $statusCode = 400): void
{
    cm_json_response(['error' => $message], $statusCode);
}

/** Parse a JSON request body into an associative array. */
function cm_json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Validate 'YYYY-MM-DD'. Returns true/false — never trust client dates blindly. */
function cm_is_valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/** Trim a value to null-or-nonempty-string, enforcing a max length. */
function cm_clean_str(?string $value, int $maxLen): ?string
{
    $value = trim((string) $value);
    if ($value === '') return null;
    return mb_substr($value, 0, $maxLen);
}

/**
 * Activity log scoped ONLY to this module — writes to cm_activity_log,
 * never to the scheduling system's shared `activity_log` table.
 */
function cm_log_activity(int $userId, string $action, string $entityType, ?int $entityId, string $details = ''): void
{
    $stmt = get_db()->prepare(
        'INSERT INTO cm_activity_log (user_id, action, entity_type, entity_id, details)
         VALUES (:user_id, :action, :entity_type, :entity_id, :details)'
    );
    $stmt->execute([
        'user_id'     => $userId,
        'action'      => $action,
        'entity_type' => $entityType,
        'entity_id'   => $entityId,
        'details'     => $details,
    ]);
}

/** Absolute path to the (webserver-blocked, .htaccess-protected) upload storage root. */
function cm_storage_root(): string
{
    $dir = __DIR__ . '/../storage/certification_docs';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    return $dir;
}

/** Allowed upload types, mapped to their expected MIME types (checked via finfo, not just extension). */
function cm_allowed_upload_mimes(): array
{
    return [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
}

const CM_MAX_UPLOAD_BYTES = 10 * 1024 * 1024; // 10 MB

/**
 * Read the configured renewal alert thresholds (days), sorted ascending.
 * Falls back to [30, 60, 90] if the setting is missing or malformed —
 * the dashboard should never break just because cm_settings is empty.
 */
function cm_get_renewal_thresholds(PDO $db): array
{
    $stmt = $db->prepare("SELECT setting_value FROM cm_settings WHERE setting_key = 'renewal_alert_thresholds' LIMIT 1");
    $stmt->execute();
    $raw = $stmt->fetchColumn();

    $parts = $raw ? array_map('intval', explode(',', $raw)) : [];
    $parts = array_values(array_filter($parts, fn($n) => $n > 0));
    sort($parts);

    return count($parts) === 3 ? $parts : [30, 60, 90];
}

function cm_set_renewal_thresholds(PDO $db, array $thresholds): void
{
    $csv = implode(',', $thresholds);
    // Two distinct placeholders for the same value: PDO with
    // ATTR_EMULATE_PREPARES=false (real prepared statements, as db.php
    // configures) does not support the same named placeholder appearing
    // twice in one query.
    $stmt = $db->prepare(
        "INSERT INTO cm_settings (setting_key, setting_value) VALUES ('renewal_alert_thresholds', :val1)
         ON DUPLICATE KEY UPDATE setting_value = :val2"
    );
    $stmt->execute(['val1' => $csv, 'val2' => $csv]);
}

/**
 * Compute a simple status/urgency badge for a certification's expiry date,
 * given the client's alert thresholds (or a default 90/60/30/0 scale if
 * none configured). Used by both the client detail page and renewal
 * dashboard so the "amber/red" logic lives in exactly one place.
 */
function cm_expiry_badge(?string $expiryDate, string $today): array
{
    if ($expiryDate === null) {
        return ['label' => 'No expiry set', 'class' => 'cm-badge-neutral'];
    }
    $daysLeft = (strtotime($expiryDate) - strtotime($today)) / 86400;

    if ($daysLeft < 0) {
        return ['label' => 'Overdue/Expired', 'class' => 'cm-badge-red'];
    }
    if ($daysLeft <= 30) {
        return ['label' => 'Expiring soon', 'class' => 'cm-badge-amber'];
    }
    return ['label' => 'Active', 'class' => 'cm-badge-green'];
}