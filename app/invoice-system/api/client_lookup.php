<?php
/**
 * invoice-system/api/client_lookup.php
 *
 * The approved cross-module exception (Option A): READ-ONLY lookup of the
 * client directory so a super_admin can COPY a client's details into a new
 * billed party. Never writes to cm_*; the only query shape is the fixed-column
 * SELECT in inv_cm_lookup() (id, company_name, uen_registration_no, address,
 * contact_person, phone, email[, entity]).
 *
 * GET ?q=text   -> up to 20 matches on company name or email (min 2 chars)
 * GET ?id=123   -> one client, for prefilling the party form
 * GET ?status=1 -> whether the lookup is available and whether cm_clients
 *                  has the `entity` column (shown on the Settings page)
 *
 * Every response says whether the lookup is available; when cm_clients or a
 * required column is missing it returns an empty result, never a 500.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/inv_helpers.php';

$user = ehs_require_role(INV_ROLES, true);
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    inv_json_error('Method not allowed.', 405);
}

if (isset($_GET['status'])) {
    inv_json_response(inv_cm_lookup_status($db));
}

if (isset($_GET['id'])) {
    $id = inv_int_or_null($_GET['id'], 1, 4294967295);
    if ($id === null || $id === false) {
        inv_json_error('A valid client id is required.', 422);
    }
    $res = inv_cm_lookup($db, null, $id);
    if ($res['available'] && count($res['results']) === 0) {
        inv_json_error('Client not found in the client directory.', 404);
    }
    inv_json_response($res);
}

$q = inv_clean_str($_GET['q'] ?? '', 100);
if ($q === null || mb_strlen($q) < 2) {
    inv_json_error('Type at least 2 characters to search the client directory.', 422);
}

inv_json_response(inv_cm_lookup($db, $q, null));
