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
 * The 4-stage renewal follow-up thresholds, counted back from a
 * certification's next milestone date (whichever CM_CYCLE_MILESTONES
 * milestone cm_certification_next_due() resolves to). Stage 1 is reached
 * first (furthest out), Stage 4 last (closest to/past the date).
 */
const CM_FOLLOWUP_STAGE_DAYS = [
    1 => 120, // ~4 months before
    2 => 60,  // ~2 months before
    3 => 30,  // 30 days before
    4 => 4,   // 4 days before
];

/**
 * Given a milestone's date and today's date, returns the HIGHEST stage
 * (1-4) that has been crossed as of today, or null if the date is still
 * more than Stage 1's threshold away (nothing due yet). "Crossed" means
 * today is within that many days of the milestone date OR the milestone
 * date has already passed (an overdue milestone is always at least Stage
 * 4, and stays there — this function doesn't invent a "Stage 5"; escalation
 * beyond Stage 4 is shown as "N days overdue" using the existing date math,
 * not a new stage number).
 *
 * Only the highest crossed stage is returned (not a list of all crossed
 * stages) — per the design agreed for the follow-up dashboard: showing
 * every previously-crossed stage as a separate open item would be
 * redundant noise once a later, more urgent stage is also due.
 */
function cm_followup_current_stage(?string $milestoneDate, string $today): ?int
{
    if ($milestoneDate === null) return null;

    $daysUntil = (strtotime($milestoneDate) - strtotime($today)) / 86400;

    $current = null;
    foreach (CM_FOLLOWUP_STAGE_DAYS as $stage => $days) {
        if ($daysUntil <= $days) {
            $current = $stage;
        }
    }
    return $current;
}

/** A certification's scheme's default_cycle_years (falls back to 3/ISO if the scheme is somehow missing/deleted). */
function cm_scheme_cycle_years(PDO $db, int $schemeTypeId): int
{
    $stmt = $db->prepare('SELECT default_cycle_years FROM cm_scheme_types WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $schemeTypeId]);
    $years = $stmt->fetchColumn();
    return $years !== false ? (int) $years : 3;
}

/**
 * The two business entities this system's clients belong to, mapped from
 * an accreditation body: SAC -> EHS Universal; IAS, SIS, and AxisCert ->
 * Axiscert (SIS operates under IAS, which trades as Axiscert). Anything
 * unmapped (JAS-ANZ, or no accreditation body recorded) defaults to EHS —
 * flagged here since that default was an assumption, not something stated
 * outright; correct it per-client via the Entity dropdown if wrong.
 */
const CM_ENTITIES = ['EHS', 'Axiscert'];

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
 * The 3 trackable cycle milestones (1st Certification isn't included —
 * a certification record existing at all already implies it happened),
 * mapped to their date column and completion-timestamp column. Shared
 * between cm_certification_next_due() and the Log Activity "mark complete"
 * feature so the milestone keys/labels live in exactly one place.
 */
const CM_CYCLE_MILESTONES = [
    'surveillance_1'  => ['label' => 'Surveillance 1',  'date_col' => 'surveillance_1_date', 'completed_col' => 'surveillance_1_completed_at'],
    'surveillance_2'  => ['label' => 'Surveillance 2',  'date_col' => 'surveillance_2_date', 'completed_col' => 'surveillance_2_completed_at'],
    'recertification' => ['label' => 'Recertification', 'date_col' => 'expiry_date',          'completed_col' => 'recertification_completed_at'],
];

/**
 * Given a certification row (must include issue_date, surveillance_1_date,
 * surveillance_2_date, expiry_date, and — if you want completion to be
 * factored in — surveillance_1_completed_at, surveillance_2_completed_at,
 * recertification_completed_at), work out which of the four cycle
 * milestones is "next due" and its date. Mirrors the client's own Excel
 * planner: 1st Certification -> Surveillance 1 -> Surveillance 2 -> Recertification.
 *
 * A milestone that's been marked COMPLETE (via Log Activity) is skipped
 * entirely, regardless of its date — an audit done a little early (or even
 * a little late but since confirmed done) shouldn't keep showing as the
 * "next due" item once it's actually been carried out. If the row doesn't
 * include the *_completed_at columns (older callers, or an explicit column
 * list that doesn't select them), completion is just treated as unknown/no
 * — behavior is unchanged from before this feature existed.
 *
 * Logic: of the milestones NOT marked complete, pick the earliest one that
 * is still today-or-future. If all remaining ones are in the past (or
 * missing), the certification is overdue — return the latest-dated
 * incomplete milestone. If every milestone is complete, there's nothing
 * pending.
 */
function cm_certification_next_due(array $cert, string $today): array
{
    $milestones = [];
    foreach (CM_CYCLE_MILESTONES as $m) {
        $milestones[] = [
            'label' => $m['label'],
            'date' => $cert[$m['date_col']] ?? null,
            'completed' => !empty($cert[$m['completed_col']] ?? null),
        ];
    }
    $pending = array_filter($milestones, fn($m) => !$m['completed']);

    $upcoming = array_filter($pending, fn($m) => $m['date'] !== null && $m['date'] >= $today);
    if ($upcoming) {
        usort($upcoming, fn($a, $b) => strcmp($a['date'], $b['date']));
        $next = $upcoming[0];
        return ['label' => $next['label'], 'date' => $next['date'], 'overdue' => false];
    }

    // Nothing upcoming — overdue. Report the latest-dated milestone that's
    // actually set (usually expiry_date/Recertification), so the message
    // reflects the real state instead of always blaming "Recertification"
    // when only an earlier milestone was ever filled in.
    $past = array_filter($pending, fn($m) => $m['date'] !== null);
    if ($past) {
        usort($past, fn($a, $b) => strcmp($b['date'], $a['date']));
        $last = $past[0];
        return ['label' => $last['label'], 'date' => $last['date'], 'overdue' => true];
    }

    return ['label' => null, 'date' => null, 'overdue' => false];
}

/**
 * Returns [startDate, endDate, label] for a calendar month offset from the
 * current month (0 = this month, -1 = last month, 1 = next month), as
 * 'YYYY-MM-DD' strings covering the FULL month (1st to last day).
 * Used for the "audit due last/this/next month" extract, which is a fixed
 * calendar window — distinct from the renewal dashboard's rolling
 * N-days-from-today thresholds.
 */
function cm_month_range(int $monthOffset): array
{
    $base = new DateTime('first day of this month');
    $base->modify($monthOffset . ' month');
    $start = $base->format('Y-m-d');
    $end = (clone $base)->modify('last day of this month')->format('Y-m-d');
    $label = $base->format('F Y');
    return [$start, $end, $label];
}

/**
 * Minimal, swappable mail sender. Uses PHP's built-in mail() by default —
 * works with zero setup but is frequently unreliable on shared hosting
 * (spam-folder or silent drop, no delivery confirmation). To switch to
 * SMTP later (recommended for production), replace the body of this one
 * function with a PHPMailer SMTP call — nothing else in the module needs
 * to change, since every caller only ever calls cm_send_mail().
 *
 * Returns true if the mail() call reported success (NOT proof of actual
 * delivery — mail() only confirms it was handed to the local MTA).
 */
function cm_send_mail(string $to, string $subject, string $htmlBody): bool
{
    $fromEmail = 'no-reply@ehscertification.sg';
    $fromName  = 'EHS Universal — Client Management';

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $fromName . ' <' . $fromEmail . '>',
    ];

    $ok = @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
    if (!$ok) {
        error_log("cm_send_mail: failed to send to $to — subject: $subject");
    }
    return $ok;
}

/**
 * Pure calculation: given an anchor date and a cycle length in years,
 * compute the milestone dates for that cycle. Generalizes the ISO 3-year
 * convention I confirmed against 265 real certification records (2
 * surveillance audits, each 1 month before its anniversary; Recertification
 * 1 month + 1 day before the final anniversary) to any cycle length: an
 * N-year cycle gets (N-1) evenly-spaced surveillance audits, same -1-month
 * offset, then Recertification at the N-year mark, same -1-month-1-day offset.
 *
 * For $cycleYears = 3 this produces EXACTLY the confirmed formula. For
 * other lengths (e.g. BizSafe's 2-year cycle, default_cycle_years on
 * cm_scheme_types) this is an EXTRAPOLATION of that same convention — there
 * is no existing BizSafe certification data in this system to verify
 * against, so treat these dates as a reasonable default to confirm with
 * the actual scheme requirements, not a verified fact the way the 3-year
 * case is.
 *
 * Returns ['surveillances' => [date, date, ...], 'recertification' => date].
 * The certifications table only has 2 surveillance date columns
 * (surveillance_1_date, surveillance_2_date), so callers should only use
 * as many entries from 'surveillances' as they have columns for — a
 * 2-year cycle naturally produces just 1 entry, leaving surveillance_2_date
 * unused/null, which cm_certification_next_due() already handles correctly
 * (a null date is simply skipped).
 */
function cm_compute_cycle_milestones(string $anchorDate, int $cycleYears): array
{
    $cycleYears = max(1, $cycleYears);
    $anchor = new DateTime($anchorDate);

    $surveillances = [];
    for ($year = 1; $year < $cycleYears; $year++) {
        $surveillances[] = (clone $anchor)->modify("+$year year")->modify('-1 month')->format('Y-m-d');
    }
    // Recertification: exactly 1 day before the pure N-year anniversary of
    // the anchor date — confirmed directly against a real case (anchor
    // 2026-09-03 -> Recert 2029-09-02). This is NOT chained from the last
    // surveillance date, and does NOT get the extra -1-month offset the
    // surveillance points get; an earlier version of this function
    // mistakenly applied that same -1-month offset to Recertification too
    // (matching a DIFFERENT confirmed example that turned out to be
    // coincidental, not the actual rule) — corrected here.
    $recert = (clone $anchor)->modify("+$cycleYears year")->modify('-1 day')->format('Y-m-d');

    return ['surveillances' => $surveillances, 'recertification' => $recert];
}

/**
 * Convenience wrapper for the common case (a certification's own 2
 * surveillance columns), returning the same [surv1, surv2, expiry] shape
 * the older 3-year-only version of this function used, so existing callers
 * don't need to change — surv2 comes back null for anything shorter than a
 * 3-year cycle.
 */
function cm_compute_cycle_milestones_from(string $anchorDate, int $cycleYears = 3): array
{
    $result = cm_compute_cycle_milestones($anchorDate, $cycleYears);
    return [
        $result['surveillances'][0] ?? null,
        $result['surveillances'][1] ?? null,
        $result['recertification'],
    ];
}

/**
 * Fills in any missing surveillance/recertification milestone dates from
 * issue_date, using this client's actual certification cycle convention:
 *   Surveillance 1  = issue_date + 1 year MINUS 1 month
 *   Surveillance 2  = Surveillance 1 + 1 year (i.e. issue_date + 2 years - 1 month)
 *                     — only for a 3-year cycle; omitted for shorter cycles
 *   Recertification = final surveillance + 1 year MINUS 1 day
 * Confirmed directly against a real example row for the 3-year (ISO) case
 * (1st Cert 2024-02-27 -> Surv 1 2025-01-27, Surv 2 2026-01-27, Recert
 * 2027-01-26). $cycleYears comes from the certification's scheme
 * (cm_scheme_types.default_cycle_years) — defaults to 3 (ISO) if not
 * provided, for backward compatibility with any caller that doesn't pass it.
 *
 * Never overwrites a date that's already set — only fills genuine gaps.
 * No-op if issue_date itself isn't provided, since there's no anchor to
 * calculate from. Called from certifications.php on both create and
 * update (and import.php on bulk import), so this is the single place the
 * "auto-populate if missing" rule lives — not duplicated per caller.
 */
function cm_apply_default_cycle_dates(?string $issueDate, ?string $surv1, ?string $surv2, ?string $expiry, int $cycleYears = 3): array
{
    if ($issueDate === null) {
        return [$surv1, $surv2, $expiry];
    }

    // If surv1 is already given (by the caller or a prior fill), cascade
    // surv2 from THAT value rather than recomputing from issue_date —
    // respects a user-provided surv1 even if it doesn't follow the
    // standard -1-month convention. Recertification, though, is always
    // computed from the true anchor (issue_date) below — never chained
    // from surv1/surv2 — since it isn't offset the same way they are.
    if ($surv1 !== null && $surv2 === null && $cycleYears >= 3) {
        $surv2 = (new DateTime($surv1))->modify('+1 year')->format('Y-m-d');
    }

    if ($surv1 === null || $surv2 === null || $expiry === null) {
        [$defSurv1, $defSurv2, $defExpiry] = cm_compute_cycle_milestones_from($issueDate, $cycleYears);
        $surv1 = $surv1 ?? $defSurv1;
        $surv2 = $surv2 ?? $defSurv2;
        $expiry = $expiry ?? $defExpiry;
    }

    return [$surv1, $surv2, $expiry];
}

/**
 * Auto-expire certifications whose Recertification (expiry_date) date has
 * passed but whose status is still 'active' or 'pending' — nothing in this
 * system previously flipped status based on dates, so a cert could sit
 * showing "Active" indefinitely after its actual expiry date had passed.
 * Deliberately does NOT touch 'suspended' or 'withdrawn' — those are
 * explicit manual decisions this shouldn't silently override.
 *
 * Called both inline (at the top of the certifications/renewal-dashboard
 * GET endpoints, so the list is always correct the moment you load it —
 * a single indexed UPDATE, cheap enough to run on every request) and from
 * a daily cron as a backstop in case a page just isn't visited for a
 * while. Returns the number of rows changed, for cron logging.
 */
function cm_auto_expire_overdue_certifications(PDO $db): int
{
    $stmt = $db->prepare(
        "UPDATE cm_certifications
         SET status = 'expired'
         WHERE status IN ('active', 'pending')
           AND expiry_date IS NOT NULL
           AND expiry_date < CURDATE()"
    );
    $stmt->execute();
    return $stmt->rowCount();
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