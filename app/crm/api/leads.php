<?php
/**
 * crm/api/leads.php
 *
 * GET  ?view=board        -> leads in the 5 funnel stages (default), for the Kanban board
 * GET  ?view=lost_on_hold -> leads in the 2 side-branch stages
 * GET  ?id=123            -> a single lead
 *   Optional filters (combinable with any GET list): owner_id, source, q (search company/contact)
 * POST -> create a new lead. Always created at stage='enquiry' — this
 *         endpoint never sets stage to anything else; that keeps stage
 *         history writes centralized in lead_stage.php only.
 * PUT  -> update a lead's own fields (company_name, contact_person,
 *         contact_designation, phone, email, industry_sector, source,
 *         owner_id, notes). Does NOT touch stage, lost_reason,
 *         on_hold_reason, converted_client_id, or converted_at — those
 *         are lead_stage.php's responsibility only, so every stage change
 *         reliably produces a crm_lead_stage_history row and, for
 *         'awarded', the conversion side-effect. No DELETE — no hard
 *         delete of leads, consistent with client-management's convention
 *         for clients/certifications.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/crm_helpers.php';

$user = ehs_require_role(['super_admin', 'admin'], true);
$db = get_db();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Single lead
    if (isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        $stmt = $db->prepare('SELECT * FROM crm_leads WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $lead = $stmt->fetch();
        if (!$lead) {
            crm_json_error('Lead not found.', 404);
        }
        crm_json_response(['lead' => $lead]);
    }

    // List — board (funnel stages) or the lost/on-hold side view
    $view = ($_GET['view'] ?? 'board') === 'lost_on_hold' ? 'lost_on_hold' : 'board';
    $stages = $view === 'lost_on_hold' ? CRM_SIDE_STAGES : CRM_FUNNEL_STAGES;

    // Build the stage IN (...) list with distinct positional placeholders —
    // never a repeated named placeholder for a multi-value list.
    $stagePlaceholders = implode(',', array_fill(0, count($stages), '?'));
    $params = $stages;
    $where = ["stage IN ($stagePlaceholders)"];

    if (!empty($_GET['owner_id'])) {
        $where[] = 'owner_id = ?';
        $params[] = (int) $_GET['owner_id'];
    }
    if (!empty($_GET['source']) && crm_is_valid_source($_GET['source'])) {
        $where[] = 'source = ?';
        $params[] = $_GET['source'];
    }
    if (!empty($_GET['q'])) {
        $q = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($_GET['q'])) . '%';
        $where[] = "(company_name LIKE ? ESCAPE '\\\\' OR contact_person LIKE ? ESCAPE '\\\\')";
        $params[] = $q;
        $params[] = $q;
    }

    $sql = 'SELECT id, company_name, contact_person, contact_designation, phone, email,
                   industry_sector, source, stage, owner_id, owner_name,
                   lost_reason, on_hold_reason, converted_client_id, converted_at,
                   created_at, updated_at
            FROM crm_leads
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY updated_at DESC
            LIMIT 1000';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $leads = $stmt->fetchAll();

    // Attach a server-computed "days in stage" so the board never has to
    // do date math against a raw timestamp string in JS.
    foreach ($leads as &$lead) {
        $lead['days_in_stage'] = crm_days_since($lead['updated_at']);
    }
    unset($lead);

    crm_json_response(['leads' => $leads, 'view' => $view]);
}

if ($method === 'POST') {
    ehs_verify_csrf();
    $input = crm_json_input();

    $companyName = crm_clean_str($input['company_name'] ?? null, 200);
    if ($companyName === null) {
        crm_json_error('company_name is required.');
    }

    $source = trim((string) ($input['source'] ?? 'other'));
    if (!crm_is_valid_source($source)) {
        crm_json_error('Invalid source value.');
    }

    $contactPerson = crm_clean_str($input['contact_person'] ?? null, 150);
    $contactDesignation = crm_clean_str($input['contact_designation'] ?? null, 100);
    $phone = crm_clean_str($input['phone'] ?? null, 30);
    $email = crm_clean_str($input['email'] ?? null, 150);
    $industrySector = crm_clean_str($input['industry_sector'] ?? null, 100);
    $notes = crm_clean_str($input['notes'] ?? null, 65000);

    $ownerId = !empty($input['owner_id']) ? (int) $input['owner_id'] : null;
    $ownerName = null;
    if ($ownerId !== null) {
        $ownerStmt = $db->prepare("SELECT name FROM users WHERE id = :id AND status = 'active' LIMIT 1");
        $ownerStmt->execute(['id' => $ownerId]);
        $ownerRow = $ownerStmt->fetch();
        if (!$ownerRow) {
            crm_json_error('owner_id does not match an active user.');
        }
        $ownerName = $ownerRow['name'];
    }

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
        'contact_person'       => $contactPerson,
        'contact_designation'  => $contactDesignation,
        'phone'                => $phone,
        'email'                => $email,
        'normalized_phone'     => crm_normalize_phone($phone),
        'normalized_email'     => crm_normalize_email($email),
        'normalized_company'   => crm_normalize_company($companyName),
        'industry_sector'      => $industrySector,
        'source'               => $source,
        'owner_id'             => $ownerId,
        'owner_name'           => $ownerName,
        'notes'                => $notes,
    ]);
    $newId = (int) $db->lastInsertId();

    crm_log_activity($user['id'], 'create_lead', 'lead', $newId, "Created lead: $companyName");

    crm_json_response(['id' => $newId], 201);
}

if ($method === 'PUT') {
    ehs_verify_csrf();
    $input = crm_json_input();

    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) {
        crm_json_error('id is required.');
    }

    $existingStmt = $db->prepare('SELECT id FROM crm_leads WHERE id = :id LIMIT 1');
    $existingStmt->execute(['id' => $id]);
    if (!$existingStmt->fetch()) {
        crm_json_error('Lead not found.', 404);
    }

    $companyName = crm_clean_str($input['company_name'] ?? null, 200);
    if ($companyName === null) {
        crm_json_error('company_name is required.');
    }

    $source = trim((string) ($input['source'] ?? 'other'));
    if (!crm_is_valid_source($source)) {
        crm_json_error('Invalid source value.');
    }

    $contactPerson = crm_clean_str($input['contact_person'] ?? null, 150);
    $contactDesignation = crm_clean_str($input['contact_designation'] ?? null, 100);
    $phone = crm_clean_str($input['phone'] ?? null, 30);
    $email = crm_clean_str($input['email'] ?? null, 150);
    $industrySector = crm_clean_str($input['industry_sector'] ?? null, 100);
    $notes = crm_clean_str($input['notes'] ?? null, 65000);

    $ownerId = !empty($input['owner_id']) ? (int) $input['owner_id'] : null;
    $ownerName = null;
    if ($ownerId !== null) {
        $ownerStmt = $db->prepare("SELECT name FROM users WHERE id = :id AND status = 'active' LIMIT 1");
        $ownerStmt->execute(['id' => $ownerId]);
        $ownerRow = $ownerStmt->fetch();
        if (!$ownerRow) {
            crm_json_error('owner_id does not match an active user.');
        }
        $ownerName = $ownerRow['name'];
    }

    $stmt = $db->prepare(
        'UPDATE crm_leads SET
            company_name = :company_name,
            contact_person = :contact_person,
            contact_designation = :contact_designation,
            phone = :phone,
            email = :email,
            normalized_phone = :normalized_phone,
            normalized_email = :normalized_email,
            normalized_company = :normalized_company,
            industry_sector = :industry_sector,
            source = :source,
            owner_id = :owner_id,
            owner_name = :owner_name,
            notes = :notes
         WHERE id = :id'
    );
    $stmt->execute([
        'company_name'         => $companyName,
        'contact_person'       => $contactPerson,
        'contact_designation'  => $contactDesignation,
        'phone'                => $phone,
        'email'                => $email,
        'normalized_phone'     => crm_normalize_phone($phone),
        'normalized_email'     => crm_normalize_email($email),
        'normalized_company'   => crm_normalize_company($companyName),
        'industry_sector'      => $industrySector,
        'source'               => $source,
        'owner_id'             => $ownerId,
        'owner_name'           => $ownerName,
        'notes'                => $notes,
        'id'                   => $id,
    ]);

    crm_log_activity($user['id'], 'update_lead', 'lead', $id, "Updated lead: $companyName");

    crm_json_response(['success' => true]);
}

crm_json_error('Method not allowed.', 405);