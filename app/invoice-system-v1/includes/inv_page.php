<?php
/**
 * invoice-system/includes/inv_page.php
 *
 * Page shell for this module's HTML pages. Same structure as the CRM pages
 * (Bootstrap 5.3.3 + Bootstrap Icons from jsdelivr, CSRF meta tag, shared
 * includes/sidebar_nav.php), kept in one place so every page gets the same
 * head, nav, toast and script setup. Bootstrap native only: no custom theme.
 *
 * Module tab bar: Stage 3 adds Invoices and Services pages WITHOUT touching
 * includes/sidebar_nav.php (owner instruction: no edits to existing nav
 * lines). The shared sidebar therefore only knows inv_home / inv_parties /
 * inv_settings; the new pages pass 'inv_home' for the sidebar and show their
 * own active tab in this module-local tab bar.
 *
 * Usage (after ehs_require_role(INV_ROLES)):
 *   inv_page_start('Invoices', 'inv_home', $user, 'invoices');
 *   ... page body ...
 *   inv_page_end(['invoice-system/assets/js/inv_invoices.js']);
 */

const INV_TABS = [
    'overview' => ['Overview', 'invoice-system/index.php', 'bi-speedometer2'],
    'invoices' => ['Invoices', 'invoice-system/invoices.php', 'bi-receipt'],
    'parties'  => ['Billed Parties', 'invoice-system/parties.php', 'bi-people'],
    'services' => ['Services', 'invoice-system/services.php', 'bi-list-check'],
    'settings' => ['Settings', 'invoice-system/settings.php', 'bi-gear'],
];

function inv_page_start(string $title, string $activePage, array $user, ?string $tab = null): void
{
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars(ehs_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
<title>Invoices — <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <?php
    // sidebar_nav.php reads $user and $activePage from the including scope.
    require __DIR__ . '/../../includes/sidebar_nav.php';
    ?>
    <main class="app-main container-fluid py-3">
    <div class="pt-3"><?php /* sidebar_nav.php forces padding-top:0 !important on .app-main; spacing lives here instead. */ ?>
    <?php if ($tab !== null): ?>
    <ul class="nav nav-pills small mb-3 flex-nowrap overflow-auto" aria-label="Invoice System sections">
        <?php foreach (INV_TABS as $key => [$label, $path, $icon]): ?>
        <li class="nav-item">
            <a class="nav-link py-1 px-2 text-nowrap<?= $key === $tab ? ' active' : '' ?>"<?= $key === $tab ? ' aria-current="page"' : '' ?>
               href="<?= htmlspecialchars(ehs_url($path), ENT_QUOTES, 'UTF-8') ?>"><i class="bi <?= $icon ?>"></i> <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <?php
}

function inv_page_end(array $scripts = []): void
{
    ?>
    </div>
    </main>
    </div>

    <div id="inv-toast" class="toast align-items-center text-white bg-dark border-0 position-fixed bottom-0 end-0 m-3" role="alert" aria-live="assertive" style="z-index:1080;">
        <div class="d-flex">
            <div class="toast-body" id="inv-toast-body"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>

    <script>
        window.EHS_BASE_URL = <?= json_encode(ehs_url(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        window.INV_CSRF_TOKEN = <?= json_encode(ehs_csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= htmlspecialchars(ehs_url('invoice-system/assets/js/inv_common.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php foreach ($scripts as $s): ?>
    <script src="<?= htmlspecialchars(ehs_url($s), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endforeach; ?>
</body>
</html>
    <?php
}
