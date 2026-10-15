<?php
/**
 * invoice-system/includes/inv_helpers.php
 *
 * This module's own JSON / validation / logging / readiness helpers.
 * A deliberate copy of the pattern in crm/includes/crm_helpers.php, not a
 * require of it. Isolation rule: the only shared PHP is includes/auth.php
 * (-> includes/db.php) and the presentational includes/sidebar_nav.php.
 *
 * Every page / endpoint in this module starts with:
 *   require_once __DIR__ . '/../../includes/auth.php';   // shared login/session/CSRF
 *   require_once __DIR__ . '/../includes/inv_helpers.php';
 *   $user = ehs_require_role(INV_ROLES, true);           // true for API endpoints
 *
 * SQL rule (hard constraint): never reuse a named placeholder in one query.
 * Every query in this module uses distinct names or positional ?.
 */

/** Roles allowed into the Invoice System (kickoff decision: super_admin only). */
const INV_ROLES = ['super_admin'];

const INV_DOC_TYPES = ['invoice', 'credit_note'];
const INV_TBC_MARKER = '«TBC»';

/**
 * inv_settings fields that a super_admin may edit, with max lengths.
 * Anything not listed here is ignored by the settings API (whitelist).
 */
const INV_SETTINGS_TEXT_FIELDS = [
    'display_name'        => 100,
    'legal_name'          => 200,
    'co_reg_no'           => 50,
    'address'             => 255,
    'email'               => 150,
    'phone'               => 50,
    'accreditation_line'  => 255,
    'cheque_payee'        => 200,
    'bank_name'           => 100,
    'bank_code'           => 20,
    'bank_branch'         => 100,
    'bank_account_label'  => 100,
    'bank_account_no'     => 50,
    'bank_swift'          => 20,
    'paynow_uen'          => 50,
    'payment_note'        => 255,
    'gst_reg_no'          => 50,
    'no_gst_label'        => 30,
    'tnc_text'            => 4000,
    'footer_text'         => 255,
    'invoice_prefix'      => 10,
    'cn_prefix'           => 10,
    'reminder_from_email' => 150,
    'reminder_from_name'  => 150,
];

/** Fields that may legitimately be blank (all others are required). */
const INV_SETTINGS_OPTIONAL_FIELDS = ['accreditation_line', 'gst_reg_no', 'invoice_prefix', 'cn_prefix'];

/** Fields whose before/after values are always written to the activity log (fraud-sensitive). */
const INV_SETTINGS_SENSITIVE_FIELDS = [
    'cheque_payee', 'bank_name', 'bank_code', 'bank_branch', 'bank_account_label',
    'bank_account_no', 'bank_swift', 'paynow_uen', 'co_reg_no', 'legal_name',
];

/**
 * Required for issuing an INVOICE: none of these may contain "TBC".
 * (README §3.) Credit notes additionally need cn_prefix.
 */
const INV_READINESS_FIELDS = [
    'legal_name', 'co_reg_no', 'address', 'email', 'phone', 'accreditation_line',
    'cheque_payee', 'bank_name', 'bank_code', 'bank_branch', 'bank_account_label',
    'bank_account_no', 'bank_swift', 'paynow_uen', 'payment_note',
    'tnc_text', 'footer_text', 'invoice_prefix',
];

const INV_FIELD_LABELS = [
    'display_name' => 'Display name', 'legal_name' => 'Legal name', 'co_reg_no' => 'Co Reg No',
    'address' => 'Address', 'email' => 'Email', 'phone' => 'Contact number',
    'accreditation_line' => 'Accreditation line', 'cheque_payee' => 'Cheque payee',
    'bank_name' => 'Bank name', 'bank_code' => 'Bank code', 'bank_branch' => 'Bank branch',
    'bank_account_label' => 'Account label', 'bank_account_no' => 'Account number',
    'bank_swift' => 'SWIFT code', 'paynow_uen' => 'PayNow UEN', 'payment_note' => 'Payment note',
    'gst_reg_no' => 'GST Reg No', 'no_gst_label' => 'No-GST label', 'tnc_text' => 'Terms & Conditions',
    'footer_text' => 'Footer text', 'invoice_prefix' => 'Invoice number prefix',
    'cn_prefix' => 'Credit-note number prefix', 'reminder_from_email' => 'Reminder sender email',
    'reminder_from_name' => 'Reminder sender name', 'gst_rate' => 'GST rate',
    'default_terms_days' => 'Default payment terms', 'seq_digits' => 'Sequence digits',
    'logo_filename' => 'Logo file', 'gst_status' => 'GST status',
];

/** Allowed logo filenames: bare names only, inside invoice-system/assets/img/. */
const INV_LOGO_PATTERN = '/^logo_[a-z0-9_]{1,40}\.(png|jpe?g)$/';
const INV_LOGO_MAX_BYTES = 2097152; // 2 MB

// ---------------------------------------------------------------------------
// JSON helpers
// ---------------------------------------------------------------------------
function inv_json_response($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function inv_json_error(string $message, int $statusCode = 400, array $extra = []): void
{
    // Never leave a transaction (and its row locks) open on an error exit.
    $db = get_db();
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    inv_json_response(array_merge(['error' => $message], $extra), $statusCode);
}

/**
 * Optimistic-lock token: SHA-256 of the row exactly as it was read.
 * Used instead of comparing updated_at, whose one-second precision cannot
 * tell apart two saves made within the same second.
 */
function inv_row_hash(array $row): string
{
    ksort($row);
    return hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE));
}

/** Parse a JSON request body (capped at 256 KB) into an associative array. */
function inv_json_input(): array
{
    $raw = file_get_contents('php://input', false, null, 0, 262144);
    $data = json_decode((string) $raw, true);
    return is_array($data) ? $data : [];
}

// ---------------------------------------------------------------------------
// Validation helpers
// ---------------------------------------------------------------------------

/** Trim, cap length, return null for empty. Strips control chars except \n and \t. */
function inv_clean_str($value, int $maxLen, bool $multiline = false): ?string
{
    if ($value === null || is_array($value) || is_object($value)) return null;
    $value = str_replace(["\r\n", "\r"], "\n", (string) $value);
    if (!$multiline) {
        $value = str_replace(["\n", "\t"], ' ', $value);
    }
    $value = preg_replace($multiline ? '/[^\P{C}\n\t]/u' : '/[^\P{C}]/u', '', $value) ?? '';
    $value = trim($value);
    if ($value === '') return null;
    return mb_substr($value, 0, $maxLen);
}

function inv_is_valid_email(?string $email): bool
{
    return $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** Validate a comma/semicolon separated email list; returns normalized "a@x, b@y" or false. */
function inv_clean_email_list(?string $list, int $maxLen)
{
    if ($list === null) return null;
    $parts = preg_split('/[,;\s]+/', $list, -1, PREG_SPLIT_NO_EMPTY);
    $clean = [];
    foreach ($parts as $p) {
        if (!inv_is_valid_email($p)) return false;
        $clean[] = strtolower($p);
    }
    $out = implode(', ', array_unique($clean));
    if (mb_strlen($out) > $maxLen) return false;
    return $out === '' ? null : $out;
}

/** Integer within [min, max], or null when the input is empty. Returns false on invalid. */
function inv_int_or_null($value, int $min, int $max)
{
    if ($value === null || $value === '') return null;
    if (is_bool($value) || is_array($value)) return false;
    if (!preg_match('/^\d+$/', (string) $value)) return false;
    $n = (int) $value;
    return ($n < $min || $n > $max) ? false : $n;
}

/** Escape LIKE wildcards so user input is matched literally. */
function inv_like_escape(string $s): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
}

/** True when a value still carries a placeholder (ASCII match, case-insensitive). */
function inv_is_tbc(?string $value): bool
{
    return $value !== null && stripos($value, 'TBC') !== false;
}

function inv_h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------------------------
// Activity log (module-local: inv_activity_log only)
// ---------------------------------------------------------------------------
function inv_log_activity(int $userId, string $action, string $entityType, ?int $entityId, string $details = ''): void
{
    $stmt = get_db()->prepare(
        'INSERT INTO inv_activity_log (user_id, action, entity_type, entity_id, details)
         VALUES (:user_id, :action, :entity_type, :entity_id, :details)'
    );
    $stmt->execute([
        'user_id'     => $userId,
        'action'      => $action,
        'entity_type' => $entityType,
        'entity_id'   => $entityId,
        'details'     => mb_substr($details, 0, 60000),
    ]);
}

// ---------------------------------------------------------------------------
// Issuing entities, numbering, readiness
// ---------------------------------------------------------------------------

/** All inv_settings rows keyed by entity_code (per-request cache). */
function inv_get_entities(PDO $db, bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        $rows = $db->query('SELECT * FROM inv_settings ORDER BY entity_code = \'EHS\' DESC, entity_code ASC')->fetchAll();
        foreach ($rows as $r) {
            $cache[$r['entity_code']] = $r;
        }
    }
    return $cache;
}

function inv_entity_exists(PDO $db, ?string $code): bool
{
    return $code !== null && array_key_exists($code, inv_get_entities($db));
}

/**
 * Format a document number: prefix + YY + zero-padded sequence.
 * ASSUMPTION (unconfirmed, README §2.3): yearly reset, YY from invoice date.
 */
function inv_format_doc_number(string $prefix, int $year, int $seq, int $digits): string
{
    return $prefix . sprintf('%02d', $year % 100) . str_pad((string) $seq, $digits, '0', STR_PAD_LEFT);
}

/** Absolute path of a validated logo file, or null when the name is not allowed. */
function inv_logo_path(?string $filename): ?string
{
    if ($filename === null || !preg_match(INV_LOGO_PATTERN, $filename)) return null;
    return __DIR__ . '/../assets/img/' . basename($filename);
}

/** Problems with the logo file (empty array = OK). Checks name, existence, size and real image type. */
function inv_logo_problems(?string $filename): array
{
    $path = inv_logo_path($filename);
    if ($path === null) return ['Logo filename is not an allowed name (logo_*.png / .jpg).'];
    if (!is_file($path)) return ['Logo file ' . $filename . ' is missing from invoice-system/assets/img/.'];
    if (filesize($path) > INV_LOGO_MAX_BYTES) return ['Logo file is larger than 2 MB.'];
    $info = @getimagesize($path);
    if ($info === false || !in_array($info['mime'] ?? '', ['image/png', 'image/jpeg'], true)) {
        return ['Logo file is not a valid PNG or JPEG image.'];
    }
    return [];
}

/** Logo files present in assets/img that match the allowed pattern. */
function inv_available_logos(): array
{
    $dir = __DIR__ . '/../assets/img/';
    $out = [];
    foreach ((array) @scandir($dir) as $f) {
        if (is_string($f) && preg_match(INV_LOGO_PATTERN, $f) && is_file($dir . $f)) {
            $out[] = $f;
        }
    }
    sort($out);
    return $out;
}

/** Number-series row, or null. */
function inv_get_series(PDO $db, string $entityCode, string $docType, int $year): ?array
{
    $stmt = $db->prepare(
        'SELECT * FROM inv_number_series
         WHERE entity_code = :s_entity AND doc_type = :s_type AND period_year = :s_year LIMIT 1'
    );
    $stmt->execute(['s_entity' => $entityCode, 's_type' => $docType, 's_year' => $year]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Has this entity issued any document (invoice or credit note)? Used to freeze the number format. */
function inv_entity_has_issued_docs(PDO $db, string $entityCode): bool
{
    $stmt = $db->prepare(
        'SELECT (SELECT COUNT(*) FROM inv_invoices WHERE entity_code = :e_inv AND status <> \'draft\')
              + (SELECT COUNT(*) FROM inv_credit_notes WHERE entity_code = :e_cn AND status <> \'draft\') AS n'
    );
    $stmt->execute(['e_inv' => $entityCode, 'e_cn' => $entityCode]);
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Highest sequence already issued in a series, read back from the stored
 * document numbers (format: prefix + YY + digits). 0 when none.
 * Used to stop a super_admin moving a counter backwards onto used numbers.
 */
function inv_max_issued_seq(PDO $db, array $entity, string $docType, int $year): int
{
    $prefix = $docType === 'invoice' ? (string) $entity['invoice_prefix'] : (string) $entity['cn_prefix'];
    $head = $prefix . sprintf('%02d', $year % 100);
    $sql = $docType === 'invoice'
        ? 'SELECT invoice_no AS no FROM inv_invoices WHERE entity_code = :m_entity AND invoice_no IS NOT NULL AND invoice_no LIKE :m_like'
        : 'SELECT cn_no AS no FROM inv_credit_notes WHERE entity_code = :m_entity AND cn_no IS NOT NULL AND cn_no LIKE :m_like';
    $stmt = $db->prepare($sql);
    $stmt->execute(['m_entity' => $entity['entity_code'], 'm_like' => inv_like_escape($head) . '%']);
    $max = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $no) {
        $tail = substr((string) $no, strlen($head));
        if ($tail !== '' && ctype_digit($tail)) {
            $max = max($max, (int) $tail);
        }
    }
    return $max;
}

/**
 * "Entity ready to issue" check (README §3). Returns a list of problems;
 * an empty list means documents of $docType dated in $year may be issued.
 * Drafts and previews never call this as a gate; issue endpoints (Stage 3/4)
 * MUST call it inside the issue transaction and refuse on any problem.
 *
 * Each problem: ['field' => string, 'message' => string]
 */
function inv_entity_readiness(PDO $db, string $entityCode, string $docType, int $year): array
{
    $entities = inv_get_entities($db);
    if (!isset($entities[$entityCode])) {
        return [['field' => 'entity', 'message' => 'Unknown issuing entity.']];
    }
    $e = $entities[$entityCode];
    $problems = [];

    if ((int) $e['is_active'] !== 1) {
        $problems[] = ['field' => 'is_active', 'message' => 'Entity is deactivated.'];
    }

    $fields = INV_READINESS_FIELDS;
    if ($docType === 'credit_note') $fields[] = 'cn_prefix';
    foreach ($fields as $f) {
        $v = $e[$f] ?? null;
        if (inv_is_tbc($v)) {
            $problems[] = ['field' => $f, 'message' => (INV_FIELD_LABELS[$f] ?? $f) . ' is still TBC.'];
        } elseif (($v === null || trim((string) $v) === '') && !in_array($f, INV_SETTINGS_OPTIONAL_FIELDS, true)) {
            $problems[] = ['field' => $f, 'message' => (INV_FIELD_LABELS[$f] ?? $f) . ' is empty.'];
        }
    }

    if ((int) $e['gst_status_confirmed'] !== 1) {
        $problems[] = ['field' => 'gst_status', 'message' => 'GST status has not been confirmed.'];
    } elseif ((int) $e['gst_registered'] === 1 && (inv_is_tbc($e['gst_reg_no']) || trim((string) $e['gst_reg_no']) === '')) {
        $problems[] = ['field' => 'gst_reg_no', 'message' => 'GST-registered but GST Reg No is missing.'];
    }

    $series = inv_get_series($db, $entityCode, $docType, $year);
    $label = $docType === 'invoice' ? 'Invoice' : 'Credit-note';
    if ($series === null) {
        $problems[] = ['field' => 'series', 'message' => "$label number series for $year does not exist yet."];
    } elseif ((int) $series['is_confirmed'] !== 1 || $series['next_value'] === null) {
        $problems[] = ['field' => 'series', 'message' => "$label number series for $year is locked: next number not confirmed."];
    } elseif ((int) $series['next_value'] >= 10 ** (int) $e['seq_digits']) {
        $problems[] = ['field' => 'series', 'message' => "$label number series for $year is exhausted (more than {$e['seq_digits']} digits)."];
    }

    foreach (inv_logo_problems($e['logo_filename']) as $msg) {
        $problems[] = ['field' => 'logo_filename', 'message' => $msg];
    }

    return $problems;
}

/** Non-blocking warnings shown on the Settings page. */
function inv_entity_warnings(array $e): array
{
    $w = [];
    if ((int) $e['gst_registered'] === 1 && stripos((string) $e['tnc_text'], 'NOT subject') !== false) {
        $w[] = 'Entity is GST-registered but the Terms & Conditions still say invoices are NOT subject to GST.';
    }
    if ((int) $e['gst_registered'] === 0 && (int) $e['gst_status_confirmed'] === 1 && stripos((string) $e['tnc_text'], 'GST') === false) {
        $w[] = 'Terms & Conditions do not mention GST.';
    }
    return $w;
}

// ---------------------------------------------------------------------------
// Approved cross-module exception (Option A): read-only cm_clients lookup.
// ONE fixed-column SELECT shape; never writes; no JOINs; callers must have
// passed ehs_require_role(INV_ROLES). The `entity` column is only selected
// when information_schema confirms it exists, so a missing column can never
// cause a 500.
// ---------------------------------------------------------------------------

/** ['available' => bool, 'has_entity' => bool] (per-request cache). */
function inv_cm_lookup_status(PDO $db): array
{
    static $status = null;
    if ($status !== null) return $status;

    $stmt = $db->prepare(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t_name'
    );
    $stmt->execute(['t_name' => 'cm_clients']);
    $cols = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $required = ['id', 'company_name', 'uen_registration_no', 'address', 'contact_person', 'phone', 'email'];
    $status = [
        'available'  => count(array_diff($required, $cols)) === 0,
        'has_entity' => in_array('entity', $cols, true),
    ];
    return $status;
}

/** Normalize a cm_clients row into the copy-suggestion shape used by the party form. */
function inv_cm_row_to_suggestion(array $r): array
{
    $phone = $r['phone'] !== null ? preg_replace('/\s*\n\s*/', ' / ', trim((string) $r['phone'])) : null;
    $entity = $r['entity'] ?? null;
    return [
        'source_client_id' => (int) $r['id'],
        'company_name'     => $r['company_name'],
        'uen'              => ($r['uen_registration_no'] !== null && trim($r['uen_registration_no']) !== '') ? trim($r['uen_registration_no']) : null,
        'address'          => ($r['address'] !== null && trim($r['address']) !== '') ? trim($r['address']) : null,
        'contact_person'   => $r['contact_person'] !== null && trim($r['contact_person']) !== '' ? trim($r['contact_person']) : null,
        'phone'            => $phone !== '' ? $phone : null,
        'email'            => $r['email'] !== null && trim($r['email']) !== '' ? strtolower(trim($r['email'])) : null,
        'entity'           => in_array($entity, ['EHS', 'Axiscert'], true) ? $entity : null,
    ];
}

/**
 * Search cm_clients by company name or email (max 20 rows), or fetch one by id.
 * Returns ['available'=>bool,'has_entity'=>bool,'results'=>[...]].
 */
function inv_cm_lookup(PDO $db, ?string $q, ?int $id): array
{
    $status = inv_cm_lookup_status($db);
    if (!$status['available']) {
        return $status + ['results' => []];
    }
    $cols = 'id, company_name, uen_registration_no, address, contact_person, phone, email'
          . ($status['has_entity'] ? ', entity' : '');

    if ($id !== null) {
        $stmt = $db->prepare("SELECT $cols FROM cm_clients WHERE id = :c_id LIMIT 1");
        $stmt->execute(['c_id' => $id]);
    } else {
        $like = '%' . inv_like_escape((string) $q) . '%';
        $stmt = $db->prepare(
            "SELECT $cols FROM cm_clients
             WHERE company_name LIKE :c_name OR email LIKE :c_email
             ORDER BY company_name ASC LIMIT 20"
        );
        $stmt->execute(['c_name' => $like, 'c_email' => $like]);
    }
    $results = array_map('inv_cm_row_to_suggestion', $stmt->fetchAll());
    return $status + ['results' => $results];
}
