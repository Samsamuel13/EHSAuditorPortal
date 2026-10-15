<?php
/**
 * invoice-system/api/quotation_action.php
 *
 * POST {id, row_hash, action, ...}
 *   action 'finalise' {confirm: true}  -> "Finalise and send": number assigned
 *          (or base-R<n> for a revision), PDF with stamp stored, locked; the
 *          revised quotation becomes superseded. 409 + problems when blocked.
 *   action 'revise'                    -> new draft revision of a SENT quotation.
 *   action 'reject' {reason?}          -> sent -> rejected.
 *   action 'cancel' {reason}           -> sent/accepted -> cancelled (nothing invoiced).
 * Acceptance: api/quotation_accept.php. Conversion: api/quotation_convert.php.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/inv_helpers.php';
require_once __DIR__ . '/../includes/inv_quote.php';

$user = ehs_require_role(INV_ROLES, true);
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') inv_json_error('Method not allowed.', 405);
ehs_verify_csrf();
if (!inv_table_exists($db, 'inv_quotations')) inv_json_error('Import db/inv_migration_004.sql first.', 409);
$in = inv_json_input();

$id = inv_int_or_null($in['id'] ?? null, 1, 4294967295);
if (!$id) inv_json_error('A valid quotation id is required.', 422);
$hash = (string) ($in['row_hash'] ?? '');
$action = $in['action'] ?? '';

switch ($action) {
    case 'finalise':
        if (($in['confirm'] ?? false) !== true) inv_json_error('Confirm that you want to finalise and send this quotation.', 422);
        $res = inv_finalise_quote($db, $id, $hash, $user);
        if (!$res['ok']) {
            if ($res['problems']) {
                inv_log_activity((int) $user['id'], 'send_quotation_blocked', 'inv_quotation', $id,
                    implode(' | ', array_map(fn($p) => $p['message'], $res['problems'])));
            } elseif ($res['http'] === 500) {
                inv_log_activity((int) $user['id'], 'send_quotation_failed', 'inv_quotation', $id, $res['error']);
            }
            inv_json_error($res['error'], $res['http'], ['problems' => $res['problems']]);
        }
        inv_json_response(['ok' => true, 'quotation' => ['id' => (int) $res['quotation']['id'], 'quote_no' => $res['quotation']['quote_no']]]);
        // no break: inv_json_response exits

    case 'revise':
        $res = inv_revise_quote($db, $id, $hash, $user);
        if (!$res['ok']) inv_json_error($res['error'], $res['http'], isset($res['id']) ? ['id' => $res['id']] : []);
        inv_json_response(['ok' => true, 'id' => $res['id']], 201);

    case 'reject':
    case 'cancel':
        $reason = inv_clean_str($in['reason'] ?? null, 500, true);
        $res = inv_close_quote($db, $id, $hash, $user, $action, $reason);
        if (!$res['ok']) inv_json_error($res['error'], $res['http'], isset($res['fields']) ? ['fields' => $res['fields']] : []);
        inv_json_response(['ok' => true]);

    default:
        inv_json_error('Unknown action.', 422);
}
