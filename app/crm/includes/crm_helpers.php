<?php
/**
 * crm/includes/crm_helpers.php
 *
 * This module's own JSON/logging/validation helpers — a deliberate copy of
 * the same pattern used by client-management/includes/cm_helpers.php, not a
 * require of it. Isolation rule for this module: no shared PHP files beyond
 * the common auth/session check (includes/auth.php -> includes/db.php).
 *
 * Every page/endpoint in this module should:
 *   require_once __DIR__ . '/../../includes/auth.php';   // shared login/session/users only
 *   require_once __DIR__ . '/crm_helpers.php';            // this module's own helpers
 */

const CRM_STAGES = ['enquiry', 'lead', 'quotation', 'negotiation', 'awarded', 'lost', 'on_hold'];
const CRM_SOURCES = ['whatsapp', 'referral', 'website', 'cold_call', 'exhibition', 'other'];
const CRM_FOLLOWUP_TYPES = ['call', 'email', 'meeting', 'whatsapp', 'other'];
const CRM_QUOTE_STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired'];

function crm_json_response($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function crm_json_error(string $message, int $statusCode = 400): void
{
    crm_json_response(['error' => $message], $statusCode);
}

/** Parse a JSON request body into an associative array. */
function crm_json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function crm_is_valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function crm_clean_str(?string $value, int $maxLen): ?string
{
    $value = trim((string) $value);
    if ($value === '') return null;
    return mb_substr($value, 0, $maxLen);
}

/** Module-local activity log — writes to crm_activity_log only. */
function crm_log_activity(int $userId, string $action, string $entityType, ?int $entityId, string $details = ''): void
{
    $stmt = get_db()->prepare(
        'INSERT INTO crm_activity_log (user_id, action, entity_type, entity_id, details)
         VALUES (:user_id, :action, :entity_type, :entity_id, :details)'
    );
    $stmt->execute([
        'user_id'     => $userId,
        'action'      => $action,
        'entity_type' => $entityType,
        'entity_id'   => $entityId,
        'details'     => $details,
    ]);
}

/**
 * Normalization helpers for the duplicate-lead check. Deliberately simple
 * (lowercase, strip non-digits/punctuation, drop common legal suffixes) —
 * this is a "warn, don't block" match, not a legal entity resolver, so a
 * cheap normalization that catches the common cases (different casing,
 * "Pte Ltd" vs "Pte. Ltd.", spaced-out phone numbers) is enough.
 */
function crm_normalize_phone(?string $phone): ?string
{
    if ($phone === null) return null;
    $digits = preg_replace('/\D+/', '', $phone);
    return $digits === '' ? null : ltrim($digits, '0') ?: $digits;
}

function crm_normalize_email(?string $email): ?string
{
    if ($email === null) return null;
    $email = strtolower(trim($email));
    return $email === '' ? null : $email;
}

function crm_normalize_company(?string $name): ?string
{
    if ($name === null) return null;
    $name = strtolower(trim($name));
    if ($name === '') return null;
    $name = preg_replace('/[^a-z0-9\s]/', '', $name);
    $name = preg_replace('/\b(pte|ltd|llp|inc|co|corp|company|limited|private)\b/', '', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name) ?: null;
}

/**
 * Check crm_leads AND cm_clients for a possible duplicate, by normalized
 * email / phone / company name. Returns a list of matches (each tagged with
 * its source table) — this is advisory only, the caller decides whether to
 * warn and still allows creation.
 *
 * Read-only query into cm_clients — the one explicitly permitted read into
 * the client-management schema (duplicate check), per the module brief.
 */
function crm_find_possible_duplicates(PDO $db, ?string $email, ?string $phone, ?string $company, ?int $excludeLeadId = null): array
{
    $normEmail   = crm_normalize_email($email);
    $normPhone   = crm_normalize_phone($phone);
    $normCompany = crm_normalize_company($company);

    if ($normEmail === null && $normPhone === null && $normCompany === null) {
        return [];
    }

    $matches = [];

    // --- crm_leads ---
    $leadConds = [];
    $leadParams = [];
    if ($normEmail !== null)   { $leadConds[] = 'normalized_email = :lead_email';     $leadParams['lead_email']   = $normEmail; }
    if ($normPhone !== null)   { $leadConds[] = 'normalized_phone = :lead_phone';     $leadParams['lead_phone']   = $normPhone; }
    if ($normCompany !== null) { $leadConds[] = 'normalized_company = :lead_company'; $leadParams['lead_company'] = $normCompany; }

    $leadSql = 'SELECT id, company_name, contact_person, phone, email, stage
                FROM crm_leads WHERE (' . implode(' OR ', $leadConds) . ')';
    if ($excludeLeadId) {
        $leadSql .= ' AND id != :exclude_id';
        $leadParams['exclude_id'] = $excludeLeadId;
    }
    $stmt = $db->prepare($leadSql);
    $stmt->execute($leadParams);
    foreach ($stmt->fetchAll() as $row) {
        $row['source_table'] = 'crm_leads';
        $matches[] = $row;
    }

    // --- cm_clients (read-only, matches on email/phone/company only —
    // cm_clients has no normalized_* columns, so this compares against a
    // simple LOWER()/REPLACE() on the fly rather than adding schema to a
    // module we don't own) ---
    $clientConds = [];
    $clientParams = [];
    if ($normEmail !== null)   { $clientConds[] = 'LOWER(TRIM(email)) = :client_email';   $clientParams['client_email']   = $normEmail; }
    if ($normPhone !== null)   { $clientConds[] = "REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE :client_phone"; $clientParams['client_phone'] = '%' . $normPhone; }
    if ($normCompany !== null) { $clientConds[] = 'LOWER(company_name) LIKE :client_company'; $clientParams['client_company'] = '%' . $normCompany . '%'; }

    if ($clientConds) {
        $clientStmt = $db->prepare(
            'SELECT id, company_name, contact_person, phone, email
             FROM cm_clients WHERE (' . implode(' OR ', $clientConds) . ') LIMIT 10'
        );
        $clientStmt->execute($clientParams);
        foreach ($clientStmt->fetchAll() as $row) {
            $row['source_table'] = 'cm_clients';
            $matches[] = $row;
        }
    }

    return $matches;
}

/**
 * Award -> Client conversion. One-way, application-level only (no FK).
 * Guards against double-conversion by checking converted_client_id first,
 * inside a transaction, so two near-simultaneous requests can't both
 * create a cm_clients row for the same lead.
 *
 * Returns the cm_clients.id that the lead is now linked to.
 */
function crm_convert_lead_to_client(PDO $db, array $lead, int $userId, string $userName): int
{
    if (!empty($lead['converted_client_id'])) {
        return (int) $lead['converted_client_id']; // already converted — no-op
    }

    $db->beginTransaction();
    try {
        // Re-check inside the transaction to close the race window.
        $lockStmt = $db->prepare('SELECT converted_client_id FROM crm_leads WHERE id = :id FOR UPDATE');
        $lockStmt->execute(['id' => $lead['id']]);
        $current = $lockStmt->fetchColumn();
        if ($current) {
            $db->commit();
            return (int) $current;
        }

        $insertStmt = $db->prepare(
            'INSERT INTO cm_clients (company_name, contact_person, industry_sector, phone, email, status, notes)
             VALUES (:company_name, :contact_person, :industry_sector, :phone, :email, \'active\', :notes)'
        );
        $insertStmt->execute([
            'company_name'    => $lead['company_name'],
            'contact_person'  => $lead['contact_person'],
            'industry_sector' => $lead['industry_sector'],
            'phone'           => $lead['phone'],
            'email'           => $lead['email'],
            'notes'           => 'Converted from CRM lead #' . $lead['id'] . ' on ' . date('Y-m-d'),
        ]);
        $newClientId = (int) $db->lastInsertId();

        $updateStmt = $db->prepare(
            'UPDATE crm_leads SET converted_client_id = :client_id, converted_at = NOW() WHERE id = :id'
        );
        $updateStmt->execute(['client_id' => $newClientId, 'id' => $lead['id']]);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    crm_log_activity($userId, 'convert_to_client', 'crm_lead', (int) $lead['id'],
        "Converted to cm_clients #$newClientId by $userName");

    return $newClientId;
}

/** Generate the next quote number for a lead's next quotation version, e.g. Q-2026-00042-v2. */
function crm_next_quote_number(PDO $db, int $leadId, int $version): string
{
    return sprintf('Q-%s-%05d-v%d', date('Y'), $leadId, $version);
}
