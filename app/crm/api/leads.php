<?php
/**
 * crm/api/leads.php
 *
 * GET    ?id=X                        -> one lead
 * GET    (filters below)              -> list of leads
 *        ?stage=&owner_id=&source=&date_from=&date_to=&q=
 * GET    ?check_duplicates=1&email=&phone=&company=[&exclude_id=]
 *                                      -> possible-duplicate lookup (used live by the New Lead form)
 * POST   { ...lead fields }           -> create lead (runs duplicate check, does NOT block on a match —
 *                                         response includes possible_duplicates so the UI can warn)
 * PUT    ?id=X { ...fields }          -> update lead fields and/or change stage
 *        If 'stage' is present and differs from the current stage:
 *          - logs a crm_lead_stage_history row (from, to, reason, who)
 *          - moving to 'lost' or 'on_hold' REQUIRES a non-empty 'reason'
 *          - moving to 'awarded' triggers the one-way conversion to cm_clients
 *            (guarded against double-conversion; see crm_convert_lead_to_client)
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/crm_helpers.php';

$user = ehs_require_role(['super_admin', 'admin'], true);
$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && isset($_GET['check_duplicates'])) {
    $matches = crm_find_possible_duplicates(
        $db,
        $_GET['email'] ?? null,
        $_GET['phone'] ?? null,
        $_GET['company'] ?? null,
        isset($_GET['exclude_id']) ? (int) $_GET['exclude_id'] : null
    );
    crm_json_response(['possible_duplicates' => $matches]);
}

if ($method === 'GET' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $stmt = $db->prepare(
        'SELECT l.*, COALESCE(u.name, l.owner_name) AS owner_display_name
         FROM crm_leads l LEFT JOIN users u ON u.id = l.owner_id
         WHERE l.id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $lead = $stmt->fetch();
    if (!$lead) crm_json_error('Lead not found.', 404);

    $histStmt = $db->prepare(
        'SELECT * FROM crm_lead_stage_history WHERE crm_lead_id = :id ORDER BY changed_at ASC'
    );
    $histStmt->execute(['id' => $id]);
    $lead['stage_history'] = $histStmt->fetchAll();

    crm_json_response(['lead' => $lead]);
}

if ($method === 'GET') {
    $stage    = trim((string) ($_GET['stage'] ?? ''));
    $ownerId  = (int) ($_GET['owner_id'] ?? 0);
    $source   = trim((string) ($_GET['source'] ?? ''));
    $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
    $dateTo   = trim((string) ($_GET['date_to'] ?? ''));
    $q        = trim((string) ($_GET['q'] ?? ''));

    $where = [];
    $params = [];

    if ($stage !== '' && in_array($stage, CRM_STAGES, true)) {
        $where[] = 'l.stage = :stage';
        $params['stage'] = $stage;
    }
    if ($ownerId > 0) {
        $where[] = 'l.owner_id = :owner_id';
        $params['owner_id'] = $ownerId;
    }
    if ($source !== '' && in_array($source, CRM_SOURCES, true)) {
        $where[] = 'l.source = :source';
        $params['source'] = $source;
    }
    if ($dateFrom !== '' && crm_is_valid_date($dateFrom)) {
        $where[] = 'DATE(l.created_at) >= :date_from';
        $params['date_from'] = $dateFrom;
    }
    if ($dateTo !== '' && crm_is_valid_date($dateTo)) {
        $where[] = 'DATE(l.created_at) <= :date_to';
        $params['date_to'] = $dateTo;
    }
    if ($q !== '') {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
        $where[] = "(l.company_name LIKE :q1 ESCAPE '\\\\' OR l.contact_person LIKE :q2 ESCAPE '\\\\' OR l.email LIKE :q3 ESCAPE '\\\\')";
        $params['q1'] = '%' . $escaped . '%';
        $params['q2'] = '%' . $escaped . '%';
        $params['q3'] = '%' . $escaped . '%';
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $sql = "SELECT l.*, COALESCE(u.name, l.owner_name) AS owner_display_name
            FROM crm_leads l LEFT JOIN users u ON u.id = l.owner_id
            $whereSql
            ORDER BY l.updated_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    crm_json_response(['leads' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    ehs_verify_csrf();
    $input = crm_json_input();

    $companyName = crm_clean_str($input['company_name'] ?? null, 200);
    if ($companyName === null) crm_json_error('company_name is required.', 422);

    $source = trim((string) ($input['source'] ?? 'other'));
    if (!in_array($source, CRM_SOURCES, true)) $source = 'other';

    $ownerId = isset($input['owner_id']) ? (int) $input['owner_id'] : null;
    $ownerName = null;
    if ($ownerId) {
        $ownerStmt = $db->prepare('SELECT name FROM users WHERE id = :id LIMIT 1');
        $ownerStmt->execute(['id' => $ownerId]);
        $ownerName = $ownerStmt->fetchColumn() ?: null;
        if (!$ownerName) $ownerId = null;
    }

    $email = crm_clean_str($input['email'] ?? null, 150);
    $phone = crm_clean_str($input['phone'] ?? null, 30);

    $duplicates = crm_find_possible_duplicates($db, $email, $phone, $companyName);

    $stmt = $db->prepare(
        'INSERT INTO crm_leads
            (company_name, contact_person, contact_designation, phone, email,
             normalized_phone, normalized_email, normalized_company,
             industry_sector, source, stage, owner_id, owner_name, notes)
         VALUES
            (:company_name, :contact_person, :contact_designation, :phone, :email,
             :normalized_phone, :normalized_email, :normalized_company,
             :industry_sector, :source, \'enquiry\', :owner_id, :owner_name, :notes)'
    );
    $stmt->execute([
        'company_name'         => $companyName,
        'contact_person'       => crm_clean_str($input['contact_person'] ?? null, 150),
        'contact_designation'  => crm_clean_str($input['contact_designation'] ?? null, 100),
        'phone'                => $phone,
        'email'                => $email,
        'normalized_phone'     => crm_normalize_phone($phone),
        'normalized_email'     => crm_normalize_email($email),
        'normalized_company'   => crm_normalize_company($companyName),
        'industry_sector'      => crm_clean_str($input['industry_sector'] ?? null, 100),
        'source'               => $source,
        'owner_id'             => $ownerId,
        'owner_name'           => $ownerName,
        'notes'                => crm_clean_str($input['notes'] ?? null, 5000),
    ]);
    $newId = (int) $db->lastInsertId();

    // Seed the stage history with the initial 'enquiry' stage so the
    // timeline always has a starting point.
    $histStmt = $db->prepare(
        'INSERT INTO crm_lead_stage_history (crm_lead_id, from_stage, to_stage, changed_by, changed_by_name)
         VALUES (:lead_id, NULL, \'enquiry\', :changed_by, :changed_by_name)'
    );
    $histStmt->execute(['lead_id' => $newId, 'changed_by' => $user['id'], 'changed_by_name' => $user['name']]);

    crm_log_activity($user['id'], 'create_lead', 'crm_lead', $newId, $companyName);

    crm_json_response(['id' => $newId, 'possible_duplicates' => $duplicates], 201);
}

if ($method === 'PUT') {
    ehs_verify_csrf();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) crm_json_error('A valid lead id is required.', 422);

    $stmt = $db->prepare('SELECT * FROM crm_leads WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $lead = $stmt->fetch();
    if (!$lead) crm_json_error('Lead not found.', 404);

    $input = crm_json_input();

    $fields = [];
    $params = ['id' => $id];

    foreach ([
        'contact_person' => 150, 'contact_designation' => 100, 'phone' => 30,
        'email' => 150, 'industry_sector' => 100, 'notes' => 5000,
    ] as $field => $maxLen) {
        if (array_key_exists($field, $input)) {
            $fields[] = "$field = :$field";
            $params[$field] = crm_clean_str($input[$field], $maxLen);
        }
    }
    if (array_key_exists('company_name', $input)) {
        $companyName = crm_clean_str($input['company_name'], 200);
        if ($companyName === null) crm_json_error('company_name cannot be empty.', 422);
        $fields[] = 'company_name = :company_name';
        $params['company_name'] = $companyName;
        $fields[] = 'normalized_company = :normalized_company';
        $params['normalized_company'] = crm_normalize_company($companyName);
    }
    if (array_key_exists('email', $input)) {
        $fields[] = 'normalized_email = :normalized_email';
        $params['normalized_email'] = crm_normalize_email($input['email']);
    }
    if (array_key_exists('phone', $input)) {
        $fields[] = 'normalized_phone = :normalized_phone';
        $params['normalized_phone'] = crm_normalize_phone($input['phone']);
    }
    if (array_key_exists('source', $input) && in_array($input['source'], CRM_SOURCES, true)) {
        $fields[] = 'source = :source';
        $params['source'] = $input['source'];
    }
    if (array_key_exists('owner_id', $input)) {
        $ownerId = (int) $input['owner_id'];
        $ownerName = null;
        if ($ownerId) {
            $ownerStmt = $db->prepare('SELECT name FROM users WHERE id = :id LIMIT 1');
            $ownerStmt->execute(['id' => $ownerId]);
            $ownerName = $ownerStmt->fetchColumn() ?: null;
            if (!$ownerName) $ownerId = null;
        }
        $fields[] = 'owner_id = :owner_id';
        $fields[] = 'owner_name = :owner_name';
        $params['owner_id'] = $ownerId ?: null;
        $params['owner_name'] = $ownerName;
    }

    $newClientId = null;
    $stageChanged = false;

    if (array_key_exists('stage', $input)) {
        $newStage = trim((string) $input['stage']);
        if (!in_array($newStage, CRM_STAGES, true)) {
            crm_json_error('Invalid stage.', 422);
        }
        if ($newStage !== $lead['stage']) {
            $reason = crm_clean_str($input['reason'] ?? null, 1000);
            if (in_array($newStage, ['lost', 'on_hold'], true) && $reason === null) {
                crm_json_error('A reason is required when moving a lead to Lost or On Hold.', 422);
            }

            $fields[] = 'stage = :stage';
            $params['stage'] = $newStage;
            if ($newStage === 'lost') {
                $fields[] = 'lost_reason = :lost_reason';
                $params['lost_reason'] = $reason;
            }
            if ($newStage === 'on_hold') {
                $fields[] = 'on_hold_reason = :on_hold_reason';
                $params['on_hold_reason'] = $reason;
            }
            $stageChanged = true;
        }
    }

    if ($fields) {
        $sql = 'UPDATE crm_leads SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $db->prepare($sql)->execute($params);
    }

    if ($stageChanged) {
        $histStmt = $db->prepare(
            'INSERT INTO crm_lead_stage_history (crm_lead_id, from_stage, to_stage, reason, changed_by, changed_by_name)
             VALUES (:lead_id, :from_stage, :to_stage, :reason, :changed_by, :changed_by_name)'
        );
        $histStmt->execute([
            'lead_id'         => $id,
            'from_stage'      => $lead['stage'],
            'to_stage'        => $params['stage'],
            'reason'          => $params['lost_reason'] ?? $params['on_hold_reason'] ?? null,
            'changed_by'      => $user['id'],
            'changed_by_name' => $user['name'],
        ]);
        crm_log_activity($user['id'], 'change_stage', 'crm_lead', $id, "{$lead['stage']} -> {$params['stage']}");

        if ($params['stage'] === 'awarded') {
            $refreshStmt = $db->prepare('SELECT * FROM crm_leads WHERE id = :id LIMIT 1');
            $refreshStmt->execute(['id' => $id]);
            $freshLead = $refreshStmt->fetch();
            $newClientId = crm_convert_lead_to_client($db, $freshLead, $user['id'], $user['name']);
        }
    }

    crm_json_response(['success' => true, 'converted_client_id' => $newClientId]);
}

crm_json_error('Method not allowed.', 405);
