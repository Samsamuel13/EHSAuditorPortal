<?php
/**
 * client-management/api/audit_extract.php
 *
 * GET ?month=last|this|next&stage=&scheme_category=&industry=&responsible_person_id=&q=
 * -> { range: {label, start, end}, counts: {last, this, next}, certifications: [...] }
 *
 * Built for the recurring "which clients need an audit last/this/next
 * month" request (Pandian Sir, WhatsApp 13 Aug) — distinct from
 * renewal_dashboard.php, which is a rolling N-days-from-today alert view.
 * This is a FIXED CALENDAR-MONTH window (e.g. run in August 2026 ->
 * Jul 1 - Sep 30 2026), because the ask was "last month / this month /
 * next month", not "next N days".
 *
 * A certification is included if ANY of its milestone dates
 * (issue_date/1st Cert, surveillance_1_date, surveillance_2_date,
 * expiry_date/Recertification) falls inside the requested month's range.
 * A cert can appear in more than one month if it has milestones in two
 * different months (rare, but correct — e.g. surveillance_1 in July and
 * surveillance_2 in September should each surface in their own month).
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/cm_helpers.php';

$user = ehs_require_role(['super_admin', 'admin'], true);
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    cm_json_error('Method not allowed.', 405);
}

$monthKey = trim((string) ($_GET['month'] ?? 'this'));
if (!in_array($monthKey, ['last', 'this', 'next'], true)) {
    $monthKey = 'this';
}

$stage          = trim((string) ($_GET['stage'] ?? ''));
$schemeCategory = trim((string) ($_GET['scheme_category'] ?? ''));
$industry       = trim((string) ($_GET['industry'] ?? ''));
$responsibleId  = (int) ($_GET['responsible_person_id'] ?? 0);
$q              = trim((string) ($_GET['q'] ?? ''));

$offsetMap = ['last' => -1, 'this' => 0, 'next' => 1];
[$rangeStart, $rangeEnd, $rangeLabel] = cm_month_range($offsetMap[$monthKey]);

// Milestone -> its DB column and human label, in cycle order.
$milestoneCols = [
    'initial'         => ['col' => 'cert.issue_date',          'label' => '1st Certification'],
    'surveillance_1'  => ['col' => 'cert.surveillance_1_date',  'label' => 'Surveillance 1'],
    'surveillance_2'  => ['col' => 'cert.surveillance_2_date',  'label' => 'Surveillance 2'],
    'recertification' => ['col' => 'cert.expiry_date',          'label' => 'Recertification'],
];
if ($stage !== '' && !isset($milestoneCols[$stage])) {
    $stage = '';
}
$activeMilestones = $stage !== '' ? [$stage => $milestoneCols[$stage]] : $milestoneCols;

$where  = ["cert.status != 'withdrawn'"];
$params = [];

if ($q !== '') {
    $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
    $where[] = "c.company_name LIKE :q ESCAPE '\\\\'";
    $params['q'] = '%' . $escaped . '%';
}
if ($schemeCategory !== '' && in_array($schemeCategory, ['ISO', 'BizSafe', 'JASANZ', 'Other'], true)) {
    $where[] = 'st.category = :scheme_category';
    $params['scheme_category'] = $schemeCategory;
}
if ($industry !== '') {
    $where[] = 'c.industry_sector = :industry';
    $params['industry'] = $industry;
}
if ($responsibleId > 0) {
    $where[] = 'cert.responsible_person_id = :responsible_id';
    $params['responsible_id'] = $responsibleId;
}

// Match if ANY active milestone column falls within [rangeStart, rangeEnd].
// NOTE: each milestone needs its OWN placeholder names — real (non-emulated)
// prepared statements reject the same named param appearing twice in one
// query, and this OR-chain can repeat the range up to 4 times.
$milestoneConds = [];
foreach ($activeMilestones as $key => $m) {
    $milestoneConds[] = "({$m['col']} BETWEEN :range_start_$key AND :range_end_$key)";
    $params["range_start_$key"] = $rangeStart;
    $params["range_end_$key"]   = $rangeEnd;
}
$where[] = '(' . implode(' OR ', $milestoneConds) . ')';

$whereSql = implode(' AND ', $where);
$sql = "
    SELECT cert.id, cert.certificate_number, cert.status, cert.cycle_stage,
           cert.issue_date, cert.surveillance_1_date, cert.surveillance_2_date, cert.expiry_date,
           c.id AS client_id, c.company_name, c.industry_sector, c.consultant,
           st.name AS scheme_name, st.category AS scheme_category,
           COALESCE(u.name, cert.responsible_person_name) AS responsible_person
    FROM cm_certifications cert
    JOIN cm_clients c ON c.id = cert.cm_client_id
    JOIN cm_scheme_types st ON st.id = cert.cm_scheme_type_id
    LEFT JOIN users u ON u.id = cert.responsible_person_id
    WHERE $whereSql
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Expand each cert into one row PER matching milestone (so a cert with
// two milestones in the same window shows both, clearly labelled) and
// tag which one(s) actually fall in the window.
$results = [];
foreach ($rows as $cert) {
    foreach ($activeMilestones as $key => $m) {
        $col = str_replace('cert.', '', $m['col']);
        $date = $cert[$col] ?? null;
        if ($date !== null && $date >= $rangeStart && $date <= $rangeEnd) {
            $row = $cert;
            $row['milestone_key']   = $key;
            $row['milestone_label'] = $m['label'];
            $row['milestone_date']  = $date;
            $results[] = $row;
        }
    }
}
usort($results, fn($a, $b) => strcmp($a['milestone_date'], $b['milestone_date']));

// Counts for all 3 months in one shot, so the tab labels can show numbers
// without 3 separate requests. Reuses the same filters (minus month).
$counts = [];
// Base filters (q/scheme_category/industry/responsible), WITHOUT the
// milestone OR-clause — that's rebuilt fresh per month below with its
// own uniquely-named placeholders.
$baseWhere = array_slice($where, 0, count($where) - 1);
$baseParams = $params;
foreach ($activeMilestones as $key => $m) {
    unset($baseParams["range_start_$key"], $baseParams["range_end_$key"]);
}

foreach ($offsetMap as $monthLoopKey => $offset) {
    [$s, $e] = cm_month_range($offset);
    $conds = [];
    $countParams = $baseParams;
    foreach ($activeMilestones as $mKey => $m) {
        $ph = "cs_{$monthLoopKey}_{$mKey}";
        $ph2 = "ce_{$monthLoopKey}_{$mKey}";
        $conds[] = "({$m['col']} BETWEEN :$ph AND :$ph2)";
        $countParams[$ph]  = $s;
        $countParams[$ph2] = $e;
    }
    $countWhere = $baseWhere;
    $countWhere[] = '(' . implode(' OR ', $conds) . ')';

    $countSql = "
        SELECT COUNT(*) FROM cm_certifications cert
        JOIN cm_clients c ON c.id = cert.cm_client_id
        JOIN cm_scheme_types st ON st.id = cert.cm_scheme_type_id
        LEFT JOIN users u ON u.id = cert.responsible_person_id
        WHERE " . implode(' AND ', $countWhere);
    $cStmt = $db->prepare($countSql);
    $cStmt->execute($countParams);
    $counts[$monthLoopKey] = (int) $cStmt->fetchColumn();
}

cm_json_response([
    'range' => ['label' => $rangeLabel, 'start' => $rangeStart, 'end' => $rangeEnd, 'month' => $monthKey],
    'counts' => $counts,
    'certifications' => $results,
]);