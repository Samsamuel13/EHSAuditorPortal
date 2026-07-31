<?php
/**
 * db.php — single PDO connection, shared via get_db().
 * Always use prepared statements with this connection. Never concatenate
 * user input into SQL strings.
 */

require_once __DIR__ . '/../config.php';

date_default_timezone_set(APP_TIMEZONE);

/**
 * Build an absolute URL from a path relative to the app root, using
 * URL_BASE from config.php. Use this for every <link>, <script src>,
 * <a href>, form action, and header('Location: ...') redirect — never
 * hardcode a leading-slash path, since that assumes the app is served from
 * the domain root, which isn't true for subfolder deployments like XAMPP's
 * http://localhost:8888/auditor_portal/app.
 *
 * ehs_url('assets/css/style.css') -> 'http://localhost:8888/auditor_portal/app/assets/css/style.css'
 * ehs_url('login.php')            -> 'http://localhost:8888/auditor_portal/app/login.php'
 * ehs_url()                       -> 'http://localhost:8888/auditor_portal/app'
 */
function ehs_url(string $path = ''): string
{
    $base = rtrim(URL_BASE, '/');
    $path = ltrim($path, '/');
    return $path === '' ? $base : $base . '/' . $path;
}

function get_db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements
            ]);
        } catch (PDOException $e) {
            // Never leak DB credentials or raw exception details to the browser.
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('A server error occurred. Please try again later.');
        }
    }

    return $pdo;
}
