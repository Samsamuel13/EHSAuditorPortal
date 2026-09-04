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
 *        new_status (optional), milestone_completed (optional) }
 *      -> logs one activity entry. If cert_id + new_status are both given,
 *         also updates that certification's status in the SAME request —
 *         the "Log Activity" combined action (log note + change status
 *         together), not two separate steps. Same pattern for
 *         milestone_completed ('surveillance_1'|'surveillance_2'|'recertification'):
 *         stamps that milestone's *_completed_at column with NOW(), separate
 *         from and in addition to any status change — a milestone being
 *         done and the certification's overall status are different facts.
 *         Captures a `previous_state` snapshot of exactly what changed, so
 *         it can be precisely reverted later (see PUT below).
 *
 * PUT  ?id=X { action: "undo" }
 *      -> reverts the status/milestone/cycle-rollover changes THAT
 *         SPECIFIC entry made, restoring the certification to its exact
 *         prior state from the stored snapshot. Only allowed if this is
 *         the MOST RECENT status/milestone-affecting entry for that
 *         certification — undoing an older one while newer changes sit on
 *         top of it would silently clobber those, so it's blocked instead.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/cm_helpers.php';

/**
 * TOUCHES EXISTING LOGIC — added per explicit confirmation on the 4-stage
 * follow-up build (Q1: "if a milestone-completion gets undone, follow-up
 * stages it auto-resolved should reopen — build it in").
 *
 * Auto-resolves any open (or previously-reverted) cm_renewal_followup_actions
 * stage rows for one specific milestone+date, up through whatever stage was
 * CURRENTLY due as of today — because the milestone itself just got marked
 * done, so any follow-up campaign chasing it is now moot. Stages beyond
 * "current" never needed resolving, since the live due-today query in
 * renewal_followups.php already stops surfacing a completed milestone's
 * stages on its own (cm_certification_next_due() skips completed milestones).
 * Linked via resolved_by_note_id so undoing THIS note can cascade-reopen
 * exactly what this call resolved — see the PUT handler below.
 */
function cm_followup_auto_resolve_on_milestone_done(PDO $db, int $certId, string $milestoneKey, ?string $milestoneDate, int $noteId): void
{
    if ($milestoneDate === null) return;
    $currentStage = cm_followup_current_stage($milestoneDate, date('Y-m-d'));
    if ($currentStage === null) return; // never even reached Stage 1 — nothing to resolve

    for ($stage = 1; $stage <= $currentStage; $stage++) {
        $existingStmt = $db->prepare(
            'SELECT id, followed_up_at, reverted_at FROM cm_renewal_followup_actions
             WHERE cm_certification_id = :cert_id AND milestone_key = :milestone_key
               AND milestone_date = :milestone_date AND stage = :stage LIMIT 1 FOR UPDATE'
        );
        $existingStmt->execute(['cert_id' => $certId, 'milestone_key' => $milestoneKey, 'milestone_date' => $milestoneDate, 'stage' => $stage]);
        $existing = $existingStmt->fetch();

        $alreadyResolved = $existing && $existing['followed_up_at'] !== null && $existing['reverted_at'] === null;
        if ($alreadyResolved) continue; // don't override a stage someone already actioned directly

        if ($existing) {
            $updStmt = $db->prepare(
                "UPDATE cm_renewal_followup_actions SET
                    followed_up_at = NOW(), resolution_type = 'milestone_done',
                    resolved_by_note_id = :note_id, resolved_by_action_id = NULL, reverted_at = NULL
                 WHERE id = :id"
            );
            $updStmt->execute(['note_id' => $noteId, 'id' => $existing['id']]);
        } else {
            $insStmt = $db->prepare(
                "INSERT INTO cm_renewal_followup_actions
                    (cm_certification_id, milestone_key, milestone_date, stage, followed_up_at, resolution_type, resolved_by_note_id)
                 VALUES (:cert_id, :milestone_key, :milestone_date, :stage, NOW(), 'milestone_done', :note_id)"
            );
            $insStmt->execute(['cert_id' => $certId, 'milestone_key' => $milestoneKey, 'milestone_date' => $milestoneDate, 'stage' => $stage, 'note_id' => $noteId]);
        }
    }
}

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
    $milestoneCompleted = trim((string) ($input['milestone_completed'] ?? ''));
    $milestoneCompletedApplied = null;
    $rolloverNote = '';
    $previousState = null;
    $completedMilestoneDateForFollowup = null;

    $confirmOutOfOrder = !empty($input['confirm_out_of_order']);

    $db->beginTransaction();
    try {
        if ($certId > 0) {
            // Confirm the cert actually belongs to this client before
            // touching it or attributing the note to it. FOR UPDATE locks
            // this row for the rest of the transaction — without it, two
            // people completing Recertification on the same cert within
            // moments of each other could both read the same "old"
            // expiry_date and each compute a conflicting "new cycle",
            // silently corrupting the dates depending on write order.
            $certStmt = $db->prepare(
                'SELECT id, status, cycle_stage, cm_scheme_type_id, expiry_date, surveillance_1_date, surveillance_1_completed_at,
                        surveillance_2_date, surveillance_2_completed_at, recertification_completed_at
                 FROM cm_certifications WHERE id = :cert_id AND cm_client_id = :client_id LIMIT 1 FOR UPDATE'
            );
            $certStmt->execute(['cert_id' => $certId, 'client_id' => $clientId]);
            $cert = $certStmt->fetch();
            if (!$cert) {
                $db->rollBack();
                cm_json_error('That certification does not belong to this client.', 422);
            }

            $previousState = ['cert_id' => $certId];

            if ($newStatus !== '' && in_array($newStatus, CM_CERT_STATUSES, true) && $newStatus !== $cert['status']) {
                $previousState['status_was'] = $cert['status'];
                $updateStmt = $db->prepare('UPDATE cm_certifications SET status = :status WHERE id = :id');
                $updateStmt->execute(['status' => $newStatus, 'id' => $certId]);
                $statusChangedTo = $newStatus;
            }

            if ($milestoneCompleted !== '' && isset(CM_CYCLE_MILESTONES[$milestoneCompleted])) {
                if ($milestoneCompleted === 'recertification') {
                    // Guard: completing Recertification while an earlier
                    // milestone in THIS cycle was scheduled but never
                    // confirmed done is very likely a mistake (wrong button
                    // clicked) rather than intentional — require an
                    // explicit confirmation flag instead of silently
                    // rolling the whole cycle forward and discarding
                    // Surv 1/2 dates that were never actually marked done.
                    $skippedMilestones = [];
                    if ($cert['surveillance_1_date'] !== null && $cert['surveillance_1_completed_at'] === null) {
                        $skippedMilestones[] = 'Surveillance 1';
                    }
                    if ($cert['surveillance_2_date'] !== null && $cert['surveillance_2_completed_at'] === null) {
                        $skippedMilestones[] = 'Surveillance 2';
                    }
                    if ($skippedMilestones && !$confirmOutOfOrder) {
                        $db->rollBack();
                        cm_json_response([
                            'error' => implode(' and ', $skippedMilestones) . ' on this certification '
                                . (count($skippedMilestones) > 1 ? 'were' : 'was') . ' never marked complete. '
                                . 'Completing Recertification now will still start a new cycle and discard those as done. '
                                . 'Resubmit with confirm_out_of_order to proceed anyway.',
                            'requires_confirmation' => 'out_of_order_recert',
                            'skipped_milestones' => $skippedMilestones,
                        ], 409);
                    }

                    // Recertification completing isn't just "mark this one
                    // done" — it's the boundary of the whole certification
                    // cycle (length varies by scheme — 3 years for ISO, 2
                    // for BizSafe, per cm_scheme_types.default_cycle_years).
                    // Anchor the NEW cycle from TODAY — the date this
                    // recertification is actually being completed — NOT
                    // from the old scheduled expiry_date. Anchoring from
                    // the old scheduled date is wrong whenever a recert was
                    // completed late (common — this is often overdue by the
                    // time someone gets to it): it would produce a "new"
                    // cycle whose own milestones are already in the past
                    // before the cycle even starts, which is nonsensical.
                    $oldSurv1 = $cert['surveillance_1_date'];
                    $oldSurv2 = $cert['surveillance_2_date'];
                    $oldExpiry = $cert['expiry_date'];
                    $anchorDate = date('Y-m-d');
                    $cycleYears = cm_scheme_cycle_years($db, (int) $cert['cm_scheme_type_id']);
                    [$newSurv1, $newSurv2, $newExpiry] = cm_compute_cycle_milestones_from($anchorDate, $cycleYears);

                    $rolloverStmt = $db->prepare(
                        "UPDATE cm_certifications SET
                            surveillance_1_date = :surv1, surveillance_2_date = :surv2, expiry_date = :expiry,
                            surveillance_1_completed_at = NULL, surveillance_2_completed_at = NULL, recertification_completed_at = NULL,
                            cycle_stage = 'initial'
                         WHERE id = :id"
                    );
                    $rolloverStmt->execute(['surv1' => $newSurv1, 'surv2' => $newSurv2, 'expiry' => $newExpiry, 'id' => $certId]);

                    // Recertifying implies the certification is active again
                    // — but only if the caller didn't already explicitly
                    // choose a different status above (e.g. deliberately
                    // suspending for an unrelated reason at the same time).
                    if ($statusChangedTo === null && $cert['status'] !== 'active') {
                        $previousState['status_was'] = $previousState['status_was'] ?? $cert['status'];
                        $activateStmt = $db->prepare("UPDATE cm_certifications SET status = 'active' WHERE id = :id");
                        $activateStmt->execute(['id' => $certId]);
                        $statusChangedTo = 'active';
                    }

                    // Full snapshot of everything this rollover overwrites —
                    // this is what a revert restores. Not just the note
                    // text (which stays for human readability) but a
                    // precise, structured "put it back exactly like this."
                    $previousState['rollover'] = [
                        'surveillance_1_date' => $oldSurv1,
                        'surveillance_1_completed_at' => $cert['surveillance_1_completed_at'],
                        'surveillance_2_date' => $oldSurv2,
                        'surveillance_2_completed_at' => $cert['surveillance_2_completed_at'],
                        'expiry_date' => $oldExpiry,
                        'recertification_completed_at' => $cert['recertification_completed_at'],
                        'cycle_stage' => $cert['cycle_stage'],
                    ];

                    // Record the OLD dates too, not just the new ones — the
                    // only surviving trace of the previous cycle once this
                    // UPDATE runs is this note's text, since the dates
                    // themselves get overwritten and there's no separate
                    // cycle-history table. This is the paper trail a manual
                    // revert would need if this was triggered by mistake.
                    $rolloverNote = " Previous cycle: Surv1 " . ($oldSurv1 ?? '—') . ", Surv2 " . ($oldSurv2 ?? '—') . ", Recert " . ($oldExpiry ?? '—') . "."
                        . " New cycle: Surv1 $newSurv1, Surv2 $newSurv2, Recert $newExpiry."
                        . ($skippedMilestones ? ' (Confirmed despite ' . implode(' and ', $skippedMilestones) . ' not being marked complete.)' : '');
                    // The follow-up campaign that was resolving is for the
                    // OLD recert date (the one that just happened) — not
                    // the new cycle's date, which hasn't started yet.
                    $completedMilestoneDateForFollowup = $oldExpiry;
                } else {
                    $completedCol = CM_CYCLE_MILESTONES[$milestoneCompleted]['completed_col'];
                    $previousState['simple_milestone'] = ['column' => $completedCol, 'old_value' => $cert[$completedCol] ?? null];
                    // Column name comes only from the CM_CYCLE_MILESTONES
                    // whitelist above (never from raw user input), so it's
                    // safe to interpolate — there's no placeholder to bind a
                    // column NAME to in PDO anyway.
                    $milestoneStmt = $db->prepare("UPDATE cm_certifications SET `$completedCol` = NOW() WHERE id = :id");
                    $milestoneStmt->execute(['id' => $certId]);
                    $rolloverNote = '';
                    $completedMilestoneDateForFollowup = $cert[CM_CYCLE_MILESTONES[$milestoneCompleted]['date_col']] ?? null;
                }
                $milestoneCompletedApplied = $milestoneCompleted;
            }
        }

        $previousStateJson = ($previousState !== null && count($previousState) > 1) ? json_encode($previousState) : null;

        $insertStmt = $db->prepare(
            'INSERT INTO cm_client_followup_notes
                (cm_client_id, cm_certification_id, activity_type, note, outcome, status_changed_to, milestone_completed, previous_state, created_by, created_by_name)
             VALUES
                (:client_id, :cert_id, :activity_type, :note, :outcome, :status_changed_to, :milestone_completed, :previous_state, :created_by, :created_by_name)'
        );
        $insertStmt->execute([
            'client_id'           => $clientId,
            'cert_id'             => $certId > 0 ? $certId : null,
            'activity_type'       => $activityType,
            'note'                => $note,
            'outcome'             => $outcome,
            'status_changed_to'   => $statusChangedTo,
            'milestone_completed' => $milestoneCompletedApplied,
            'previous_state'      => $previousStateJson,
            'created_by'          => $user['id'],
            'created_by_name'     => $user['name'],
        ]);
        $newId = (int) $db->lastInsertId();

        // TOUCHES EXISTING LOGIC (see docblock on the function above) —
        // if this note completed a milestone, resolve any follow-up
        // stages that were chasing it.
        if ($milestoneCompletedApplied !== null && $certId > 0) {
            cm_followup_auto_resolve_on_milestone_done($db, $certId, $milestoneCompletedApplied, $completedMilestoneDateForFollowup, $newId);
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    $logDetail = $activityType
        . ($statusChangedTo ? " (status -> $statusChangedTo)" : '')
        . ($milestoneCompletedApplied ? " ({$milestoneCompletedApplied} marked complete)" : '')
        . ': ' . mb_substr($note, 0, 100)
        . $rolloverNote;
    cm_log_activity($user['id'], 'log_activity', 'cm_client', $clientId, $logDetail);

    cm_json_response([
        'id' => $newId,
        'status_changed_to' => $statusChangedTo,
        'milestone_completed' => $milestoneCompletedApplied,
        'cycle_rolled_over' => $rolloverNote !== '',
        'undoable' => $previousStateJson !== null,
    ], 201);
}

if ($method === 'PUT') {
    ehs_verify_csrf();
    $noteId = (int) ($_GET['id'] ?? 0);
    if ($noteId <= 0) cm_json_error('A valid note id is required.', 422);

    $input = cm_json_input();
    if (($input['action'] ?? '') !== 'undo') {
        cm_json_error('Unsupported action.', 422);
    }

    $db->beginTransaction();
    try {
        $noteStmt = $db->prepare('SELECT * FROM cm_client_followup_notes WHERE id = :id LIMIT 1 FOR UPDATE');
        $noteStmt->execute(['id' => $noteId]);
        $note = $noteStmt->fetch();
        if (!$note) {
            $db->rollBack();
            cm_json_error('Activity entry not found.', 404);
        }
        if ($note['reverted_at'] !== null) {
            $db->rollBack();
            cm_json_error('This activity has already been reverted.', 422);
        }
        if ($note['previous_state'] === null) {
            $db->rollBack();
            cm_json_error('This activity did not change the certification\'s status or milestones, so there\'s nothing to revert.', 422);
        }

        $state = json_decode($note['previous_state'], true);
        $certId = (int) ($state['cert_id'] ?? 0);
        if ($certId <= 0) {
            $db->rollBack();
            cm_json_error('Could not determine which certification this entry affected.', 500);
        }

        // Safety check: refuse to undo if a NEWER entry on the same
        // certification also changed status/milestones and hasn't itself
        // been reverted — undoing this one underneath it would silently
        // clobber that newer, presumably intentional, change. Revert the
        // newer one first if that's really what's needed.
        $newerStmt = $db->prepare(
            'SELECT id FROM cm_client_followup_notes
             WHERE cm_certification_id = :cert_id AND id > :note_id AND reverted_at IS NULL
               AND (status_changed_to IS NOT NULL OR milestone_completed IS NOT NULL)
             ORDER BY id ASC LIMIT 1'
        );
        $newerStmt->execute(['cert_id' => $certId, 'note_id' => $noteId]);
        $newerId = $newerStmt->fetchColumn();
        if ($newerId) {
            $db->rollBack();
            cm_json_error("A newer activity (#$newerId) already changed this certification's status or milestones. Revert that one first.", 409);
        }

        $certLockStmt = $db->prepare('SELECT id FROM cm_certifications WHERE id = :id LIMIT 1 FOR UPDATE');
        $certLockStmt->execute(['id' => $certId]);
        if (!$certLockStmt->fetch()) {
            $db->rollBack();
            cm_json_error('The certification this entry affected no longer exists.', 404);
        }

        if (isset($state['rollover']) && is_array($state['rollover'])) {
            $r = $state['rollover'];
            $undoStmt = $db->prepare(
                'UPDATE cm_certifications SET
                    surveillance_1_date = :surv1, surveillance_1_completed_at = :surv1_completed,
                    surveillance_2_date = :surv2, surveillance_2_completed_at = :surv2_completed,
                    expiry_date = :expiry, recertification_completed_at = :recert_completed,
                    cycle_stage = :cycle_stage
                 WHERE id = :id'
            );
            $undoStmt->execute([
                'surv1' => $r['surveillance_1_date'] ?? null,
                'surv1_completed' => $r['surveillance_1_completed_at'] ?? null,
                'surv2' => $r['surveillance_2_date'] ?? null,
                'surv2_completed' => $r['surveillance_2_completed_at'] ?? null,
                'expiry' => $r['expiry_date'] ?? null,
                'recert_completed' => $r['recertification_completed_at'] ?? null,
                'cycle_stage' => $r['cycle_stage'] ?? 'initial',
                'id' => $certId,
            ]);
        } elseif (isset($state['simple_milestone']) && is_array($state['simple_milestone'])) {
            $column = $state['simple_milestone']['column'] ?? '';
            $validColumns = array_column(CM_CYCLE_MILESTONES, 'completed_col');
            if (in_array($column, $validColumns, true)) {
                $undoStmt = $db->prepare("UPDATE cm_certifications SET `$column` = :old_value WHERE id = :id");
                $undoStmt->execute(['old_value' => $state['simple_milestone']['old_value'] ?? null, 'id' => $certId]);
            }
        }

        if (array_key_exists('status_was', $state)) {
            $statusUndoStmt = $db->prepare('UPDATE cm_certifications SET status = :status WHERE id = :id');
            $statusUndoStmt->execute(['status' => $state['status_was'], 'id' => $certId]);
        }

        $markRevertedStmt = $db->prepare('UPDATE cm_client_followup_notes SET reverted_at = NOW() WHERE id = :id');
        $markRevertedStmt->execute(['id' => $noteId]);

        // TOUCHES EXISTING LOGIC — Q1 confirmed: undoing a milestone
        // completion should reopen any follow-up stages it auto-resolved,
        // since the reason they closed no longer holds.
        $followupCascadeStmt = $db->prepare(
            'SELECT id, stage FROM cm_renewal_followup_actions WHERE resolved_by_note_id = :note_id AND reverted_at IS NULL FOR UPDATE'
        );
        $followupCascadeStmt->execute(['note_id' => $noteId]);
        $followupCascadeRows = $followupCascadeStmt->fetchAll();
        foreach ($followupCascadeRows as $fr) {
            $followupReopenStmt = $db->prepare('UPDATE cm_renewal_followup_actions SET reverted_at = NOW() WHERE id = :id');
            $followupReopenStmt->execute(['id' => $fr['id']]);
        }

        // A visible trail entry for the revert itself — the timeline
        // should show that this happened, not just silently roll back.
        $revertNoteStmt = $db->prepare(
            'INSERT INTO cm_client_followup_notes
                (cm_client_id, cm_certification_id, activity_type, note, created_by, created_by_name)
             VALUES (:client_id, :cert_id, \'other\', :note, :created_by, :created_by_name)'
        );
        $revertNoteStmt->execute([
            'client_id' => $note['cm_client_id'],
            'cert_id' => $certId,
            'note' => "Reverted activity #$noteId (" . mb_substr((string) $note['note'], 0, 80) . ')'
                . ($followupCascadeRows ? ' [reopened follow-up stage ' . implode(', ', array_column($followupCascadeRows, 'stage')) . ']' : ''),
            'created_by' => $user['id'],
            'created_by_name' => $user['name'],
        ]);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    cm_log_activity($user['id'], 'undo_log_activity', 'cm_client', (int) $note['cm_client_id'], "Reverted activity #$noteId");

    cm_json_response(['success' => true]);
}

cm_json_error('Method not allowed.', 405);