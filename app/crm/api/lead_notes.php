<?php
/**
 * crm/api/lead_notes.php
 *
 * GET  ?lead_id=X
 *      -> narrative activity log for a lead, newest first.
 *
 * POST { lead_id, followup_id (optional), activity_type, note,
 *        outcome (optional), new_stage (optional) }
 *      -> logs one activity entry. If new_stage is given and differs from
 *         the lead's current stage, ALSO changes the lead's stage in the
 *         SAME request (the "Log Activity" combined action, mirroring
 *         cm_client_followup_notes.php) via the shared
 *         crm_change_lead_stage() engine — so this path gets exactly the
 *         same history write / lost-reason handling / one-way conversion
 *         as the board's drag-and-drop path, never a second copy of that
 *         logic. Captures a `previous_state` snapshot (just
 *         {lead_id, stage_was} — there's no rollover/milestone concept on
 *         a lead the way there is on a certification) so the stage part
 *         of this entry can be precisely undone later.
 *
 *         IMPORTANT SAFETY BEHAVIOR: if new_stage is 'awarded' and this
 *         triggers a NEW conversion, undo will later restore `stage` but
 *         will NEVER touch converted_client_id/converted_at and will
 *         NEVER delete the resulting cm_clients row — the conversion is
 *         permanent by design (see the 2026-09-15 one-way-conversion
 *         decision). A lead can end up showing an earlier stage while
 *         still carrying a converted_client_id from a since-undone
 *         'awarded' entry — this is accepted, intentional behavior, not
 *         a bug: the client record, once created, is never retracted.
 *
 * PUT  ?id=X { action: "undo" }
 *      -> reverts ONLY the stage change this specific entry made
 *         (restoring `stage` from the snapshot). Blocked if a newer
 *         stage-changing entry on the same lead hasn't itself been
 *         reverted yet, same safety rule as the CM module.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/crm_helpers.php';

const CRM_NOTE_ACTIVITY_TYPES = ['whatsapp_sent', 'call', 'email', 'meeting', 'site_visit', 'other'];

$user = ehs_require_role(['super_admin', 'admin'], true);
$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $leadId = (int) ($_GET['lead_id'] ?? 0);
    if ($leadId <= 0) {
        crm_json_error('lead_id is required.', 422);
    }

    $stmt = $db->prepare(
        'SELECT n.*, COALESCE(u.name, n.created_by_name) AS created_by_display_name
         FROM crm_lead_notes n LEFT JOIN users u ON u.id = n.created_by
         WHERE n.crm_lead_id = :lead_id
         ORDER BY n.created_at DESC'
    );
    $stmt->execute(['lead_id' => $leadId]);
    crm_json_response(['notes' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    ehs_verify_csrf();
    $input = crm_json_input();

    $leadId = (int) ($input['lead_id'] ?? 0);
    $note = crm_clean_str($input['note'] ?? null, 2000);
    if ($leadId <= 0 || $note === null) {
        crm_json_error('lead_id and a non-empty note are required.', 422);
    }

    $activityType = trim((string) ($input['activity_type'] ?? 'other'));
    if (!in_array($activityType, CRM_NOTE_ACTIVITY_TYPES, true)) {
        $activityType = 'other';
    }
    $outcome = crm_clean_str($input['outcome'] ?? null, 255);

    $followupId = !empty($input['followup_id']) ? (int) $input['followup_id'] : null;
    if ($followupId !== null) {
        $fuCheck = $db->prepare('SELECT id FROM crm_followups WHERE id = :id AND crm_lead_id = :lead_id LIMIT 1');
        $fuCheck->execute(['id' => $followupId, 'lead_id' => $leadId]);
        if (!$fuCheck->fetch()) {
            crm_json_error('followup_id does not belong to this lead.', 422);
        }
    }

    $newStage = trim((string) ($input['new_stage'] ?? ''));
    $stageChangedTo = null;
    $previousState = null;
    $convertedClientId = null;

    $db->beginTransaction();
    try {
        // Confirm the lead exists before attributing a note to it.
        $leadCheck = $db->prepare('SELECT id, stage FROM crm_leads WHERE id = :id LIMIT 1');
        $leadCheck->execute(['id' => $leadId]);
        $leadRow = $leadCheck->fetch();
        if (!$leadRow) {
            $db->rollBack();
            crm_json_error('Lead not found.', 404);
        }

        if ($newStage !== '' && $newStage !== $leadRow['stage']) {
            $reason = crm_clean_str($input['reason'] ?? null, 1000);
            $result = crm_change_lead_stage($db, $leadId, $newStage, $reason, $user['id'], $user['name']);
            $stageChangedTo = $result['to_stage'];
            $convertedClientId = $result['converted_client_id'];
            $previousState = json_encode(['lead_id' => $leadId, 'stage_was' => $result['from_stage']]);
        }

        $insertStmt = $db->prepare(
            'INSERT INTO crm_lead_notes
                (crm_lead_id, crm_followup_id, activity_type, note, outcome,
                 stage_changed_to, previous_state, created_by, created_by_name)
             VALUES
                (:crm_lead_id, :crm_followup_id, :activity_type, :note, :outcome,
                 :stage_changed_to, :previous_state, :created_by, :created_by_name)'
        );
        $insertStmt->execute([
            'crm_lead_id'      => $leadId,
            'crm_followup_id'  => $followupId,
            'activity_type'    => $activityType,
            'note'             => $note,
            'outcome'          => $outcome,
            'stage_changed_to' => $stageChangedTo,
            'previous_state'   => $previousState,
            'created_by'       => $user['id'],
            'created_by_name'  => $user['name'],
        ]);
        $newId = (int) $db->lastInsertId();

        // If this note closes out a specific scheduled follow-up, mark it done.
        if ($followupId !== null) {
            $doneStmt = $db->prepare('UPDATE crm_followups SET done = 1, done_at = NOW() WHERE id = :id');
            $doneStmt->execute(['id' => $followupId]);
        }

        $db->commit();
    } catch (InvalidArgumentException $e) {
        if ($db->inTransaction()) $db->rollBack();
        crm_json_error($e->getMessage(), 422);
    } catch (\Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        crm_json_error('Failed to log activity: ' . $e->getMessage(), 500);
    }

    $logDetail = $activityType
        . ($stageChangedTo ? " (stage -> $stageChangedTo)" : '')
        . ($convertedClientId ? " (converted to cm_clients #$convertedClientId)" : '')
        . ': ' . mb_substr($note, 0, 100);
    crm_log_activity($user['id'], 'log_activity', 'lead', $leadId, $logDetail);

    crm_json_response([
        'id'                   => $newId,
        'stage_changed_to'     => $stageChangedTo,
        'converted_client_id'  => $convertedClientId,
        'undoable'             => $previousState !== null,
    ], 201);
}

if ($method === 'PUT') {
    ehs_verify_csrf();
    $noteId = (int) ($_GET['id'] ?? 0);
    if ($noteId <= 0) {
        crm_json_error('A valid note id is required.', 422);
    }

    $input = crm_json_input();
    if (($input['action'] ?? '') !== 'undo') {
        crm_json_error('Unsupported action.', 422);
    }

    $db->beginTransaction();
    try {
        $noteStmt = $db->prepare('SELECT * FROM crm_lead_notes WHERE id = :id LIMIT 1 FOR UPDATE');
        $noteStmt->execute(['id' => $noteId]);
        $note = $noteStmt->fetch();
        if (!$note) {
            $db->rollBack();
            crm_json_error('Activity entry not found.', 404);
        }
        if ($note['reverted_at'] !== null) {
            $db->rollBack();
            crm_json_error('This activity has already been reverted.', 422);
        }
        if ($note['previous_state'] === null) {
            $db->rollBack();
            crm_json_error('This activity did not change the lead\'s stage, so there\'s nothing to revert.', 422);
        }

        $state = json_decode($note['previous_state'], true);
        $leadId = (int) ($state['lead_id'] ?? 0);
        $stageWas = $state['stage_was'] ?? null;
        if ($leadId <= 0 || $stageWas === null) {
            $db->rollBack();
            crm_json_error('Could not determine what this entry changed.', 500);
        }

        // Safety check: refuse to undo if a newer stage-changing entry on
        // the same lead also hasn't been reverted — undoing this one
        // underneath it would silently clobber that newer change.
        $newerStmt = $db->prepare(
            'SELECT id FROM crm_lead_notes
             WHERE crm_lead_id = :lead_id AND id > :note_id AND reverted_at IS NULL
               AND stage_changed_to IS NOT NULL
             ORDER BY id ASC LIMIT 1'
        );
        $newerStmt->execute(['lead_id' => $leadId, 'note_id' => $noteId]);
        $newerId = $newerStmt->fetchColumn();
        if ($newerId) {
            $db->rollBack();
            crm_json_error("A newer activity (#$newerId) already changed this lead's stage. Revert that one first.", 409);
        }

        $leadLockStmt = $db->prepare('SELECT id FROM crm_leads WHERE id = :id LIMIT 1 FOR UPDATE');
        $leadLockStmt->execute(['id' => $leadId]);
        if (!$leadLockStmt->fetch()) {
            $db->rollBack();
            crm_json_error('The lead this entry affected no longer exists.', 404);
        }

        // Deliberately ONLY restores `stage` — never touches
        // converted_client_id/converted_at, never deletes a cm_clients
        // row. See the file docblock's SAFETY BEHAVIOR note.
        $undoStmt = $db->prepare('UPDATE crm_leads SET stage = :stage WHERE id = :id');
        $undoStmt->execute(['stage' => $stageWas, 'id' => $leadId]);

        $markRevertedStmt = $db->prepare('UPDATE crm_lead_notes SET reverted_at = NOW() WHERE id = :id');
        $markRevertedStmt->execute(['id' => $noteId]);

        // A visible trail entry for the revert itself.
        $revertNoteStmt = $db->prepare(
            'INSERT INTO crm_lead_notes
                (crm_lead_id, activity_type, note, created_by, created_by_name)
             VALUES
                (:lead_id, \'other\', :note, :created_by, :created_by_name)'
        );
        $revertNoteStmt->execute([
            'lead_id'         => $leadId,
            'note'            => "Reverted activity #$noteId (" . mb_substr((string) $note['note'], 0, 80) . ')',
            'created_by'      => $user['id'],
            'created_by_name' => $user['name'],
        ]);

        // Also record the revert in crm_lead_stage_history, for the same
        // reason the original change was recorded there — the stage
        // timeline should show every actual stage value the lead passed
        // through, including ones later undone.
        $historyStmt = $db->prepare(
            'INSERT INTO crm_lead_stage_history
                (crm_lead_id, from_stage, to_stage, reason, changed_by, changed_by_name)
             VALUES
                (:lead_id, :from_stage, :to_stage, :reason, :changed_by, :changed_by_name)'
        );
        $historyStmt->execute([
            'lead_id'         => $leadId,
            'from_stage'      => $note['stage_changed_to'],
            'to_stage'        => $stageWas,
            'reason'          => "Undo of activity #$noteId",
            'changed_by'      => $user['id'],
            'changed_by_name' => $user['name'],
        ]);

        $db->commit();
    } catch (\Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        crm_json_error('Undo failed: ' . $e->getMessage(), 500);
    }

    crm_log_activity($user['id'], 'undo_log_activity', 'lead', $leadId, "Reverted activity #$noteId");

    crm_json_response(['success' => true]);
}

crm_json_error('Method not allowed.', 405);