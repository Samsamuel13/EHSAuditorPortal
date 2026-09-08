<?php
// File: admin/export.php
require_once __DIR__ . '/../includes/auth.php';
$user = ehs_require_role(['super_admin', 'admin']);

$defaultMonth = date('Y-m');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Export — <?= htmlspecialchars(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= ehs_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/management.css') ?>">
</head>
<body>
            <?php $activePage = 'export'; require __DIR__ . '/../includes/sidebar_nav.php'; ?>
    <div class="back-nav">
        <button onclick="history.back()" class="btn btn-ghost-light btn-small back-btn">&larr; Back</button>
    </div>

        <main class="app-main page">
        <h1>Export monthly schedule</h1>
        <p class="field-hint">
            Exports the full month grid (every active auditor, every audit) —
            the same shape as the original Excel planner, plus color-coded
            weekends and holidays.
        </p>

        <div class="mgmt-table-wrap" style="padding: 24px;">
            <label for="month-input">Month</label>
            <input type="month" id="month-input" value="<?= htmlspecialchars($defaultMonth) ?>" style="max-width:200px;">

            <div style="margin-top:20px; display:flex; gap:10px;">
                <a id="xlsx-link" href="#" class="btn btn-primary btn-small" style="width:auto;">⬇ Export to Excel (.xlsx)</a>
                <a id="pdf-link" href="#" class="btn btn-ghost-light btn-small" style="width:auto;">⬇ Export to PDF</a>
            </div>
        </div>
    </main>
    </div>

    <script>window.EHS_BASE_URL = "<?= addslashes(ehs_url()) ?>";</script>
    <script src="<?= ehs_url('assets/js/session_guard.js') ?>"></script>
    <script src="<?= ehs_url('assets/js/export.js') ?>"></script>
</body>
</html>