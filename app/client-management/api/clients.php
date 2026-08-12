<?php
/**
 * client-management/api/clients.php
 *
 * GET    ?id=X                -> single client (full record)
 * GET    (list, with filters) -> ?q=&industry=&status=&scheme_type_id=&expiring_within_days=&page=&per_page=
 * POST   { ...fields }        -> create a client (duplicate-checked on name + UEN)
 * PUT    ?id=X { ...fields }  -> update a client (including status changes)
 *
 * There is deliberately NO DELETE handler: per the module spec, clients are
 * never hard-deleted — certification bodies need historical records. Use
 * PUT to change `status` to 'withdrawn' / 'blacklisted' / 'suspended' instead.
 *
 * Reuses ONLY the shared session/login system (auth.php -> db.php) and the
 * `users` table. All tables touched here are cm_-prefixed.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/cm_helpers.php';

// Auditors have no access to this module by default.
$user = ehs_require_role(['super_admin', 'admin'], true);
$db = get_db();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    if ($id <= 0) cm_json_error('A valid client id is required.', 422);

    $stmt = $db->prepare('SELECT * FROM cm_clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $client = $stmt->fetch();

    if (!$client) cm_json_error('Client not found.', 404);

    cm_json_response(['client' => $client]);
}

if ($method === 'GET') {
    $q          = trim((string) ($_GET['q'] ?? ''));
    $industry   = trim((string) ($_GET['industry'] ?? ''));
    $status     = trim((string) ($_GET['status'] ?? ''));
    $schemeId   = (int) ($_GET['scheme_type_id'] ?? 0);
    $expiringWithinDays = isset($_GET['expiring_within_days']) ? (int) $_GET['expiring_within_days'] : null;

    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 25)));
    $offset  = ($page - 1) * $perPage;

    $where  = [];
    $params = [];

    if ($q !== '') {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
        $where[] = "(c.company_name LIKE :q ESCAPE '\\\\' OR c.uen_registration_no LIKE :q2 ESCAPE '\\\\')";
        $params['q']  = '%' . $escaped . '%';
        $params['q2'] = '%' . $escaped . '%';
    }
    if ($industry !== '') {
        $where[] = 'c.industry_sector = :industry';
        $params['industry'] = $industry;
    }
    $validStatuses = ['active', 'suspended', 'withdrawn', 'blacklisted'];
    if ($status !== '' && in_array($status, $validStatuses, true)) {
        $where[] = 'c.status = :status';
        $params['status'] = $status;
    }

    // Scheme-type / expiry filters require joining to certifications. Use a
    // correlated EXISTS rather than a JOIN so a client with multiple matching
    // certifications isn't returned as duplicate rows.
    if ($schemeId > 0) {
        $where[] = 'EXISTS (SELECT 1 FROM cm_certifications cc WHERE cc.cm_client_id = c.id AND cc.cm_scheme_type_id = :scheme_id)';
        $params['scheme_id'] = $schemeId;
    }
    if ($expiringWithinDays !== null && $expiringWithinDays >= 0) {
        $where[] = 'EXISTS (
            SELECT 1 FROM cm_certifications cc
            WHERE cc.cm_client_id = c.id
              AND cc.expiry_date IS NOT NULL
              AND cc.expiry_date <= DATE_ADD(CURDATE(), INTERVAL :expiring_days DAY)
        )';
        $params['expiring_days'] = $expiringWithinDays;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $countStmt = $db->prepare("SELECT COUNT(*) FROM cm_clients c $whereSql");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $listSql = "SELECT c.* FROM cm_clients c $whereSql ORDER BY c.company_name LIMIT :limit OFFSET :offset";
    $listStmt = $db->prepare($listSql);
    foreach ($params as $key => $val) {
        $listStmt->bindValue(':' . $key, $val);
    }
    $listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $listStmt->execute();

    cm_json_response([
        'clients'  => $listStmt->fetchAll(),
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
    ]);
}

/** Shared field extraction/validation for POST and PUT. */
function cm_extract_client_fields(array $input): array
{
    $companyName = cm_clean_str($input['company_name'] ?? null, 200);
    if ($companyName === null) {
        cm_json_error('Company name is required.', 422);
    }

    $status = trim((string) ($input['status'] ?? 'active'));
    if (!in_array($status, ['active', 'suspended', 'withdrawn', 'blacklisted'], true)) {
        cm_json_error('Invalid status value.', 422);
    }

    $email = cm_clean_str($input['email'] ?? null, 150);
    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        cm_json_error('Email address is not valid.', 422);
    }

    return [
        'company_name'        => $companyName,
        'uen_registration_no' => cm_clean_str($input['uen_registration_no'] ?? null, 50),
        'industry_sector'     => cm_clean_str($input['industry_sector'] ?? null, 100),
        'address'             => cm_clean_str($input['address'] ?? null, 255),
        'contact_person'      => cm_clean_str($input['contact_person'] ?? null, 150),
        'contact_designation' => cm_clean_str($input['contact_designation'] ?? null, 100),
        'consultant'          => cm_clean_str($input['consultant'] ?? null, 150),
        'phone'               => cm_clean_str($input['phone'] ?? null, 30),
        'email'               => $email,
        'website'             => cm_clean_str($input['website'] ?? null, 255),
        'status'              => $status,
        'notes'               => cm_clean_str($input['notes'] ?? null, 65535),
    ];
}

if ($method === 'POST') {
    ehs_verify_csrf();
    $fields = cm_extract_client_fields(cm_json_input());

    // App-level duplicate check (name, case-insensitive; and UEN if given)
    // so the user gets a friendly warning instead of a raw DB constraint
    // error. The UEN column also has a DB-level UNIQUE index as a backstop.
    // Built conditionally because PDO with ATTR_EMULATE_PREPARES=false (real
    // prepared statements, as this project requires) does not support the
    // same named placeholder appearing twice in one query.
    $dupSql = 'SELECT id, company_name FROM cm_clients WHERE LOWER(company_name) = LOWER(:name)';
    $dupParams = ['name' => $fields['company_name']];
    if ($fields['uen_registration_no'] !== null) {
        $dupSql .= ' OR uen_registration_no = :uen';
        $dupParams['uen'] = $fields['uen_registration_no'];
    }
    $dupSql .= ' LIMIT 1';
    $dupStmt = $db->prepare($dupSql);
    $dupStmt->execute($dupParams);
    $dup = $dupStmt->fetch();
    if ($dup) {
        cm_json_error('A client with that name or UEN already exists: "' . $dup['company_name'] . '".', 409);
    }

    $stmt = $db->prepare(
        'INSERT INTO cm_clients
            (company_name, uen_registration_no, industry_sector, address, contact_person,
             contact_designation, consultant, phone, email, website, status, notes)
         VALUES
            (:company_name, :uen_registration_no, :industry_sector, :address, :contact_person,
             :contact_designation, :consultant, :phone, :email, :website, :status, :notes)'
    );
    try {
        $stmt->execute($fields);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            cm_json_error('A client with that UEN already exists.', 409);
        }
        throw $e;
    }
    $newId = (int) $db->lastInsertId();

    cm_log_activity($user['id'], 'create_client', 'cm_client', $newId, $fields['company_name']);
    cm_json_response(['client' => array_merge(['id' => $newId], $fields), 'created' => true], 201);
}

if ($method === 'PUT') {
    ehs_verify_csrf();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) cm_json_error('A valid client id is required.', 422);

    $existsStmt = $db->prepare('SELECT id FROM cm_clients WHERE id = :id LIMIT 1');
    $existsStmt->execute(['id' => $id]);
    if (!$existsStmt->fetch()) cm_json_error('Client not found.', 404);

    $fields = cm_extract_client_fields(cm_json_input());

    $dupSql = 'SELECT id, company_name FROM cm_clients WHERE (LOWER(company_name) = LOWER(:name)';
    $dupParams = ['name' => $fields['company_name']];
    if ($fields['uen_registration_no'] !== null) {
        $dupSql .= ' OR uen_registration_no = :uen';
        $dupParams['uen'] = $fields['uen_registration_no'];
    }
    $dupSql .= ') AND id != :id LIMIT 1'; // parenthesized: AND binds tighter than OR in SQL
    $dupParams['id'] = $id;
    $dupStmt = $db->prepare($dupSql);
    $dupStmt->execute($dupParams);
    $dup = $dupStmt->fetch();
    if ($dup) {
        cm_json_error('Another client already uses that name or UEN: "' . $dup['company_name'] . '".', 409);
    }

    $stmt = $db->prepare(
        'UPDATE cm_clients SET
            company_name = :company_name, uen_registration_no = :uen_registration_no,
            industry_sector = :industry_sector, address = :address, contact_person = :contact_person,
            contact_designation = :contact_designation, consultant = :consultant, phone = :phone, email = :email,
            website = :website, status = :status, notes = :notes
         WHERE id = :id'
    );
    try {
        $stmt->execute(array_merge($fields, ['id' => $id]));
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            cm_json_error('Another client already uses that UEN.', 409);
        }
        throw $e;
    }

    cm_log_activity($user['id'], 'update_client', 'cm_client', $id, $fields['company_name']);
    cm_json_response(['success' => true]);
}

cm_json_error('Method not allowed.', 405);