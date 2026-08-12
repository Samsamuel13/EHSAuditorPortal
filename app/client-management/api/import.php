<?php
/**
 * client-management/api/import.php
 *
 * POST ?action=preview  multipart file upload (field name "file", .csv or .xlsx)
 *      -> parses + validates every row WITHOUT writing to the database.
 *      Returns { token, rows: [{row_num, status, messages, data}], summary }.
 *      The parsed+validated rows are cached server-side (session) under
 *      `token` so commit doesn't have to trust a client-supplied payload
 *      as the thing it writes — it re-validates against current DB state
 *      before inserting anything.
 *
 * POST ?action=commit  { token }
 *      -> re-validates the cached rows (duplicates may have changed since
 *      preview) and inserts valid rows only. Existing clients are matched
 *      by UEN (or company name if no UEN) and reused, never overwritten —
 *      bulk import only adds new clients/certifications, it never edits
 *      an existing client's fields, to avoid a bad file clobbering data.
 *      Returns a per-row outcome summary.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/cm_helpers.php';

$user = ehs_require_role(['super_admin', 'admin'], true);
$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Safety net: a true PHP fatal (e.g. memory exhausted, execution timeout)
// cannot be caught by try/catch — it terminates the script immediately.
// This shutdown handler runs even then, and if nothing was sent to the
// browser yet, turns it into a readable JSON error instead of a blank 500.
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('cm import.php fatal: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Server error during import: ' . $error['message']
                    . ' (' . basename($error['file']) . ':' . $error['line'] . '). '
                    . 'If this mentions memory or execution time, increase memory_limit/max_execution_time in cPanel MultiPHP INI Editor.',
            ]);
        }
    }
});

const CM_IMPORT_MAX_ROWS = 2000;
const CM_IMPORT_MAX_BYTES = 8 * 1024 * 1024; // 8 MB
const CM_IMPORT_HEADERS = [
    'company_name', 'uen_registration_no', 'industry_sector', 'address', 'contact_person',
    'contact_designation', 'consultant', 'phone', 'email', 'website', 'client_status',
    'scheme_type_name', 'accreditation_body', 'certificate_number',
    'issue_date', 'surveillance_1_date', 'surveillance_2_date', 'expiry_date',
    'cycle_stage', 'cert_status', 'responsible_person_name', 'notes',
];

/** Normalize a header cell: strip a UTF-8 BOM if present, lowercase, trim, spaces/dashes -> underscores. */
function cm_normalize_header(string $h): string
{
    $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
    return strtolower(trim(preg_replace('/[\s\-]+/', '_', $h)));
}

/** Coerce a possibly-Excel-serial-date cell value into 'YYYY-MM-DD' or null. Returns ['ok'=>bool,'value'=>?string]. */
function cm_coerce_date_cell($value): array
{
    if ($value === null || $value === '') return ['ok' => true, 'value' => null];
    if ($value instanceof \DateTimeInterface) {
        return ['ok' => true, 'value' => $value->format('Y-m-d')];
    }
    $str = trim((string) $value);
    if (cm_is_valid_date($str)) return ['ok' => true, 'value' => $str];
    // Excel serial date number (e.g. from a CSV that lost formatting, or a
    // numeric-typed xlsx cell PhpSpreadsheet didn't auto-convert).
    if (is_numeric($str)) {
        try {
            $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $str);
            return ['ok' => true, 'value' => $dt->format('Y-m-d')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'value' => null];
        }
    }
    return ['ok' => false, 'value' => null];
}

/**
 * Parse an uploaded CSV or XLSX into an array of associative rows keyed by
 * the normalized CM_IMPORT_HEADERS names. Throws RuntimeException with a
 * user-facing message on any structural problem.
 */
function cm_parse_import_file(string $tmpPath, string $originalName): array
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($ext === 'csv') {
        $rows = [];
        $handle = fopen($tmpPath, 'r');
        if (!$handle) throw new \RuntimeException('Could not read the uploaded file.');
        $header = fgetcsv($handle);
        if ($header === false) { fclose($handle); throw new \RuntimeException('The file has no header row.'); }
        $normalizedHeader = array_map('cm_normalize_header', $header);
        while (($line = fgetcsv($handle)) !== false) {
            if (count(array_filter($line, fn($v) => trim((string) $v) !== '')) === 0) continue; // skip fully blank lines
            $row = [];
            foreach ($normalizedHeader as $i => $key) {
                $row[$key] = $line[$i] ?? null;
            }
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    if (in_array($ext, ['xlsx', 'xls'], true)) {
        $vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
        if (!file_exists($vendorAutoload)) {
            throw new \RuntimeException('Import dependencies are not installed on the server.');
        }
        require_once $vendorAutoload;

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmpPath);
        $reader->setReadDataOnly(false); // keep date typing so we can detect real Excel dates
        $spreadsheet = $reader->load($tmpPath);
        $sheet = $spreadsheet->getSheetByName('Clients_Certifications') ?? $spreadsheet->getActiveSheet();

        $data = $sheet->toArray(null, true, false, false); // formatData=false: real date cells come back as raw Excel serials, not locale-formatted display strings
        if (empty($data)) throw new \RuntimeException('The spreadsheet is empty.');

        $header = array_map('cm_normalize_header', array_map('strval', $data[0]));
        $rows = [];
        foreach (array_slice($data, 1) as $line) {
            if (count(array_filter($line, fn($v) => trim((string) $v) !== '')) === 0) continue;
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = $line[$i] ?? null;
            }
            $rows[] = $row;
        }
        return $rows;
    }

    throw new \RuntimeException('Unsupported file type. Upload a .csv or .xlsx file.');
}

/**
 * Validate one parsed row. $seen tracks UENs/cert numbers already seen
 * earlier in THIS file, to catch in-file duplicates; $db checks are for
 * duplicates against already-committed data.
 */
function cm_validate_import_row(array $row, int $rowNum, array &$seenUens, array &$seenCertNumbers, array $validSchemeNames, array $existingUens, array $existingCertNumbers): array
{
    $messages = [];
    $status = 'valid';

    $companyName = cm_clean_str($row['company_name'] ?? null, 200);
    if ($companyName === null) {
        $messages[] = 'company_name is required.';
        $status = 'error';
    }

    $uen = cm_clean_str($row['uen_registration_no'] ?? null, 50);
    if ($uen !== null) {
        $uenKey = mb_strtolower($uen);
        if (isset($seenUens[$uenKey]) && $seenUens[$uenKey] !== mb_strtolower((string) $companyName)) {
            $messages[] = "UEN \"$uen\" is already used by a different company name earlier in this file (row {$seenUens[$uenKey . '_row']}).";
            $status = 'error';
        }
        $existingClientName = $existingUens[$uenKey] ?? null;
        if ($existingClientName !== null && mb_strtolower($existingClientName) !== mb_strtolower((string) $companyName)) {
            $messages[] = "UEN \"$uen\" already belongs to an existing client (\"{$existingClientName}\") with a different name.";
            $status = 'error';
        } elseif ($existingClientName !== null) {
            $messages[] = 'Matches an existing client — certification will be added to it, client fields left unchanged.';
            if ($status === 'valid') $status = 'info';
        }
        $seenUens[$uenKey] = mb_strtolower((string) $companyName);
        $seenUens[$uenKey . '_row'] = $rowNum;
    }

    $clientStatus = cm_clean_str($row['client_status'] ?? null, 20) ?? 'active';
    if (!in_array($clientStatus, ['active', 'suspended', 'withdrawn', 'blacklisted'], true)) {
        $messages[] = "Invalid client_status \"$clientStatus\".";
        $status = 'error';
    }

    $email = cm_clean_str($row['email'] ?? null, 150);
    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $messages[] = "Invalid email \"$email\".";
        $status = 'error';
    }

    $schemeTypeName = cm_clean_str($row['scheme_type_name'] ?? null, 100);
    if ($schemeTypeName !== null) {
        $matchedScheme = null;
        foreach ($validSchemeNames as $name) {
            if (mb_strtolower($name) === mb_strtolower($schemeTypeName)) { $matchedScheme = $name; break; }
        }
        if ($matchedScheme === null) {
            $messages[] = "Unknown scheme_type_name \"$schemeTypeName\" — must exactly match a name on the template's \"Valid Scheme Types\" sheet.";
            $status = 'error';
        }
    }

    $issue = cm_coerce_date_cell($row['issue_date'] ?? null);
    $surv1 = cm_coerce_date_cell($row['surveillance_1_date'] ?? null);
    $surv2 = cm_coerce_date_cell($row['surveillance_2_date'] ?? null);
    $expiry = cm_coerce_date_cell($row['expiry_date'] ?? null);
    foreach (['issue_date' => $issue, 'surveillance_1_date' => $surv1, 'surveillance_2_date' => $surv2, 'expiry_date' => $expiry] as $label => $coerced) {
        if (!$coerced['ok']) { $messages[] = "$label is not a valid date (expected YYYY-MM-DD)."; $status = 'error'; }
    }
    $orderedDates = array_values(array_filter(
        [$issue['value'], $surv1['value'], $surv2['value'], $expiry['value']],
        fn($d) => $d !== null
    ));
    $sortedDates = $orderedDates;
    sort($sortedDates);
    if ($orderedDates !== $sortedDates) {
        $messages[] = 'Milestone dates are out of order — expected 1st Certification <= Surveillance 1 <= Surveillance 2 <= Recertification.';
        $status = 'error';
    }

    $cycleStage = cm_clean_str($row['cycle_stage'] ?? null, 30) ?? 'initial';
    if (!in_array($cycleStage, ['initial', 'surveillance_1', 'surveillance_2', 'recertification'], true)) {
        $messages[] = "Invalid cycle_stage \"$cycleStage\".";
        $status = 'error';
    }
    $certStatus = cm_clean_str($row['cert_status'] ?? null, 20) ?? 'pending';
    if (!in_array($certStatus, ['active', 'expired', 'suspended', 'withdrawn', 'pending'], true)) {
        $messages[] = "Invalid cert_status \"$certStatus\".";
        $status = 'error';
    }

    $certNumber = cm_clean_str($row['certificate_number'] ?? null, 100);
    if ($certNumber !== null) {
        $certKey = mb_strtolower($certNumber);
        if (isset($seenCertNumbers[$certKey])) {
            $messages[] = "certificate_number \"$certNumber\" is duplicated earlier in this file (row {$seenCertNumbers[$certKey]}).";
            $status = 'error';
        }
        $existingCert = isset($existingCertNumbers[$certKey]);
        if ($existingCert) {
            $messages[] = "certificate_number \"$certNumber\" already exists in the system.";
            $status = 'error';
        }
        $seenCertNumbers[$certKey] = $rowNum;
    }

    return [
        'row_num'  => $rowNum,
        'status'   => $status, // 'valid' | 'info' | 'error'
        'messages' => $messages,
        'data' => [
            'company_name'        => $companyName,
            'uen_registration_no'  => $uen,
            'industry_sector'      => cm_clean_str($row['industry_sector'] ?? null, 100),
            'address'              => cm_clean_str($row['address'] ?? null, 255),
            'contact_person'       => cm_clean_str($row['contact_person'] ?? null, 150),
            'contact_designation'  => cm_clean_str($row['contact_designation'] ?? null, 100),
            'consultant'            => cm_clean_str($row['consultant'] ?? null, 150),
            'phone'                => cm_clean_str($row['phone'] ?? null, 30),
            'email'                => $email,
            'website'              => cm_clean_str($row['website'] ?? null, 255),
            'client_status'        => $clientStatus,
            'scheme_type_name'     => $schemeTypeName,
            'accreditation_body'   => cm_clean_str($row['accreditation_body'] ?? null, 100),
            'certificate_number'   => $certNumber,
            'issue_date'           => $issue['value'],
            'surveillance_1_date'  => $surv1['value'],
            'surveillance_2_date'  => $surv2['value'],
            'expiry_date'          => $expiry['value'],
            'cycle_stage'          => $cycleStage,
            'cert_status'          => $certStatus,
            'responsible_person_name' => cm_clean_str($row['responsible_person_name'] ?? null, 150),
            'notes'                => cm_clean_str($row['notes'] ?? null, 65535),
        ],
    ];
}

/**
 * Preload every existing UEN -> company_name and every existing
 * certificate_number into PHP arrays in exactly 2 queries total, instead
 * of cm_validate_import_row querying the DB once per row (which meant
 * 800+ round-trips for a few hundred rows — slow enough on shared hosting
 * to hit max_execution_time/memory_limit and 500).
 */
function cm_preload_import_lookups(PDO $db): array
{
    $uenStmt = $db->query('SELECT uen_registration_no, company_name FROM cm_clients WHERE uen_registration_no IS NOT NULL');
    $existingUens = [];
    foreach ($uenStmt->fetchAll() as $row) {
        $existingUens[mb_strtolower($row['uen_registration_no'])] = $row['company_name'];
    }

    $certStmt = $db->query('SELECT certificate_number FROM cm_certifications WHERE certificate_number IS NOT NULL');
    $existingCertNumbers = [];
    foreach ($certStmt->fetchAll() as $row) {
        $existingCertNumbers[mb_strtolower($row['certificate_number'])] = true;
    }

    return [$existingUens, $existingCertNumbers];
}

if ($method === 'POST' && $action === 'preview') {
    ehs_verify_csrf();

    if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
        cm_json_error('No file was uploaded.', 422);
    }
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) cm_json_error('Upload failed (error code ' . $file['error'] . ').', 422);
    if ($file['size'] > CM_IMPORT_MAX_BYTES) cm_json_error('File is too large (8 MB max).', 422);
    if (!is_uploaded_file($file['tmp_name'])) cm_json_error('Invalid upload.', 422);

    try {
        $parsedRows = cm_parse_import_file($file['tmp_name'], $file['name']);

        if (empty($parsedRows)) cm_json_error('No data rows found in the file.', 422);
        if (count($parsedRows) > CM_IMPORT_MAX_ROWS) {
            cm_json_error('Too many rows (' . count($parsedRows) . '). Please split into files of ' . CM_IMPORT_MAX_ROWS . ' rows or fewer.', 422);
        }

        $schemeStmt = $db->query('SELECT name FROM cm_scheme_types');
        $validSchemeNames = array_column($schemeStmt->fetchAll(), 'name');
        [$existingUens, $existingCertNumbers] = cm_preload_import_lookups($db);

        $seenUens = [];
        $seenCertNumbers = [];
        $results = [];
        foreach ($parsedRows as $i => $row) {
            $results[] = cm_validate_import_row($row, $i + 2, $seenUens, $seenCertNumbers, $validSchemeNames, $existingUens, $existingCertNumbers); // +2: header is row 1
        }

        $token = bin2hex(random_bytes(16));
        $_SESSION['cm_import_batches'][$token] = ['rows' => $results, 'created_at' => time()];
        // Keep the session from growing unbounded across many uploads in one sitting.
        if (count($_SESSION['cm_import_batches']) > 5) {
            $_SESSION['cm_import_batches'] = array_slice($_SESSION['cm_import_batches'], -5, null, true);
        }

        $summary = ['total' => count($results), 'valid' => 0, 'info' => 0, 'error' => 0];
        foreach ($results as $r) { $summary[$r['status']]++; }

        cm_json_response(['token' => $token, 'rows' => $results, 'summary' => $summary]);
    } catch (\RuntimeException $e) {
        cm_json_error($e->getMessage(), 422);
    } catch (\Throwable $e) {
        error_log('cm import.php preview exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        cm_json_error('Server error during preview: ' . $e->getMessage(), 500);
    }
}

if ($method === 'POST' && $action === 'commit') {
    ehs_verify_csrf();
    $input = cm_json_input();
    $token = (string) ($input['token'] ?? '');

    $batch = $_SESSION['cm_import_batches'][$token] ?? null;
    if (!$batch) {
        cm_json_error('This import batch has expired or was not found. Please re-upload and preview the file again.', 410);
    }

    // Re-validate against CURRENT data — time may have passed since preview,
    // and another admin could have imported/added conflicting records.
    try {
        $schemeStmt = $db->query('SELECT name FROM cm_scheme_types');
        $validSchemeNames = array_column($schemeStmt->fetchAll(), 'name');
        [$existingUens, $existingCertNumbers] = cm_preload_import_lookups($db);
    } catch (\Throwable $e) {
        error_log('cm import.php commit setup exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        cm_json_error('Server error preparing the import: ' . $e->getMessage(), 500);
    }
    $seenUens = [];
    $seenCertNumbers = [];

    $createdClients = 0;
    $matchedClients = 0;
    $createdCerts = 0;
    $skipped = 0;
    $rowOutcomes = [];

    foreach ($batch['rows'] as $original) {
        $revalidated = cm_validate_import_row(
            array_merge($original['data'], []), // data already normalized; re-run through the same rules
            $original['row_num'], $seenUens, $seenCertNumbers, $validSchemeNames, $existingUens, $existingCertNumbers
        );

        if ($revalidated['status'] === 'error') {
            $skipped++;
            $rowOutcomes[] = ['row_num' => $original['row_num'], 'outcome' => 'skipped', 'messages' => $revalidated['messages']];
            continue;
        }

        $d = $revalidated['data'];

        try {
            $db->beginTransaction();

            // Match an existing client by UEN, else by exact company name; never overwrite an existing client's fields.
            $clientId = null;
            if ($d['uen_registration_no'] !== null) {
                $find = $db->prepare('SELECT id FROM cm_clients WHERE uen_registration_no = :uen LIMIT 1');
                $find->execute(['uen' => $d['uen_registration_no']]);
                $clientId = $find->fetchColumn() ?: null;
            }
            if ($clientId === null) {
                $find = $db->prepare('SELECT id FROM cm_clients WHERE LOWER(company_name) = LOWER(:name) LIMIT 1');
                $find->execute(['name' => $d['company_name']]);
                $clientId = $find->fetchColumn() ?: null;
            }

            if ($clientId === null) {
                $insertClient = $db->prepare(
                    'INSERT INTO cm_clients (company_name, uen_registration_no, industry_sector, address, contact_person,
                        contact_designation, consultant, phone, email, website, status, notes)
                     VALUES (:company_name, :uen_registration_no, :industry_sector, :address, :contact_person,
                        :contact_designation, :consultant, :phone, :email, :website, :status, :notes)'
                );
                $insertClient->execute([
                    'company_name' => $d['company_name'], 'uen_registration_no' => $d['uen_registration_no'],
                    'industry_sector' => $d['industry_sector'], 'address' => $d['address'],
                    'contact_person' => $d['contact_person'], 'contact_designation' => $d['contact_designation'],
                    'consultant' => $d['consultant'],
                    'phone' => $d['phone'], 'email' => $d['email'], 'website' => $d['website'],
                    'status' => $d['client_status'], 'notes' => $d['notes'],
                ]);
                $clientId = (int) $db->lastInsertId();
                $createdClients++;
                cm_log_activity($user['id'], 'bulk_import_client', 'cm_client', $clientId, $d['company_name']);
            } else {
                $matchedClients++;
            }

            if ($d['scheme_type_name'] !== null) {
                $schemeIdStmt = $db->prepare('SELECT id FROM cm_scheme_types WHERE LOWER(name) = LOWER(:name) LIMIT 1');
                $schemeIdStmt->execute(['name' => $d['scheme_type_name']]);
                $schemeId = $schemeIdStmt->fetchColumn();

                $insertCert = $db->prepare(
                    'INSERT INTO cm_certifications
                        (cm_client_id, cm_scheme_type_id, accreditation_body, certificate_number,
                         issue_date, surveillance_1_date, surveillance_2_date, expiry_date,
                         cycle_stage, status, responsible_person_name, notes)
                     VALUES
                        (:client_id, :scheme_id, :accreditation_body, :certificate_number,
                         :issue_date, :surveillance_1_date, :surveillance_2_date, :expiry_date,
                         :cycle_stage, :status, :responsible_person_name, :notes)'
                );
                $insertCert->execute([
                    'client_id' => $clientId, 'scheme_id' => $schemeId,
                    'accreditation_body' => $d['accreditation_body'], 'certificate_number' => $d['certificate_number'],
                    'issue_date' => $d['issue_date'], 'surveillance_1_date' => $d['surveillance_1_date'],
                    'surveillance_2_date' => $d['surveillance_2_date'], 'expiry_date' => $d['expiry_date'],
                    'cycle_stage' => $d['cycle_stage'], 'status' => $d['cert_status'],
                    'responsible_person_name' => $d['responsible_person_name'], 'notes' => $d['notes'],
                ]);
                $certId = (int) $db->lastInsertId();
                $createdCerts++;
                cm_log_activity($user['id'], 'bulk_import_certification', 'cm_certification', $certId, $d['company_name']);
            }

            $db->commit();
            $rowOutcomes[] = ['row_num' => $original['row_num'], 'outcome' => 'imported', 'messages' => []];
        } catch (\Throwable $e) {
            $db->rollBack();
            error_log('cm_import commit row ' . $original['row_num'] . ' failed: ' . $e->getMessage());
            $skipped++;
            $rowOutcomes[] = ['row_num' => $original['row_num'], 'outcome' => 'skipped', 'messages' => ['Could not import this row due to a server error — see server logs.']];
        }
    }

    unset($_SESSION['cm_import_batches'][$token]);

    cm_json_response([
        'created_clients'       => $createdClients,
        'matched_existing_clients' => $matchedClients,
        'created_certifications' => $createdCerts,
        'skipped'               => $skipped,
        'row_outcomes'          => $rowOutcomes,
    ]);
}

cm_json_error('Unknown action.', 400);