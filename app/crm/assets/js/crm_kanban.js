// crm/assets/js/crm_kanban.js
document.addEventListener('DOMContentLoaded', function () {
    const STAGES = ['enquiry', 'lead', 'quotation', 'negotiation', 'awarded', 'lost', 'on_hold'];
    const REASON_STAGES = ['lost', 'on_hold'];
    let leadsCache = [];
    let pendingMove = null; // { leadId, fromStage, toStage, cardEl, originalColumnBody }

    function loadDashboard() {
        crmFetch(CRM_API + '/dashboard.php').then(data => {
            document.getElementById('w-new-enquiries').textContent = data.new_enquiries_this_week;
            document.getElementById('w-overdue-followups').textContent = data.overdue_followups;
            document.getElementById('w-quotations-awaiting').textContent = data.quotations_awaiting_response;
            const wr = data.win_rate_this_month;
            document.getElementById('w-win-rate').textContent = wr.percent === null ? '—' : wr.percent + '%';
            document.getElementById('w-win-rate-detail').textContent = wr.closed > 0 ? `${wr.awarded} awarded of ${wr.closed} closed` : 'No closed leads yet';
        }).catch(() => {});
    }

    function buildBoard() {
        const board = document.getElementById('board');
        board.innerHTML = STAGES.map(stage => `
            <div class="crm-column" data-stage="${stage}">
                <div class="crm-column-header">
                    <span>${CRM_STAGE_LABELS[stage]}</span>
                    <span class="badge bg-secondary" id="col-count-${stage}">0</span>
                </div>
                <div class="crm-column-body" id="col-body-${stage}" data-stage="${stage}"></div>
            </div>
        `).join('');

        STAGES.forEach(stage => {
            new Sortable(document.getElementById('col-body-' + stage), {
                group: 'crm-board',
                animation: 150,
                onEnd: handleCardMoved,
            });
        });
    }

    function renderCards() {
        STAGES.forEach(stage => {
            const body = document.getElementById('col-body-' + stage);
            const items = leadsCache.filter(l => l.stage === stage);
            document.getElementById('col-count-' + stage).textContent = items.length;
            body.innerHTML = items.map(l => `
                <div class="crm-card" data-lead-id="${l.id}" data-stage="${l.stage}" onclick="window.location.href='${window.EHS_BASE_URL}/crm/lead_detail.php?id=${l.id}'">
                    <div class="crm-card-company">${crmEscapeHtml(l.company_name)}</div>
                    <div class="crm-card-meta">${crmEscapeHtml(l.contact_person || '—')}</div>
                    <div class="crm-card-meta"><i class="bi bi-person-badge"></i> ${crmEscapeHtml(l.owner_display_name || 'Unassigned')} &middot; ${CRM_SOURCE_LABELS[l.source] || l.source}</div>
                </div>
            `).join('');
        });
    }

    function loadLeads() {
        crmFetch(CRM_API + '/leads.php').then(data => {
            leadsCache = data.leads || [];
            renderCards();
        }).catch(err => crmToast(err.message, true));
    }

    function handleCardMoved(evt) {
        const cardEl = evt.item;
        const leadId = parseInt(cardEl.dataset.leadId, 10);
        const fromStage = evt.from.dataset.stage;
        const toStage = evt.to.dataset.stage;
        if (fromStage === toStage) return;

        // Clicking triggers navigation via onclick; a drag shouldn't. Since the
        // card itself carries the onclick, a completed drag can still fire a
        // click on drop in some browsers — harmless here since onEnd already
        // has the authoritative from/to, but we stop the pending nav just in case.
        cardEl.onclick = null;

        if (REASON_STAGES.includes(toStage)) {
            pendingMove = { leadId, fromStage, toStage, cardEl };
            document.getElementById('reason-modal-title').textContent =
                'Reason for moving to ' + CRM_STAGE_LABELS[toStage];
            document.getElementById('reason-modal-label').textContent =
                CRM_STAGE_LABELS[toStage] + ' reason (required)';
            document.getElementById('reason-text').value = '';
            new bootstrap.Modal(document.getElementById('modal-stage-reason')).show();
            return;
        }

        commitStageChange(leadId, toStage, null, cardEl);
    }

    function commitStageChange(leadId, toStage, reason, cardEl) {
        const body = { stage: toStage };
        if (reason) body.reason = reason;

        crmFetch(CRM_API + '/leads.php?id=' + leadId, { method: 'PUT', body })
            .then(res => {
                const lead = leadsCache.find(l => l.id === leadId);
                if (lead) lead.stage = toStage;
                if (cardEl) cardEl.dataset.stage = toStage;
                document.getElementById('col-count-' + toStage).textContent =
                    parseInt(document.getElementById('col-count-' + toStage).textContent, 10);
                loadLeads(); // re-sync counts/cards cleanly
                if (res.converted_client_id) {
                    crmToast('Lead awarded — converted to Client Management #' + res.converted_client_id + '.');
                } else {
                    crmToast('Stage updated.');
                }
                loadDashboard();
            })
            .catch(err => {
                crmToast(err.message, true);
                loadLeads(); // revert the visual move by re-rendering from server truth
            });
    }

    document.getElementById('btn-reason-confirm').addEventListener('click', function () {
        const reason = document.getElementById('reason-text').value.trim();
        if (!reason) { crmToast('A reason is required.', true); return; }
        if (pendingMove) {
            commitStageChange(pendingMove.leadId, pendingMove.toStage, reason, pendingMove.cardEl);
            pendingMove = null;
        }
        bootstrap.Modal.getInstance(document.getElementById('modal-stage-reason')).hide();
    });
    ['btn-reason-cancel'].forEach(id => document.getElementById(id).addEventListener('click', () => {
        pendingMove = null;
        loadLeads(); // snap the card back since the move wasn't confirmed
    }));

    // --- New Lead modal ---
    let dupConfirmed = false;
    crmLoadOwnersInto(document.getElementById('nl-owner'));

    document.getElementById('btn-save-lead').addEventListener('click', function () {
        const company = document.getElementById('nl-company').value.trim();
        if (!company) { crmToast('Company name is required.', true); return; }
        const email = document.getElementById('nl-email').value.trim();
        const phone = document.getElementById('nl-phone').value.trim();

        const params = new URLSearchParams({ check_duplicates: '1', company, email, phone });
        crmFetch(CRM_API + '/leads.php?' + params.toString()).then(dupData => {
            const dups = dupData.possible_duplicates || [];
            const warnEl = document.getElementById('dup-warning');
            if (dups.length && !dupConfirmed) {
                warnEl.classList.remove('d-none');
                warnEl.innerHTML = '<strong>Possible duplicate(s) found:</strong><ul class="mb-1">' +
                    dups.map(d => `<li>${crmEscapeHtml(d.company_name)} (${d.source_table === 'cm_clients' ? 'existing client' : 'existing lead'})</li>`).join('') +
                    '</ul>Click "Create Anyway" to proceed.';
                document.getElementById('btn-save-lead').textContent = 'Create Anyway';
                dupConfirmed = true;
                return;
            }

            const payload = {
                company_name: company,
                contact_person: document.getElementById('nl-contact').value.trim(),
                phone, email,
                source: document.getElementById('nl-source').value,
                owner_id: document.getElementById('nl-owner').value || null,
                industry_sector: document.getElementById('nl-industry').value.trim(),
                notes: document.getElementById('nl-notes').value.trim(),
            };
            crmFetch(CRM_API + '/leads.php', { method: 'POST', body: payload }).then(() => {
                bootstrap.Modal.getInstance(document.getElementById('modal-new-lead')).hide();
                resetNewLeadForm();
                crmToast('Lead created.');
                loadLeads();
                loadDashboard();
            }).catch(err => crmToast(err.message, true));
        }).catch(err => crmToast(err.message, true));
    });

    function resetNewLeadForm() {
        ['nl-company', 'nl-contact', 'nl-phone', 'nl-email', 'nl-industry', 'nl-notes'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('nl-source').value = 'other';
        document.getElementById('nl-owner').value = '';
        document.getElementById('dup-warning').classList.add('d-none');
        document.getElementById('btn-save-lead').textContent = 'Create Lead';
        dupConfirmed = false;
    }
    document.getElementById('modal-new-lead').addEventListener('hidden.bs.modal', resetNewLeadForm);

    buildBoard();
    loadLeads();
    loadDashboard();
});
