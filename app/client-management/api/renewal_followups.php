<?php
/**
 * client-management/api/renewal_followups.php
 *
 * GET  (?entity=&scheme_category=&industry=&responsible_person_id=)
 *      -> { today: [...], followup_stage_days: {1:120,2:60,3:30,4:4} }
 *      "today" = every certification whose next milestone
 *      (cm_certification_next_due()) has crossed a follow-up stage
 *      threshold and that stage is NOT already resolved. Computed 100%
 *      live from cm_certifications' own dates every time — this endpoint
 *      never caches or snapshots "who's due"; cm_renewal_followup_actions
 *      only stores RESOLUTION state (has this stage-slot been actioned),
 *      which is the one thing that has to persist.
 *
 * GET  ?cert_id=X&history=1
 *      -> { history: [...] } — every stage-action row (resolved or not,
 *         reverted or not) for one certification, oldest first. This is
 *         the compact stage-by-stage timeline; the full narrative log
 *         (notes, outcomes, WhatsApp/call/etc.) is still
 *         cm_client_followup_notes via the existing Log Activity history.
 *
 * POST { cert_id, note }
 *      -> marks the certification's CURRENT stage (recomputed live at
 *         the moment of the request, not trusted from the client) as
 *         followed up. One-directional supersede: also auto-resolves any
 *         earlier, still-open stage for the SAME milestone+date. Writes a
 *         linked cm_client_followup_notes entry so this shows in the
 *         normal activity timeline too.
 *
 * PUT  ?id=X { action: "undo" }
 *      -> reverts that stage action, and cascades to reopen anything it
 *         had auto-resolved via the one-directional supersede (one level
 *         — a chain of supersedes reopens the direct link only, not a
 *         deep recursive unwind, to avoid over-engineering an edge case
 *         within an edge case).
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/cm_helpers.php';

$user = ehs_require_role(['super_admin', 'admin'], true);
$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];

/** Reverse-lookup: cm_certification_next_due()'s human label -> CM_CYCLE_MILESTONES key. */
function cm_followup_milestone_key_from_label(string $label): ?string
{
    foreach (CM_CYCLE_MILESTONES as $key => $m) {
        if ($m['label'] === $label) return $key;
    }
    return null;
}

if ($method === 'GET' && isset($_GET['cert_id']) && isset($_GET['history'])) {
    $certId = (int) $_GET['cert_id'];
    if ($certId <= 0) cm_json_error('A valid cert_id is required.', 422);

    $stmt = $db->prepare(
        'SELECT a.*, COALESCE(u.name, a.followed_up_by_name) AS followed_up_by_display_name
         FROM cm_renewal_followup_actions a LEFT JOIN users u ON u.id = a.followed_up_by
         WHERE a.cm_certification_id = :cert_id
         ORDER BY a.milestone_date ASC, a.stage ASC'
    );
    $stmt->execute(['cert_id' => $certId]);
    cm_json_response(['history' => $stmt->fetchAll()]);
}

if ($method === 'GET') {
    $today = date('Y-m-d');
    $schemeCategory = trim((string) ($_GET['scheme_category'] ?? ''));
    $industry       = trim((string) ($_GET['industry'] ?? ''));
    $entity         = trim((string) ($_GET['entity'] ?? ''));
    $responsibleId  = (int) ($_GET['responsible_person_id'] ?? 0);

    $where = [
        "cert.status != 'withdrawn'",
        '(cert.surveillance_1_date IS NOT NULL OR cert.surveillance_2_date IS NOT NULL OR cert.expiry_date IS NOT NULL)',
    ];
    $params = [];
    if ($schemeCategory !== '' && in_array($schemeCategory, ['ISO', 'BizSafe', 'JASANZ', 'Other'], true)) {
        $where[] = 'st.category = :scheme_category';
        $params['scheme_category'] = $schemeCategory;
    }
    if ($industry !== '') {
        $where[] = 'c.industry_sector = :industry';
        $params['industry'] = $industry;
    }
    if ($entity !== '' && in_array($entity, CM_ENTITIES, true)) {
        $where[] = 'c.entity = :entity';
        $params['entity'] = $entity;
    }
    if ($responsibleId > 0) {
        $where[] = 'cert.responsible_person_id = :responsible_id';
        $params['responsible_id'] = $responsibleId;
    }

    $stmt = $db->prepare(
        'SELECT cert.id, cert.surveillance_1_date, cert.surveillance_1_completed_at,
                cert.surveillance_2_date, cert.surveillance_2_completed_at,
                cert.expiry_date, cert.recertification_completed_at,
                c.id AS client_id, c.company_name, c.entity,
                st.name AS scheme_name,
                COALESCE(u.name, cert.responsible_person_name) AS responsible_person
         FROM cm_certifications cert
         JOIN cm_clients c ON c.id = cert.cm_client_id
         JOIN cm_scheme_types st ON st.id = cert.cm_scheme_type_id
         LEFT JOIN users u ON u.id = cert.responsible_person_id
         WHERE ' . implode(' AND ', $where)
    );
    $stmt->execute($params);
    $allCerts = $stmt->fetchAll();

    // Work out each cert's current follow-up stage (if any) purely from
    // live dates — no DB write happens here, this is read-only.
    $dueCandidates = [];
    foreach ($allCerts as $cert) {
        $next = cm_certification_next_due($cert, $today);
        if ($next['date'] === null) continue;

        $stage = cm_followup_current_stage($next['date'], $today);
        if ($stage === null) continue; // not due yet, nothing to show

        $milestoneKey = cm_followup_milestone_key_from_label($next['label']);
        if ($milestoneKey === null) continue; // shouldn't happen, defensive

        $daysUntil = (int) round((strtotime($next['date']) - strtotime($today)) / 86400);

        $dueCandidates[] = [
            'cert_id' => $cert['id'],
            'client_id' => $cert['client_id'],
            'company_name' => $cert['company_name'],
            'entity' => $cert['entity'],
            'scheme_name' => $cert['scheme_name'],
            'responsible_person' => $cert['responsible_person'],
            'milestone_key' => $milestoneKey,
            'milestone_label' => $next['label'],
            'milestone_date' => $next['date'],
            'overdue' => $next['overdue'],
            'days_until' => $daysUntil,
            'stage' => $stage,
        ];
    }

    // Batch-fetch resolution state for every candidate cert in one query
    // rather than one query per cert.
    $resolvedIndex = [];
    $certIds = array_values(array_unique(array_column($dueCandidates, 'cert_id')));
    if ($certIds) {
        $inPlaceholders = implode(',', array_fill(0, count($certIds), '?'));
        $resolvedStmt = $db->prepare(
            "SELECT cm_certification_id, milestone_key, milestone_date, stage
             FROM cm_renewal_followup_actions
             WHERE cm_certification_id IN ($inPlaceholders) AND followed_up_at IS NOT NULL AND reverted_at IS NULL"
        );
        $resolvedStmt->execute($certIds);
        foreach ($resolvedStmt->fetchAll() as $r) {
            $key = $r['cm_certification_id'] . '|' . $r['milestone_key'] . '|' . $r['milestone_date'] . '|' . $r['stage'];
            $resolvedIndex[$key] = true;
        }
    }

    $today_list = [];
    foreach ($dueCandidates as $d) {
        $key = $d['cert_id'] . '|' . $d['milestone_key'] . '|' . $d['milestone_date'] . '|' . $d['stage'];
        if (isset($resolvedIndex[$key])) continue; // already actioned — don't show again
        $today_list[] = $d;
    }

    // Most overdue / most urgent first.
    usort($today_list, fn($a, $b) => $b['stage'] <=> $a['stage'] ?: $a['days_until'] <=> $b['days_until']);

    cm_json_response(['today' => $today_list, 'followup_stage_days' => CM_FOLLOWUP_STAGE_DAYS]);
}

if ($method === 'POST') {
    ehs_verify_csrf();
    $input = cm_json_input();

    $certId = (int) ($input['cert_id'] ?? 0);
    $note = cm_clean_str($input['note'] ?? null, 2000);
    if ($certId <= 0) cm_json_error('cert_id is required.', 422);
    if ($note === null) cm_json_error('A note is required when marking a follow-up.', 422);

    $db->beginTransaction();
    try {
        $certStmt = $db->prepare(
            'SELECT id, cm_client_id, surveillance_1_date, surveillance_1_completed_at,
                    surveillance_2_date, surveillance_2_completed_at, expiry_date, recertification_completed_at
             FROM cm_certifications WHERE id = :id LIMIT 1 FOR UPDATE'
        );
        $certStmt->execute(['id' => $certId]);
        $cert = $certStmt->fetch();
        if (!$cert) {
            $db->rollBack();
            cm_json_error('Certification not found.', 404);
        }

        // Recompute live — never trust a stage/date the client sent, since
        // it could be stale by the time the request lands.
        $today = date('Y-m-d');
        $next = cm_certification_next_due($cert, $today);
        $stage = cm_followup_current_stage($next['date'], $today);
        $milestoneKey = $next['date'] !== null ? cm_followup_milestone_key_from_label($next['label']) : null;

        if ($stage === null || $milestoneKey === null) {
            $db->rollBack();
            cm_json_error('This certification has nothing due for follow-up right now.', 422);
        }

        $milestoneDate = $next['date'];

        // Upsert this exact stage-slot as followed-up.
        $existingStmt = $db->prepare(
            'SELECT id FROM cm_renewal_followup_actions
             WHERE cm_certification_id = :cert_id AND milestone_key = :milestone_key
               AND milestone_date = :milestone_date AND stage = :stage LIMIT 1 FOR UPDATE'
        );
        $existingStmt->execute(['cert_id' => $certId, 'milestone_key' => $milestoneKey, 'milestone_date' => $milestoneDate, 'stage' => $stage]);
        $existingId = $existingStmt->fetchColumn();

        if ($existingId) {
            $updateStmt = $db->prepare(
                "UPDATE cm_renewal_followup_actions SET
                    followed_up_at = NOW(), followed_up_by = :user_id, followed_up_by_name = :user_name,
                    note = :note, resolution_type = 'followed_up', resolved_by_note_id = NULL,
                    resolved_by_action_id = NULL, reverted_at = NULL
                 WHERE id = :id"
            );
            $updateStmt->execute(['user_id' => $user['id'], 'user_name' => $user['name'], 'note' => $note, 'id' => $existingId]);
            $actionId = (int) $existingId;
        } else {
            $insertStmt = $db->prepare(
                "INSERT INTO cm_renewal_followup_actions
                    (cm_certification_id, milestone_key, milestone_date, stage, followed_up_at,
                     followed_up_by, followed_up_by_name, note, resolution_type)
                 VALUES (:cert_id, :milestone_key, :milestone_date, :stage, NOW(), :user_id, :user_name, :note, 'followed_up')"
            );
            $insertStmt->execute([
                'cert_id' => $certId, 'milestone_key' => $milestoneKey, 'milestone_date' => $milestoneDate,
                'stage' => $stage, 'user_id' => $user['id'], 'user_name' => $user['name'], 'note' => $note,
            ]);
            $actionId = (int) $db->lastInsertId();
        }

        // One-directional supersede: earlier stages (1..stage-1) for the
        // SAME milestone+date that aren't already resolved get closed out
        // too — a later-stage follow-up implies whatever an earlier stage
        // would have asked about was already covered.
        $supersededStages = [];
        for ($earlierStage = 1; $earlierStage < $stage; $earlierStage++) {
            $earlierStmt = $db->prepare(
                'SELECT id, followed_up_at, reverted_at FROM cm_renewal_followup_actions
                 WHERE cm_certification_id = :cert_id AND milestone_key = :milestone_key
                   AND milestone_date = :milestone_date AND stage = :stage LIMIT 1 FOR UPDATE'
            );
            $earlierStmt->execute(['cert_id' => $certId, 'milestone_key' => $milestoneKey, 'milestone_date' => $milestoneDate, 'stage' => $earlierStage]);
            $earlierRow = $earlierStmt->fetch();

            $alreadyResolved = $earlierRow && $earlierRow['followed_up_at'] !== null && $earlierRow['reverted_at'] === null;
            if ($alreadyResolved) continue; // don't touch a stage someone already actioned directly

            if ($earlierRow) {
                $closeStmt = $db->prepare(
                    "UPDATE cm_renewal_followup_actions SET
                        followed_up_at = NOW(), resolution_type = 'stage_superseded',
                        resolved_by_action_id = :action_id, reverted_at = NULL
                     WHERE id = :id"
                );
                $closeStmt->execute(['action_id' => $actionId, 'id' => $earlierRow['id']]);
            } else {
                $closeInsertStmt = $db->prepare(
                    "INSERT INTO cm_renewal_followup_actions
                        (cm_certification_id, milestone_key, milestone_date, stage, followed_up_at, resolution_type, resolved_by_action_id)
                     VALUES (:cert_id, :milestone_key, :milestone_date, :stage, NOW(), 'stage_superseded', :action_id)"
                );
                $closeInsertStmt->execute([
                    'cert_id' => $certId, 'milestone_key' => $milestoneKey, 'milestone_date' => $milestoneDate,
                    'stage' => $earlierStage, 'action_id' => $actionId,
                ]);
            }
            $supersededStages[] = $earlierStage;
        }

        // Linked entry in the normal activity timeline, so this shows up
        // in the same Log Activity history staff already look at.
        $noteInsertStmt = $db->prepare(
            "INSERT INTO cm_client_followup_notes
                (cm_client_id, cm_certification_id, activity_type, note, created_by, created_by_name)
             VALUES (:client_id, :cert_id, 'other', :note, :user_id, :user_name)"
        );
        $noteInsertStmt->execute([
            'client_id' => $cert['cm_client_id'],
            'cert_id' => $certId,
            'note' => "Stage $stage follow-up (" . CM_CYCLE_MILESTONES[$milestoneKey]['label'] . ", due $milestoneDate): $note"
                . ($supersededStages ? ' [also cleared stage ' . implode(', ', $supersededStages) . ']' : ''),
            'user_id' => $user['id'],
            'user_name' => $user['name'],
        ]);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    cm_json_response(['id' => $actionId, 'stage' => $stage, 'superseded_stages' => $supersededStages], 201);
}

if ($method === 'PUT') {
    ehs_verify_csrf();
    $actionId = (int) ($_GET['id'] ?? 0);
    if ($actionId <= 0) cm_json_error('A valid action id is required.', 422);

    $input = cm_json_input();
    if (($input['action'] ?? '') !== 'undo') cm_json_error('Unsupported action.', 422);

    $db->beginTransaction();
    try {
        $rowStmt = $db->prepare('SELECT * FROM cm_renewal_followup_actions WHERE id = :id LIMIT 1 FOR UPDATE');
        $rowStmt->execute(['id' => $actionId]);
        $row = $rowStmt->fetch();
        if (!$row) {
            $db->rollBack();
            cm_json_error('Follow-up action not found.', 404);
        }
        if ($row['followed_up_at'] === null || $row['reverted_at'] !== null) {
            $db->rollBack();
            cm_json_error('This action isn\'t currently resolved, so there\'s nothing to undo.', 422);
        }

        $revertStmt = $db->prepare('UPDATE cm_renewal_followup_actions SET reverted_at = NOW() WHERE id = :id');
        $revertStmt->execute(['id' => $actionId]);

        // Cascade: reopen anything THIS row's resolution had auto-closed
        // via the one-directional supersede (one level, not a deep
        // recursive unwind).
        $cascadeStmt = $db->prepare(
            'SELECT id FROM cm_renewal_followup_actions WHERE resolved_by_action_id = :id AND reverted_at IS NULL FOR UPDATE'
        );
        $cascadeStmt->execute(['id' => $actionId]);
        $cascadeIds = $cascadeStmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($cascadeIds as $cid) {
            $cascadeRevertStmt = $db->prepare('UPDATE cm_renewal_followup_actions SET reverted_at = NOW() WHERE id = :id');
            $cascadeRevertStmt->execute(['id' => $cid]);
        }

        $clientIdStmt = $db->prepare('SELECT cm_client_id FROM cm_certifications WHERE id = :id LIMIT 1');
        $clientIdStmt->execute(['id' => $row['cm_certification_id']]);
        $clientIdForNote = $clientIdStmt->fetchColumn();

        $noteInsertStmt = $db->prepare(
            "INSERT INTO cm_client_followup_notes
                (cm_client_id, cm_certification_id, activity_type, note, created_by, created_by_name)
             VALUES (:client_id, :cert_id, 'other', :note, :user_id, :user_name)"
        );
        $noteInsertStmt->execute([
            'client_id' => $clientIdForNote,
            'cert_id' => $row['cm_certification_id'],
            'note' => "Reverted Stage {$row['stage']} follow-up" . ($cascadeIds ? ' (also reopened stage ' . implode(', ', $cascadeIds) . ')' : ''),
            'user_id' => $user['id'],
            'user_name' => $user['name'],
        ]);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    cm_json_response(['success' => true, 'reopened_count' => count($cascadeIds)]);
}

cm_json_error('Method not allowed.', 405);