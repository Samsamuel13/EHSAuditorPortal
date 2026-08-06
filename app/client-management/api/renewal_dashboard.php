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

    $q              = trim((string) ($_GET['q'] ?? ''));
    $schemeCategory = trim((string) ($_GET['scheme_category'] ?? ''));
    $industry       = trim((string) ($_GET['industry'] ?? ''));
    $responsibleId  = (int) ($_GET['responsible_person_id'] ?? 0);
    $bucket         = trim((string) ($_GET['bucket'] ?? ''));

    // A certification is a candidate if ANY of its 3 forward-looking
    // milestones (surveillance_1, surveillance_2, recertification/expiry)
    // is set — next-due is computed in PHP below rather than in SQL,
    // since "earliest of up to 3 nullable date columns" is much clearer
    // and less error-prone done row-by-row than as nested SQL CASE logic.
    $where = [
        "cert.status != 'withdrawn'",
        '(cert.surveillance_1_date IS NOT NULL OR cert.surveillance_2_date IS NOT NULL OR cert.expiry_date IS NOT NULL)',
    ];
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

    $whereSql = implode(' AND ', $where);
    $sql = "
        SELECT cert.id, cert.certificate_number, cert.status, cert.cycle_stage,
               cert.issue_date, cert.surveillance_1_date, cert.surveillance_2_date, cert.expiry_date,
               c.id AS client_id, c.company_name, c.industry_sector,
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
    $all = $stmt->fetchAll();

    $counts = ['overdue' => 0, 'near' => 0, 'far' => 0];
    $bucketed = ['overdue' => [], 'near' => [], 'far' => [], 'later' => []];

    foreach ($all as $cert) {
        $next = cm_certification_next_due($cert, $today);
        if ($next['date'] === null) continue; // shouldn't happen given the WHERE clause, but be defensive

        $daysUntil = (strtotime($next['date']) - strtotime($today)) / 86400;

        if ($next['overdue']) {
            $b = 'overdue';
        } elseif ($daysUntil <= $t1) {
            $b = 'near';
        } elseif ($daysUntil >= $t2 && $daysUntil <= $t3) {
            $b = 'far';
        } else {
            $b = 'later'; // between t1 and t2, or beyond t3 — not shown in a widget, but not dropped from "all"
        }

        $cert['next_due'] = $next;
        $cert['expiry_badge'] = cm_expiry_badge($cert['expiry_date'], $today);
        $bucketed[$b][] = $cert;
        if (isset($counts[$b])) $counts[$b]++;
    }

    if ($bucket !== '' && isset($bucketed[$bucket])) {
        $selected = $bucketed[$bucket];
    } else {
        $selected = array_merge($bucketed['overdue'], $bucketed['near'], $bucketed['far'], $bucketed['later']);
    }
    usort($selected, fn($a, $b2) => strcmp($a['next_due']['date'] ?? '9999-99-99', $b2['next_due']['date'] ?? '9999-99-99'));
    $selected = array_slice($selected, 0, 500);

    cm_json_response([
        'thresholds' => [$t1, $t2, $t3],
        'counts' => $counts,
        'certifications' => $selected,
    ]);
}

cm_json_error('Method not allowed.', 405);