<?php
/**
 * invoice-system/includes/inv_invoice.php — invoices: drafts, issue, status.
 *
 * Rules enforced HERE (in PHP) as well as by the database where it can:
 *   - money in integer cents; line amount = ROUND(qty x rate, 2); GST on the
 *     subtotal; total = subtotal + GST            (DB: CHECK constraints)
 *   - drafts have no number; issued documents are never edited
 *   - invoice date: valid, not in the future, not before 2020
 *   - issue needs: entity ready, series confirmed, total > 0, >= 1 line,
 *     category, bill-to name + address, active party, no «TBC» in content
 *   - status is RECOMPUTED (never set by the UI) inside the same
 *     transaction as every change, after SELECT ... FOR UPDATE on the row
 * All SQL touches inv_ tables only (no joins to users / cm_* / crm_*), so no
 * cross-collation comparison can occur. Named placeholders are never reused
 * within a query.
 */

require_once __DIR__ . '/inv_money.php';
require_once __DIR__ . '/inv_storage.php';
require_once __DIR__ . '/inv_pdf.php';

const INV_STATUSES = ['draft', 'issued', 'partially_paid', 'paid', 'void', 'credited'];
const INV_OPEN_STATUSES = ['issued', 'partially_paid'];
const INV_STATUS_LABELS = [
    'draft' => 'Draft', 'issued' => 'Issued', 'partially_paid' => 'Partially paid',
    'paid' => 'Paid', 'void' => 'Void', 'credited' => 'Credited',
];
const INV_MAX_LINES = 50;
const INV_MAX_ENTITIES_PER_LINE = 200;
const INV_DRAFT_WATERMARK = 'DRAFT, NOT FOR ISSUE';
const INV_MIN_DATE = '2020-01-01';

/** Today in the APP timezone (PHP), never the DB server clock. */
function inv_today(): string
{
    return date('Y-m-d');
}

function inv_add_days(string $ymd, int $days): string
{
    $d = DateTime::createFromFormat('!Y-m-d', $ymd);
    $d->modify('+' . $days . ' days');
    return $d->format('Y-m-d');
}

function inv_is_valid_ymd($s): bool
{
    if (!is_string($s) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return false;
    $d = DateTime::createFromFormat('!Y-m-d', $s);
    return $d !== false && $d->format('Y-m-d') === $s;
}

/** Exact placeholder marker check for invoice CONTENT (plain "TBC" may be a real name). */
function inv_has_tbc_marker(?string $s): bool
{
    return $s !== null && strpos($s, '«TBC»') !== false;
}

function inv_fetch_invoice(PDO $db, int $id, bool $forUpdate = false): ?array
{
    $stmt = $db->prepare('SELECT * FROM inv_invoices WHERE id = :f_id' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute(['f_id' => $id]);
    $r = $stmt->fetch();
    return $r ?: null;
}

/** Lines with their audited entities, in order. */
function inv_fetch_lines(PDO $db, int $invoiceId): array
{
    $stmt = $db->prepare('SELECT * FROM inv_invoice_lines WHERE invoice_id = :l_inv ORDER BY line_no ASC, id ASC');
    $stmt->execute(['l_inv' => $invoiceId]);
    $lines = $stmt->fetchAll();
    if (!$lines) return [];
    $ids = array_map(fn($l) => (int) $l['id'], $lines);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $es = $db->prepare("SELECT * FROM inv_line_audited_entities WHERE line_id IN ($ph) ORDER BY line_id, seq");
    $es->execute($ids);
    $byLine = [];
    foreach ($es->fetchAll() as $e) {
        $byLine[(int) $e['line_id']][] = $e;
    }
    foreach ($lines as &$l) {
        $l['entities'] = $byLine[(int) $l['id']] ?? [];
    }
    return $lines;
}

function inv_fetch_party_row(PDO $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT * FROM inv_parties WHERE id = :p_id');
    $stmt->execute(['p_id' => $id]);
    $r = $stmt->fetch();
    return $r ?: null;
}

function inv_fetch_category(PDO $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT * FROM inv_categories WHERE id = :c_id');
    $stmt->execute(['c_id' => $id]);
    $r = $stmt->fetch();
    return $r ?: null;
}

/** GST rate (hundredths of a percent) an entity charges right now. */
function inv_entity_gst_rate_h(array $settings): int
{
    if ((int) $settings['gst_status_confirmed'] === 1 && (int) $settings['gst_registered'] === 1) {
        return inv_parse_cents((string) $settings['gst_rate']) ?? 0;
    }
    return 0;
}

/**
 * Validate and normalise a draft from input. Never trusts client totals:
 * every amount is recomputed here.
 * $existing: the current draft row when updating (null when creating).
 * Returns ['header' => [...], 'lines' => [...], 'errors' => [field => msg], 'warnings' => [msg]].
 */
function inv_build_draft(PDO $db, array $in, ?array $existing): array
{
    $errors = [];
    $warnings = [];
    $h = [];

    // --- entity ---------------------------------------------------------
    $entities = inv_get_entities($db, true);
    $code = inv_clean_str($in['entity_code'] ?? ($existing['entity_code'] ?? null), 20);
    if ($code === null || !isset($entities[$code])) {
        $errors['entity_code'] = 'Choose the issuing entity.';
        $settings = null;
    } else {
        $settings = $entities[$code];
        if ((int) $settings['is_active'] !== 1) $errors['entity_code'] = 'This issuing entity is deactivated.';
    }
    $h['entity_code'] = $code;

    // --- party ------------------------------------------------------------
    $partyId = inv_int_or_null($in['party_id'] ?? null, 1, 4294967295);
    $party = $partyId ? inv_fetch_party_row($db, $partyId) : null;
    if (!$party) {
        $errors['party_id'] = 'Choose the billed party.';
    } elseif ((int) $party['is_active'] !== 1 && (!$existing || (int) $existing['party_id'] !== (int) $party['id'])) {
        $errors['party_id'] = 'This party is deactivated. Reactivate it first.';
    }
    $h['party_id'] = $party ? (int) $party['id'] : null;

    // --- category -----------------------------------------------------------
    $catId = inv_int_or_null($in['category_id'] ?? null, 1, 4294967295);
    if ($catId === false) {
        $errors['category_id'] = 'Invalid category.';
        $catId = null;
    } elseif ($catId !== null) {
        $cat = inv_fetch_category($db, $catId);
        if (!$cat) {
            $errors['category_id'] = 'Unknown category.';
        } elseif ((int) $cat['is_active'] !== 1 && (!$existing || (int) $existing['category_id'] !== $catId)) {
            $errors['category_id'] = 'This category is deactivated.';
        }
    }
    $h['category_id'] = $catId;

    // --- dates and terms --------------------------------------------------
    $date = $in['invoice_date'] ?? inv_today();
    if (!inv_is_valid_ymd($date)) {
        $errors['invoice_date'] = 'Enter a valid invoice date.';
        $date = inv_today();
    } elseif ($date > inv_today()) {
        $errors['invoice_date'] = 'Future invoice dates are not allowed.';
    } elseif ($date < INV_MIN_DATE) {
        $errors['invoice_date'] = 'Invoice date is too far in the past.';
    } elseif ($date < inv_today()) {
        $warnings[] = 'Backdated: the invoice date is before today.';
    }
    $h['invoice_date'] = $date;

    $defaultTerms = $party && $party['payment_terms_days'] !== null
        ? (int) $party['payment_terms_days']
        : ($settings ? (int) $settings['default_terms_days'] : 30);
    $terms = array_key_exists('payment_terms_days', $in) && $in['payment_terms_days'] !== '' && $in['payment_terms_days'] !== null
        ? inv_int_or_null($in['payment_terms_days'], 0, 365) : $defaultTerms;
    if ($terms === false || $terms === null) {
        $errors['payment_terms_days'] = 'Payment terms must be 0 to 365 days.';
        $terms = $defaultTerms;
    }
    $h['payment_terms_days'] = $terms;
    $h['due_date'] = inv_add_days($date, $terms);

    // --- bill-to (prefilled from the party when not supplied) ---------------
    $fromParty = [
        'bill_to_name'      => $party['display_name'] ?? null,
        'bill_to_address'   => $party['billing_address'] ?? null,
        'bill_to_attention' => $party['attention'] ?? null,
        'bill_to_email'     => $party['accounts_email'] ?? null,
    ];
    $limits = ['bill_to_name' => 200, 'bill_to_address' => 1000, 'bill_to_attention' => 150, 'bill_to_email' => 150];
    foreach ($limits as $f => $max) {
        $h[$f] = array_key_exists($f, $in) ? inv_clean_str($in[$f], $max, $f === 'bill_to_address') : $fromParty[$f];
    }
    if ($h['bill_to_email'] !== null && !inv_is_valid_email($h['bill_to_email'])) {
        $errors['bill_to_email'] = 'Not a valid email address.';
    }
    if ($h['bill_to_address'] === null) {
        $warnings[] = 'No billing address: required before issuing.';
    }
    $h['customer_ref'] = inv_clean_str($in['customer_ref'] ?? null, 100);
    $h['internal_notes'] = inv_clean_str($in['internal_notes'] ?? null, 2000, true);

    // --- lines --------------------------------------------------------------
    $rawLines = $in['lines'] ?? [];
    if (!is_array($rawLines)) $rawLines = [];
    if (count($rawLines) > INV_MAX_LINES) {
        $errors['lines'] = 'At most ' . INV_MAX_LINES . ' lines per invoice.';
        $rawLines = array_slice($rawLines, 0, INV_MAX_LINES);
    }
    $services = [];
    foreach ($db->query('SELECT * FROM inv_services')->fetchAll() as $s) {
        $services[(int) $s['id']] = $s;
    }

    $lines = [];
    $subtotal = 0;
    foreach (array_values($rawLines) as $idx => $rl) {
        $n = $idx + 1;
        $key = 'lines.' . $idx;
        if (!is_array($rl)) {
            $errors[$key] = "Line $n is invalid.";
            continue;
        }
        $sid = inv_int_or_null($rl['service_id'] ?? null, 1, 4294967295);
        $svc = null;
        if ($sid === false) {
            $errors[$key . '.service_id'] = "Line $n: invalid service.";
            $sid = null;
        } elseif ($sid !== null) {
            $svc = $services[$sid] ?? null;
            if (!$svc) {
                $errors[$key . '.service_id'] = "Line $n: unknown service.";
                $sid = null;
            } elseif ($svc['entity_code'] !== null && $code !== null && $svc['entity_code'] !== $code) {
                $errors[$key . '.service_id'] = "Line $n: this service belongs to " . $svc['entity_code'] . '.';
            }
        }
        $title = inv_clean_str($rl['title'] ?? null, 255);
        if ($title === null) $errors[$key . '.title'] = "Line $n: title is required.";
        $desc = inv_clean_str($rl['description'] ?? null, 4000, true);

        $ents = [];
        $rawEnts = is_array($rl['entities'] ?? null) ? $rl['entities'] : [];
        if (count($rawEnts) > INV_MAX_ENTITIES_PER_LINE) {
            $errors[$key . '.entities'] = "Line $n: at most " . INV_MAX_ENTITIES_PER_LINE . ' audited entities.';
            $rawEnts = array_slice($rawEnts, 0, INV_MAX_ENTITIES_PER_LINE);
        }
        foreach ($rawEnts as $re) {
            $name = inv_clean_str(is_array($re) ? ($re['company_name'] ?? null) : $re, 200);
            if ($name === null) continue;
            $src = is_array($re) ? inv_int_or_null($re['source_client_id'] ?? null, 1, 4294967295) : null;
            $ents[] = [
                'company_name'     => $name,
                'uen'              => is_array($re) ? inv_clean_str($re['uen'] ?? null, 50) : null,
                'source_client_id' => $src ?: null,
            ];
        }

        $rateC = inv_parse_cents($rl['rate'] ?? '');
        if ($rateC === null || $rateC > INV_MAX_RATE_CENTS) {
            $errors[$key . '.rate'] = "Line $n: rate must be 0 to 9,999,999.99.";
            $rateC = 0;
        }
        $qtyAuto = $svc !== null && (int) $svc['qty_from_entities'] === 1 && count($ents) > 0;
        if ($qtyAuto) {
            $qtyH = count($ents) * 100;
        } else {
            $qtyH = inv_parse_qty($rl['qty'] ?? '');
            if ($qtyH === null || $qtyH <= 0 || $qtyH > INV_MAX_QTY_HUNDREDTHS) {
                $errors[$key . '.qty'] = "Line $n: quantity must be more than 0 and at most 9,999.99.";
                $qtyH = 100;
            }
        }
        $amountC = inv_line_amount_cents($qtyH, $rateC);
        if ($amountC > INV_MAX_MONEY_CENTS) {
            $errors[$key . '.rate'] = "Line $n: amount is too large.";
            $amountC = 0;
        }
        $subtotal += $amountC;
        $lines[] = [
            'line_no' => $n, 'service_id' => $sid, 'title' => $title, 'description' => $desc,
            'qty_h' => $qtyH, 'rate_c' => $rateC, 'amount_c' => $amountC, 'qty_auto' => $qtyAuto,
            'entities' => $ents,
        ];
    }

    $gstRateH = $settings ? inv_entity_gst_rate_h($settings) : 0;
    $gstC = inv_gst_cents($subtotal, $gstRateH);
    $totalC = $subtotal + $gstC;
    if ($totalC > INV_MAX_MONEY_CENTS) {
        $errors['lines'] = 'Invoice total is too large.';
    }
    if (!$lines) $warnings[] = 'No lines yet: at least one line is required before issuing.';
    if ($totalC <= 0 && $lines) $warnings[] = 'Total is zero: invoices with a total of 0.00 or less cannot be issued.';

    $h['subtotal_c'] = $subtotal;
    $h['gst_rate_h'] = $gstRateH;
    $h['gst_c'] = $gstC;
    $h['total_c'] = $totalC;

    return ['header' => $h, 'lines' => $lines, 'errors' => $errors, 'warnings' => $warnings];
}

/** Insert or update a draft and replace its lines (caller holds a transaction). Returns the id. */
function inv_save_draft(PDO $db, ?int $id, array $h, array $lines, array $user): int
{
    $params = [
        'entity_code'        => $h['entity_code'],
        'party_id'           => $h['party_id'],
        'category_id'        => $h['category_id'],
        'invoice_date'       => $h['invoice_date'],
        'payment_terms_days' => $h['payment_terms_days'],
        'due_date'           => $h['due_date'],
        'customer_ref'       => $h['customer_ref'],
        'subtotal'           => inv_cents_to_decimal($h['subtotal_c']),
        'gst_rate'           => inv_cents_to_decimal($h['gst_rate_h']),
        'gst_amount'         => inv_cents_to_decimal($h['gst_c']),
        'total'              => inv_cents_to_decimal($h['total_c']),
        'bill_to_name'       => $h['bill_to_name'],
        'bill_to_address'    => $h['bill_to_address'],
        'bill_to_attention'  => $h['bill_to_attention'],
        'bill_to_email'      => $h['bill_to_email'],
        'internal_notes'     => $h['internal_notes'],
    ];
    if ($id === null) {
        $stmt = $db->prepare(
            'INSERT INTO inv_invoices
                (entity_code, status, party_id, category_id, invoice_date, payment_terms_days, due_date, customer_ref,
                 subtotal, gst_rate, gst_amount, total, bill_to_name, bill_to_address, bill_to_attention, bill_to_email,
                 internal_notes, created_by, created_by_name)
             VALUES
                (:entity_code, \'draft\', :party_id, :category_id, :invoice_date, :payment_terms_days, :due_date, :customer_ref,
                 :subtotal, :gst_rate, :gst_amount, :total, :bill_to_name, :bill_to_address, :bill_to_attention, :bill_to_email,
                 :internal_notes, :created_by, :created_by_name)'
        );
        $stmt->execute($params + ['created_by' => (int) $user['id'], 'created_by_name' => $user['name']]);
        $id = (int) $db->lastInsertId();
    } else {
        $stmt = $db->prepare(
            'UPDATE inv_invoices SET
                entity_code = :entity_code, party_id = :party_id, category_id = :category_id,
                invoice_date = :invoice_date, payment_terms_days = :payment_terms_days, due_date = :due_date,
                customer_ref = :customer_ref, subtotal = :subtotal, gst_rate = :gst_rate, gst_amount = :gst_amount,
                total = :total, bill_to_name = :bill_to_name, bill_to_address = :bill_to_address,
                bill_to_attention = :bill_to_attention, bill_to_email = :bill_to_email,
                internal_notes = :internal_notes, row_version = row_version + 1
             WHERE id = :w_id AND status = \'draft\''
        );
        $stmt->execute($params + ['w_id' => $id]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Only drafts can be saved.');
        }
    }
    inv_save_lines_only($db, $id, $lines);
    return $id;
}

/** Convert stored DB lines to the in-memory shape inv_build_draft produces. */
function inv_lines_to_input(array $dbLines): array
{
    $out = [];
    foreach ($dbLines as $l) {
        $out[] = [
            'service_id'  => $l['service_id'] !== null ? (int) $l['service_id'] : null,
            'title'       => $l['title'],
            'description' => $l['description'],
            'qty'         => $l['qty'],
            'rate'        => $l['rate'],
            'entities'    => array_map(fn($e) => [
                'company_name' => $e['company_name'], 'uen' => $e['uen'],
                'source_client_id' => $e['source_client_id'] !== null ? (int) $e['source_client_id'] : null,
            ], $l['entities']),
        ];
    }
    return $out;
}

/** Problems with the module environment that block issuing (storage, PDF library, GD). */
function inv_module_problems(PDO $db): array
{
    $p = [];
    foreach (inv_storage_status($db)['problems'] as $m) {
        $p[] = ['field' => 'storage', 'message' => $m];
    }
    if (!is_file(dirname(__DIR__, 2) . '/vendor/autoload.php')) {
        $p[] = ['field' => 'dompdf', 'message' => 'PDF library (Dompdf) is not installed (vendor/autoload.php missing).'];
    }
    if (!extension_loaded('gd')) {
        $p[] = ['field' => 'gd', 'message' => 'PHP GD extension is missing (needed to place PNG logos in PDFs).'];
    }
    $paper = inv_config_get($db, 'pdf_page_size', null);
    if ($paper === null) {
        $p[] = ['field' => 'migration', 'message' => 'Migration 003 has not been run (inv_migration_003.sql).'];
    }
    return $p;
}

/**
 * Content problems that block issuing a draft (beyond entity readiness).
 * $built is the output of inv_build_draft for the stored draft.
 */
function inv_issue_content_problems(PDO $db, array $inv, array $built): array
{
    $p = [];
    $h = $built['header'];
    foreach ($built['errors'] as $f => $m) {
        $p[] = ['field' => $f, 'message' => $m];
    }
    if (!$built['lines']) $p[] = ['field' => 'lines', 'message' => 'Add at least one line.'];
    if ($h['total_c'] <= 0) $p[] = ['field' => 'total', 'message' => 'Total must be more than 0.00.'];
    if ($h['category_id'] === null) $p[] = ['field' => 'category_id', 'message' => 'Choose a category.'];
    if ($h['bill_to_name'] === null) $p[] = ['field' => 'bill_to_name', 'message' => 'Bill-to name is required.'];
    if ($h['bill_to_address'] === null) $p[] = ['field' => 'bill_to_address', 'message' => 'Billing address is required before issuing.'];
    if ($h['party_id'] !== null) {
        $party = inv_fetch_party_row($db, (int) $h['party_id']);
        if ($party && (int) $party['is_active'] !== 1) {
            $p[] = ['field' => 'party_id', 'message' => 'The billed party is deactivated.'];
        }
    }
    $texts = [$h['bill_to_name'], $h['bill_to_address'], $h['bill_to_attention'], $h['customer_ref']];
    foreach ($built['lines'] as $l) {
        $texts[] = $l['title'];
        $texts[] = $l['description'];
        foreach ($l['entities'] as $e) $texts[] = $e['company_name'];
    }
    foreach ($texts as $t) {
        if (inv_has_tbc_marker($t)) {
            $p[] = ['field' => 'content', 'message' => 'The invoice text still contains a «TBC» placeholder.'];
            break;
        }
    }
    return $p;
}

/** Fields of inv_settings frozen into the issued document. */
function inv_entity_snapshot(array $s, ?string $paper): array
{
    $keys = ['entity_code', 'display_name', 'legal_name', 'co_reg_no', 'address', 'email', 'phone', 'accreditation_line',
        'logo_filename', 'cheque_payee', 'bank_name', 'bank_code', 'bank_branch', 'bank_account_label', 'bank_account_no',
        'bank_swift', 'paynow_uen', 'payment_note', 'gst_registered', 'gst_reg_no', 'gst_rate', 'no_gst_label',
        'tnc_text', 'footer_text'];
    $snap = [];
    foreach ($keys as $k) $snap[$k] = $s[$k] ?? null;
    $logo = inv_logo_path($s['logo_filename'] ?? null);
    $snap['logo_sha256'] = ($logo && is_file($logo)) ? hash_file('sha256', $logo) : null;
    $snap['paper'] = $paper;
    return $snap;
}

/** Build the PDF view model from a built draft + entity settings. */
function inv_doc_model(array $built, array $settings, ?string $number, ?string $categoryName, string $paper): array
{
    $h = $built['header'];
    $lines = [];
    foreach ($built['lines'] as $l) {
        $lines[] = [
            'title' => (string) $l['title'], 'description' => $l['description'],
            'entities' => array_map(fn($e) => $e['company_name'], $l['entities']),
            'qty_h' => $l['qty_h'], 'rate_c' => $l['rate_c'], 'amount_c' => $l['amount_c'],
        ];
    }
    return [
        'number' => $number, 'invoice_date' => $h['invoice_date'], 'due_date' => $h['due_date'],
        'terms_days' => $h['payment_terms_days'], 'category' => $categoryName, 'customer_ref' => $h['customer_ref'],
        'bill_to' => ['name' => (string) $h['bill_to_name'], 'address' => $h['bill_to_address'], 'attention' => $h['bill_to_attention']],
        'entity' => $settings, 'lines' => $lines,
        'subtotal_c' => $h['subtotal_c'], 'gst_c' => $h['gst_c'], 'gst_rate_h' => $h['gst_rate_h'],
        'gst_registered' => (int) ($settings['gst_registered'] ?? 0) === 1 && (int) ($settings['gst_status_confirmed'] ?? 0) === 1 ? 1 : 0,
        'total_c' => $h['total_c'], 'words' => inv_amount_in_words($h['total_c']), 'paper' => $paper,
    ];
}

function inv_paper(PDO $db): string
{
    $p = inv_config_get($db, 'pdf_page_size', 'A4');
    return in_array($p, INV_PDF_PAPERS, true) ? $p : 'A4';
}

/** Ledger totals for one invoice (cents). */
function inv_invoice_ledger(PDO $db, int $id): array
{
    $stmt = $db->prepare(
        'SELECT
            (SELECT COALESCE(SUM(p.amount), 0) FROM inv_payments p WHERE p.invoice_id = :p_inv AND p.reversed_at IS NULL) AS paid,
            (SELECT COALESCE(SUM(c.total), 0) FROM inv_credit_notes c WHERE c.invoice_id = :c_inv AND c.status = \'issued\') AS credited'
    );
    $stmt->execute(['p_inv' => $id, 'c_inv' => $id]);
    $r = $stmt->fetch();
    return ['paid_c' => inv_decimal_to_cents($r['paid']), 'credited_c' => inv_decimal_to_cents($r['credited'])];
}

/**
 * Recompute and store the status of one invoice from its ledger.
 * Caller MUST hold a transaction and a FOR UPDATE lock on the invoice row.
 *   draft / void  -> unchanged
 *   balance < 0   -> exception (overpayment / over-credit; caller rolls back)
 *   balance = 0   -> 'credited' if credit notes cover the whole total and no
 *                    active payments exist, else 'paid'
 *   balance < total -> 'partially_paid';  balance = total -> 'issued'
 */
function inv_recompute_status(PDO $db, int $id): string
{
    $inv = inv_fetch_invoice($db, $id);
    if (!$inv) throw new RuntimeException('Invoice not found.');
    if (in_array($inv['status'], ['draft', 'void'], true)) return $inv['status'];

    $total = inv_decimal_to_cents($inv['total']);
    $led = inv_invoice_ledger($db, $id);
    $balance = $total - $led['paid_c'] - $led['credited_c'];
    if ($balance < 0) {
        throw new RuntimeException('Payments and credit notes would exceed the invoice total.');
    }
    if ($balance === 0) {
        $status = ($led['paid_c'] === 0 && $led['credited_c'] === $total) ? 'credited' : 'paid';
    } elseif ($balance < $total) {
        $status = 'partially_paid';
    } else {
        $status = 'issued';
    }
    if ($status !== $inv['status']) {
        $u = $db->prepare('UPDATE inv_invoices SET status = :s_status WHERE id = :s_id');
        $u->execute(['s_status' => $status, 's_id' => $id]);
    }
    return $status;
}

/** Non-locking pre-check for the editor: everything that would block issuing right now. */
function inv_issue_precheck(PDO $db, array $inv): array
{
    $built = inv_build_draft($db, inv_draft_row_to_input($db, $inv), $inv);
    $year = (int) substr($built['header']['invoice_date'], 0, 4);
    $problems = array_merge(
        inv_entity_readiness($db, (string) $inv['entity_code'], 'invoice', $year),
        inv_module_problems($db),
        inv_issue_content_problems($db, $inv, $built)
    );
    $next = null;
    $settings = inv_get_entities($db)[$inv['entity_code']] ?? null;
    $series = $settings ? inv_get_series($db, (string) $inv['entity_code'], 'invoice', $year) : null;
    if ($series && (int) $series['is_confirmed'] === 1 && $series['next_value'] !== null) {
        $next = inv_format_doc_number((string) $settings['invoice_prefix'], $year, (int) $series['next_value'], (int) $settings['seq_digits']);
    }
    // de-duplicate messages
    $seen = [];
    $problems = array_values(array_filter($problems, function ($p) use (&$seen) {
        if (isset($seen[$p['message']])) return false;
        $seen[$p['message']] = true;
        return true;
    }));
    return ['problems' => $problems, 'next_number' => $next, 'built' => $built];
}

/** A stored draft row (+ lines) as inv_build_draft input. */
function inv_draft_row_to_input(PDO $db, array $inv): array
{
    return [
        'entity_code' => $inv['entity_code'], 'party_id' => $inv['party_id'], 'category_id' => $inv['category_id'],
        'invoice_date' => $inv['invoice_date'], 'payment_terms_days' => $inv['payment_terms_days'],
        'customer_ref' => $inv['customer_ref'], 'bill_to_name' => $inv['bill_to_name'],
        'bill_to_address' => $inv['bill_to_address'], 'bill_to_attention' => $inv['bill_to_attention'],
        'bill_to_email' => $inv['bill_to_email'], 'internal_notes' => $inv['internal_notes'],
        'lines' => inv_lines_to_input(inv_fetch_lines($db, (int) $inv['id'])),
    ];
}

/**
 * Issue a draft. One transaction:
 *   lock invoice -> lock entity settings -> lock number series (fixed order,
 *   so it cannot deadlock with the settings / number-series endpoints);
 *   re-check everything; assign the number; snapshot; render the PDF and
 *   WRITE IT TO ITS FINAL PATH; record path + SHA-256; recompute status;
 *   log; COMMIT. Any failure rolls back AND deletes the new file, so no
 *   number is consumed and no PDF is left behind.
 * Returns ['ok' => true, 'invoice' => row] or ['ok' => false, 'http' => int, 'error' => str, 'problems' => [...]].
 */
function inv_issue_invoice(PDO $db, int $id, string $rowHash, array $user): array
{
    $written = null;
    $db->beginTransaction();
    try {
        $inv = inv_fetch_invoice($db, $id, true);
        if (!$inv) {
            $db->rollBack();
            return ['ok' => false, 'http' => 404, 'error' => 'Invoice not found.', 'problems' => []];
        }
        if ($inv['status'] !== 'draft') {
            $db->rollBack();
            return ['ok' => false, 'http' => 409, 'error' => 'This invoice has already been issued.', 'problems' => []];
        }
        if (!hash_equals(inv_row_hash($inv), $rowHash)) {
            $db->rollBack();
            return ['ok' => false, 'http' => 409, 'error' => 'This draft was changed since you opened it. Reload and try again.', 'problems' => []];
        }

        $ls = $db->prepare('SELECT * FROM inv_settings WHERE entity_code = :l_entity FOR UPDATE');
        $ls->execute(['l_entity' => $inv['entity_code']]);
        $settings = $ls->fetch();
        inv_get_entities($db, true); // refresh cache inside the transaction

        $built = inv_build_draft($db, inv_draft_row_to_input($db, $inv), $inv);
        $h = $built['header'];
        $year = (int) substr($h['invoice_date'], 0, 4);

        $lk = $db->prepare(
            'SELECT * FROM inv_number_series
             WHERE entity_code = :k_entity AND doc_type = \'invoice\' AND period_year = :k_year FOR UPDATE'
        );
        $lk->execute(['k_entity' => $inv['entity_code'], 'k_year' => $year]);
        $series = $lk->fetch();

        $problems = array_merge(
            $settings ? inv_entity_readiness($db, (string) $inv['entity_code'], 'invoice', $year)
                      : [['field' => 'entity', 'message' => 'Unknown issuing entity.']],
            inv_module_problems($db),
            inv_issue_content_problems($db, $inv, $built)
        );
        if (!$problems && $series) {
            $maxIssued = inv_max_issued_seq($db, $settings, 'invoice', $year);
            if ((int) $series['next_value'] <= $maxIssued) {
                $problems[] = ['field' => 'series', 'message' => 'The number counter is behind numbers already issued. Confirm the next number again in Settings.'];
            }
        }
        if ($problems) {
            $db->rollBack();
            return ['ok' => false, 'http' => 409, 'error' => 'This invoice cannot be issued yet.', 'problems' => $problems];
        }

        // --- number ---------------------------------------------------------
        $seq = (int) $series['next_value'];
        $number = inv_format_doc_number((string) $settings['invoice_prefix'], $year, $seq, (int) $settings['seq_digits']);
        $taken = $db->prepare('SELECT COUNT(*) FROM inv_invoices WHERE entity_code = :t_entity AND invoice_no = :t_no');
        $taken->execute(['t_entity' => $inv['entity_code'], 't_no' => $number]);
        if ((int) $taken->fetchColumn() > 0) {
            $db->rollBack();
            return ['ok' => false, 'http' => 409, 'error' => "Number $number is already used. Confirm the next number again in Settings.", 'problems' => []];
        }
        $up = $db->prepare(
            'UPDATE inv_number_series SET next_value = next_value + 1
             WHERE entity_code = :n_entity AND doc_type = \'invoice\' AND period_year = :n_year'
        );
        $up->execute(['n_entity' => $inv['entity_code'], 'n_year' => $year]);

        // --- snapshot ---------------------------------------------------------
        $cat = inv_fetch_category($db, (int) $h['category_id']);
        $paper = inv_paper($db);
        $snapshot = inv_entity_snapshot($settings, $paper);
        $words = inv_amount_in_words($h['total_c']);

        $iss = $db->prepare(
            'UPDATE inv_invoices SET
                status = \'issued\', invoice_no = :i_no, issued_at = NOW(), issued_by = :i_by, issued_by_name = :i_by_name,
                invoice_date = :i_date, payment_terms_days = :i_terms, due_date = :i_due,
                subtotal = :i_sub, gst_rate = :i_rate, gst_amount = :i_gst, total = :i_total,
                amount_in_words = :i_words, category_name = :i_cat,
                bill_to_name = :i_bn, bill_to_address = :i_ba, bill_to_attention = :i_bat, bill_to_email = :i_be,
                customer_ref = :i_ref, entity_snapshot = :i_snap, row_version = row_version + 1
             WHERE id = :i_id AND status = \'draft\''
        );
        $iss->execute([
            'i_no' => $number, 'i_by' => (int) $user['id'], 'i_by_name' => $user['name'],
            'i_date' => $h['invoice_date'], 'i_terms' => $h['payment_terms_days'], 'i_due' => $h['due_date'],
            'i_sub' => inv_cents_to_decimal($h['subtotal_c']), 'i_rate' => inv_cents_to_decimal($h['gst_rate_h']),
            'i_gst' => inv_cents_to_decimal($h['gst_c']), 'i_total' => inv_cents_to_decimal($h['total_c']),
            'i_words' => $words, 'i_cat' => $cat['name'],
            'i_bn' => $h['bill_to_name'], 'i_ba' => $h['bill_to_address'], 'i_bat' => $h['bill_to_attention'],
            'i_be' => $h['bill_to_email'], 'i_ref' => $h['customer_ref'],
            'i_snap' => json_encode($snapshot, JSON_UNESCAPED_UNICODE), 'i_id' => $id,
        ]);
        if ($iss->rowCount() !== 1) {
            throw new RuntimeException('Draft could not be marked as issued.');
        }
        // Lines are re-written with the recomputed amounts (identical unless a
        // service's qty rule changed since the last save).
        inv_save_lines_only($db, $id, $built['lines']);

        // --- PDF: render and write to its FINAL path before commit -------------
        $html = inv_pdf_html(inv_doc_model($built, $settings, $number, $cat['name'], $paper));
        $pdf = inv_pdf_render($html, $paper, null, 'Invoice ' . $number);
        $root = inv_storage_status($db)['root'];
        if ($root === null) throw new RuntimeException('PDF storage is not available.');
        $safeEntity = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $inv['entity_code']);
        $safeNo = preg_replace('/[^A-Za-z0-9_-]/', '_', $number);
        $written = inv_storage_write_new($root, 'invoices/' . $safeEntity . '/' . $year, $safeNo, $pdf);

        $pf = $db->prepare('UPDATE inv_invoices SET pdf_file = :p_file, pdf_sha256 = :p_sha WHERE id = :p_id');
        $pf->execute(['p_file' => $written['rel'], 'p_sha' => $written['sha256'], 'p_id' => $id]);

        $status = inv_recompute_status($db, $id);
        inv_log_activity((int) $user['id'], 'issue_invoice', 'inv_invoice', $id,
            "$number issued ({$inv['entity_code']}, total " . inv_cents_to_decimal($h['total_c']) . ", status $status, pdf sha256 {$written['sha256']})"
            . ($written['orphan'] ? '; moved aside orphan file ' . basename($written['orphan']) : ''));

        $db->commit();
        return ['ok' => true, 'invoice' => inv_fetch_invoice($db, $id)];
    } catch (Throwable $ex) {
        if ($db->inTransaction()) $db->rollBack();
        if ($written !== null && is_file($written['abs'])) {
            @unlink($written['abs']);
        }
        error_log('inv issue ' . $id . ': ' . $ex->getMessage());
        return ['ok' => false, 'http' => 500, 'error' => 'Issuing failed and was fully rolled back (no number used, no file kept). ' . $ex->getMessage(), 'problems' => []];
    }
}

/** Replace the lines of an invoice (used inside the issue transaction). */
function inv_save_lines_only(PDO $db, int $id, array $lines): void
{
    $del = $db->prepare('DELETE FROM inv_invoice_lines WHERE invoice_id = :d_inv');
    $del->execute(['d_inv' => $id]);
    $insLine = $db->prepare(
        'INSERT INTO inv_invoice_lines (invoice_id, line_no, service_id, title, description, qty, rate, amount)
         VALUES (:invoice_id, :line_no, :service_id, :title, :description, :qty, :rate, :amount)'
    );
    $insEnt = $db->prepare(
        'INSERT INTO inv_line_audited_entities (line_id, seq, company_name, uen, source_client_id)
         VALUES (:line_id, :seq, :company_name, :uen, :source_client_id)'
    );
    foreach ($lines as $l) {
        $insLine->execute([
            'invoice_id' => $id, 'line_no' => $l['line_no'], 'service_id' => $l['service_id'],
            'title' => $l['title'], 'description' => $l['description'],
            'qty' => inv_cents_to_decimal($l['qty_h']), 'rate' => inv_cents_to_decimal($l['rate_c']),
            'amount' => inv_cents_to_decimal($l['amount_c']),
        ]);
        $lineId = (int) $db->lastInsertId();
        foreach ($l['entities'] as $i => $e) {
            $insEnt->execute([
                'line_id' => $lineId, 'seq' => $i + 1, 'company_name' => $e['company_name'],
                'uen' => $e['uen'], 'source_client_id' => $e['source_client_id'],
            ]);
        }
    }
}
