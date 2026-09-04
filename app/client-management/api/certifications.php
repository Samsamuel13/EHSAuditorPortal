<?php
/**
 * client-management/api/certifications.php
 *
 * GET    ?client_id=X          -> all certifications for one client (with scheme name/category joined,
 *                                  plus a computed expiry status badge)
 * GET    ?id=X                 -> single certification
 * POST   { cm_client_id, ... } -> create a certification for a client
 * PUT    ?id=X { ... }         -> update a certification (including status/cycle_stage changes)
 *
 * No DELETE handler: certifications are historical audit/compliance records
 * (same reasoning as cm_clients) — use PUT to set status to 'withdrawn'.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/cm_helpers.php';

$user = ehs_require_role(['super_admin', 'admin'], true);
$db = get_db();

// Live auto-expire: keeps the Status column truthful on every load, not
// just after a daily cron run. Cheap single UPDATE, safe to run per-request.
cm_auto_expire_overdue_certifications($db);
$method = $_SERVER['REQUEST_METHOD'];
$today = date('Y-m-d');

const CM_CERT_SELECT = "
    SELECT cert.*, st.name AS scheme_name, st.category AS scheme_category
    FROM cm_certifications cert
    JOIN cm_scheme_types st ON st.id = cert.cm_scheme_type_id
";

if ($method === 'GET' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    if ($id <= 0) cm_json_error('A valid certification id is required.', 422);

    $stmt = $db->prepare(CM_CERT_SELECT . ' WHERE cert.id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $cert = $stmt->fetch();
    if (!$cert) cm_json_error('Certification not found.', 404);

    $cert['expiry_badge'] = cm_expiry_badge($cert['expiry_date'], $today);
    $cert['next_due'] = cm_certification_next_due($cert, $today);
    cm_json_response(['certification' => $cert]);
}

if ($method === 'GET') {
    $clientId = (int) ($_GET['client_id'] ?? 0);
    if ($clientId <= 0) cm_json_error('A valid client_id is required.', 422);

    $stmt = $db->prepare(CM_CERT_SELECT . ' WHERE cert.cm_client_id = :client_id ORDER BY cert.expiry_date IS NULL, cert.expiry_date ASC');
    $stmt->execute(['client_id' => $clientId]);
    $certs = $stmt->fetchAll();

    foreach ($certs as &$cert) {
        $cert['expiry_badge'] = cm_expiry_badge($cert['expiry_date'], $today);
        $cert['next_due'] = cm_certification_next_due($cert, $today);
    }
    unset($cert);
    usort($certs, fn($a, $b) => strcmp($a['next_due']['date'] ?? '9999-99-99', $b['next_due']['date'] ?? '9999-99-99'));

    cm_json_response(['certifications' => $certs]);
}

/** Shared field extraction/validation for POST and PUT. */
function cm_extract_cert_fields(array $input, PDO $db): array
{
    $schemeTypeId = (int) ($input['cm_scheme_type_id'] ?? 0);
    if ($schemeTypeId <= 0) cm_json_error('A scheme type is required.', 422);

    $schemeCheck = $db->prepare('SELECT id, default_cycle_years FROM cm_scheme_types WHERE id = :id LIMIT 1');
    $schemeCheck->execute(['id' => $schemeTypeId]);
    $schemeRow = $schemeCheck->fetch();
    if (!$schemeRow) cm_json_error('That scheme type does not exist.', 422);
    $cycleYears = (int) $schemeRow['default_cycle_years'];

    $cycleStage = trim((string) ($input['cycle_stage'] ?? 'initial'));
    if (!in_array($cycleStage, ['initial', 'surveillance_1', 'surveillance_2', 'recertification'], true)) {
        cm_json_error('Invalid cycle stage.', 422);
    }

    $status = trim((string) ($input['status'] ?? 'pending'));
    if (!in_array($status, ['active', 'expired', 'suspended', 'withdrawn', 'pending'], true)) {
        cm_json_error('Invalid status value.', 422);
    }

    foreach ([
        'issue_date' => $input['issue_date'] ?? null,
        'surveillance_1_date' => $input['surveillance_1_date'] ?? null,
        'surveillance_2_date' => $input['surveillance_2_date'] ?? null,
        'expiry_date' => $input['expiry_date'] ?? null,
    ] as $label => $val) {
        if ($val !== null && $val !== '' && !cm_is_valid_date($val)) {
            cm_json_error("Invalid $label — expected YYYY-MM-DD.", 422);
        }
    }
    $issueDate  = ($input['issue_date'] ?? '') !== '' ? $input['issue_date'] : null;
    $surv1Date  = ($input['surveillance_1_date'] ?? '') !== '' ? $input['surveillance_1_date'] : null;
    $surv2Date  = ($input['surveillance_2_date'] ?? '') !== '' ? $input['surveillance_2_date'] : null;
    $expiryDate = ($input['expiry_date'] ?? '') !== '' ? $input['expiry_date'] : null;

    // Auto-populate any missing surveillance/recertification dates from
    // issue_date, following this scheme's own cycle length (+1yr/-1mo per
    // step) — never touches a date that was actually provided.
    [$surv1Date, $surv2Date, $expiryDate] = cm_apply_default_cycle_dates($issueDate, $surv1Date, $surv2Date, $expiryDate, $cycleYears);

    // Chronological order across whichever of the 4 milestones are set —
    // mirrors the client's own planner: 1st Cert -> Surveillance 1 ->
    // Surveillance 2 -> Recertification. Only compares pairs that are both
    // present, so partially-filled-in certifications aren't blocked.
    $ordered = array_values(array_filter([$issueDate, $surv1Date, $surv2Date, $expiryDate], fn($d) => $d !== null));
    $sorted = $ordered;
    sort($sorted);
    if ($ordered !== $sorted) {
        cm_json_error('Milestone dates must be in order: 1st Certification, Surveillance 1, Surveillance 2, Recertification.', 422);
    }

    $responsiblePersonId = (int) ($input['responsible_person_id'] ?? 0);
    if ($responsiblePersonId > 0) {
        $userCheck = $db->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
        $userCheck->execute(['id' => $responsiblePersonId]);
        if (!$userCheck->fetch()) cm_json_error('That responsible person does not exist.', 422);
    } else {
        $responsiblePersonId = null;
    }

    return [
        'cm_scheme_type_id'       => $schemeTypeId,
        'accreditation_body'      => cm_clean_str($input['accreditation_body'] ?? null, 100),
        'certificate_number'      => cm_clean_str($input['certificate_number'] ?? null, 100),
        'issue_date'              => $issueDate,
        'surveillance_1_date'     => $surv1Date,
        'surveillance_2_date'     => $surv2Date,
        'expiry_date'             => $expiryDate,
        'cycle_stage'             => $cycleStage,
        'status'                  => $status,
        'responsible_person_id'   => $responsiblePersonId,
        'responsible_person_name' => cm_clean_str($input['responsible_person_name'] ?? null, 150),
        'notes'                   => cm_clean_str($input['notes'] ?? null, 65535),
    ];
}

if ($method === 'POST') {
    ehs_verify_csrf();
    $input = cm_json_input();

    $clientId = (int) ($input['cm_client_id'] ?? 0);
    if ($clientId <= 0) cm_json_error('A valid cm_client_id is required.', 422);
    $clientCheck = $db->prepare('SELECT id, company_name FROM cm_clients WHERE id = :id LIMIT 1');
    $clientCheck->execute(['id' => $clientId]);
    $client = $clientCheck->fetch();
    if (!$client) cm_json_error('Client not found.', 404);

    $fields = cm_extract_cert_fields($input, $db);
    $fields['cm_client_id'] = $clientId;

    $stmt = $db->prepare(
        'INSERT INTO cm_certifications
            (cm_client_id, cm_scheme_type_id, accreditation_body, certificate_number, issue_date,
             surveillance_1_date, surveillance_2_date, expiry_date,
             cycle_stage, status, responsible_person_id, responsible_person_name, notes)
         VALUES
            (:cm_client_id, :cm_scheme_type_id, :accreditation_body, :certificate_number, :issue_date,
             :surveillance_1_date, :surveillance_2_date, :expiry_date,
             :cycle_stage, :status, :responsible_person_id, :responsible_person_name, :notes)'
    );
    try {
        $stmt->execute($fields);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            cm_json_error('A certification with that certificate number already exists.', 409);
        }
        throw $e;
    }
    $newId = (int) $db->lastInsertId();

    cm_log_activity($user['id'], 'create_certification', 'cm_certification', $newId,
        $client['company_name'] . ' — ' . ($fields['certificate_number'] ?? 'no cert #'));
    cm_json_response(['certification' => array_merge(['id' => $newId], $fields), 'created' => true], 201);
}

if ($method === 'PUT') {
    ehs_verify_csrf();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) cm_json_error('A valid certification id is required.', 422);

    $existsStmt = $db->prepare('SELECT cert.id, c.company_name FROM cm_certifications cert JOIN cm_clients c ON c.id = cert.cm_client_id WHERE cert.id = :id LIMIT 1');
    $existsStmt->execute(['id' => $id]);
    $existing = $existsStmt->fetch();
    if (!$existing) cm_json_error('Certification not found.', 404);

    $fields = cm_extract_cert_fields(cm_json_input(), $db);

    $stmt = $db->prepare(
        'UPDATE cm_certifications SET
            cm_scheme_type_id = :cm_scheme_type_id, accreditation_body = :accreditation_body,
            certificate_number = :certificate_number, issue_date = :issue_date,
            surveillance_1_date = :surveillance_1_date, surveillance_2_date = :surveillance_2_date,
            expiry_date = :expiry_date,
            cycle_stage = :cycle_stage, status = :status, responsible_person_id = :responsible_person_id,
            responsible_person_name = :responsible_person_name, notes = :notes
         WHERE id = :id'
    );
    try {
        $stmt->execute(array_merge($fields, ['id' => $id]));
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            cm_json_error('Another certification already uses that certificate number.', 409);
        }
        throw $e;
    }

    cm_log_activity($user['id'], 'update_certification', 'cm_certification', $id,
        $existing['company_name'] . ' — ' . ($fields['certificate_number'] ?? 'no cert #'));
    cm_json_response(['success' => true]);
}

cm_json_error('Method not allowed.', 405);