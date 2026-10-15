<?php
/**
 * invoice-system/api/services.php — services catalogue and categories
 *
 * GET                                         -> services + categories + entities
 * POST {kind:'service', ...}                  -> create service
 * PUT  {kind:'service', id, row_hash, ...}    -> update service
 * PUT  {kind:'service', id, action:'deactivate'|'reactivate'}
 * POST {kind:'category', name}                -> create category
 * PUT  {kind:'category', id, row_hash, name, sort_order}
 * PUT  {kind:'category', id, action:'deactivate'|'reactivate'}
 * No DELETE: invoice lines reference services and invoices reference
 * categories, so both are only deactivated. Editing a service never changes
 * existing invoices (lines copy title, wording and rate).
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/inv_helpers.php';
require_once __DIR__ . '/../includes/inv_money.php';

$user = ehs_require_role(INV_ROLES, true);
$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];

function inv_svc_payload(PDO $db): array
{
    $services = $db->query(
        'SELECT s.*, c.name AS category_name,
                (SELECT COUNT(*) FROM inv_invoice_lines l WHERE l.service_id = s.id) AS used_lines
         FROM inv_services s LEFT JOIN inv_categories c ON c.id = s.category_id
         ORDER BY s.is_active DESC, s.sort_order ASC, s.title ASC'
    )->fetchAll();
    foreach ($services as &$s) {
        $s['row_hash'] = inv_row_hash(array_diff_key($s, ['category_name' => 1, 'used_lines' => 1]));
    }
    unset($s);
    $cats = $db->query(
        'SELECT c.*, (SELECT COUNT(*) FROM inv_invoices i WHERE i.category_id = c.id) AS used_invoices
         FROM inv_categories c ORDER BY c.is_active DESC, c.sort_order ASC, c.name ASC'
    )->fetchAll();
    foreach ($cats as &$c) {
        $c['row_hash'] = inv_row_hash(array_diff_key($c, ['used_invoices' => 1]));
    }
    unset($c);
    $ents = [];
    foreach (inv_get_entities($db) as $code => $e) $ents[] = ['code' => $code, 'name' => $e['display_name']];
    return ['services' => $services, 'categories' => $cats, 'entities' => $ents];
}

function inv_lock_row(PDO $db, string $table, int $id): ?array
{
    // $table is one of two fixed names (never user input).
    $stmt = $db->prepare('SELECT * FROM ' . ($table === 'inv_services' ? 'inv_services' : 'inv_categories') . ' WHERE id = :r_id FOR UPDATE');
    $stmt->execute(['r_id' => $id]);
    $r = $stmt->fetch();
    return $r ?: null;
}

function inv_service_from_input(PDO $db, array $in, ?int $selfId): array
{
    $e = [];
    $d = [];
    $d['code'] = strtoupper((string) inv_clean_str($in['code'] ?? null, 30));
    if (!preg_match('/^[A-Z0-9][A-Z0-9-]{1,29}$/', $d['code'])) {
        $e['code'] = 'Code: 2 to 30 letters, digits or "-" (e.g. RM-BIZSAFE).';
    } else {
        $chk = $db->prepare('SELECT id FROM inv_services WHERE code = :u_code');
        $chk->execute(['u_code' => $d['code']]);
        $other = $chk->fetchColumn();
        if ($other !== false && (int) $other !== (int) $selfId) $e['code'] = 'This code is already used.';
    }
    $d['title'] = inv_clean_str($in['title'] ?? null, 255);
    if ($d['title'] === null) $e['title'] = 'Title is required (printed in bold on the invoice).';
    $d['standard_wording'] = inv_clean_str($in['standard_wording'] ?? null, 4000, true);
    $rate = inv_parse_cents($in['default_rate'] ?? '');
    if ($rate === null || $rate > INV_MAX_RATE_CENTS) {
        $e['default_rate'] = 'Default rate must be 0 to 9,999,999.99.';
        $rate = 0;
    }
    $d['default_rate'] = inv_cents_to_decimal($rate);
    $cat = inv_int_or_null($in['category_id'] ?? null, 1, 4294967295);
    if ($cat === false) {
        $e['category_id'] = 'Invalid category.';
        $cat = null;
    } elseif ($cat !== null) {
        $c = $db->prepare('SELECT id FROM inv_categories WHERE id = :c_id');
        $c->execute(['c_id' => $cat]);
        if ($c->fetchColumn() === false) $e['category_id'] = 'Unknown category.';
    }
    $d['category_id'] = $cat;
    $ent = inv_clean_str($in['entity_code'] ?? null, 20);
    if ($ent !== null && !inv_entity_exists($db, $ent)) {
        $e['entity_code'] = 'Unknown issuing entity.';
        $ent = null;
    }
    $d['entity_code'] = $ent;
    $d['qty_from_entities'] = !empty($in['qty_from_entities']) ? 1 : 0;
    $so = inv_int_or_null($in['sort_order'] ?? 0, 0, 100000);
    $d['sort_order'] = $so === false || $so === null ? 0 : $so;
    return [$d, $e];
}

if ($method === 'GET') {
    inv_json_response(inv_svc_payload($db));
}

if ($method !== 'POST' && $method !== 'PUT') {
    inv_json_error('Method not allowed.', 405);
}
ehs_verify_csrf();
$in = inv_json_input();
$kind = $in['kind'] ?? '';
if (!in_array($kind, ['service', 'category'], true)) inv_json_error('Unknown kind.', 422);

// --------------------------------------------------------------- services
if ($kind === 'service') {
    if ($method === 'POST') {
        [$d, $e] = inv_service_from_input($db, $in, null);
        if ($e) inv_json_error('Please correct the highlighted fields.', 422, ['fields' => $e]);
        $stmt = $db->prepare(
            'INSERT INTO inv_services (code, title, standard_wording, default_rate, category_id, entity_code, qty_from_entities, sort_order, created_by)
             VALUES (:code, :title, :standard_wording, :default_rate, :category_id, :entity_code, :qty_from_entities, :sort_order, :created_by)'
        );
        $stmt->execute($d + ['created_by' => (int) $user['id']]);
        $id = (int) $db->lastInsertId();
        inv_log_activity((int) $user['id'], 'create_service', 'inv_service', $id, $d['code'] . ' ' . $d['title'] . ' @ ' . $d['default_rate']);
        inv_json_response(inv_svc_payload($db), 201);
    }

    $id = inv_int_or_null($in['id'] ?? null, 1, 4294967295);
    if (!$id) inv_json_error('A valid service id is required.', 422);
    $db->beginTransaction();
    $before = inv_lock_row($db, 'inv_services', $id);
    if (!$before) inv_json_error('Service not found.', 404);
    $action = $in['action'] ?? 'update';

    if ($action === 'deactivate' || $action === 'reactivate') {
        $u = $db->prepare('UPDATE inv_services SET is_active = :a_on WHERE id = :a_id');
        $u->execute(['a_on' => $action === 'reactivate' ? 1 : 0, 'a_id' => $id]);
        inv_log_activity((int) $user['id'], $action . '_service', 'inv_service', $id, $before['code']);
        $db->commit();
        inv_json_response(inv_svc_payload($db));
    }
    if ($action !== 'update') inv_json_error('Unknown action.', 422);
    if (!hash_equals(inv_row_hash($before), (string) ($in['row_hash'] ?? ''))) {
        inv_json_error('This service was changed by someone else. Reload and try again.', 409);
    }
    [$d, $e] = inv_service_from_input($db, $in, $id);
    if ($e) inv_json_error('Please correct the highlighted fields.', 422, ['fields' => $e]);
    $u = $db->prepare(
        'UPDATE inv_services SET code = :code, title = :title, standard_wording = :standard_wording,
            default_rate = :default_rate, category_id = :category_id, entity_code = :entity_code,
            qty_from_entities = :qty_from_entities, sort_order = :sort_order
         WHERE id = :w_id'
    );
    $u->execute($d + ['w_id' => $id]);
    $changed = [];
    foreach ($d as $k => $v) if ((string) $before[$k] !== (string) $v) $changed[] = $k;
    if ($changed) {
        $det = in_array('default_rate', $changed, true) ? " (rate {$before['default_rate']} -> {$d['default_rate']})" : '';
        inv_log_activity((int) $user['id'], 'update_service', 'inv_service', $id, $d['code'] . ': ' . implode(', ', $changed) . $det);
    }
    $db->commit();
    inv_json_response(inv_svc_payload($db));
}

// ------------------------------------------------------------- categories
if ($method === 'POST') {
    $name = inv_clean_str($in['name'] ?? null, 100);
    if ($name === null) inv_json_error('Category name is required.', 422, ['fields' => ['name' => 'Required.']]);
    $chk = $db->prepare('SELECT COUNT(*) FROM inv_categories WHERE name = :n_name');
    $chk->execute(['n_name' => $name]);
    if ((int) $chk->fetchColumn() > 0) inv_json_error('A category with this name already exists.', 422, ['fields' => ['name' => 'Already exists.']]);
    $so = inv_int_or_null($in['sort_order'] ?? 50, 0, 100000);
    $stmt = $db->prepare('INSERT INTO inv_categories (name, sort_order) VALUES (:c_name, :c_sort)');
    $stmt->execute(['c_name' => $name, 'c_sort' => $so === false || $so === null ? 50 : $so]);
    inv_log_activity((int) $user['id'], 'create_category', 'inv_category', (int) $db->lastInsertId(), $name);
    inv_json_response(inv_svc_payload($db), 201);
}

$id = inv_int_or_null($in['id'] ?? null, 1, 4294967295);
if (!$id) inv_json_error('A valid category id is required.', 422);
$db->beginTransaction();
$before = inv_lock_row($db, 'inv_categories', $id);
if (!$before) inv_json_error('Category not found.', 404);
$action = $in['action'] ?? 'update';
if ($action === 'deactivate' || $action === 'reactivate') {
    $u = $db->prepare('UPDATE inv_categories SET is_active = :a_on WHERE id = :a_id');
    $u->execute(['a_on' => $action === 'reactivate' ? 1 : 0, 'a_id' => $id]);
    inv_log_activity((int) $user['id'], $action . '_category', 'inv_category', $id, $before['name']);
    $db->commit();
    inv_json_response(inv_svc_payload($db));
}
if ($action !== 'update') inv_json_error('Unknown action.', 422);
if (!hash_equals(inv_row_hash($before), (string) ($in['row_hash'] ?? ''))) {
    inv_json_error('This category was changed by someone else. Reload and try again.', 409);
}
$name = inv_clean_str($in['name'] ?? null, 100);
if ($name === null) inv_json_error('Category name is required.', 422, ['fields' => ['name' => 'Required.']]);
$chk = $db->prepare('SELECT COUNT(*) FROM inv_categories WHERE name = :n_name AND id <> :n_id');
$chk->execute(['n_name' => $name, 'n_id' => $id]);
if ((int) $chk->fetchColumn() > 0) inv_json_error('A category with this name already exists.', 422, ['fields' => ['name' => 'Already exists.']]);
$so = inv_int_or_null($in['sort_order'] ?? $before['sort_order'], 0, 100000);
$u = $db->prepare('UPDATE inv_categories SET name = :u_name, sort_order = :u_sort WHERE id = :u_id');
$u->execute(['u_name' => $name, 'u_sort' => $so === false || $so === null ? (int) $before['sort_order'] : $so, 'u_id' => $id]);
inv_log_activity((int) $user['id'], 'update_category', 'inv_category', $id, "'{$before['name']}' -> '$name' (issued invoices keep their printed category)");
$db->commit();
inv_json_response(inv_svc_payload($db));
