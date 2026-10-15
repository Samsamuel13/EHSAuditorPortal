<?php
/**
 * crm/api/users_lookup.php
 *
 * GET -> id + name of active users, for the "owner" dropdown on the lead
 * form and board filter bar. Deliberately its own tiny standalone endpoint
 * (a copy of client-management/api/users_lookup.php in spirit) rather than
 * reusing that module's file directly, per the module-isolation convention
 * established in client-management — this module reads the shared `users`
 * table directly, but has its own PHP.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/crm_helpers.php';

ehs_require_role(['super_admin', 'admin'], true);

$stmt = get_db()->query("SELECT id, name, role FROM users WHERE status = 'active' ORDER BY name");
crm_json_response(['users' => $stmt->fetchAll()]);