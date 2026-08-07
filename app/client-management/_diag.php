<?php
// TEMPORARY DIAGNOSTIC — upload to client-management/_diag.php, visit it in
// your browser, paste me the full output, then DELETE this file afterward
// (it exposes server config details you don't want public long-term).

header('Content-Type: text/plain');
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "PHP version: " . PHP_VERSION . "\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "\n";

echo "--- Loading vendor/autoload.php ---\n";
$vendorPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorPath)) {
    echo "MISSING: $vendorPath does not exist\n";
} else {
    require_once $vendorPath;
    echo "OK — loaded from $vendorPath\n";
    echo "PhpSpreadsheet class exists: " . (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet') ? 'YES' : 'NO') . "\n";
}
echo "\n";

echo "--- Loading includes/auth.php ---\n";
try {
    require_once __DIR__ . '/../includes/auth.php';
    echo "OK\n";
} catch (\Throwable $e) {
    echo "FATAL: " . $e->getMessage() . " at " . $e->getFile() . ':' . $e->getLine() . "\n";
}
echo "\n";

echo "--- Loading client-management/includes/cm_helpers.php ---\n";
try {
    require_once __DIR__ . '/includes/cm_helpers.php';
    echo "OK\n";
    echo "cm_certification_next_due exists: " . (function_exists('cm_certification_next_due') ? 'YES' : 'NO') . "\n";
    echo "cm_send_mail exists: " . (function_exists('cm_send_mail') ? 'YES' : 'NO') . "\n";
} catch (\Throwable $e) {
    echo "FATAL: " . $e->getMessage() . " at " . $e->getFile() . ':' . $e->getLine() . "\n";
}
echo "\n";

echo "--- Testing DB connection ---\n";
try {
    $db = get_db();
    echo "OK — connected\n";
    $cols = $db->query("SHOW COLUMNS FROM cm_certifications LIKE 'surveillance_1_date'")->fetchAll();
    echo "surveillance_1_date column exists: " . (count($cols) > 0 ? 'YES' : 'NO — migration not run!') . "\n";
    $cols2 = $db->query("SHOW COLUMNS FROM cm_certifications LIKE 'surveillance_2_date'")->fetchAll();
    echo "surveillance_2_date column exists: " . (count($cols2) > 0 ? 'YES' : 'NO — migration not run!') . "\n";
} catch (\Throwable $e) {
    echo "FATAL: " . $e->getMessage() . "\n";
}
echo "\n";

echo "--- Testing import.php syntax (does it parse without a fatal error?) ---\n";
$importCheck = @file_get_contents(__DIR__ . '/api/import.php');
if ($importCheck === false) {
    echo "Could not read api/import.php\n";
} else {
    echo "File read OK, " . strlen($importCheck) . " bytes\n";
}

echo "\n--- Done ---\n";