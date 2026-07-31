<?php
/**
 * /api/users.php
 * Full account management — super_admin ONLY. This is where logins, roles,
 * and passwords are created/changed; /api/auditor_profile.php handles the
 * narrower, admin-accessible operational fields for existing auditors.
 *
 * GET    -> list all users (never includes password_hash)
 * POST   { name, email, username, password, role, color_hex, phone, status } -> create
 * PUT    ?id=N (same body; password optional — only resets it if provided)  -> update
 * DELETE ?id=N -> remove (blocked if the user has historical records, or is
 *                 the last active super admin, or is the requester themself)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

$user = ehs_require_role(['super_admin'], true);
$db = get_db();

$validRoles = ['super_admin', 'admin', 'auditor'];
$validStatuses = ['active', 'inactive'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->query(
        'SELECT id, name, email, username, role, color_hex, phone, status, created_at
         FROM users ORDER BY role, name'
    );
    ehs_json_response(['users' => $stmt->fetchAll()]);
}

function ehs_validate_user_input(array $input, array $validRoles, array $validStatuses, bool $requirePassword): array
{
    $errors = [];
    if (trim((string) ($input['name'] ?? '')) === '') $errors[] = 'Name is required.';
    if (!filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if (trim((string) ($input['username'] ?? '')) === '') $errors[] = 'Username is required.';
    if (!in_array($input['role'] ?? '', $validRoles, true)) $errors[] = 'A valid role is required.';
    if (!in_array($input['status'] ?? 'active', $validStatuses, true)) $errors[] = 'Invalid status.';
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $input['color_hex'] ?? '')) $errors[] = 'color_hex must be a valid #RRGGBB value.';

    $password = (string) ($input['password'] ?? '');
    if ($requirePassword && strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif ($password !== '' && strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ehs_verify_csrf();
    $input = ehs_json_input();
    $errors = ehs_validate_user_input($input, $validRoles, $validStatuses, true);
    if ($errors) ehs_json_error(implode(' ', $errors), 422);

    try {
        $stmt = $db->prepare(
            'INSERT INTO users (name, email, username, password_hash, role, color_hex, phone, status)
             VALUES (:name, :email, :username, :password_hash, :role, :color_hex, :phone, :status)'
        );
        $stmt->execute([
            'name'          => trim($input['name']),
            'email'         => trim($input['email']),
            'username'      => trim($input['username']),
            'password_hash' => password_hash($input['password'], PASSWORD_BCRYPT),
            'role'          => $input['role'],
            'color_hex'     => $input['color_hex'],
            'phone'         => trim((string) ($input['phone'] ?? '')) ?: null,
            'status'        => $input['status'] ?? 'active',
        ]);
        $id = (int) $db->lastInsertId();
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            ehs_json_error('A user with that email or username already exists.', 409);
        }
        throw $e;
    }

    ehs_log_activity($user['id'], 'create_user', 'user', $id, trim($input['name']) . ' (' . $input['role'] . ')');
    ehs_json_response(['success' => true, 'id' => $id], 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    ehs_verify_csrf();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) ehs_json_error('A valid user id is required.', 422);

    $input = ehs_json_input();
    $errors = ehs_validate_user_input($input, $validRoles, $validStatuses, false);
    if ($errors) ehs_json_error(implode(' ', $errors), 422);

    // Guard against locking everyone out: don't allow demoting/deactivating
    // the last active super admin.
    if ($input['role'] !== 'super_admin' || ($input['status'] ?? 'active') !== 'active') {
        $countStmt = $db->prepare(
            "SELECT COUNT(*) AS c FROM users WHERE role = 'super_admin' AND status = 'active' AND id != :id"
        );
        $countStmt->execute(['id' => $id]);
        $remaining = (int) $countStmt->fetch()['c'];

        $target = $db->prepare("SELECT role, status FROM users WHERE id = :id");
        $target->execute(['id' => $id]);
        $targetRow = $target->fetch();

        if ($targetRow && $targetRow['role'] === 'super_admin' && $targetRow['status'] === 'active' && $remaining === 0) {
            ehs_json_error('Cannot change the last active super admin — promote another account first.', 409);
        }
    }

    $params = [
        'name'      => trim($input['name']),
        'email'     => trim($input['email']),
        'username'  => trim($input['username']),
        'role'      => $input['role'],
        'color_hex' => $input['color_hex'],
        'phone'     => trim((string) ($input['phone'] ?? '')) ?: null,
        'status'    => $input['status'] ?? 'active',
        'id'        => $id,
    ];

    $sql = 'UPDATE users SET name = :name, email = :email, username = :username, role = :role,
                   color_hex = :color_hex, phone = :phone, status = :status';
    if (!empty($input['password'])) {
        $sql .= ', password_hash = :password_hash';
        $params['password_hash'] = password_hash($input['password'], PASSWORD_BCRYPT);
    }
    $sql .= ' WHERE id = :id';

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            ehs_json_error('A user with that email or username already exists.', 409);
        }
        throw $e;
    }

    ehs_log_activity($user['id'], 'update_user', 'user', $id, trim($input['name']));
    ehs_json_response(['success' => true]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    ehs_verify_csrf();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) ehs_json_error('A valid user id is required.', 422);

    if ($id === $user['id']) {
        ehs_json_error('You cannot delete your own account.', 400);
    }

    try {
        $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            ehs_json_error('Cannot delete: this user has historical records (audits, availability, or activity). Deactivate the account instead.', 409);
        }
        throw $e;
    }

    if ($stmt->rowCount() === 0) ehs_json_error('User not found.', 404);

    ehs_log_activity($user['id'], 'delete_user', 'user', $id, '');
    ehs_json_response(['success' => true]);
}

ehs_json_error('Method not allowed.', 405);
