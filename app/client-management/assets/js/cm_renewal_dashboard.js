document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const isSuperAdmin = document.querySelector('meta[name="cm-is-super-admin"]').content === '1';
    const API_BASE = window.EHS_BASE_URL + '/client-management/api';

    let activeBucket = ''; // '' | 'near' | 'far' | 'overdue'
    let thresholds = [30, 60, 90];
    let activeNotesClientId = null;
    let activeNotesCertId = null;

    const ACTIVITY_TYPE_LABELS = {
        whatsapp_sent: '💬 WhatsApp Sent', call: '📞 Call', email: '✉️ Email',
        meeting: '🗓️ Meeting', site_visit: '📍 Site Visit', other: '📝 Other',
    };
    const STATUS_LABELS = {
        active: '🟢 Active', pending: '🔵 Pending', suspended: '🟠 Suspended',
        expired: '🔴 Expired', withdrawn: '⚪ Withdrawn',
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
            responsible_person_id: document.getElementById('filter-responsible').value,
        };
    }

    function load() {
        const params = new URLSearchParams(currentFilters());
        if (activeBucket) params.set('bucket', activeBucket);

        fetch(API_BASE + '/renewal_dashboard.php?' + params.toString())
            .then(r => r.json())
            .then(data => {
                thresholds = data.thresholds || thresholds;
                render(data);
            })
            .catch(() => showToast('Could not load the renewal dashboard.', true));
    }

    function render(data) {
        const [t1, t2, t3] = thresholds;
        document.getElementById('widget-near-title').textContent = `Expiring in ${t1} days`;
        document.getElementById('widget-far-title').textContent = `Expiring in ${t2}-${t3} days`;
        document.getElementById('count-near').textContent = data.counts.near;
        document.getElementById('count-far').textContent = data.counts.far;
        document.getElementById('count-overdue').textContent = data.counts.overdue;
        document.getElementById('widget-near-blink').classList.toggle('hidden', !(data.counts.near > 0));

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

        body.innerHTML = certs.map(c => {
            const days = daysInfo(c.next_due.date);
            const dot = days.blink ? `<span class="cm-blink-dot ${days.blink}" title="Needs attention"></span>` : '';
            return `
            <tr>
                <td><a href="${window.EHS_BASE_URL}/client-management/client_detail.php?id=${c.client_id}">${escapeHtml(c.company_name)}</a></td>
                <td>${escapeHtml(c.scheme_name)} (${escapeHtml(c.scheme_category)})</td>
                <td>${escapeHtml(c.certificate_number || '—')}</td>
                <td>${escapeHtml(c.next_due.label || '—')}: ${escapeHtml(c.next_due.date || '—')} <span class="badge ${c.next_due.overdue ? 'cm-badge-red' : 'cm-badge-amber'}">${c.next_due.overdue ? 'Overdue' : 'Upcoming'}</span></td>
                <td class="${days.cls}">${dot}${escapeHtml(days.text)}</td>
                <td>${escapeHtml(c.status)}</td>
                <td>${escapeHtml(c.responsible_person || '—')}</td>
                <td><button class="btn btn-ghost-light btn-small" style="width:auto;" onclick="cmOpenNotesModal(${c.client_id}, ${c.id}, '${escapeHtml(c.company_name).replace(/'/g, "\\'")}', '${escapeHtml(c.status)}')">Log Activity</button></td>
            </tr>
        `;
        }).join('');
    }

    document.getElementById('widget-near').addEventListener('click', () => { activeBucket = activeBucket === 'near' ? '' : 'near'; load(); });
    document.getElementById('widget-far').addEventListener('click', () => { activeBucket = activeBucket === 'far' ? '' : 'far'; load(); });
    document.getElementById('widget-overdue').addEventListener('click', () => { activeBucket = activeBucket === 'overdue' ? '' : 'overdue'; load(); });

    function debounce(fn, ms) {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
    }
    ['filter-client-name', 'filter-scheme-category', 'filter-industry', 'filter-responsible'].forEach(id => {
        const el = document.getElementById(id);
        el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', debounce(load, 300));
    });
    document.getElementById('filter-clear').addEventListener('click', () => {
        document.getElementById('filter-client-name').value = '';
        document.getElementById('filter-scheme-category').value = '';
        document.getElementById('filter-industry').value = '';
        document.getElementById('filter-responsible').value = '';
        activeBucket = '';
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
            const outcomeLine = n.outcome
                ? `<div style="margin-top:2px; color:#374151;"><strong>Outcome:</strong> ${escapeHtml(n.outcome)}</div>` : '';
            return `
            <div class="cm-note-entry">
                <div class="cm-note-meta">${typeLabel} &middot; ${escapeHtml((n.created_at || '').substring(0, 16).replace('T', ' '))} — ${escapeHtml(n.created_by_display_name || '—')}</div>
                <div>${escapeHtml(n.note)}</div>
                ${outcomeLine}
                ${statusChangeLine}
            </div>
        `;
        }).join('');
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

    document.getElementById('notes-add-btn').addEventListener('click', function () {
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
        };

        fetch(API_BASE + '/client_followup_notes.php', {
            method: 'POST',
            headers: csrfHeaders(),
            body: JSON.stringify(payload),
        })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not log this activity.', true); return; }
                document.getElementById('log-notes').value = '';
                document.getElementById('log-outcome').value = '';
                document.getElementById('log-status').value = '';
                loadNotes(activeNotesClientId, activeNotesCertId);
                showToast(body.status_changed_to ? 'Activity logged — status updated.' : 'Activity logged.');
                if (body.status_changed_to) load(); // refresh the table so the Status column reflects the change
            })
            .catch(() => showToast('Network error — please try again.', true));
    });

    loadResponsibleFilter();
    fetch(API_BASE + '/renewal_dashboard.php')
        .then(r => r.json())
        .then(data => { thresholds = data.thresholds || thresholds; initThresholdInputs(); render(data); })
        .catch(() => showToast('Could not load the renewal dashboard.', true));
});