<?php
// File: client-management/renewal_dashboard.php
require_once __DIR__ . '/../includes/auth.php';

$user = ehs_require_role(['super_admin', 'admin']);
$isSuperAdmin = $user['role'] === 'super_admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars(ehs_csrf_token()) ?>">
<meta name="cm-is-super-admin" content="<?= $isSuperAdmin ? '1' : '0' ?>">
<title>Renewal Dashboard — Client Management</title>
<link rel="stylesheet" href="<?= ehs_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/calendar.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/dashboard.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/management.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('client-management/assets/css/cm.css') ?>">
<style>
    /* Scoped to this page only — a small self-contained modal so this
       feature doesn't depend on adding new classes to the shared cm.css. */
    .cm-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; z-index: 1000; }
    .cm-modal-overlay.hidden { display: none; }
    .cm-modal { background: #fff; border-radius: 8px; padding: 20px; width: 480px; max-width: 92vw; max-height: 80vh; overflow-y: auto; box-shadow: 0 8px 30px rgba(0,0,0,0.2); }
    .cm-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .cm-modal-header h3 { margin: 0; font-size: 1.05rem; }
    .cm-modal-close { background: none; border: none; font-size: 1.5rem; line-height: 1; cursor: pointer; color: #6b7280; }
    .cm-modal-close:hover { color: #111827; }
    .cm-note-entry { border-left: 2px solid #dfe1ea; padding: 6px 0 10px 12px; margin-bottom: 4px; }
    .cm-note-entry .cm-note-meta { font-size: 0.75rem; color: #6b7280; margin-bottom: 2px; }
    .cm-note-empty { color: #6b7280; font-size: 0.9rem; padding: 8px 0; }
    #notes-new-text { width: 100%; box-sizing: border-box; font-family: inherit; padding: 6px 8px; }
    .cm-days-overdue { color: #b91c1c; font-weight: 600; }
    .cm-days-soon { color: #b45309; font-weight: 600; }
    .cm-days-ok { color: #374151; }

    /* Log Activity form */
    .cm-log-field { margin-bottom: 14px; }
    .cm-log-field label { display: block; font-size: 0.72rem; letter-spacing: 0.04em; text-transform: uppercase; color: #6b7280; margin-bottom: 5px; font-weight: 600; }
    .cm-log-field select, .cm-log-field input[type="text"], .cm-log-field textarea {
        width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; font-size: 0.9rem;
    }
    .cm-log-field textarea { resize: vertical; }
    .cm-log-row { display: flex; gap: 12px; }
    .cm-log-row .cm-log-field { flex: 1; }
    .cm-status-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 6px; }
    .cm-btn-log { background: #16a34a; color: #fff; border: none; border-radius: 6px; padding: 9px 18px; font-weight: 600; cursor: pointer; }
    .cm-btn-log:hover { background: #15803d; }
    .cm-history-divider { border-top: 1px solid #e5e7eb; margin: 16px 0 12px; padding-top: 12px; font-size: 0.8rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }

    /* Blinking attention indicator for urgent rows/widgets */
    @keyframes cm-blink { 0%, 100% { opacity: 1; box-shadow: 0 0 0 0 currentColor; } 50% { opacity: 0.35; box-shadow: 0 0 6px 2px currentColor; } }
    .cm-blink-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 6px; vertical-align: middle; animation: cm-blink 1.1s ease-in-out infinite; }
    .cm-blink-red { background: #dc2626; color: #dc2626; }
    .cm-blink-amber { background: #d97706; color: #d97706; }
    /* Small blinking badge pinned to the top-right corner of a widget card */
    .cm-widget-blink { position: absolute; top: 10px; right: 12px; width: 12px; height: 12px; border-radius: 50%; background: #d97706; color: #d97706; animation: cm-blink 1.1s ease-in-out infinite; }
    .dash-card { position: relative; }

    /* Grouped rows: same client's multiple certifications share one
       Client/Entity cell (rowspan) — these rules keep the spanned cell
       vertically centered and lighten the border between grouped rows so
       they read as one block instead of unrelated rows. */
    .cm-group-cell { vertical-align: middle; }
    .cm-grouped-row td { border-top: 1px dashed #e5e7eb; }

    /* Contains the main certifications table's scroll to its own box
       instead of letting it stretch the whole page — with pagination at
       25/page this rarely needs to scroll internally at all, but caps it
       either way so the page height stays predictable. */
    .cm-scroll-table-wrap { max-height: 560px; overflow-y: auto; }
    .cm-scroll-table-wrap table thead th {
        position: sticky; top: 0; z-index: 1;
        background: #f8f9fc; /* opaque so rows don't show through while scrolling */
    }

    /* Monthly Stats tab (2026-09 feature) — scoped to this page only, same
       pattern as the rest of this <style> block. Additive: nothing above
       this comment was changed. */
    .cm-tabs { display: flex; gap: 4px; border-bottom: 2px solid #e5e7eb; margin-bottom: 16px; }
    .cm-tab-btn { background: none; border: none; padding: 10px 16px; font-weight: 600; font-size: 0.9rem; color: #6b7280; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; }
    .cm-tab-btn.active { color: var(--brand, #283891); border-bottom-color: var(--brand, #283891); }
    .cm-tab-panel.hidden { display: none; }
    .cm-stats-filter-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 16px; }
    .cm-stats-filter-bar select, .cm-stats-filter-bar input { padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.85rem; }
    .cm-stats-tiles { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
    .cm-stats-tile { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; }
    .cm-stats-tile .cm-stats-tile-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; color: #6b7280; margin-bottom: 4px; }
    .cm-stats-tile .cm-stats-tile-value { font-size: 1.6rem; font-weight: 700; color: #111827; }
    .cm-stats-section-title { font-size: 0.95rem; margin: 20px 0 8px; color: var(--muted, #6b7280); text-transform: uppercase; letter-spacing: 0.04em; }
    table.cm-breakdown-table { border-collapse: collapse; width: 100%; margin-bottom: 6px; }
    table.cm-breakdown-table th, table.cm-breakdown-table td { border: 1px solid #e5e7eb; padding: 6px 10px; text-align: left; font-size: 0.85rem; }
    table.cm-breakdown-table th { background: #f8f9fc; }
    .cm-due-overdue { color: #b91c1c; font-weight: 600; }
    .cm-due-pending { color: #b45309; font-weight: 600; }
    .cm-due-done { color: #16a34a; font-weight: 600; }

    /* Card-triggered inline breakdown/detail panels (2026-09 layout rework) */
    .cm-due-chips { display: flex; gap: 10px; margin-bottom: 6px; flex-wrap: wrap; }
    .cm-due-chip { background: #fff; border: 1px solid #d1d5db; border-radius: 20px; padding: 7px 14px; font-size: 0.82rem; cursor: pointer; color: #374151; }
    .cm-due-chip:hover, .cm-breakdown-trigger:hover, .cm-drill:hover { border-color: var(--brand, #283891); }
    .cm-due-chip.active, .cm-breakdown-trigger.active, .cm-drill.active { outline: 2px solid var(--brand, #283891); }
    #ms-breakdown-panel, #ms-detail-panel { margin: 16px 0; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; background: #fafbfd; }
    #ms-breakdown-subtabs { display: flex; gap: 6px; }
    #ms-breakdown-subtabs.hidden { display: none; }
    .cm-breakdown-subtab-btn { background: #fff; border: 1px solid #d1d5db; border-radius: 6px; padding: 5px 12px; font-size: 0.8rem; cursor: pointer; }
    .cm-breakdown-subtab-btn.active { background: var(--brand, #283891); color: #fff; border-color: var(--brand, #283891); }
    .cm-drill-row { cursor: pointer; }
    .cm-drill-row:hover td { background: #f0f2f8; }
    .cm-export-btns { display: flex; gap: 8px; margin: 14px 0; }
</style>
</head>
<body>
            <?php $activePage = 'renewal_dashboard'; require __DIR__ . '/../includes/sidebar_nav.php'; ?>
    <main class="app-main page">
        <h1>Renewal Dashboard</h1>

        <div class="cm-tabs">
            <button type="button" class="cm-tab-btn active" id="tab-btn-renewal">Renewal Dashboard</button>
            <button type="button" class="cm-tab-btn" id="tab-btn-monthly-stats">Monthly Stats</button>
        </div>

        <div id="tab-panel-renewal" class="cm-tab-panel">

        <div class="filter-bar">
            <input type="text" id="filter-client-name" placeholder="Search client name...">
            <select id="filter-scheme-category">
                <option value="">All scheme categories</option>
                <option value="ISO">ISO</option>
                <option value="BizSafe">BizSafe</option>
                <option value="JASANZ">JAS-ANZ</option>
                <option value="Other">Other</option>
            </select>
            <input type="text" id="filter-industry" placeholder="Industry sector">
            <select id="filter-entity">
                <option value="">All entities</option>
                <option value="EHS">EHS</option>
                <option value="Axiscert">Axiscert</option>
            </select>
            <select id="filter-responsible">
                <option value="">All responsible persons</option>
            </select>
            <button id="filter-clear" class="btn btn-ghost-light btn-small" style="width:auto;">Clear</button>
        </div>

        <div class="dash-grid" style="grid-template-columns: repeat(3, 1fr);">
            <div class="dash-card" id="widget-near" style="cursor:pointer;">
                <span id="widget-near-blink" class="cm-widget-blink hidden"></span>
                <h3 id="widget-near-title">Expiring soon</h3>
                <div class="stat-row"><span class="stat-number" id="count-near">—</span><span class="stat-label">certifications</span></div>
            </div>
            <div class="dash-card" id="widget-far" style="cursor:pointer;">
                <h3 id="widget-far-title">Expiring later</h3>
                <div class="stat-row"><span class="stat-number" id="count-far">—</span><span class="stat-label">certifications</span></div>
            </div>
            <div class="dash-card" id="widget-overdue" style="cursor:pointer;">
                <h3>Overdue / Expired</h3>
                <div class="stat-row"><span class="stat-number" id="count-overdue">—</span><span class="stat-label">certifications</span></div>
            </div>
        </div>

        <h2 style="font-size:0.95rem; margin-top:20px; color:var(--muted, #6b7280); text-transform:uppercase; letter-spacing:0.04em;">This Month — Milestones Completed</h2>
        <div class="dash-grid" style="grid-template-columns: repeat(4, 1fr);">
            <div class="dash-card">
                <h3>Surveillance 1</h3>
                <div class="stat-row"><span class="stat-number" id="stat-surv1-done">—</span><span class="stat-label">done</span></div>
            </div>
            <div class="dash-card">
                <h3>Surveillance 2</h3>
                <div class="stat-row"><span class="stat-number" id="stat-surv2-done">—</span><span class="stat-label">done</span></div>
            </div>
            <div class="dash-card">
                <h3>Recertification</h3>
                <div class="stat-row"><span class="stat-number" id="stat-recert-done">—</span><span class="stat-label">done</span></div>
            </div>
            <div class="dash-card">
                <h3>Total Activity</h3>
                <div class="stat-row"><span class="stat-number" id="stat-activity-total">—</span><span class="stat-label">logged</span></div>
            </div>
        </div>

        <h2 style="font-size:0.95rem; margin-top:20px; color:var(--muted, #6b7280); text-transform:uppercase; letter-spacing:0.04em;">Follow-up Status <span style="text-transform:none; font-weight:400;">(of what's shown below)</span></h2>
        <div class="dash-grid" style="grid-template-columns: repeat(2, 1fr);">
            <div class="dash-card">
                <h3>Followed Up</h3>
                <div class="stat-row"><span class="stat-number" id="stat-followed-up" style="color:#16a34a;">—</span><span class="stat-label" id="stat-followed-up-label">certifications</span></div>
            </div>
            <div class="dash-card">
                <h3>Pending Follow-up</h3>
                <div class="stat-row"><span class="stat-number" id="stat-pending-followup" style="color:#b45309;">—</span><span class="stat-label" id="stat-pending-followup-label">certifications</span></div>
            </div>
        </div>

        <?php if ($isSuperAdmin): ?>
        <div class="cm-section-placeholder" style="text-align:left; margin-top:16px;">
            <strong>Alert thresholds (days)</strong> — Super Admin only. Controls the widget/bucket boundaries above.
            <div style="display:flex; gap:10px; align-items:center; margin-top:10px;">
                <input type="number" id="t1" min="1" style="width:80px;">
                <span>/</span>
                <input type="number" id="t2" min="1" style="width:80px;">
                <span>/</span>
                <input type="number" id="t3" min="1" style="width:80px;">
                <button id="save-thresholds" class="btn btn-primary btn-small" style="width:auto;">Save thresholds</button>
            </div>
        </div>
        <?php endif; ?>

        <h2 style="font-size:1.05rem; margin-top:24px; display:flex; justify-content:space-between; align-items:center;">
            <span>📋 Today's Renewal Follow-ups</span>
            <span id="followup-today-count" class="badge" style="background:#fee2e2; color:#b91c1c;">—</span>
        </h2>
        <p style="color:var(--muted, #6b7280); margin-top:-8px; font-size:0.85rem;">
            Click a stage below to see who's due for it. Nothing shows until you pick one — with a large list, that's easier to work through than one long combined table.
        </p>

        <div class="dash-grid" style="grid-template-columns: repeat(4, 1fr);">
            <div class="dash-card followup-stage-card" data-stage="4" style="cursor:pointer; border-left:4px solid #b91c1c;">
                <h3>Stage 4 <span style="font-weight:400; font-size:0.75rem;">(4 days)</span></h3>
                <div class="stat-row"><span class="stat-number" id="stage-count-4">—</span><span class="stat-label">due</span></div>
            </div>
            <div class="dash-card followup-stage-card" data-stage="3" style="cursor:pointer; border-left:4px solid #b45309;">
                <h3>Stage 3 <span style="font-weight:400; font-size:0.75rem;">(30 days)</span></h3>
                <div class="stat-row"><span class="stat-number" id="stage-count-3">—</span><span class="stat-label">due</span></div>
            </div>
            <div class="dash-card followup-stage-card" data-stage="2" style="cursor:pointer; border-left:4px solid #7c3aed;">
                <h3>Stage 2 <span style="font-weight:400; font-size:0.75rem;">(2 months)</span></h3>
                <div class="stat-row"><span class="stat-number" id="stage-count-2">—</span><span class="stat-label">due</span></div>
            </div>
            <div class="dash-card followup-stage-card" data-stage="1" style="cursor:pointer; border-left:4px solid #2563eb;">
                <h3>Stage 1 <span style="font-weight:400; font-size:0.75rem;">(4 months)</span></h3>
                <div class="stat-row"><span class="stat-number" id="stage-count-1">—</span><span class="stat-label">due</span></div>
            </div>
        </div>

        <div id="followup-list-section" class="hidden" style="margin-top:14px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3 style="font-size:0.95rem; margin:0;" id="followup-list-title">Stage —</h3>
                <div>
                    <input type="text" id="followup-search" placeholder="Search company..." style="font-size:0.85rem; padding:4px 8px;">
                    <button id="followup-list-close" class="btn btn-ghost-light btn-small" style="width:auto; margin-left:6px;">Close</button>
                </div>
            </div>
            <div class="mgmt-table-wrap" style="margin-top:8px;">
                <table class="mgmt-table">
                    <thead><tr>
                        <th>Client</th>
                        <th>Entity</th>
                        <th>Milestone</th>
                        <th>Due</th>
                        <th>Responsible</th>
                        <th></th>
                    </tr></thead>
                    <tbody id="followup-today-body"></tbody>
                </table>
                <div id="followup-today-empty" class="mgmt-empty hidden">Nothing due for this stage right now.</div>
            </div>
            <div id="followup-pagination" style="display:flex; justify-content:center; align-items:center; gap:10px; margin-top:10px; font-size:0.85rem;"></div>
        </div>

        <h2 style="font-size:1.05rem; margin-top:24px;" id="list-title">All certifications with an expiry date</h2>
        <div class="mgmt-table-wrap cm-scroll-table-wrap">
            <table class="mgmt-table">
                <thead><tr>
                    <th>Client</th>
                    <th>Entity</th>
                    <th>Scheme</th>
                    <th>Cert #</th>
                    <th>Next Due</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th>Responsible</th>
                    <th>Follow-up</th>
                </tr></thead>
                <tbody id="results-body"></tbody>
            </table>
            <div id="results-empty-state" class="mgmt-empty hidden">No certifications match these filters.</div>
        </div>
        <div id="results-pagination" style="display:flex; justify-content:center; align-items:center; gap:10px; margin-top:10px; font-size:0.85rem;"></div>

        </div><!-- /#tab-panel-renewal -->

        <div id="tab-panel-monthly-stats" class="cm-tab-panel hidden">
            <div class="cm-stats-filter-bar">
                <label style="font-size:0.8rem; color:#6b7280;">Month
                    <input type="month" id="ms-filter-month">
                </label>
                <span style="color:#9ca3af; font-size:0.8rem;">or custom range:</span>
                <label style="font-size:0.8rem; color:#6b7280;">From
                    <input type="date" id="ms-filter-date-from">
                </label>
                <label style="font-size:0.8rem; color:#6b7280;">To
                    <input type="date" id="ms-filter-date-to">
                </label>
                <select id="ms-filter-scheme-category">
                    <option value="">All scheme categories</option>
                    <option value="ISO">ISO</option>
                    <option value="BizSafe">BizSafe</option>
                    <option value="JASANZ">JAS-ANZ</option>
                    <option value="Other">Other</option>
                </select>
                <select id="ms-filter-entity">
                    <option value="">All entities</option>
                    <option value="EHS">EHS</option>
                    <option value="Axiscert">Axiscert</option>
                </select>
                <select id="ms-filter-consultant">
                    <option value="">All consultants</option>
                </select>
                <select id="ms-filter-client-status">
                    <option value="">All client statuses</option>
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                    <option value="withdrawn">Withdrawn</option>
                    <option value="blacklisted">Blacklisted</option>
                </select>
                <button id="ms-filter-clear" class="btn btn-ghost-light btn-small" style="width:auto;">Clear</button>
            </div>

            <h2 style="font-size:1rem;" id="ms-range-label">—</h2>

            <div class="cm-export-btns">
                <button class="btn btn-ghost-light btn-small" style="width:auto;" id="ms-export-xlsx">⬇ Export XLSX</button>
                <button class="btn btn-ghost-light btn-small" style="width:auto;" id="ms-export-csv">⬇ Export CSV</button>
                <button class="btn btn-ghost-light btn-small" style="width:auto;" id="ms-export-pdf">⬇ Export PDF</button>
            </div>

            <!-- 2026-09 layout rework: every card below is clickable, and
                 clicking ANY of them — including the 3 Due-milestone chips,
                 which have no single scalar tile of their own — opens its
                 breakdown (and, from there, row-level detail) in the ONE
                 shared panel pair directly below this cards section, not in
                 a fixed table further down the page. Applied uniformly to
                 all 6 former static tables: Due by Milestone, Issued by
                 Scheme/Status/Consultant, Follow-up Activity, Followups
                 Actioned. -->
            <div class="cm-stats-tiles">
                <div class="cm-stats-tile cm-drill" data-metric="new_clients" style="cursor:pointer;" title="Click to see the clients behind this number"><div class="cm-stats-tile-label">New Clients Onboarded</div><div class="cm-stats-tile-value" id="ms-new-clients">—</div></div>
                <div class="cm-stats-tile cm-breakdown-trigger" data-breakdown="certifications_issued" style="cursor:pointer;" title="Click to see this broken down by scheme, status, or consultant"><div class="cm-stats-tile-label">Certifications Issued</div><div class="cm-stats-tile-value" id="ms-certs-issued">—</div></div>
                <div class="cm-stats-tile cm-drill" data-metric="certifications_renewed" style="cursor:pointer;" title="Click to see the certifications behind this number"><div class="cm-stats-tile-label">Certifications Renewed</div><div class="cm-stats-tile-value" id="ms-certs-renewed">—</div></div>
                <div class="cm-stats-tile cm-drill" data-metric="certifications_expired" style="cursor:pointer;" title="Click to see the certifications behind this number"><div class="cm-stats-tile-label">Certifications Expired</div><div class="cm-stats-tile-value" id="ms-certs-expired">—</div></div>
            </div>
            <div class="cm-stats-tiles">
                <div class="cm-stats-tile cm-breakdown-trigger" data-breakdown="followup_activity" style="cursor:pointer;" title="Click to see this broken down by activity type"><div class="cm-stats-tile-label">Follow-up Activity Logged</div><div class="cm-stats-tile-value" id="ms-activity-total">—</div></div>
                <div class="cm-stats-tile cm-breakdown-trigger" data-breakdown="followups_actioned" style="cursor:pointer;" title="Click to see this broken down by stage / resolution"><div class="cm-stats-tile-label">Follow-ups Actioned</div><div class="cm-stats-tile-value" id="ms-followups-actioned">—</div></div>
                <div class="cm-stats-tile"><div class="cm-stats-tile-label">Avg Days-to-Resolution</div><div class="cm-stats-tile-value" id="ms-avg-resolution">—</div></div>
                <div class="cm-stats-tile"><div class="cm-stats-tile-label">Avg Time-to-Renewal (days)</div><div class="cm-stats-tile-value" id="ms-avg-renewal">—</div></div>
            </div>

            <!-- Due This Range (2026-09: elevated from 3 small chips into
                 full cards, same visual/behavioral pattern as every other
                 tile above — each is its own clickable .cm-stats-tile that
                 opens the due_breakdown panel scoped to that one milestone.
                 IDs kept identical to the old chip IDs (ms-due-chip-*) so
                 the existing renderMonthlyStats() value-update code needed
                 no change. -->
            <div class="cm-stats-tiles" style="grid-template-columns: repeat(3, 1fr);">
                <div class="cm-stats-tile cm-breakdown-trigger" data-breakdown="due_breakdown" data-milestone="surveillance_1" style="cursor:pointer;" title="Click to see the clients due for Surveillance 1 in this range"><div class="cm-stats-tile-label">Surveillance 1 Due</div><div class="cm-stats-tile-value" id="ms-due-chip-surveillance_1">—</div></div>
                <div class="cm-stats-tile cm-breakdown-trigger" data-breakdown="due_breakdown" data-milestone="surveillance_2" style="cursor:pointer;" title="Click to see the clients due for Surveillance 2 in this range"><div class="cm-stats-tile-label">Surveillance 2 Due</div><div class="cm-stats-tile-value" id="ms-due-chip-surveillance_2">—</div></div>
                <div class="cm-stats-tile cm-breakdown-trigger" data-breakdown="due_breakdown" data-milestone="recertification" style="cursor:pointer;" title="Click to see the clients due for Recertification/Expiry in this range"><div class="cm-stats-tile-label">Recert / Expiry Due</div><div class="cm-stats-tile-value" id="ms-due-chip-recertification">—</div></div>
            </div>
            <p style="color:var(--muted,#6b7280); margin-top:-4px; font-size:0.8rem;">
                "Incomplete — overdue" is only possible once the range reaches today; a range entirely in the future will correctly show 0 overdue.
            </p>

            <!-- Shared breakdown panel (2026-09 layout rework) — directly
                 below the cards/chips section, ONE location for whichever
                 card was clicked. Replaces the 6 tables that used to render
                 permanently further down the page. -->
            <div id="ms-breakdown-panel" class="hidden">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 id="ms-breakdown-title" style="font-size:0.95rem; margin:0;">Breakdown</h3>
                    <button id="ms-breakdown-close" class="btn btn-ghost-light btn-small" style="width:auto;">Close</button>
                </div>
                <div id="ms-breakdown-subtabs" class="hidden"></div>
                <table class="cm-breakdown-table" id="ms-breakdown-table">
                    <thead id="ms-breakdown-head"></thead>
                    <tbody id="ms-breakdown-body"></tbody>
                </table>
            </div>

            <!-- Row-level drill-down detail panel — sits directly below the
                 breakdown panel (same shared location), opened either
                 straight from a tile (New Clients / Renewed / Expired, which
                 have no aggregate breakdown of their own) or from a row
                 inside the breakdown panel above it. -->
            <div id="ms-detail-panel" class="hidden">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 id="ms-detail-title" style="font-size:0.95rem; margin:0;">Detail</h3>
                    <button id="ms-detail-close" class="btn btn-ghost-light btn-small" style="width:auto;">Close</button>
                </div>
                <div class="mgmt-table-wrap" style="margin-top:8px;">
                    <table class="mgmt-table">
                        <thead><tr id="ms-detail-head"></tr></thead>
                        <tbody id="ms-detail-body"></tbody>
                    </table>
                    <div id="ms-detail-empty" class="mgmt-empty hidden">No rows for this range/filter combination.</div>
                </div>
                <div id="ms-detail-pagination" style="display:flex; justify-content:center; align-items:center; gap:10px; margin-top:10px; font-size:0.85rem;"></div>
            </div>
        </div><!-- /#tab-panel-monthly-stats -->

    </main>
    </div>

    <!-- Mark stage follow-up modal -->
    <div id="stage-followup-modal-overlay" class="cm-modal-overlay hidden">
        <div class="cm-modal">
            <div class="cm-modal-header">
                <h3 id="stage-followup-modal-title">Mark Follow-up</h3>
                <button id="stage-followup-modal-close" class="cm-modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="cm-log-field">
                <label for="stage-followup-note">Note</label>
                <textarea id="stage-followup-note" rows="3" placeholder="What did you do / find out?"></textarea>
            </div>
            <button id="stage-followup-save-btn" class="cm-btn-log">Mark Followed Up</button>
        </div>
    </div>

    <!-- Per-company follow-up stage history modal -->
    <div id="followup-history-modal-overlay" class="cm-modal-overlay hidden">
        <div class="cm-modal">
            <div class="cm-modal-header">
                <h3 id="followup-history-modal-title">Follow-up Stage History</h3>
                <button id="followup-history-modal-close" class="cm-modal-close" aria-label="Close">&times;</button>
            </div>
            <div id="followup-history-list"></div>
        </div>
    </div>
    <!-- Log Activity modal -->
    <div id="notes-modal-overlay" class="cm-modal-overlay hidden">
        <div class="cm-modal">
            <div class="cm-modal-header">
                <h3 id="notes-modal-title">📋 Log Activity</h3>
                <button id="notes-modal-close" class="cm-modal-close" aria-label="Close">&times;</button>
            </div>

            <div class="cm-log-row">
                <div class="cm-log-field">
                    <label for="log-activity-type">Activity Type</label>
                    <select id="log-activity-type">
                        <option value="whatsapp_sent">💬 WhatsApp Sent</option>
                        <option value="call">📞 Call</option>
                        <option value="email">✉️ Email</option>
                        <option value="meeting">🗓️ Meeting</option>
                        <option value="site_visit">📍 Site Visit</option>
                        <option value="other">📝 Other</option>
                    </select>
                </div>
                <div class="cm-log-field" id="log-status-field">
                    <label for="log-status">Update Status</label>
                    <select id="log-status">
                        <option value="">— No change —</option>
                        <option value="active">🟢 Active</option>
                        <option value="pending">🔵 Pending</option>
                        <option value="suspended">🟠 Suspended</option>
                        <option value="expired">🔴 Expired</option>
                        <option value="withdrawn">⚪ Withdrawn</option>
                    </select>
                </div>
            </div>

            <div class="cm-log-field">
                <label for="log-milestone">Mark Milestone Complete</label>
                <select id="log-milestone">
                    <option value="">— None —</option>
                    <option value="surveillance_1">✅ Surveillance 1</option>
                    <option value="surveillance_2">✅ Surveillance 2</option>
                    <option value="recertification">✅ Recertification</option>
                </select>
            </div>

            <div class="cm-log-field">
                <label for="log-notes">Notes</label>
                <textarea id="log-notes" rows="3" placeholder="What happened?"></textarea>
            </div>

            <div class="cm-log-field">
                <label for="log-outcome">Outcome</label>
                <input type="text" id="log-outcome" placeholder="e.g. Interested — sending quote">
            </div>

            <button id="notes-add-btn" class="cm-btn-log">Log Activity</button>

            <div class="cm-history-divider">History</div>
            <div id="notes-list"></div>
        </div>
    </div>

    <div id="toast" class="toast hidden"></div>
    <script>window.EHS_BASE_URL = "<?= addslashes(ehs_url()) ?>";</script>
    <script src="<?= ehs_url('assets/js/session_guard.js') ?>"></script>
    <?php
        // Cache-busting: append the file's actual last-modified time as a
        // query string, so every deploy that changes this JS automatically
        // gets a new URL and browsers/CDNs can't serve a stale cached copy
        // silently — this exact class of bug (page HTML updated, old JS
        // still cached) has caused real column-misalignment before.
        $cmRenewalJsPath = __DIR__ . '/assets/js/cm_renewal_dashboard.js';
        $cmRenewalJsVer = file_exists($cmRenewalJsPath) ? filemtime($cmRenewalJsPath) : time();
    ?>
    <script src="<?= ehs_url('client-management/assets/js/cm_renewal_dashboard.js') ?>?v=<?= $cmRenewalJsVer ?>"></script>
    <?php
        $cmFollowupsJsPath = __DIR__ . '/assets/js/cm_renewal_followups.js';
        $cmFollowupsJsVer = file_exists($cmFollowupsJsPath) ? filemtime($cmFollowupsJsPath) : time();
    ?>
    <script src="<?= ehs_url('client-management/assets/js/cm_renewal_followups.js') ?>?v=<?= $cmFollowupsJsVer ?>"></script>
</body>
</html>