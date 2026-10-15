<?php
/**
 * invoice-system/api/quotations.php
 *
 * GET  ?id=N            -> one quotation: row, lines (+ audited entities),
 *                          display status (expired is computed), revisions of
 *                          the same base number, invoices created from it and,
 *                          for drafts, the live finalise pre-check.
 * GET  ?status=&entity=&q=&date_from=&date_to=&page=
 *                       -> list (50 per page) + overview metrics.
 *                          status: draft|sent|accepted|rejected|cancelled|superseded|expired|open|all
 *                          ("open" = sent and still valid; "expired" = sent and past valid_until)
 * POST {action:'create', ...draft}  -> new draft (texts copied from the entity defaults)
 * POST {action:'duplicate', id}     -> new draft copied from any quotation (not a revision)
 * PUT  {id, row_hash, ...draft}     -> save a draft (409 if changed meanwhile)
 * DELETE {id, row_hash}             -> delete a draft (never a sent quotation)
 *
 * Actions: api/quotation_action.php (finalise, revise, reject, cancel),
 * api/quotation_accept.php, api/quotation_convert.php. PDF: api/quotation_pdf.php.
 * All URLs use the numeric id: quote numbers contain "/" and never appear in a URL or path.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/inv_helpers.php';
require_once __DIR__ . '/../includes/inv_quote.php';

$user = ehs_require_role(INV_ROLES, true);
$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];

if (!inv_table_exists($db, 'inv_quotations')) {
    inv_json_error('Quotations are not installed yet: import db/inv_migration_004.sql first.', 409);
}

const INV_QLIST_PAGE_SIZE = 50;

function inv_api_quote_lines(array $lines): array
{
    return array_map(fn($l) => [
        'id' => (int) $l['id'], 'line_no' => (int) $l['line_no'], 'line_type' => $l['line_type'],
        'service_id' => $l['service_id'] !== null ? (int) $l['service_id'] : null,
        'title' => $l['title'], 'description' => $l['description'],
        'qty' => $l['qty'], 'rate' => $l['rate'], 'special_rate' => $l['special_rate'],
        'amount' => $l['amount'], 'special_amount' => $l['special_amount'],
        'cycle_year' => $l['cycle_year'] !== null ? (int) $l['cycle_year'] : null,
        'invoiced_invoice_id' => $l['invoiced_invoice_id'] !== null ? (int) $l['invoiced_invoice_id'] : null,
        'entities' => array_map(fn($e) => [
            'company_name' => $e['company_name'], 'uen' => $e['uen'],
            'source_client_id' => $e['source_client_id'] !== null ? (int) $e['source_client_id'] : null,
        ], $l['entities']),
    ], $lines);
}

function inv_api_quote_payload(PDO $db, int $id): ?array
{
    $q = inv_fetch_quote($db, $id);
    if (!$q) return null;
    $lines = inv_fetch_quote_lines($db, $id);
    $hash = inv_row_hash($q);
    $q['entity_snapshot'] = null; // internal; not needed by the screens
    $out = [
        'quotation' => $q,
        'row_hash' => $hash,
        'display_status' => inv_quote_display_status($q),
        'lines' => inv_api_quote_lines($lines),
        'today' => inv_today(),
    ];
    // Invoices created from this quotation (inv_ tables only).
    $inv = $db->prepare('SELECT id, invoice_no, status, invoice_date, total FROM inv_invoices WHERE quotation_id = :i_q ORDER BY id');
    $inv->execute(['i_q' => $id]);
    $out['invoices'] = $inv->fetchAll();
    // Revisions sharing the same base number (or the draft chain).
    if ($q['base_no'] !== null) {
        $rv = $db->prepare('SELECT id, quote_no, revision_no, status, quote_date, valid_until FROM inv_quotations
                            WHERE entity_code = :r_entity AND base_no = :r_base ORDER BY revision_no, id');
        $rv->execute(['r_entity' => $q['entity_code'], 'r_base' => $q['base_no']]);
        $out['revisions'] = array_map(function ($r) {
            $r['display_status'] = inv_quote_display_status($r);
            return $r;
        }, $rv->fetchAll());
    } else {
        $out['revisions'] = [];
    }
    $out['signed_copy'] = $q['signed_file'] !== null;
    if ($q['status'] === 'draft') {
        $pre = inv_quote_precheck($db, $q);
        $out['finalise_check'] = ['problems' => $pre['problems'], 'next_number' => $pre['next_number']];
        $out['warnings'] = $pre['built']['warnings'];
        $out['qty_auto'] = array_map(fn($l) => $l['qty_auto'], $pre['built']['lines']);
        $out['computed'] = array_map(fn($l) => ['amount' => inv_cents_to_decimal($l['amount_c']),
            'special_amount' => inv_cents_to_decimal($l['special_amount_c'])], $pre['built']['lines']);
    }
    return $out;
}

// ---------------------------------------------------------------------------
// GET
// ---------------------------------------------------------------------------
if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $id = inv_int_or_null($_GET['id'], 1, 4294967295);
        if (!$id) inv_json_error('A valid quotation id is required.', 422);
        $p = inv_api_quote_payload($db, $id);
        if (!$p) inv_json_error('Quotation not found.', 404);
        inv_json_response($p);
    }

    $where = [];
    $params = [];
    $today = inv_today();
    $status = $_GET['status'] ?? 'all';
    if (in_array($status, INV_QUOTE_STATUSES, true)) {
        $where[] = 'q.status = ?';
        $params[] = $status;
        if ($status === 'sent') {
            $where[] = 'q.valid_until >= ?';
            $params[] = $today;
        }
    } elseif ($status === 'expired') {
        $where[] = "q.status = 'sent' AND q.valid_until < ?";
        $params[] = $today;
    } elseif ($status === 'open') {
        $where[] = "q.status = 'sent' AND q.valid_until >= ?";
        $params[] = $today;
    } elseif ($status !== 'all') {
        inv_json_error('Unknown status filter.', 422);
    }
    $entity = inv_clean_str($_GET['entity'] ?? null, 20);
    if ($entity !== null) {
        if (!inv_entity_exists($db, $entity)) inv_json_error('Unknown entity filter.', 422);
        $where[] = 'q.entity_code = ?';
        $params[] = $entity;
    }
    $search = inv_clean_str($_GET['q'] ?? null, 100);
    if ($search !== null) {
        $like = '%' . inv_like_escape($search) . '%';
        $where[] = '(q.quote_no LIKE ? OR q.bill_to_name LIKE ? OR p.display_name LIKE ? OR q.subject LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }
    foreach (['date_from' => '>=', 'date_to' => '<='] as $k => $op) {
        if (!empty($_GET[$k])) {
            if (!inv_is_valid_ymd($_GET[$k])) inv_json_error('Invalid date filter.', 422);
            $where[] = "q.quote_date $op ?";
            $params[] = $_GET[$k];
        }
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $from = "FROM inv_quotations q JOIN inv_parties p ON p.id = q.party_id $whereSql";

    $page = inv_int_or_null($_GET['page'] ?? 1, 1, 100000) ?: 1;
    $offset = ($page - 1) * INV_QLIST_PAGE_SIZE;
    $count = $db->prepare("SELECT COUNT(*) $from");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $stmt = $db->prepare(
        "SELECT q.id, q.entity_code, q.quote_no, q.status, q.quote_date, q.valid_until, q.total_list, q.total_special,
                q.subject, q.revision_no, COALESCE(q.bill_to_name, p.display_name) AS party_name,
                (SELECT COUNT(*) FROM inv_quotation_lines l WHERE l.quotation_id = q.id AND l.line_type = 'item') AS item_n,
                (SELECT COUNT(*) FROM inv_quotation_lines l WHERE l.quotation_id = q.id AND l.invoiced_invoice_id IS NOT NULL) AS invoiced_n
         $from
         ORDER BY (q.status = 'draft') DESC, q.quote_date DESC, q.id DESC
         LIMIT " . INV_QLIST_PAGE_SIZE . ' OFFSET ' . (int) $offset
    );
    $stmt->execute($params);
    $rows = array_map(function ($r) use ($today) {
        $r['display_status'] = inv_quote_display_status($r, $today);
        return $r;
    }, $stmt->fetchAll());

    inv_json_response(['quotations' => $rows, 'total' => $total, 'page' => $page, 'page_size' => INV_QLIST_PAGE_SIZE,
                       'metrics' => inv_quote_metrics($db), 'today' => $today]);
}

// ---------------------------------------------------------------------------
// POST: create / duplicate
// ---------------------------------------------------------------------------
if ($method === 'POST') {
    ehs_verify_csrf();
    $in = inv_json_input();
    $action = $in['action'] ?? 'create';

    if ($action === 'duplicate') {
        $srcId = inv_int_or_null($in['id'] ?? null, 1, 4294967295);
        $src = $srcId ? inv_fetch_quote($db, $srcId) : null;
        if (!$src) inv_json_error('Quotation not found.', 404);
        $copy = inv_quote_row_to_input($db, $src);
        $copy['quote_date'] = inv_today();
        unset($copy['valid_days']); // take the entity's current default
        $built = inv_build_quote($db, $copy, null);
        if ($built['errors']) {
            inv_json_error('The copy needs attention: ' . implode(' ', array_values($built['errors'])), 422, ['fields' => $built['errors']]);
        }
        $db->beginTransaction();
        $newId = inv_save_quote($db, null, $built['header'], $built['lines'], $user);
        inv_log_activity((int) $user['id'], 'duplicate_quotation', 'inv_quotation', $newId,
            'Draft copied from ' . ($src['quote_no'] ?? ('draft #' . $src['id'])));
        $db->commit();
        inv_json_response(inv_api_quote_payload($db, $newId), 201);
    }
    if ($action !== 'create') inv_json_error('Unknown action.', 422);

    $built = inv_build_quote($db, $in, null);
    if ($built['errors']) inv_json_error('Please correct the highlighted fields.', 422, ['fields' => $built['errors']]);
    $db->beginTransaction();
    $newId = inv_save_quote($db, null, $built['header'], $built['lines'], $user);
    inv_log_activity((int) $user['id'], 'create_quotation_draft', 'inv_quotation', $newId,
        $built['header']['entity_code'] . ' draft for ' . $built['header']['bill_to_name'] . ', special total ' . inv_cents_to_decimal($built['header']['total_special_c']));
    $db->commit();
    inv_json_response(inv_api_quote_payload($db, $newId), 201);
}

// ---------------------------------------------------------------------------
// PUT: save draft
// ---------------------------------------------------------------------------
if ($method === 'PUT') {
    ehs_verify_csrf();
    $in = inv_json_input();
    $id = inv_int_or_null($in['id'] ?? null, 1, 4294967295);
    if (!$id) inv_json_error('A valid quotation id is required.', 422);
    $db->beginTransaction();
    $q = inv_fetch_quote($db, $id, true);
    if (!$q) inv_json_error('Quotation not found.', 404);
    if ($q['status'] !== 'draft') inv_json_error('A sent quotation is locked. Use "Revise" to create the next revision.', 409);
    if (!hash_equals(inv_row_hash($q), (string) ($in['row_hash'] ?? ''))) {
        inv_json_error('This draft was changed by someone else since you opened it. Reload and try again.', 409);
    }
    if ($q['revision_of_id'] !== null && isset($in['entity_code']) && $in['entity_code'] !== $q['entity_code']) {
        inv_json_error('A revision keeps the issuing entity of the quotation it revises.', 422, ['fields' => ['entity_code' => 'Fixed for a revision.']]);
    }
    $built = inv_build_quote($db, $in, $q);
    if ($built['errors']) inv_json_error('Please correct the highlighted fields.', 422, ['fields' => $built['errors']]);
    inv_save_quote($db, $id, $built['header'], $built['lines'], $user);
    inv_log_activity((int) $user['id'], 'update_quotation_draft', 'inv_quotation', $id,
        'Draft saved, special total ' . inv_cents_to_decimal($built['header']['total_special_c']) . ', ' . count($built['lines']) . ' line(s)');
    $db->commit();
    inv_json_response(inv_api_quote_payload($db, $id));
}

// ---------------------------------------------------------------------------
// DELETE: drafts only
// ---------------------------------------------------------------------------
if ($method === 'DELETE') {
    ehs_verify_csrf();
    $in = inv_json_input();
    $id = inv_int_or_null($in['id'] ?? null, 1, 4294967295);
    if (!$id) inv_json_error('A valid quotation id is required.', 422);
    $db->beginTransaction();
    $q = inv_fetch_quote($db, $id, true);
    if (!$q) inv_json_error('Quotation not found.', 404);
    if ($q['status'] !== 'draft') inv_json_error('Only drafts can be deleted. Sent quotations are kept permanently.', 409);
    if (!hash_equals(inv_row_hash($q), (string) ($in['row_hash'] ?? ''))) {
        inv_json_error('This draft was changed by someone else since you opened it. Reload and try again.', 409);
    }
    $del = $db->prepare('DELETE FROM inv_quotations WHERE id = :d_id AND status = \'draft\'');
    $del->execute(['d_id' => $id]);
    inv_log_activity((int) $user['id'], 'delete_quotation_draft', 'inv_quotation', $id,
        'Draft deleted: ' . ($q['bill_to_name'] ?? '') . ($q['revision_of_id'] !== null ? ' (revision draft of #' . $q['revision_of_id'] . ')' : ''));
    $db->commit();
    inv_json_response(['ok' => true]);
}

inv_json_error('Method not allowed.', 405);
