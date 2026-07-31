<?php
// File: api/activity_log.php
/**
 * /api/activity_log.php
 * GET ?user_id=&action=&entity_type=&start=&end=&page=&per_page= -> paginated
 * audit trail. Super admin only — this is the one screen that can see
 * everyone's actions across the whole system.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

ehs_require_role(['super_admin'], true);
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ehs_json_error('Method not allowed.', 405);
}

$userId     = $_GET['user_id'] ?? '';
$action     = trim($_GET['action'] ?? '');
$entityType = trim($_GET['entity_type'] ?? '');
$start      = $_GET['start'] ?? '';
$end        = $_GET['end'] ?? '';
$page       = max(1, (int) ($_GET['page'] ?? 1));
$perPage    = min(100, max(10, (int) ($_GET['per_page'] ?? 50)));

$where = [];
$params = [];

if ($userId !== '') {
    $where[] = 'l.user_id = :user_id';
    $params['user_id'] = (int) $userId;
}
if ($action !== '') {
    $where[] = 'l.action = :action';
    $params['action'] = $action;
}
if ($entityType !== '') {
    $where[] = 'l.entity_type = :entity_type';
    $params['entity_type'] = $entityType;
}
if ($start !== '' && ehs_is_valid_date($start)) {
    $where[] = 'l.created_at >= :start';
    $params['start'] = $start . ' 00:00:00';
}
if ($end !== '' && ehs_is_valid_date($end)) {
    $where[] = 'l.created_at <= :end';
    $params['end'] = $end . ' 23:59:59';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $db->prepare("SELECT COUNT(*) AS c FROM activity_log l $whereSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetch()['c'];

$offset = ($page - 1) * $perPage;
// $perPage/$offset are validated integers (cast above), not user strings, so
// this interpolation is safe. They're interpolated rather than bound because
// with PDO::ATTR_EMULATE_PREPARES off, MySQL's native prepared statements
// require LIMIT/OFFSET placeholders to be bound with an explicit PARAM_INT
// type, which the simple execute(array) shorthand used everywhere else in
// this app does not do — interpolating known-safe ints avoids that pitfall.
$sql = "SELECT l.id, l.action, l.entity_type, l.entity_id, l.details, l.created_at,
               u.name AS user_name, u.role AS user_role
        FROM activity_log l
        LEFT JOIN users u ON u.id = l.user_id
        $whereSql
        ORDER BY l.created_at DESC
        LIMIT $perPage OFFSET $offset";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
foreach ($rows as &$r) {
    $r['id'] = (int) $r['id'];
    $r['entity_id'] = $r['entity_id'] !== null ? (int) $r['entity_id'] : null;
}
unset($r);

// Distinct action/entity_type lists, to populate filter dropdowns.
$actions = $db->query('SELECT DISTINCT action FROM activity_log ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
$entityTypes = $db->query('SELECT DISTINCT entity_type FROM activity_log ORDER BY entity_type')->fetchAll(PDO::FETCH_COLUMN);

ehs_json_response([
    'entries'      => $rows,
    'total'        => $total,
    'page'         => $page,
    'per_page'     => $perPage,
    'actions'      => $actions,
    'entity_types' => $entityTypes,
]);
