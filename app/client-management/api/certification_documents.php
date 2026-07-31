<?php
/**
 * client-management/api/certification_documents.php
 *
 * GET    ?certification_id=X          -> list documents for a certification
 * POST   multipart/form-data          -> upload a document (fields: certification_id, doc_type, file)
 * DELETE ?id=X                        -> remove a document (file + DB row)
 *
 * Uploaded files are stored under client-management/storage/certification_docs/,
 * which is blocked from direct web access by .htaccess — the only way to read
 * a file back out is certification_document_download.php, which re-checks
 * auth/role before streaming it. Filenames on disk are random, never the
 * user-supplied name, so there's no path-traversal or overwrite risk.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/cm_helpers.php';

$user = ehs_require_role(['super_admin', 'admin'], true);
$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $certId = (int) ($_GET['certification_id'] ?? 0);
    if ($certId <= 0) cm_json_error('A valid certification_id is required.', 422);

    $stmt = $db->prepare(
        'SELECT d.id, d.doc_type, d.original_filename, d.uploaded_at, u.name AS uploaded_by_name
         FROM cm_certification_documents d
         JOIN users u ON u.id = d.uploaded_by
         WHERE d.cm_certification_id = :cert_id
         ORDER BY d.uploaded_at DESC'
    );
    $stmt->execute(['cert_id' => $certId]);
    cm_json_response(['documents' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    ehs_verify_csrf();

    $certId = (int) ($_POST['certification_id'] ?? 0);
    if ($certId <= 0) cm_json_error('A valid certification_id is required.', 422);

    $certStmt = $db->prepare(
        'SELECT cert.id, cert.cm_client_id, c.company_name
         FROM cm_certifications cert JOIN cm_clients c ON c.id = cert.cm_client_id
         WHERE cert.id = :id LIMIT 1'
    );
    $certStmt->execute(['id' => $certId]);
    $cert = $certStmt->fetch();
    if (!$cert) cm_json_error('Certification not found.', 404);

    $docType = trim((string) ($_POST['doc_type'] ?? 'other'));
    if (!in_array($docType, ['certificate', 'audit_report', 'application_form', 'other'], true)) {
        cm_json_error('Invalid document type.', 422);
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
        cm_json_error('No file was uploaded.', 422);
    }
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        cm_json_error('Upload failed (error code ' . $file['error'] . ').', 422);
    }
    if ($file['size'] > CM_MAX_UPLOAD_BYTES) {
        cm_json_error('File is too large (10 MB max).', 422);
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        cm_json_error('Invalid upload.', 422);
    }

    // Validate the actual file content's MIME type (finfo), not just the
    // client-supplied extension/Content-Type, which is trivially spoofable.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->file($file['tmp_name']);
    $allowed = cm_allowed_upload_mimes();
    $ext = null;
    foreach ($allowed as $extCandidate => $mime) {
        if ($mime === $detectedMime) { $ext = $extCandidate; break; }
    }
    if ($ext === null) {
        cm_json_error('Unsupported file type. Allowed: PDF, JPG, PNG, DOC, DOCX.', 422);
    }

    $originalName = cm_clean_str($file['name'] ?? 'upload', 255) ?? 'upload';

    $clientDir = cm_storage_root() . '/' . $cert['cm_client_id'];
    if (!is_dir($clientDir)) {
        mkdir($clientDir, 0750, true);
    }
    $randomName = bin2hex(random_bytes(16)) . '.' . $ext;
    $destPath = $clientDir . '/' . $randomName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        cm_json_error('Could not save the uploaded file.', 500);
    }

    // Stored relative to client-management/, so the download script (which
    // lives in client-management/api/) can resolve it as __DIR__.'/../'.$file_path.
    $relativePath = 'storage/certification_docs/' . $cert['cm_client_id'] . '/' . $randomName;

    $stmt = $db->prepare(
        'INSERT INTO cm_certification_documents (cm_certification_id, doc_type, file_path, original_filename, uploaded_by)
         VALUES (:cert_id, :doc_type, :file_path, :original_filename, :uploaded_by)'
    );
    $stmt->execute([
        'cert_id'          => $certId,
        'doc_type'         => $docType,
        'file_path'        => $relativePath,
        'original_filename'=> $originalName,
        'uploaded_by'      => $user['id'],
    ]);
    $newId = (int) $db->lastInsertId();

    cm_log_activity($user['id'], 'upload_certification_document', 'cm_certification', $certId,
        $cert['company_name'] . ' — uploaded ' . $originalName);

    cm_json_response(['document' => [
        'id' => $newId, 'doc_type' => $docType, 'original_filename' => $originalName,
    ], 'created' => true], 201);
}

if ($method === 'DELETE') {
    ehs_verify_csrf();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) cm_json_error('A valid document id is required.', 422);

    $stmt = $db->prepare(
        'SELECT d.*, c.company_name FROM cm_certification_documents d
         JOIN cm_certifications cert ON cert.id = d.cm_certification_id
         JOIN cm_clients c ON c.id = cert.cm_client_id
         WHERE d.id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $doc = $stmt->fetch();
    if (!$doc) cm_json_error('Document not found.', 404);

    $delStmt = $db->prepare('DELETE FROM cm_certification_documents WHERE id = :id');
    $delStmt->execute(['id' => $id]);

    $absPath = __DIR__ . '/../' . $doc['file_path'];
    if (is_file($absPath)) {
        unlink($absPath);
    }

    cm_log_activity($user['id'], 'delete_certification_document', 'cm_certification', (int) $doc['cm_certification_id'],
        $doc['company_name'] . ' — removed ' . $doc['original_filename']);

    cm_json_response(['success' => true]);
}

cm_json_error('Method not allowed.', 405);
