document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const isSuperAdmin = document.querySelector('meta[name="cm-is-super-admin"]').content === '1';
    const API_BASE = window.EHS_BASE_URL + '/client-management/api';

    let activeBucket = ''; // '' | 'near' | 'far' | 'overdue'
    let thresholds = [30, 60, 90];
    let activeNotesClientId = null;
    let activeNotesCertId = null;
    let currentPage = 1;

    const ACTIVITY_TYPE_LABELS = {
        whatsapp_sent: '💬 WhatsApp Sent', call: '📞 Call', email: '✉️ Email',
        meeting: '🗓️ Meeting', site_visit: '📍 Site Visit', other: '📝 Other',
    };
    const STATUS_LABELS = {
        active: '🟢 Active', pending: '🔵 Pending', suspended: '🟠 Suspended',
        expired: '🔴 Expired', withdrawn: '⚪ Withdrawn',
    };
    const MILESTONE_LABELS = {
        surveillance_1: 'Surveillance 1', surveillance_2: 'Surveillance 2', recertification: 'Recertification',
    };

    const toastEl = document.getElementById('toast');
    let toastTimer = null;
    function showToast(message, isError = false) {
        clearTimeout(toastTimer);
        toastEl.textContent = message;
        toastEl.classList.toggle('error', isError);
        toastEl.classList.remove('hidden');
        toastTimer = setTimeout(() => toastEl.classList.add('hidden'), 3500);
    }
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : str;
        return div.innerHTML;
    }
    function csrfHeaders() {
        return { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken };
    }

    // --- Days column ---
    // Computed client-side from the same next_due.date the API already
    // returns, so no backend change was needed for this column.
    function daysInfo(dateStr) {
        if (!dateStr) return { text: '—', cls: 'cm-days-ok', blink: null };
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const due = new Date(dateStr + 'T00:00:00');
        const diff = Math.round((due - today) / 86400000);

        if (diff < 0) return { text: `${Math.abs(diff)} day${Math.abs(diff) === 1 ? '' : 's'} overdue`, cls: 'cm-days-overdue', blink: 'cm-blink-red' };
        if (diff === 0) return { text: 'Due today', cls: 'cm-days-overdue', blink: 'cm-blink-red' };
        if (diff <= 30) return { text: `In ${diff} day${diff === 1 ? '' : 's'}`, cls: 'cm-days-soon', blink: 'cm-blink-amber' };
        return { text: `In ${diff} days`, cls: 'cm-days-ok', blink: null };
    }

    function loadResponsibleFilter() {
        fetch(API_BASE + '/users_lookup.php')
            .then(r => r.json())
            .then(data => {
                const sel = document.getElementById('filter-responsible');
                (data.users || []).forEach(u => {
                    const opt = document.createElement('option');
                    opt.value = u.id;
                    opt.textContent = u.name;
                    sel.appendChild(opt);
                });
            })
            .catch(() => {});
    }

    function currentFilters() {
        return {
            q: document.getElementById('filter-client-name').value.trim(),
            scheme_category: document.getElementById('filter-scheme-category').value,
            industry: document.getElementById('filter-industry').value.trim(),
            entity: document.getElementById('filter-entity').value,
            responsible_person_id: document.getElementById('filter-responsible').value,
        };
    }

    function load() {
        const params = new URLSearchParams(currentFilters());
        if (activeBucket) params.set('bucket', activeBucket);
        params.set('page', currentPage);
        params.set('per_page', 25);

        fetch(API_BASE + '/renewal_dashboard.php?' + params.toString())
            .then(r => r.json())
            .then(data => {
                thresholds = data.thresholds || thresholds;
                render(data);
                renderResultsPagination(data.pagination);
            })
            .catch(() => showToast('Could not load the renewal dashboard.', true));
    }

    /** Small, self-contained pagination control — Prev / page X of Y / Next. Deliberately not shared as a cross-file global, since script load order between this file and cm_renewal_followups.js isn't guaranteed at call time. */
    function renderResultsPagination(pagination) {
        const el = document.getElementById('results-pagination');
        if (!pagination || pagination.total_pages <= 1) { el.innerHTML = ''; return; }
        el.innerHTML = `
            <button class="btn btn-ghost-light btn-small" style="width:auto;" ${pagination.page <= 1 ? 'disabled' : ''} id="results-page-prev">‹ Prev</button>
            <span>Page ${pagination.page} of ${pagination.total_pages} (${pagination.total_count} total)</span>
            <button class="btn btn-ghost-light btn-small" style="width:auto;" ${pagination.page >= pagination.total_pages ? 'disabled' : ''} id="results-page-next">Next ›</button>
        `;
        const prevBtn = document.getElementById('results-page-prev');
        const nextBtn = document.getElementById('results-page-next');
        if (prevBtn) prevBtn.addEventListener('click', () => { currentPage = pagination.page - 1; load(); });
        if (nextBtn) nextBtn.addEventListener('click', () => { currentPage = pagination.page + 1; load(); });
    }

    function render(data) {
        const [t1, t2, t3] = thresholds;
        document.getElementById('widget-near-title').textContent = `Expiring in ${t1} days`;
        document.getElementById('widget-far-title').textContent = `Expiring in ${t2}-${t3} days`;
        document.getElementById('count-near').textContent = data.counts.near;
        document.getElementById('count-far').textContent = data.counts.far;
        document.getElementById('count-overdue').textContent = data.counts.overdue;
        document.getElementById('widget-near-blink').classList.toggle('hidden', !(data.counts.near > 0));

        const stats = data.stats || {};
        const milestones = stats.milestones_done_this_month || {};
        document.getElementById('stat-surv1-done').textContent = milestones.surveillance_1 ?? 0;
        document.getElementById('stat-surv2-done').textContent = milestones.surveillance_2 ?? 0;
        document.getElementById('stat-recert-done').textContent = milestones.recertification ?? 0;
        document.getElementById('stat-activity-total').textContent = stats.activity_logged_this_month ?? 0;
        document.getElementById('stat-followed-up').textContent = stats.followed_up_count ?? 0;
        document.getElementById('stat-pending-followup').textContent = stats.pending_followup_count ?? 0;
        const recencyDays = stats.followup_recency_days ?? 14;
        document.getElementById('stat-followed-up-label').textContent = `certifications (last ${recencyDays}d)`;
        document.getElementById('stat-pending-followup-label').textContent = `certifications (no activity in ${recencyDays}d)`;

        ['widget-near', 'widget-far', 'widget-overdue'].forEach(id => document.getElementById(id).style.outline = '');
        const activeMap = { near: 'widget-near', far: 'widget-far', overdue: 'widget-overdue' };
        if (activeBucket && activeMap[activeBucket]) {
            document.getElementById(activeMap[activeBucket]).style.outline = '2px solid var(--brand)';
        }

        const titles = { '': 'All certifications with an expiry date', near: `Expiring within ${t1} days`, far: `Expiring in ${t2}-${t3} days`, overdue: 'Overdue / Expired' };
        document.getElementById('list-title').textContent = titles[activeBucket] ?? titles[''];

        const body = document.getElementById('results-body');
        const certs = data.certifications || [];
        document.getElementById('results-empty-state').classList.toggle('hidden', certs.length > 0);

        // Merge consecutive rows for the same client (same company appearing
        // back-to-back because it has multiple certifications due around the
        // same time) into one visual block: Client + Entity are shown once,
        // via rowspan, while every certification still gets its own row for
        // Scheme/Cert #/Next Due/Days/Status/Follow-up — those are genuinely
        // separate action items and each needs its own "Log Activity" button.
        let html = '';
        for (let i = 0; i < certs.length; i++) {
            const c = certs[i];
            const days = daysInfo(c.next_due.date);
            const dot = days.blink ? `<span class="cm-blink-dot ${days.blink}" title="Needs attention"></span>` : '';
            const entityColor = c.entity === 'Axiscert' ? '#7c3aed' : '#2563eb';
            const entityBadge = `<span class="badge" style="background:${entityColor}22; color:${entityColor}; border:1px solid ${entityColor}55;" title="${c.client_email ? 'Send from ' + escapeHtml(c.entity || 'EHS') + ' — client email: ' + escapeHtml(c.client_email) : ''}">${escapeHtml(c.entity || 'EHS')}</span>`;

            const isContinuation = i > 0 && certs[i - 1].client_id === c.client_id;
            let clientCells = '';
            if (!isContinuation) {
                let span = 1;
                while (i + span < certs.length && certs[i + span].client_id === c.client_id) span++;
                const rowspanAttr = span > 1 ? ` rowspan="${span}"` : '';
                clientCells = `
                    <td${rowspanAttr} class="cm-group-cell"><a href="${window.EHS_BASE_URL}/client-management/client_detail.php?id=${c.client_id}">${escapeHtml(c.company_name)}</a></td>
                    <td${rowspanAttr} class="cm-group-cell">${entityBadge}</td>
                `;
            }

            html += `
            <tr class="${isContinuation ? 'cm-grouped-row' : ''}">
                ${clientCells}
                <td>${escapeHtml(c.scheme_name)} (${escapeHtml(c.scheme_category)})</td>
                <td>${escapeHtml(c.certificate_number || '—')}</td>
                <td>${escapeHtml(c.next_due.label || '—')}: ${escapeHtml(c.next_due.date || '—')} <span class="badge ${c.next_due.overdue ? 'cm-badge-red' : 'cm-badge-amber'}">${c.next_due.overdue ? 'Overdue' : 'Upcoming'}</span></td>
                <td class="${days.cls}">${dot}${escapeHtml(days.text)}</td>
                <td>${escapeHtml(c.status)}</td>
                <td>${escapeHtml(c.responsible_person || '—')}</td>
                <td><button class="btn btn-ghost-light btn-small" style="width:auto;" onclick="cmOpenNotesModal(${c.client_id}, ${c.id}, '${escapeHtml(c.company_name).replace(/'/g, "\\'")}', '${escapeHtml(c.status)}')">Log Activity</button></td>
            </tr>`;
        }
        body.innerHTML = html;
    }

    document.getElementById('widget-near').addEventListener('click', () => { activeBucket = activeBucket === 'near' ? '' : 'near'; currentPage = 1; load(); });
    document.getElementById('widget-far').addEventListener('click', () => { activeBucket = activeBucket === 'far' ? '' : 'far'; currentPage = 1; load(); });
    document.getElementById('widget-overdue').addEventListener('click', () => { activeBucket = activeBucket === 'overdue' ? '' : 'overdue'; currentPage = 1; load(); });

    function debounce(fn, ms) {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
    }
    ['filter-client-name', 'filter-scheme-category', 'filter-industry', 'filter-entity', 'filter-responsible'].forEach(id => {
        const el = document.getElementById(id);
        el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', debounce(() => { currentPage = 1; load(); }, 300));
    });
    document.getElementById('filter-clear').addEventListener('click', () => {
        document.getElementById('filter-client-name').value = '';
        document.getElementById('filter-scheme-category').value = '';
        document.getElementById('filter-industry').value = '';
        document.getElementById('filter-entity').value = '';
        document.getElementById('filter-responsible').value = '';
        activeBucket = '';
        currentPage = 1;
        load();
    });

    if (isSuperAdmin) {
        document.getElementById('save-thresholds').addEventListener('click', function () {
            const t1 = parseInt(document.getElementById('t1').value, 10);
            const t2 = parseInt(document.getElementById('t2').value, 10);
            const t3 = parseInt(document.getElementById('t3').value, 10);
            if (!(t1 > 0 && t2 > t1 && t3 > t2)) {
                showToast('Thresholds must be positive and strictly increasing (e.g. 30, 60, 90).', true);
                return;
            }
            fetch(API_BASE + '/renewal_dashboard.php', { method: 'PUT', headers: csrfHeaders(), body: JSON.stringify({ thresholds: [t1, t2, t3] }) })
                .then(r => r.json().then(body => ({ ok: r.ok, body })))
                .then(({ ok, body }) => {
                    if (!ok) { showToast(body.error || 'Could not save thresholds.', true); return; }
                    showToast('Thresholds updated.');
                    thresholds = body.thresholds;
                    load();
                })
                .catch(() => showToast('Network error — please try again.', true));
        });
    }

    function initThresholdInputs() {
        if (!isSuperAdmin) return;
        document.getElementById('t1').value = thresholds[0];
        document.getElementById('t2').value = thresholds[1];
        document.getElementById('t3').value = thresholds[2];
    }

    // --- Log Activity modal ---
    function renderNotesList(notes) {
        const listEl = document.getElementById('notes-list');
        if (!notes.length) {
            listEl.innerHTML = '<div class="cm-note-empty">No activity logged yet.</div>';
            return;
        }
        listEl.innerHTML = notes.map(n => {
            const typeLabel = ACTIVITY_TYPE_LABELS[n.activity_type] || '📝 Other';
            const statusChangeLine = n.status_changed_to
                ? `<div style="margin-top:2px;">Status changed to ${escapeHtml(STATUS_LABELS[n.status_changed_to] || n.status_changed_to)}</div>` : '';
            const milestoneLine = n.milestone_completed
                ? `<div style="margin-top:2px;">✅ ${escapeHtml(MILESTONE_LABELS[n.milestone_completed] || n.milestone_completed)} marked complete</div>` : '';
            const outcomeLine = n.outcome
                ? `<div style="margin-top:2px; color:#374151;"><strong>Outcome:</strong> ${escapeHtml(n.outcome)}</div>` : '';
            const canUndo = n.previous_state && !n.reverted_at;
            const revertedLabel = n.reverted_at ? ' <span style="color:#9ca3af; font-style:italic;">(reverted)</span>' : '';
            const undoLine = canUndo
                ? `<div style="margin-top:4px;"><button class="cm-undo-btn" data-note-id="${n.id}" style="font-size:0.75rem; padding:1px 8px; border-radius:4px; border:1px solid #d1d5db; background:#fff; cursor:pointer;">Undo</button></div>` : '';
            return `
            <div class="cm-note-entry">
                <div class="cm-note-meta">${typeLabel} &middot; ${escapeHtml((n.created_at || '').substring(0, 16).replace('T', ' '))} — ${escapeHtml(n.created_by_display_name || '—')}${revertedLabel}</div>
                <div>${escapeHtml(n.note)}</div>
                ${outcomeLine}
                ${statusChangeLine}
                ${milestoneLine}
                ${undoLine}
            </div>
        `;
        }).join('');

        listEl.querySelectorAll('.cm-undo-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const noteId = this.dataset.noteId;
                if (!confirm('Undo this activity? This restores the certification\'s status and dates to what they were right before this entry, and only works if nothing newer has changed them since.')) return;
                fetch(API_BASE + '/client_followup_notes.php?id=' + noteId, {
                    method: 'PUT', headers: csrfHeaders(), body: JSON.stringify({ action: 'undo' }),
                })
                    .then(r => r.json().then(body => ({ ok: r.ok, body })))
                    .then(({ ok, body }) => {
                        if (!ok) { showToast(body.error || 'Could not undo this activity.', true); return; }
                        showToast('Activity reverted.');
                        loadNotes(activeNotesClientId, activeNotesCertId);
                        load(); // refresh the table — status/next-due may have changed back
                    })
                    .catch(() => showToast('Network error — please try again.', true));
            });
        });
    }

    function loadNotes(clientId, certId) {
        const params = new URLSearchParams({ client_id: clientId });
        if (certId) params.set('cert_id', certId);
        fetch(API_BASE + '/client_followup_notes.php?' + params.toString())
            .then(r => r.json())
            .then(data => renderNotesList(data.notes || []))
            .catch(() => showToast('Could not load the activity log.', true));
    }

    // clientId/certId identify what this activity is about; companyName is
    // for the modal title; currentStatus pre-selects nothing (status field
    // defaults to "No change" so a log entry never accidentally changes
    // status just because the modal was opened).
    window.cmOpenNotesModal = function (clientId, certId, companyName, currentStatus) {
        activeNotesClientId = clientId;
        activeNotesCertId = certId;
        document.getElementById('notes-modal-title').textContent = '📋 Log Activity — ' + companyName;
        document.getElementById('log-activity-type').value = 'whatsapp_sent';
        document.getElementById('log-status').value = '';
        document.getElementById('log-milestone').value = '';
        document.getElementById('log-notes').value = '';
        document.getElementById('log-outcome').value = '';
        document.getElementById('notes-list').innerHTML = '<div class="cm-note-empty">Loading…</div>';
        document.getElementById('notes-modal-overlay').classList.remove('hidden');
        loadNotes(clientId, certId);
    };

    function closeNotesModal() {
        document.getElementById('notes-modal-overlay').classList.add('hidden');
        activeNotesClientId = null;
        activeNotesCertId = null;
    }
    document.getElementById('notes-modal-close').addEventListener('click', closeNotesModal);
    document.getElementById('notes-modal-overlay').addEventListener('click', function (e) {
        if (e.target === this) closeNotesModal();
    });

    function submitLogActivity(confirmOutOfOrder) {
        const note = document.getElementById('log-notes').value.trim();
        if (!note) { showToast('Notes cannot be empty.', true); return; }
        if (!activeNotesClientId) return;

        const payload = {
            client_id: activeNotesClientId,
            cert_id: activeNotesCertId || null,
            activity_type: document.getElementById('log-activity-type').value,
            note,
            outcome: document.getElementById('log-outcome').value.trim(),
            new_status: document.getElementById('log-status').value,
            milestone_completed: document.getElementById('log-milestone').value,
            confirm_out_of_order: !!confirmOutOfOrder,
        };

        fetch(API_BASE + '/client_followup_notes.php', {
            method: 'POST',
            headers: csrfHeaders(),
            body: JSON.stringify(payload),
        })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) {
                    // Backend is asking for confirmation, not rejecting —
                    // Surv 1 and/or 2 wasn't marked done yet, and completing
                    // Recertification now would still roll the cycle
                    // forward. Re-submit with confirmation if they say yes.
                    if (body.requires_confirmation === 'out_of_order_recert' && confirm(body.error + '\n\nProceed anyway?')) {
                        submitLogActivity(true);
                        return;
                    }
                    showToast(body.error || 'Could not log this activity.', true);
                    return;
                }
                document.getElementById('log-notes').value = '';
                document.getElementById('log-outcome').value = '';
                document.getElementById('log-status').value = '';
                document.getElementById('log-milestone').value = '';
                loadNotes(activeNotesClientId, activeNotesCertId);
                showToast(body.cycle_rolled_over ? 'Recertification complete — new certification cycle started.' : (body.milestone_completed ? 'Activity logged — milestone marked complete.' : (body.status_changed_to ? 'Activity logged — status updated.' : 'Activity logged.')));
                if (body.status_changed_to || body.milestone_completed) load(); // refresh the table — Status and/or Next Due/Days may have changed
            })
            .catch(() => showToast('Network error — please try again.', true));
    }

    document.getElementById('notes-add-btn').addEventListener('click', () => submitLogActivity(false));

    loadResponsibleFilter();
    fetch(API_BASE + '/renewal_dashboard.php')
        .then(r => r.json())
        .then(data => { thresholds = data.thresholds || thresholds; initThresholdInputs(); render(data); })
        .catch(() => showToast('Could not load the renewal dashboard.', true));
});


/**
 * Monthly Stats tab (2026-09 feature; 2026-09 layout rework) — a SEPARATE
 * DOMContentLoaded listener, deliberately not merged into the block above:
 * it doesn't touch any existing variable, function, or element from the
 * renewal-dashboard logic, so nothing above this point had to change to
 * add or rework this tab.
 *
 * Layout rework: every card (the 4+4 tiles, and the 3 Due-milestone chips
 * that stand in for a tile Due This Range doesn't have) now opens its
 * breakdown/detail inline, directly below the cards section, in one of two
 * shared panels (#ms-breakdown-panel for aggregate tables, #ms-detail-panel
 * for row-level records) — replacing the 6 tables that used to render
 * permanently further down the page.
 */
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const API_BASE = window.EHS_BASE_URL + '/client-management/api';

    const tabBtnRenewal = document.getElementById('tab-btn-renewal');
    const tabBtnStats = document.getElementById('tab-btn-monthly-stats');
    const panelRenewal = document.getElementById('tab-panel-renewal');
    const panelStats = document.getElementById('tab-panel-monthly-stats');
    if (!tabBtnRenewal || !tabBtnStats || !panelRenewal || !panelStats) return; // defensive — page markup not present

    let statsLoaded = false;

    function showTab(which) {
        const onStats = which === 'stats';
        tabBtnRenewal.classList.toggle('active', !onStats);
        tabBtnStats.classList.toggle('active', onStats);
        panelRenewal.classList.toggle('hidden', onStats);
        panelStats.classList.toggle('hidden', !onStats);
        if (onStats && !statsLoaded) {
            statsLoaded = true;
            loadMonthlyStats();
        }
    }
    tabBtnRenewal.addEventListener('click', () => showTab('renewal'));
    tabBtnStats.addEventListener('click', () => showTab('stats'));

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : str;
        return div.innerHTML;
    }

    function currentMonthValue() {
        const d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    }
    document.getElementById('ms-filter-month').value = currentMonthValue();

    function statsFilterParams() {
        const params = {
            scheme_category: document.getElementById('ms-filter-scheme-category').value,
            entity: document.getElementById('ms-filter-entity').value,
            consultant: document.getElementById('ms-filter-consultant').value,
            client_status: document.getElementById('ms-filter-client-status').value,
        };
        const dateFrom = document.getElementById('ms-filter-date-from').value;
        const dateTo = document.getElementById('ms-filter-date-to').value;
        if (dateFrom && dateTo) {
            params.date_from = dateFrom;
            params.date_to = dateTo;
        } else {
            params.month = document.getElementById('ms-filter-month').value || currentMonthValue();
        }
        return params;
    }

    function populateConsultantOptions(consultants) {
        const sel = document.getElementById('ms-filter-consultant');
        const current = sel.value;
        // Rebuild only if the option count actually changed, so an in-progress
        // selection isn't reset on every reload.
        if (sel.dataset.optionsCount == String((consultants || []).length)) return;
        sel.innerHTML = '<option value="">All consultants</option>' +
            (consultants || []).map(c => `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('');
        sel.dataset.optionsCount = String((consultants || []).length);
        if (current) sel.value = current;
    }

    let lastStatsData = null; // the full monthly_stats response, kept so the breakdown panel can re-render from it without an extra request

    function loadMonthlyStats() {
        const params = new URLSearchParams(statsFilterParams());
        params.set('view', 'monthly_stats');
        fetch(API_BASE + '/renewal_dashboard.php?' + params.toString())
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not load monthly stats.', true); return; }
                lastStatsData = body;
                renderMonthlyStats(body);
                // A filter change should refresh whatever's currently open
                // inline too, not just the tiles — otherwise the breakdown/
                // detail panels would silently keep showing the previous
                // filter's rows.
                if (currentBreakdown) openBreakdown(currentBreakdown.type, currentBreakdown, true);
                if (currentDrill && !document.getElementById('ms-detail-panel').classList.contains('hidden')) { detailPage = 1; loadDetail(); }
            })
            .catch(() => showToast('Could not load monthly stats.', true));
    }

    function renderMonthlyStats(data) {
        document.getElementById('ms-range-label').textContent = data.range.label;
        document.getElementById('ms-new-clients').textContent = data.new_clients;
        document.getElementById('ms-certs-issued').textContent = data.certifications_issued;
        document.getElementById('ms-certs-renewed').textContent = data.certifications_renewed;
        document.getElementById('ms-certs-expired').textContent = data.certifications_expired;
        document.getElementById('ms-activity-total').textContent = data.followup_activity.total;
        document.getElementById('ms-followups-actioned').textContent = data.followups_actioned.total;
        document.getElementById('ms-avg-resolution').textContent = data.followups_actioned.avg_days_to_resolution ?? '—';
        document.getElementById('ms-avg-renewal').textContent = data.avg_time_to_renewal_days ?? '—';

        populateConsultantOptions(data.filter_options && data.filter_options.consultants);

        ['surveillance_1', 'surveillance_2', 'recertification'].forEach(k => {
            const el = document.getElementById('ms-due-chip-' + k);
            if (el) el.textContent = (data.due_breakdown[k] || {}).due_total ?? '—';
        });
    }

    // ---------------- Shared breakdown panel (aggregate tables) ----------------
    const breakdownPanel = document.getElementById('ms-breakdown-panel');
    let currentBreakdown = null; // { type, milestone?, subDimension? }

    const BREAKDOWN_TITLES = {
        certifications_issued: 'Certifications Issued — Breakdown',
        followup_activity: 'Follow-up Activity — by Type',
        followups_actioned: 'Follow-ups Actioned — by Stage / Resolution',
        due_breakdown: 'Due This Range',
    };

    function openBreakdown(type, opts, isRefresh) {
        opts = opts || {};
        currentBreakdown = { type, milestone: opts.milestone, subDimension: opts.subDimension || 'scheme' };
        document.getElementById('ms-detail-panel').classList.add('hidden'); // one thing open at a time, keeps the page from growing unbounded
        currentDrill = null;
        breakdownPanel.classList.remove('hidden');
        document.getElementById('ms-breakdown-title').textContent = type === 'due_breakdown'
            ? `Due This Range — ${(lastStatsData.due_breakdown[opts.milestone] || {}).label || opts.milestone}`
            : BREAKDOWN_TITLES[type];
        if (!isRefresh) breakdownPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        renderBreakdown();
    }
    document.getElementById('ms-breakdown-close').addEventListener('click', () => {
        breakdownPanel.classList.add('hidden');
        currentBreakdown = null;
    });

    function keyValueRowsHtml(obj, metric, groupBy) {
        const keys = Object.keys(obj || {});
        if (!keys.length) return '<tr><td style="color:#9ca3af;">No data for this range/filter combination.</td></tr>';
        return keys.map(k =>
            `<tr class="cm-drill-row" data-metric="${escapeHtml(metric)}" data-group-by="${escapeHtml(groupBy)}" data-key="${escapeHtml(k)}" title="Click to see the underlying rows"><td>${escapeHtml(k)}</td><td>${escapeHtml(String(obj[k]))}</td></tr>`
        ).join('');
    }

    function wireDrillRows(container) {
        container.querySelectorAll('.cm-drill-row').forEach(tr => {
            tr.addEventListener('click', () => openDetail({
                metric: tr.dataset.metric, group_by: tr.dataset.groupBy, key: tr.dataset.key,
                title: `${tr.children[0].textContent} (${tr.children[1].textContent})`,
            }));
        });
    }

    function renderBreakdown() {
        if (!currentBreakdown || !lastStatsData) return;
        const { type, milestone, subDimension } = currentBreakdown;
        const head = document.getElementById('ms-breakdown-head');
        const body = document.getElementById('ms-breakdown-body');
        const subtabsEl = document.getElementById('ms-breakdown-subtabs');

        if (type === 'certifications_issued') {
            subtabsEl.classList.remove('hidden');
            const dims = [['scheme', 'By Scheme'], ['status', 'By Status'], ['consultant', 'By Consultant']];
            subtabsEl.innerHTML = dims.map(([dim, label]) =>
                `<button type="button" class="cm-breakdown-subtab-btn${dim === subDimension ? ' active' : ''}" data-dim="${dim}">${label}</button>`
            ).join('');
            subtabsEl.querySelectorAll('.cm-breakdown-subtab-btn').forEach(btn => {
                btn.addEventListener('click', () => { currentBreakdown.subDimension = btn.dataset.dim; renderBreakdown(); });
            });
            const dataMap = { scheme: lastStatsData.by_scheme_type, status: lastStatsData.by_status, consultant: lastStatsData.by_consultant };
            head.innerHTML = '';
            body.innerHTML = keyValueRowsHtml(dataMap[subDimension], 'certifications_issued', subDimension);
            wireDrillRows(body);
            return;
        }

        subtabsEl.classList.add('hidden');
        subtabsEl.innerHTML = '';

        if (type === 'followup_activity') {
            head.innerHTML = '';
            body.innerHTML = keyValueRowsHtml(lastStatsData.followup_activity.by_type, 'followup_activity', 'type');
            wireDrillRows(body);
            return;
        }

        if (type === 'followups_actioned') {
            head.innerHTML = '';
            const stageRows = Object.entries(lastStatsData.followups_actioned.by_stage || {}).map(([k, v]) =>
                `<tr class="cm-drill-row" data-metric="followups_actioned" data-group-by="stage" data-key="${escapeHtml(k)}"><td>${escapeHtml(k)}</td><td>${v}</td></tr>`);
            const resolutionRows = Object.entries(lastStatsData.followups_actioned.by_resolution || {}).map(([k, v]) =>
                `<tr class="cm-drill-row" data-metric="followups_actioned" data-group-by="resolution" data-key="${escapeHtml(k)}"><td>Resolution: ${escapeHtml(k)}</td><td>${v}</td></tr>`);
            const allRows = stageRows.concat(resolutionRows);
            body.innerHTML = allRows.length ? allRows.join('') : '<tr><td colspan="2" style="color:#9ca3af;">No data for this range/filter combination.</td></tr>';
            wireDrillRows(body);
            return;
        }

        if (type === 'due_breakdown') {
            const d = lastStatsData.due_breakdown[milestone];
            head.innerHTML = '<tr><th>Due (total)</th><th>Done</th><th>Incomplete — Overdue</th><th>Incomplete — Not Yet Due</th></tr>';
            if (!d) {
                body.innerHTML = '<tr><td colspan="4" style="color:#9ca3af;">No data for this range/filter combination.</td></tr>';
                return;
            }
            body.innerHTML = `<tr>
                <td class="cm-drill-cell" style="cursor:pointer;" data-status-bucket="" title="Click to see all rows due this range">${d.due_total}</td>
                <td class="cm-due-done cm-drill-cell" style="cursor:pointer;" data-status-bucket="done" title="Click to see the done rows">${d.done}</td>
                <td class="cm-due-overdue cm-drill-cell" style="cursor:pointer;" data-status-bucket="overdue" title="Click to see the overdue rows">${d.incomplete_overdue}</td>
                <td class="cm-due-pending cm-drill-cell" style="cursor:pointer;" data-status-bucket="pending" title="Click to see the not-yet-due rows">${d.incomplete_pending}</td>
            </tr>`;
            body.querySelectorAll('.cm-drill-cell').forEach(td => {
                td.addEventListener('click', () => {
                    const statusBucket = td.dataset.statusBucket;
                    const bucketLabel = statusBucket === 'done' ? 'Done' : statusBucket === 'overdue' ? 'Incomplete — Overdue' : statusBucket === 'pending' ? 'Incomplete — Not Yet Due' : 'All Due';
                    openDetail({ metric: 'due_breakdown', milestone, status_bucket: statusBucket, title: `${d.label} — ${bucketLabel}` });
                });
            });
        }
    }

    // Tiles that open the breakdown panel. The 3 due-milestone cards
    // (2026-09: promoted from small chips to full tiles) carry a
    // data-milestone attribute alongside data-breakdown="due_breakdown";
    // every other breakdown tile has no data-milestone, so this stays a
    // single wiring block rather than a separate one per tile shape.
    document.querySelectorAll('.cm-breakdown-trigger[data-breakdown]').forEach(el => {
        el.addEventListener('click', () => {
            openBreakdown(el.dataset.breakdown, el.dataset.milestone ? { milestone: el.dataset.milestone } : undefined);
        });
    });

    // ---------------- Row-level drill-down detail panel ----------------
    const METRIC_COLUMNS = {
        new_clients: [['company_name', 'Company'], ['entity', 'Entity'], ['consultant', 'Consultant'], ['status', 'Status'], ['industry_sector', 'Industry'], ['first_cert_date', 'First Certification Date']],
        certifications_issued: [['company_name', 'Company'], ['scheme_name', 'Scheme'], ['scheme_category', 'Category'], ['consultant', 'Consultant'], ['cert_status', 'Status'], ['certificate_number', 'Cert #'], ['issue_date', 'Issue Date'], ['expiry_date', 'Expiry Date']],
        certifications_renewed: [['company_name', 'Company'], ['scheme_name', 'Scheme'], ['scheme_category', 'Category'], ['cert_status', 'Status'], ['certificate_number', 'Cert #'], ['recertification_completed_at', 'Renewed At']],
        certifications_expired: [['company_name', 'Company'], ['scheme_name', 'Scheme'], ['scheme_category', 'Category'], ['certificate_number', 'Cert #'], ['expiry_date', 'Expiry Date']],
        followup_activity: [['company_name', 'Company'], ['activity_type', 'Type'], ['note', 'Note'], ['outcome', 'Outcome'], ['status_changed_to', 'Status Changed To'], ['milestone_completed', 'Milestone Completed'], ['created_by_name', 'Logged By'], ['created_at', 'Logged At']],
        followups_actioned: [['company_name', 'Company'], ['scheme_name', 'Scheme'], ['milestone_key', 'Milestone'], ['stage', 'Stage'], ['resolution_type', 'Resolution'], ['milestone_date', 'Milestone Date'], ['followed_up_at', 'Actioned At'], ['followed_up_by_name', 'Actioned By']],
        due_breakdown: [['company_name', 'Company'], ['scheme_name', 'Scheme'], ['cert_status', 'Cert Status'], ['milestone_date', 'Due Date'], ['completed_at', 'Completed At']],
    };

    let currentDrill = null; // { metric, group_by, key, milestone, status_bucket, title }
    let detailPage = 1;

    const detailPanel = document.getElementById('ms-detail-panel');

    function openDetail(drill) {
        currentDrill = drill;
        detailPage = 1;
        document.getElementById('ms-detail-title').textContent = drill.title || 'Detail';
        detailPanel.classList.remove('hidden');
        detailPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        loadDetail();
    }
    document.getElementById('ms-detail-close').addEventListener('click', () => {
        detailPanel.classList.add('hidden');
        currentDrill = null;
    });

    function loadDetail() {
        if (!currentDrill) return;
        const params = new URLSearchParams(statsFilterParams());
        params.set('view', 'monthly_stats_detail');
        params.set('metric', currentDrill.metric);
        if (currentDrill.group_by) params.set('group_by', currentDrill.group_by);
        if (currentDrill.key) params.set('key', currentDrill.key);
        if (currentDrill.milestone) params.set('milestone', currentDrill.milestone);
        if (currentDrill.status_bucket) params.set('status_bucket', currentDrill.status_bucket);
        params.set('page', detailPage);
        params.set('per_page', 25);

        fetch(API_BASE + '/renewal_dashboard.php?' + params.toString())
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not load the detail rows.', true); return; }
                renderDetail(body);
            })
            .catch(() => showToast('Could not load the detail rows.', true));
    }

    function renderDetail(data) {
        const cols = METRIC_COLUMNS[data.metric] || [];
        const head = document.getElementById('ms-detail-head');
        head.innerHTML = cols.map(([, label]) => `<th>${escapeHtml(label)}</th>`).join('');

        const body = document.getElementById('ms-detail-body');
        const rows = data.rows || [];
        document.getElementById('ms-detail-empty').classList.toggle('hidden', rows.length > 0);
        body.innerHTML = rows.map(row => '<tr>' + cols.map(([key]) => `<td>${escapeHtml(row[key] == null ? '—' : String(row[key]))}</td>`).join('') + '</tr>').join('');

        const paginationEl = document.getElementById('ms-detail-pagination');
        if (!data.total_pages || data.total_pages <= 1) {
            paginationEl.innerHTML = '';
            return;
        }
        paginationEl.innerHTML = `
            <button class="btn btn-ghost-light btn-small" style="width:auto;" ${data.page <= 1 ? 'disabled' : ''} id="ms-detail-page-prev">‹ Prev</button>
            <span>Page ${data.page} of ${data.total_pages} (${data.total_count} total)</span>
            <button class="btn btn-ghost-light btn-small" style="width:auto;" ${data.page >= data.total_pages ? 'disabled' : ''} id="ms-detail-page-next">Next ›</button>
        `;
        const prevBtn = document.getElementById('ms-detail-page-prev');
        const nextBtn = document.getElementById('ms-detail-page-next');
        if (prevBtn) prevBtn.addEventListener('click', () => { detailPage = data.page - 1; loadDetail(); });
        if (nextBtn) nextBtn.addEventListener('click', () => { detailPage = data.page + 1; loadDetail(); });
    }

    // Tile clicks with no aggregate breakdown of their own (New Clients /
    // Renewed / Expired) — go straight to row-level detail, opened inline
    // right below the cards, same as everything else.
    document.querySelectorAll('.cm-drill[data-metric]').forEach(tile => {
        tile.addEventListener('click', () => {
            breakdownPanel.classList.add('hidden');
            currentBreakdown = null;
            const metric = tile.dataset.metric;
            const label = tile.querySelector('.cm-stats-tile-label').textContent;
            openDetail({ metric, title: label });
        });
    });

    // ---------------- Filters / export (unchanged behavior) ----------------
    ['ms-filter-month', 'ms-filter-date-from', 'ms-filter-date-to', 'ms-filter-scheme-category', 'ms-filter-entity', 'ms-filter-consultant', 'ms-filter-client-status'].forEach(id => {
        document.getElementById(id).addEventListener('change', loadMonthlyStats);
    });
    document.getElementById('ms-filter-clear').addEventListener('click', () => {
        document.getElementById('ms-filter-month').value = currentMonthValue();
        document.getElementById('ms-filter-date-from').value = '';
        document.getElementById('ms-filter-date-to').value = '';
        document.getElementById('ms-filter-scheme-category').value = '';
        document.getElementById('ms-filter-entity').value = '';
        document.getElementById('ms-filter-consultant').value = '';
        document.getElementById('ms-filter-client-status').value = '';
        loadMonthlyStats();
    });

    function triggerExport(format) {
        const params = new URLSearchParams(statsFilterParams());
        params.set('format', format);
        // A plain navigation (not fetch) so the browser handles the file
        // download/Content-Disposition itself — same approach the existing
        // export buttons elsewhere in this module use (plain links/hrefs to
        // a GET export endpoint), no CSRF header needed since this is a
        // GET, read-only, auth-gated-by-session request, matching
        // export_xlsx.php / export_audit_extract.php / export_client_pdf.php.
        window.location.href = API_BASE + '/export_monthly_stats.php?' + params.toString();
    }
    document.getElementById('ms-export-xlsx').addEventListener('click', () => triggerExport('xlsx'));
    document.getElementById('ms-export-csv').addEventListener('click', () => triggerExport('csv'));
    document.getElementById('ms-export-pdf').addEventListener('click', () => triggerExport('pdf'));
});