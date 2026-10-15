<?php
/**
 * invoice-system/api/number_series.php — confirm / set the next document number
 *
 * POST {entity_code, doc_type, period_year, next_value, acknowledge: true}
 *   Creates the series row if missing (e.g. 2027), sets next_value and marks
 *   it confirmed. Runs in a transaction holding SELECT ... FOR UPDATE on the
 *   series row: the same lock the issue endpoint (Stage 3) takes, so a
 *   confirmation can never interleave with an issue.
 *
 * Rules:
 *   - acknowledge must be true: the super_admin confirms the format
 *     ASSUMPTION ([prefix] + YY + N digits, yearly reset; README §2.3).
 *   - The prefix for that document type must be decided first (not TBC;
 *     blank is allowed and means "no prefix").
 *   - next_value must be greater than the highest sequence already issued in
 *     that series, and must fit in seq_digits. It may move backwards only
 *     onto numbers that were never issued (UNIQUE key is the final guard).
 *   - period_year: last year, this year or next year only.
 * Nothing is ever auto-assumed: a series without a confirmed row blocks issuing.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/inv_helpers.php';

$user = ehs_require_role(INV_ROLES, true);
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    inv_json_error('Method not allowed.', 405);
}
ehs_verify_csrf();
$in = inv_json_input();

$code = inv_clean_str($in['entity_code'] ?? null, 20);
$docType = $in['doc_type'] ?? '';
$thisYear = (int) date('Y');
$year = inv_int_or_null($in['period_year'] ?? null, $thisYear - 1, $thisYear + 1);
$nextValue = inv_int_or_null($in['next_value'] ?? null, 1, 999999);

$entities = inv_get_entities($db, true);
if ($code === null || !isset($entities[$code])) inv_json_error('Unknown issuing entity.', 422);
if (!in_array($docType, INV_DOC_TYPES, true)) inv_json_error('Unknown document type.', 422);
if (!$year) inv_json_error('Year must be last year, this year or next year.', 422);
if (!$nextValue) inv_json_error('Next number must be a whole number of 1 or more.', 422, ['fields' => ['next_value' => 'Required.']]);
if (($in['acknowledge'] ?? false) !== true) {
    inv_json_error('Tick the confirmation box to accept the number format.', 422, ['fields' => ['acknowledge' => 'Required.']]);
}

$e = $entities[$code];
if ($docType === 'quotation' && !array_key_exists('quote_prefix', $e)) {
    inv_json_error('Run db/inv_migration_004.sql first.', 409);
}
$prefix = inv_series_prefix($e, $docType);
$prefixLabel = ['invoice' => 'Invoice number prefix', 'credit_note' => 'Credit-note number prefix',
                'quotation' => 'Quotation number prefix'][$docType];
if (inv_is_tbc($prefix)) {
    inv_json_error("$prefixLabel is still TBC. Set it on the entity settings first (leave blank for no prefix).", 422);
}
$digits = (int) $e['seq_digits'];
if ($nextValue >= 10 ** $digits) {
    inv_json_error("Next number $nextValue does not fit in $digits digits.", 422, ['fields' => ['next_value' => 'Too large.']]);
}

$db->beginTransaction();
try {
    $lock = $db->prepare(
        'SELECT * FROM inv_number_series
         WHERE entity_code = :l_entity AND doc_type = :l_type AND period_year = :l_year FOR UPDATE'
    );
    $lock->execute(['l_entity' => $code, 'l_type' => $docType, 'l_year' => $year]);
    $row = $lock->fetch();

    $maxIssued = inv_max_issued_seq($db, $e, $docType, $year);
    if ($nextValue <= $maxIssued) {
        $db->rollBack();
        inv_json_error("Number " . inv_format_doc_number($prefix, $year, $maxIssued, $digits)
            . " has already been issued. The next number must be greater than $maxIssued.", 422,
            ['fields' => ['next_value' => 'Already used.']]);
    }

    if ($row === false) {
        $ins = $db->prepare(
            'INSERT INTO inv_number_series
                (entity_code, doc_type, period_year, next_value, is_confirmed, confirmed_by, confirmed_by_name, confirmed_at)
             VALUES (:i_entity, :i_type, :i_year, :i_next, 1, :i_by, :i_by_name, NOW())'
        );
        $ins->execute([
            'i_entity' => $code, 'i_type' => $docType, 'i_year' => $year, 'i_next' => $nextValue,
            'i_by' => (int) $user['id'], 'i_by_name' => $user['name'],
        ]);
        $beforeTxt = 'none';
    } else {
        $upd = $db->prepare(
            'UPDATE inv_number_series
             SET next_value = :u_next, is_confirmed = 1, confirmed_by = :u_by, confirmed_by_name = :u_by_name, confirmed_at = NOW()
             WHERE entity_code = :u_entity AND doc_type = :u_type AND period_year = :u_year'
        );
        $upd->execute([
            'u_next' => $nextValue, 'u_by' => (int) $user['id'], 'u_by_name' => $user['name'],
            'u_entity' => $code, 'u_type' => $docType, 'u_year' => $year,
        ]);
        $beforeTxt = ($row['is_confirmed'] ? 'confirmed ' : 'locked ') . ($row['next_value'] ?? 'NULL');
    }
    if ($docType === 'quotation' && (int) $e['quote_prefix_confirmed'] !== 1) {
        // Confirming the quotation series also confirms the (seeded, UNCONFIRMED) prefix.
        $qp = $db->prepare('UPDATE inv_settings SET quote_prefix_confirmed = 1 WHERE entity_code = :qp_entity');
        $qp->execute(['qp_entity' => $code]);
    }
    $db->commit();
} catch (Throwable $ex) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('inv number_series: ' . $ex->getMessage());
    inv_json_error('Could not save the number series. Please try again.', 500);
}

$preview = inv_format_doc_number($prefix, $year, $nextValue, $digits);
inv_log_activity((int) $user['id'], 'confirm_number_series', 'inv_number_series', null,
    "$code $docType $year: $beforeTxt -> confirmed $nextValue (next number $preview; format prefix+YY+$digits digits acknowledged"
    . ($docType === 'quotation' ? "; quotation prefix '$prefix' confirmed" : '') . ')');

inv_json_response(['ok' => true, 'preview' => $preview]);
