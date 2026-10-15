<?php
/**
 * invoice-system/api/config.php — module options editable on the Settings page.
 *
 * PUT {pdf_page_size: 'A4'|'letter'} -> change the paper size for NEW PDFs.
 * Issued invoices keep their stored PDF. Only whitelisted keys are writable;
 * storage_path is deliberately NOT editable here (set it by SQL, see README).
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/inv_helpers.php';

$user = ehs_require_role(INV_ROLES, true);
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    inv_json_error('Method not allowed.', 405);
}
ehs_verify_csrf();
$in = inv_json_input();

$paper = $in['pdf_page_size'] ?? null;
if (!in_array($paper, ['A4', 'letter'], true)) {
    inv_json_error('Paper size must be A4 or letter.', 422, ['fields' => ['pdf_page_size' => 'Choose A4 or letter.']]);
}

$cur = $db->prepare('SELECT cfg_value FROM inv_config WHERE cfg_key = :g_key');
$cur->execute(['g_key' => 'pdf_page_size']);
$before = $cur->fetchColumn();
if ($before === false) {
    inv_json_error('Run db/inv_migration_003.sql first (pdf_page_size is missing).', 409);
}
if ($before !== $paper) {
    $u = $db->prepare('UPDATE inv_config SET cfg_value = :u_val, updated_by = :u_by, updated_by_name = :u_name WHERE cfg_key = :u_key');
    $u->execute(['u_val' => $paper, 'u_by' => (int) $user['id'], 'u_name' => $user['name'], 'u_key' => 'pdf_page_size']);
    inv_log_activity((int) $user['id'], 'update_module_option', 'inv_config', null, "pdf_page_size: $before -> $paper");
}
inv_json_response(['ok' => true, 'pdf_page_size' => $paper]);
