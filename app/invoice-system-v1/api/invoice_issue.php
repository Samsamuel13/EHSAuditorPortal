<?php
/**
 * invoice-system/api/invoice_issue.php
 *
 * POST {id, row_hash, confirm: true} -> issue a draft (see inv_issue_invoice()).
 * 409 with `problems` when anything blocks issuing (TBC settings, locked
 * number series, missing address, total <= 0, «TBC» in the text, storage...).
 * A blocked attempt is logged as `issue_blocked` with the reasons; nothing
 * else changes (no number used, no file written).
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/inv_helpers.php';
require_once __DIR__ . '/../includes/inv_invoice.php';

$user = ehs_require_role(INV_ROLES, true);
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    inv_json_error('Method not allowed.', 405);
}
ehs_verify_csrf();
$in = inv_json_input();

$id = inv_int_or_null($in['id'] ?? null, 1, 4294967295);
if (!$id) inv_json_error('A valid invoice id is required.', 422);
if (($in['confirm'] ?? false) !== true) {
    inv_json_error('Confirm that you want to issue this invoice.', 422);
}

$res = inv_issue_invoice($db, $id, (string) ($in['row_hash'] ?? ''), $user);

if (!$res['ok']) {
    if ($res['problems']) {
        inv_log_activity((int) $user['id'], 'issue_blocked', 'inv_invoice', $id,
            implode(' | ', array_map(fn($p) => $p['message'], $res['problems'])));
    } elseif ($res['http'] === 500) {
        inv_log_activity((int) $user['id'], 'issue_failed', 'inv_invoice', $id, $res['error']);
    }
    inv_json_error($res['error'], $res['http'], ['problems' => $res['problems']]);
}

inv_json_response(['ok' => true, 'invoice' => $res['invoice']]);
