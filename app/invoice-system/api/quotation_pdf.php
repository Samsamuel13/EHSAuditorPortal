<?php
/**
 * invoice-system/api/quotation_pdf.php?id=N[&inline=1][&signed=1]
 *
 * Sent (or later) quotation: streams the STORED PDF (with the stamp, if one
 *   was configured when it was finalised) after verifying its SHA-256. Never
 *   re-rendered, so later settings/logo/stamp changes cannot alter it.
 * Draft: renders a preview on the fly with the "DRAFT, NOT SENT" watermark,
 *   "Our Ref: DRAFT" and NO stamp. Previews are not stored.
 * &signed=1: the client's signed copy recorded at acceptance (PDF/JPG/PNG),
 *   hash-verified, always as a download (attachment) with nosniff.
 * Files are addressed by numeric id only; the quote number (which contains
 * "/") is used only in the sanitised download name. Every download/preview
 * is activity-logged.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/inv_helpers.php';
require_once __DIR__ . '/../includes/inv_quote.php';

$user = ehs_require_role(INV_ROLES, true);
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') inv_json_error('Method not allowed.', 405);
if (!inv_table_exists($db, 'inv_quotations')) inv_json_error('Import db/inv_migration_004.sql first.', 409);
$id = inv_int_or_null($_GET['id'] ?? null, 1, 4294967295);
if (!$id) inv_json_error('A valid quotation id is required.', 422);
$inline = ($_GET['inline'] ?? '') === '1';
$signed = ($_GET['signed'] ?? '') === '1';

$q = inv_fetch_quote($db, $id);
if (!$q) inv_json_error('Quotation not found.', 404);

function inv_send_file(string $bytes, string $mime, string $filename, bool $inline): void
{
    $ascii = preg_replace('/[^A-Za-z0-9 &()._-]/', '_', $filename);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($bytes));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    if ($mime !== 'application/pdf') {
        // Uploaded images: never rendered as anything else (inline PDFs need the viewer, so no sandbox there).
        header("Content-Security-Policy: default-src 'none'; sandbox");
    }
    echo $bytes;
    exit;
}

$party = (string) ($q['bill_to_name'] ?? '');

if ($signed) {
    if ($q['signed_file'] === null || $q['signed_sha256'] === null) inv_json_error('No signed copy was uploaded for this quotation.', 404);
    $st = inv_storage_status($db);
    if ($st['root'] === null) inv_json_error('File storage is not available: ' . implode(' ', $st['problems']), 500);
    try {
        $bytes = inv_storage_read_verified($st['root'], (string) $q['signed_file'], (string) $q['signed_sha256']);
    } catch (Throwable $ex) {
        inv_log_activity((int) $user['id'], 'file_integrity_failure', 'inv_quotation', $id, 'signed copy: ' . $ex->getMessage());
        inv_json_error('The signed copy could not be served: ' . $ex->getMessage(), 500);
    }
    $ext = strtolower(pathinfo((string) $q['signed_file'], PATHINFO_EXTENSION));
    $mime = array_search($ext, INV_SIGNED_TYPES, true) ?: 'application/octet-stream';
    inv_log_activity((int) $user['id'], 'download_signed_quotation', 'inv_quotation', $id, (string) $q['quote_no']);
    inv_send_file($bytes, $mime, inv_quote_pdf_filename((string) $q['quote_no'], $party, false, $id, ' - signed.' . $ext), false);
}

if ($q['status'] === 'draft') {
    try {
        $built = inv_build_quote($db, inv_quote_row_to_input($db, $q), $q);
        $settings = inv_get_entities($db)[$q['entity_code']] ?? null;
        if (!$settings) throw new RuntimeException('Unknown issuing entity.');
        $paper = INV_QUOTE_PAPER; // quotations are always A4 (the owner's sample is A4)
        // Draft preview: no stamp, ever.
        $pdf = inv_pdf_render(inv_quote_pdf_html(inv_quote_doc_model($built, $settings, null, $paper, false)),
            $paper, INV_QUOTE_DRAFT_WATERMARK, 'Draft quotation');
    } catch (Throwable $ex) {
        error_log('inv quote preview ' . $id . ': ' . $ex->getMessage());
        inv_json_error('The preview could not be generated: ' . $ex->getMessage(), 500);
    }
    inv_log_activity((int) $user['id'], 'preview_quotation_pdf', 'inv_quotation', $id, 'Draft preview generated (no stamp)');
    inv_send_file($pdf, 'application/pdf', inv_quote_pdf_filename(null, $party, true, $id, '.pdf'), $inline);
}

if ($q['pdf_file'] === null || $q['pdf_sha256'] === null) inv_json_error('No stored PDF exists for this quotation.', 404);
$st = inv_storage_status($db);
if ($st['root'] === null) inv_json_error('PDF storage is not available: ' . implode(' ', $st['problems']), 500);
try {
    $bytes = inv_storage_read_verified($st['root'], (string) $q['pdf_file'], (string) $q['pdf_sha256']);
} catch (Throwable $ex) {
    inv_log_activity((int) $user['id'], 'pdf_integrity_failure', 'inv_quotation', $id, $ex->getMessage());
    inv_json_error('The stored PDF could not be served: ' . $ex->getMessage(), 500);
}
inv_log_activity((int) $user['id'], 'download_quotation_pdf', 'inv_quotation', $id, (string) $q['quote_no']);
inv_send_file($bytes, 'application/pdf', inv_quote_pdf_filename((string) $q['quote_no'], $party, false, $id, '.pdf'), $inline);
