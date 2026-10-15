<?php
/**
 * invoice-system/api/parties.php — billed parties (customers / consultancies)
 *
 * GET  ?id=123                                  -> one party
 * GET  ?q=&status=active|inactive|all&page=1    -> list (50 per page)
 * POST {fields..., source, source_client_id, confirm_duplicate}
 *                                               -> create. Returns 409 with
 *        `duplicates` when a party with the same client-directory id or the
 *        same normalized name exists; resend with confirm_duplicate=true.
 * PUT  {id, row_hash, fields...}                -> update (optimistic lock:
 *        409 if the row changed since it was read; row_hash comes from GET ?id=)
 * PUT  {id, action: 'deactivate'|'reactivate'}  -> toggle is_active
 * No DELETE: invoices reference parties, so parties are only deactivated.
 *
 * source / source_client_id are set once at creation and never changed.
 * Copied client-directory details are a starting point only: every field is
 * editable here, and nothing is ever written back to cm_clients.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/inv_helpers.php';

$user = ehs_require_role(INV_ROLES, true);
$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];

const INV_PARTY_PAGE_SIZE = 50;

/** Lowercase, strip punctuation and legal suffixes; for the duplicate warning only. */
function inv_normalize_party_name(?string $name): string
{
    $n = strtolower(trim((string) $name));
    $n = preg_replace('/[^a-z0-9\s]/', ' ', $n);
    $n = preg_replace('/\b(pte|ltd|llp|inc|co|corp|company|limited|private|sdn|bhd)\b/', ' ', $n);
    return trim(preg_replace('/\s+/', ' ', $n));
}

/**
 * Validate and normalize party fields from input.
 * Returns [$data, $errors]. $data only contains whitelisted columns.
 */
function inv_party_from_input(PDO $db, array $in): array
{
    $errors = [];
    $d = [];

    $d['display_name'] = inv_clean_str($in['display_name'] ?? null, 200);
    if ($d['display_name'] === null) $errors['display_name'] = 'Party name is required.';

    $d['legal_name']      = inv_clean_str($in['legal_name'] ?? null, 200);
    $d['uen']             = inv_clean_str($in['uen'] ?? null, 50);
    $d['billing_address'] = inv_clean_str($in['billing_address'] ?? null, 1000, true);
    $d['attention']       = inv_clean_str($in['attention'] ?? null, 150);
    $d['phone']           = inv_clean_str($in['phone'] ?? null, 100);
    $d['notes']           = inv_clean_str($in['notes'] ?? null, 2000, true);

    $email = inv_clean_str($in['accounts_email'] ?? null, 150);
    if ($email !== null && !inv_is_valid_email($email)) {
        $errors['accounts_email'] = 'Accounts email is not a valid email address.';
    }
    $d['accounts_email'] = $email !== null ? strtolower($email) : null;

    $cc = inv_clean_email_list(inv_clean_str($in['cc_emails'] ?? null, 500), 500);
    if ($cc === false) {
        $errors['cc_emails'] = 'CC emails must be valid addresses separated by commas.';
        $cc = null;
    }
    $d['cc_emails'] = $cc;

    $terms = inv_int_or_null($in['payment_terms_days'] ?? null, 0, 365);
    if ($terms === false) {
        $errors['payment_terms_days'] = 'Payment terms must be a whole number of days from 0 to 365 (blank = entity default).';
        $terms = null;
    }
    $d['payment_terms_days'] = $terms;

    $entity = inv_clean_str($in['default_entity_code'] ?? null, 20);
    if ($entity !== null && !inv_entity_exists($db, $entity)) {
        $errors['default_entity_code'] = 'Unknown issuing entity.';
        $entity = null;
    }
    $d['default_entity_code'] = $entity;

    return [$d, $errors];
}

/** Attach the optimistic-lock token the edit form must send back. */
function inv_with_hash(?array $party): ?array
{
    return $party === null ? null : $party + ['row_hash' => inv_row_hash($party)];
}

function inv_fetch_party(PDO $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT * FROM inv_parties WHERE id = :p_id LIMIT 1');
    $stmt->execute(['p_id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// ---------------------------------------------------------------------------
// GET
// ---------------------------------------------------------------------------
if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $id = inv_int_or_null($_GET['id'], 1, 4294967295);
        if (!$id) inv_json_error('A valid party id is required.', 422);
        $party = inv_fetch_party($db, $id);
        if (!$party) inv_json_error('Party not found.', 404);
        inv_json_response(['party' => inv_with_hash($party)]);
    }

    $where = [];
    $params = [];

    $status = $_GET['status'] ?? 'active';
    if (!in_array($status, ['active', 'inactive', 'all'], true)) $status = 'active';
    if ($status !== 'all') {
        $where[] = 'is_active = ?';
        $params[] = $status === 'active' ? 1 : 0;
    }

    $q = inv_clean_str($_GET['q'] ?? null, 100);
    if ($q !== null) {
        $like = '%' . inv_like_escape($q) . '%';
        $where[] = '(display_name LIKE ? OR legal_name LIKE ? OR accounts_email LIKE ? OR uen LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $page = inv_int_or_null($_GET['page'] ?? 1, 1, 100000) ?: 1;
    $offset = ($page - 1) * INV_PARTY_PAGE_SIZE;

    $count = $db->prepare("SELECT COUNT(*) FROM inv_parties $whereSql");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    // LIMIT/OFFSET are server-computed integers (never user strings).
    $stmt = $db->prepare(
        "SELECT id, display_name, legal_name, uen, billing_address, attention, accounts_email, phone,
                payment_terms_days, default_entity_code, source, source_client_id, is_active, updated_at
         FROM inv_parties $whereSql
         ORDER BY display_name ASC, id ASC
         LIMIT " . INV_PARTY_PAGE_SIZE . ' OFFSET ' . (int) $offset
    );
    $stmt->execute($params);

    inv_json_response([
        'parties'   => $stmt->fetchAll(),
        'total'     => $total,
        'page'      => $page,
        'page_size' => INV_PARTY_PAGE_SIZE,
    ]);
}

// ---------------------------------------------------------------------------
// POST: create
// ---------------------------------------------------------------------------
if ($method === 'POST') {
    ehs_verify_csrf();
    $in = inv_json_input();

    [$d, $errors] = inv_party_from_input($db, $in);

    $source = ($in['source'] ?? 'manual') === 'cm_lookup' ? 'cm_lookup' : 'manual';
    $sourceClientId = null;
    if ($source === 'cm_lookup') {
        $sourceClientId = inv_int_or_null($in['source_client_id'] ?? null, 1, 4294967295);
        if (!$sourceClientId) {
            $errors['source_client_id'] = 'Client directory id is missing.';
        } else {
            $check = inv_cm_lookup($db, null, $sourceClientId);
            if (!$check['available'] || count($check['results']) === 0) {
                $errors['source_client_id'] = 'That client was not found in the client directory. Save it as a manual party instead.';
            }
        }
    }

    if ($errors) inv_json_error('Please correct the highlighted fields.', 422, ['fields' => $errors]);

    // Duplicate warning (advisory; the user can confirm and continue).
    if (empty($in['confirm_duplicate'])) {
        $dups = [];
        if ($sourceClientId) {
            $s = $db->prepare('SELECT id, display_name, is_active FROM inv_parties WHERE source_client_id = :d_client');
            $s->execute(['d_client' => $sourceClientId]);
            foreach ($s->fetchAll() as $r) $dups[$r['id']] = $r + ['reason' => 'Same client-directory record'];
        }
        $norm = inv_normalize_party_name($d['display_name']);
        if ($norm !== '') {
            foreach ($db->query('SELECT id, display_name, legal_name, is_active FROM inv_parties')->fetchAll() as $r) {
                if (!isset($dups[$r['id']]) &&
                    ($norm === inv_normalize_party_name($r['display_name']) || $norm === inv_normalize_party_name($r['legal_name']))) {
                    $dups[$r['id']] = ['id' => $r['id'], 'display_name' => $r['display_name'], 'is_active' => $r['is_active'], 'reason' => 'Same name'];
                }
            }
        }
        if ($dups) {
            inv_json_error('A similar party already exists.', 409, ['duplicates' => array_values($dups)]);
        }
    }

    $stmt = $db->prepare(
        'INSERT INTO inv_parties
            (display_name, legal_name, uen, billing_address, attention, accounts_email, cc_emails, phone,
             payment_terms_days, default_entity_code, source, source_client_id, source_copied_at, notes,
             created_by, created_by_name)
         VALUES
            (:display_name, :legal_name, :uen, :billing_address, :attention, :accounts_email, :cc_emails, :phone,
             :payment_terms_days, :default_entity_code, :source, :source_client_id, :source_copied_at, :notes,
             :created_by, :created_by_name)'
    );
    $stmt->execute([
        'display_name'        => $d['display_name'],
        'legal_name'          => $d['legal_name'],
        'uen'                 => $d['uen'],
        'billing_address'     => $d['billing_address'],
        'attention'           => $d['attention'],
        'accounts_email'      => $d['accounts_email'],
        'cc_emails'           => $d['cc_emails'],
        'phone'               => $d['phone'],
        'payment_terms_days'  => $d['payment_terms_days'],
        'default_entity_code' => $d['default_entity_code'],
        'source'              => $source,
        'source_client_id'    => $sourceClientId,
        'source_copied_at'    => $sourceClientId ? date('Y-m-d H:i:s') : null,
        'notes'               => $d['notes'],
        'created_by'          => (int) $user['id'],
        'created_by_name'     => $user['name'],
    ]);
    $id = (int) $db->lastInsertId();

    inv_log_activity((int) $user['id'], 'create_party', 'inv_party', $id,
        $d['display_name'] . ($sourceClientId ? " (copied from client directory #$sourceClientId)" : ' (manual)'));

    inv_json_response(['party' => inv_with_hash(inv_fetch_party($db, $id))], 201);
}

// ---------------------------------------------------------------------------
// PUT: update or (de)activate
// ---------------------------------------------------------------------------
if ($method === 'PUT') {
    ehs_verify_csrf();
    $in = inv_json_input();

    $id = inv_int_or_null($in['id'] ?? null, 1, 4294967295);
    if (!$id) inv_json_error('A valid party id is required.', 422);

    // Row lock for the check-update sequence (released by commit / rollback).
    $db->beginTransaction();
    $lock = $db->prepare('SELECT * FROM inv_parties WHERE id = :l_id FOR UPDATE');
    $lock->execute(['l_id' => $id]);
    $before = $lock->fetch();
    if (!$before) inv_json_error('Party not found.', 404);

    $action = $in['action'] ?? 'update';

    if ($action === 'deactivate' || $action === 'reactivate') {
        $newActive = $action === 'reactivate' ? 1 : 0;
        $stmt = $db->prepare('UPDATE inv_parties SET is_active = :a_active WHERE id = :a_id');
        $stmt->execute(['a_active' => $newActive, 'a_id' => $id]);
        inv_log_activity((int) $user['id'], $action . '_party', 'inv_party', $id, $before['display_name']);
        $db->commit();
        inv_json_response(['party' => inv_with_hash(inv_fetch_party($db, $id))]);
    }

    if ($action !== 'update') inv_json_error('Unknown action.', 422);

    // Content-hash optimistic lock (updated_at has 1-second precision).
    if (!hash_equals(inv_row_hash($before), (string) ($in['row_hash'] ?? ''))) {
        inv_json_error('This party was changed by someone else since you opened it. Reload and try again.', 409);
    }

    [$d, $errors] = inv_party_from_input($db, $in);
    if ($errors) inv_json_error('Please correct the highlighted fields.', 422, ['fields' => $errors]);

    $stmt = $db->prepare(
        'UPDATE inv_parties SET
            display_name = :display_name, legal_name = :legal_name, uen = :uen,
            billing_address = :billing_address, attention = :attention, accounts_email = :accounts_email,
            cc_emails = :cc_emails, phone = :phone, payment_terms_days = :payment_terms_days,
            default_entity_code = :default_entity_code, notes = :notes
         WHERE id = :w_id'
    );
    $stmt->execute([
        'display_name'        => $d['display_name'],
        'legal_name'          => $d['legal_name'],
        'uen'                 => $d['uen'],
        'billing_address'     => $d['billing_address'],
        'attention'           => $d['attention'],
        'accounts_email'      => $d['accounts_email'],
        'cc_emails'           => $d['cc_emails'],
        'phone'               => $d['phone'],
        'payment_terms_days'  => $d['payment_terms_days'],
        'default_entity_code' => $d['default_entity_code'],
        'notes'               => $d['notes'],
        'w_id'                => $id,
    ]);

    $after = inv_fetch_party($db, $id);
    $changed = [];
    foreach ($d as $k => $v) {
        if ((string) $before[$k] !== (string) $after[$k]) $changed[] = $k;
    }
    if ($changed) {
        inv_log_activity((int) $user['id'], 'update_party', 'inv_party', $id,
            $after['display_name'] . ': changed ' . implode(', ', $changed));
    }
    $db->commit();
    inv_json_response(['party' => inv_with_hash($after), 'changed' => $changed]);
}

inv_json_error('Method not allowed.', 405);
