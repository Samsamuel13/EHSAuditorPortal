<?php
/**
 * invoice-system/api/env_check.php — READ-ONLY environment check (GET only).
 *
 * Reports: database server type/version, whether CHECK constraints are
 * enforced, sql_mode, DB vs PHP clock, the collation of every inv_ table
 * and text column (and warns when any differs from the rest), the engine of
 * every inv_ table, the users / cm_clients collations for reference,
 * migration status, PDF storage, Dompdf and GD.
 * Executes only SELECT statements and filesystem stat calls. Changes nothing.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/inv_helpers.php';
require_once __DIR__ . '/../includes/inv_invoice.php';

$user = ehs_require_role(INV_ROLES, true);
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    inv_json_error('Method not allowed.', 405);
}

$warn = [];
$info = [];

// --- server ---------------------------------------------------------------
$row = $db->query('SELECT VERSION() AS v, @@sql_mode AS m, NOW() AS db_now, @@session.time_zone AS tz')->fetch();
$version = (string) $row['v'];
$isMaria = stripos($version, 'mariadb') !== false;
preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $vm);
$vnum = $vm ? ((int) $vm[1]) * 10000 + ((int) $vm[2]) * 100 + (int) $vm[3] : 0;
$checksEnforced = $isMaria ? $vnum >= 100201 : $vnum >= 80016;
if (!$checksEnforced) {
    $warn[] = 'This server does not enforce CHECK constraints: the module still enforces every rule in PHP, but the database safety net is off.';
}
$strict = stripos((string) $row['m'], 'STRICT_TRANS_TABLES') !== false || stripos((string) $row['m'], 'STRICT_ALL_TABLES') !== false;
if (!$strict) {
    $warn[] = 'sql_mode is not strict: invalid values could be silently truncated by the database (the module validates all input in PHP).';
}
$drift = abs(strtotime((string) $row['db_now']) - time());
if ($drift > 120) {
    $info[] = 'The database clock (session time zone ' . $row['tz'] . ') differs from PHP (' . date_default_timezone_get() . ') by '
        . round($drift / 60) . ' min. Dates such as "today" and "overdue" use the PHP time zone; issued_at timestamps use the database clock.';
}

// --- inv_ tables and columns ---------------------------------------------
$t = $db->prepare(
    "SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE :t_like ORDER BY TABLE_NAME"
);
$t->execute(['t_like' => 'inv\_%']);
$tables = $t->fetchAll();

$c = $db->prepare(
    "SELECT TABLE_NAME, COLUMN_NAME, COLLATION_NAME, DATA_TYPE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE :c_like AND COLLATION_NAME IS NOT NULL
     ORDER BY TABLE_NAME, ORDINAL_POSITION"
);
$c->execute(['c_like' => 'inv\_%']);
$columns = $c->fetchAll();

$counts = [];
foreach ($tables as $tb) $counts[$tb['TABLE_COLLATION']] = ($counts[$tb['TABLE_COLLATION']] ?? 0) + 1;
foreach ($columns as $col) {
    if (substr((string) $col['COLLATION_NAME'], -4) === '_bin' && $col['COLUMN_NAME'] === 'entity_snapshot') continue;
    $counts[$col['COLLATION_NAME']] = ($counts[$col['COLLATION_NAME']] ?? 0) + 1;
}
arsort($counts);
$main = $counts ? array_key_first($counts) : null;

$odd = [];
foreach ($tables as $tb) {
    if ($tb['TABLE_COLLATION'] !== $main) $odd[] = $tb['TABLE_NAME'] . ' (table default ' . $tb['TABLE_COLLATION'] . ')';
    if (strtoupper((string) $tb['ENGINE']) !== 'INNODB') {
        $warn[] = $tb['TABLE_NAME'] . ' uses engine ' . $tb['ENGINE'] . ': row locks and foreign keys need InnoDB.';
    }
}
// MariaDB implements JSON as LONGTEXT with a json_valid() CHECK and forces a
// *_bin collation on it. That is expected, harmless (the module never sorts
// or compares JSON text) and is reported as information, not as a mismatch.
$jsonCols = ['inv_invoices.entity_snapshot', 'inv_credit_notes.entity_snapshot'];
$jsonBin = [];
foreach ($columns as $col) {
    $name = $col['TABLE_NAME'] . '.' . $col['COLUMN_NAME'];
    if ($col['COLLATION_NAME'] === $main) continue;
    if (in_array($name, $jsonCols, true) && strtolower((string) $col['DATA_TYPE']) === 'longtext'
        && substr((string) $col['COLLATION_NAME'], -4) === '_bin') {
        $jsonBin[] = $name;
        continue;
    }
    $odd[] = $name . ' (' . $col['COLLATION_NAME'] . ')';
}
if ($jsonBin) {
    $info[] = 'JSON columns ' . implode(', ', $jsonBin) . ' use a binary collation: this is how MariaDB stores JSON (LONGTEXT + json_valid). Expected; not a mismatch.';
}
// The main collation is decided by tables and ordinary columns only.

if ($odd) {
    $warn[] = 'Collation mismatch inside the module: ' . implode(', ', $odd) . ' differ(s) from ' . $main . '.';
}
$expected = ['inv_activity_log', 'inv_categories', 'inv_config', 'inv_credit_note_lines', 'inv_credit_notes', 'inv_invoice_lines',
    'inv_invoices', 'inv_line_audited_entities', 'inv_number_series', 'inv_parties', 'inv_payments', 'inv_reminders_log',
    'inv_services', 'inv_settings'];
$missing = array_diff($expected, array_column($tables, 'TABLE_NAME'));
if ($missing) $warn[] = 'Missing inv_ tables: ' . implode(', ', $missing) . '.';

// --- reference: other modules' collations (read-only) ------------------------
$r = $db->prepare(
    "SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('users', 'cm_clients')"
);
$r->execute();
$reference = $r->fetchAll();
foreach ($reference as $ref) {
    if ($main !== null && $ref['TABLE_COLLATION'] !== $main) {
        $info[] = $ref['TABLE_NAME'] . ' uses ' . $ref['TABLE_COLLATION'] . ' (inv_ uses ' . $main
            . '). The module never joins or compares text across them; any future query that does must add an explicit COLLATE.';
    }
}

// --- migration / options ----------------------------------------------------
$st = $db->prepare(
    "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_invoices' AND COLUMN_NAME = 'status'"
);
$st->execute();
$statusType = (string) $st->fetchColumn();
$mig3 = strpos($statusType, "'credited'") !== false;
if (!$mig3) $warn[] = 'Migration 003 not applied: run invoice-system/db/inv_migration_003.sql.';

// --- files / extensions -------------------------------------------------------
$storage = inv_storage_status($db);
foreach ($storage['problems'] as $p) $warn[] = 'Storage: ' . $p;
$dompdfVersion = null;
$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
    if (class_exists('\Composer\InstalledVersions') && \Composer\InstalledVersions::isInstalled('dompdf/dompdf')) {
        $dompdfVersion = \Composer\InstalledVersions::getPrettyVersion('dompdf/dompdf');
    }
} else {
    $warn[] = 'Dompdf not found (vendor/autoload.php missing).';
}
if (!extension_loaded('gd')) $warn[] = 'PHP GD extension missing: PNG logos cannot be placed in PDFs.';

inv_json_response([
    'server' => [
        'version' => $version, 'type' => $isMaria ? 'MariaDB' : 'MySQL',
        'check_constraints_enforced' => $checksEnforced, 'sql_mode' => $row['m'], 'strict' => $strict,
        'db_time_zone' => $row['tz'], 'php_time_zone' => date_default_timezone_get(), 'php_version' => PHP_VERSION,
    ],
    'collation' => ['module' => $main, 'tables' => $tables, 'odd' => $odd, 'reference' => $reference],
    'migration_003' => $mig3,
    'pdf' => ['page_size' => inv_config_get($db, 'pdf_page_size', null), 'dompdf' => $dompdfVersion, 'gd' => extension_loaded('gd')],
    'storage' => ['path' => $storage['path'], 'source' => $storage['source'], 'in_web_root' => $storage['in_docroot'], 'ok' => !$storage['problems']],
    'warnings' => $warn,
    'info' => $info,
]);
