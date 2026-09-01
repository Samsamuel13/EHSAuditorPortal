document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const clientId = document.querySelector('meta[name="cm-client-id"]').content;
    const API_BASE = window.EHS_BASE_URL + '/client-management/api';

    const toastEl = document.getElementById('toast');
    let toastTimer = null;
    function showToast(message, isError = false) {
        clearTimeout(toastTimer);
        toastEl.textContent = message;
        toastEl.classList.toggle('error', isError);
        toastEl.classList.remove('hidden');
        toastTimer = setTimeout(() => toastEl.classList.add('hidden'), 3500);
    }

    function csrfHeaders() {
        return { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken };
    }

    const modalBackdrop = document.getElementById('modal-backdrop');
    document.getElementById('edit-btn').addEventListener('click', () => modalBackdrop.classList.remove('hidden'));
    document.getElementById('modal-cancel').addEventListener('click', () => modalBackdrop.classList.add('hidden'));

    document.getElementById('modal-save').addEventListener('click', function () {
        const companyName = document.getElementById('f-company-name').value.trim();
        if (!companyName) { showToast('Company name is required.', true); return; }

        const payload = {
            company_name: companyName,
            uen_registration_no: document.getElementById('f-uen').value.trim(),
            industry_sector: document.getElementById('f-industry').value.trim(),
            address: document.getElementById('f-address').value.trim(),
            contact_person: document.getElementById('f-contact-person').value.trim(),
            contact_designation: document.getElementById('f-contact-designation').value.trim(),
            consultant: document.getElementById('f-consultant').value.trim(),
            phone: document.getElementById('f-phone').value.trim(),
            email: document.getElementById('f-email').value.trim(),
            website: document.getElementById('f-website').value.trim(),
            status: document.getElementById('f-status').value,
            notes: document.getElementById('f-notes').value.trim(),
        };

        fetch(`${API_BASE}/clients.php?id=${clientId}`, { method: 'PUT', headers: csrfHeaders(), body: JSON.stringify(payload) })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not save client.', true); return; }
                showToast('Client updated.');
                setTimeout(() => location.reload(), 600);
            })
            .catch(() => showToast('Network error — please try again.', true));
    });

    // --- Log Activity ---
    // Same client_followup_notes.php endpoint the Renewal Dashboard uses —
    // no new backend for this page.
    const ACTIVITY_TYPE_LABELS = {
        whatsapp_sent: 'WhatsApp Sent', call: 'Call', email: 'Email',
        meeting: 'Meeting', site_visit: 'Site Visit', other: 'Other',
    };
    const STATUS_LABELS = {
        active: 'Active', pending: 'Pending', suspended: 'Suspended',
        expired: 'Expired', withdrawn: 'Withdrawn',
    };

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : str;
        return div.innerHTML;
    }

    let activeLogCertId = null; // null = general client-level activity, no status field

    function renderLogActivityList(notes) {
        const listEl = document.getElementById('log-activity-list');
        if (!notes.length) {
            listEl.innerHTML = '<div class="mgmt-empty">No activity logged yet.</div>';
            return;
        }
        listEl.innerHTML = notes.map(n => {
            const parts = [escapeHtml(n.note)];
            if (n.outcome) parts.push(`<strong>Outcome:</strong> ${escapeHtml(n.outcome)}`);
            if (n.status_changed_to) parts.push(`Status changed to ${escapeHtml(STATUS_LABELS[n.status_changed_to] || n.status_changed_to)}`);
            return `
            <div class="list-item">
                <strong>${escapeHtml(ACTIVITY_TYPE_LABELS[n.activity_type] || 'Other')}</strong>
                <span class="cm-activity-meta">${escapeHtml(n.created_by_display_name || '—')} &middot; ${escapeHtml((n.created_at || '').substring(0, 16).replace('T', ' '))}</span>
                <span>${parts.join(' — ')}</span>
            </div>`;
        }).join('');
    }

    function loadLogActivity(certId) {
        const params = new URLSearchParams({ client_id: clientId });
        if (certId) params.set('cert_id', certId);
        fetch(`${API_BASE}/client_followup_notes.php?${params.toString()}`)
            .then(r => r.json())
            .then(data => renderLogActivityList(data.notes || []))
            .catch(() => showToast('Could not load the activity log.', true));
    }

    const logBackdrop = document.getElementById('log-activity-backdrop');

    // clientId comes from the meta tag already read above. certId is only
    // passed once cm_certifications.js grows a per-row button (see the
    // comment near window.CM_CLIENT_ID in client_detail.php) — for now the
    // toolbar button always calls this with certId = null.
    window.cmOpenLogActivity = function (certId) {
        activeLogCertId = certId || null;
        document.getElementById('log-status-field').style.display = activeLogCertId ? '' : 'none';
        document.getElementById('log-activity-type').value = 'whatsapp_sent';
        document.getElementById('log-status').value = '';
        document.getElementById('log-notes').value = '';
        document.getElementById('log-outcome').value = '';
        document.getElementById('log-activity-list').innerHTML = '<div class="mgmt-empty">Loading…</div>';
        logBackdrop.classList.remove('hidden');
        loadLogActivity(activeLogCertId);
    };

    document.getElementById('log-activity-btn').addEventListener('click', () => window.cmOpenLogActivity(null));
    document.getElementById('log-activity-cancel').addEventListener('click', () => logBackdrop.classList.add('hidden'));

    document.getElementById('log-activity-save').addEventListener('click', function () {
        const note = document.getElementById('log-notes').value.trim();
        if (!note) { showToast('Notes cannot be empty.', true); return; }

        const payload = {
            client_id: clientId,
            cert_id: activeLogCertId,
            activity_type: document.getElementById('log-activity-type').value,
            note,
            outcome: document.getElementById('log-outcome').value.trim(),
            new_status: activeLogCertId ? document.getElementById('log-status').value : '',
        };

        fetch(`${API_BASE}/client_followup_notes.php`, { method: 'POST', headers: csrfHeaders(), body: JSON.stringify(payload) })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not log this activity.', true); return; }
                document.getElementById('log-notes').value = '';
                document.getElementById('log-outcome').value = '';
                document.getElementById('log-status').value = '';
                loadLogActivity(activeLogCertId);
                showToast(body.status_changed_to ? 'Activity logged — status updated.' : 'Activity logged.');
                if (body.status_changed_to) setTimeout(() => location.reload(), 600); // refresh cert table to show new status
            })
            .catch(() => showToast('Network error — please try again.', true));
    });
});