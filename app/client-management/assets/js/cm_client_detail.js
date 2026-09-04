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
            entity: document.getElementById('f-entity').value,
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
    const MILESTONE_LABELS = {
        surveillance_1: 'Surveillance 1', surveillance_2: 'Surveillance 2', recertification: 'Recertification',
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
            if (n.milestone_completed) parts.push(`✅ ${escapeHtml(MILESTONE_LABELS[n.milestone_completed] || n.milestone_completed)} marked complete`);
            const canUndo = n.previous_state && !n.reverted_at;
            const revertedLabel = n.reverted_at ? '<span style="color:#9ca3af; font-style:italic;"> (reverted)</span>' : '';
            const undoBtn = canUndo ? ` <button class="cm-undo-btn" data-note-id="${n.id}" style="margin-left:8px; font-size:0.75rem; padding:1px 8px; border-radius:4px; border:1px solid #d1d5db; background:#fff; cursor:pointer;">Undo</button>` : '';
            return `
            <div class="list-item">
                <strong>${escapeHtml(ACTIVITY_TYPE_LABELS[n.activity_type] || 'Other')}</strong>${revertedLabel}
                <span class="cm-activity-meta">${escapeHtml(n.created_by_display_name || '—')} &middot; ${escapeHtml((n.created_at || '').substring(0, 16).replace('T', ' '))}</span>
                <span>${parts.join(' — ')}${undoBtn}</span>
            </div>`;
        }).join('');

        listEl.querySelectorAll('.cm-undo-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const noteId = this.dataset.noteId;
                if (!confirm('Undo this activity? This restores the certification\'s status and dates to what they were right before this entry, and only works if nothing newer has changed them since.')) return;
                fetch(`${API_BASE}/client_followup_notes.php?id=${noteId}`, {
                    method: 'PUT', headers: csrfHeaders(), body: JSON.stringify({ action: 'undo' }),
                })
                    .then(r => r.json().then(body => ({ ok: r.ok, body })))
                    .then(({ ok, body }) => {
                        if (!ok) { showToast(body.error || 'Could not undo this activity.', true); return; }
                        showToast('Activity reverted.');
                        loadLogActivity(activeLogCertId);
                        setTimeout(() => location.reload(), 600); // refresh cert table to show restored status/dates
                    })
                    .catch(() => showToast('Network error — please try again.', true));
            });
        });
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
    const certSelect = document.getElementById('log-cert-select');
    let certListCache = [];

    function loadCertOptionsAndOpen(preselectCertId) {
        certSelect.innerHTML = '<option value="">Loading certifications…</option>';
        fetch(`${API_BASE}/certifications.php?client_id=${clientId}`)
            .then(r => r.json())
            .then(data => {
                certListCache = data.certifications || [];
                certSelect.innerHTML = '<option value="">— General (not tied to a specific certification) —</option>'
                    + certListCache.map(c => `<option value="${c.id}">${escapeHtml(c.scheme_name || 'Certification')} — ${escapeHtml(c.certificate_number || 'no cert #')} (${escapeHtml(c.status)})</option>`).join('');
                // Default to the most urgent certification — the list
                // already comes back sorted by next-due date (same
                // ordering the renewal dashboard uses), so this puts
                // Status + Milestone in front of you immediately, same as
                // opening a per-row button there, instead of requiring an
                // extra click to pick one from "General" first.
                const defaultCertId = certListCache.length ? String(certListCache[0].id) : '';
                certSelect.value = preselectCertId || defaultCertId;
                applyCertSelection();
            })
            .catch(() => {
                certSelect.innerHTML = '<option value="">— General (not tied to a specific certification) —</option>';
                showToast('Could not load this client\'s certifications — you can still log a general activity.', true);
            });
    }

    function applyCertSelection() {
        activeLogCertId = certSelect.value ? parseInt(certSelect.value, 10) : null;
        document.getElementById('log-status-field').style.display = activeLogCertId ? '' : 'none';
        document.getElementById('log-milestone-field').style.display = activeLogCertId ? '' : 'none';
        document.getElementById('log-status').value = '';
        document.getElementById('log-milestone').value = '';
        document.getElementById('log-activity-list').innerHTML = '<div class="mgmt-empty">Loading…</div>';
        loadLogActivity(activeLogCertId);
    }
    certSelect.addEventListener('change', applyCertSelection);

    // certId is optional — pass a real certification id to open the modal
    // pre-scoped to it (e.g. from a future per-row trigger), or omit/pass
    // null to open on "General" and let the person pick from the dropdown.
    window.cmOpenLogActivity = function (certId) {
        document.getElementById('log-activity-type').value = 'whatsapp_sent';
        document.getElementById('log-notes').value = '';
        document.getElementById('log-outcome').value = '';
        logBackdrop.classList.remove('hidden');
        loadCertOptionsAndOpen(certId || '');
    };

    document.getElementById('log-activity-btn').addEventListener('click', () => window.cmOpenLogActivity(null));
    document.getElementById('log-activity-cancel').addEventListener('click', () => logBackdrop.classList.add('hidden'));

    function submitLogActivity(confirmOutOfOrder) {
        const note = document.getElementById('log-notes').value.trim();
        if (!note) { showToast('Notes cannot be empty.', true); return; }

        const payload = {
            client_id: clientId,
            cert_id: activeLogCertId,
            activity_type: document.getElementById('log-activity-type').value,
            note,
            outcome: document.getElementById('log-outcome').value.trim(),
            new_status: activeLogCertId ? document.getElementById('log-status').value : '',
            milestone_completed: activeLogCertId ? document.getElementById('log-milestone').value : '',
            confirm_out_of_order: !!confirmOutOfOrder,
        };

        fetch(`${API_BASE}/client_followup_notes.php`, { method: 'POST', headers: csrfHeaders(), body: JSON.stringify(payload) })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) {
                    // The backend is asking "are you sure?" rather than
                    // rejecting outright — Surveillance 1 and/or 2 wasn't
                    // marked done yet, and completing Recertification now
                    // would still roll the cycle forward and discard that.
                    // Re-submit with confirmation if the person says yes.
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
                loadLogActivity(activeLogCertId);
                showToast(body.cycle_rolled_over ? 'Recertification complete — new certification cycle started.' : (body.milestone_completed ? 'Activity logged — milestone marked complete.' : (body.status_changed_to ? 'Activity logged — status updated.' : 'Activity logged.')));
                if (body.status_changed_to || body.milestone_completed) setTimeout(() => location.reload(), 600); // refresh cert table to show new status
            })
            .catch(() => showToast('Network error — please try again.', true));
    }

    document.getElementById('log-activity-save').addEventListener('click', () => submitLogActivity(false));
});