<?php
/**
 * invoice-system/api/quotation_accept.php
 *
 * POST multipart/form-data (CSRF token in the X-CSRF-Token header, as for the
 * JSON endpoints):
 *   id, row_hash, accepted_on (YYYY-MM-DD), accepted_by (client signatory),
 *   note (optional), signed_copy (optional file: PDF, JPG or PNG, max 10 MB).
 *
 * The upload is validated server-side: PHP upload error codes, size, extension
 * whitelist, MIME type sniffed from the CONTENT (finfo, never the browser's
 * claim), and a structural check (a PDF must start with "%PDF-"; an image must
 * be decodable by getimagesize with matching type). Accepted files are stored
 * under storage/acceptances/<ENTITY>/<YYYY>/q<ID>-signed-<random>.<ext>
 * (deny-all), never under the quote number, and only served through
 * api/quotation_pdf.php?id=N&signed=1 after a SHA-256 check.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/inv_helpers.php';
require_once __DIR__ . '/../includes/inv_quote.php';

$user = ehs_require_role(INV_ROLES, true);
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') inv_json_error('Method not allowed.', 405);
// An upload larger than post_max_size arrives with empty $_POST/$_FILES.
if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    inv_json_error('The upload is larger than the server allows. Maximum 10 MB.', 413);
}
ehs_verify_csrf();
if (!inv_table_exists($db, 'inv_quotations')) inv_json_error('Import db/inv_migration_004.sql first.', 409);

$id = inv_int_or_null($_POST['id'] ?? null, 1, 4294967295);
if (!$id) inv_json_error('A valid quotation id is required.', 422);
$fields = [];
$acceptedOn = (string) ($_POST['accepted_on'] ?? '');
if (!inv_is_valid_ymd($acceptedOn)) {
    $fields['accepted_on'] = 'Enter the acceptance date.';
} elseif ($acceptedOn > inv_today()) {
    $fields['accepted_on'] = 'The acceptance date cannot be in the future.';
}
$acceptedBy = inv_clean_str($_POST['accepted_by'] ?? null, 150);
if ($acceptedBy === null) $fields['accepted_by'] = 'Enter who accepted (client signatory name).';
$note = inv_clean_str($_POST['note'] ?? null, 1000, true);
if ($fields) inv_json_error('Please correct the highlighted fields.', 422, ['fields' => $fields]);

$file = null;
$up = $_FILES['signed_copy'] ?? null;
if (is_array($up) && ($up['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    if (is_array($up['error'])) inv_json_error('Upload one file only.', 422);
    $err = (int) $up['error'];
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        inv_json_error('The signed copy is larger than the server allows. Maximum 10 MB.', 413, ['fields' => ['signed_copy' => 'Too large.']]);
    }
    if ($err !== UPLOAD_ERR_OK) {
        inv_json_error('The signed copy could not be uploaded (error ' . $err . '). Try again.', 422, ['fields' => ['signed_copy' => 'Upload failed.']]);
    }
    $tmp = (string) $up['tmp_name'];
    if (!is_uploaded_file($tmp)) inv_json_error('Invalid upload.', 422);
    $size = (int) filesize($tmp);
    if ($size <= 0) inv_json_error('The signed copy is empty.', 422, ['fields' => ['signed_copy' => 'Empty file.']]);
    if ($size > INV_SIGNED_MAX_BYTES) inv_json_error('The signed copy is larger than 10 MB.', 413, ['fields' => ['signed_copy' => 'Too large.']]);

    $origName = (string) ($up['name'] ?? 'signed');
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if ($ext === 'jpeg') $ext = 'jpg';
    if (!in_array($ext, ['pdf', 'jpg', 'png'], true)) {
        inv_json_error('Only PDF, JPG or PNG files are accepted.', 422, ['fields' => ['signed_copy' => 'Wrong file type.']]);
    }
    if (!class_exists('finfo')) inv_json_error('The server cannot verify file types (PHP fileinfo extension missing).', 500);
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!isset(INV_SIGNED_TYPES[$mime]) || INV_SIGNED_TYPES[$mime] !== $ext) {
        inv_json_error('The file content does not match a ' . strtoupper($ext) . ' file.', 422, ['fields' => ['signed_copy' => 'Content does not match its type.']]);
    }
    $bytes = (string) file_get_contents($tmp);
    if ($ext === 'pdf') {
        if (strncmp($bytes, '%PDF-', 5) !== 0) inv_json_error('The file is not a valid PDF.', 422, ['fields' => ['signed_copy' => 'Not a PDF.']]);
    } else {
        $info = @getimagesize($tmp);
        $want = $ext === 'png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG;
        if ($info === false || (int) $info[2] !== $want || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 60000000) {
            inv_json_error('The image could not be read.', 422, ['fields' => ['signed_copy' => 'Unreadable image.']]);
        }
    }
    $file = ['bytes' => $bytes, 'ext' => $ext, 'mime' => $mime,
             'name' => preg_replace('/[^\pL\pN ._()-]+/u', '_', basename($origName))];
}

$res = inv_accept_quote($db, $id, (string) ($_POST['row_hash'] ?? ''), $user, $acceptedOn, $acceptedBy, $note, $file);
if (!$res['ok']) inv_json_error($res['error'], $res['http'], isset($res['fields']) ? ['fields' => $res['fields']] : []);
inv_json_response(['ok' => true]);
