<?php
/**
 * client-management/api/certification_document_download.php
 *
 * GET ?id=X -> streams the file back with its original filename. This is the
 * ONLY route that ever serves a certification document's bytes — the
 * storage folder itself is blocked by .htaccess (Require all denied), so a
 * direct URL guess at the file path cannot work even for a logged-in user.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/cm_helpers.php';

ehs_require_role(['super_admin', 'admin']); // HTML-style: redirects to login rather than JSON, since this is a direct file link

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(422);
    die('A valid document id is required.');
}

$stmt = get_db()->prepare('SELECT * FROM cm_certification_documents WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    die('Document not found.');
}

$absPath = realpath(__DIR__ . '/../' . $doc['file_path']);
$storageRoot = realpath(cm_storage_root());

// Defense in depth: confirm the resolved path is actually inside the
// storage root before reading it, even though file_path is DB-controlled
// (never user-supplied at read time) and randomly generated at upload time.
if ($absPath === false || $storageRoot === false || strpos($absPath, $storageRoot) !== 0 || !is_file($absPath)) {
    http_response_code(404);
    die('File not found on disk.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($absPath) ?: 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename(str_replace(['"', "\r", "\n"], '', $doc['original_filename'])) . '"');
header('Content-Length: ' . filesize($absPath));
header('X-Content-Type-Options: nosniff');
readfile($absPath);
exit;
