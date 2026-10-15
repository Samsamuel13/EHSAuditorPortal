<?php
/**
 * invoice-system/includes/inv_storage.php — stored PDF files.
 *
 * Root folder:
 *   inv_config.storage_path (absolute path, set by SQL only), or, when blank,
 *   invoice-system/storage/ (which ships with a deny-all .htaccess).
 *
 * Safeguards:
 *   - The root must exist, be a real directory (not a symlink), be writable
 *     and, when it is inside the web document root, contain a .htaccess
 *     with "Require all denied". Otherwise issuing is blocked.
 *   - Stored paths are RELATIVE and must match a strict pattern
 *     (invoices/<ENTITY>/<YYYY>/<NUMBER>.pdf); every resolved path is checked
 *     with realpath() to be inside the root (no traversal, no symlink escape).
 *   - Files are created with fopen('xb') (never overwrite), flushed, fsync'd
 *     where available, re-read and hashed (SHA-256) before the issue commits.
 *   - A file left behind by a crashed issue (number not committed) is moved
 *     aside to *.orphan-<timestamp>.pdf, never deleted, never served.
 *   - Downloads re-check the SHA-256 before serving.
 */

const INV_STORAGE_REL_PATTERN = '#^invoices/[A-Za-z0-9_-]{1,20}/\d{4}/[A-Za-z0-9_-]{1,40}\.pdf$#';

function inv_config_get(PDO $db, string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach ($db->query('SELECT cfg_key, cfg_value FROM inv_config')->fetchAll() as $r) {
            $cache[$r['cfg_key']] = $r['cfg_value'];
        }
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

function inv_module_storage_dir(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage';
}

/** True when $path is inside the web server's document root. */
function inv_path_in_docroot(string $path): bool
{
    $docroot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
    if ($docroot === false || $docroot === '') {
        // CLI / unknown: treat the app folder as the web root (conservative).
        $docroot = realpath(dirname(__DIR__, 2));
    }
    return $docroot !== false && ($path === $docroot || strpos($path, rtrim($docroot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0);
}

/**
 * Inspect the storage root without changing anything.
 * Returns ['root' => ?string, 'configured' => string, 'problems' => [..], 'in_docroot' => bool, 'source' => 'module'|'config'].
 */
function inv_storage_status(PDO $db): array
{
    $configured = trim((string) inv_config_get($db, 'storage_path', ''));
    $source = $configured === '' ? 'module' : 'config';
    $candidate = $configured === '' ? inv_module_storage_dir() : $configured;
    $problems = [];
    $root = null;
    $inDocroot = false;

    if ($source === 'config' && !preg_match('#^(/|[A-Za-z]:[\\\\/])#', $candidate)) {
        $problems[] = 'Storage path in inv_config must be an absolute path.';
    } elseif (!file_exists($candidate)) {
        $problems[] = 'Storage folder does not exist: ' . $candidate;
    } elseif (is_link($candidate) || !is_dir($candidate)) {
        $problems[] = 'Storage path is not a real folder (symlinks are not allowed).';
    } else {
        $root = realpath($candidate);
        if ($root === false) {
            $problems[] = 'Storage folder cannot be resolved.';
            $root = null;
        } else {
            if (!is_writable($root)) {
                $problems[] = 'Storage folder is not writable by PHP.';
            }
            $inDocroot = inv_path_in_docroot($root);
            if ($inDocroot) {
                $ht = $root . DIRECTORY_SEPARATOR . '.htaccess';
                $content = is_file($ht) ? (string) @file_get_contents($ht) : '';
                if (stripos($content, 'Require all denied') === false && stripos($content, 'Deny from all') === false) {
                    $problems[] = 'Storage folder is inside the web root but has no deny-all .htaccess.';
                }
            }
        }
    }
    return ['root' => $problems ? null : $root, 'configured' => $configured, 'problems' => $problems,
            'in_docroot' => $inDocroot, 'source' => $source, 'path' => $root ?? $candidate];
}

/** Resolve a stored relative path to an absolute path inside the root, or throw. */
function inv_storage_resolve(string $root, string $rel): string
{
    if (!preg_match(INV_STORAGE_REL_PATTERN, $rel)) {
        throw new RuntimeException('Stored path has an unexpected format.');
    }
    $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $real = realpath($abs);
    if ($real === false || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0 || is_link($abs)) {
        throw new RuntimeException('Stored file is missing or outside the storage folder.');
    }
    return $real;
}

/** Create (if needed) a sub-folder inside the root and verify it stayed inside. */
function inv_storage_ensure_dir(string $root, string $relDir): string
{
    $abs = $root;
    foreach (explode('/', $relDir) as $seg) {
        if (!preg_match('/^[A-Za-z0-9_-]{1,20}$/', $seg)) {
            throw new RuntimeException('Invalid storage folder name.');
        }
        $abs .= DIRECTORY_SEPARATOR . $seg;
        if (is_link($abs)) {
            throw new RuntimeException('Symlinks are not allowed inside storage.');
        }
        if (!is_dir($abs) && !@mkdir($abs, 0750) && !is_dir($abs)) {
            throw new RuntimeException('Cannot create storage folder.');
        }
    }
    $real = realpath($abs);
    if ($real === false || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0) {
        throw new RuntimeException('Storage folder escaped the storage root.');
    }
    return $real;
}

/**
 * Write a NEW file at its FINAL path (called before the issue transaction
 * commits). Never overwrites: an existing file at that path is an orphan of
 * a failed issue (the caller holds the series lock and has verified that no
 * committed document uses this number), so it is moved aside first.
 * Returns ['rel' => string, 'abs' => string, 'sha256' => string, 'bytes' => int, 'orphan' => ?string].
 */
function inv_storage_write_new(string $root, string $relDir, string $baseName, string $bytes): array
{
    if (!preg_match('/^[A-Za-z0-9_-]{1,40}$/', $baseName)) {
        throw new RuntimeException('Invalid stored file name.');
    }
    $dir = inv_storage_ensure_dir($root, $relDir);
    $abs = $dir . DIRECTORY_SEPARATOR . $baseName . '.pdf';
    $orphan = null;
    if (file_exists($abs) || is_link($abs)) {
        $orphan = $dir . DIRECTORY_SEPARATOR . $baseName . '.orphan-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.pdf';
        if (!@rename($abs, $orphan)) {
            throw new RuntimeException('A file already exists for this number and could not be moved aside.');
        }
    }
    $fh = @fopen($abs, 'xb');
    if ($fh === false) {
        throw new RuntimeException('Cannot create the PDF file in storage.');
    }
    $written = fwrite($fh, $bytes);
    fflush($fh);
    if (function_exists('fsync')) {
        @fsync($fh);
    }
    fclose($fh);
    @chmod($abs, 0640);
    if ($written !== strlen($bytes)) {
        @unlink($abs);
        throw new RuntimeException('The PDF file was not fully written.');
    }
    $check = @file_get_contents($abs);
    $sha = hash('sha256', $bytes);
    if ($check === false || hash('sha256', $check) !== $sha) {
        @unlink($abs);
        throw new RuntimeException('The stored PDF failed verification after writing.');
    }
    return ['rel' => $relDir . '/' . $baseName . '.pdf', 'abs' => $abs, 'sha256' => $sha,
            'bytes' => strlen($bytes), 'orphan' => $orphan];
}

/** Read a stored file and verify its hash. Throws on any mismatch. */
function inv_storage_read_verified(string $root, string $rel, string $expectedSha): string
{
    $abs = inv_storage_resolve($root, $rel);
    $bytes = @file_get_contents($abs);
    if ($bytes === false) {
        throw new RuntimeException('Stored PDF cannot be read.');
    }
    if (!hash_equals($expectedSha, hash('sha256', $bytes))) {
        throw new RuntimeException('Stored PDF failed its integrity check.');
    }
    return $bytes;
}
