<?php
/**
 * invoice-system/api/invoices.php
 *
 * GET  ?id=N                -> one invoice: row, lines (+ audited entities),
 *                              ledger (paid / credited / balance / overdue),
 *                              and for drafts the live issue pre-check
 *                              (problems + next number) and warnings.
 * GET  ?status=&entity=&q=&date_from=&date_to=&page=
 *                           -> list (50 per page) + totals for the same filter.
 *                              status: draft|issued|partially_paid|paid|credited|void|open|overdue|all
 * POST {action:'create', ...draft}      -> new draft (bill-to prefilled from the party)
 * POST {action:'duplicate', id}         -> new draft copied from any invoice
 * PUT  {id, row_hash, ...draft}         -> save a draft (409 if changed meanwhile)
 * DELETE {id, row_hash}                 -> delete a draft (never an issued invoice)
 *
 * Issue: api/invoice_issue.php. PDF: api/invoice_pdf.php.
 * "Today" and "overdue" use the PHP (app) timezone, passed as a parameter,
 * never the database server clock.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/inv_helpers.php';
require_once __DIR__ . '/../includes/inv_invoice.php';

$user = ehs_require_role(INV_ROLES, true);
$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];

const INV_LIST_PAGE_SIZE = 50;

/** Shape a lines array for JSON (decimal strings + entities). */
function inv_api_lines(array $lines): array
{
    return array_map(fn($l) => [
        'id' => (int) $l['id'], 'line_no' => (int) $l['line_no'],
        'service_id' => $l['service_id'] !== null ? (int) $l['service_id'] : null,
        'title' => $l['title'], 'description' => $l['description'],
        'qty' => $l['qty'], 'rate' => $l['rate'], 'amount' => $l['amount'],
        'entities' => array_map(fn($e) => [
            'company_name' => $e['company_name'], 'uen' => $e['uen'],
            'source_client_id' => $e['source_client_id'] !== null ? (int) $e['source_client_id'] : null,
        ], $l['entities']),
    ], $lines);
}

function inv_api_invoice_payload(PDO $db, int $id): ?array
{
    $inv = inv_fetch_invoice($db, $id);
    if (!$inv) return null;
    $lines = inv_fetch_lines($db, $id);
    $led = inv_invoice_ledger($db, $id);
    $total = inv_decimal_to_cents($inv['total']);
    $balance = in_array($inv['status'], ['draft', 'void'], true) ? 0 : $total - $led['paid_c'] - $led['credited_c'];
    $overdue = in_array($inv['status'], INV_OPEN_STATUSES, true) && $inv['due_date'] !== null && $inv['due_date'] < inv_today();

    $out = [
        'invoice'   => $inv,
        'row_hash'  => inv_row_hash($inv),
        'lines'     => inv_api_lines($lines),
        'ledger'    => [
            'paid' => inv_cents_to_decimal($led['paid_c']), 'credited' => inv_cents_to_decimal($led['credited_c']),
            'balance' => inv_cents_to_decimal(max(0, $balance)), 'overdue' => $overdue,
            'days_overdue' => $overdue ? (int) ((strtotime(inv_today()) - strtotime($inv['due_date'])) / 86400) : 0,
        ],
        'amount_in_words' => $inv['amount_in_words'] ?? inv_amount_in_words($total),
    ];
    if ($inv['status'] === 'draft') {
        $pre = inv_issue_precheck($db, $inv);
        $out['issue_check'] = ['problems' => $pre['problems'], 'next_number' => $pre['next_number']];
        $out['warnings'] = $pre['built']['warnings'];
        $out['qty_auto'] = array_map(fn($l) => $l['qty_auto'], $pre['built']['lines']);
    }
    return $out;
}

// ---------------------------------------------------------------------------
// GET
// ---------------------------------------------------------------------------
if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $id = inv_int_or_null($_GET['id'], 1, 4294967295);
        if (!$id) inv_json_error('A valid invoice id is required.', 422);
        $p = inv_api_invoice_payload($db, $id);
        if (!$p) inv_json_error('Invoice not found.', 404);
        inv_json_response($p);
    }

    $where = [];
    $params = [];
    $today = inv_today();

    $status = $_GET['status'] ?? 'all';
    if (in_array($status, INV_STATUSES, true)) {
        $where[] = 'i.status = ?';
        $params[] = $status;
    } elseif ($status === 'open') {
        $where[] = "i.status IN ('issued','partially_paid')";
    } elseif ($status === 'overdue') {
        $where[] = "i.status IN ('issued','partially_paid') AND i.due_date < ?";
        $params[] = $today;
    }

    $entity = inv_clean_str($_GET['entity'] ?? null, 20);
    if ($entity !== null) {
        if (!inv_entity_exists($db, $entity)) inv_json_error('Unknown entity filter.', 422);
        $where[] = 'i.entity_code = ?';
        $params[] = $entity;
    }
    $q = inv_clean_str($_GET['q'] ?? null, 100);
    if ($q !== null) {
        $like = '%' . inv_like_escape($q) . '%';
        $where[] = '(i.invoice_no LIKE ? OR i.bill_to_name LIKE ? OR p.display_name LIKE ? OR i.customer_ref LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }
    foreach (['date_from' => '>=', 'date_to' => '<='] as $k => $op) {
        if (!empty($_GET[$k])) {
            if (!inv_is_valid_ymd($_GET[$k])) inv_json_error('Invalid date filter.', 422);
            $where[] = "i.invoice_date $op ?";
            $params[] = $_GET[$k];
        }
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // inv_ tables only: no cross-module join, so no collation mixing.
    $from = "FROM inv_invoices i
             JOIN inv_parties p ON p.id = i.party_id
             LEFT JOIN (SELECT invoice_id, SUM(amount) AS paid FROM inv_payments WHERE reversed_at IS NULL GROUP BY invoice_id) pay
                    ON pay.invoice_id = i.id
             LEFT JOIN (SELECT invoice_id, SUM(total) AS credited FROM inv_credit_notes WHERE status = 'issued' GROUP BY invoice_id) cn
                    ON cn.invoice_id = i.id
             $whereSql";

    $page = inv_int_or_null($_GET['page'] ?? 1, 1, 100000) ?: 1;
    $offset = ($page - 1) * INV_LIST_PAGE_SIZE;

    $count = $db->prepare("SELECT COUNT(*) $from");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $stmt = $db->prepare(
        "SELECT i.id, i.entity_code, i.invoice_no, i.status, i.invoice_date, i.due_date, i.total, i.category_name,
                i.category_id, i.customer_ref, COALESCE(i.bill_to_name, p.display_name) AS party_name,
                COALESCE(pay.paid, 0) AS paid, COALESCE(cn.credited, 0) AS credited
         $from
         ORDER BY (i.status = 'draft') DESC, i.invoice_date DESC, i.id DESC
         LIMIT " . INV_LIST_PAGE_SIZE . ' OFFSET ' . (int) $offset
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $cats = [];
    foreach ($db->query('SELECT id, name FROM inv_categories')->fetchAll() as $c) $cats[(int) $c['id']] = $c['name'];
    foreach ($rows as &$r) {
        $t = inv_decimal_to_cents($r['total']);
        $bal = in_array($r['status'], ['draft', 'void'], true) ? 0 : max(0, $t - inv_decimal_to_cents($r['paid']) - inv_decimal_to_cents($r['credited']));
        $r['balance'] = inv_cents_to_decimal($bal);
        $r['overdue'] = in_array($r['status'], INV_OPEN_STATUSES, true) && $r['due_date'] < $today;
        if ($r['category_name'] === null && $r['category_id'] !== null) $r['category_name'] = $cats[(int) $r['category_id']] ?? null;
        unset($r['category_id']);
    }
    unset($r);

    // Totals for the same filter (KPIs agreed: credited invoices are not outstanding).
    $sum = $db->prepare(
        "SELECT i.status,
                COUNT(*) AS n,
                COALESCE(SUM(i.total), 0) AS total,
                COALESCE(SUM(CASE WHEN i.status IN ('issued','partially_paid')
                                  THEN i.total - COALESCE(pay.paid, 0) - COALESCE(cn.credited, 0) ELSE 0 END), 0) AS outstanding,
                COALESCE(SUM(CASE WHEN i.status IN ('issued','partially_paid') AND i.due_date < ? THEN 1 ELSE 0 END), 0) AS overdue_n,
                COALESCE(SUM(CASE WHEN i.status IN ('issued','partially_paid') AND i.due_date < ?
                                  THEN i.total - COALESCE(pay.paid, 0) - COALESCE(cn.credited, 0) ELSE 0 END), 0) AS overdue_amt
         $from
         GROUP BY i.status"
    );
    $sum->execute(array_merge([$today, $today], $params));
    $byStatus = [];
    $outstanding = 0;
    $overdueN = 0;
    $overdueAmt = 0;
    foreach ($sum->fetchAll() as $s) {
        $byStatus[$s['status']] = ['count' => (int) $s['n'], 'total' => $s['total']];
        $outstanding += inv_decimal_to_cents($s['outstanding']);
        $overdueN += (int) $s['overdue_n'];
        $overdueAmt += inv_decimal_to_cents($s['overdue_amt']);
    }

    inv_json_response([
        'invoices' => $rows, 'total' => $total, 'page' => $page, 'page_size' => INV_LIST_PAGE_SIZE,
        'summary' => [
            'by_status' => $byStatus,
            'outstanding' => inv_cents_to_decimal($outstanding),
            'overdue_count' => $overdueN,
            'overdue_amount' => inv_cents_to_decimal($overdueAmt),
        ],
        'today' => $today,
    ]);
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
        $src = $srcId ? inv_fetch_invoice($db, $srcId) : null;
        if (!$src) inv_json_error('Invoice not found.', 404);
        $copy = inv_draft_row_to_input($db, $src);
        $copy['invoice_date'] = inv_today();
        $copy['customer_ref'] = null;
        $built = inv_build_draft($db, $copy, null);
        if ($built['errors']) {
            inv_json_error('The copy needs attention: ' . implode(' ', array_values($built['errors'])), 422, ['fields' => $built['errors']]);
        }
        $db->beginTransaction();
        $newId = inv_save_draft($db, null, $built['header'], $built['lines'], $user);
        inv_log_activity((int) $user['id'], 'duplicate_invoice', 'inv_invoice', $newId,
            'Draft copied from ' . ($src['invoice_no'] ?? ('draft #' . $src['id'])));
        $db->commit();
        inv_json_response(inv_api_invoice_payload($db, $newId), 201);
    }

    if ($action !== 'create') inv_json_error('Unknown action.', 422);

    $built = inv_build_draft($db, $in, null);
    if ($built['errors']) {
        inv_json_error('Please correct the highlighted fields.', 422, ['fields' => $built['errors']]);
    }
    $db->beginTransaction();
    $newId = inv_save_draft($db, null, $built['header'], $built['lines'], $user);
    inv_log_activity((int) $user['id'], 'create_invoice_draft', 'inv_invoice', $newId,
        $built['header']['entity_code'] . ' draft for ' . $built['header']['bill_to_name'] . ', total ' . inv_cents_to_decimal($built['header']['total_c']));
    $db->commit();
    inv_json_response(inv_api_invoice_payload($db, $newId), 201);
}

// ---------------------------------------------------------------------------
// PUT: save draft
// ---------------------------------------------------------------------------
if ($method === 'PUT') {
    ehs_verify_csrf();
    $in = inv_json_input();
    $id = inv_int_or_null($in['id'] ?? null, 1, 4294967295);
    if (!$id) inv_json_error('A valid invoice id is required.', 422);

    $db->beginTransaction();
    $inv = inv_fetch_invoice($db, $id, true);
    if (!$inv) inv_json_error('Invoice not found.', 404);
    if ($inv['status'] !== 'draft') inv_json_error('Issued invoices cannot be edited. Use a credit note or void (Stage 4).', 409);
    if (!hash_equals(inv_row_hash($inv), (string) ($in['row_hash'] ?? ''))) {
        inv_json_error('This draft was changed by someone else since you opened it. Reload and try again.', 409);
    }
    $built = inv_build_draft($db, $in, $inv);
    if ($built['errors']) {
        inv_json_error('Please correct the highlighted fields.', 422, ['fields' => $built['errors']]);
    }
    inv_save_draft($db, $id, $built['header'], $built['lines'], $user);
    inv_log_activity((int) $user['id'], 'update_invoice_draft', 'inv_invoice', $id,
        'Draft saved, total ' . inv_cents_to_decimal($built['header']['total_c']) . ', ' . count($built['lines']) . ' line(s)');
    $db->commit();
    inv_json_response(inv_api_invoice_payload($db, $id));
}

// ---------------------------------------------------------------------------
// DELETE: drafts only
// ---------------------------------------------------------------------------
if ($method === 'DELETE') {
    ehs_verify_csrf();
    $in = inv_json_input();
    $id = inv_int_or_null($in['id'] ?? null, 1, 4294967295);
    if (!$id) inv_json_error('A valid invoice id is required.', 422);

    $db->beginTransaction();
    $inv = inv_fetch_invoice($db, $id, true);
    if (!$inv) inv_json_error('Invoice not found.', 404);
    if ($inv['status'] !== 'draft') inv_json_error('Only drafts can be deleted. Issued invoices are kept permanently.', 409);
    if (!hash_equals(inv_row_hash($inv), (string) ($in['row_hash'] ?? ''))) {
        inv_json_error('This draft was changed by someone else since you opened it. Reload and try again.', 409);
    }
    $del = $db->prepare('DELETE FROM inv_invoices WHERE id = :d_id AND status = \'draft\'');
    $del->execute(['d_id' => $id]);
    inv_log_activity((int) $user['id'], 'delete_invoice_draft', 'inv_invoice', $id,
        'Draft deleted: ' . ($inv['bill_to_name'] ?? '') . ', total ' . $inv['total']);
    $db->commit();
    inv_json_response(['ok' => true]);
}

inv_json_error('Method not allowed.', 405);
