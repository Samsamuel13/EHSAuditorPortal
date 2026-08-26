<?php
/**
 * crm/api/followups.php
 *
 * GET    ?lead_id=X          -> follow-ups for one lead, newest due first
 * GET    ?overdue=1          -> all overdue (due_date < today, not done) follow-ups, any lead —
 *                               powers the "overdue follow-ups" dashboard widget
 * POST   { lead_id, due_date, type, owner_id, note } -> create
 * PUT    ?id=X { ...fields, done }                   -> update / mark done
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/crm_helpers.php';

$user = ehs_require_role(['super_admin', 'admin'], true);
$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && isset($_GET['overdue'])) {
    $stmt = $db->prepare(
        "SELECT f.*, l.company_name, COALESCE(u.name, f.owner_name) AS owner_display_name
         FROM crm_followups f
         JOIN crm_leads l ON l.id = f.crm_lead_id
         LEFT JOIN users u ON u.id = f.owner_id
         WHERE f.done = 0 AND f.due_date < CURDATE()
         ORDER BY f.due_date ASC"
    );
    $stmt->execute();
    crm_json_response(['followups' => $stmt->fetchAll()]);
}

if ($method === 'GET') {
    $leadId = (int) ($_GET['lead_id'] ?? 0);
    if ($leadId <= 0) crm_json_error('lead_id is required.', 422);

    $stmt = $db->prepare(
        'SELECT f.*, COALESCE(u.name, f.owner_name) AS owner_display_name
         FROM crm_followups f LEFT JOIN users u ON u.id = f.owner_id
         WHERE f.crm_lead_id = :lead_id
         ORDER BY f.done ASC, f.due_date ASC'
    );
    $stmt->execute(['lead_id' => $leadId]);
    crm_json_response(['followups' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    ehs_verify_csrf();
    $input = crm_json_input();

    $leadId = (int) ($input['lead_id'] ?? 0);
    $dueDate = trim((string) ($input['due_date'] ?? ''));
    if ($leadId <= 0 || !crm_is_valid_date($dueDate)) {
        crm_json_error('lead_id and a valid due_date (YYYY-MM-DD) are required.', 422);
    }

    $leadCheck = $db->prepare('SELECT id FROM crm_leads WHERE id = :id LIMIT 1');
    $leadCheck->execute(['id' => $leadId]);
    if (!$leadCheck->fetch()) crm_json_error('Lead not found.', 404);

    $type = trim((string) ($input['type'] ?? 'call'));
    if (!in_array($type, CRM_FOLLOWUP_TYPES, true)) $type = 'call';

    $ownerId = isset($input['owner_id']) ? (int) $input['owner_id'] : $user['id'];
    $ownerStmt = $db->prepare('SELECT name FROM users WHERE id = :id LIMIT 1');
    $ownerStmt->execute(['id' => $ownerId]);
    $ownerName = $ownerStmt->fetchColumn() ?: $user['name'];

    $stmt = $db->prepare(
        'INSERT INTO crm_followups (crm_lead_id, due_date, type, owner_id, owner_name, note)
         VALUES (:lead_id, :due_date, :type, :owner_id, :owner_name, :note)'
    );
    $stmt->execute([
        'lead_id'   => $leadId,
        'due_date'  => $dueDate,
        'type'      => $type,
        'owner_id'  => $ownerId,
        'owner_name'=> $ownerName,
        'note'      => crm_clean_str($input['note'] ?? null, 2000),
    ]);
    $newId = (int) $db->lastInsertId();

    crm_log_activity($user['id'], 'create_followup', 'crm_lead', $leadId, "Follow-up due $dueDate ($type)");

    crm_json_response(['id' => $newId], 201);
}

if ($method === 'PUT') {
    ehs_verify_csrf();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) crm_json_error('A valid follow-up id is required.', 422);

    $stmt = $db->prepare('SELECT * FROM crm_followups WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $followup = $stmt->fetch();
    if (!$followup) crm_json_error('Follow-up not found.', 404);

    $input = crm_json_input();
    $fields = [];
    $params = ['id' => $id];

    if (array_key_exists('due_date', $input) && crm_is_valid_date($input['due_date'])) {
        $fields[] = 'due_date = :due_date';
        $params['due_date'] = $input['due_date'];
    }
    if (array_key_exists('type', $input) && in_array($input['type'], CRM_FOLLOWUP_TYPES, true)) {
        $fields[] = 'type = :type';
        $params['type'] = $input['type'];
    }
    if (array_key_exists('note', $input)) {
        $fields[] = 'note = :note';
        $params['note'] = crm_clean_str($input['note'], 2000);
    }
    if (array_key_exists('done', $input)) {
        $done = (bool) $input['done'];
        $fields[] = 'done = :done';
        $params['done'] = $done ? 1 : 0;
        $fields[] = 'done_at = :done_at';
        $params['done_at'] = $done ? date('Y-m-d H:i:s') : null;
    }

    if (!$fields) crm_json_error('Nothing to update.', 422);

    $sql = 'UPDATE crm_followups SET ' . implode(', ', $fields) . ' WHERE id = :id';
    $db->prepare($sql)->execute($params);

    crm_log_activity($user['id'], 'update_followup', 'crm_lead', (int) $followup['crm_lead_id'], "Follow-up #$id updated");

    crm_json_response(['success' => true]);
}

crm_json_error('Method not allowed.', 405);
