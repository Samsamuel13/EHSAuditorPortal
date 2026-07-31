document.addEventListener('DOMContentLoaded', function () {
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : str;
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        return new Date(dateStr + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
    }

    const toastEl = document.getElementById('toast');
    let toastTimer = null;
    function showToast(message, isError = false) {
        if (!toastEl) return;
        clearTimeout(toastTimer);
        toastEl.textContent = message;
        toastEl.classList.toggle('error', isError);
        toastEl.classList.remove('hidden');
        toastTimer = setTimeout(() => toastEl.classList.add('hidden'), 3500);
    }

    // --- admin-wide dashboard data ---
    fetch(window.EHS_BASE_URL + '/api/dashboard_admin.php')
        .then(r => r.json())
        .then(data => {
            document.getElementById('audits-month-title').textContent = `Audits this month (${data.month_label})`;
            document.getElementById('audits-total').textContent = data.audits_this_month.total;

            const statusEl = document.getElementById('audits-by-status');
            const byStatus = data.audits_this_month.by_status;
            statusEl.innerHTML = Object.keys(byStatus).map(status =>
                `<span class="status-pill">${status}: ${byStatus[status]}</span>`
            ).join('');

            renderPending(data.pending_audits || []);
            renderOverdueCompletions(data.overdue_completions || []);
            renderHolidays(data.upcoming_holidays || []);
            renderUtilization(data.utilization || []);
        })
        .catch(() => {
            document.getElementById('audits-total').textContent = '—';
        });

    function renderPending(pending) {
        const el = document.getElementById('pending-list');
        if (pending.length === 0) {
            el.innerHTML = '<p class="empty-note">Nothing pending confirmation.</p>';
            return;
        }
        el.innerHTML = pending.map(p => `
            <div class="list-item">
                <div>
                    <div class="list-item-main">${escapeHtml(p.client_name)}</div>
                    <div class="list-item-sub">${escapeHtml(p.auditor_names || 'Unassigned')}</div>
                </div>
                <div class="list-item-sub" style="text-align:right;">
                    ${formatDate(p.audit_date)}<br>${p.session === 'FULL_DAY' ? 'Full day' : p.session}
                    ${p.is_overdue ? '<br><span class="overdue-badge">OVERDUE</span>' : ''}
                </div>
            </div>
        `).join('');
    }

    function renderOverdueCompletions(items) {
        const el = document.getElementById('overdue-completions-list');
        if (!el) return; // page hasn't been updated with this card yet
        if (items.length === 0) {
            el.innerHTML = '<p class="empty-note">Nothing overdue for completion.</p>';
            return;
        }
        el.innerHTML = items.map(o => `
            <div class="list-item">
                <div>
                    <div class="list-item-main">${escapeHtml(o.client_name)}</div>
                    <div class="list-item-sub">${escapeHtml(o.auditor_names || 'Unassigned')}</div>
                </div>
                <div class="list-item-sub" style="text-align:right;">
                    ${formatDate(o.audit_date)}<br>${o.session === 'FULL_DAY' ? 'Full day' : o.session}
                    <br><span class="overdue-badge">OVERDUE</span>
                </div>
            </div>
        `).join('');
    }

    function renderHolidays(holidays) {
        const el = document.getElementById('holidays-list');
        if (holidays.length === 0) {
            el.innerHTML = '<p class="empty-note">No upcoming holidays on file.</p>';
            return;
        }
        el.innerHTML = holidays.map(h => `
            <div class="list-item">
                <div class="list-item-main">${escapeHtml(h.name)}</div>
                <div class="list-item-sub">${formatDate(h.date)}</div>
            </div>
        `).join('');
    }

    function renderUtilization(utilization) {
        const el = document.getElementById('utilization-list');
        if (utilization.length === 0) {
            el.innerHTML = '<p class="empty-note">No active auditors found.</p>';
            return;
        }
        el.innerHTML = utilization.map(u => `
            <div class="util-row">
                <span class="util-name">${escapeHtml(u.name)}</span>
                <div class="util-bar-track">
                    <div class="util-bar-fill" style="width:${Math.min(u.percent, 100)}%; background:${u.color_hex}"></div>
                </div>
                <span class="util-percent">${u.percent}%</span>
            </div>
        `).join('');
    }

    // --- "my" assignments, in case this admin/super admin also audits ---
    const MY_STATUS_LABELS = { scheduled: 'Scheduled', confirmed: 'Confirmed', completed: 'Completed', cancelled: 'Cancelled' };
    const MY_STATUS_BADGE_CLASS = { scheduled: 'badge-role', confirmed: 'badge-active', completed: 'badge-active', cancelled: 'badge-inactive' };
    function myActionsFor(status) {
        if (status === 'scheduled')  return [['✓ Confirm', 'confirmed', 'btn-primary'], ['✕ Cancel', 'cancelled', 'btn-danger']];
        if (status === 'confirmed')  return [['✓ Mark Completed', 'completed', 'btn-primary'], ['✕ Cancel', 'cancelled', 'btn-danger']];
        if (status === 'cancelled')  return [['↺ Reopen', 'scheduled', 'btn-ghost-light']];
        return [];
    }

    function loadMyUpcoming() {
        fetch(window.EHS_BASE_URL + '/api/dashboard_auditor.php')
            .then(r => r.json())
            .then(data => {
                const el = document.getElementById('my-upcoming-list');
                const upcoming = data.upcoming || [];
                if (upcoming.length === 0) {
                    el.innerHTML = '<p class="empty-note">No upcoming assignments of your own.</p>';
                    return;
                }

                el.innerHTML = upcoming.map(a => {
                    const actions = myActionsFor(a.status);
                    const buttonsHtml = actions.map(([label, targetStatus, cls]) =>
                        `<button type="button" class="btn ${cls} btn-small my-status-action-btn" data-audit-id="${a.id}" data-target-status="${targetStatus}" style="width:auto; margin:2px 0 0 0;">${label}</button>`
                    ).join(' ');

                    return `
                    <div class="list-item">
                        <div>
                            <div class="list-item-main">${escapeHtml(a.client_name)}</div>
                            <div class="list-item-sub">${(a.schemes || []).join(', ')}</div>
                        </div>
                        <div class="list-item-sub" style="text-align:right;">
                            ${formatDate(a.audit_date)}<br>${a.session === 'FULL_DAY' ? 'Full day' : a.session}
                            <br>
                            <span class="badge ${MY_STATUS_BADGE_CLASS[a.status] || 'badge-role'}">${MY_STATUS_LABELS[a.status] || a.status}</span>
                            <div>${buttonsHtml}</div>
                        </div>
                    </div>`;
                }).join('');

                el.querySelectorAll('.my-status-action-btn').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const auditId = this.dataset.auditId;
                        const newStatus = this.dataset.targetStatus;
                        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                        fetch(`${window.EHS_BASE_URL}/api/audit_status.php?id=${auditId}`, {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                            body: JSON.stringify({ status: newStatus }),
                        })
                            .then(r => r.json().then(body => ({ ok: r.ok, body })))
                            .then(({ ok, body }) => {
                                if (!ok) { showToast(body.error || 'Could not update status.', true); return; }
                                showToast('Marked as ' + MY_STATUS_LABELS[newStatus] + '.');
                                loadMyUpcoming();
                            })
                            .catch(() => showToast('Network error — please try again.', true));
                    });
                });
            });
    }
    loadMyUpcoming();
});
