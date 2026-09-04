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
    // Live auto-expire: keeps the Status column truthful on every load, not
    // just after a daily cron run. Cheap single UPDATE, safe to run per-request.
    cm_auto_expire_overdue_certifications($db);

    $today = date('Y-m-d');
    [$t1, $t2, $t3] = cm_get_renewal_thresholds($db);

    $q              = trim((string) ($_GET['q'] ?? ''));
    $schemeCategory = trim((string) ($_GET['scheme_category'] ?? ''));
    $industry       = trim((string) ($_GET['industry'] ?? ''));
    $entity         = trim((string) ($_GET['entity'] ?? ''));
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
    if ($entity !== '' && in_array($entity, CM_ENTITIES, true)) {
        $where[] = 'c.entity = :entity';
        $params['entity'] = $entity;
    }
    if ($responsibleId > 0) {
        $where[] = 'cert.responsible_person_id = :responsible_id';
        $params['responsible_id'] = $responsibleId;
    }

    $whereSql = implode(' AND ', $where);
    $sql = "
        SELECT cert.id, cert.certificate_number, cert.status, cert.cycle_stage,
               cert.issue_date, cert.surveillance_1_date, cert.surveillance_2_date, cert.expiry_date,
               cert.surveillance_1_completed_at, cert.surveillance_2_completed_at, cert.recertification_completed_at,
               c.id AS client_id, c.company_name, c.industry_sector, c.entity, c.email AS client_email,
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
    // Secondary sort by client_id (after next_due date) so a client with
    // several certifications due on the exact same date — the common case,
    // since they're usually all opened together — always ends up adjacent
    // in the results. That adjacency is what lets the dashboard merge them
    // into one visual row group instead of repeating the client 3 times.
    usort($selected, function ($a, $b2) {
        $dateCmp = strcmp($a['next_due']['date'] ?? '9999-99-99', $b2['next_due']['date'] ?? '9999-99-99');
        return $dateCmp !== 0 ? $dateCmp : ($a['client_id'] <=> $b2['client_id']);
    });
    $selected = array_slice($selected, 0, 500);

    // --- Stats cards ---
    // Milestone-done counts read from the ACTIVITY LOG
    // (cm_client_followup_notes.milestone_completed), NOT the
    // certification's live surveillance_*_completed_at columns — those get
    // reset to NULL the instant a Recertification rollover happens (the new
    // cycle's milestones start "not done" again), so the log is the only
    // durable record of "how many completions happened in a given period."
    // Filtered by entity/scheme/industry/responsible — deliberately NOT by
    // urgency bucket: a completed milestone usually resolves the urgency
    // that put a cert in a bucket, so bucket-filtering these would make
    // them read as near-permanently zero.
    $statsWhere = ['n.milestone_completed IS NOT NULL', 'n.created_at >= :stats_month_start'];
    $statsParams = ['stats_month_start' => date('Y-m-01')];
    if ($schemeCategory !== '' && in_array($schemeCategory, ['ISO', 'BizSafe', 'JASANZ', 'Other'], true)) {
        $statsWhere[] = 'st2.category = :stats_scheme_category';
        $statsParams['stats_scheme_category'] = $schemeCategory;
    }
    if ($industry !== '') {
        $statsWhere[] = 'c2.industry_sector = :stats_industry';
        $statsParams['stats_industry'] = $industry;
    }
    if ($entity !== '' && in_array($entity, CM_ENTITIES, true)) {
        $statsWhere[] = 'c2.entity = :stats_entity';
        $statsParams['stats_entity'] = $entity;
    }
    if ($responsibleId > 0) {
        $statsWhere[] = 'cert2.responsible_person_id = :stats_responsible_id';
        $statsParams['stats_responsible_id'] = $responsibleId;
    }
    $statsStmt = $db->prepare(
        'SELECT n.milestone_completed, COUNT(*) AS cnt
         FROM cm_client_followup_notes n
         JOIN cm_certifications cert2 ON cert2.id = n.cm_certification_id
         JOIN cm_clients c2 ON c2.id = n.cm_client_id
         JOIN cm_scheme_types st2 ON st2.id = cert2.cm_scheme_type_id
         WHERE ' . implode(' AND ', $statsWhere) . '
         GROUP BY n.milestone_completed'
    );
    $statsStmt->execute($statsParams);
    $milestonesDone = ['surveillance_1' => 0, 'surveillance_2' => 0, 'recertification' => 0];
    foreach ($statsStmt->fetchAll() as $row) {
        if (isset($milestonesDone[$row['milestone_completed']])) {
            $milestonesDone[$row['milestone_completed']] = (int) $row['cnt'];
        }
    }

    // Total activity logged this month (any type) — same filters, general
    // throughput number alongside the milestone-specific ones. LEFT JOINs
    // to cert/scheme since a client-level-only note (no certification
    // attached) has nothing to filter by scheme/responsible — it's simply
    // excluded when those filters are active, which is correct.
    $activityWhere = ['n.created_at >= :act_month_start'];
    $activityParams = ['act_month_start' => date('Y-m-01')];
    if ($schemeCategory !== '' && in_array($schemeCategory, ['ISO', 'BizSafe', 'JASANZ', 'Other'], true)) {
        $activityWhere[] = 'st3.category = :act_scheme_category';
        $activityParams['act_scheme_category'] = $schemeCategory;
    }
    if ($industry !== '') {
        $activityWhere[] = 'c3.industry_sector = :act_industry';
        $activityParams['act_industry'] = $industry;
    }
    if ($entity !== '' && in_array($entity, CM_ENTITIES, true)) {
        $activityWhere[] = 'c3.entity = :act_entity';
        $activityParams['act_entity'] = $entity;
    }
    if ($responsibleId > 0) {
        $activityWhere[] = 'cert3.responsible_person_id = :act_responsible_id';
        $activityParams['act_responsible_id'] = $responsibleId;
    }
    $activityStmt = $db->prepare(
        'SELECT COUNT(*) FROM cm_client_followup_notes n
         JOIN cm_clients c3 ON c3.id = n.cm_client_id
         LEFT JOIN cm_certifications cert3 ON cert3.id = n.cm_certification_id
         LEFT JOIN cm_scheme_types st3 ON st3.id = cert3.cm_scheme_type_id
         WHERE ' . implode(' AND ', $activityWhere)
    );
    $activityStmt->execute($activityParams);
    $activityLoggedThisMonth = (int) $activityStmt->fetchColumn();

    // Follow-up pending/done — Option A: derived from the activity log,
    // no new scheduling table. Scoped to exactly what's shown in the table
    // below ($selected — respects the bucket filter too, unlike the
    // milestone-done stats above), since "are the certs I'm currently
    // looking at being followed up" is genuinely bucket-relevant.
    // "Followed up" = a Log Activity entry on that certification within
    // the last $followupRecencyDays days. "Pending" = none.
    $followupRecencyDays = 14;
    $certIdsInView = array_column($selected, 'id');
    $recentActivityCertIds = [];
    if ($certIdsInView) {
        $inPlaceholders = implode(',', array_fill(0, count($certIdsInView), '?'));
        $recentStmt = $db->prepare(
            "SELECT DISTINCT cm_certification_id FROM cm_client_followup_notes
             WHERE cm_certification_id IN ($inPlaceholders) AND created_at >= ?"
        );
        $recentStmt->execute(array_merge($certIdsInView, [date('Y-m-d H:i:s', strtotime("-$followupRecencyDays days"))]));
        $recentActivityCertIds = array_column($recentStmt->fetchAll(), 'cm_certification_id');
    }
    $recentActivitySet = array_flip($recentActivityCertIds);
    $followedUpCount = 0;
    $pendingFollowupCount = 0;
    foreach ($selected as $cert) {
        if (isset($recentActivitySet[$cert['id']])) {
            $followedUpCount++;
        } else {
            $pendingFollowupCount++;
        }
    }

    cm_json_response([
        'thresholds' => [$t1, $t2, $t3],
        'counts' => $counts,
        'certifications' => $selected,
        'stats' => [
            'milestones_done_this_month' => $milestonesDone,
            'activity_logged_this_month' => $activityLoggedThisMonth,
            'followup_recency_days' => $followupRecencyDays,
            'followed_up_count' => $followedUpCount,
            'pending_followup_count' => $pendingFollowupCount,
        ],
    ]);
}

cm_json_error('Method not allowed.', 405);