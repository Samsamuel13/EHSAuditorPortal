<?php
/**
 * /api/dashboard_admin.php
 * GET -> admin/super-admin dashboard data:
 *   - audits_this_month: total + breakdown by status
 *   - pending_audits: audits still in 'scheduled' status (not yet confirmed)
 *   - utilization: per active auditor, % of this month's working days
 *                  they have at least one audit assignment
 *   - upcoming_holidays: next few holidays from today
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

ehs_require_role(['super_admin', 'admin'], true);
$db = get_db();

$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-01', strtotime($monthStart . ' +1 month'));

// ---------------------------------------------------------------------------
// Audits this month, by status
// ---------------------------------------------------------------------------
$stmt = $db->prepare(
    "SELECT status, COUNT(*) AS c FROM audits
     WHERE audit_date >= :start AND audit_date < :end
     GROUP BY status"
);
$stmt->execute(['start' => $monthStart, 'end' => $monthEnd]);
$byStatus = ['scheduled' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
$total = 0;
foreach ($stmt->fetchAll() as $row) {
    $byStatus[$row['status']] = (int) $row['c'];
    $total += (int) $row['c'];
}

// ---------------------------------------------------------------------------
// Pending confirmation (status = scheduled), upcoming only
// ---------------------------------------------------------------------------
// ---------------------------------------------------------------------------
// Pending confirmation (status = scheduled) — overdue ones (date already
// passed) are surfaced first, not excluded. The previous version filtered
// on "audit_date >= today", which silently hid the exact audits an admin
// most needs to see: ones nobody ever confirmed.
// ---------------------------------------------------------------------------
$stmt = $db->prepare(
    "SELECT a.id, a.audit_date, a.session, c.name AS client_name,
            (SELECT GROUP_CONCAT(u.name SEPARATOR ', ') FROM audit_auditors aa
             JOIN users u ON u.id = aa.auditor_id WHERE aa.audit_id = a.id) AS auditor_names
     FROM audits a
     JOIN clients c ON c.id = a.client_id
     WHERE a.status = 'scheduled'
     ORDER BY (a.audit_date < :today) DESC, a.audit_date, a.session
     LIMIT 10"
);
$stmt->execute(['today' => $today]);
$pending = $stmt->fetchAll();
foreach ($pending as &$p) {
    $p['id'] = (int) $p['id'];
    $overdue = ehs_compute_overdue('scheduled', $p['audit_date'], $today);
    $p['is_overdue'] = $overdue['is_overdue'];
}
unset($p);

// ---------------------------------------------------------------------------
// Overdue completions: confirmed audits whose date has passed but nobody
// has marked them 'completed' yet — the other half of "overdue", separate
// from unconfirmed ones above.
// ---------------------------------------------------------------------------
$stmt = $db->prepare(
    "SELECT a.id, a.audit_date, a.session, c.name AS client_name,
            (SELECT GROUP_CONCAT(u.name SEPARATOR ', ') FROM audit_auditors aa
             JOIN users u ON u.id = aa.auditor_id WHERE aa.audit_id = a.id) AS auditor_names
     FROM audits a
     JOIN clients c ON c.id = a.client_id
     WHERE a.status = 'confirmed' AND a.audit_date < :today
     ORDER BY a.audit_date
     LIMIT 10"
);
$stmt->execute(['today' => $today]);
$overdueCompletions = $stmt->fetchAll();
foreach ($overdueCompletions as &$oc) {
    $oc['id'] = (int) $oc['id'];
}
unset($oc);

// ---------------------------------------------------------------------------
// Auditor utilization this month
// ---------------------------------------------------------------------------
$holidayStmt = $db->prepare('SELECT date FROM holidays WHERE date >= :start AND date < :end');
$holidayStmt->execute(['start' => $monthStart, 'end' => $monthEnd]);
$holidaySet = array_flip(array_column($holidayStmt->fetchAll(), 'date'));

$workingDays = 0;
$cursor = new DateTime($monthStart);
$endDt = new DateTime($monthEnd);
while ($cursor < $endDt) {
    $dow = (int) $cursor->format('w');
    if ($dow !== 0 && $dow !== 6 && !isset($holidaySet[$cursor->format('Y-m-d')])) {
        $workingDays++;
    }
    $cursor->modify('+1 day');
}

$auditorStmt = $db->query(
    "SELECT id, name, color_hex FROM users WHERE role IN ('super_admin','auditor') AND status = 'active' ORDER BY name"
);
$auditors = $auditorStmt->fetchAll();

$assignedStmt = $db->prepare(
    "SELECT aa.auditor_id, COUNT(DISTINCT a.audit_date) AS days_assigned
     FROM audit_auditors aa
     JOIN audits a ON a.id = aa.audit_id
     WHERE a.audit_date >= :start AND a.audit_date < :end AND a.status != 'cancelled'
     GROUP BY aa.auditor_id"
);
$assignedStmt->execute(['start' => $monthStart, 'end' => $monthEnd]);
$assignedByAuditor = [];
foreach ($assignedStmt->fetchAll() as $row) {
    $assignedByAuditor[(int) $row['auditor_id']] = (int) $row['days_assigned'];
}

$utilization = [];
foreach ($auditors as $a) {
    $daysAssigned = $assignedByAuditor[(int) $a['id']] ?? 0;
    $percent = $workingDays > 0 ? round(($daysAssigned / $workingDays) * 100, 1) : 0.0;
    $utilization[] = [
        'id'            => (int) $a['id'],
        'name'          => $a['name'],
        'color_hex'     => $a['color_hex'],
        'days_assigned' => $daysAssigned,
        'working_days'  => $workingDays,
        'percent'       => $percent,
    ];
}
usort($utilization, fn($a, $b) => $b['percent'] <=> $a['percent']);

// ---------------------------------------------------------------------------
// Upcoming holidays
// ---------------------------------------------------------------------------
$stmt = $db->prepare('SELECT date, name, type FROM holidays WHERE date >= :today ORDER BY date LIMIT 5');
$stmt->execute(['today' => $today]);
$upcomingHolidays = $stmt->fetchAll();

ehs_json_response([
    'month_label' => date('F Y'),
    'audits_this_month' => ['total' => $total, 'by_status' => $byStatus],
    'pending_audits' => $pending,
    'overdue_completions' => $overdueCompletions,
    'utilization' => $utilization,
    'upcoming_holidays' => $upcomingHolidays,
]);
