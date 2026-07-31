<?php
/**
 * client-management/api/users_lookup.php
 *
 * GET -> id + name of active users, for the "responsible person" dropdown
 * on the certification form. Deliberately a tiny standalone endpoint
 * rather than reusing the scheduling system's /api/users.php, per the
 * isolation requirement — this module reads the shared `users` table
 * directly, but has its own PHP.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/cm_helpers.php';

ehs_require_role(['super_admin', 'admin'], true);

$stmt = get_db()->query("SELECT id, name, role FROM users WHERE status = 'active' ORDER BY name");
cm_json_response(['users' => $stmt->fetchAll()]);
