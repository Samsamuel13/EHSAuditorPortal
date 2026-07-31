<?php
// File: api/team_availability.php
/**
 * /api/team_availability.php
 * GET ?start=&end= -> availability rows for EVERY auditor in range.
 * Admin/super_admin only — this is the one place availability crosses from
 * "my own calendar" into "everyone's calendar at once", needed to tint the
 * master schedule's grid view. /api/availability.php stays scoped to the
 * logged-in user's own data; this is a separate, more privileged view.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

ehs_require_role(['super_admin', 'admin'], true);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ehs_json_error('Method not allowed.', 405);
}

$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';

if (!ehs_is_valid_date($start) || !ehs_is_valid_date($end)) {
    ehs_json_error('start and end must be valid YYYY-MM-DD dates.', 422);
}

$stmt = get_db()->prepare(
    'SELECT auditor_id, date, session, status FROM availability
     WHERE date >= :start AND date < :end'
);
$stmt->execute(['start' => $start, 'end' => $end]);
$rows = $stmt->fetchAll();
foreach ($rows as &$r) { $r['auditor_id'] = (int) $r['auditor_id']; }
unset($r);

ehs_json_response(['availability' => $rows]);
