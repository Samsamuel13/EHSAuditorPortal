<?php
// File: api/audit_status.php
/**
 * /api/audit_status.php
 * PUT ?id=N { status } -> update ONLY the status of an audit.
 *
 * - super_admin/admin: may update any audit's status (same as the full
 *   edit endpoint, just narrower — convenient for quick status changes).
 * - auditor: may ONLY update the status of an audit they are personally
 *   assigned to (checked server-side via audit_auditors, not just hidden UI).
 *
 * This exists separately from /api/audits.php's PUT so an auditor's access
 * can be scoped to "confirm/complete/cancel my own assignment" without
 * giving them the ability to change the client, date, schemes, or who
 * else is assigned.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

$user = ehs_require_role(['super_admin', 'admin', 'auditor'], true);
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    ehs_json_error('Method not allowed.', 405);
}

ehs_verify_csrf();

$auditId = (int) ($_GET['id'] ?? 0);
if ($auditId <= 0) {
    ehs_json_error('A valid audit id is required.', 422);
}

$input = ehs_json_input();
$status = $input['status'] ?? '';
$validStatuses = ['scheduled', 'confirmed', 'completed', 'cancelled'];
if (!in_array($status, $validStatuses, true)) {
    ehs_json_error('Invalid status value.', 422);
}

$audit = $db->prepare('SELECT id FROM audits WHERE id = :id');
$audit->execute(['id' => $auditId]);
if (!$audit->fetch()) {
    ehs_json_error('Audit not found.', 404);
}

// Auditors can only touch audits they're personally assigned to.
if ($user['role'] === 'auditor') {
    $assigned = $db->prepare(
        'SELECT 1 FROM audit_auditors WHERE audit_id = :audit_id AND auditor_id = :auditor_id'
    );
    $assigned->execute(['audit_id' => $auditId, 'auditor_id' => $user['id']]);
    if (!$assigned->fetch()) {
        ehs_json_error('Forbidden: you are not assigned to this audit.', 403);
    }
}

$stmt = $db->prepare('UPDATE audits SET status = :status WHERE id = :id');
$stmt->execute(['status' => $status, 'id' => $auditId]);

ehs_log_activity($user['id'], 'update_audit_status', 'audit', $auditId, "status=$status");
ehs_json_response(['success' => true, 'id' => $auditId, 'status' => $status]);
