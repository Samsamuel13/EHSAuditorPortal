<?php
// File: includes/export_data.php
/**
 * Shared month-grid data builder for /api/export_xlsx.php and /api/export_pdf.php.
 * Returns everything needed to render "rows = dates, columns = auditors",
 * mirroring the original Excel planner's layout.
 */

function ehs_build_month_grid(PDO $db, string $monthStart, string $monthEnd): array
{
    // --- auditors (columns), active only, with their approved scheme codes ---
    $auditorStmt = $db->query(
        "SELECT u.id, u.name, u.color_hex,
                GROUP_CONCAT(s.code ORDER BY s.code SEPARATOR ', ') AS scheme_codes
         FROM users u
         LEFT JOIN auditor_schemes aus ON aus.auditor_id = u.id
         LEFT JOIN schemes s ON s.id = aus.scheme_id
         WHERE u.role IN ('super_admin','auditor') AND u.status = 'active'
         GROUP BY u.id, u.name, u.color_hex
         ORDER BY u.name"
    );
    $auditors = $auditorStmt->fetchAll();
    foreach ($auditors as &$a) {
        $a['id'] = (int) $a['id'];
        $a['scheme_codes'] = $a['scheme_codes'] ?? '';
    }
    unset($a);

    // --- holidays in range ---
    $holidayStmt = $db->prepare('SELECT date, name FROM holidays WHERE date >= :start AND date < :end');
    $holidayStmt->execute(['start' => $monthStart, 'end' => $monthEnd]);
    $holidaysByDate = [];
    foreach ($holidayStmt->fetchAll() as $row) {
        $holidaysByDate[$row['date']] = $row['name'];
    }

    // --- days (rows) ---
    $days = [];
    $cursor = new DateTime($monthStart);
    $endDt = new DateTime($monthEnd);
    while ($cursor < $endDt) {
        $dateStr = $cursor->format('Y-m-d');
        $dow = (int) $cursor->format('w');
        $days[] = [
            'date'         => $dateStr,
            'weekday_name' => $cursor->format('l'),
            'is_weekend'   => $dow === 0 || $dow === 6,
            'holiday_name' => $holidaysByDate[$dateStr] ?? null,
        ];
        $cursor->modify('+1 day');
    }

    // --- audits in range, with client/schemes/auditors ---
    $stmt = $db->prepare(
        'SELECT a.id, a.audit_date, a.session, a.status, c.name AS client_name
         FROM audits a
         JOIN clients c ON c.id = a.client_id
         WHERE a.audit_date >= :start AND a.audit_date < :end AND a.status != "cancelled"
         ORDER BY a.audit_date, a.session'
    );
    $stmt->execute(['start' => $monthStart, 'end' => $monthEnd]);
    $audits = $stmt->fetchAll();

    $cells = []; // [date][auditor_id] = [ 'ClientName (SCHEME) — Session', ... ]

    if ($audits) {
        $ids = array_map('intval', array_column($audits, 'id'));
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

        $auditorStmt2 = $db->prepare(
            "SELECT aa.audit_id, aa.auditor_id FROM audit_auditors aa WHERE aa.audit_id IN ($inClause)"
        );
        $auditorStmt2->execute($ids);
        $auditorIdsByAudit = [];
        foreach ($auditorStmt2->fetchAll() as $row) {
            $auditorIdsByAudit[$row['audit_id']][] = (int) $row['auditor_id'];
        }

        foreach ($audits as $audit) {
            $schemeLabel = implode('/', $schemesByAudit[$audit['id']] ?? []);
            $sessionLabel = $audit['session'] === 'FULL_DAY' ? '' : (' ' . $audit['session']);
            $text = trim($audit['client_name'] . ($schemeLabel !== '' ? " ($schemeLabel)" : '') . $sessionLabel);

            foreach ($auditorIdsByAudit[$audit['id']] ?? [] as $auditorId) {
                $cells[$audit['audit_date']][$auditorId][] = $text;
            }
        }
    }

    return [
        'auditors'        => $auditors,
        'days'            => $days,
        'cells'           => $cells,
    ];
}
