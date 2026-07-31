<?php
/**
 * /api/schemes.php
 * GET    -> list all certification schemes
 * POST   { name, code } -> create
 * PUT    ?id=N { name, code } -> update
 * DELETE ?id=N -> remove (blocked if in use by any auditor or audit)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

$user = ehs_require_role(['super_admin', 'admin'], true);
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->query('SELECT id, name, code FROM schemes ORDER BY name');
    ehs_json_response(['schemes' => $stmt->fetchAll()]);
}

function ehs_validate_scheme_input(array $input): array
{
    $errors = [];
    $name = trim((string) ($input['name'] ?? ''));
    $code = trim((string) ($input['code'] ?? ''));
    if ($name === '' || mb_strlen($name) > 100) $errors[] = 'A valid scheme name is required (max 100 chars).';
    if ($code === '' || mb_strlen($code) > 20) $errors[] = 'A valid scheme code is required (max 20 chars).';
    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ehs_verify_csrf();
    $input = ehs_json_input();
    $errors = ehs_validate_scheme_input($input);
    if ($errors) ehs_json_error(implode(' ', $errors), 422);

    try {
        $stmt = $db->prepare('INSERT INTO schemes (name, code) VALUES (:name, :code)');
        $stmt->execute(['name' => trim($input['name']), 'code' => trim($input['code'])]);
        $id = (int) $db->lastInsertId();
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            ehs_json_error('A scheme with that name or code already exists.', 409);
        }
        throw $e;
    }

    ehs_log_activity($user['id'], 'create_scheme', 'scheme', $id, trim($input['name']));
    ehs_json_response(['success' => true, 'id' => $id], 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    ehs_verify_csrf();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) ehs_json_error('A valid scheme id is required.', 422);

    $input = ehs_json_input();
    $errors = ehs_validate_scheme_input($input);
    if ($errors) ehs_json_error(implode(' ', $errors), 422);

    try {
        $stmt = $db->prepare('UPDATE schemes SET name = :name, code = :code WHERE id = :id');
        $stmt->execute(['name' => trim($input['name']), 'code' => trim($input['code']), 'id' => $id]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            ehs_json_error('A scheme with that name or code already exists.', 409);
        }
        throw $e;
    }

    if ($stmt->rowCount() === 0) {
        $check = $db->prepare('SELECT id FROM schemes WHERE id = :id');
        $check->execute(['id' => $id]);
        if (!$check->fetch()) ehs_json_error('Scheme not found.', 404);
    }

    ehs_log_activity($user['id'], 'update_scheme', 'scheme', $id, trim($input['name']));
    ehs_json_response(['success' => true]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    ehs_verify_csrf();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) ehs_json_error('A valid scheme id is required.', 422);

    try {
        $stmt = $db->prepare('DELETE FROM schemes WHERE id = :id');
        $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            ehs_json_error('Cannot delete: this scheme is still assigned to auditors or audits.', 409);
        }
        throw $e;
    }

    if ($stmt->rowCount() === 0) ehs_json_error('Scheme not found.', 404);

    ehs_log_activity($user['id'], 'delete_scheme', 'scheme', $id, '');
    ehs_json_response(['success' => true]);
}

ehs_json_error('Method not allowed.', 405);
