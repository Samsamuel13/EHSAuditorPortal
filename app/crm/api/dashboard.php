<?php
/**
 * crm/api/dashboard.php
 *
 * GET -> { new_enquiries_this_week, overdue_followups, quotations_awaiting_response,
 *          win_rate_this_month: {awarded, closed, percent}, stage_counts: {enquiry: n, ...} }
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/crm_helpers.php';

$user = ehs_require_role(['super_admin', 'admin'], true);
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    crm_json_error('Method not allowed.', 405);
}

// New enquiries this week (Mon-Sun, current week).
$weekStmt = $db->query(
    "SELECT COUNT(*) FROM crm_leads
     WHERE created_at >= (CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY)"
);
$newEnquiriesThisWeek = (int) $weekStmt->fetchColumn();

// Overdue follow-ups: not done, due date in the past.
$overdueStmt = $db->query(
    "SELECT COUNT(*) FROM crm_followups WHERE done = 0 AND due_date < CURDATE()"
);
$overdueFollowups = (int) $overdueStmt->fetchColumn();

// Quotations awaiting response: latest version per lead with status = 'sent'.
$awaitingStmt = $db->query(
    "SELECT COUNT(*) FROM crm_quotations q
     WHERE q.status = 'sent'
       AND q.version = (SELECT MAX(q2.version) FROM crm_quotations q2 WHERE q2.crm_lead_id = q.crm_lead_id)"
);
$quotationsAwaiting = (int) $awaitingStmt->fetchColumn();

// Win rate this month: of leads that reached a terminal stage (awarded/lost)
// via a stage-history event this month, what % were awarded.
$winStmt = $db->prepare(
    "SELECT to_stage, COUNT(DISTINCT crm_lead_id) AS cnt
     FROM crm_lead_stage_history
     WHERE to_stage IN ('awarded', 'lost')
       AND changed_at >= :month_start
     GROUP BY to_stage"
);
$winStmt->execute(['month_start' => date('Y-m-01')]);
$awarded = 0; $lost = 0;
foreach ($winStmt->fetchAll() as $row) {
    if ($row['to_stage'] === 'awarded') $awarded = (int) $row['cnt'];
    if ($row['to_stage'] === 'lost') $lost = (int) $row['cnt'];
}
$closed = $awarded + $lost;
$winRatePercent = $closed > 0 ? round(($awarded / $closed) * 100, 1) : null;

// Stage counts, for the kanban column headers / a simple funnel chart.
$stageStmt = $db->query("SELECT stage, COUNT(*) AS cnt FROM crm_leads GROUP BY stage");
$stageCounts = array_fill_keys(CRM_STAGES, 0);
foreach ($stageStmt->fetchAll() as $row) {
    $stageCounts[$row['stage']] = (int) $row['cnt'];
}

crm_json_response([
    'new_enquiries_this_week'      => $newEnquiriesThisWeek,
    'overdue_followups'            => $overdueFollowups,
    'quotations_awaiting_response' => $quotationsAwaiting,
    'win_rate_this_month'          => ['awarded' => $awarded, 'closed' => $closed, 'percent' => $winRatePercent],
    'stage_counts'                 => $stageCounts,
]);
