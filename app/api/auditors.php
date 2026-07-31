<?php
/**
 * /api/auditors.php
 * GET -> active auditors (and super admins, who also audit per real schedule
 *        data) with their color and approved schemes.
 *
 * Optional ?date=YYYY-MM-DD&session=AM|PM|FULL_DAY&exclude_audit_id=N adds,
 * per auditor:
 *   - availability_status: 'available' | 'unavailable' | 'tentative' | 'not_set'
 *   - conflict: true if already assigned to a different audit that overlaps
 *               this date/session (used for the assignment modal's warnings)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

ehs_require_role(['super_admin', 'admin'], true);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ehs_json_error('Method not allowed.', 405);
}

$db = get_db();

$stmt = $db->query(
    "SELECT u.id, u.name, u.color_hex,
            GROUP_CONCAT(s.code ORDER BY s.code SEPARATOR ',') AS scheme_codes
     FROM users u
     LEFT JOIN auditor_schemes aus ON aus.auditor_id = u.id
     LEFT JOIN schemes s ON s.id = aus.scheme_id
     WHERE u.role IN ('super_admin','auditor') AND u.status = 'active'
     GROUP BY u.id, u.name, u.color_hex
     ORDER BY u.name"
);
$auditors = $stmt->fetchAll();
foreach ($auditors as &$a) {
    $a['id'] = (int) $a['id'];
    $a['scheme_codes'] = $a['scheme_codes'] ? explode(',', $a['scheme_codes']) : [];
    $a['availability_status'] = 'not_set';
    $a['conflict'] = false;
}
unset($a);

$date    = $_GET['date'] ?? '';
$session = $_GET['session'] ?? '';
$excludeAuditId = isset($_GET['exclude_audit_id']) ? (int) $_GET['exclude_audit_id'] : null;

if ($date !== '' && $session !== '' && ehs_is_valid_date($date) && in_array($session, ['AM', 'PM', 'FULL_DAY'], true)) {
    $sessionsToCheck = $session === 'FULL_DAY' ? ['AM', 'PM', 'FULL_DAY'] : [$session, 'FULL_DAY'];

    // --- availability lookup for this date across all auditors at once ---
    $placeholders = implode(',', array_fill(0, count($sessionsToCheck), '?'));
    $availStmt = $db->prepare(
        "SELECT auditor_id, session, status FROM availability
         WHERE date = ? AND session IN ($placeholders)"
    );
    $availStmt->execute(array_merge([$date], $sessionsToCheck));
    $availRows = $availStmt->fetchAll();

    $availByAuditor = [];
    foreach ($availRows as $row) {
        $availByAuditor[(int) $row['auditor_id']][] = $row;
    }

    // --- existing assignments lookup for this date across all auditors ---
    $sql = "SELECT aa.auditor_id, a.session, a.id AS audit_id
            FROM audit_auditors aa
            JOIN audits a ON a.id = aa.audit_id
            WHERE a.audit_date = ? AND a.status != 'cancelled'";
    $params = [$date];
    if ($excludeAuditId) {
        $sql .= ' AND a.id != ?';
        $params[] = $excludeAuditId;
    }
    $assignStmt = $db->prepare($sql);
    $assignStmt->execute($params);
    $assignRows = $assignStmt->fetchAll();

    $assignByAuditor = [];
    foreach ($assignRows as $row) {
        $assignByAuditor[(int) $row['auditor_id']][] = $row['session'];
    }

    $overlaps = function (string $a, string $b): bool {
        if ($a === 'FULL_DAY' || $b === 'FULL_DAY') return true;
        return $a === $b;
    };

    foreach ($auditors as &$a) {
        // Availability: unavailable beats tentative beats available beats not_set.
        $rows = $availByAuditor[$a['id']] ?? [];
        $priority = ['unavailable' => 3, 'tentative' => 2, 'available' => 1];
        $best = 'not_set';
        foreach ($rows as $r) {
            if (($priority[$r['status']] ?? 0) > ($priority[$best] ?? 0)) {
                $best = $r['status'];
            }
        }
        $a['availability_status'] = $best;

        // Conflict: already assigned to another audit overlapping this session.
        $existingSessions = $assignByAuditor[$a['id']] ?? [];
        foreach ($existingSessions as $es) {
            if ($overlaps($es, $session)) {
                $a['conflict'] = true;
                break;
            }
        }
    }
    unset($a);
}

ehs_json_response(['auditors' => $auditors]);
