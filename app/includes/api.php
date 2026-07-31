<?php
/**
 * api.php — small helpers shared by every /api endpoint.
 */

function ehs_json_response($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function ehs_json_error(string $message, int $statusCode = 400): void
{
    ehs_json_response(['error' => $message], $statusCode);
}

/** Parse a JSON request body into an associative array. */
function ehs_json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Validate 'YYYY-MM-DD'. Returns true/false — never trust client dates blindly. */
function ehs_is_valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * An audit is overdue when:
 *  - it's still 'scheduled' (not yet confirmed) and its date is today or has
 *    already passed, or
 *  - it's 'confirmed' but its date has fully passed without being marked
 *    'completed'.
 * 'completed'/'cancelled' audits are never overdue.
 */
function ehs_compute_overdue(string $status, string $auditDate, string $today): array
{
    if ($status === 'scheduled' && $auditDate <= $today) {
        return ['is_overdue' => true, 'overdue_reason' => 'Not yet confirmed'];
    }
    if ($status === 'confirmed' && $auditDate < $today) {
        return ['is_overdue' => true, 'overdue_reason' => 'Not yet marked completed'];
    }
    return ['is_overdue' => false, 'overdue_reason' => null];
}
