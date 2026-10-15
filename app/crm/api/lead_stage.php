<?php
/**
 * crm/api/lead_stage.php
 *
 * PUT -> change a lead's stage (plain path, no note attached — see
 * lead_notes.php for the "Log Activity" combined note+stage-change path).
 * Body: { id: int, to_stage: string, reason?: string }
 *
 * All the actual logic (whitelist validation, row lock, history write,
 * lost_reason/on_hold_reason, and the one-way cm_clients conversion) now
 * lives in crm_change_lead_stage() in crm_helpers.php, shared with
 * lead_notes.php — see that function's docblock for why this had to be
 * centralized rather than duplicated.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/crm_helpers.php';

$user = ehs_require_role(['super_admin', 'admin'], true);
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    crm_json_error('Method not allowed.', 405);
}

ehs_verify_csrf();
$input = crm_json_input();

$id = (int) ($input['id'] ?? 0);
$toStage = trim((string) ($input['to_stage'] ?? ''));
$reason = crm_clean_str($input['reason'] ?? null, 1000);

if ($id <= 0) {
    crm_json_error('id is required.');
}

try {
    $db->beginTransaction();
    $result = crm_change_lead_stage($db, $id, $toStage, $reason, $user['id'], $user['name']);

    $details = "Stage changed: {$result['from_stage']} -> {$result['to_stage']}"
        . ($result['converted_client_id'] ? " (converted to cm_clients #{$result['converted_client_id']})" : '');
    crm_log_activity($user['id'], 'stage_change', 'lead', $id, $details);

    $db->commit();
} catch (InvalidArgumentException $e) {
    if ($db->inTransaction()) $db->rollBack();
    crm_json_error($e->getMessage(), 422);
} catch (\Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    crm_json_error('Stage change failed: ' . $e->getMessage(), 500);
}

crm_json_response([
    'success'             => true,
    'from_stage'          => $result['from_stage'],
    'to_stage'            => $result['to_stage'],
    'converted_client_id' => $result['converted_client_id'],
]);