<?php
/**
 * /api/holidays.php
 * GET    ?start=&end=            -> holidays in range (any authenticated role — used to highlight calendars)
 * POST   { date, name, type }    -> create one holiday                (admin/super_admin only)
 *        { holidays: [ {date,name,type}, ... ] } -> bulk import       (admin/super_admin only)
 * PUT    ?id=N { date, name, type } -> update                          (admin/super_admin only)
 * DELETE ?id=N                    -> remove                             (admin/super_admin only)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

$user = ehs_require_role(['super_admin', 'admin', 'auditor'], true);
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $start = $_GET['start'] ?? '';
    $end   = $_GET['end'] ?? '';

    if (!ehs_is_valid_date($start) || !ehs_is_valid_date($end)) {
        ehs_json_error('start and end must be valid YYYY-MM-DD dates.', 422);
    }

    $stmt = $db->prepare(
        'SELECT id, date, name, type FROM holidays WHERE date >= :start AND date < :end ORDER BY date'
    );
    $stmt->execute(['start' => $start, 'end' => $end]);

    ehs_json_response(['holidays' => $stmt->fetchAll()]);
}

// Everything below mutates data — admin/super_admin only.
if (!in_array($user['role'], ['super_admin', 'admin'], true)) {
    ehs_json_error('Forbidden: insufficient role.', 403);
}

function ehs_validate_holiday_input(array $input): array
{
    $errors = [];
    if (!ehs_is_valid_date($input['date'] ?? '')) $errors[] = 'A valid date is required.';
    if (trim((string) ($input['name'] ?? '')) === '') $errors[] = 'A holiday name is required.';
    $type = $input['type'] ?? 'public_holiday';
    if (!in_array($type, ['public_holiday', 'company_holiday'], true)) $errors[] = 'Invalid holiday type.';
    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ehs_verify_csrf();
    $input = ehs_json_input();

    // --- bulk import path ---
    if (isset($input['holidays']) && is_array($input['holidays'])) {
        $stmt = $db->prepare(
            'INSERT INTO holidays (date, name, type) VALUES (:date, :name, :type)
             ON DUPLICATE KEY UPDATE name = VALUES(name), type = VALUES(type)'
        );
        $count = 0;
        $skipped = [];
        foreach ($input['holidays'] as $row) {
            if (!is_array($row)) continue;
            $errors = ehs_validate_holiday_input($row);
            if ($errors) { $skipped[] = ($row['date'] ?? '?') . ': ' . implode(' ', $errors); continue; }
            $stmt->execute([
                'date' => $row['date'],
                'name' => trim($row['name']),
                'type' => $row['type'] ?? 'public_holiday',
            ]);
            $count++;
        }
        ehs_log_activity($user['id'], 'bulk_import_holidays', 'holiday', null, "$count imported");
        ehs_json_response(['success' => true, 'imported' => $count, 'skipped' => $skipped], 201);
    }

    // --- single create path ---
    $errors = ehs_validate_holiday_input($input);
    if ($errors) ehs_json_error(implode(' ', $errors), 422);

    try {
        $stmt = $db->prepare('INSERT INTO holidays (date, name, type) VALUES (:date, :name, :type)');
        $stmt->execute(['date' => $input['date'], 'name' => trim($input['name']), 'type' => $input['type'] ?? 'public_holiday']);
        $id = (int) $db->lastInsertId();
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') ehs_json_error('A holiday already exists on that date.', 409);
        throw $e;
    }

    ehs_log_activity($user['id'], 'create_holiday', 'holiday', $id, trim($input['name']));
    ehs_json_response(['success' => true, 'id' => $id], 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    ehs_verify_csrf();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) ehs_json_error('A valid holiday id is required.', 422);

    $input = ehs_json_input();
    $errors = ehs_validate_holiday_input($input);
    if ($errors) ehs_json_error(implode(' ', $errors), 422);

    try {
        $stmt = $db->prepare('UPDATE holidays SET date = :date, name = :name, type = :type WHERE id = :id');
        $stmt->execute(['date' => $input['date'], 'name' => trim($input['name']), 'type' => $input['type'] ?? 'public_holiday', 'id' => $id]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') ehs_json_error('A holiday already exists on that date.', 409);
        throw $e;
    }

    ehs_log_activity($user['id'], 'update_holiday', 'holiday', $id, trim($input['name']));
    ehs_json_response(['success' => true]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    ehs_verify_csrf();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) ehs_json_error('A valid holiday id is required.', 422);

    $stmt = $db->prepare('DELETE FROM holidays WHERE id = :id');
    $stmt->execute(['id' => $id]);

    if ($stmt->rowCount() === 0) ehs_json_error('Holiday not found.', 404);

    ehs_log_activity($user['id'], 'delete_holiday', 'holiday', $id, '');
    ehs_json_response(['success' => true]);
}

ehs_json_error('Method not allowed.', 405);
