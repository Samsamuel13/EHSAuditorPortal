<?php
/**
 * /api/availability.php
 * GET  ?start=YYYY-MM-DD&end=YYYY-MM-DD  -> the logged-in user's own availability rows in range
 * POST { dates: [...], session: 'AM'|'PM'|'FULL_DAY', status: 'available'|'unavailable'|'tentative', note: '' }
 *      -> bulk upsert for the logged-in user only (an auditor can never write another auditor's row here)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

// Any authenticated role may manage their OWN availability (super admins and
// admins sometimes audit too, per the real schedule data), but never someone
// else's — auditor_id always comes from the session, never from client input.
$user = ehs_require_role(['super_admin', 'admin', 'auditor'], true);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $start = $_GET['start'] ?? '';
    $end   = $_GET['end'] ?? '';

    if (!ehs_is_valid_date($start) || !ehs_is_valid_date($end)) {
        ehs_json_error('start and end must be valid YYYY-MM-DD dates.', 422);
    }

    $stmt = get_db()->prepare(
        'SELECT id, date, session, status, note
         FROM availability
         WHERE auditor_id = :auditor_id AND date >= :start AND date < :end
         ORDER BY date, session'
    );
    $stmt->execute([
        'auditor_id' => $user['id'],
        'start'      => $start,
        'end'        => $end,
    ]);

    ehs_json_response(['availability' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ehs_verify_csrf();

    $input   = ehs_json_input();
    $dates   = $input['dates'] ?? [];
    $session = $input['session'] ?? '';
    $status  = $input['status'] ?? '';
    $note    = trim((string) ($input['note'] ?? ''));

    $validSessions = ['AM', 'PM', 'FULL_DAY'];
    $validStatuses = ['available', 'unavailable', 'tentative'];

    if (!is_array($dates) || empty($dates)) {
        ehs_json_error('No dates were selected.', 422);
    }
    if (!in_array($session, $validSessions, true)) {
        ehs_json_error('Invalid session value.', 422);
    }
    if (!in_array($status, $validStatuses, true)) {
        ehs_json_error('Invalid status value.', 422);
    }
    if (strlen($note) > 255) {
        ehs_json_error('Note is too long (255 characters max).', 422);
    }
    foreach ($dates as $d) {
        if (!is_string($d) || !ehs_is_valid_date($d)) {
            ehs_json_error('One or more dates were invalid.', 422);
        }
    }

    $db = get_db();
    $db->beginTransaction();

    try {
        // Keep AM/PM and FULL_DAY mutually exclusive per auditor+date so the
        // calendar never has to guess which one "wins" when rendering:
        //  - marking FULL_DAY clears any existing AM/PM rows for that date
        //  - marking AM or PM clears any existing FULL_DAY row for that date
        $clearOther = $session === 'FULL_DAY'
            ? $db->prepare('DELETE FROM availability WHERE auditor_id = :auditor_id AND date = :date AND session IN ("AM","PM")')
            : $db->prepare('DELETE FROM availability WHERE auditor_id = :auditor_id AND date = :date AND session = "FULL_DAY"');

        $upsert = $db->prepare(
            'INSERT INTO availability (auditor_id, date, session, status, note)
             VALUES (:auditor_id, :date, :session, :status, :note)
             ON DUPLICATE KEY UPDATE status = VALUES(status), note = VALUES(note)'
        );

        foreach ($dates as $date) {
            $clearOther->execute(['auditor_id' => $user['id'], 'date' => $date]);
            $upsert->execute([
                'auditor_id' => $user['id'],
                'date'       => $date,
                'session'    => $session,
                'status'     => $status,
                'note'       => $note !== '' ? $note : null,
            ]);
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('Availability bulk update failed: ' . $e->getMessage());
        ehs_json_error('Could not save availability. Please try again.', 500);
    }

    ehs_log_activity(
        $user['id'],
        'bulk_update_availability',
        'availability',
        null,
        sprintf('%s/%s set for %d date(s)', $session, $status, count($dates))
    );

    ehs_json_response(['success' => true, 'updated' => count($dates)]);
}

ehs_json_error('Method not allowed.', 405);
