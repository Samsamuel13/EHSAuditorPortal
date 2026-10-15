<?php
/**
 * invoice-system/includes/inv_quote.php — quotations (Stage 3B).
 *
 * Lifecycle (stored status):
 *   draft -> sent ("Finalise and send": numbered, PDF stored, locked)
 *   sent  -> accepted (acceptance date, client signatory, optional signed copy)
 *   sent  -> rejected | cancelled
 *   sent  -> superseded (when its revision -R<n> is finalised)
 *   accepted -> cancelled (only while none of its lines has been invoiced)
 * "Expired" is COMPUTED, never stored: status = 'sent' AND valid_until < today.
 * An ACCEPTED quotation never expires and stays convertible, so later-year
 * lines can be invoiced later.
 *
 * Numbers: prefix + YY + 3-digit sequence from the 'quotation' series (e.g.
 * Q/SH26001); revisions keep the base and add -R1, -R2 (no new sequence).
 * Numbers contain "/", so they are NEVER used in a file path, folder or URL:
 * the PDF is stored as quotations/<ENTITY>/<YYYY>/q<ID>.pdf, signed copies as
 * acceptances/<ENTITY>/<YYYY>/q<ID>-signed-<random>.<ext>, URLs use ?id=<ID>,
 * and download file names are sanitised ("/" becomes "-").
 *
 * All SQL touches inv_ tables only. Named placeholders are never reused
 * within one query. Money is integer cents.
 */

require_once __DIR__ . '/inv_money.php';
require_once __DIR__ . '/inv_storage.php';
require_once __DIR__ . '/inv_invoice.php';
require_once __DIR__ . '/inv_pdf.php';
require_once __DIR__ . '/inv_quote_pdf.php';

const INV_QUOTE_STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'cancelled', 'superseded'];
const INV_QUOTE_STATUS_LABELS = [
    'draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted', 'rejected' => 'Rejected',
    'cancelled' => 'Cancelled', 'superseded' => 'Superseded', 'expired' => 'Expired',
];
const INV_QUOTE_LINE_TYPES = ['item', 'auto_sum', 'manual_sum'];
const INV_QUOTE_MAX_LINES = 50;
const INV_SIGNED_MAX_BYTES = 10485760; // 10 MB
const INV_SIGNED_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

/** Quotation text fields copied from the entity defaults on a new quotation. */
const INV_QUOTE_TEXT_DEFAULTS = [
    'ref_prefix' => ['quote_ref_prefix', 255, false],
    'intro' => ['quote_intro', 2000, true],
    'terms_text' => ['quote_terms', 2000, true],
    'payment_terms' => ['quote_payment_terms', 2000, true],
    'closing_text' => ['quote_closing', 2000, true],
];
// No signatory: quotations are computer generated (owner decision 15/10/2026). The
// signatory_name column is kept (NULL on new quotations) so no migration is needed.


function inv_fetch_quote(PDO $db, int $id, bool $forUpdate = false): ?array
{
    $stmt = $db->prepare('SELECT * FROM inv_quotations WHERE id = :q_id' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute(['q_id' => $id]);
    $r = $stmt->fetch();
    return $r ?: null;
}

function inv_fetch_quote_lines(PDO $db, int $quoteId, bool $forUpdate = false): array
{
    $stmt = $db->prepare('SELECT * FROM inv_quotation_lines WHERE quotation_id = :l_q ORDER BY line_no, id' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute(['l_q' => $quoteId]);
    $lines = $stmt->fetchAll();
    if (!$lines) return [];
    $ids = array_map(fn($l) => (int) $l['id'], $lines);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $es = $db->prepare("SELECT * FROM inv_quotation_line_entities WHERE line_id IN ($ph) ORDER BY line_id, seq");
    $es->execute($ids);
    $by = [];
    foreach ($es->fetchAll() as $e) $by[(int) $e['line_id']][] = $e;
    foreach ($lines as &$l) $l['entities'] = $by[(int) $l['id']] ?? [];
    return $lines;
}

function inv_quote_is_expired(array $q, ?string $today = null): bool
{
    return $q['status'] === 'sent' && $q['valid_until'] < ($today ?? inv_today());
}

function inv_quote_display_status(array $q, ?string $today = null): string
{
    return inv_quote_is_expired($q, $today) ? 'expired' : $q['status'];
}

/**
 * Validate and normalise a quotation draft. All amounts are recomputed.
 * Returns ['header' => [...], 'lines' => [...], 'errors' => [...], 'warnings' => [...]].
 */
function inv_build_quote(PDO $db, array $in, ?array $existing): array
{
    $errors = [];
    $warnings = [];
    $h = [];

    $entities = inv_get_entities($db, true);
    $code = inv_clean_str($in['entity_code'] ?? ($existing['entity_code'] ?? null), 20);
    $settings = ($code !== null && isset($entities[$code])) ? $entities[$code] : null;
    if (!$settings) {
        $errors['entity_code'] = 'Choose the issuing entity.';
    } elseif ((int) $settings['is_active'] !== 1) {
        $errors['entity_code'] = 'This issuing entity is deactivated.';
    }
    $h['entity_code'] = $code;

    $partyId = inv_int_or_null($in['party_id'] ?? null, 1, 4294967295);
    $party = $partyId ? inv_fetch_party_row($db, $partyId) : null;
    if (!$party) {
        $errors['party_id'] = 'Choose the party the quotation is addressed to.';
    } elseif ((int) $party['is_active'] !== 1 && (!$existing || (int) $existing['party_id'] !== (int) $party['id'])) {
        $errors['party_id'] = 'This party is deactivated. Reactivate it first.';
    }
    $h['party_id'] = $party ? (int) $party['id'] : null;

    $date = $in['quote_date'] ?? inv_today();
    if (!inv_is_valid_ymd($date)) {
        $errors['quote_date'] = 'Enter a valid quotation date.';
        $date = inv_today();
    } elseif ($date > inv_today()) {
        $errors['quote_date'] = 'Future quotation dates are not allowed.';
    } elseif ($date < INV_MIN_DATE) {
        $errors['quote_date'] = 'Quotation date is too far in the past.';
    } elseif ($date < inv_today()) {
        $warnings[] = 'Backdated: the quotation date is before today.';
    }
    $h['quote_date'] = $date;

    $defaultDays = $settings ? (int) ($settings['quote_valid_days'] ?? 45) : 45;
    $vd = (isset($in['valid_days']) && $in['valid_days'] !== '') ? inv_int_or_null($in['valid_days'], 1, 365) : $defaultDays;
    if ($vd === false || $vd === null) {
        $errors['valid_days'] = 'Validity must be 1 to 365 days.';
        $vd = $defaultDays;
    }
    $h['valid_days'] = $vd;
    $h['valid_until'] = inv_add_days($date, $vd);

    // Bill-to: prefilled from the party when not supplied.
    $fromParty = [
        'bill_to_name' => $party['display_name'] ?? null, 'bill_to_address' => $party['billing_address'] ?? null,
        'bill_to_attention' => $party['attention'] ?? null, 'bill_to_email' => $party['accounts_email'] ?? null,
    ];
    foreach (['bill_to_name' => 200, 'bill_to_address' => 1000, 'bill_to_attention' => 150, 'bill_to_email' => 150] as $f => $max) {
        $h[$f] = array_key_exists($f, $in) ? inv_clean_str($in[$f], $max, $f === 'bill_to_address') : $fromParty[$f];
    }
    if ($h['bill_to_email'] !== null && !inv_is_valid_email($h['bill_to_email'])) {
        $errors['bill_to_email'] = 'Not a valid email address.';
    }
    if ($h['bill_to_address'] === null) $warnings[] = 'No address: required before finalising.';

    // Texts: from input, else from the existing draft, else entity defaults.
    foreach (INV_QUOTE_TEXT_DEFAULTS as $f => [$src, $max, $multi]) {
        if (array_key_exists($f, $in)) {
            $h[$f] = inv_clean_str($in[$f], $max, $multi);
        } elseif ($existing !== null) {
            $h[$f] = $existing[$f];
        } else {
            $h[$f] = $settings ? inv_clean_str($settings[$src] ?? null, $max, $multi) : null;
        }
    }
    $h['signatory_name'] = null;
    $h['subject'] = inv_clean_str($in['subject'] ?? ($existing['subject'] ?? null), 500);
    $h['scope'] = inv_clean_str($in['scope'] ?? ($existing['scope'] ?? null), 2000, true);
    $h['internal_notes'] = inv_clean_str($in['internal_notes'] ?? ($existing['internal_notes'] ?? null), 2000, true);

    // Lines.
    $raw = is_array($in['lines'] ?? null) ? array_values($in['lines']) : [];
    if (count($raw) > INV_QUOTE_MAX_LINES) {
        $errors['lines'] = 'At most ' . INV_QUOTE_MAX_LINES . ' lines per quotation.';
        $raw = array_slice($raw, 0, INV_QUOTE_MAX_LINES);
    }
    $services = [];
    foreach ($db->query('SELECT * FROM inv_services')->fetchAll() as $s) $services[(int) $s['id']] = $s;

    $lines = [];
    $runList = 0;
    $runSpecial = 0;
    $totalList = 0;
    $totalSpecial = 0;
    foreach ($raw as $idx => $rl) {
        $n = $idx + 1;
        $k = 'lines.' . $idx;
        if (!is_array($rl)) {
            $errors[$k] = "Line $n is invalid.";
            continue;
        }
        $type = $rl['line_type'] ?? 'item';
        if (!in_array($type, INV_QUOTE_LINE_TYPES, true)) {
            $errors[$k . '.line_type'] = "Line $n: unknown line type.";
            $type = 'item';
        }
        $title = inv_clean_str($rl['title'] ?? null, 255);
        if ($title === null) $errors[$k . '.title'] = "Line $n: description is required.";
        $desc = inv_clean_str($rl['description'] ?? null, 4000, true);
        $year = inv_int_or_null($rl['cycle_year'] ?? null, 1, 10);
        if ($year === false) {
            $errors[$k . '.cycle_year'] = "Line $n: year must be 1 to 10.";
            $year = null;
        }

        $line = ['line_no' => $n, 'line_type' => $type, 'service_id' => null, 'title' => $title, 'description' => $desc,
                 'qty_h' => 100, 'rate_c' => null, 'special_c' => null, 'amount_c' => 0, 'special_amount_c' => 0,
                 'cycle_year' => $year, 'entities' => [], 'qty_auto' => false];

        if ($type === 'auto_sum') {
            $line['amount_c'] = $runList;
            $line['special_amount_c'] = $runSpecial;
            $runList = 0;
            $runSpecial = 0;
            $lines[] = $line;
            continue;
        }

        $rate = inv_parse_cents($rl['rate'] ?? '');
        if ($rate === null || $rate > INV_MAX_RATE_CENTS) {
            $errors[$k . '.rate'] = "Line $n: rate must be 0 to 9,999,999.99.";
            $rate = 0;
        }
        $spRaw = trim((string) ($rl['special_rate'] ?? ''));
        $special = null;
        if ($spRaw !== '') {
            $special = inv_parse_cents($spRaw);
            if ($special === null || $special > INV_MAX_RATE_CENTS) {
                $errors[$k . '.special_rate'] = "Line $n: special rate must be 0 to 9,999,999.99 (or blank).";
                $special = null;
            }
        }
        $line['rate_c'] = $rate;
        $line['special_c'] = $special;

        if ($type === 'manual_sum') {
            $line['amount_c'] = $rate;
            $line['special_amount_c'] = $special ?? $rate;
            $runList = 0;
            $runSpecial = 0;
            $lines[] = $line;
            continue;
        }

        // item
        $sid = inv_int_or_null($rl['service_id'] ?? null, 1, 4294967295);
        $svc = null;
        if ($sid === false) {
            $errors[$k . '.service_id'] = "Line $n: invalid service.";
        } elseif ($sid !== null) {
            $svc = $services[$sid] ?? null;
            if (!$svc) {
                $errors[$k . '.service_id'] = "Line $n: unknown service.";
            } elseif ($svc['entity_code'] !== null && $code !== null && $svc['entity_code'] !== $code) {
                $errors[$k . '.service_id'] = "Line $n: this service belongs to " . $svc['entity_code'] . '.';
            } else {
                $line['service_id'] = $sid;
            }
        }
        foreach ((is_array($rl['entities'] ?? null) ? array_slice($rl['entities'], 0, INV_MAX_ENTITIES_PER_LINE) : []) as $re) {
            $nm = inv_clean_str(is_array($re) ? ($re['company_name'] ?? null) : $re, 200);
            if ($nm === null) continue;
            $src = is_array($re) ? inv_int_or_null($re['source_client_id'] ?? null, 1, 4294967295) : null;
            $line['entities'][] = ['company_name' => $nm, 'uen' => is_array($re) ? inv_clean_str($re['uen'] ?? null, 50) : null,
                                   'source_client_id' => $src ?: null];
        }
        $auto = $svc !== null && (int) $svc['qty_from_entities'] === 1 && count($line['entities']) > 0;
        if ($auto) {
            $qty = count($line['entities']) * 100;
        } else {
            $qtyIn = $rl['qty'] ?? '1';
            $qty = inv_parse_qty($qtyIn === '' || $qtyIn === null ? '1' : $qtyIn);
            if ($qty === null || $qty <= 0 || $qty > INV_MAX_QTY_HUNDREDTHS) {
                $errors[$k . '.qty'] = "Line $n: quantity must be more than 0 and at most 9,999.99.";
                $qty = 100;
            }
        }
        $line['qty_h'] = $qty;
        $line['qty_auto'] = $auto;
        $line['amount_c'] = inv_line_amount_cents($qty, $rate);
        $line['special_amount_c'] = inv_line_amount_cents($qty, $special ?? $rate);
        if ($line['amount_c'] > INV_MAX_MONEY_CENTS || $line['special_amount_c'] > INV_MAX_MONEY_CENTS) {
            $errors[$k . '.rate'] = "Line $n: amount is too large.";
        }
        $runList += $line['amount_c'];
        $runSpecial += $line['special_amount_c'];
        $totalList += $line['amount_c'];
        $totalSpecial += $line['special_amount_c'];
        $lines[] = $line;
    }
    if ($totalList > INV_MAX_MONEY_CENTS || $totalSpecial > INV_MAX_MONEY_CENTS) $errors['lines'] = 'Quotation total is too large.';
    if (!array_filter($lines, fn($l) => $l['line_type'] === 'item')) $warnings[] = 'No priced item lines yet.';

    $h['total_list_c'] = $totalList;
    $h['total_special_c'] = $totalSpecial;
    return ['header' => $h, 'lines' => $lines, 'errors' => $errors, 'warnings' => $warnings];
}

/** Replace a quotation's lines (and their audited entities). Caller holds a transaction. */
function inv_save_quote_lines(PDO $db, int $id, array $lines): void
{
    $del = $db->prepare('DELETE FROM inv_quotation_lines WHERE quotation_id = :d_q');
    $del->execute(['d_q' => $id]);
    $ins = $db->prepare(
        'INSERT INTO inv_quotation_lines
            (quotation_id, line_no, line_type, service_id, title, description, qty, rate, special_rate, amount, special_amount, cycle_year)
         VALUES (:quotation_id, :line_no, :line_type, :service_id, :title, :description, :qty, :rate, :special_rate, :amount, :special_amount, :cycle_year)'
    );
    $insE = $db->prepare(
        'INSERT INTO inv_quotation_line_entities (line_id, seq, company_name, uen, source_client_id)
         VALUES (:line_id, :seq, :company_name, :uen, :source_client_id)'
    );
    foreach ($lines as $l) {
        $ins->execute([
            'quotation_id' => $id, 'line_no' => $l['line_no'], 'line_type' => $l['line_type'],
            'service_id' => $l['service_id'], 'title' => $l['title'], 'description' => $l['description'],
            'qty' => inv_cents_to_decimal($l['qty_h']),
            'rate' => $l['rate_c'] === null ? null : inv_cents_to_decimal($l['rate_c']),
            'special_rate' => $l['special_c'] === null ? null : inv_cents_to_decimal($l['special_c']),
            'amount' => inv_cents_to_decimal($l['amount_c']), 'special_amount' => inv_cents_to_decimal($l['special_amount_c']),
            'cycle_year' => $l['cycle_year'],
        ]);
        $lineId = (int) $db->lastInsertId();
        foreach ($l['entities'] as $i => $e) {
            $insE->execute(['line_id' => $lineId, 'seq' => $i + 1, 'company_name' => $e['company_name'],
                            'uen' => $e['uen'], 'source_client_id' => $e['source_client_id']]);
        }
    }
}

/** Insert or update a draft quotation (caller holds a transaction). Returns the id. */
function inv_save_quote(PDO $db, ?int $id, array $h, array $lines, array $user, array $extra = []): int
{
    $p = [
        'entity_code' => $h['entity_code'], 'party_id' => $h['party_id'], 'quote_date' => $h['quote_date'],
        'valid_days' => $h['valid_days'], 'valid_until' => $h['valid_until'],
        'ref_prefix' => $h['ref_prefix'], 'subject' => $h['subject'], 'scope' => $h['scope'],
        'intro' => $h['intro'], 'terms_text' => $h['terms_text'], 'payment_terms' => $h['payment_terms'],
        'closing_text' => $h['closing_text'], 'signatory_name' => $h['signatory_name'],
        'bill_to_name' => $h['bill_to_name'], 'bill_to_address' => $h['bill_to_address'],
        'bill_to_attention' => $h['bill_to_attention'], 'bill_to_email' => $h['bill_to_email'],
        'total_list' => inv_cents_to_decimal($h['total_list_c']), 'total_special' => inv_cents_to_decimal($h['total_special_c']),
        'internal_notes' => $h['internal_notes'],
    ];
    if ($id === null) {
        $stmt = $db->prepare(
            'INSERT INTO inv_quotations
                (entity_code, status, party_id, quote_date, valid_days, valid_until, ref_prefix, subject, scope, intro,
                 terms_text, payment_terms, closing_text, signatory_name, bill_to_name, bill_to_address, bill_to_attention,
                 bill_to_email, total_list, total_special, internal_notes, revision_of_id, revision_no, base_no,
                 created_by, created_by_name)
             VALUES
                (:entity_code, \'draft\', :party_id, :quote_date, :valid_days, :valid_until, :ref_prefix, :subject, :scope, :intro,
                 :terms_text, :payment_terms, :closing_text, :signatory_name, :bill_to_name, :bill_to_address, :bill_to_attention,
                 :bill_to_email, :total_list, :total_special, :internal_notes, :revision_of_id, :revision_no, :base_no,
                 :created_by, :created_by_name)'
        );
        $stmt->execute($p + [
            'revision_of_id' => $extra['revision_of_id'] ?? null, 'revision_no' => $extra['revision_no'] ?? 0,
            'base_no' => $extra['base_no'] ?? null, 'created_by' => (int) $user['id'], 'created_by_name' => $user['name'],
        ]);
        $id = (int) $db->lastInsertId();
    } else {
        $stmt = $db->prepare(
            'UPDATE inv_quotations SET
                entity_code = :entity_code, party_id = :party_id, quote_date = :quote_date, valid_days = :valid_days,
                valid_until = :valid_until, ref_prefix = :ref_prefix, subject = :subject, scope = :scope, intro = :intro,
                terms_text = :terms_text, payment_terms = :payment_terms, closing_text = :closing_text,
                signatory_name = :signatory_name, bill_to_name = :bill_to_name, bill_to_address = :bill_to_address,
                bill_to_attention = :bill_to_attention, bill_to_email = :bill_to_email, total_list = :total_list,
                total_special = :total_special, internal_notes = :internal_notes, row_version = row_version + 1
             WHERE id = :w_id AND status = \'draft\''
        );
        $stmt->execute($p + ['w_id' => $id]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('Only drafts can be saved.');
    }
    inv_save_quote_lines($db, $id, $lines);
    return $id;
}

/** A stored quotation (+ lines) as inv_build_quote input. */
function inv_quote_row_to_input(PDO $db, array $q): array
{
    $in = [
        'entity_code' => $q['entity_code'], 'party_id' => $q['party_id'], 'quote_date' => $q['quote_date'],
        'valid_days' => $q['valid_days'], 'subject' => $q['subject'], 'scope' => $q['scope'],
        'internal_notes' => $q['internal_notes'],
        'bill_to_name' => $q['bill_to_name'], 'bill_to_address' => $q['bill_to_address'],
        'bill_to_attention' => $q['bill_to_attention'], 'bill_to_email' => $q['bill_to_email'], 'lines' => [],
    ];
    foreach (array_keys(INV_QUOTE_TEXT_DEFAULTS) as $f) $in[$f] = $q[$f];
    foreach (inv_fetch_quote_lines($db, (int) $q['id']) as $l) {
        $in['lines'][] = [
            'line_type' => $l['line_type'], 'service_id' => $l['service_id'] !== null ? (int) $l['service_id'] : null,
            'title' => $l['title'], 'description' => $l['description'], 'qty' => $l['qty'], 'rate' => $l['rate'],
            'special_rate' => $l['special_rate'], 'cycle_year' => $l['cycle_year'],
            'entities' => array_map(fn($e) => ['company_name' => $e['company_name'], 'uen' => $e['uen'],
                'source_client_id' => $e['source_client_id'] !== null ? (int) $e['source_client_id'] : null], $l['entities']),
        ];
    }
    return $in;
}

/** Content problems that block finalising a draft quotation. */
function inv_quote_content_problems(PDO $db, array $built): array
{
    $p = [];
    $h = $built['header'];
    foreach ($built['errors'] as $f => $m) $p[] = ['field' => $f, 'message' => $m];
    if (!array_filter($built['lines'], fn($l) => $l['line_type'] === 'item')) {
        $p[] = ['field' => 'lines', 'message' => 'Add at least one priced item line.'];
    }
    if ($h['bill_to_name'] === null) $p[] = ['field' => 'bill_to_name', 'message' => 'Addressee name is required.'];
    if ($h['bill_to_address'] === null) $p[] = ['field' => 'bill_to_address', 'message' => 'Address is required before finalising.'];
    if ($h['subject'] === null) $p[] = ['field' => 'subject', 'message' => 'The "Ref:" subject is required.'];
    foreach (array_keys(INV_QUOTE_TEXT_DEFAULTS) as $f) {
        if ($h[$f] === null) $p[] = ['field' => $f, 'message' => 'The ' . str_replace('_', ' ', $f) . ' text is empty.'];
    }
    if ($h['party_id'] !== null) {
        $party = inv_fetch_party_row($db, (int) $h['party_id']);
        if ($party && (int) $party['is_active'] !== 1) $p[] = ['field' => 'party_id', 'message' => 'The party is deactivated.'];
    }
    $texts = [$h['bill_to_name'], $h['bill_to_address'], $h['bill_to_attention'], $h['subject'], $h['scope']];
    foreach (array_keys(INV_QUOTE_TEXT_DEFAULTS) as $f) $texts[] = $h[$f];
    foreach ($built['lines'] as $l) {
        $texts[] = $l['title'];
        $texts[] = $l['description'];
        foreach ($l['entities'] as $e) $texts[] = $e['company_name'];
    }
    foreach ($texts as $t) {
        if (inv_has_tbc_marker($t)) {
            $p[] = ['field' => 'content', 'message' => 'The quotation text still contains a «TBC» placeholder.'];
            break;
        }
    }
    return $p;
}

/** Entity fields frozen into a sent quotation (incl. hashes of logo and stamp used). */
function inv_quote_entity_snapshot(array $s, string $paper, bool $stampPrinted): array
{
    $keys = ['entity_code', 'legal_name', 'co_reg_no', 'address', 'email', 'phone', 'logo_filename', 'footer_text',
             'gst_registered', 'gst_status_confirmed', 'gst_rate', 'stamp_filename'];
    $snap = [];
    foreach ($keys as $k) $snap[$k] = $s[$k] ?? null;
    $logo = inv_logo_path($s['logo_filename'] ?? null);
    $stamp = inv_stamp_path($s['stamp_filename'] ?? null);
    $snap['logo_sha256'] = ($logo && is_file($logo)) ? hash_file('sha256', $logo) : null;
    $snap['stamp_sha256'] = ($stampPrinted && $stamp && is_file($stamp)) ? hash_file('sha256', $stamp) : null;
    $snap['stamp_printed'] = $stampPrinted && $snap['stamp_sha256'] !== null;
    $snap['paper'] = $paper;
    return $snap;
}

/** Pre-check for the editor (non-locking). */
function inv_quote_precheck(PDO $db, array $q): array
{
    $built = inv_build_quote($db, inv_quote_row_to_input($db, $q), $q);
    $year = (int) substr($built['header']['quote_date'], 0, 4);
    $ready = inv_quote_readiness($db, (string) $q['entity_code'], $year);
    if ($q['revision_of_id'] !== null) {
        $ready = array_values(array_filter($ready, fn($p) => $p['field'] !== 'series' && $p['field'] !== 'quote_prefix'));
    }
    $problems = array_merge($ready, inv_module_problems($db), inv_quote_content_problems($db, $built));
    $seen = [];
    $problems = array_values(array_filter($problems, function ($p) use (&$seen) {
        if (isset($seen[$p['message']])) return false;
        return $seen[$p['message']] = true;
    }));
    $next = null;
    $s = inv_get_entities($db)[$q['entity_code']] ?? null;
    if ($q['revision_of_id'] !== null && $q['base_no'] !== null) {
        $next = $q['base_no'] . '-R' . (int) $q['revision_no'];
    } elseif ($s && array_key_exists('quote_prefix', $s)) {
        $series = inv_get_series($db, (string) $q['entity_code'], 'quotation', $year);
        if ($series && (int) $series['is_confirmed'] === 1 && $series['next_value'] !== null) {
            $next = inv_format_doc_number((string) $s['quote_prefix'], $year, (int) $series['next_value'], (int) $s['seq_digits']);
        }
    }
    return ['problems' => $problems, 'next_number' => $next, 'built' => $built];
}

/** PDF view model. $settings: live settings (draft) or the frozen snapshot merged over them (sent). */
function inv_quote_doc_model(array $built, array $settings, ?string $number, string $paper, bool $withStamp): array
{
    $h = $built['header'];
    $gstReg = (int) ($settings['gst_registered'] ?? 0) === 1 && (int) ($settings['gst_status_confirmed'] ?? 0) === 1;
    return [
        'number' => $number, 'quote_date' => $h['quote_date'], 'valid_days' => $h['valid_days'],
        'bill_to' => ['name' => (string) $h['bill_to_name'], 'address' => $h['bill_to_address'], 'attention' => $h['bill_to_attention']],
        'ref_prefix' => $h['ref_prefix'], 'subject' => $h['subject'], 'scope' => $h['scope'], 'intro' => $h['intro'],
        'terms_text' => $h['terms_text'], 'payment_terms' => $h['payment_terms'], 'closing_text' => $h['closing_text'],
        'lines' => $built['lines'], 'entity' => $settings,
        'gst_registered' => $gstReg, 'gst_rate' => $settings['gst_rate'] ?? '9.00',
        'stamp' => $withStamp ? ($settings['stamp_filename'] ?? null) : null, 'paper' => $paper,
    ];
}

/**
 * Finalise and send a draft. One transaction:
 *   lock quotation -> (parent quotation for a revision) -> entity settings ->
 *   quotation number series; re-check; assign the number (or base-R<n>);
 *   snapshot; render the PDF WITH the stamp and write it to its final,
 *   ID-based path before COMMIT; supersede the parent (revision); log; COMMIT.
 *   Any failure rolls back and deletes the new file.
 */
function inv_finalise_quote(PDO $db, int $id, string $rowHash, array $user): array
{
    $written = null;
    $db->beginTransaction();
    try {
        $q = inv_fetch_quote($db, $id, true);
        if (!$q) {
            $db->rollBack();
            return ['ok' => false, 'http' => 404, 'error' => 'Quotation not found.', 'problems' => []];
        }
        if ($q['status'] !== 'draft') {
            $db->rollBack();
            return ['ok' => false, 'http' => 409, 'error' => 'This quotation has already been finalised.', 'problems' => []];
        }
        if (!hash_equals(inv_row_hash($q), $rowHash)) {
            $db->rollBack();
            return ['ok' => false, 'http' => 409, 'error' => 'This draft was changed since you opened it. Reload and try again.', 'problems' => []];
        }
        $parent = null;
        if ($q['revision_of_id'] !== null) {
            $parent = inv_fetch_quote($db, (int) $q['revision_of_id'], true);
            if (!$parent || $parent['status'] !== 'sent') {
                $db->rollBack();
                return ['ok' => false, 'http' => 409, 'error' => 'The quotation being revised is no longer open (it was accepted, rejected, cancelled or already superseded).', 'problems' => []];
            }
        }
        $ls = $db->prepare('SELECT * FROM inv_settings WHERE entity_code = :l_entity FOR UPDATE');
        $ls->execute(['l_entity' => $q['entity_code']]);
        $settings = $ls->fetch();
        inv_get_entities($db, true);

        $built = inv_build_quote($db, inv_quote_row_to_input($db, $q), $q);
        $h = $built['header'];
        $year = (int) substr($h['quote_date'], 0, 4);

        $series = null;
        if ($parent === null) {
            $lk = $db->prepare('SELECT * FROM inv_number_series WHERE entity_code = :k_entity AND doc_type = \'quotation\' AND period_year = :k_year FOR UPDATE');
            $lk->execute(['k_entity' => $q['entity_code'], 'k_year' => $year]);
            $series = $lk->fetch() ?: null;
        }
        $ready = $settings ? inv_quote_readiness($db, (string) $q['entity_code'], $year) : [['field' => 'entity', 'message' => 'Unknown issuing entity.']];
        if ($parent !== null) {
            $ready = array_values(array_filter($ready, fn($p) => $p['field'] !== 'series' && $p['field'] !== 'quote_prefix'));
        }
        $problems = array_merge($ready, inv_module_problems($db), inv_quote_content_problems($db, $built));
        if (!$problems && $parent === null && $series) {
            if ((int) $series['next_value'] <= inv_max_issued_seq($db, $settings, 'quotation', $year)) {
                $problems[] = ['field' => 'series', 'message' => 'The quotation counter is behind numbers already used. Confirm the next number again in Settings.'];
            }
        }
        if ($problems) {
            $db->rollBack();
            return ['ok' => false, 'http' => 409, 'error' => 'This quotation cannot be finalised yet.', 'problems' => $problems];
        }

        if ($parent !== null) {
            $base = (string) $parent['base_no'];
            $revNo = (int) $parent['revision_no'] + 1;
            $number = $base . '-R' . $revNo;
        } else {
            $base = inv_format_doc_number((string) $settings['quote_prefix'], $year, (int) $series['next_value'], (int) $settings['seq_digits']);
            $revNo = 0;
            $number = $base;
            $up = $db->prepare('UPDATE inv_number_series SET next_value = next_value + 1 WHERE entity_code = :n_entity AND doc_type = \'quotation\' AND period_year = :n_year');
            $up->execute(['n_entity' => $q['entity_code'], 'n_year' => $year]);
        }
        $taken = $db->prepare('SELECT COUNT(*) FROM inv_quotations WHERE entity_code = :t_entity AND quote_no = :t_no');
        $taken->execute(['t_entity' => $q['entity_code'], 't_no' => $number]);
        if ((int) $taken->fetchColumn() > 0) {
            $db->rollBack();
            return ['ok' => false, 'http' => 409, 'error' => "Number $number is already used. Confirm the next number again in Settings.", 'problems' => []];
        }

        $paper = INV_QUOTE_PAPER; // quotations are always A4 (the owner's sample is A4)
        $stampOk = inv_stamp_path($settings['stamp_filename'] ?? null) !== null && !inv_stamp_problems($settings['stamp_filename']);
        $snap = inv_quote_entity_snapshot($settings, $paper, $stampOk);

        // PDF with the stamp (if configured), written to its final ID-based path
        // BEFORE the row is marked sent (the CHECK requires pdf_file once sent).
        $html = inv_quote_pdf_html(inv_quote_doc_model($built, $settings, $number, $paper, $snap['stamp_printed']));
        $pdf = inv_pdf_render($html, $paper, null, 'Quotation ' . $number);
        $root = inv_storage_status($db)['root'];
        if ($root === null) throw new RuntimeException('PDF storage is not available.');
        $written = inv_storage_write_new($root, 'quotations/' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $q['entity_code']) . '/' . $year, 'q' . $id, $pdf);

        $u = $db->prepare(
            'UPDATE inv_quotations SET status = \'sent\', quote_no = :s_no, base_no = :s_base, revision_no = :s_rev,
                sent_at = NOW(), sent_by = :s_by, sent_by_name = :s_by_name, quote_date = :s_date, valid_days = :s_days,
                valid_until = :s_until, total_list = :s_tl, total_special = :s_ts, bill_to_name = :s_bn,
                bill_to_address = :s_ba, bill_to_attention = :s_bat, bill_to_email = :s_be,
                entity_snapshot = :s_snap, pdf_file = :s_file, pdf_sha256 = :s_sha, row_version = row_version + 1
             WHERE id = :s_id AND status = \'draft\''
        );
        $u->execute([
            's_no' => $number, 's_base' => $base, 's_rev' => $revNo, 's_by' => (int) $user['id'], 's_by_name' => $user['name'],
            's_date' => $h['quote_date'], 's_days' => $h['valid_days'], 's_until' => $h['valid_until'],
            's_tl' => inv_cents_to_decimal($h['total_list_c']), 's_ts' => inv_cents_to_decimal($h['total_special_c']),
            's_bn' => $h['bill_to_name'], 's_ba' => $h['bill_to_address'], 's_bat' => $h['bill_to_attention'],
            's_be' => $h['bill_to_email'], 's_snap' => json_encode($snap, JSON_UNESCAPED_UNICODE),
            's_file' => $written['rel'], 's_sha' => $written['sha256'], 's_id' => $id,
        ]);
        if ($u->rowCount() !== 1) throw new RuntimeException('Draft could not be marked as sent.');
        inv_save_quote_lines($db, $id, $built['lines']);

        if ($parent !== null) {
            $sp = $db->prepare(
                'UPDATE inv_quotations SET status = \'superseded\', closed_at = NOW(), closed_by_name = :c_name, closed_reason = :c_reason
                 WHERE id = :c_id AND status = \'sent\''
            );
            $sp->execute(['c_name' => $user['name'], 'c_reason' => 'Superseded by ' . $number, 'c_id' => (int) $parent['id']]);
            inv_log_activity((int) $user['id'], 'supersede_quotation', 'inv_quotation', (int) $parent['id'], $parent['quote_no'] . ' superseded by ' . $number);
        }
        inv_log_activity((int) $user['id'], 'send_quotation', 'inv_quotation', $id,
            "$number finalised and sent by {$user['name']} ({$q['entity_code']}, special total " . inv_cents_to_decimal($h['total_special_c'])
            . ', stamp ' . ($snap['stamp_printed'] ? 'printed (sha256 ' . $snap['stamp_sha256'] . ')' : 'not configured')
            . ", pdf sha256 {$written['sha256']})" . ($written['orphan'] ? '; moved aside orphan file ' . basename($written['orphan']) : ''));
        $db->commit();
        return ['ok' => true, 'quotation' => inv_fetch_quote($db, $id)];
    } catch (Throwable $ex) {
        if ($db->inTransaction()) $db->rollBack();
        if ($written !== null && is_file($written['abs'])) @unlink($written['abs']);
        error_log('inv quote finalise ' . $id . ': ' . $ex->getMessage());
        return ['ok' => false, 'http' => 500, 'error' => 'Finalising failed and was fully rolled back (no number used, no file kept). ' . $ex->getMessage(), 'problems' => []];
    }
}

/** Create the next revision draft of a sent quotation. Returns the new id. */
function inv_revise_quote(PDO $db, int $id, string $rowHash, array $user): array
{
    $db->beginTransaction();
    $q = inv_fetch_quote($db, $id, true);
    if (!$q) { $db->rollBack(); return ['ok' => false, 'http' => 404, 'error' => 'Quotation not found.']; }
    if ($q['status'] !== 'sent') { $db->rollBack(); return ['ok' => false, 'http' => 409, 'error' => 'Only a sent quotation awaiting a reply can be revised.']; }
    if (!hash_equals(inv_row_hash($q), $rowHash)) { $db->rollBack(); return ['ok' => false, 'http' => 409, 'error' => 'This quotation was changed since you opened it. Reload and try again.']; }
    $chk = $db->prepare('SELECT id FROM inv_quotations WHERE revision_of_id = :r_of AND status = \'draft\'');
    $chk->execute(['r_of' => $id]);
    $existing = $chk->fetchColumn();
    if ($existing !== false) {
        $db->rollBack();
        return ['ok' => false, 'http' => 409, 'error' => 'A revision draft already exists for this quotation.', 'id' => (int) $existing];
    }
    $in = inv_quote_row_to_input($db, $q);
    $in['quote_date'] = inv_today();
    $built = inv_build_quote($db, $in, null);
    if ($built['errors']) {
        $db->rollBack();
        return ['ok' => false, 'http' => 422, 'error' => 'The revision needs attention: ' . implode(' ', $built['errors'])];
    }
    $newId = inv_save_quote($db, null, $built['header'], $built['lines'], $user,
        ['revision_of_id' => $id, 'revision_no' => (int) $q['revision_no'] + 1, 'base_no' => $q['base_no']]);
    inv_log_activity((int) $user['id'], 'revise_quotation', 'inv_quotation', $newId,
        'Revision draft ' . $q['base_no'] . '-R' . ((int) $q['revision_no'] + 1) . ' created from ' . $q['quote_no']);
    $db->commit();
    return ['ok' => true, 'id' => $newId];
}

/**
 * Record acceptance. $file: null or ['bytes' => ..., 'ext' => pdf|jpg|png,
 * 'mime' => ..., 'name' => original name] ALREADY validated by the caller.
 */
function inv_accept_quote(PDO $db, int $id, string $rowHash, array $user, string $acceptedOn, string $acceptedBy, ?string $note, ?array $file): array
{
    $written = null;
    $db->beginTransaction();
    try {
        $q = inv_fetch_quote($db, $id, true);
        if (!$q) { $db->rollBack(); return ['ok' => false, 'http' => 404, 'error' => 'Quotation not found.']; }
        if ($q['status'] !== 'sent') { $db->rollBack(); return ['ok' => false, 'http' => 409, 'error' => 'Only a sent quotation can be accepted.']; }
        if (!hash_equals(inv_row_hash($q), $rowHash)) { $db->rollBack(); return ['ok' => false, 'http' => 409, 'error' => 'This quotation was changed since you opened it. Reload and try again.']; }
        if ($acceptedOn < $q['quote_date']) { $db->rollBack(); return ['ok' => false, 'http' => 422, 'error' => 'The acceptance date cannot be before the quotation date.', 'fields' => ['accepted_on' => 'Before the quotation date.']]; }

        $rel = null; $sha = null; $mime = null; $orig = null;
        if ($file !== null) {
            $root = inv_storage_status($db)['root'];
            if ($root === null) throw new RuntimeException('File storage is not available.');
            $year = (int) substr((string) $q['quote_date'], 0, 4);
            $written = inv_storage_write_new($root, 'acceptances/' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $q['entity_code']) . '/' . $year,
                'q' . $id . '-signed-' . bin2hex(random_bytes(6)), $file['bytes'], $file['ext']);
            $rel = $written['rel']; $sha = $written['sha256']; $mime = $file['mime']; $orig = mb_substr((string) $file['name'], 0, 255);
        }
        $u = $db->prepare(
            'UPDATE inv_quotations SET status = \'accepted\', accepted_on = :a_on, accepted_by_name = :a_by,
                acceptance_note = :a_note, accepted_recorded_at = NOW(), accepted_recorded_by = :a_rec,
                accepted_recorded_by_name = :a_rec_name, signed_file = :a_file, signed_sha256 = :a_sha,
                signed_mime = :a_mime, signed_original_name = :a_orig, row_version = row_version + 1
             WHERE id = :a_id AND status = \'sent\''
        );
        $u->execute([
            'a_on' => $acceptedOn, 'a_by' => $acceptedBy, 'a_note' => $note, 'a_rec' => (int) $user['id'],
            'a_rec_name' => $user['name'], 'a_file' => $rel, 'a_sha' => $sha, 'a_mime' => $mime, 'a_orig' => $orig, 'a_id' => $id,
        ]);
        if ($u->rowCount() !== 1) throw new RuntimeException('Quotation could not be marked accepted.');
        $late = inv_quote_is_expired($q) ? ' (after its validity date ' . $q['valid_until'] . ')' : '';
        inv_log_activity((int) $user['id'], 'accept_quotation', 'inv_quotation', $id,
            "{$q['quote_no']} accepted on $acceptedOn by client signatory '$acceptedBy'$late; recorded by {$user['name']}"
            . ($sha ? "; signed copy sha256 $sha" : '; no signed copy uploaded'));
        $db->commit();
        return ['ok' => true];
    } catch (Throwable $ex) {
        if ($db->inTransaction()) $db->rollBack();
        if ($written !== null && is_file($written['abs'])) @unlink($written['abs']);
        error_log('inv quote accept ' . $id . ': ' . $ex->getMessage());
        return ['ok' => false, 'http' => 500, 'error' => 'Recording the acceptance failed and was rolled back. ' . $ex->getMessage()];
    }
}

/** Reject (sent) or cancel (sent, or accepted with nothing invoiced). */
function inv_close_quote(PDO $db, int $id, string $rowHash, array $user, string $action, ?string $reason): array
{
    $db->beginTransaction();
    $q = inv_fetch_quote($db, $id, true);
    if (!$q) { $db->rollBack(); return ['ok' => false, 'http' => 404, 'error' => 'Quotation not found.']; }
    if (!hash_equals(inv_row_hash($q), $rowHash)) { $db->rollBack(); return ['ok' => false, 'http' => 409, 'error' => 'This quotation was changed since you opened it. Reload and try again.']; }
    if ($action === 'reject') {
        if ($q['status'] !== 'sent') { $db->rollBack(); return ['ok' => false, 'http' => 409, 'error' => 'Only a sent quotation can be marked rejected.']; }
        $new = 'rejected';
    } else {
        if (!in_array($q['status'], ['sent', 'accepted'], true)) { $db->rollBack(); return ['ok' => false, 'http' => 409, 'error' => 'Only a sent or accepted quotation can be cancelled.']; }
        if ($reason === null || mb_strlen($reason) < 5) { $db->rollBack(); return ['ok' => false, 'http' => 422, 'error' => 'Give a reason (at least 5 characters).', 'fields' => ['reason' => 'Required.']]; }
        $inv = $db->prepare('SELECT COUNT(*) FROM inv_quotation_lines WHERE quotation_id = :c_q AND invoiced_invoice_id IS NOT NULL');
        $inv->execute(['c_q' => $id]);
        if ((int) $inv->fetchColumn() > 0) { $db->rollBack(); return ['ok' => false, 'http' => 409, 'error' => 'Lines of this quotation have been invoiced; it cannot be cancelled.']; }
        $new = 'cancelled';
    }
    $u = $db->prepare('UPDATE inv_quotations SET status = :c_status, closed_reason = :c_reason, closed_at = NOW(), closed_by_name = :c_name, row_version = row_version + 1 WHERE id = :c_id');
    $u->execute(['c_status' => $new, 'c_reason' => $reason, 'c_name' => $user['name'], 'c_id' => $id]);
    inv_log_activity((int) $user['id'], $action . '_quotation', 'inv_quotation', $id, $q['quote_no'] . ' ' . $new . ($reason ? ": $reason" : ''));
    $db->commit();
    return ['ok' => true];
}

/**
 * Convert ticked item lines of an ACCEPTED quotation into ONE new draft
 * invoice (Special Rate, or Rate when blank). Each line can be invoiced once;
 * deleting the draft invoice frees its lines (FK ON DELETE SET NULL).
 */
function inv_convert_quote(PDO $db, int $id, array $lineIds, ?int $categoryId, array $user): array
{
    $lineIds = array_values(array_unique(array_filter(array_map('intval', $lineIds), fn($x) => $x > 0)));
    if (!$lineIds) return ['ok' => false, 'http' => 422, 'error' => 'Tick at least one line to invoice.'];
    $db->beginTransaction();
    try {
        $q = inv_fetch_quote($db, $id, true);
        if (!$q) { $db->rollBack(); return ['ok' => false, 'http' => 404, 'error' => 'Quotation not found.']; }
        if ($q['status'] !== 'accepted') { $db->rollBack(); return ['ok' => false, 'http' => 409, 'error' => 'Only accepted quotations can be converted to an invoice.']; }
        $lines = inv_fetch_quote_lines($db, $id, true);
        $byId = [];
        foreach ($lines as $l) $byId[(int) $l['id']] = $l;
        $picked = [];
        foreach ($lineIds as $lid) {
            $l = $byId[$lid] ?? null;
            if (!$l) { $db->rollBack(); return ['ok' => false, 'http' => 422, 'error' => 'A selected line does not belong to this quotation.']; }
            if ($l['line_type'] !== 'item') { $db->rollBack(); return ['ok' => false, 'http' => 422, 'error' => 'Sum rows cannot be invoiced; tick the item lines instead.']; }
            if ($l['invoiced_invoice_id'] !== null) { $db->rollBack(); return ['ok' => false, 'http' => 409, 'error' => 'Line "' . $l['title'] . '" has already been invoiced.']; }
            $picked[] = $l;
        }
        usort($picked, fn($a, $b) => (int) $a['line_no'] <=> (int) $b['line_no']);
        $input = [
            'entity_code' => $q['entity_code'], 'party_id' => $q['party_id'], 'category_id' => $categoryId,
            'invoice_date' => inv_today(), 'bill_to_name' => $q['bill_to_name'], 'bill_to_address' => $q['bill_to_address'],
            'bill_to_attention' => $q['bill_to_attention'], 'bill_to_email' => $q['bill_to_email'],
            'internal_notes' => 'From quotation ' . $q['quote_no'],
            'lines' => array_map(fn($l) => [
                'service_id' => $l['service_id'] !== null ? (int) $l['service_id'] : null,
                'title' => $l['title'], 'description' => $l['description'], 'qty' => $l['qty'],
                'rate' => $l['special_rate'] ?? $l['rate'],
                'entities' => array_map(fn($e) => ['company_name' => $e['company_name'], 'uen' => $e['uen'],
                    'source_client_id' => $e['source_client_id'] !== null ? (int) $e['source_client_id'] : null], $l['entities']),
            ], $picked),
        ];
        $built = inv_build_draft($db, $input, null);
        if ($built['errors']) {
            $db->rollBack();
            return ['ok' => false, 'http' => 422, 'error' => 'The invoice draft needs attention: ' . implode(' ', $built['errors'])];
        }
        $invId = inv_save_draft($db, null, $built['header'], $built['lines'], $user);
        $lk = $db->prepare('UPDATE inv_invoices SET quotation_id = :k_q WHERE id = :k_inv');
        $lk->execute(['k_q' => $id, 'k_inv' => $invId]);
        $ph = implode(',', array_fill(0, count($lineIds), '?'));
        $mk = $db->prepare("UPDATE inv_quotation_lines SET invoiced_invoice_id = ? WHERE quotation_id = ? AND line_type = 'item' AND invoiced_invoice_id IS NULL AND id IN ($ph)");
        $mk->execute(array_merge([$invId, $id], $lineIds));
        if ($mk->rowCount() !== count($lineIds)) throw new RuntimeException('Some lines were invoiced by someone else at the same time.');
        inv_log_activity((int) $user['id'], 'convert_quotation', 'inv_quotation', $id,
            $q['quote_no'] . ': ' . count($picked) . ' line(s) converted to draft invoice #' . $invId);
        inv_log_activity((int) $user['id'], 'create_invoice_draft', 'inv_invoice', $invId, 'Draft created from quotation ' . $q['quote_no']);
        $db->commit();
        return ['ok' => true, 'invoice_id' => $invId];
    } catch (Throwable $ex) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('inv quote convert ' . $id . ': ' . $ex->getMessage());
        return ['ok' => false, 'http' => 500, 'error' => 'Conversion failed and was rolled back. ' . $ex->getMessage()];
    }
}

/** Overview metrics (agreed in the Stage 3B plan). Superseded/cancelled excluded. */
function inv_quote_metrics(PDO $db): array
{
    $today = inv_today();
    $in7 = inv_add_days($today, 7);
    $since = inv_add_days($today, -90);
    $s = $db->prepare(
        "SELECT
            SUM(status = 'sent' AND valid_until >= :m_t1) AS open_n,
            COALESCE(SUM(CASE WHEN status = 'sent' AND valid_until >= :m_t2 THEN total_special ELSE 0 END), 0) AS open_value,
            SUM(status = 'sent' AND valid_until >= :m_t3 AND valid_until <= :m_in7) AS expiring_n,
            SUM(status = 'accepted' AND sent_at >= :m_s1) AS acc_n,
            SUM(status = 'rejected' AND sent_at >= :m_s2) AS rej_n,
            SUM(status = 'sent' AND valid_until < :m_t4 AND sent_at >= :m_s3) AS exp_n,
            AVG(CASE WHEN status = 'accepted' AND accepted_on >= :m_s4 THEN DATEDIFF(accepted_on, DATE(sent_at)) END) AS avg_days
         FROM inv_quotations"
    );
    $s->execute(['m_t1' => $today, 'm_t2' => $today, 'm_t3' => $today, 'm_in7' => $in7, 'm_s1' => $since,
                 'm_s2' => $since, 'm_t4' => $today, 'm_s3' => $since, 'm_s4' => $since]);
    $r = $s->fetch();
    $u = $db->query(
        "SELECT COALESCE(SUM(l.special_amount), 0) FROM inv_quotation_lines l
         JOIN inv_quotations q ON q.id = l.quotation_id
         LEFT JOIN inv_invoices i ON i.id = l.invoiced_invoice_id
         WHERE q.status = 'accepted' AND l.line_type = 'item' AND (l.invoiced_invoice_id IS NULL OR i.status = 'draft')"
    )->fetchColumn();
    $decided = (int) $r['acc_n'] + (int) $r['rej_n'] + (int) $r['exp_n'];
    return [
        'open_count' => (int) $r['open_n'], 'open_value' => $r['open_value'],
        'expiring_7d' => (int) $r['expiring_n'],
        'acceptance_rate_90d' => $decided > 0 ? round(100 * (int) $r['acc_n'] / $decided) : null,
        'accepted_not_invoiced' => inv_cents_to_decimal(inv_decimal_to_cents($u)),
        'avg_days_to_accept' => $r['avg_days'] !== null ? round((float) $r['avg_days'], 1) : null,
    ];
}