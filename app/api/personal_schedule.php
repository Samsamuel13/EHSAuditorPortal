<?php
// File: api/personal_schedule.php
/**
 * /api/personal_schedule.php
 * GET    ?start=&end=          -> the logged-in user's own items in range
 * POST   { date, time_label, title } -> create
 * DELETE ?id=N                  -> remove (only your own item)
 *
 * No editing endpoint by design — these are quick personal notes; deleting
 * and re-adding is simpler than a full edit flow for something this
 * lightweight. Always scoped to the session user; there is no user_id
 * accepted from the client anywhere in this file.
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
        'SELECT id, date, time_label, title FROM personal_schedule_items
         WHERE user_id = :uid AND date >= :start AND date < :end
         ORDER BY date, id'
    );
    $stmt->execute(['uid' => $user['id'], 'start' => $start, 'end' => $end]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) { $r['id'] = (int) $r['id']; }
    unset($r);

    ehs_json_response(['items' => $rows]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ehs_verify_csrf();
    $input = ehs_json_input();

    $date = $input['date'] ?? '';
    $timeLabel = trim((string) ($input['time_label'] ?? ''));
    $title = trim((string) ($input['title'] ?? ''));

    if (!ehs_is_valid_date($date)) {
        ehs_json_error('A valid date is required.', 422);
    }
    if ($title === '' || mb_strlen($title) > 255) {
        ehs_json_error('A title is required (max 255 characters).', 422);
    }
    if (mb_strlen($timeLabel) > 50) {
        ehs_json_error('Time label is too long (max 50 characters).', 422);
    }

    $stmt = $db->prepare(
        'INSERT INTO personal_schedule_items (user_id, date, time_label, title)
         VALUES (:uid, :date, :time_label, :title)'
    );
    $stmt->execute([
        'uid'        => $user['id'],
        'date'       => $date,
        'time_label' => $timeLabel !== '' ? $timeLabel : null,
        'title'      => $title,
    ]);
    $id = (int) $db->lastInsertId();

    ehs_log_activity($user['id'], 'create_personal_schedule_item', 'personal_schedule_item', $id, "$date: $title");
    ehs_json_response(['success' => true, 'id' => $id], 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    ehs_verify_csrf();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        ehs_json_error('A valid item id is required.', 422);
    }

    // Scoped to user_id = own session id, so this can never delete anyone else's item.
    $stmt = $db->prepare('DELETE FROM personal_schedule_items WHERE id = :id AND user_id = :uid');
    $stmt->execute(['id' => $id, 'uid' => $user['id']]);

    if ($stmt->rowCount() === 0) {
        ehs_json_error('Item not found.', 404);
    }

    ehs_log_activity($user['id'], 'delete_personal_schedule_item', 'personal_schedule_item', $id, '');
    ehs_json_response(['success' => true]);
}

ehs_json_error('Method not allowed.', 405);
