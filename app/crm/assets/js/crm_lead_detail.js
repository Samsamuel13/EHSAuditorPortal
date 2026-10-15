(function () {
    'use strict';

    const API_BASE = 'api';
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
    const LEAD_ID = parseInt(document.querySelector('meta[name="crm-lead-id"]').content, 10);

    const SIDE_STAGES = ['lost', 'on_hold']; // must match crm/includes/crm_helpers.php CRM_SIDE_STAGES

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function apiFetch(path, options) {
        options = options || {};
        options.headers = Object.assign({
            'Content-Type': 'application/json',
            'X-CSRF-Token': CSRF_TOKEN,
        }, options.headers || {});
        return fetch(API_BASE + path, options).then(function (r) {
            if (r.status === 401) {
                window.location.reload();
                throw new Error('Session expired');
            }
            return r.json().then(function (data) {
                if (!r.ok) throw new Error(data.error || 'Request failed');
                return data;
            });
        });
    }

    // -------------------------------------------------------------------
    // Owner dropdown (edit modal)
    // -------------------------------------------------------------------

    function loadOwnerOptions() {
        const sel = document.getElementById('edit-lead-owner');
        const current = parseInt(sel.getAttribute('data-current'), 10) || 0;
        apiFetch('/users_lookup.php').then(function (data) {
            sel.innerHTML = '<option value="">Unassigned</option>';
            data.users.forEach(function (u) {
                const opt = document.createElement('option');
                opt.value = u.id;
                opt.textContent = u.name;
                if (u.id === current) opt.selected = true;
                sel.appendChild(opt);
            });
        }).catch(function () {});
    }
    loadOwnerOptions();

    // -------------------------------------------------------------------
    // Edit lead info
    // -------------------------------------------------------------------

    document.getElementById('edit-lead-btn').addEventListener('click', function () {
        new bootstrap.Modal(document.getElementById('editLeadModal')).show();
    });

    document.getElementById('edit-lead-submit').addEventListener('click', function () {
        const form = document.getElementById('edit-lead-form');
        if (!form.reportValidity()) return;

        const formData = new FormData(form);
        const payload = { id: LEAD_ID };
        formData.forEach(function (value, key) { payload[key] = value; });

        apiFetch('/leads.php', {
            method: 'PUT',
            body: JSON.stringify(payload),
        }).then(function () {
            // Simplest correct option: full reload, so every displayed field
            // (including the owner name resolved server-side) reflects the
            // saved state exactly, rather than duplicating that resolution
            // logic here.
            window.location.reload();
        }).catch(function (err) {
            alert('Could not save changes: ' + err.message);
        });
    });

    // -------------------------------------------------------------------
    // Stage change
    // -------------------------------------------------------------------

    const stageSelect = document.getElementById('stage-select');
    const reasonField = document.getElementById('stage-reason');

    function syncReasonFieldVisibility() {
        const needsReason = SIDE_STAGES.indexOf(stageSelect.value) !== -1;
        reasonField.classList.toggle('d-none', !needsReason);
        document.getElementById('stage-reason-error').classList.add('d-none');
    }
    stageSelect.addEventListener('change', syncReasonFieldVisibility);
    syncReasonFieldVisibility();

    document.getElementById('stage-change-submit').addEventListener('click', function () {
        const toStage = stageSelect.value;
        const reason = reasonField.value.trim();

        if (SIDE_STAGES.indexOf(toStage) !== -1 && !reason) {
            document.getElementById('stage-reason-error').classList.remove('d-none');
            return;
        }

        const body = { id: LEAD_ID, to_stage: toStage };
        if (reason) body.reason = reason;

        apiFetch('/lead_stage.php', {
            method: 'PUT',
            body: JSON.stringify(body),
        }).then(function (result) {
            if (result.converted_client_id) {
                alert('Lead awarded — a new client record (#' + result.converted_client_id + ') was created in Client Management.');
            }
            window.location.reload();
        }).catch(function (err) {
            alert('Could not change stage: ' + err.message);
        });
    });

    // -------------------------------------------------------------------
    // Activity log (crm_lead_notes)
    // -------------------------------------------------------------------

    const stageLabels = {
        enquiry: 'Enquiry', lead: 'Lead', quotation: 'Quotation',
        negotiation: 'Negotiation', awarded: 'Awarded', lost: 'Lost', on_hold: 'On Hold',
    };
    const activityTypeLabels = {
        whatsapp_sent: 'WhatsApp sent', call: 'Call', email: 'Email',
        meeting: 'Meeting', site_visit: 'Site visit', other: 'Other',
    };

    function noteHtml(n) {
        let extra = '';
        if (n.stage_changed_to) {
            extra += `<div class="crm-history-reason">Stage changed to ${escapeHtml(stageLabels[n.stage_changed_to] || n.stage_changed_to)}</div>`;
        }
        if (n.outcome) {
            extra += `<div class="crm-history-reason">Outcome: ${escapeHtml(n.outcome)}</div>`;
        }
        let undoBtn = '';
        if (n.previous_state && !n.reverted_at) {
            undoBtn = `<button type="button" class="btn btn-outline-secondary btn-sm mt-1 crm-undo-note" data-id="${n.id}">Undo stage change</button>`;
        } else if (n.reverted_at) {
            extra += `<div class="crm-history-reason text-muted">Reverted ${escapeHtml(n.reverted_at)}</div>`;
        }
        return `
          <div class="crm-history-item">
            <strong>${escapeHtml(activityTypeLabels[n.activity_type] || n.activity_type)}</strong>
            <span class="crm-history-meta">${escapeHtml(n.created_by_display_name || 'Unknown')} · ${escapeHtml(n.created_at)}</span>
            <div>${escapeHtml(n.note)}</div>
            ${extra}
            ${undoBtn}
          </div>`;
    }

    function loadNotes() {
        apiFetch('/lead_notes.php?lead_id=' + LEAD_ID).then(function (data) {
            const el = document.getElementById('activity-log-list');
            if (data.notes.length === 0) {
                el.innerHTML = '<div class="text-muted small">No activity logged yet.</div>';
                return;
            }
            el.innerHTML = data.notes.map(noteHtml).join('');
            el.querySelectorAll('.crm-undo-note').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (!confirm('Undo this activity\'s stage change? The note itself stays on the record.')) return;
                    apiFetch('/lead_notes.php?id=' + btn.getAttribute('data-id'), {
                        method: 'PUT',
                        body: JSON.stringify({ action: 'undo' }),
                    }).then(function () {
                        window.location.reload();
                    }).catch(function (err) {
                        alert('Could not undo: ' + err.message);
                    });
                });
            });
        }).catch(function (err) {
            document.getElementById('activity-log-list').innerHTML =
                '<div class="text-danger small">Failed to load activity log: ' + escapeHtml(err.message) + '</div>';
        });
    }
    loadNotes();

    // -------------------------------------------------------------------
    // Log Activity modal
    // -------------------------------------------------------------------

    document.getElementById('log-activity-btn').addEventListener('click', function () {
        document.getElementById('log-activity-type').value = 'other';
        document.getElementById('log-new-stage').value = '';
        document.getElementById('log-reason-wrap').classList.add('d-none');
        document.getElementById('log-reason').value = '';
        document.getElementById('log-note').value = '';
        document.getElementById('log-outcome').value = '';
        document.getElementById('log-activity-error').classList.add('d-none');
        new bootstrap.Modal(document.getElementById('logActivityModal')).show();
    });

    document.getElementById('log-new-stage').addEventListener('change', function () {
        const needsReason = SIDE_STAGES.indexOf(this.value) !== -1;
        document.getElementById('log-reason-wrap').classList.toggle('d-none', !needsReason);
    });

    document.getElementById('log-activity-submit').addEventListener('click', function () {
        const note = document.getElementById('log-note').value.trim();
        const errEl = document.getElementById('log-activity-error');
        errEl.classList.add('d-none');

        if (!note) {
            errEl.textContent = 'A note is required.';
            errEl.classList.remove('d-none');
            return;
        }

        const newStage = document.getElementById('log-new-stage').value;
        const reason = document.getElementById('log-reason').value.trim();
        if (SIDE_STAGES.indexOf(newStage) !== -1 && !reason) {
            errEl.textContent = 'A reason is required when changing stage to Lost or On Hold.';
            errEl.classList.remove('d-none');
            return;
        }

        const payload = {
            lead_id: LEAD_ID,
            activity_type: document.getElementById('log-activity-type').value,
            note: note,
            outcome: document.getElementById('log-outcome').value.trim() || null,
        };
        if (newStage) {
            payload.new_stage = newStage;
            if (reason) payload.reason = reason;
        }

        apiFetch('/lead_notes.php', {
            method: 'POST',
            body: JSON.stringify(payload),
        }).then(function (result) {
            if (result.converted_client_id) {
                alert('Lead awarded — a new client record (#' + result.converted_client_id + ') was created in Client Management.');
            }
            window.location.reload();
        }).catch(function (err) {
            errEl.textContent = err.message;
            errEl.classList.remove('d-none');
        });
    });
})();