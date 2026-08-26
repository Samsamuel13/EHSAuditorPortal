<?php
/**
 * crm/api/quotations.php
 *
 * GET  ?lead_id=X               -> all versions for a lead, newest first, each with its items
 * GET  ?id=X                    -> one quotation version + items
 * POST { lead_id, valid_until, tax_percent, notes, items: [{description,qty,unit_price}] }
 *      -> creates the NEXT version for that lead (version = max(existing)+1, starts at 1).
 *         A re-negotiated quote is always a new row, never an overwrite of a previous version.
 * PUT  ?id=X { status }         -> status transition only (draft -> sent -> accepted/rejected/expired).
 *                                  Line items are immutable once a version exists — to change
 *                                  pricing, create a new version instead.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/crm_helpers.php';

$user = ehs_require_role(['super_admin', 'admin'], true);
$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];

function crm_load_quotation_with_items(PDO $db, int $quotationId): ?array
{
    $stmt = $db->prepare(
        'SELECT q.*, COALESCE(u.name, q.created_by_name) AS created_by_display_name
         FROM crm_quotations q LEFT JOIN users u ON u.id = q.created_by
         WHERE q.id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $quotationId]);
    $quotation = $stmt->fetch();
    if (!$quotation) return null;

    $itemStmt = $db->prepare('SELECT * FROM crm_quotation_items WHERE crm_quotation_id = :qid ORDER BY sort_order ASC, id ASC');
    $itemStmt->execute(['qid' => $quotationId]);
    $quotation['items'] = $itemStmt->fetchAll();
    return $quotation;
}

if ($method === 'GET' && isset($_GET['id'])) {
    $quotation = crm_load_quotation_with_items($db, (int) $_GET['id']);
    if (!$quotation) crm_json_error('Quotation not found.', 404);
    crm_json_response(['quotation' => $quotation]);
}

if ($method === 'GET') {
    $leadId = (int) ($_GET['lead_id'] ?? 0);
    if ($leadId <= 0) crm_json_error('lead_id is required.', 422);

    $stmt = $db->prepare('SELECT id FROM crm_quotations WHERE crm_lead_id = :lead_id ORDER BY version DESC');
    $stmt->execute(['lead_id' => $leadId]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $quotations = array_map(fn($id) => crm_load_quotation_with_items($db, (int) $id), $ids);
    crm_json_response(['quotations' => $quotations]);
}

if ($method === 'POST') {
    ehs_verify_csrf();
    $input = crm_json_input();

    $leadId = (int) ($input['lead_id'] ?? 0);
    if ($leadId <= 0) crm_json_error('lead_id is required.', 422);

    $leadStmt = $db->prepare('SELECT id FROM crm_leads WHERE id = :id LIMIT 1');
    $leadStmt->execute(['id' => $leadId]);
    if (!$leadStmt->fetch()) crm_json_error('Lead not found.', 404);

    $items = is_array($input['items'] ?? null) ? $input['items'] : [];
    if (!$items) crm_json_error('At least one line item is required.', 422);

    $validUntil = trim((string) ($input['valid_until'] ?? ''));
    if ($validUntil !== '' && !crm_is_valid_date($validUntil)) {
        crm_json_error('valid_until must be YYYY-MM-DD.', 422);
    }
    $taxPercent = max(0, (float) ($input['tax_percent'] ?? 0));

    $subtotal = 0.0;
    $cleanItems = [];
    foreach ($items as $i => $item) {
        $desc = crm_clean_str($item['description'] ?? null, 255);
        if ($desc === null) continue;
        $qty = max(0.01, (float) ($item['qty'] ?? 1));
        $unitPrice = max(0, (float) ($item['unit_price'] ?? 0));
        $lineTotal = round($qty * $unitPrice, 2);
        $subtotal += $lineTotal;
        $cleanItems[] = ['description' => $desc, 'qty' => $qty, 'unit_price' => $unitPrice, 'line_total' => $lineTotal, 'sort_order' => $i];
    }
    if (!$cleanItems) crm_json_error('At least one valid line item is required.', 422);

    $subtotal = round($subtotal, 2);
    $taxAmount = round($subtotal * $taxPercent / 100, 2);
    $total = round($subtotal + $taxAmount, 2);

    $db->beginTransaction();
    try {
        $versionStmt = $db->prepare('SELECT COALESCE(MAX(version), 0) + 1 FROM crm_quotations WHERE crm_lead_id = :lead_id FOR UPDATE');
        $versionStmt->execute(['lead_id' => $leadId]);
        $version = (int) $versionStmt->fetchColumn();

        $quoteNumber = crm_next_quote_number($db, $leadId, $version);

        $insertStmt = $db->prepare(
            'INSERT INTO crm_quotations
                (crm_lead_id, version, quote_number, status, valid_until, subtotal, tax_percent, tax_amount, total, notes, created_by, created_by_name)
             VALUES
                (:lead_id, :version, :quote_number, \'draft\', :valid_until, :subtotal, :tax_percent, :tax_amount, :total, :notes, :created_by, :created_by_name)'
        );
        $insertStmt->execute([
            'lead_id'         => $leadId,
            'version'         => $version,
            'quote_number'    => $quoteNumber,
            'valid_until'     => $validUntil !== '' ? $validUntil : null,
            'subtotal'        => $subtotal,
            'tax_percent'     => $taxPercent,
            'tax_amount'      => $taxAmount,
            'total'           => $total,
            'notes'           => crm_clean_str($input['notes'] ?? null, 2000),
            'created_by'      => $user['id'],
            'created_by_name' => $user['name'],
        ]);
        $quotationId = (int) $db->lastInsertId();

        $itemStmt = $db->prepare(
            'INSERT INTO crm_quotation_items (crm_quotation_id, description, qty, unit_price, line_total, sort_order)
             VALUES (:qid, :description, :qty, :unit_price, :line_total, :sort_order)'
        );
        foreach ($cleanItems as $item) {
            $itemStmt->execute([
                'qid'         => $quotationId,
                'description' => $item['description'],
                'qty'         => $item['qty'],
                'unit_price'  => $item['unit_price'],
                'line_total'  => $item['line_total'],
                'sort_order'  => $item['sort_order'],
            ]);
        }

        // Advancing to the Quotation stage is a reasonable side effect of
        // creating the first quotation, but only nudges the stage forward
        // (enquiry/lead -> quotation) — never overrides negotiation/awarded/lost/on_hold.
        $leadStageStmt = $db->prepare('SELECT stage FROM crm_leads WHERE id = :id LIMIT 1');
        $leadStageStmt->execute(['id' => $leadId]);
        $currentStage = $leadStageStmt->fetchColumn();
        if (in_array($currentStage, ['enquiry', 'lead'], true)) {
            $db->prepare('UPDATE crm_leads SET stage = \'quotation\' WHERE id = :id')->execute(['id' => $leadId]);
            $histStmt = $db->prepare(
                'INSERT INTO crm_lead_stage_history (crm_lead_id, from_stage, to_stage, reason, changed_by, changed_by_name)
                 VALUES (:lead_id, :from_stage, \'quotation\', \'Auto-advanced: quotation created\', :changed_by, :changed_by_name)'
            );
            $histStmt->execute(['lead_id' => $leadId, 'from_stage' => $currentStage, 'changed_by' => $user['id'], 'changed_by_name' => $user['name']]);
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    crm_log_activity($user['id'], 'create_quotation', 'crm_lead', $leadId, "$quoteNumber ($total)");

    crm_json_response(['id' => $quotationId, 'quote_number' => $quoteNumber, 'version' => $version], 201);
}

if ($method === 'PUT') {
    ehs_verify_csrf();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) crm_json_error('A valid quotation id is required.', 422);

    $stmt = $db->prepare('SELECT * FROM crm_quotations WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $quotation = $stmt->fetch();
    if (!$quotation) crm_json_error('Quotation not found.', 404);

    $input = crm_json_input();
    $status = trim((string) ($input['status'] ?? ''));
    if (!in_array($status, CRM_QUOTE_STATUSES, true)) crm_json_error('Invalid status.', 422);

    $db->prepare('UPDATE crm_quotations SET status = :status WHERE id = :id')->execute(['status' => $status, 'id' => $id]);
    crm_log_activity($user['id'], 'update_quotation_status', 'crm_lead', (int) $quotation['crm_lead_id'], "{$quotation['quote_number']} -> $status");

    crm_json_response(['success' => true]);
}

crm_json_error('Method not allowed.', 405);
