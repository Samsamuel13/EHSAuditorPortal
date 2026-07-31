<?php
/**
 * auth.php — session bootstrap, login/logout, role middleware, CSRF helpers.
 * require_once this at the top of every protected page and every API endpoint.
 */

require_once __DIR__ . '/db.php';

// ---------------------------------------------------------------------------
// Session bootstrap (must run before any output)
// ---------------------------------------------------------------------------
function ehs_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME_SECONDS,
        'path'     => '/',
        'domain'   => '',
        'secure'   => SESSION_SECURE_COOKIE, // true once served over HTTPS
        'httponly' => true,                  // JS can never read the session cookie
        'samesite' => 'Lax',
    ]);

    session_name('ehs_session');
    session_start();

    // Idle timeout — destroy stale sessions rather than trusting the cookie forever.
    if (isset($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity']) > SESSION_LIFETIME_SECONDS) {
        ehs_logout();
        return;
    }
    $_SESSION['last_activity'] = time();
}

ehs_start_session();

// ---------------------------------------------------------------------------
// CSRF protection
// ---------------------------------------------------------------------------
function ehs_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Render a hidden CSRF field for a <form>. */
function ehs_csrf_field(): string
{
    $token = htmlspecialchars(ehs_csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/** Call at the top of every POST handler (form pages and API endpoints). */
function ehs_verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(403);
        die('Invalid or missing CSRF token.');
    }
}

// ---------------------------------------------------------------------------
// Login / logout
// ---------------------------------------------------------------------------

/**
 * Attempt to log a user in. Returns the user row on success, or null on
 * invalid credentials / inactive account. Never reveals which of
 * username/password was wrong (avoids user enumeration).
 */
function ehs_attempt_login(string $username, string $password): ?array
{
    $stmt = get_db()->prepare(
        'SELECT id, name, email, username, password_hash, role, color_hex, status
         FROM users WHERE username = :username LIMIT 1'
    );
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'active') {
        return null;
    }
    if (!password_verify($password, $user['password_hash'])) {
        return null;
    }

    // Prevent session fixation: regenerate the session ID on privilege change.
    session_regenerate_id(true);

    unset($user['password_hash']);
    $_SESSION['user'] = $user;

    ehs_log_activity($user['id'], 'login', 'user', $user['id'], 'User logged in');

    return $user;
}

function ehs_logout(): void
{
    if (!empty($_SESSION['user']['id'])) {
        ehs_log_activity($_SESSION['user']['id'], 'logout', 'user', $_SESSION['user']['id'], 'User logged out');
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function ehs_current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function ehs_is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

// ---------------------------------------------------------------------------
// Role middleware — call at the very top of every protected page/endpoint
// ---------------------------------------------------------------------------

/**
 * Requires an authenticated session. Redirects to the login page for normal
 * requests; for AJAX/API calls (isApi = true) returns a 401 JSON body
 * instead, since an HTTP redirect is useless to a fetch() call and previously
 * left the frontend showing a generic "network error" when a session expired
 * mid-use.
 */
function ehs_require_login(bool $isApi = false): array
{
    if (!ehs_is_logged_in()) {
        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Your session has expired. Please log in again.', 'session_expired' => true]);
            exit;
        }
        header('Location: ' . ehs_url('login.php'));
        exit;
    }
    return ehs_current_user();
}

/**
 * Redirects/aborts unless the current user's role is in $allowedRoles.
 * Usage: ehs_require_role(['super_admin', 'admin']);
 * For API endpoints (JSON), pass $isApi = true to get a 401/403 JSON body
 * instead of an HTML redirect.
 */
function ehs_require_role(array $allowedRoles, bool $isApi = false): array
{
    $user = ehs_require_login($isApi);

    if (!in_array($user['role'], $allowedRoles, true)) {
        if ($isApi) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Forbidden: insufficient role']);
            exit;
        }
        http_response_code(403);
        die('403 Forbidden: you do not have permission to view this page.');
    }

    return $user;
}

function ehs_is_super_admin(): bool
{
    $user = ehs_current_user();
    return $user !== null && $user['role'] === 'super_admin';
}

// ---------------------------------------------------------------------------
// Activity log
// ---------------------------------------------------------------------------
function ehs_log_activity(int $userId, string $action, string $entityType, ?int $entityId, string $details = ''): void
{
    $stmt = get_db()->prepare(
        'INSERT INTO activity_log (user_id, action, entity_type, entity_id, details)
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
