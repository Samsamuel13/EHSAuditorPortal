<?php
// File: api/change_password.php
/**
 * /api/change_password.php
 * PUT { current_password, new_password } -> change the LOGGED-IN user's own
 * password. Deliberately has no concept of a target user id from the
 * request — it always acts on $_SESSION['user']['id'], so there is no way
 * for this endpoint to be used to change anyone else's password (that stays
 * the super-admin-only /api/users.php flow).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

$user = ehs_require_role(['super_admin', 'admin', 'auditor'], true);

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    ehs_json_error('Method not allowed.', 405);
}

ehs_verify_csrf();

$input = ehs_json_input();
$currentPassword = (string) ($input['current_password'] ?? '');
$newPassword = (string) ($input['new_password'] ?? '');

if ($currentPassword === '') {
    ehs_json_error('Please enter your current password.', 422);
}
if (strlen($newPassword) < 8) {
    ehs_json_error('New password must be at least 8 characters.', 422);
}

$db = get_db();

// Re-fetch the real password hash — the session only holds the safe fields
// (see ehs_attempt_login(), which unsets password_hash before storing).
$stmt = $db->prepare('SELECT password_hash FROM users WHERE id = :id');
$stmt->execute(['id' => $user['id']]);
$row = $stmt->fetch();

if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
    ehs_json_error('Current password is incorrect.', 403);
}

if (password_verify($newPassword, $row['password_hash'])) {
    ehs_json_error('New password must be different from your current password.', 422);
}

$update = $db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
$update->execute([
    'hash' => password_hash($newPassword, PASSWORD_BCRYPT),
    'id'   => $user['id'],
]);

// Defense in depth: rotate the session id after a credential change.
session_regenerate_id(true);

ehs_log_activity($user['id'], 'change_own_password', 'user', $user['id'], 'Password changed by user');
ehs_json_response(['success' => true]);
