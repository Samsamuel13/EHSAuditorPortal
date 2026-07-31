<?php
// File: admin/schedule.php
require_once __DIR__ . '/../includes/auth.php';

$user = ehs_require_role(['super_admin', 'admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars(ehs_csrf_token()) ?>">
<title>Master Schedule — <?= htmlspecialchars(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= ehs_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/calendar.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/schedule.css') ?>">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.21/index.global.min.js"></script>
</head>
<body>
    <header class="topbar">
        <span class="topbar-brand">
            <img src="<?= ehs_url('assets/img/logo.png') ?>" alt="EHS Universal" class="topbar-logo">
            <span class="topbar-subtitle">Auditor Scheduler</span>
        </span>
        <span class="topbar-user">
            <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['role']) ?>)
            &middot; <a href="<?= ehs_url('admin/index.php') ?>">Dashboard</a>
            &middot; <a href="<?= ehs_url('admin/day_schedule.php') ?>">My Day Schedule</a>
            &middot; <a href="<?= ehs_url('admin/auditors.php') ?>">Auditors</a>
            &middot; <a href="<?= ehs_url('admin/clients.php') ?>">Clients</a>
            &middot; <a href="<?= ehs_url('admin/schemes.php') ?>">Schemes</a>
            &middot; <a href="<?= ehs_url('admin/holidays.php') ?>">Holidays</a>
            &middot; <a href="<?= ehs_url('admin/export.php') ?>">Export</a>
            &middot; <a href="<?= ehs_url('admin/profile.php') ?>">My Account</a>
            &middot; <a href="<?= ehs_url('logout.php') ?>">Log out</a>
        </span>
    </header>

    <div class="back-nav">
        <button onclick="history.back()" class="btn btn-ghost-light btn-small back-btn">&larr; Back</button>
    </div>

    <main class="page page-wide">
        <h1>Master Schedule</h1>

        <div class="toolbar">
            <div class="view-toggle">
                <button id="view-calendar-btn" class="btn-toggle active">Calendar view</button>
                <button id="view-grid-btn" class="btn-toggle">Grid / Sheet view</button>
            </div>

            <button id="new-audit-btn" class="btn btn-primary btn-small">+ New audit</button>
        </div>

        <div class="filter-bar">
            <select id="filter-auditor">
                <option value="">All auditors</option>
            </select>
            <select id="filter-scheme">
                <option value="">All schemes</option>
            </select>
            <input type="text" id="filter-client" placeholder="Search client…">
            <select id="filter-status">
                <option value="">All statuses</option>
                <option value="scheduled">Scheduled</option>
                <option value="confirmed">Confirmed</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <button id="filter-clear-btn" class="btn btn-ghost-light btn-small">Clear filters</button>
        </div>

        <div id="auditor-legend" class="legend"></div>
        <div class="legend" style="margin-top:-8px;">
            <span class="legend-item"><i class="dot dot-available"></i> Grid cell tint: Available</span>
            <span class="legend-item"><i class="dot dot-unavailable"></i> Unavailable</span>
            <span class="legend-item"><i class="dot dot-tentative"></i> Tentative</span>
            <span class="legend-item">(top half = AM, bottom half = PM)</span>
        </div>

        <div id="calendar-view">
            <div id="calendar"></div>
        </div>

        <div id="grid-view" class="hidden">
            <div class="grid-nav-bar">
                <button id="grid-prev-btn" class="btn btn-ghost-light btn-small">← Prev month</button>
                <span id="grid-month-label" class="grid-month-label"></span>
                <button id="grid-today-btn" class="btn btn-ghost-light btn-small">Today</button>
                <button id="grid-next-btn" class="btn btn-ghost-light btn-small">Next month →</button>
            </div>
            <div class="grid-scroll">
                <table id="grid-table" class="grid-table">
                    <thead><tr><th>Date</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Assignment modal -->
    <div id="modal-backdrop" class="modal-backdrop hidden">
        <div class="modal modal-wide">
            <h2 id="modal-title">New audit</h2>

            <label for="audit-client">Client</label>
            <div class="autocomplete">
                <input type="text" id="audit-client" autocomplete="off" placeholder="Type to search or add a client…">
                <input type="hidden" id="audit-client-id">
                <div id="client-suggestions" class="autocomplete-list hidden"></div>
            </div>

            <label>Schemes</label>
            <div id="scheme-checkboxes" class="checkbox-grid"></div>

            <div class="modal-row">
                <div>
                    <label for="audit-date">Date</label>
                    <input type="date" id="audit-date">
                </div>
                <div>
                    <label>Session</label>
                    <div class="segmented">
                        <label><input type="radio" name="audit-session" value="FULL_DAY" checked> Full day</label>
                        <label><input type="radio" name="audit-session" value="AM"> AM</label>
                        <label><input type="radio" name="audit-session" value="PM"> PM</label>
                    </div>
                </div>
            </div>

            <label>Auditors <span class="label-hint">(highlighted = selected; badge = unavailable/conflict that day)</span></label>
            <div id="auditor-checkboxes"></div>

            <div class="modal-row">
                <div>
                    <label for="audit-location">Location</label>
                    <input type="text" id="audit-location" maxlength="255">
                </div>
                <div>
                    <label for="audit-status">Status</label>
                    <select id="audit-status">
                        <option value="scheduled">Scheduled</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            <label for="audit-notes">Notes</label>
            <input type="text" id="audit-notes" maxlength="1000">

            <div id="modal-warnings" class="modal-warnings hidden"></div>

            <div class="modal-actions modal-actions-split">
                <div>
                    <button id="modal-delete" class="btn btn-danger hidden">Delete</button>
                    <button id="modal-timeline" class="btn btn-ghost-light hidden">View Timeline</button>
                </div>
                <div class="modal-actions">
                    <button id="modal-cancel" class="btn btn-ghost">Cancel</button>
                    <button id="modal-save" class="btn btn-primary">Save audit</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Generic confirm dialog -->
    <div id="confirm-backdrop" class="modal-backdrop hidden">
        <div class="modal modal-narrow">
            <h2 id="confirm-title">Are you sure?</h2>
            <p id="confirm-message"></p>
            <div class="modal-actions">
                <button id="confirm-cancel" class="btn btn-ghost">Cancel</button>
                <button id="confirm-ok" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>

    <!-- Day agenda: list of a day's audits, opened from a Calendar-view date click -->
    <div id="day-agenda-backdrop" class="modal-backdrop hidden">
        <div class="modal modal-wide">
            <h2 id="day-agenda-title">Audits on this day</h2>
            <div id="day-agenda-list"></div>
            <div class="modal-actions" style="justify-content: space-between;">
                <button id="day-agenda-add-btn" class="btn btn-primary btn-small">+ Add New Audit</button>
                <button id="day-agenda-close" class="btn btn-ghost">Close</button>
            </div>
        </div>
    </div>

    <!-- Per-audit timeline -->
    <div id="timeline-backdrop" class="modal-backdrop hidden">
        <div class="modal">
            <h2>Audit Timeline</h2>
            <p id="timeline-subtitle" class="modal-subtitle"></p>
            <div id="timeline-list" class="timeline-list"></div>
            <div class="modal-actions">
                <button id="timeline-close" class="btn btn-ghost">Close</button>
            </div>
        </div>
    </div>

    <div id="toast" class="toast hidden"></div>

    <script>window.EHS_BASE_URL = "<?= addslashes(ehs_url()) ?>";</script>
    <script src="<?= ehs_url('assets/js/session_guard.js') ?>"></script>
    <script src="<?= ehs_url('assets/js/schedule.js') ?>"></script>
</body>
</html>
