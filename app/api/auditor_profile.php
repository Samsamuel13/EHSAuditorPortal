<?php
/**
 * /api/auditor_profile.php
 * GET       -> all auditors (role IN super_admin, auditor) with profile fields + approved schemes
 * PUT ?id=N { color_hex, phone, status, scheme_ids[] } -> update ONLY operational profile fields
 *
 * Deliberately does NOT touch username/email/password/role — those are
 * account-level changes reserved for super admins via /api/users.php.
 * Creating a brand-new auditor login also happens there; this endpoint only
 * edits auditors who already have an account.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

$user = ehs_require_role(['super_admin', 'admin'], true);
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->query(
        "SELECT u.id, u.name, u.email, u.phone, u.color_hex, u.status,
                GROUP_CONCAT(s.id ORDER BY s.id SEPARATOR ',') AS scheme_ids
         FROM users u
         LEFT JOIN auditor_schemes aus ON aus.auditor_id = u.id
         LEFT JOIN schemes s ON s.id = aus.scheme_id
         WHERE u.role IN ('super_admin','auditor')
         GROUP BY u.id, u.name, u.email, u.phone, u.color_hex, u.status
         ORDER BY u.name"
    );
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['scheme_ids'] = $r['scheme_ids'] ? array_map('intval', explode(',', $r['scheme_ids'])) : [];
    }
    unset($r);

    ehs_json_response(['auditors' => $rows]);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    ehs_verify_csrf();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) ehs_json_error('A valid auditor id is required.', 422);

    $check = $db->prepare("SELECT id FROM users WHERE id = :id AND role IN ('super_admin','auditor')");
    $check->execute(['id' => $id]);
    if (!$check->fetch()) ehs_json_error('Auditor not found.', 404);

    $input = ehs_json_input();
    $colorHex = trim((string) ($input['color_hex'] ?? ''));
    $phone    = trim((string) ($input['phone'] ?? ''));
    $status   = $input['status'] ?? 'active';
    $schemeIds = $input['scheme_ids'] ?? [];

    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $colorHex)) {
        ehs_json_error('color_hex must be a valid #RRGGBB value.', 422);
    }
    if (!in_array($status, ['active', 'inactive'], true)) {
        ehs_json_error('Invalid status value.', 422);
    }
    if (!is_array($schemeIds)) {
        ehs_json_error('scheme_ids must be an array.', 422);
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('UPDATE users SET color_hex = :color, phone = :phone, status = :status WHERE id = :id');
        $stmt->execute([
            'color'  => $colorHex,
            'phone'  => $phone !== '' ? $phone : null,
            'status' => $status,
            'id'     => $id,
        ]);

        $db->prepare('DELETE FROM auditor_schemes WHERE auditor_id = :id')->execute(['id' => $id]);
        $insert = $db->prepare('INSERT INTO auditor_schemes (auditor_id, scheme_id) VALUES (:auditor_id, :scheme_id)');
        foreach (array_unique(array_map('intval', $schemeIds)) as $schemeId) {
            $insert->execute(['auditor_id' => $id, 'scheme_id' => $schemeId]);
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('Auditor profile update failed: ' . $e->getMessage());
        ehs_json_error('Could not update auditor profile.', 500);
    }

    ehs_log_activity($user['id'], 'update_auditor_profile', 'user', $id, "status=$status");
    ehs_json_response(['success' => true]);
}

ehs_json_error('Method not allowed.', 405);
