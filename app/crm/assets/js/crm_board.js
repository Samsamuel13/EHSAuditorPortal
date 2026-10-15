(function () {
    'use strict';

    const API_BASE = 'api';
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

    // Must match crm/includes/crm_helpers.php CRM_FUNNEL_STAGES exactly —
    // display order only, never used for any ordinal/progress comparison.
    const FUNNEL_STAGES = [
        { key: 'enquiry', label: 'Enquiry' },
        { key: 'lead', label: 'Lead' },
        { key: 'quotation', label: 'Quotation' },
        { key: 'negotiation', label: 'Negotiation' },
        { key: 'awarded', label: 'Awarded' },
    ];

    let currentView = 'board';
    let sortableInstances = [];
    let pendingMove = null; // { id, to_stage, cardEl } while the reason modal is open

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

    function daysBadge(days) {
        const cls = days >= 14 ? 'crm-days-badge crm-days-stale' : 'crm-days-badge';
        return `<span class="${cls}">${days}d in stage</span>`;
    }

    // -------------------------------------------------------------------
    // Board view
    // -------------------------------------------------------------------

    function renderBoardColumns() {
        const container = document.getElementById('crm-board-columns');
        container.innerHTML = FUNNEL_STAGES.map(function (s) {
            return `
              <div class="col crm-column-wrap">
                <div class="crm-column" data-stage="${s.key}">
                  <div class="crm-column-header">
                    <span>${escapeHtml(s.label)}</span>
                    <span class="crm-column-count" id="count-${s.key}">0</span>
                  </div>
                  <div class="crm-column-cards" id="cards-${s.key}" data-stage="${s.key}"></div>
                </div>
              </div>`;
        }).join('');
    }

    function cardHtml(lead) {
        return `
          <div class="crm-card" data-id="${lead.id}">
            <div class="crm-card-company">
              <a href="lead_detail.php?id=${lead.id}" class="text-decoration-none">${escapeHtml(lead.company_name)}</a>
            </div>
            <div class="crm-card-meta">${escapeHtml(lead.contact_person || '—')}</div>
            <div class="crm-card-meta">${escapeHtml(lead.owner_name || 'Unassigned')} · ${escapeHtml(lead.source)}</div>
            <div class="mt-1">${daysBadge(lead.days_in_stage)}</div>
            <div class="crm-card-actions">
              <button type="button" class="btn btn-outline-danger btn-sm crm-mark-lost" data-id="${lead.id}" data-company="${escapeHtml(lead.company_name)}">Mark Lost</button>
              <button type="button" class="btn btn-outline-warning btn-sm crm-mark-hold" data-id="${lead.id}" data-company="${escapeHtml(lead.company_name)}">On Hold</button>
            </div>
          </div>`;
    }

    function loadBoard() {
        const params = buildFilterParams('board');
        apiFetch('/leads.php?' + params.toString()).then(function (data) {
            const byStage = {};
            FUNNEL_STAGES.forEach(function (s) { byStage[s.key] = []; });
            data.leads.forEach(function (lead) {
                if (byStage[lead.stage]) byStage[lead.stage].push(lead);
            });

            FUNNEL_STAGES.forEach(function (s) {
                const cardsEl = document.getElementById('cards-' + s.key);
                const leads = byStage[s.key];
                cardsEl.innerHTML = leads.map(cardHtml).join('');
                document.getElementById('count-' + s.key).textContent = leads.length;
            });

            attachCardActionHandlers();
            initSortable();
        }).catch(function (err) {
            alert('Failed to load board: ' + err.message);
        });
    }

    function initSortable() {
        sortableInstances.forEach(function (s) { s.destroy(); });
        sortableInstances = [];

        FUNNEL_STAGES.forEach(function (s) {
            const el = document.getElementById('cards-' + s.key);
            const instance = new Sortable(el, {
                group: 'crm-funnel',
                animation: 150,
                onEnd: function (evt) {
                    const leadId = parseInt(evt.item.getAttribute('data-id'), 10);
                    const toStage = evt.to.getAttribute('data-stage');
                    const fromStage = evt.from.getAttribute('data-stage');
                    if (toStage === fromStage && evt.oldIndex === evt.newIndex) return;
                    if (toStage === fromStage) return; // reordered within the same column — no stage change, nothing to persist

                    apiFetch('/lead_stage.php', {
                        method: 'PUT',
                        body: JSON.stringify({ id: leadId, to_stage: toStage }),
                    }).then(function (result) {
                        if (result.converted_client_id) {
                            alert('Lead awarded — a new client record (#' + result.converted_client_id + ') was created in Client Management.');
                        }
                        loadBoard();
                    }).catch(function (err) {
                        alert('Could not move lead: ' + err.message);
                        loadBoard(); // reload from server to reset the DOM to actual state
                    });
                },
            });
            sortableInstances.push(instance);
        });
    }

    // -------------------------------------------------------------------
    // Lost & On Hold view
    // -------------------------------------------------------------------

    function loadLostOnHold() {
        const params = buildFilterParams('lost_on_hold');
        apiFetch('/leads.php?' + params.toString()).then(function (data) {
            const tbody = document.getElementById('lost-on-hold-tbody');
            if (data.leads.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No leads currently Lost or On Hold.</td></tr>';
                return;
            }
            tbody.innerHTML = data.leads.map(function (lead) {
                const reason = lead.stage === 'lost' ? lead.lost_reason : lead.on_hold_reason;
                const stageLabel = lead.stage === 'lost' ? 'Lost' : 'On Hold';
                return `
                  <tr>
                    <td>${escapeHtml(lead.company_name)}</td>
                    <td>${escapeHtml(lead.contact_person || '—')}</td>
                    <td><span class="badge ${lead.stage === 'lost' ? 'bg-danger' : 'bg-warning text-dark'}">${stageLabel}</span></td>
                    <td>${escapeHtml(reason || '—')}</td>
                    <td>${escapeHtml(lead.owner_name || '—')}</td>
                    <td>${lead.days_in_stage}d</td>
                    <td><button type="button" class="btn btn-sm btn-outline-primary crm-reopen" data-id="${lead.id}">Reopen to Enquiry</button></td>
                  </tr>`;
            }).join('');

            tbody.querySelectorAll('.crm-reopen').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    apiFetch('/lead_stage.php', {
                        method: 'PUT',
                        body: JSON.stringify({ id: parseInt(btn.getAttribute('data-id'), 10), to_stage: 'enquiry' }),
                    }).then(loadLostOnHold).catch(function (err) {
                        alert('Could not reopen lead: ' + err.message);
                    });
                });
            });
        }).catch(function (err) {
            alert('Failed to load Lost & On Hold list: ' + err.message);
        });
    }

    // -------------------------------------------------------------------
    // Mark Lost / On Hold (reason-required) card actions
    // -------------------------------------------------------------------

    function attachCardActionHandlers() {
        document.querySelectorAll('.crm-mark-lost').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openMoveReasonModal(parseInt(btn.getAttribute('data-id'), 10), 'lost', btn.getAttribute('data-company'));
            });
        });
        document.querySelectorAll('.crm-mark-hold').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openMoveReasonModal(parseInt(btn.getAttribute('data-id'), 10), 'on_hold', btn.getAttribute('data-company'));
            });
        });
    }

    function openMoveReasonModal(leadId, toStage, companyName) {
        pendingMove = { id: leadId, to_stage: toStage };
        document.getElementById('moveReasonModalTitle').textContent =
            (toStage === 'lost' ? 'Mark Lost — ' : 'Put On Hold — ') + companyName;
        document.getElementById('move-reason-text').value = '';
        document.getElementById('move-reason-error').classList.add('d-none');
        new bootstrap.Modal(document.getElementById('moveReasonModal')).show();
    }

    document.getElementById('move-reason-submit').addEventListener('click', function () {
        const reason = document.getElementById('move-reason-text').value.trim();
        if (!reason) {
            document.getElementById('move-reason-error').classList.remove('d-none');
            return;
        }
        apiFetch('/lead_stage.php', {
            method: 'PUT',
            body: JSON.stringify({ id: pendingMove.id, to_stage: pendingMove.to_stage, reason: reason }),
        }).then(function () {
            bootstrap.Modal.getInstance(document.getElementById('moveReasonModal')).hide();
            pendingMove = null;
            loadBoard();
        }).catch(function (err) {
            alert('Could not move lead: ' + err.message);
        });
    });

    // -------------------------------------------------------------------
    // Filters
    // -------------------------------------------------------------------

    function buildFilterParams(view) {
        const params = new URLSearchParams();
        params.set('view', view);
        const q = document.getElementById('filter-search').value.trim();
        const ownerId = document.getElementById('filter-owner').value;
        const source = document.getElementById('filter-source').value;
        if (q) params.set('q', q);
        if (ownerId) params.set('owner_id', ownerId);
        if (source) params.set('source', source);
        return params;
    }

    let searchDebounce = null;
    function reloadCurrentView() {
        if (currentView === 'board') loadBoard();
        else loadLostOnHold();
    }

    document.getElementById('filter-search').addEventListener('input', function () {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(reloadCurrentView, 350);
    });
    document.getElementById('filter-owner').addEventListener('change', reloadCurrentView);
    document.getElementById('filter-source').addEventListener('change', reloadCurrentView);

    // -------------------------------------------------------------------
    // View toggle
    // -------------------------------------------------------------------

    document.getElementById('view-board-btn').addEventListener('click', function () {
        currentView = 'board';
        document.getElementById('view-board-btn').className = 'btn btn-primary btn-sm';
        document.getElementById('view-lost-on-hold-btn').className = 'btn btn-outline-secondary btn-sm';
        document.getElementById('board-view').classList.remove('d-none');
        document.getElementById('lost-on-hold-view').classList.add('d-none');
        loadBoard();
    });
    document.getElementById('view-lost-on-hold-btn').addEventListener('click', function () {
        currentView = 'lost_on_hold';
        document.getElementById('view-lost-on-hold-btn').className = 'btn btn-primary btn-sm';
        document.getElementById('view-board-btn').className = 'btn btn-outline-secondary btn-sm';
        document.getElementById('board-view').classList.add('d-none');
        document.getElementById('lost-on-hold-view').classList.remove('d-none');
        loadLostOnHold();
    });

    // -------------------------------------------------------------------
    // Owners (filter dropdown + add-lead form)
    // -------------------------------------------------------------------

    function loadOwners() {
        apiFetch('/users_lookup.php').then(function (data) {
            const filterSel = document.getElementById('filter-owner');
            const formSel = document.getElementById('add-lead-owner');
            formSel.innerHTML = '<option value="">Unassigned</option>';
            data.users.forEach(function (u) {
                const opt1 = document.createElement('option');
                opt1.value = u.id;
                opt1.textContent = u.name;
                filterSel.appendChild(opt1);

                const opt2 = document.createElement('option');
                opt2.value = u.id;
                opt2.textContent = u.name;
                formSel.appendChild(opt2);
            });
        }).catch(function () {});
    }

    // -------------------------------------------------------------------
    // Add Lead
    // -------------------------------------------------------------------

    document.getElementById('add-lead-btn').addEventListener('click', function () {
        document.getElementById('add-lead-form').reset();
        new bootstrap.Modal(document.getElementById('addLeadModal')).show();
    });

    document.getElementById('add-lead-submit').addEventListener('click', function () {
        const form = document.getElementById('add-lead-form');
        if (!form.reportValidity()) return;

        const formData = new FormData(form);
        const payload = {};
        formData.forEach(function (value, key) { payload[key] = value; });

        apiFetch('/leads.php', {
            method: 'POST',
            body: JSON.stringify(payload),
        }).then(function () {
            bootstrap.Modal.getInstance(document.getElementById('addLeadModal')).hide();
            reloadCurrentView();
        }).catch(function (err) {
            alert('Could not add lead: ' + err.message);
        });
    });

    // -------------------------------------------------------------------
    // Init
    // -------------------------------------------------------------------

    renderBoardColumns();
    loadOwners();
    loadBoard();
})();