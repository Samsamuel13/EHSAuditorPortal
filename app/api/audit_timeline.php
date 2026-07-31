<?php
// File: api/audit_timeline.php
/**
 * /api/audit_timeline.php
 * GET ?audit_id=N -> chronological timeline of everything that's happened
 * to one audit, built from activity_log (every create/update/status-change
 * already gets logged there — no new schema needed).
 *
 * Admin/super_admin can view any audit's timeline; an auditor can only view
 * the timeline of an audit they're personally assigned to.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

$user = ehs_require_role(['super_admin', 'admin', 'auditor'], true);
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ehs_json_error('Method not allowed.', 405);
}

$auditId = (int) ($_GET['audit_id'] ?? 0);
if ($auditId <= 0) {
    ehs_json_error('A valid audit_id is required.', 422);
}

$audit = $db->prepare(
    'SELECT a.id, a.audit_date, a.session, a.status, c.name AS client_name
     FROM audits a JOIN clients c ON c.id = a.client_id WHERE a.id = :id'
);
$audit->execute(['id' => $auditId]);
$auditRow = $audit->fetch();
if (!$auditRow) {
    ehs_json_error('Audit not found.', 404);
}

if ($user['role'] === 'auditor') {
    $assigned = $db->prepare('SELECT 1 FROM audit_auditors WHERE audit_id = :id AND auditor_id = :uid');
    $assigned->execute(['id' => $auditId, 'uid' => $user['id']]);
    if (!$assigned->fetch()) {
        ehs_json_error('Forbidden: you are not assigned to this audit.', 403);
    }
}

$stmt = $db->prepare(
    "SELECT l.action, l.details, l.created_at, u.name AS user_name
     FROM activity_log l
     LEFT JOIN users u ON u.id = l.user_id
     WHERE l.entity_type = 'audit' AND l.entity_id = :id
     ORDER BY l.created_at ASC"
);
$stmt->execute(['id' => $auditId]);
$events = $stmt->fetchAll();

ehs_json_response([
    'audit' => [
        'id' => (int) $auditRow['id'],
        'client_name' => $auditRow['client_name'],
        'audit_date' => $auditRow['audit_date'],
        'session' => $auditRow['session'],
        'status' => $auditRow['status'],
    ],
    'events' => $events,
]);
