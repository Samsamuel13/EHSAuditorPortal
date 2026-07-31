<?php
// File: api/admin_availability.php
/**
 * /api/admin_availability.php
 * GET  ?auditor_id=N&start=&end= -> that auditor's availability rows in range
 * POST { auditor_id, dates[], session, status, note } -> bulk upsert ON
 *      BEHALF OF that auditor
 *
 * Super admin ONLY — this deliberately bypasses the normal "you can only
 * touch your own availability" rule that /api/availability.php enforces, so
 * it's kept as its own narrow, clearly-audited endpoint rather than adding
 * an escape hatch to the auditor-facing one. Every write here is logged
 * with both the acting super admin's id and the target auditor's id, so
 * there's a clear trail of when someone's calendar was edited on their
 * behalf rather than by themselves.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

$actor = ehs_require_role(['super_admin'], true);
$db = get_db();

function ehs_validate_target_auditor(PDO $db, $auditorId): ?array
{
    $auditorId = (int) $auditorId;
    if ($auditorId <= 0) return null;
    $stmt = $db->prepare("SELECT id, name FROM users WHERE id = :id AND role IN ('super_admin','admin','auditor')");
    $stmt->execute(['id' => $auditorId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $auditorId = $_GET['auditor_id'] ?? '';
    $target = ehs_validate_target_auditor($db, $auditorId);
    if (!$target) {
        ehs_json_error('A valid auditor_id is required.', 422);
    }

    $start = $_GET['start'] ?? '';
    $end   = $_GET['end'] ?? '';
    if (!ehs_is_valid_date($start) || !ehs_is_valid_date($end)) {
        ehs_json_error('start and end must be valid YYYY-MM-DD dates.', 422);
    }

    $stmt = $db->prepare(
        'SELECT id, date, session, status, note
         FROM availability
         WHERE auditor_id = :auditor_id AND date >= :start AND date < :end
         ORDER BY date, session'
    );
    $stmt->execute(['auditor_id' => $target['id'], 'start' => $start, 'end' => $end]);

    ehs_json_response(['availability' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ehs_verify_csrf();
    $input = ehs_json_input();

    $target = ehs_validate_target_auditor($db, $input['auditor_id'] ?? null);
    if (!$target) {
        ehs_json_error('A valid auditor_id is required.', 422);
    }

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

    $db->beginTransaction();
    try {
        // Same mutual-exclusivity rule as the auditor's own endpoint:
        // FULL_DAY clears AM/PM, and AM/PM clears FULL_DAY.
        $clearOther = $session === 'FULL_DAY'
            ? $db->prepare('DELETE FROM availability WHERE auditor_id = :auditor_id AND date = :date AND session IN ("AM","PM")')
            : $db->prepare('DELETE FROM availability WHERE auditor_id = :auditor_id AND date = :date AND session = "FULL_DAY"');

        $upsert = $db->prepare(
            'INSERT INTO availability (auditor_id, date, session, status, note)
             VALUES (:auditor_id, :date, :session, :status, :note)
             ON DUPLICATE KEY UPDATE status = VALUES(status), note = VALUES(note)'
        );

        foreach ($dates as $date) {
            $clearOther->execute(['auditor_id' => $target['id'], 'date' => $date]);
            $upsert->execute([
                'auditor_id' => $target['id'],
                'date'       => $date,
                'session'    => $session,
                'status'     => $status,
                'note'       => $note !== '' ? $note : null,
            ]);
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('Admin-override availability update failed: ' . $e->getMessage());
        ehs_json_error('Could not save availability. Please try again.', 500);
    }

    ehs_log_activity(
        $actor['id'],
        'admin_override_availability',
        'availability',
        $target['id'],
        sprintf('Set %s/%s for %d date(s) on behalf of %s (user #%d)', $session, $status, count($dates), $target['name'], $target['id'])
    );

    ehs_json_response(['success' => true, 'updated' => count($dates)]);
}

ehs_json_error('Method not allowed.', 405);
