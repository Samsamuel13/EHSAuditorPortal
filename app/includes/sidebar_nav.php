<?php
/**
 * includes/sidebar_nav.php
 *
 * Top bar with 3 app switchers (Auditor Portal / Client Management / CRM)
 * + a left sidebar that shows ONLY the currently active app's navigation
 * — not all three stacked. Clicking a different app in the top bar
 * navigates to that app's home page.
 *
 * This is a deliberate, small exception to the "only auth.php/db.php are
 * shared" isolation rule — flagged here rather than added silently: it's
 * a pure presentational partial (nav links only, no business logic, no
 * direct DB queries), so it doesn't cross any actual data-isolation
 * boundary between cm_/crm_/scheduler schemas — but it IS a new shared
 * file, worth knowing about.
 *
 * USAGE — the including page must already have $user in scope (from
 * ehs_require_role()/ehs_require_login()) and set $activePage. This
 * include outputs the topbar AND opens <div class="app-shell"><aside>...
 * — the calling page does NOT open its own app-shell div; it just adds
 * <main class="app-main"> after this include and </div> after its own
 * closing </main>:
 *
 *   <body>
 *       <?php $activePage = 'renewal_dashboard'; require __DIR__ . '/../includes/sidebar_nav.php'; ?>
 *       <main class="app-main page">
 *           ... existing page content ...
 *       </main>
 *       </div>
 *   </body>
 *
 * $activePage values recognized (add more as pages adopt this):
 *   Scheduler: schedule, day_schedule, auditors, clients, schemes, holidays,
 *              export, activity_log, users, profile
 *   Client Management: cm_index, cm_client_detail, renewal_dashboard,
 *              audit_extract, cm_import
 *   CRM: crm_board, crm_list, crm_lead_detail
 *   Invoices: inv_home, inv_parties, inv_settings  (super_admin only)
 */

$role = $user['role'] ?? 'auditor';
$isAdmin = in_array($role, ['super_admin', 'admin'], true);
$activePage = $activePage ?? '';

$cmPages = ['cm_index', 'cm_client_detail', 'renewal_dashboard', 'audit_extract', 'cm_import'];
$crmPages = ['crm_board', 'crm_list', 'crm_lead_detail'];
$navInvPages = ['inv_home', 'inv_parties', 'inv_settings'];
$navInvVisible = $role === 'super_admin';
$currentApp = in_array($activePage, $cmPages, true) ? 'cm' : (in_array($activePage, $crmPages, true) ? 'crm' : 'scheduler');
if ($navInvVisible && in_array($activePage, $navInvPages, true)) {
    $currentApp = 'inv';
}

$schedulerHome = $isAdmin ? ehs_url('admin/index.php') : ehs_url('auditor/index.php');
?>
<style>
    .app-topbar {
        display: flex; align-items: center; gap: 4px; background: #1b2559; color: #fff;
        padding: 0 16px; height: 52px; position: sticky; top: 0; z-index: 20;
    }
    .app-topbar-brand { display: flex; align-items: center; gap: 8px; margin-right: 18px; }
    .app-topbar-brand img { height: 24px; }
    .app-topbar-brand span { font-weight: 700; font-size: 0.92rem; white-space: nowrap; }
    .app-switcher-btn {
        color: #b9c0e8; text-decoration: none; font-size: 0.85rem; font-weight: 600;
        padding: 8px 14px; border-radius: 6px; white-space: nowrap;
    }
    .app-switcher-btn:hover { color: #fff; background: rgba(255,255,255,0.08); }
    .app-switcher-btn.active { color: #fff; background: rgba(255,255,255,0.16); }
    .app-topbar-user { margin-left: auto; font-size: 0.82rem; color: #b9c0e8; white-space: nowrap; }
    .app-topbar-user a { color: #b9c0e8; text-decoration: none; margin-left: 10px; }
    .app-topbar-user a:hover { color: #fff; }

    .app-shell { display: flex; min-height: calc(100vh - 52px); }
    .app-sidebar {
        width: 220px; flex: 0 0 220px; background: #f4f5fa; border-right: 1px solid #e2e4ee;
        position: sticky; top: 52px; height: calc(100vh - 52px); overflow-y: auto; padding: 14px 0;
    }
    .app-sidebar-title { padding: 4px 16px 10px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #9095a8; }
    .app-sidebar a {
        display: block; padding: 9px 16px; color: #374151; text-decoration: none; font-size: 0.88rem;
        border-left: 3px solid transparent;
    }
    .app-sidebar a:hover { background: #eceefa; }
    .app-sidebar a.active { background: #e4e7fb; color: #283891; font-weight: 600; border-left-color: #283891; }
    .app-main { flex: 1 1 auto; min-width: 0; }

    /* Defensive reset: the old topbar was position:fixed, so existing page
       CSS likely reserves top padding/margin to keep content from hiding
       underneath it (a common pattern with fixed headers). The new topbar
       is position:sticky instead — it doesn't remove itself from normal
       document flow, so that old reserved space is no longer needed and
       was stacking on top of the new layout as a big empty gap. Forcing
       these back to zero here, rather than needing to track down and edit
       the exact original rule in files I haven't seen. !important is used
       deliberately, to win regardless of the old rule's specificity/order.
    */
    body, .page, .page-wide, .app-main, .container-fluid {
        padding-top: 0 !important; margin-top: 0 !important; min-height: 0 !important;
    }

    .app-mobile-toggle { display: none; background: none; border: none; color: #fff; font-size: 1.3rem; padding: 4px 10px; cursor: pointer; margin-left: auto; }
    @media (max-width: 820px) {
        .app-switcher-btn span.label { display: none; }
        .app-topbar-user { display: none; }
        .app-shell { flex-direction: column; }
        .app-sidebar { width: 100%; flex: 0 0 auto; height: auto; position: relative; top: 0; }
        .app-sidebar.nav-collapsed { display: none; }
        .app-mobile-toggle { display: block; }
    }
</style>

<header class="app-topbar">
    <div class="app-topbar-brand">
        <img src="<?= ehs_url('assets/img/logo.png') ?>" alt="EHS Universal">
        <span>EHS Universal</span>
    </div>
    <nav style="display:flex; gap:2px;">
        <a class="app-switcher-btn <?= $currentApp === 'scheduler' ? 'active' : '' ?>" href="<?= $schedulerHome ?>">🗓️ Auditor Portal</a>
        <?php if ($isAdmin): ?>
        <a class="app-switcher-btn <?= $currentApp === 'cm' ? 'active' : '' ?>" href="<?= ehs_url('client-management/index.php') ?>">📋 Client Management</a>
        <a class="app-switcher-btn <?= $currentApp === 'crm' ? 'active' : '' ?>" href="<?= ehs_url('crm/index.php') ?>">🎯 CRM</a>
        <?php endif; ?>
        <?php if ($navInvVisible): ?>
        <a class="app-switcher-btn <?= $currentApp === 'inv' ? 'active' : '' ?>" href="<?= ehs_url('invoice-system/index.php') ?>" title="Invoices">🧾 <span class="label">Invoices</span></a>
        <?php endif; ?>
    </nav>
    <button class="app-mobile-toggle" id="app-sidebar-toggle" aria-label="Toggle navigation">☰</button>
    <div class="app-topbar-user">
        <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($role) ?>)
        <a href="<?= ehs_url('logout.php') ?>">Log out</a>
    </div>
</header>

<div class="app-shell">
    <aside class="app-sidebar" id="app-sidebar">
        <?php if ($currentApp === 'scheduler'): ?>
            <div class="app-sidebar-title">Auditor Portal</div>
            <a href="<?= $isAdmin ? ehs_url('admin/schedule.php') : ehs_url('auditor/index.php') ?>" class="<?= in_array($activePage, ['schedule']) ? 'active' : '' ?>">Master Schedule</a>
            <a href="<?= $isAdmin ? ehs_url('admin/day_schedule.php') : ehs_url('auditor/day_schedule.php') ?>" class="<?= $activePage === 'day_schedule' ? 'active' : '' ?>">My Day Schedule</a>
            <?php if ($isAdmin): ?>
            <a href="<?= ehs_url('admin/auditor_availability.php') ?>" class="<?= $activePage === 'auditor_availability' ? 'active' : '' ?>">Auditor Availability</a>
            <a href="<?= ehs_url('admin/auditors.php') ?>" class="<?= $activePage === 'auditors' ? 'active' : '' ?>">Auditors</a>
            <a href="<?= ehs_url('admin/clients.php') ?>" class="<?= $activePage === 'clients' ? 'active' : '' ?>">Clients (Scheduler)</a>
            <a href="<?= ehs_url('admin/schemes.php') ?>" class="<?= $activePage === 'schemes' ? 'active' : '' ?>">Schemes</a>
            <a href="<?= ehs_url('admin/holidays.php') ?>" class="<?= $activePage === 'holidays' ? 'active' : '' ?>">Holidays</a>
            <a href="<?= ehs_url('admin/export.php') ?>" class="<?= $activePage === 'export' ? 'active' : '' ?>">Export</a>
            <a href="<?= ehs_url('admin/activity_log.php') ?>" class="<?= $activePage === 'activity_log' ? 'active' : '' ?>">Activity Log</a>
            <a href="<?= ehs_url('admin/users.php') ?>" class="<?= $activePage === 'users' ? 'active' : '' ?>">User Accounts</a>
            <?php else: ?>
            <a href="<?= ehs_url('auditor/availability.php') ?>" class="<?= $activePage === 'availability' ? 'active' : '' ?>">My Availability</a>
            <?php endif; ?>
            <a href="<?= $isAdmin ? ehs_url('admin/profile.php') : ehs_url('auditor/profile.php') ?>" class="<?= $activePage === 'profile' ? 'active' : '' ?>">My Account</a>

        <?php elseif ($currentApp === 'cm'): ?>
            <div class="app-sidebar-title">Client Management</div>
            <a href="<?= ehs_url('client-management/index.php') ?>" class="<?= $activePage === 'cm_index' ? 'active' : '' ?>">Client Directory</a>
            <a href="<?= ehs_url('client-management/renewal_dashboard.php') ?>" class="<?= $activePage === 'renewal_dashboard' ? 'active' : '' ?>">Renewal Dashboard</a>
            <a href="<?= ehs_url('client-management/audit_extract.php') ?>" class="<?= $activePage === 'audit_extract' ? 'active' : '' ?>">Audit Extract</a>
            <a href="<?= ehs_url('client-management/import.php') ?>" class="<?= $activePage === 'cm_import' ? 'active' : '' ?>">Bulk Import</a>

        <?php elseif ($currentApp === 'crm'): ?>
            <div class="app-sidebar-title">CRM</div>
            <a href="<?= ehs_url('crm/index.php') ?>" class="<?= $activePage === 'crm_board' ? 'active' : '' ?>">Board</a>
            <a href="<?= ehs_url('crm/list.php') ?>" class="<?= $activePage === 'crm_list' ? 'active' : '' ?>">List</a>

        <?php elseif ($currentApp === 'inv'): ?>
            <div class="app-sidebar-title">Invoices</div>
            <a href="<?= ehs_url('invoice-system/index.php') ?>" class="<?= $activePage === 'inv_home' ? 'active' : '' ?>">Overview</a>
            <a href="<?= ehs_url('invoice-system/parties.php') ?>" class="<?= $activePage === 'inv_parties' ? 'active' : '' ?>">Billed Parties</a>
            <a href="<?= ehs_url('invoice-system/settings.php') ?>" class="<?= $activePage === 'inv_settings' ? 'active' : '' ?>">Settings</a>
        <?php endif; ?>
    </aside>

<script>
(function () {
    var toggle = document.getElementById('app-sidebar-toggle');
    if (toggle) {
        toggle.addEventListener('click', function () {
            document.getElementById('app-sidebar').classList.toggle('nav-collapsed');
        });
    }
})();
</script>