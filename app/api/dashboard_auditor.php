<?php
/**
 * /api/dashboard_auditor.php
 * GET -> the logged-in user's own dashboard data:
 *   - upcoming: next assignments (any role can have assignments, per real data)
 *   - availability_summary: for this month and next month, how many working
 *     days (weekdays, excluding holidays) are available/unavailable/tentative/not_set
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

$user = ehs_require_role(['super_admin', 'admin', 'auditor'], true);
$db = get_db();

$today = date('Y-m-d');

// ---------------------------------------------------------------------------
// Upcoming assignments
// ---------------------------------------------------------------------------
$stmt = $db->prepare(
    'SELECT a.id, a.audit_date, a.session, a.status, c.name AS client_name
     FROM audits a
     JOIN audit_auditors aa ON aa.audit_id = a.id
     JOIN clients c ON c.id = a.client_id
     WHERE aa.auditor_id = :uid AND a.status IN ("scheduled", "confirmed")
     ORDER BY (a.audit_date < :today) DESC, a.audit_date, a.session
     LIMIT 10'
);
$stmt->execute(['uid' => $user['id'], 'today' => $today]);
$upcoming = $stmt->fetchAll();

if ($upcoming) {
    $ids = array_map('intval', array_column($upcoming, 'id'));
    $inClause = implode(',', array_fill(0, count($ids), '?'));
    $schemeStmt = $db->prepare(
        "SELECT as2.audit_id, s.code FROM audit_schemes as2
         JOIN schemes s ON s.id = as2.scheme_id WHERE as2.audit_id IN ($inClause)"
    );
    $schemeStmt->execute($ids);
    $schemesByAudit = [];
    foreach ($schemeStmt->fetchAll() as $row) {
        $schemesByAudit[$row['audit_id']][] = $row['code'];
    }
    foreach ($upcoming as &$a) {
        $a['id'] = (int) $a['id'];
        $a['schemes'] = $schemesByAudit[$a['id']] ?? [];
        $overdue = ehs_compute_overdue($a['status'], $a['audit_date'], $today);
        $a['is_overdue'] = $overdue['is_overdue'];
    }
    unset($a);
}

// ---------------------------------------------------------------------------
// Availability completeness for this month + next month
// ---------------------------------------------------------------------------
$monthStart = date('Y-m-01');
$twoMonthsEnd = date('Y-m-01', strtotime($monthStart . ' +2 month'));
$nextMonthStart = date('Y-m-01', strtotime($monthStart . ' +1 month'));

$holidayStmt = $db->prepare('SELECT date FROM holidays WHERE date >= :start AND date < :end');
$holidayStmt->execute(['start' => $monthStart, 'end' => $twoMonthsEnd]);
$holidaySet = array_flip(array_column($holidayStmt->fetchAll(), 'date'));

$availStmt = $db->prepare(
    'SELECT date, session, status FROM availability
     WHERE auditor_id = :uid AND date >= :start AND date < :end'
);
$availStmt->execute(['uid' => $user['id'], 'start' => $monthStart, 'end' => $twoMonthsEnd]);
$availByDate = [];
foreach ($availStmt->fetchAll() as $row) {
    $availByDate[$row['date']][] = $row;
}

$priority = ['unavailable' => 3, 'tentative' => 2, 'available' => 1];

function ehs_summarize_month(string $start, string $end, array $holidaySet, array $availByDate, array $priority): array
{
    $summary = ['available' => 0, 'unavailable' => 0, 'tentative' => 0, 'not_set' => 0, 'working_days' => 0];
    $cursor = new DateTime($start);
    $endDt = new DateTime($end);

    while ($cursor < $endDt) {
        $dateStr = $cursor->format('Y-m-d');
        $dow = (int) $cursor->format('w');
        $isWorkingDay = $dow !== 0 && $dow !== 6 && !isset($holidaySet[$dateStr]);

        if ($isWorkingDay) {
            $summary['working_days']++;
            $rows = $availByDate[$dateStr] ?? [];
            $best = 'not_set';
            foreach ($rows as $r) {
                if (($priority[$r['status']] ?? 0) > ($priority[$best] ?? 0)) {
                    $best = $r['status'];
                }
            }
            $summary[$best]++;
        }
        $cursor->modify('+1 day');
    }
    return $summary;
}

$thisMonthSummary = ehs_summarize_month($monthStart, $nextMonthStart, $holidaySet, $availByDate, $priority);
$nextMonthSummary = ehs_summarize_month($nextMonthStart, $twoMonthsEnd, $holidaySet, $availByDate, $priority);

ehs_json_response([
    'upcoming' => $upcoming,
    'availability_summary' => [
        'this_month' => array_merge(['label' => date('F Y')], $thisMonthSummary),
        'next_month' => array_merge(['label' => date('F Y', strtotime($nextMonthStart))], $nextMonthSummary),
    ],
]);
