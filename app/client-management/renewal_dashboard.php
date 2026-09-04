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
</style>
</head>
<body>
    <header class="topbar">
        <span class="topbar-brand">
            <img src="<?= ehs_url('assets/img/logo.png') ?>" alt="EHS Universal" class="topbar-logo">
            <span class="topbar-subtitle">Client Management</span>
        </span>
        <span class="topbar-user">
            <?= htmlspecialchars($user['name']) ?>
            &middot; <a href="<?= ehs_url('client-management/index.php') ?>">Client Directory</a>
            &middot; <a href="<?= ehs_url('client-management/audit_extract.php') ?>">Audit Extract</a>
            &middot; <a href="<?= ehs_url('client-management/import.php') ?>">Bulk Import</a>
            &middot; <a href="<?= ehs_url('crm/index.php') ?>">CRM</a>
            &middot; <a href="<?= ehs_url('admin/index.php') ?>">Scheduler Dashboard</a>
            &middot; <a href="<?= ehs_url('logout.php') ?>">Log out</a>
        </span>
    </header>

    <main class="page">
        <h1>Renewal Dashboard</h1>

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
            Companies that have crossed a follow-up stage (4 months / 2 months / 30 days / 4 days before their next Surveillance or Recertification) and haven't been actioned for that stage yet.
        </p>
        <div class="mgmt-table-wrap">
            <table class="mgmt-table">
                <thead><tr>
                    <th>Client</th>
                    <th>Entity</th>
                    <th>Milestone</th>
                    <th>Stage</th>
                    <th>Due</th>
                    <th>Responsible</th>
                    <th></th>
                </tr></thead>
                <tbody id="followup-today-body"></tbody>
            </table>
            <div id="followup-today-empty" class="mgmt-empty hidden">Nothing due for follow-up right now.</div>
        </div>

        <h2 style="font-size:1.05rem; margin-top:24px;" id="list-title">All certifications with an expiry date</h2>
        <div class="mgmt-table-wrap">
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
    </main>

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