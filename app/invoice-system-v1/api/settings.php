<?php
/**
 * invoice-system/api/settings.php — issuing-entity settings (inv_settings)
 *
 * GET -> every entity's settings, its "ready to issue" problems (invoice and
 *        credit note, current year), non-blocking warnings, number series,
 *        available logo files and the client-directory lookup status.
 * PUT {entity_code, row_hash, fields...} -> update one entity (409 when the row
 *        changed since the page loaded it: content-hash optimistic lock).
 *        Whitelisted fields only (INV_SETTINGS_TEXT_FIELDS + gst_status,
 *        gst_rate, default_terms_days, seq_digits, logo_filename).
 *        A value containing "TBC" is accepted as a placeholder, so partial
 *        information can be saved; it keeps issuing blocked.
 *        Number FORMAT (prefixes, seq_digits) is frozen once the entity has
 *        issued any document.
 *        Every change is activity-logged; bank / payee / registration fields
 *        are logged with before and after values (invoice-fraud audit trail).
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/inv_helpers.php';

$user = ehs_require_role(INV_ROLES, true);
$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];

function inv_settings_payload(PDO $db): array
{
    $year = (int) date('Y');
    $entities = inv_get_entities($db, true);

    $seriesRows = $db->query(
        'SELECT entity_code, doc_type, period_year, next_value, is_confirmed, confirmed_by_name, confirmed_at
         FROM inv_number_series ORDER BY entity_code, period_year DESC, doc_type'
    )->fetchAll();

    $out = [];
    foreach ($entities as $code => $e) {
        $series = [];
        foreach ($seriesRows as $s) {
            if ($s['entity_code'] !== $code) continue;
            $prefix = $s['doc_type'] === 'invoice' ? (string) $e['invoice_prefix'] : (string) $e['cn_prefix'];
            $s['preview'] = $s['next_value'] !== null
                ? inv_format_doc_number($prefix, (int) $s['period_year'], (int) $s['next_value'], (int) $e['seq_digits'])
                : null;
            $s['max_issued_seq'] = inv_max_issued_seq($db, $e, $s['doc_type'], (int) $s['period_year']);
            $series[] = $s;
        }
        $out[] = [
            'settings'        => $e,
            'row_hash'        => inv_row_hash($e),
            'gst_status'      => (int) $e['gst_status_confirmed'] !== 1 ? 'unconfirmed'
                                 : ((int) $e['gst_registered'] === 1 ? 'registered' : 'not_registered'),
            'readiness'       => [
                'invoice'     => inv_entity_readiness($db, $code, 'invoice', $year),
                'credit_note' => inv_entity_readiness($db, $code, 'credit_note', $year),
            ],
            'warnings'        => inv_entity_warnings($e),
            'format_frozen'   => inv_entity_has_issued_docs($db, $code),
            'series'          => $series,
        ];
    }

    return [
        'year'           => $year,
        'entities'       => $out,
        'logos'          => inv_available_logos(),
        'client_lookup'  => inv_cm_lookup_status($db),
        'labels'         => INV_FIELD_LABELS,
    ];
}

if ($method === 'GET') {
    inv_json_response(inv_settings_payload($db));
}

if ($method !== 'PUT') {
    inv_json_error('Method not allowed.', 405);
}

ehs_verify_csrf();
$in = inv_json_input();

$code = inv_clean_str($in['entity_code'] ?? null, 20);
if ($code === null || !inv_entity_exists($db, $code)) {
    inv_json_error('Unknown issuing entity.', 422);
}

// Lock the row for the whole check-validate-update sequence, then compare the
// content hash the page loaded with the row as it is now (catches edits made
// in the same second, which an updated_at comparison would miss).
$db->beginTransaction();
$lock = $db->prepare('SELECT * FROM inv_settings WHERE entity_code = :l_entity FOR UPDATE');
$lock->execute(['l_entity' => $code]);
$before = $lock->fetch();
if (!$before || !hash_equals(inv_row_hash($before), (string) ($in['row_hash'] ?? ''))) {
    inv_json_error('These settings were changed by someone else since you opened them. Reload and try again.', 409);
}

$errors = [];
$new = [];

// --- text fields --------------------------------------------------------
$multiline = ['tnc_text'];
$emailFields = ['email', 'reminder_from_email'];
foreach (INV_SETTINGS_TEXT_FIELDS as $field => $max) {
    if (!array_key_exists($field, $in)) {
        $new[$field] = $before[$field];
        continue;
    }
    $v = inv_clean_str($in[$field], $max, in_array($field, $multiline, true));
    $label = INV_FIELD_LABELS[$field] ?? $field;

    if ($v === null) {
        if (!in_array($field, INV_SETTINGS_OPTIONAL_FIELDS, true)) {
            $errors[$field] = "$label is required (enter «TBC» if not known yet).";
        }
        $new[$field] = in_array($field, ['invoice_prefix', 'cn_prefix'], true) ? '' : null;
        continue;
    }
    if (in_array($field, $emailFields, true) && !inv_is_tbc($v) && !inv_is_valid_email($v)) {
        $errors[$field] = "$label is not a valid email address.";
    }
    if (in_array($field, ['invoice_prefix', 'cn_prefix'], true) && $v !== INV_TBC_MARKER
        && !preg_match('/^[A-Za-z0-9\-\/]{1,10}$/', $v)) {
        $errors[$field] = "$label may contain only letters, digits, '-' or '/' (max 10), or be left blank.";
    }
    $new[$field] = $v;
}

// --- GST ----------------------------------------------------------------
$gstStatus = $in['gst_status'] ?? null;
if ($gstStatus === null) {
    $new['gst_registered'] = (int) $before['gst_registered'];
    $new['gst_status_confirmed'] = (int) $before['gst_status_confirmed'];
} elseif (in_array($gstStatus, ['unconfirmed', 'not_registered', 'registered'], true)) {
    $new['gst_status_confirmed'] = $gstStatus === 'unconfirmed' ? 0 : 1;
    $new['gst_registered'] = $gstStatus === 'registered' ? 1 : 0;
} else {
    $errors['gst_status'] = 'Invalid GST status.';
}

$rate = array_key_exists('gst_rate', $in) ? trim((string) $in['gst_rate']) : (string) $before['gst_rate'];
if (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $rate) || (float) $rate > 100) {
    $errors['gst_rate'] = 'GST rate must be a number from 0 to 100 with at most 2 decimals.';
} else {
    $new['gst_rate'] = number_format((float) $rate, 2, '.', '');
}

// --- integers -----------------------------------------------------------
$terms = array_key_exists('default_terms_days', $in) ? inv_int_or_null($in['default_terms_days'], 0, 365) : (int) $before['default_terms_days'];
if ($terms === false || $terms === null) {
    $errors['default_terms_days'] = 'Default payment terms must be a whole number of days from 0 to 365.';
} else {
    $new['default_terms_days'] = $terms;
}

$digits = array_key_exists('seq_digits', $in) ? inv_int_or_null($in['seq_digits'], 3, 6) : (int) $before['seq_digits'];
if ($digits === false || $digits === null) {
    $errors['seq_digits'] = 'Sequence digits must be 3 to 6.';
} else {
    $new['seq_digits'] = $digits;
}

// --- logo ---------------------------------------------------------------
$logo = array_key_exists('logo_filename', $in) ? (string) $in['logo_filename'] : (string) $before['logo_filename'];
if (!in_array($logo, inv_available_logos(), true)) {
    $errors['logo_filename'] = 'Choose a logo file that exists in invoice-system/assets/img/.';
} else {
    $new['logo_filename'] = $logo;
}

// --- number-format freeze ---------------------------------------------------
$formatChanged = (string) $new['invoice_prefix'] !== (string) $before['invoice_prefix']
    || (string) $new['cn_prefix'] !== (string) $before['cn_prefix']
    || (isset($new['seq_digits']) && (int) $new['seq_digits'] !== (int) $before['seq_digits']);
if ($formatChanged && inv_entity_has_issued_docs($db, $code)) {
    $errors['invoice_prefix'] = 'The number format (prefixes, digits) cannot change after documents have been issued for this entity.';
}
if (isset($new['seq_digits']) && (int) $new['seq_digits'] !== (int) $before['seq_digits']) {
    $chk = $db->prepare('SELECT MAX(next_value) FROM inv_number_series WHERE entity_code = :d_entity AND is_confirmed = 1');
    $chk->execute(['d_entity' => $code]);
    $maxNext = (int) $chk->fetchColumn();
    if ($maxNext >= 10 ** (int) $new['seq_digits']) {
        $errors['seq_digits'] = "A confirmed series already uses next number $maxNext, which needs more digits.";
    }
}

if ($errors) {
    inv_json_error('Please correct the highlighted fields.', 422, ['fields' => $errors]);
}

// --- save ---------------------------------------------------------------
$columns = array_merge(array_keys(INV_SETTINGS_TEXT_FIELDS),
    ['gst_registered', 'gst_status_confirmed', 'gst_rate', 'default_terms_days', 'seq_digits', 'logo_filename']);

$changed = [];
foreach ($columns as $c) {
    if ((string) $new[$c] !== (string) $before[$c]) $changed[] = $c;
}
if (!$changed) {
    $db->rollBack();
    inv_json_response(inv_settings_payload($db) + ['changed' => []]);
}

// Column names come from the fixed whitelist above, never from input.
$set = [];
$params = [];
foreach ($changed as $c) {
    $set[] = "`$c` = :v_$c";
    $params["v_$c"] = $new[$c];
}
$set[] = '`updated_by` = :v_updated_by';
$set[] = '`updated_by_name` = :v_updated_by_name';
$params['v_updated_by'] = (int) $user['id'];
$params['v_updated_by_name'] = $user['name'];
$params['w_entity'] = $code;

$stmt = $db->prepare('UPDATE inv_settings SET ' . implode(', ', $set) . ' WHERE entity_code = :w_entity');
$stmt->execute($params);

$details = [];
foreach ($changed as $c) {
    if (in_array($c, INV_SETTINGS_SENSITIVE_FIELDS, true)) {
        $details[] = "$c: '" . $before[$c] . "' -> '" . $new[$c] . "'";
    } else {
        $details[] = $c;
    }
}
inv_log_activity((int) $user['id'], 'update_entity_settings', 'inv_settings', null,
    "$code: " . implode('; ', $details));
$db->commit();

inv_json_response(inv_settings_payload($db) + ['changed' => $changed]);
