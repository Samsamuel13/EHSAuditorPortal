// crm/assets/js/crm_lead_detail.js
document.addEventListener('DOMContentLoaded', function () {
    const REASON_STAGES = ['lost', 'on_hold'];
    const leadId = window.CRM_LEAD_ID;
    let currentLead = null;
    let pendingStage = null;

    function loadLead() {
        return crmFetch(CRM_API + '/leads.php?id=' + leadId).then(data => {
            currentLead = data.lead;
            renderLeadInfo();
            renderTimeline();
        }).catch(err => crmToast(err.message, true));
    }

    function renderLeadInfo() {
        const l = currentLead;
        document.getElementById('ld-company').textContent = l.company_name;
        document.getElementById('ld-stage-badge').innerHTML =
            `<span class="badge bg-secondary">${CRM_STAGE_LABELS[l.stage] || l.stage}</span>`;
        document.getElementById('ld-contact').textContent = l.contact_person || '—';
        document.getElementById('ld-phone').textContent = l.phone || '—';
        document.getElementById('ld-email').textContent = l.email || '—';
        document.getElementById('ld-industry').textContent = l.industry_sector || '—';
        document.getElementById('ld-source').textContent = CRM_SOURCE_LABELS[l.source] || l.source;
        document.getElementById('ld-owner').textContent = l.owner_display_name || 'Unassigned';
        document.getElementById('ld-stage-select').value = l.stage;

        const banner = document.getElementById('ld-converted-banner');
        if (l.converted_client_id) {
            banner.classList.remove('d-none');
            banner.innerHTML = `<i class="bi bi-check-circle"></i> Converted to Client Management ` +
                `<a href="${window.EHS_BASE_URL}/client-management/client_detail.php?id=${l.converted_client_id}">#${l.converted_client_id}</a>`;
        } else {
            banner.classList.add('d-none');
        }
    }

    function renderTimeline() {
        Promise.all([
            crmFetch(CRM_API + '/followups.php?lead_id=' + leadId),
            crmFetch(CRM_API + '/quotations.php?lead_id=' + leadId),
        ]).then(([fuData, qtData]) => {
            const events = [];
            (currentLead.stage_history || []).forEach(h => events.push({
                ts: h.changed_at, type: 'stage',
                html: `<strong>${h.from_stage ? (CRM_STAGE_LABELS[h.from_stage] + ' &rarr; ') : ''}${CRM_STAGE_LABELS[h.to_stage] || h.to_stage}</strong>` +
                      (h.reason ? ` — ${crmEscapeHtml(h.reason)}` : '') +
                      ` <span class="text-muted small">by ${crmEscapeHtml(h.changed_by_name || '—')}</span>`,
            }));
            (fuData.followups || []).forEach(f => events.push({
                ts: f.created_at, type: 'followup',
                html: `<i class="bi bi-calendar-check"></i> Follow-up scheduled: ${crmEscapeHtml(f.type)} on ${crmEscapeHtml(f.due_date)}` +
                      (f.note ? ` — ${crmEscapeHtml(f.note)}` : '') +
                      ` <span class="text-muted small">by ${crmEscapeHtml(f.owner_display_name || '—')}</span>`,
            }));
            (qtData.quotations || []).forEach(q => events.push({
                ts: q.created_at, type: 'quotation',
                html: `<i class="bi bi-file-earmark-text"></i> Quotation ${crmEscapeHtml(q.quote_number)} created (v${q.version}, ${crmEscapeHtml(q.currency)} ${q.total})` +
                      ` <span class="text-muted small">by ${crmEscapeHtml(q.created_by_display_name || '—')}</span>`,
            }));
            events.sort((a, b) => new Date(b.ts) - new Date(a.ts));

            document.getElementById('timeline-list').innerHTML = events.length
                ? events.map(e => `<div class="crm-timeline-item"><div class="small text-muted">${crmEscapeHtml((e.ts || '').substring(0, 16).replace('T', ' '))}</div>${e.html}</div>`).join('')
                : '<p class="text-muted">No activity yet.</p>';

            renderFollowups(fuData.followups || []);
            renderQuotations(qtData.quotations || []);
        }).catch(err => crmToast(err.message, true));
    }

    function renderFollowups(followups) {
        document.getElementById('followups-body').innerHTML = followups.map(f => `
            <tr>
                <td>${crmEscapeHtml(f.due_date)}</td>
                <td>${crmEscapeHtml(f.type)}</td>
                <td>${crmEscapeHtml(f.owner_display_name || '—')}</td>
                <td>${crmEscapeHtml(f.note || '—')}</td>
                <td>${f.done ? '<span class="badge bg-success">Done</span>' : (f.due_date < new Date().toISOString().substring(0,10) ? '<span class="badge bg-danger">Overdue</span>' : '<span class="badge bg-warning text-dark">Pending</span>')}</td>
                <td>${f.done ? '' : `<button class="btn btn-sm btn-outline-success" onclick="crmMarkFollowupDone(${f.id})">Mark done</button>`}</td>
            </tr>
        `).join('') || '<tr><td colspan="6" class="text-muted">No follow-ups yet.</td></tr>';
    }
    window.crmMarkFollowupDone = function (id) {
        crmFetch(CRM_API + '/followups.php?id=' + id, { method: 'PUT', body: { done: true } })
            .then(() => { crmToast('Follow-up marked done.'); renderTimeline(); })
            .catch(err => crmToast(err.message, true));
    };

    function renderQuotations(quotations) {
        document.getElementById('quotations-list').innerHTML = quotations.map(q => `
            <div class="card mb-2">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><strong>${crmEscapeHtml(q.quote_number)}</strong> (v${q.version})
                            <span class="badge bg-secondary ms-1">${crmEscapeHtml(q.status)}</span></div>
                        <div>
                            <select class="form-select form-select-sm d-inline-block w-auto" onchange="crmUpdateQuoteStatus(${q.id}, this.value)">
                                ${['draft','sent','accepted','rejected','expired'].map(s => `<option value="${s}" ${s === q.status ? 'selected' : ''}>${s}</option>`).join('')}
                            </select>
                            <a class="btn btn-sm btn-outline-primary" href="${CRM_API}/quotation_pdf.php?id=${q.id}"><i class="bi bi-file-pdf"></i> PDF</a>
                        </div>
                    </div>
                    <div class="small text-muted mt-1">Total: ${crmEscapeHtml(q.currency)} ${q.total} &middot; Valid until: ${crmEscapeHtml(q.valid_until || '—')}</div>
                </div>
            </div>
        `).join('') || '<p class="text-muted">No quotations yet.</p>';
    }
    window.crmUpdateQuoteStatus = function (id, status) {
        crmFetch(CRM_API + '/quotations.php?id=' + id, { method: 'PUT', body: { status } })
            .then(() => { crmToast('Quotation status updated.'); renderTimeline(); })
            .catch(err => crmToast(err.message, true));
    };

    // --- Stage change ---
    document.getElementById('btn-change-stage').addEventListener('click', function () {
        const newStage = document.getElementById('ld-stage-select').value;
        if (newStage === currentLead.stage) return;

        if (REASON_STAGES.includes(newStage)) {
            pendingStage = newStage;
            document.getElementById('reason-modal-title').textContent = 'Reason for moving to ' + CRM_STAGE_LABELS[newStage];
            document.getElementById('reason-text').value = '';
            new bootstrap.Modal(document.getElementById('modal-stage-reason')).show();
            return;
        }
        applyStageChange(newStage, null);
    });
    document.getElementById('btn-reason-confirm').addEventListener('click', function () {
        const reason = document.getElementById('reason-text').value.trim();
        if (!reason) { crmToast('A reason is required.', true); return; }
        applyStageChange(pendingStage, reason);
        bootstrap.Modal.getInstance(document.getElementById('modal-stage-reason')).hide();
    });
    function applyStageChange(stage, reason) {
        const body = { stage };
        if (reason) body.reason = reason;
        crmFetch(CRM_API + '/leads.php?id=' + leadId, { method: 'PUT', body }).then(res => {
            crmToast(res.converted_client_id ? 'Awarded — converted to Client Management #' + res.converted_client_id + '.' : 'Stage updated.');
            loadLead();
        }).catch(err => { crmToast(err.message, true); document.getElementById('ld-stage-select').value = currentLead.stage; });
    }

    // --- Edit lead ---
    crmLoadOwnersInto(document.getElementById('ed-owner'));
    document.getElementById('btn-edit-lead').addEventListener('click', function () {
        const l = currentLead;
        document.getElementById('ed-company').value = l.company_name || '';
        document.getElementById('ed-contact').value = l.contact_person || '';
        document.getElementById('ed-phone').value = l.phone || '';
        document.getElementById('ed-email').value = l.email || '';
        document.getElementById('ed-source').value = l.source;
        document.getElementById('ed-owner').value = l.owner_id || '';
        document.getElementById('ed-industry').value = l.industry_sector || '';
        document.getElementById('ed-notes').value = l.notes || '';
    });
    document.getElementById('btn-save-edit').addEventListener('click', function () {
        const body = {
            company_name: document.getElementById('ed-company').value.trim(),
            contact_person: document.getElementById('ed-contact').value.trim(),
            phone: document.getElementById('ed-phone').value.trim(),
            email: document.getElementById('ed-email').value.trim(),
            source: document.getElementById('ed-source').value,
            owner_id: document.getElementById('ed-owner').value || 0,
            industry_sector: document.getElementById('ed-industry').value.trim(),
            notes: document.getElementById('ed-notes').value.trim(),
        };
        crmFetch(CRM_API + '/leads.php?id=' + leadId, { method: 'PUT', body }).then(() => {
            bootstrap.Modal.getInstance(document.getElementById('modal-edit-lead')).hide();
            crmToast('Lead updated.');
            loadLead();
        }).catch(err => crmToast(err.message, true));
    });

    // --- New follow-up ---
    crmLoadOwnersInto(document.getElementById('fu-owner'));
    document.getElementById('btn-save-followup').addEventListener('click', function () {
        const due = document.getElementById('fu-due').value;
        if (!due) { crmToast('Due date is required.', true); return; }
        const body = {
            lead_id: leadId, due_date: due,
            type: document.getElementById('fu-type').value,
            owner_id: document.getElementById('fu-owner').value || null,
            note: document.getElementById('fu-note').value.trim(),
        };
        crmFetch(CRM_API + '/followups.php', { method: 'POST', body }).then(() => {
            bootstrap.Modal.getInstance(document.getElementById('modal-new-followup')).hide();
            document.getElementById('fu-due').value = '';
            document.getElementById('fu-note').value = '';
            crmToast('Follow-up added.');
            renderTimeline();
        }).catch(err => crmToast(err.message, true));
    });

    // --- New quotation ---
    function addItemRow() {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="text" class="form-control form-control-sm qt-desc"></td>
            <td><input type="number" step="0.01" class="form-control form-control-sm qt-qty" value="1"></td>
            <td><input type="number" step="0.01" class="form-control form-control-sm qt-price" value="0"></td>
            <td><button class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); crmRecalcQuoteTotal();">&times;</button></td>
        `;
        document.getElementById('qt-items-body').appendChild(row);
    }
    document.getElementById('btn-add-item').addEventListener('click', addItemRow);
    document.getElementById('modal-new-quotation').addEventListener('shown.bs.modal', function () {
        document.getElementById('qt-items-body').innerHTML = '';
        addItemRow();
    });
    document.getElementById('qt-items-body').addEventListener('input', crmRecalcQuoteTotal);
    document.getElementById('qt-tax').addEventListener('input', crmRecalcQuoteTotal);
    window.crmRecalcQuoteTotal = function () {
        let subtotal = 0;
        document.querySelectorAll('#qt-items-body tr').forEach(row => {
            const qty = parseFloat(row.querySelector('.qt-qty').value) || 0;
            const price = parseFloat(row.querySelector('.qt-price').value) || 0;
            subtotal += qty * price;
        });
        const taxPct = parseFloat(document.getElementById('qt-tax').value) || 0;
        const total = subtotal + (subtotal * taxPct / 100);
        document.getElementById('qt-total-preview').textContent = 'Total: SGD ' + total.toFixed(2);
    };

    document.getElementById('btn-save-quotation').addEventListener('click', function () {
        const items = [...document.querySelectorAll('#qt-items-body tr')].map(row => ({
            description: row.querySelector('.qt-desc').value.trim(),
            qty: parseFloat(row.querySelector('.qt-qty').value) || 0,
            unit_price: parseFloat(row.querySelector('.qt-price').value) || 0,
        })).filter(i => i.description);

        if (!items.length) { crmToast('At least one line item with a description is required.', true); return; }

        const body = {
            lead_id: leadId,
            valid_until: document.getElementById('qt-valid-until').value || null,
            tax_percent: parseFloat(document.getElementById('qt-tax').value) || 0,
            notes: document.getElementById('qt-notes').value.trim(),
            items,
        };
        crmFetch(CRM_API + '/quotations.php', { method: 'POST', body }).then(() => {
            bootstrap.Modal.getInstance(document.getElementById('modal-new-quotation')).hide();
            crmToast('Quotation version created.');
            loadLead();
        }).catch(err => crmToast(err.message, true));
    });

    loadLead();
});
