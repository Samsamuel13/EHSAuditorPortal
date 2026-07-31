<?php
/**
 * /api/clients.php
 * GET  ?q=search -> up to 20 matching clients (id, name), or all clients if q is empty
 * POST { name, notes } -> create a new client (or return the existing one if the
 *      name already exists, since the assignment modal's "add new client" flow
 *      shouldn't create silent duplicates)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

$user = ehs_require_role(['super_admin', 'admin'], true);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $q = trim($_GET['q'] ?? '');

    if ($q === '') {
        $stmt = get_db()->query('SELECT id, name, notes FROM clients ORDER BY name LIMIT 50');
    } else {
        // Escape LIKE wildcards in user input so % and _ are treated literally.
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
        $stmt = get_db()->prepare(
            "SELECT id, name, notes FROM clients WHERE name LIKE :q ESCAPE '\\\\' ORDER BY name LIMIT 20"
        );
        $stmt->execute(['q' => '%' . $escaped . '%']);
    }

    ehs_json_response(['clients' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ehs_verify_csrf();

    $input = ehs_json_input();
    $name  = trim((string) ($input['name'] ?? ''));
    $notes = trim((string) ($input['notes'] ?? ''));

    if ($name === '') {
        ehs_json_error('Client name is required.', 422);
    }
    if (mb_strlen($name) > 150) {
        ehs_json_error('Client name is too long (150 characters max).', 422);
    }

    $db = get_db();

    // Look for an existing client with this exact name first (case-insensitive
    // via the column's default collation) to avoid accidental duplicates.
    $existing = $db->prepare('SELECT id, name, notes FROM clients WHERE name = :name LIMIT 1');
    $existing->execute(['name' => $name]);
    $found = $existing->fetch();

    if ($found) {
        ehs_json_response(['client' => $found, 'created' => false]);
    }

    $stmt = $db->prepare('INSERT INTO clients (name, notes) VALUES (:name, :notes)');
    $stmt->execute(['name' => $name, 'notes' => $notes !== '' ? $notes : null]);
    $newId = (int) $db->lastInsertId();

    ehs_log_activity($user['id'], 'create_client', 'client', $newId, $name);

    ehs_json_response(['client' => ['id' => $newId, 'name' => $name, 'notes' => $notes], 'created' => true], 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    ehs_verify_csrf();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) ehs_json_error('A valid client id is required.', 422);

    $input = ehs_json_input();
    $name  = trim((string) ($input['name'] ?? ''));
    $notes = trim((string) ($input['notes'] ?? ''));

    if ($name === '' || mb_strlen($name) > 150) {
        ehs_json_error('A valid client name is required (max 150 chars).', 422);
    }

    try {
        $stmt = $db->prepare('UPDATE clients SET name = :name, notes = :notes WHERE id = :id');
        $stmt->execute(['name' => $name, 'notes' => $notes !== '' ? $notes : null, 'id' => $id]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            ehs_json_error('A client with that name already exists.', 409);
        }
        throw $e;
    }

    ehs_log_activity($user['id'], 'update_client', 'client', $id, $name);
    ehs_json_response(['success' => true]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    ehs_verify_csrf();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) ehs_json_error('A valid client id is required.', 422);

    try {
        $stmt = $db->prepare('DELETE FROM clients WHERE id = :id');
        $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            ehs_json_error('Cannot delete: this client has existing audits on file.', 409);
        }
        throw $e;
    }

    if ($stmt->rowCount() === 0) ehs_json_error('Client not found.', 404);

    ehs_log_activity($user['id'], 'delete_client', 'client', $id, '');
    ehs_json_response(['success' => true]);
}

ehs_json_error('Method not allowed.', 405);
