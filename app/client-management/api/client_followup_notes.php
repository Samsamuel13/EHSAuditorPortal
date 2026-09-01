<?php
/**
 * client-management/api/client_followup_notes.php
 *
 * GET  ?client_id=X[&cert_id=Y]
 *      -> activity log for a client, newest first. If cert_id is given,
 *         scoped to just that certification's history; otherwise every
 *         entry for the client (any cert, plus client-level-only entries).
 *
 * POST { client_id, cert_id (optional), activity_type, note, outcome (optional),
 *        new_status (optional) }
 *      -> logs one activity entry. If cert_id + new_status are both given,
 *         also updates that certification's status in the SAME request —
 *         this is the "Log Activity" combined action (log note + change
 *         status together), not two separate steps.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/cm_helpers.php';

const CM_ACTIVITY_TYPES = ['whatsapp_sent', 'call', 'email', 'meeting', 'site_visit', 'other'];
const CM_CERT_STATUSES = ['active', 'expired', 'suspended', 'withdrawn', 'pending'];

$user = ehs_require_role(['super_admin', 'admin'], true);
$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $clientId = (int) ($_GET['client_id'] ?? 0);
    $certId = (int) ($_GET['cert_id'] ?? 0);
    if ($clientId <= 0) cm_json_error('client_id is required.', 422);

    $where = ['n.cm_client_id = :client_id'];
    $params = ['client_id' => $clientId];
    if ($certId > 0) {
        $where[] = 'n.cm_certification_id = :cert_id';
        $params['cert_id'] = $certId;
    }

    $stmt = $db->prepare(
        'SELECT n.*, COALESCE(u.name, n.created_by_name) AS created_by_display_name
         FROM cm_client_followup_notes n LEFT JOIN users u ON u.id = n.created_by
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY n.created_at DESC'
    );
    $stmt->execute($params);
    cm_json_response(['notes' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    ehs_verify_csrf();
    $input = cm_json_input();

    $clientId = (int) ($input['client_id'] ?? 0);
    $note = cm_clean_str($input['note'] ?? null, 2000);
    if ($clientId <= 0 || $note === null) {
        cm_json_error('client_id and a non-empty note are required.', 422);
    }

    $clientCheck = $db->prepare('SELECT id FROM cm_clients WHERE id = :id LIMIT 1');
    $clientCheck->execute(['id' => $clientId]);
    if (!$clientCheck->fetch()) cm_json_error('Client not found.', 404);

    $activityType = trim((string) ($input['activity_type'] ?? 'other'));
    if (!in_array($activityType, CM_ACTIVITY_TYPES, true)) $activityType = 'other';

    $outcome = cm_clean_str($input['outcome'] ?? null, 255);

    $certId = (int) ($input['cert_id'] ?? 0);
    $newStatus = trim((string) ($input['new_status'] ?? ''));
    $statusChangedTo = null;

    $db->beginTransaction();
    try {
        if ($certId > 0) {
            // Confirm the cert actually belongs to this client before
            // touching it or attributing the note to it.
            $certStmt = $db->prepare('SELECT id, status FROM cm_certifications WHERE id = :cert_id AND cm_client_id = :client_id LIMIT 1');
            $certStmt->execute(['cert_id' => $certId, 'client_id' => $clientId]);
            $cert = $certStmt->fetch();
            if (!$cert) {
                $db->rollBack();
                cm_json_error('That certification does not belong to this client.', 422);
            }

            if ($newStatus !== '' && in_array($newStatus, CM_CERT_STATUSES, true) && $newStatus !== $cert['status']) {
                $updateStmt = $db->prepare('UPDATE cm_certifications SET status = :status WHERE id = :id');
                $updateStmt->execute(['status' => $newStatus, 'id' => $certId]);
                $statusChangedTo = $newStatus;
            }
        }

        $insertStmt = $db->prepare(
            'INSERT INTO cm_client_followup_notes
                (cm_client_id, cm_certification_id, activity_type, note, outcome, status_changed_to, created_by, created_by_name)
             VALUES
                (:client_id, :cert_id, :activity_type, :note, :outcome, :status_changed_to, :created_by, :created_by_name)'
        );
        $insertStmt->execute([
            'client_id'         => $clientId,
            'cert_id'           => $certId > 0 ? $certId : null,
            'activity_type'     => $activityType,
            'note'              => $note,
            'outcome'           => $outcome,
            'status_changed_to' => $statusChangedTo,
            'created_by'        => $user['id'],
            'created_by_name'   => $user['name'],
        ]);
        $newId = (int) $db->lastInsertId();

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    $logDetail = $activityType . ($statusChangedTo ? " (status -> $statusChangedTo)" : '') . ': ' . mb_substr($note, 0, 100);
    cm_log_activity($user['id'], 'log_activity', 'cm_client', $clientId, $logDetail);

    cm_json_response(['id' => $newId, 'status_changed_to' => $statusChangedTo], 201);
}

cm_json_error('Method not allowed.', 405);