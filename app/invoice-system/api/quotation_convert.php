<?php
/**
 * invoice-system/api/quotation_convert.php
 *
 * POST {id, line_ids: [..], category_id?, confirm: true}
 *   Converts the ticked ITEM lines of an ACCEPTED quotation into ONE new
 *   draft invoice (Special Rate, or Rate where Special Rate is blank). Each
 *   line can be invoiced once; deleting that draft invoice frees its lines.
 *   Sum rows are never invoiced. Accepted quotations never expire, so
 *   later-year lines can be converted in later years.
 * Returns {ok, invoice_id}; the screen then opens invoice_edit.php?id=N.
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
if (($in['confirm'] ?? false) !== true) inv_json_error('Confirm the conversion.', 422);
$lineIds = $in['line_ids'] ?? null;
if (!is_array($lineIds) || count($lineIds) > INV_QUOTE_MAX_LINES) inv_json_error('Tick the lines to invoice.', 422);
foreach ($lineIds as $l) {
    if (!is_int($l) && !(is_string($l) && ctype_digit($l))) inv_json_error('Invalid line selection.', 422);
}
$cat = null;
if (isset($in['category_id']) && $in['category_id'] !== '' && $in['category_id'] !== null) {
    $cat = inv_int_or_null($in['category_id'], 1, 4294967295);
    if (!$cat || !inv_fetch_category($db, $cat)) inv_json_error('Unknown category.', 422, ['fields' => ['category_id' => 'Unknown.']]);
}

$res = inv_convert_quote($db, $id, $lineIds, $cat, $user);
if (!$res['ok']) inv_json_error($res['error'], $res['http']);
inv_json_response(['ok' => true, 'invoice_id' => $res['invoice_id']], 201);
