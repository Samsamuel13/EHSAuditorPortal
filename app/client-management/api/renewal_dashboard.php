<?php
/**
 * client-management/api/renewal_dashboard.php
 *
 * GET  ?scheme_category=&industry=&responsible_person_id=&bucket=
 *      -> { thresholds: [t1,t2,t3], counts: {overdue, near, far}, certifications: [...] }
 *      bucket (optional): 'overdue' | 'near' | 'far' | '' (all, default)
 *
 * PUT  { thresholds: [t1,t2,t3] } -> update the default alert thresholds
 *      (Super Admin only, per the module's permission table)
 *
 * "near" = expiring within the first threshold (default: 30 days).
 * "far"  = expiring between the 2nd and 3rd thresholds (default: 60-90 days),
 *          matching the three dashboard widgets named in the spec.
 * This mirrors cm_expiry_badge's simpler <=30-day logic but with
 * configurable, multi-bucket boundaries for the dashboard specifically.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/cm_helpers.php';

$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'PUT') {
    // Only Super Admin may change default alert thresholds, per the
    // module's permission table (Admin cannot edit alert thresholds).
    ehs_require_role(['super_admin'], true);
    ehs_verify_csrf();

    $input = cm_json_input();
    $thresholds = array_map('intval', (array) ($input['thresholds'] ?? []));
    $thresholds = array_values(array_filter($thresholds, fn($n) => $n > 0));
    sort($thresholds);

    if (count($thresholds) !== 3) {
        cm_json_error('Provide exactly 3 positive-day thresholds (e.g. 30, 60, 90).', 422);
    }

    cm_set_renewal_thresholds($db, $thresholds);
    cm_json_response(['thresholds' => $thresholds]);
}

$user = ehs_require_role(['super_admin', 'admin'], true);

if ($method === 'GET') {
    $today = date('Y-m-d');
    [$t1, $t2, $t3] = cm_get_renewal_thresholds($db);

    $schemeCategory = trim((string) ($_GET['scheme_category'] ?? ''));
    $industry       = trim((string) ($_GET['industry'] ?? ''));
    $responsibleId  = (int) ($_GET['responsible_person_id'] ?? 0);
    $bucket         = trim((string) ($_GET['bucket'] ?? ''));

    $where  = ["cert.status != 'withdrawn'", 'cert.expiry_date IS NOT NULL'];
    $params = [];

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

    $baseWhereSql = implode(' AND ', $where);
    $baseSql = "
        FROM cm_certifications cert
        JOIN cm_clients c ON c.id = cert.cm_client_id
        JOIN cm_scheme_types st ON st.id = cert.cm_scheme_type_id
        LEFT JOIN users u ON u.id = cert.responsible_person_id
        WHERE $baseWhereSql
    ";

    // Bucket boundaries, expressed as day offsets from today:
    //   overdue: expiry < today
    //   near:    today <= expiry <= today + t1        (e.g. 0-30 days)
    //   far:     today + t2 <= expiry <= today + t3    (e.g. 60-90 days)
    $countParams = $params;
    $countParams['t1'] = $t1;
    $countParams['t2'] = $t2;
    $countParams['t3'] = $t3;

    $countSql = "
        SELECT
            SUM(CASE WHEN cert.expiry_date < :today1 THEN 1 ELSE 0 END) AS overdue_count,
            SUM(CASE WHEN cert.expiry_date >= :today2 AND cert.expiry_date <= DATE_ADD(:today3, INTERVAL :t1 DAY) THEN 1 ELSE 0 END) AS near_count,
            SUM(CASE WHEN cert.expiry_date >= DATE_ADD(:today4, INTERVAL :t2 DAY) AND cert.expiry_date <= DATE_ADD(:today5, INTERVAL :t3 DAY) THEN 1 ELSE 0 END) AS far_count
        $baseSql
    ";
    $countParams['today1'] = $today;
    $countParams['today2'] = $today;
    $countParams['today3'] = $today;
    $countParams['today4'] = $today;
    $countParams['today5'] = $today;

    $countStmt = $db->prepare($countSql);
    $countStmt->execute($countParams);
    $counts = $countStmt->fetch();

    // Drill-down list, optionally narrowed to one bucket. Built as three
    // explicit branches (rather than splicing WHERE-clause fragments)
    // so each bucket's date-arithmetic is easy to read and verify.
    $listSql = "
        SELECT cert.id, cert.certificate_number, cert.expiry_date, cert.status, cert.cycle_stage,
               c.id AS client_id, c.company_name, c.industry_sector,
               st.name AS scheme_name, st.category AS scheme_category,
               COALESCE(u.name, cert.responsible_person_name) AS responsible_person
        $baseSql
    ";
    if ($bucket === 'overdue') {
        $listSql .= ' AND cert.expiry_date < :b_today';
        $listParams = array_merge($params, ['b_today' => $today]);
    } elseif ($bucket === 'near') {
        $listSql .= ' AND cert.expiry_date >= :b_today AND cert.expiry_date <= DATE_ADD(:b_today2, INTERVAL :b_t1 DAY)';
        $listParams = array_merge($params, ['b_today' => $today, 'b_today2' => $today, 'b_t1' => $t1]);
    } elseif ($bucket === 'far') {
        $listSql .= ' AND cert.expiry_date >= DATE_ADD(:b_today, INTERVAL :b_t2 DAY) AND cert.expiry_date <= DATE_ADD(:b_today2, INTERVAL :b_t3 DAY)';
        $listParams = array_merge($params, ['b_today' => $today, 'b_today2' => $today, 'b_t2' => $t2, 'b_t3' => $t3]);
    } else {
        $listParams = $params;
    }
    $listSql .= ' ORDER BY cert.expiry_date ASC LIMIT 500';

    $listStmt = $db->prepare($listSql);
    $listStmt->execute($listParams);
    $certifications = $listStmt->fetchAll();

    foreach ($certifications as &$cert) {
        $cert['expiry_badge'] = cm_expiry_badge($cert['expiry_date'], $today);
    }
    unset($cert);

    cm_json_response([
        'thresholds' => [$t1, $t2, $t3],
        'counts' => [
            'overdue' => (int) ($counts['overdue_count'] ?? 0),
            'near'    => (int) ($counts['near_count'] ?? 0),
            'far'     => (int) ($counts['far_count'] ?? 0),
        ],
        'certifications' => $certifications,
    ]);
}

cm_json_error('Method not allowed.', 405);
