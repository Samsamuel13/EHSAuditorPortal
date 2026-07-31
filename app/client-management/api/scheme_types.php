<?php
/**
 * client-management/api/scheme_types.php
 *
 * GET -> list all scheme types (for filter dropdowns and, later, the
 * certification form). Read-only for now — a dedicated "manage scheme
 * types" screen (Super Admin only, per the module's permission table)
 * is planned alongside certification management in the next build step.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/cm_helpers.php';

ehs_require_role(['super_admin', 'admin'], true);
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->query('SELECT id, category, name, default_cycle_years, description FROM cm_scheme_types ORDER BY category, name');
    cm_json_response(['scheme_types' => $stmt->fetchAll()]);
}

cm_json_error('Method not allowed.', 405);
