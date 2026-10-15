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

/* ============================================================
 * Month-wise micro-stats (2026-09 feature) — everything below this
 * point is ADDITIVE: new constants/functions only, nothing above this
 * line was changed. Added to support renewal_dashboard.php's new
 * "Monthly Stats" tab and api/export_monthly_stats.php.
 * ============================================================ */

/**
 * cm_clients.status's valid values — cm_clients.status has no existing
 * whitelist constant the way CM_ENTITIES/scheme categories do, so this
 * mirrors the enum straight from the schema (cm_clients.status enum:
 * active, suspended, withdrawn, blacklisted) for the new client-status
 * filter on the monthly stats view.
 */
const CM_CLIENT_STATUSES = ['active', 'suspended', 'withdrawn', 'blacklisted'];

/**
 * Like cm_month_range(), but for an ARBITRARY calendar month ('YYYY-MM')
 * rather than an offset from the current month — the monthly-stats month
 * picker needs to reach back to any past month, not just a small relative
 * window. Deliberately a sibling function, not a change to cm_month_range()
 * itself (that function's relative-offset contract is used elsewhere —
 * audit_extract.php — and must keep working exactly as before).
 * Falls back to the current month on an invalid/empty input, so a bad
 * query string can't produce an error, just today's month.
 */
function cm_parse_month_range(string $month): array
{
    $base = false;
    if (preg_match('/^\d{4}-\d{2}$/', $month)) {
        $base = DateTime::createFromFormat('Y-m-d', $month . '-01');
    }
    if (!$base) {
        $base = new DateTime('first day of this month');
    } else {
        $base->setDate((int) $base->format('Y'), (int) $base->format('m'), 1);
    }
    $start = $base->format('Y-m-d');
    $end = (clone $base)->modify('last day of this month')->format('Y-m-d');
    $label = $base->format('F Y');
    return [$start, $end, $label];
}

/**
 * Builds the full month-wise (or custom-range) micro-stats payload for the
 * client-management module. Shared by api/renewal_dashboard.php's
 * ?view=monthly_stats branch and api/export_monthly_stats.php, so the
 * dashboard and its exports can never drift apart on what a number means —
 * both call this one function rather than each running their own copy of
 * these queries.
 *
 * $filters (all optional, all trimmed/whitelisted internally):
 *   month        'YYYY-MM' — ignored if date_from/date_to are both given
 *   date_from    'YYYY-MM-DD' — custom range start
 *   date_to      'YYYY-MM-DD' — custom range end
 *   scheme_category, consultant, entity, client_status
 *
 * Placeholder-naming discipline (per the module's hard rule on the
 * duplicate-placeholder bug that broke Audit Extract): every prepared
 * statement below uses each :name exactly once WITHIN that statement.
 * The same :name (e.g. :scheme_category-style names) reappearing in a
 * DIFFERENT prepare() call further down is fine — each prepare() is an
 * independent statement with its own placeholder scope; the bug is only
 * about one name appearing twice in the SAME query text. Every block below
 * that repeats a value in one query (the two CURDATE comparisons in the
 * due/done/incomplete block) uses two distinct names for it.
 */
function cm_build_monthly_stats(PDO $db, array $filters): array
{
    // --- Resolve the date range ---
    $month    = trim((string) ($filters['month'] ?? ''));
    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    $dateTo   = trim((string) ($filters['date_to'] ?? ''));

    if ($dateFrom !== '' && $dateTo !== '' && cm_is_valid_date($dateFrom) && cm_is_valid_date($dateTo) && $dateFrom <= $dateTo) {
        $start = $dateFrom;
        $end   = $dateTo;
        $label = 'Custom range: ' . $start . ' to ' . $end;
    } elseif ($month !== '') {
        [$start, $end, $label] = cm_parse_month_range($month);
    } else {
        [$start, $end, $label] = cm_month_range(0);
    }
    $startDt = $start . ' 00:00:00';
    $endDt   = $end . ' 23:59:59';
    $today   = date('Y-m-d');

    // --- Resolve + whitelist filters (never trust client input directly in SQL) ---
    $schemeCategory = trim((string) ($filters['scheme_category'] ?? ''));
    if (!in_array($schemeCategory, ['ISO', 'BizSafe', 'JASANZ', 'Other'], true)) $schemeCategory = '';
    $consultant = trim((string) ($filters['consultant'] ?? ''));
    $entity = trim((string) ($filters['entity'] ?? ''));
    if (!in_array($entity, CM_ENTITIES, true)) $entity = '';
    $clientStatus = trim((string) ($filters['client_status'] ?? ''));
    if (!in_array($clientStatus, CM_CLIENT_STATUSES, true)) $clientStatus = '';

    // ---------- 1) New clients onboarded ----------
    // REDEFINED 2026-09 (confirmed): "onboarded" now means a client's FIRST
    // certification issue_date falls in the selected range — MIN(cert.issue_date)
    // grouped by client — replacing cm_clients.created_at (the client row's
    // own creation timestamp). Confirmed consequences of this change:
    //   - MIN() is taken across ALL of a client's certifications, not a
    //     "primary" one — cm_clients has no primary-scheme concept in the
    //     schema (no primary_scheme_type_id or similar column), so there is
    //     nothing else to key off of.
    //   - A client with a cm_clients row but zero certifications (e.g. just
    //     converted from a CRM lead, or created manually ahead of
    //     certification) has no MIN(issue_date) at all, so the JOIN below
    //     produces no row for them and they correctly never appear in any
    //     month's "New Clients Onboarded" until their first certification
    //     is actually issued. This is the intended behavior of the new
    //     definition, not an oversight.
    //   - scheme_category is deliberately NOT applied as a filter here (unlike
    //     the other client-level filters). Applying it would silently change
    //     what "new client" means to "a client whose FIRST certification was
    //     in scheme category X" — a materially different, narrower question
    //     than what was asked for, so it's left off rather than guessed at.
    // Confirmed unaffected by this change: cm_clients.created_at is used
    // NOWHERE else in this file, and nowhere else in the module's PHP,
    // except in this block and its row-level counterpart below in
    // cm_build_monthly_stats_detail() (grepped the whole client-management/
    // tree to confirm — client_detail.php's created_at reference is an
    // unrelated table, cm_activity_log).
    $where = ['cert.issue_date IS NOT NULL'];
    $params = [];
    if ($consultant !== '')   { $where[] = 'c.consultant = :nc_consultant'; $params['nc_consultant'] = $consultant; }
    if ($entity !== '')       { $where[] = 'c.entity = :nc_entity';         $params['nc_entity'] = $entity; }
    if ($clientStatus !== '') { $where[] = 'c.status = :nc_status';         $params['nc_status'] = $clientStatus; }
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM (
             SELECT c.id, MIN(cert.issue_date) AS first_cert_date
             FROM cm_clients c
             JOIN cm_certifications cert ON cert.cm_client_id = c.id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY c.id
             HAVING first_cert_date BETWEEN :nc_start AND :nc_end
         ) first_certs'
    );
    $params['nc_start'] = $start;
    $params['nc_end'] = $end;
    $stmt->execute($params);
    $newClients = (int) $stmt->fetchColumn();

    // ---------- 2) Certifications issued this range ----------
    // Also feeds by-scheme-type / by-status / by-consultant and
    // avg-time-to-renewal below — one fetch, aggregated in PHP, same
    // "fetch matching rows then loop" style already used in
    // api/renewal_dashboard.php's own bucketing logic.
    $where = ['cert.issue_date BETWEEN :ci_start AND :ci_end'];
    $params = ['ci_start' => $start, 'ci_end' => $end];
    if ($schemeCategory !== '') { $where[] = 'st.category = :ci_scheme_category'; $params['ci_scheme_category'] = $schemeCategory; }
    if ($consultant !== '')     { $where[] = 'c.consultant = :ci_consultant';      $params['ci_consultant'] = $consultant; }
    if ($entity !== '')         { $where[] = 'c.entity = :ci_entity';              $params['ci_entity'] = $entity; }
    if ($clientStatus !== '')   { $where[] = 'c.status = :ci_status';              $params['ci_status'] = $clientStatus; }
    $stmt = $db->prepare(
        'SELECT cert.id, cert.issue_date, cert.expiry_date, cert.status AS cert_status,
                st.name AS scheme_name, st.category AS scheme_category, c.consultant
         FROM cm_certifications cert
         JOIN cm_clients c ON c.id = cert.cm_client_id
         JOIN cm_scheme_types st ON st.id = cert.cm_scheme_type_id
         WHERE ' . implode(' AND ', $where)
    );
    $stmt->execute($params);
    $issuedCerts = $stmt->fetchAll();

    $certificationsIssued = count($issuedCerts);
    $bySchemeType = [];
    $byStatus = [];
    $byConsultant = [];
    $renewalSpans = [];
    foreach ($issuedCerts as $row) {
        $schemeKey = $row['scheme_name'] . ' (' . $row['scheme_category'] . ')';
        $bySchemeType[$schemeKey] = ($bySchemeType[$schemeKey] ?? 0) + 1;
        $byStatus[$row['cert_status']] = ($byStatus[$row['cert_status']] ?? 0) + 1;
        $consultantKey = ($row['consultant'] !== null && $row['consultant'] !== '') ? $row['consultant'] : '(No consultant on file)';
        $byConsultant[$consultantKey] = ($byConsultant[$consultantKey] ?? 0) + 1;
        if ($row['issue_date'] && $row['expiry_date']) {
            $renewalSpans[] = (strtotime($row['expiry_date']) - strtotime($row['issue_date'])) / 86400;
        }
    }
    $avgTimeToRenewalDays = $renewalSpans ? round(array_sum($renewalSpans) / count($renewalSpans), 1) : null;

    // ---------- 3) Certifications renewed this range ----------
    // "Renewed" = the Recertification milestone was actually completed
    // (recertification_completed_at set) within the range — the same
    // completion event the existing dashboard's "Recertification done"
    // stat already counts, just windowed to an arbitrary range here
    // instead of hardcoded to the current calendar month.
    $where = ['cert.recertification_completed_at BETWEEN :cr_start AND :cr_end'];
    $params = ['cr_start' => $startDt, 'cr_end' => $endDt];
    if ($schemeCategory !== '') { $where[] = 'st.category = :cr_scheme_category'; $params['cr_scheme_category'] = $schemeCategory; }
    if ($consultant !== '')     { $where[] = 'c.consultant = :cr_consultant';      $params['cr_consultant'] = $consultant; }
    if ($entity !== '')         { $where[] = 'c.entity = :cr_entity';              $params['cr_entity'] = $entity; }
    if ($clientStatus !== '')   { $where[] = 'c.status = :cr_status';              $params['cr_status'] = $clientStatus; }
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM cm_certifications cert
         JOIN cm_clients c ON c.id = cert.cm_client_id
         JOIN cm_scheme_types st ON st.id = cert.cm_scheme_type_id
         WHERE ' . implode(' AND ', $where)
    );
    $stmt->execute($params);
    $certificationsRenewed = (int) $stmt->fetchColumn();

    // ---------- 4) Certifications expired this range ----------
    // Grounded on expiry_date falling in-range AND status = 'expired' (the
    // flag cm_auto_expire_overdue_certifications() sets) — deliberately
    // NOT cert.updated_at, which is bumped by ANY edit to the row (a note,
    // a status change, a document upload), not only the auto-expire flip,
    // and would over-count. This is a refinement over the Step-1 proposal
    // made after tracing cm_auto_expire_overdue_certifications() directly.
    $where = ["cert.status = 'expired'", 'cert.expiry_date BETWEEN :ce_start AND :ce_end'];
    $params = ['ce_start' => $start, 'ce_end' => $end];
    if ($schemeCategory !== '') { $where[] = 'st.category = :ce_scheme_category'; $params['ce_scheme_category'] = $schemeCategory; }
    if ($consultant !== '')     { $where[] = 'c.consultant = :ce_consultant';      $params['ce_consultant'] = $consultant; }
    if ($entity !== '')         { $where[] = 'c.entity = :ce_entity';              $params['ce_entity'] = $entity; }
    if ($clientStatus !== '')   { $where[] = 'c.status = :ce_status';              $params['ce_status'] = $clientStatus; }
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM cm_certifications cert
         JOIN cm_clients c ON c.id = cert.cm_client_id
         JOIN cm_scheme_types st ON st.id = cert.cm_scheme_type_id
         WHERE ' . implode(' AND ', $where)
    );
    $stmt->execute($params);
    $certificationsExpired = (int) $stmt->fetchColumn();

    // ---------- 5) Follow-up activity volume, by type ----------
    // Mirrors api/renewal_dashboard.php's existing $activityWhere block
    // (LEFT JOINs to cert/scheme so a client-level-only note, with no
    // certification attached, is still counted unless a cert-scoped filter
    // like scheme_category excludes it).
    $where = ['n.created_at BETWEEN :fa_start AND :fa_end'];
    $params = ['fa_start' => $startDt, 'fa_end' => $endDt];
    if ($schemeCategory !== '') { $where[] = 'st2.category = :fa_scheme_category'; $params['fa_scheme_category'] = $schemeCategory; }
    if ($consultant !== '')     { $where[] = 'c2.consultant = :fa_consultant';      $params['fa_consultant'] = $consultant; }
    if ($entity !== '')         { $where[] = 'c2.entity = :fa_entity';              $params['fa_entity'] = $entity; }
    if ($clientStatus !== '')   { $where[] = 'c2.status = :fa_status';              $params['fa_status'] = $clientStatus; }
    $stmt = $db->prepare(
        'SELECT n.activity_type, COUNT(*) AS cnt
         FROM cm_client_followup_notes n
         JOIN cm_clients c2 ON c2.id = n.cm_client_id
         LEFT JOIN cm_certifications cert2 ON cert2.id = n.cm_certification_id
         LEFT JOIN cm_scheme_types st2 ON st2.id = cert2.cm_scheme_type_id
         WHERE ' . implode(' AND ', $where) . '
         GROUP BY n.activity_type'
    );
    $stmt->execute($params);
    $followupActivityByType = [];
    $followupActivityTotal = 0;
    foreach ($stmt->fetchAll() as $row) {
        $key = $row['activity_type'] !== null && $row['activity_type'] !== '' ? $row['activity_type'] : 'other';
        $followupActivityByType[$key] = (int) $row['cnt'];
        $followupActivityTotal += (int) $row['cnt'];
    }

    // ---------- 6) Renewal follow-ups ACTIONED, by stage / resolution ----------
    // cm_renewal_alerts is NOT used here — it's dead (its only writer,
    // cron/send_renewal_reminders.php, disabled itself with an early exit
    // when the 4-stage follow-up system replaced it). cm_renewal_followup_actions
    // is the live table. Its rows are only INSERTed at the moment of
    // resolution (api/renewal_followups.php), so followed_up_at IS the
    // "actioned" event — there's no separately-stored "triggered" timestamp
    // to report a historical triggered-per-month count from; "triggered"
    // stays a live-only number on the existing follow-up stage widgets.
    $where = ['a.followed_up_at BETWEEN :ra_start AND :ra_end'];
    $params = ['ra_start' => $startDt, 'ra_end' => $endDt];
    if ($schemeCategory !== '') { $where[] = 'st3.category = :ra_scheme_category'; $params['ra_scheme_category'] = $schemeCategory; }
    if ($consultant !== '')     { $where[] = 'c3.consultant = :ra_consultant';      $params['ra_consultant'] = $consultant; }
    if ($entity !== '')         { $where[] = 'c3.entity = :ra_entity';              $params['ra_entity'] = $entity; }
    if ($clientStatus !== '')   { $where[] = 'c3.status = :ra_status';              $params['ra_status'] = $clientStatus; }
    $stmt = $db->prepare(
        'SELECT a.stage, a.resolution_type, a.milestone_date, a.followed_up_at
         FROM cm_renewal_followup_actions a
         JOIN cm_certifications cert3 ON cert3.id = a.cm_certification_id
         JOIN cm_clients c3 ON c3.id = cert3.cm_client_id
         JOIN cm_scheme_types st3 ON st3.id = cert3.cm_scheme_type_id
         WHERE ' . implode(' AND ', $where)
    );
    $stmt->execute($params);
    $actionedRows = $stmt->fetchAll();
    $followupsActionedByStage = [];
    $followupsActionedByResolution = [];
    $resolutionSpans = [];
    foreach ($actionedRows as $row) {
        $stageKey = 'Stage ' . $row['stage'];
        $followupsActionedByStage[$stageKey] = ($followupsActionedByStage[$stageKey] ?? 0) + 1;
        $resKey = $row['resolution_type'] !== null && $row['resolution_type'] !== '' ? $row['resolution_type'] : 'unspecified';
        $followupsActionedByResolution[$resKey] = ($followupsActionedByResolution[$resKey] ?? 0) + 1;
        if ($row['milestone_date'] && $row['followed_up_at']) {
            $resolutionSpans[] = (strtotime($row['followed_up_at']) - strtotime($row['milestone_date'])) / 86400;
        }
    }
    $followupsActionedTotal = count($actionedRows);
    $avgDaysToResolution = $resolutionSpans ? round(array_sum($resolutionSpans) / count($resolutionSpans), 1) : null;

    // ---------- 7) Due / Done / Incomplete, per cycle milestone ----------
    // Reuses CM_CYCLE_MILESTONES — the SAME 3-milestone map
    // cm_certification_next_due() and the Log Activity "mark complete"
    // feature already use — rather than a second, separately-maintained
    // milestone list. The 'recertification' entry already IS the combined
    // Recertification/Expiry bucket (date_col => expiry_date, completed_col
    // => recertification_completed_at) per the confirmed design: a cert
    // that expired without being recertified in time shows up here as
    // incomplete + overdue, never as a second separate row.
    //
    // "Overdue" vs "Pending" only differs meaningfully when the selected
    // range includes or precedes today: a fully-future range (a month not
    // yet reached) can only ever produce incomplete_pending, never
    // incomplete_overdue, since nothing in it can be < today yet; a fully-past
    // range can only ever produce incomplete_overdue. Both columns are
    // still always returned so the UI doesn't need range-aware conditional
    // logic — an all-zero column for a future month is the correct,
    // expected answer, not a bug.
    $dueBreakdown = [];
    foreach (CM_CYCLE_MILESTONES as $key => $m) {
        $dateCol = $m['date_col'];
        $completedCol = $m['completed_col'];
        $where = [
            "cert.$dateCol BETWEEN :db_start AND :db_end",
            "cert.status != 'withdrawn'",
        ];
        $params = ['db_start' => $start, 'db_end' => $end];
        if ($schemeCategory !== '') { $where[] = 'st4.category = :db_scheme_category'; $params['db_scheme_category'] = $schemeCategory; }
        if ($consultant !== '')     { $where[] = 'c4.consultant = :db_consultant';      $params['db_consultant'] = $consultant; }
        if ($entity !== '')         { $where[] = 'c4.entity = :db_entity';              $params['db_entity'] = $entity; }
        if ($clientStatus !== '')   { $where[] = 'c4.status = :db_status';              $params['db_status'] = $clientStatus; }
        $sql = "SELECT
                    SUM(CASE WHEN cert.$completedCol IS NOT NULL THEN 1 ELSE 0 END) AS done,
                    SUM(CASE WHEN cert.$completedCol IS NULL AND cert.$dateCol <  :db_today  THEN 1 ELSE 0 END) AS incomplete_overdue,
                    SUM(CASE WHEN cert.$completedCol IS NULL AND cert.$dateCol >= :db_today2 THEN 1 ELSE 0 END) AS incomplete_pending,
                    COUNT(*) AS due_total
                FROM cm_certifications cert
                JOIN cm_clients c4 ON c4.id = cert.cm_client_id
                JOIN cm_scheme_types st4 ON st4.id = cert.cm_scheme_type_id
                WHERE " . implode(' AND ', $where);
        $params['db_today'] = $today;
        $params['db_today2'] = $today;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        $dueBreakdown[$key] = [
            'label' => $key === 'recertification' ? 'Recertification / Expiry' : $m['label'],
            'due_total' => (int) ($row['due_total'] ?? 0),
            'done' => (int) ($row['done'] ?? 0),
            'incomplete_overdue' => (int) ($row['incomplete_overdue'] ?? 0),
            'incomplete_pending' => (int) ($row['incomplete_pending'] ?? 0),
        ];
    }

    // ---------- Filter option lists ----------
    // cm_clients.consultant is free text with no enum/lookup table backing
    // it, unlike scheme_category/entity/client_status which are already
    // fixed whitelists — so its filter dropdown is populated from distinct
    // values actually on file, refreshed on every call.
    $consultantOptions = array_column(
        $db->query("SELECT DISTINCT consultant FROM cm_clients WHERE consultant IS NOT NULL AND consultant <> '' ORDER BY consultant")->fetchAll(),
        'consultant'
    );

    return [
        'range' => ['start' => $start, 'end' => $end, 'label' => $label],
        'filters_applied' => [
            'scheme_category' => $schemeCategory,
            'consultant' => $consultant,
            'entity' => $entity,
            'client_status' => $clientStatus,
        ],
        'new_clients' => $newClients,
        'certifications_issued' => $certificationsIssued,
        'certifications_renewed' => $certificationsRenewed,
        'certifications_expired' => $certificationsExpired,
        'by_scheme_type' => $bySchemeType,
        'by_status' => $byStatus,
        'by_consultant' => $byConsultant,
        'avg_time_to_renewal_days' => $avgTimeToRenewalDays,
        'followup_activity' => [
            'total' => $followupActivityTotal,
            'by_type' => $followupActivityByType,
        ],
        'followups_actioned' => [
            'total' => $followupsActionedTotal,
            'by_stage' => $followupsActionedByStage,
            'by_resolution' => $followupsActionedByResolution,
            'avg_days_to_resolution' => $avgDaysToResolution,
        ],
        'due_breakdown' => $dueBreakdown,
        'filter_options' => [
            'consultants' => $consultantOptions,
        ],
    ];
}

/* ============================================================
 * Monthly-stats drill-down (2026-09 feature, added after the tab above
 * was already built and delivered). ADDITIVE ONLY — cm_build_monthly_stats()
 * above this comment is completely untouched, on purpose: the summary
 * tiles/tables it feeds were already confirmed correct, and reworking that
 * function to also carry row-level detail would have meant fetching full
 * row sets on every single page load just in case a user clicks to expand
 * something. Instead, this is a separate function called lazily, only when
 * a stat is actually expanded — so a normal page load's cost is completely
 * unchanged, and the summary numbers can never be affected by this code,
 * since it doesn't touch the queries that produce them.
 * ============================================================ */

/** The only metric values cm_build_monthly_stats_detail() will accept — used to build the query, so this must stay a closed whitelist, never open to arbitrary client input. */
const CM_MONTHLY_STATS_DETAIL_METRICS = [
    'new_clients', 'certifications_issued', 'certifications_renewed',
    'certifications_expired', 'followup_activity', 'followups_actioned', 'due_breakdown',
];

/**
 * Row-level detail behind one monthly-stats metric (or one bucket within
 * it — e.g. just the "ISO" slice of certifications_issued, or just the
 * "Stage 2" slice of followups_actioned), paginated.
 *
 * $args:
 *   metric        one of CM_MONTHLY_STATS_DETAIL_METRICS (required)
 *   group_by      optional sub-filter dimension: 'scheme' | 'status' | 'consultant'
 *                 for certifications_issued; 'stage' | 'resolution' for
 *                 followups_actioned; 'type' for followup_activity
 *   key           the specific bucket value to filter to, paired with group_by
 *                 (e.g. group_by=status, key=active) — omitted = the full
 *                 unfiltered metric list
 *   milestone     required only for metric=due_breakdown: 'surveillance_1' |
 *                 'surveillance_2' | 'recertification' (matches CM_CYCLE_MILESTONES keys)
 *   status_bucket optional for metric=due_breakdown: 'done' | 'overdue' | 'pending'
 *   month / date_from / date_to / scheme_category / consultant / entity / client_status
 *                 — identical meaning and whitelisting to cm_build_monthly_stats()
 *   page, per_page
 *
 * Throws InvalidArgumentException for an unrecognized metric/milestone —
 * callers (the API endpoint) catch this and return a 422, the same way
 * other endpoints in this module reject bad input.
 *
 * Placeholder discipline: every branch below builds and executes its own
 * single prepared statement (or a COUNT + a LIST statement, each separate),
 * so no :name is ever reused within one statement — same rule as
 * cm_build_monthly_stats() and every other query in this module.
 */
function cm_build_monthly_stats_detail(PDO $db, array $args): array
{
    $metric = trim((string) ($args['metric'] ?? ''));
    if (!in_array($metric, CM_MONTHLY_STATS_DETAIL_METRICS, true)) {
        throw new InvalidArgumentException('Unknown metric.');
    }
    $groupBy = trim((string) ($args['group_by'] ?? ''));
    $key = trim((string) ($args['key'] ?? ''));
    $milestone = trim((string) ($args['milestone'] ?? ''));
    $statusBucket = trim((string) ($args['status_bucket'] ?? ''));
    $page = max(1, (int) ($args['page'] ?? 1));
    // _max_per_page is an internal-only escape hatch (never read from
    // request input by the API layer) so cm_build_monthly_stats_detail_all()
    // can request a much larger single page for export, while the normal
    // drill-down-panel API call stays capped at 100 rows/click.
    $maxPerPage = (int) ($args['_max_per_page'] ?? 100);
    $perPage = min($maxPerPage, max(10, (int) ($args['per_page'] ?? 25)));
    $offset = ($page - 1) * $perPage;

    // --- Resolve the date range — same logic as cm_build_monthly_stats(),
    // deliberately re-run here rather than shared, so this function has no
    // dependency on that one and editing either can never silently change
    // the other's behavior. ---
    $month    = trim((string) ($args['month'] ?? ''));
    $dateFrom = trim((string) ($args['date_from'] ?? ''));
    $dateTo   = trim((string) ($args['date_to'] ?? ''));
    if ($dateFrom !== '' && $dateTo !== '' && cm_is_valid_date($dateFrom) && cm_is_valid_date($dateTo) && $dateFrom <= $dateTo) {
        $start = $dateFrom;
        $end   = $dateTo;
    } elseif ($month !== '') {
        [$start, $end] = cm_parse_month_range($month);
    } else {
        [$start, $end] = cm_month_range(0);
    }
    $startDt = $start . ' 00:00:00';
    $endDt   = $end . ' 23:59:59';
    $today   = date('Y-m-d');

    $schemeCategory = trim((string) ($args['scheme_category'] ?? ''));
    if (!in_array($schemeCategory, ['ISO', 'BizSafe', 'JASANZ', 'Other'], true)) $schemeCategory = '';
    $consultant = trim((string) ($args['consultant'] ?? ''));
    $entity = trim((string) ($args['entity'] ?? ''));
    if (!in_array($entity, CM_ENTITIES, true)) $entity = '';
    $clientStatus = trim((string) ($args['client_status'] ?? ''));
    if (!in_array($clientStatus, CM_CLIENT_STATUSES, true)) $clientStatus = '';

    $where = [];
    $params = [];
    $selectSql = '';
    $fromSql = '';
    $orderSql = '';
    $groupBySql = '';
    $havingSql = '';

    switch ($metric) {
        case 'new_clients':
            // REDEFINED 2026-09 to match cm_build_monthly_stats() — "onboarded"
            // is a client's first certification date (MIN(cert.issue_date)),
            // not cm_clients.created_at. Same GROUP BY/HAVING shape as the
            // summary count, just row-level (id + display columns) instead
            // of COUNT(*). See the summary function's block for the full
            // rationale (primary-scheme question, zero-cert clients,
            // scheme_category deliberately not applied here either).
            $where[] = 'cert.issue_date IS NOT NULL';
            if ($consultant !== '')   { $where[] = 'c.consultant = :d_consultant'; $params['d_consultant'] = $consultant; }
            if ($entity !== '')       { $where[] = 'c.entity = :d_entity';         $params['d_entity'] = $entity; }
            if ($clientStatus !== '') { $where[] = 'c.status = :d_status';         $params['d_status'] = $clientStatus; }
            $groupBySql = 'GROUP BY c.id, c.company_name, c.entity, c.consultant, c.status, c.industry_sector';
            $havingSql = 'HAVING MIN(cert.issue_date) BETWEEN :d_start AND :d_end';
            $params['d_start'] = $start;
            $params['d_end'] = $end;
            $selectSql = 'c.id AS client_id, c.company_name, c.entity, c.consultant, c.status, c.industry_sector, MIN(cert.issue_date) AS first_cert_date';
            $fromSql = 'cm_clients c JOIN cm_certifications cert ON cert.cm_client_id = c.id';
            $orderSql = 'MIN(cert.issue_date) DESC, c.company_name ASC';
            break;

        case 'certifications_issued':
            $where[] = 'cert.issue_date BETWEEN :d_start AND :d_end';
            $params = ['d_start' => $start, 'd_end' => $end];
            if ($schemeCategory !== '') { $where[] = 'st.category = :d_scheme_category'; $params['d_scheme_category'] = $schemeCategory; }
            if ($consultant !== '')     { $where[] = 'c.consultant = :d_consultant';      $params['d_consultant'] = $consultant; }
            if ($entity !== '')         { $where[] = 'c.entity = :d_entity';              $params['d_entity'] = $entity; }
            if ($clientStatus !== '')   { $where[] = 'c.status = :d_status';              $params['d_status'] = $clientStatus; }
            if ($groupBy === 'scheme' && $key !== '') {
                // by_scheme_type's key is rendered as "Name (Category)" —
                // matched the same way here so a click on that exact label
                // filters to exactly those rows.
                $where[] = "CONCAT(st.name, ' (', st.category, ')') = :d_group_key";
                $params['d_group_key'] = $key;
            } elseif ($groupBy === 'status' && $key !== '') {
                $where[] = 'cert.status = :d_group_key';
                $params['d_group_key'] = $key;
            } elseif ($groupBy === 'consultant' && $key !== '') {
                if ($key === '(No consultant on file)') {
                    $where[] = "(c.consultant IS NULL OR c.consultant = '')";
                } else {
                    $where[] = 'c.consultant = :d_group_key';
                    $params['d_group_key'] = $key;
                }
            }
            $selectSql = 'cert.id AS cert_id, c.id AS client_id, c.company_name, st.name AS scheme_name, st.category AS scheme_category, c.consultant, cert.status AS cert_status, cert.certificate_number, cert.issue_date, cert.expiry_date';
            $fromSql = 'cm_certifications cert JOIN cm_clients c ON c.id = cert.cm_client_id JOIN cm_scheme_types st ON st.id = cert.cm_scheme_type_id';
            $orderSql = 'cert.issue_date DESC, c.company_name ASC';
            break;

        case 'certifications_renewed':
            $where[] = 'cert.recertification_completed_at BETWEEN :d_start AND :d_end';
            $params = ['d_start' => $startDt, 'd_end' => $endDt];
            if ($schemeCategory !== '') { $where[] = 'st.category = :d_scheme_category'; $params['d_scheme_category'] = $schemeCategory; }
            if ($consultant !== '')     { $where[] = 'c.consultant = :d_consultant';      $params['d_consultant'] = $consultant; }
            if ($entity !== '')         { $where[] = 'c.entity = :d_entity';              $params['d_entity'] = $entity; }
            if ($clientStatus !== '')   { $where[] = 'c.status = :d_status';              $params['d_status'] = $clientStatus; }
            $selectSql = 'cert.id AS cert_id, c.id AS client_id, c.company_name, st.name AS scheme_name, st.category AS scheme_category, cert.status AS cert_status, cert.certificate_number, cert.recertification_completed_at';
            $fromSql = 'cm_certifications cert JOIN cm_clients c ON c.id = cert.cm_client_id JOIN cm_scheme_types st ON st.id = cert.cm_scheme_type_id';
            $orderSql = 'cert.recertification_completed_at DESC, c.company_name ASC';
            break;

        case 'certifications_expired':
            $where[] = "cert.status = 'expired'";
            $where[] = 'cert.expiry_date BETWEEN :d_start AND :d_end';
            $params = ['d_start' => $start, 'd_end' => $end];
            if ($schemeCategory !== '') { $where[] = 'st.category = :d_scheme_category'; $params['d_scheme_category'] = $schemeCategory; }
            if ($consultant !== '')     { $where[] = 'c.consultant = :d_consultant';      $params['d_consultant'] = $consultant; }
            if ($entity !== '')         { $where[] = 'c.entity = :d_entity';              $params['d_entity'] = $entity; }
            if ($clientStatus !== '')   { $where[] = 'c.status = :d_status';              $params['d_status'] = $clientStatus; }
            $selectSql = 'cert.id AS cert_id, c.id AS client_id, c.company_name, st.name AS scheme_name, st.category AS scheme_category, cert.certificate_number, cert.expiry_date';
            $fromSql = 'cm_certifications cert JOIN cm_clients c ON c.id = cert.cm_client_id JOIN cm_scheme_types st ON st.id = cert.cm_scheme_type_id';
            $orderSql = 'cert.expiry_date DESC, c.company_name ASC';
            break;

        case 'followup_activity':
            $where[] = 'n.created_at BETWEEN :d_start AND :d_end';
            $params = ['d_start' => $startDt, 'd_end' => $endDt];
            if ($schemeCategory !== '') { $where[] = 'st.category = :d_scheme_category'; $params['d_scheme_category'] = $schemeCategory; }
            if ($consultant !== '')     { $where[] = 'c.consultant = :d_consultant';      $params['d_consultant'] = $consultant; }
            if ($entity !== '')         { $where[] = 'c.entity = :d_entity';              $params['d_entity'] = $entity; }
            if ($clientStatus !== '')   { $where[] = 'c.status = :d_status';              $params['d_status'] = $clientStatus; }
            if ($groupBy === 'type' && $key !== '') {
                // Matches the summary's fallback bucketing exactly (the
                // by_type loop in cm_build_monthly_stats() folds a NULL or
                // empty activity_type into the SAME 'other' key as rows
                // literally tagged 'other' — a real, selectable value in
                // the Log Activity modal). Plain equality alone would only
                // match the literal string and silently drop any row whose
                // activity_type is NULL/empty, undercounting or zeroing out
                // the 'other' bucket in the drill-down vs. the tile above it.
                if ($key === 'other') {
                    $where[] = "(n.activity_type = 'other' OR n.activity_type IS NULL OR n.activity_type = '')";
                } else {
                    $where[] = 'n.activity_type = :d_group_key';
                    $params['d_group_key'] = $key;
                }
            }
            $selectSql = 'n.id AS note_id, c.id AS client_id, c.company_name, n.activity_type, n.note, n.outcome, n.status_changed_to, n.milestone_completed, n.created_by_name, n.created_at';
            $fromSql = 'cm_client_followup_notes n JOIN cm_clients c ON c.id = n.cm_client_id LEFT JOIN cm_certifications cert ON cert.id = n.cm_certification_id LEFT JOIN cm_scheme_types st ON st.id = cert.cm_scheme_type_id';
            $orderSql = 'n.created_at DESC';
            break;

        case 'followups_actioned':
            $where[] = 'a.followed_up_at BETWEEN :d_start AND :d_end';
            $params = ['d_start' => $startDt, 'd_end' => $endDt];
            if ($schemeCategory !== '') { $where[] = 'st.category = :d_scheme_category'; $params['d_scheme_category'] = $schemeCategory; }
            if ($consultant !== '')     { $where[] = 'c.consultant = :d_consultant';      $params['d_consultant'] = $consultant; }
            if ($entity !== '')         { $where[] = 'c.entity = :d_entity';              $params['d_entity'] = $entity; }
            if ($clientStatus !== '')   { $where[] = 'c.status = :d_status';              $params['d_status'] = $clientStatus; }
            if ($groupBy === 'stage' && $key !== '') {
                $where[] = 'a.stage = :d_group_key';
                $params['d_group_key'] = (int) preg_replace('/\D/', '', $key); // key arrives as "Stage 2" — extract the digit
            } elseif ($groupBy === 'resolution' && $key !== '') {
                if ($key === 'unspecified') {
                    $where[] = 'a.resolution_type IS NULL';
                } else {
                    $where[] = 'a.resolution_type = :d_group_key';
                    $params['d_group_key'] = $key;
                }
            }
            $selectSql = 'a.id AS action_id, c.id AS client_id, c.company_name, st.name AS scheme_name, a.milestone_key, a.stage, a.resolution_type, a.milestone_date, a.followed_up_at, a.followed_up_by_name, a.note';
            $fromSql = 'cm_renewal_followup_actions a JOIN cm_certifications cert ON cert.id = a.cm_certification_id JOIN cm_clients c ON c.id = cert.cm_client_id JOIN cm_scheme_types st ON st.id = cert.cm_scheme_type_id';
            $orderSql = 'a.followed_up_at DESC';
            break;

        case 'due_breakdown':
            if (!array_key_exists($milestone, CM_CYCLE_MILESTONES)) {
                throw new InvalidArgumentException('A valid milestone (surveillance_1, surveillance_2, or recertification) is required for the due_breakdown metric.');
            }
            $dateCol = CM_CYCLE_MILESTONES[$milestone]['date_col'];
            $completedCol = CM_CYCLE_MILESTONES[$milestone]['completed_col'];
            $where[] = "cert.$dateCol BETWEEN :d_start AND :d_end";
            $where[] = "cert.status != 'withdrawn'";
            $params = ['d_start' => $start, 'd_end' => $end];
            if ($schemeCategory !== '') { $where[] = 'st.category = :d_scheme_category'; $params['d_scheme_category'] = $schemeCategory; }
            if ($consultant !== '')     { $where[] = 'c.consultant = :d_consultant';      $params['d_consultant'] = $consultant; }
            if ($entity !== '')         { $where[] = 'c.entity = :d_entity';              $params['d_entity'] = $entity; }
            if ($clientStatus !== '')   { $where[] = 'c.status = :d_status';              $params['d_status'] = $clientStatus; }
            if ($statusBucket === 'done') {
                $where[] = "cert.$completedCol IS NOT NULL";
            } elseif ($statusBucket === 'overdue') {
                $where[] = "cert.$completedCol IS NULL AND cert.$dateCol < :d_today";
                $params['d_today'] = $today;
            } elseif ($statusBucket === 'pending') {
                $where[] = "cert.$completedCol IS NULL AND cert.$dateCol >= :d_today2";
                $params['d_today2'] = $today;
            }
            $selectSql = "cert.id AS cert_id, c.id AS client_id, c.company_name, c.consultant, st.name AS scheme_name, cert.status AS cert_status, cert.$dateCol AS milestone_date, cert.$completedCol AS completed_at";
            $fromSql = 'cm_certifications cert JOIN cm_clients c ON c.id = cert.cm_client_id JOIN cm_scheme_types st ON st.id = cert.cm_scheme_type_id';
            $orderSql = "cert.$dateCol ASC, c.company_name ASC";
            break;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    // $groupBySql/$havingSql are only non-empty for 'new_clients' (the
    // MIN(issue_date)-per-client redefinition needs GROUP BY/HAVING; every
    // other metric is a plain row-level SELECT with no aggregation, so this
    // stays '' for them and the two branches below collapse to the same
    // simple COUNT/LIST queries as before this metric was added).
    if ($groupBySql !== '') {
        $countStmt = $db->prepare("SELECT COUNT(*) FROM (SELECT 1 FROM $fromSql $whereSql $groupBySql $havingSql) sub");
    } else {
        $countStmt = $db->prepare("SELECT COUNT(*) FROM $fromSql $whereSql");
    }
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $listStmt = $db->prepare("SELECT $selectSql FROM $fromSql $whereSql $groupBySql $havingSql ORDER BY $orderSql LIMIT :limit OFFSET :offset");
    foreach ($params as $k => $v) {
        $listStmt->bindValue(':' . $k, $v);
    }
    $listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $listStmt->execute();
    $rows = $listStmt->fetchAll();

    return [
        'metric' => $metric,
        'rows' => $rows,
        'total_count' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => max(1, (int) ceil($total / $perPage)),
    ];
}

/**
 * Unpaginated variant for export: same query-building logic (literally
 * calls the paginated function with a large per_page, capped) so exported
 * detail rows can never disagree with what the drill-down panel shows for
 * the same metric/key. Capped at 5000 rows per sheet — a report this size
 * belongs in a scoped date-range export, not a single spreadsheet tab; the
 * returned array tells the caller whether it was capped so the export file
 * can say so on the sheet itself rather than silently truncating.
 */
function cm_build_monthly_stats_detail_all(PDO $db, array $args): array
{
    $capped = array_merge($args, ['page' => 1, 'per_page' => 5000, '_max_per_page' => 5000]);
    $result = cm_build_monthly_stats_detail($db, $capped);
    $result['truncated'] = $result['total_count'] > count($result['rows']);
    return $result;
}