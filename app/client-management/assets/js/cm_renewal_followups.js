// client-management/assets/js/cm_renewal_followups.js
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const API_BASE = window.EHS_BASE_URL + '/client-management/api';
    const PAGE_SIZE = 15;

    let activeCertIdForModal = null;
    let allDueItems = [];       // full due-today list, fetched once per filter/reload
    let activeStage = null;     // which stage card is currently open (1-4), or null = list section hidden
    let currentPage = 1;
    let searchTerm = '';

    const toastEl = document.getElementById('toast');
    let toastTimer = null;
    function showToast(message, isError) {
        if (!toastEl) return;
        clearTimeout(toastTimer);
        toastEl.textContent = message;
        toastEl.classList.toggle('error', !!isError);
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

    const STAGE_LABELS = { 1: 'Stage 1 (4 months)', 2: 'Stage 2 (2 months)', 3: 'Stage 3 (30 days)', 4: 'Stage 4 (4 days)' };
    const MILESTONE_LABELS = { surveillance_1: 'Surveillance 1', surveillance_2: 'Surveillance 2', recertification: 'Recertification' };

    // Reuses the SAME filter inputs the certifications table below already
    // has (Entity/Scheme/Industry/Responsible) — one shared filter bar,
    // both sections stay consistent with each other.
    function currentFilters() {
        return {
            scheme_category: document.getElementById('filter-scheme-category').value,
            industry: document.getElementById('filter-industry').value.trim(),
            entity: document.getElementById('filter-entity').value,
            responsible_person_id: document.getElementById('filter-responsible').value,
        };
    }

    function loadFollowupsToday() {
        const params = new URLSearchParams(currentFilters());
        fetch(API_BASE + '/renewal_followups.php?' + params.toString())
            .then(r => r.json())
            .then(data => {
                allDueItems = data.today || [];
                renderStageCounts();
                if (activeStage) renderFollowupList(); // keep whatever's open in sync after a filter change/reload
            })
            .catch(() => showToast('Could not load today\'s follow-ups.', true));
    }

    function renderStageCounts() {
        const counts = { 1: 0, 2: 0, 3: 0, 4: 0 };
        allDueItems.forEach(item => { counts[item.stage] = (counts[item.stage] || 0) + 1; });
        document.getElementById('followup-today-count').textContent = allDueItems.length;
        [1, 2, 3, 4].forEach(s => { document.getElementById('stage-count-' + s).textContent = counts[s]; });
    }

    // --- Stage card click -> open the filtered, paginated list for that stage ---
    document.querySelectorAll('.followup-stage-card').forEach(card => {
        card.addEventListener('click', function () {
            const stage = parseInt(this.dataset.stage, 10);
            activeStage = (activeStage === stage) ? null : stage; // click again to close
            currentPage = 1;
            document.getElementById('followup-list-section').classList.toggle('hidden', activeStage === null);
            document.querySelectorAll('.followup-stage-card').forEach(c => c.style.outline = '');
            if (activeStage !== null) {
                this.style.outline = '2px solid var(--brand, #283891)';
                renderFollowupList();
            }
        });
    });
    document.getElementById('followup-list-close').addEventListener('click', function () {
        activeStage = null;
        document.getElementById('followup-list-section').classList.add('hidden');
        document.querySelectorAll('.followup-stage-card').forEach(c => c.style.outline = '');
    });

    let searchDebounce;
    document.getElementById('followup-search').addEventListener('input', function () {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => {
            searchTerm = this.value.trim().toLowerCase();
            currentPage = 1;
            renderFollowupList();
        }, 250);
    });

    function renderFollowupList() {
        document.getElementById('followup-list-title').textContent = STAGE_LABELS[activeStage] || ('Stage ' + activeStage);

        let items = allDueItems.filter(i => i.stage === activeStage);
        if (searchTerm) {
            items = items.filter(i => i.company_name.toLowerCase().includes(searchTerm));
        }
        // Most overdue first within the stage.
        items.sort((a, b) => a.days_until - b.days_until);

        const totalPages = Math.max(1, Math.ceil(items.length / PAGE_SIZE));
        currentPage = Math.min(currentPage, totalPages);
        const pageItems = items.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE);

        document.getElementById('followup-today-empty').classList.toggle('hidden', items.length > 0);

        const body = document.getElementById('followup-today-body');
        body.innerHTML = pageItems.map(item => {
            const dueText = item.overdue ? `${Math.abs(item.days_until)}d overdue` : `in ${item.days_until}d`;
            return `
            <tr>
                <td><a href="${window.EHS_BASE_URL}/client-management/client_detail.php?id=${item.client_id}">${escapeHtml(item.company_name)}</a></td>
                <td>${escapeHtml(item.entity || 'EHS')}</td>
                <td>${escapeHtml(item.scheme_name)} — ${escapeHtml(MILESTONE_LABELS[item.milestone_key] || item.milestone_label)}</td>
                <td>${escapeHtml(item.milestone_date)} <span style="color:${item.overdue ? '#b91c1c' : '#6b7280'}; font-size:0.78rem;">(${dueText})</span></td>
                <td>${escapeHtml(item.responsible_person || '—')}</td>
                <td>
                    <button class="btn btn-primary btn-small" style="width:auto;" onclick="cmOpenStageFollowupModal(${item.cert_id}, '${escapeHtml(item.company_name).replace(/'/g, "\\'")}')">Mark Followed Up</button>
                    <button class="btn btn-ghost-light btn-small" style="width:auto; margin-left:4px;" onclick="cmOpenFollowupHistory(${item.cert_id}, '${escapeHtml(item.company_name).replace(/'/g, "\\'")}')">History</button>
                </td>
            </tr>`;
        }).join('');

        renderPaginationControls('followup-pagination', currentPage, totalPages, (p) => { currentPage = p; renderFollowupList(); });
    }

    /** Shared, minimal pagination control renderer — Prev / page X of Y / Next. */
    function renderPaginationControls(containerId, page, totalPages, onChange) {
        const el = document.getElementById(containerId);
        if (totalPages <= 1) { el.innerHTML = ''; return; }
        el.innerHTML = `
            <button class="btn btn-ghost-light btn-small" style="width:auto;" ${page <= 1 ? 'disabled' : ''} id="${containerId}-prev">‹ Prev</button>
            <span>Page ${page} of ${totalPages}</span>
            <button class="btn btn-ghost-light btn-small" style="width:auto;" ${page >= totalPages ? 'disabled' : ''} id="${containerId}-next">Next ›</button>
        `;
        const prevBtn = document.getElementById(containerId + '-prev');
        const nextBtn = document.getElementById(containerId + '-next');
        if (prevBtn) prevBtn.addEventListener('click', () => onChange(page - 1));
        if (nextBtn) nextBtn.addEventListener('click', () => onChange(page + 1));
    }
    window.cmRenderPaginationControls = renderPaginationControls; // shared with cm_renewal_dashboard.js

    // --- Mark followed-up modal ---
    const stageModal = document.getElementById('stage-followup-modal-overlay');
    window.cmOpenStageFollowupModal = function (certId, companyName) {
        activeCertIdForModal = certId;
        document.getElementById('stage-followup-modal-title').textContent = 'Mark Follow-up — ' + companyName;
        document.getElementById('stage-followup-note').value = '';
        stageModal.classList.remove('hidden');
    };
    document.getElementById('stage-followup-modal-close').addEventListener('click', () => stageModal.classList.add('hidden'));
    stageModal.addEventListener('click', function (e) { if (e.target === this) this.classList.add('hidden'); });

    document.getElementById('stage-followup-save-btn').addEventListener('click', function () {
        const note = document.getElementById('stage-followup-note').value.trim();
        if (!note) { showToast('A note is required.', true); return; }
        if (!activeCertIdForModal) return;

        fetch(API_BASE + '/renewal_followups.php', {
            method: 'POST', headers: csrfHeaders(),
            body: JSON.stringify({ cert_id: activeCertIdForModal, note }),
        })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not mark this follow-up.', true); return; }
                stageModal.classList.add('hidden');
                showToast(body.superseded_stages && body.superseded_stages.length
                    ? `Marked followed up (also cleared stage ${body.superseded_stages.join(', ')}).`
                    : 'Marked followed up.');
                loadFollowupsToday();
            })
            .catch(() => showToast('Network error — please try again.', true));
    });

    // --- Per-company history modal ---
    const historyModal = document.getElementById('followup-history-modal-overlay');
    window.cmOpenFollowupHistory = function (certId, companyName) {
        document.getElementById('followup-history-modal-title').textContent = 'Follow-up History — ' + companyName;
        document.getElementById('followup-history-list').innerHTML = '<div class="cm-note-empty">Loading…</div>';
        historyModal.classList.remove('hidden');

        fetch(`${API_BASE}/renewal_followups.php?cert_id=${certId}&history=1`)
            .then(r => r.json())
            .then(data => renderFollowupHistory(data.history || []))
            .catch(() => showToast('Could not load follow-up history.', true));
    };
    document.getElementById('followup-history-modal-close').addEventListener('click', () => historyModal.classList.add('hidden'));
    historyModal.addEventListener('click', function (e) { if (e.target === this) this.classList.add('hidden'); });

    function renderFollowupHistory(rows) {
        const listEl = document.getElementById('followup-history-list');
        if (!rows.length) {
            listEl.innerHTML = '<div class="cm-note-empty">No follow-up stages actioned yet for this certification.</div>';
            return;
        }
        listEl.innerHTML = rows.map(r => {
            let statusLine;
            if (r.reverted_at) {
                statusLine = `<span style="color:#9ca3af; font-style:italic;">Reverted ${escapeHtml((r.reverted_at || '').substring(0, 16).replace('T', ' '))}</span>`;
            } else if (r.followed_up_at) {
                const via = r.resolution_type === 'milestone_done' ? ' (milestone marked done)'
                    : r.resolution_type === 'stage_superseded' ? ' (cleared by a later stage)' : '';
                statusLine = `<span style="color:#16a34a;">✅ Followed up ${escapeHtml((r.followed_up_at || '').substring(0, 16).replace('T', ' '))} by ${escapeHtml(r.followed_up_by_display_name || '—')}${via}</span>`;
            } else {
                statusLine = '<span style="color:#b45309;">Open</span>';
            }
            return `
            <div class="cm-note-entry">
                <div class="cm-note-meta">${escapeHtml(MILESTONE_LABELS[r.milestone_key] || r.milestone_key)} — ${escapeHtml(STAGE_LABELS[r.stage] || 'Stage ' + r.stage)} &middot; due ${escapeHtml(r.milestone_date)}</div>
                <div>${statusLine}</div>
                ${r.note ? `<div style="margin-top:2px; color:#374151;">${escapeHtml(r.note)}</div>` : ''}
                ${(r.followed_up_at && !r.reverted_at && r.resolution_type === 'followed_up') ? `<button class="cm-undo-btn" data-action-id="${r.id}" style="margin-top:4px; font-size:0.75rem; padding:1px 8px; border-radius:4px; border:1px solid #d1d5db; background:#fff; cursor:pointer;">Undo</button>` : ''}
            </div>`;
        }).join('');

        listEl.querySelectorAll('.cm-undo-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const actionId = this.dataset.actionId;
                if (!confirm('Undo this follow-up? Any earlier stage it cleared will reopen too.')) return;
                fetch(`${API_BASE}/renewal_followups.php?id=${actionId}`, {
                    method: 'PUT', headers: csrfHeaders(), body: JSON.stringify({ action: 'undo' }),
                })
                    .then(r => r.json().then(body => ({ ok: r.ok, body })))
                    .then(({ ok, body }) => {
                        if (!ok) { showToast(body.error || 'Could not undo.', true); return; }
                        showToast('Follow-up reverted.' + (body.reopened_count ? ` Reopened ${body.reopened_count} earlier stage(s).` : ''));
                        loadFollowupsToday();
                    })
                    .catch(() => showToast('Network error — please try again.', true));
            });
        });
    }

    // Re-load when the shared filters change (the certifications table's
    // own JS also listens to these same inputs independently).
    ['filter-scheme-category', 'filter-industry', 'filter-entity', 'filter-responsible'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        let t;
        el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', () => {
            clearTimeout(t);
            t = setTimeout(loadFollowupsToday, 300);
        });
    });

    loadFollowupsToday();
});