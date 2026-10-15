<?php
/**
 * invoice-system/api/invoice_pdf.php?id=N[&inline=1]
 *
 * Issued (or later) invoice: streams the STORED PDF after verifying its
 *   SHA-256. Never re-rendered, so later settings/logo changes cannot alter it.
 * Draft: renders a preview on the fly with the "DRAFT, NOT FOR ISSUE"
 *   watermark on every page and the title "INVOICE: DRAFT". Previews are not
 *   stored. Unconfirmed («TBC») settings print as they are.
 * Every download/preview is activity-logged. Files are only ever served
 * through this authenticated endpoint (storage/ is deny-all).
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/inv_helpers.php';
require_once __DIR__ . '/../includes/inv_invoice.php';

$user = ehs_require_role(INV_ROLES, true);
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    inv_json_error('Method not allowed.', 405);
}
$id = inv_int_or_null($_GET['id'] ?? null, 1, 4294967295);
if (!$id) inv_json_error('A valid invoice id is required.', 422);
$inline = ($_GET['inline'] ?? '') === '1';

$inv = inv_fetch_invoice($db, $id);
if (!$inv) inv_json_error('Invoice not found.', 404);

function inv_send_pdf(string $bytes, string $filename, bool $inline): void
{
    $ascii = preg_replace('/[^A-Za-z0-9 &()._-]/', '_', $filename);
    header('Content-Type: application/pdf');
    header('Content-Length: ' . strlen($bytes));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    echo $bytes;
    exit;
}

if ($inv['status'] === 'draft') {
    try {
        $built = inv_build_draft($db, inv_draft_row_to_input($db, $inv), $inv);
        $entities = inv_get_entities($db);
        $settings = $entities[$inv['entity_code']] ?? null;
        if (!$settings) throw new RuntimeException('Unknown issuing entity.');
        $catName = null;
        if ($built['header']['category_id'] !== null) {
            $cat = inv_fetch_category($db, (int) $built['header']['category_id']);
            $catName = $cat['name'] ?? null;
        }
        $paper = inv_paper($db);
        $pdf = inv_pdf_render(inv_pdf_html(inv_doc_model($built, $settings, null, $catName, $paper)), $paper, INV_DRAFT_WATERMARK, 'Draft invoice');
    } catch (Throwable $ex) {
        error_log('inv preview ' . $id . ': ' . $ex->getMessage());
        inv_json_error('The preview could not be generated: ' . $ex->getMessage(), 500);
    }
    inv_log_activity((int) $user['id'], 'preview_invoice_pdf', 'inv_invoice', $id, 'Draft preview generated');
    inv_send_pdf($pdf, inv_pdf_filename(null, (string) $inv['bill_to_name'], $catName, true, $id), $inline);
}

// Issued / paid / credited / void: the stored, hash-verified file.
if ($inv['pdf_file'] === null || $inv['pdf_sha256'] === null) {
    inv_json_error('No stored PDF exists for this invoice.', 404);
}
$st = inv_storage_status($db);
if ($st['root'] === null) {
    inv_json_error('PDF storage is not available: ' . implode(' ', $st['problems']), 500);
}
try {
    $bytes = inv_storage_read_verified($st['root'], (string) $inv['pdf_file'], (string) $inv['pdf_sha256']);
} catch (Throwable $ex) {
    inv_log_activity((int) $user['id'], 'pdf_integrity_failure', 'inv_invoice', $id, $ex->getMessage());
    inv_json_error('The stored PDF could not be served: ' . $ex->getMessage(), 500);
}
inv_log_activity((int) $user['id'], 'download_invoice_pdf', 'inv_invoice', $id, (string) $inv['invoice_no']);
inv_send_pdf($bytes, inv_pdf_filename((string) $inv['invoice_no'], (string) $inv['bill_to_name'], $inv['category_name'], false), $inline);
