<?php
/**
 * crm/api/users_lookup.php — id+name lookup for the owner dropdown.
 * Own copy, same pattern as client-management/api/users_lookup.php,
 * not a shared file (isolation rule).
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/crm_helpers.php';

ehs_require_role(['super_admin', 'admin'], true);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    crm_json_error('Method not allowed.', 405);
}

$stmt = get_db()->query("SELECT id, name FROM users WHERE status = 'active' ORDER BY name ASC");
crm_json_response(['users' => $stmt->fetchAll()]);
