<?php
/**
 * /api/audits.php
 * GET    ?start=&end=&auditor_id=&scheme_id=&client=&status=  -> filtered list
 * POST   { client_id? , client_name?, scheme_ids[], audit_date, session,
 *          auditor_ids[], location, notes, status } -> create
 * PUT    ?id=N  (same body as POST)                            -> update
 * DELETE ?id=N                                                  -> remove
 *
 * All writes are restricted to super_admin/admin. Conflict/availability
 * warnings are surfaced by /api/auditors.php ahead of save — this endpoint
 * does NOT block on conflicts, since the spec calls for a warning, not a hard
 * stop (an admin may deliberately double-book in a pinch).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

$user = ehs_require_role(['super_admin', 'admin'], true);

$db = get_db();
$validSessions = ['AM', 'PM', 'FULL_DAY'];
$validStatuses = ['scheduled', 'confirmed', 'completed', 'cancelled'];

// ---------------------------------------------------------------------------
// GET — filtered list for the calendar and grid views
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $start     = $_GET['start'] ?? '';
    $end       = $_GET['end'] ?? '';
    $auditorId = $_GET['auditor_id'] ?? '';
    $schemeId  = $_GET['scheme_id'] ?? '';
    $client    = trim($_GET['client'] ?? '');
    $status    = $_GET['status'] ?? '';

    if (!ehs_is_valid_date($start) || !ehs_is_valid_date($end)) {
        ehs_json_error('start and end must be valid YYYY-MM-DD dates.', 422);
    }

    $where  = ['a.audit_date >= :start', 'a.audit_date < :end'];
    $params = ['start' => $start, 'end' => $end];

    if ($auditorId !== '') {
        $where[] = 'EXISTS (SELECT 1 FROM audit_auditors aa2 WHERE aa2.audit_id = a.id AND aa2.auditor_id = :auditor_id)';
        $params['auditor_id'] = (int) $auditorId;
    }
    if ($schemeId !== '') {
        $where[] = 'EXISTS (SELECT 1 FROM audit_schemes as2 WHERE as2.audit_id = a.id AND as2.scheme_id = :scheme_id)';
        $params['scheme_id'] = (int) $schemeId;
    }
    if ($client !== '') {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $client);
        $where[] = "c.name LIKE :client ESCAPE '\\\\'";
        $params['client'] = '%' . $escaped . '%';
    }
    if ($status !== '' && in_array($status, $validStatuses, true)) {
        $where[] = 'a.status = :status';
        $params['status'] = $status;
    }

    $sql = 'SELECT a.id, a.audit_date, a.session, a.status, a.location, a.notes, a.updated_at,
                   c.id AS client_id, c.name AS client_name
            FROM audits a
            JOIN clients c ON c.id = a.client_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY a.audit_date, a.session';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $audits = $stmt->fetchAll();

    if ($audits) {
        $ids = array_column($audits, 'id');
        $ids = array_map('intval', $ids);
        $inClause = implode(',', array_fill(0, count($ids), '?'));

        $schemeStmt = $db->prepare(
            "SELECT as2.audit_id, s.id, s.name, s.code FROM audit_schemes as2
             JOIN schemes s ON s.id = as2.scheme_id
             WHERE as2.audit_id IN ($inClause)"
        );
        $schemeStmt->execute($ids);
        $schemesByAudit = [];
        foreach ($schemeStmt->fetchAll() as $row) {
            $schemesByAudit[$row['audit_id']][] = ['id' => (int) $row['id'], 'name' => $row['name'], 'code' => $row['code']];
        }

        $auditorStmt = $db->prepare(
            "SELECT aa.audit_id, u.id, u.name, u.color_hex FROM audit_auditors aa
             JOIN users u ON u.id = aa.auditor_id
             WHERE aa.audit_id IN ($inClause)"
        );
        $auditorStmt->execute($ids);
        $auditorsByAudit = [];
        foreach ($auditorStmt->fetchAll() as $row) {
            $auditorsByAudit[$row['audit_id']][] = ['id' => (int) $row['id'], 'name' => $row['name'], 'color_hex' => $row['color_hex']];
        }

        foreach ($audits as &$audit) {
            $audit['id'] = (int) $audit['id'];
            $audit['client_id'] = (int) $audit['client_id'];
            $audit['schemes'] = $schemesByAudit[$audit['id']] ?? [];
            $audit['auditors'] = $auditorsByAudit[$audit['id']] ?? [];
            $overdue = ehs_compute_overdue($audit['status'], $audit['audit_date'], date('Y-m-d'));
            $audit['is_overdue'] = $overdue['is_overdue'];
            $audit['overdue_reason'] = $overdue['overdue_reason'];
        }
        unset($audit);
    }

    ehs_json_response(['audits' => $audits]);
}

// ---------------------------------------------------------------------------
// Shared input validation for POST/PUT
// ---------------------------------------------------------------------------
function ehs_validate_audit_input(array $input, array $validSessions, array $validStatuses): array
{
    $errors = [];

    $auditDate = $input['audit_date'] ?? '';
    if (!ehs_is_valid_date($auditDate)) {
        $errors[] = 'A valid audit_date is required.';
    }

    $session = $input['session'] ?? '';
    if (!in_array($session, $validSessions, true)) {
        $errors[] = 'A valid session is required.';
    }

    $status = $input['status'] ?? 'scheduled';
    if (!in_array($status, $validStatuses, true)) {
        $errors[] = 'Invalid status value.';
    }

    $schemeIds = $input['scheme_ids'] ?? [];
    if (!is_array($schemeIds) || empty($schemeIds)) {
        $errors[] = 'At least one scheme must be selected.';
    }

    $auditorIds = $input['auditor_ids'] ?? [];
    if (!is_array($auditorIds) || empty($auditorIds)) {
        $errors[] = 'At least one auditor must be assigned.';
    }

    $clientId   = $input['client_id'] ?? null;
    $clientName = trim((string) ($input['client_name'] ?? ''));
    if (!$clientId && $clientName === '') {
        $errors[] = 'A client is required.';
    }

    $location = trim((string) ($input['location'] ?? ''));
    if (mb_strlen($location) > 255) {
        $errors[] = 'Location is too long (255 characters max).';
    }

    return $errors;
}

/**
 * Hard cap: an auditor may not be assigned to more than 2 audits on the same
 * date (counting the one being created/edited), regardless of session —
 * so an existing AM+PM pair is already at the limit. Returns an error
 * message naming the first auditor over the limit, or null if all clear.
 */
function ehs_check_daily_audit_cap(PDO $db, array $auditorIds, string $date, ?int $excludeAuditId): ?string
{
    foreach (array_unique(array_map('intval', $auditorIds)) as $auditorId) {
        $sql = "SELECT COUNT(DISTINCT a.id) AS c
                FROM audit_auditors aa
                JOIN audits a ON a.id = aa.audit_id
                WHERE aa.auditor_id = ? AND a.audit_date = ? AND a.status != 'cancelled'";
        $params = [$auditorId, $date];
        if ($excludeAuditId) {
            $sql .= ' AND a.id != ?';
            $params[] = $excludeAuditId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $count = (int) $stmt->fetch()['c'];

        if ($count >= 2) {
            $nameStmt = $db->prepare('SELECT name FROM users WHERE id = ?');
            $nameStmt->execute([$auditorId]);
            $name = $nameStmt->fetch()['name'] ?? "Auditor #$auditorId";
            return "$name already has 2 audits on $date \u2014 the maximum allowed per day.";
        }
    }
    return null;
}

/** Resolve client_id from input, creating a new client if only a name was given. */
function ehs_resolve_client_id(PDO $db, array $input): int
{
    if (!empty($input['client_id'])) {
        return (int) $input['client_id'];
    }

    $name = trim((string) $input['client_name']);
    $existing = $db->prepare('SELECT id FROM clients WHERE name = :name LIMIT 1');
    $existing->execute(['name' => $name]);
    $row = $existing->fetch();
    if ($row) {
        return (int) $row['id'];
    }

    $insert = $db->prepare('INSERT INTO clients (name) VALUES (:name)');
    $insert->execute(['name' => $name]);
    return (int) $db->lastInsertId();
}

// ---------------------------------------------------------------------------
// POST — create
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ehs_verify_csrf();
    $input = ehs_json_input();

    $errors = ehs_validate_audit_input($input, $validSessions, $validStatuses);
    if ($errors) {
        ehs_json_error(implode(' ', $errors), 422);
    }

    $capError = ehs_check_daily_audit_cap($db, $input['auditor_ids'], $input['audit_date'], null);
    if ($capError) {
        ehs_json_error($capError, 422);
    }

    $db->beginTransaction();
    try {
        $clientId = ehs_resolve_client_id($db, $input);

        $stmt = $db->prepare(
            'INSERT INTO audits (client_id, audit_date, session, status, location, notes, created_by)
             VALUES (:client_id, :audit_date, :session, :status, :location, :notes, :created_by)'
        );
        $stmt->execute([
            'client_id'  => $clientId,
            'audit_date' => $input['audit_date'],
            'session'    => $input['session'],
            'status'     => $input['status'] ?? 'scheduled',
            'location'   => trim((string) ($input['location'] ?? '')) ?: null,
            'notes'      => trim((string) ($input['notes'] ?? '')) ?: null,
            'created_by' => $user['id'],
        ]);
        $auditId = (int) $db->lastInsertId();

        $schemeStmt = $db->prepare('INSERT INTO audit_schemes (audit_id, scheme_id) VALUES (:audit_id, :scheme_id)');
        foreach (array_unique(array_map('intval', $input['scheme_ids'])) as $schemeId) {
            $schemeStmt->execute(['audit_id' => $auditId, 'scheme_id' => $schemeId]);
        }

        $auditorStmt = $db->prepare('INSERT INTO audit_auditors (audit_id, auditor_id) VALUES (:audit_id, :auditor_id)');
        foreach (array_unique(array_map('intval', $input['auditor_ids'])) as $auditorId) {
            $auditorStmt->execute(['audit_id' => $auditId, 'auditor_id' => $auditorId]);
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('Audit create failed: ' . $e->getMessage());
        ehs_json_error('Could not create the audit. Please try again.', 500);
    }

    ehs_log_activity($user['id'], 'create_audit', 'audit', $auditId, $input['audit_date'] . ' ' . $input['session']);
    ehs_json_response(['success' => true, 'id' => $auditId], 201);
}

// ---------------------------------------------------------------------------
// PUT — update
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    ehs_verify_csrf();
    $auditId = (int) ($_GET['id'] ?? 0);
    if ($auditId <= 0) {
        ehs_json_error('A valid audit id is required.', 422);
    }

    $input = ehs_json_input();
    $errors = ehs_validate_audit_input($input, $validSessions, $validStatuses);
    if ($errors) {
        ehs_json_error(implode(' ', $errors), 422);
    }

    $exists = $db->prepare('SELECT id, updated_at FROM audits WHERE id = :id');
    $exists->execute(['id' => $auditId]);
    $existingRow = $exists->fetch();
    if (!$existingRow) {
        ehs_json_error('Audit not found.', 404);
    }

    // Optimistic locking: the client sends back the updated_at it saw when
    // it loaded this audit. If the row has changed since then (someone else
    // saved a change in between), reject rather than silently overwrite
    // their edit — this is what stops two admins' concurrent edits from
    // clobbering each other with no warning.
    if (!empty($input['expected_updated_at']) && $input['expected_updated_at'] !== $existingRow['updated_at']) {
        ehs_json_error('This audit was changed by someone else since you opened it. Please reload and try again.', 409);
    }

    $capError = ehs_check_daily_audit_cap($db, $input['auditor_ids'], $input['audit_date'], $auditId);
    if ($capError) {
        ehs_json_error($capError, 422);
    }

    $db->beginTransaction();
    try {
        $clientId = ehs_resolve_client_id($db, $input);

        $stmt = $db->prepare(
            'UPDATE audits SET client_id = :client_id, audit_date = :audit_date, session = :session,
                    status = :status, location = :location, notes = :notes
             WHERE id = :id'
        );
        $stmt->execute([
            'client_id'  => $clientId,
            'audit_date' => $input['audit_date'],
            'session'    => $input['session'],
            'status'     => $input['status'] ?? 'scheduled',
            'location'   => trim((string) ($input['location'] ?? '')) ?: null,
            'notes'      => trim((string) ($input['notes'] ?? '')) ?: null,
            'id'         => $auditId,
        ]);

        $db->prepare('DELETE FROM audit_schemes WHERE audit_id = :id')->execute(['id' => $auditId]);
        $schemeStmt = $db->prepare('INSERT INTO audit_schemes (audit_id, scheme_id) VALUES (:audit_id, :scheme_id)');
        foreach (array_unique(array_map('intval', $input['scheme_ids'])) as $schemeId) {
            $schemeStmt->execute(['audit_id' => $auditId, 'scheme_id' => $schemeId]);
        }

        $db->prepare('DELETE FROM audit_auditors WHERE audit_id = :id')->execute(['id' => $auditId]);
        $auditorStmt = $db->prepare('INSERT INTO audit_auditors (audit_id, auditor_id) VALUES (:audit_id, :auditor_id)');
        foreach (array_unique(array_map('intval', $input['auditor_ids'])) as $auditorId) {
            $auditorStmt->execute(['audit_id' => $auditId, 'auditor_id' => $auditorId]);
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('Audit update failed: ' . $e->getMessage());
        ehs_json_error('Could not update the audit. Please try again.', 500);
    }

    ehs_log_activity($user['id'], 'update_audit', 'audit', $auditId, $input['audit_date'] . ' ' . $input['session']);
    ehs_json_response(['success' => true, 'id' => $auditId]);
}

// ---------------------------------------------------------------------------
// DELETE — remove
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    ehs_verify_csrf();
    $auditId = (int) ($_GET['id'] ?? 0);
    if ($auditId <= 0) {
        ehs_json_error('A valid audit id is required.', 422);
    }

    $stmt = $db->prepare('DELETE FROM audits WHERE id = :id');
    $stmt->execute(['id' => $auditId]);

    if ($stmt->rowCount() === 0) {
        ehs_json_error('Audit not found.', 404);
    }

    ehs_log_activity($user['id'], 'delete_audit', 'audit', $auditId, '');
    ehs_json_response(['success' => true]);
}

ehs_json_error('Method not allowed.', 405);
