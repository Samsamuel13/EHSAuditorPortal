document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

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

    function loadDashboard() {
        fetch(window.EHS_BASE_URL + '/api/dashboard_auditor.php')
            .then(r => r.json())
            .then(data => {
                renderUpcoming(data.upcoming || []);
                renderAvailabilitySummary(data.availability_summary || {});
            });
    }
    loadDashboard();

    const STATUS_LABELS = { scheduled: 'Scheduled', confirmed: 'Confirmed', completed: 'Completed', cancelled: 'Cancelled' };
    const STATUS_BADGE_CLASS = { scheduled: 'badge-role', confirmed: 'badge-active', completed: 'badge-active', cancelled: 'badge-inactive' };

    // Which action buttons to show for a given current status. Each action
    // is [button label, target status, button style class].
    function actionsFor(status) {
        if (status === 'scheduled')  return [['✓ Confirm', 'confirmed', 'btn-primary'], ['✕ Cancel', 'cancelled', 'btn-danger']];
        if (status === 'confirmed')  return [['✓ Mark Completed', 'completed', 'btn-primary'], ['✕ Cancel', 'cancelled', 'btn-danger']];
        if (status === 'cancelled')  return [['↺ Reopen', 'scheduled', 'btn-ghost-light']];
        return []; // 'completed' is a terminal state — nothing to do
    }

    function renderUpcoming(upcoming) {
        const el = document.getElementById('upcoming-list');
        if (upcoming.length === 0) {
            el.innerHTML = '<p class="empty-note">No upcoming assignments.</p>';
            return;
        }
        el.innerHTML = upcoming.map(a => {
            const actions = actionsFor(a.status);
            const buttonsHtml = actions.map(([label, targetStatus, cls]) =>
                `<button type="button" class="btn ${cls} btn-small status-action-btn" data-audit-id="${a.id}" data-target-status="${targetStatus}" style="width:auto; margin:2px 0 0 0;">${label}</button>`
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
                    <span class="badge ${STATUS_BADGE_CLASS[a.status] || 'badge-role'}">${STATUS_LABELS[a.status] || a.status}</span>
                    ${a.is_overdue ? '<span class="overdue-badge">OVERDUE</span>' : ''}
                    <div>${buttonsHtml}</div>
                </div>
            </div>`;
        }).join('');

        el.querySelectorAll('.status-action-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const auditId = this.dataset.auditId;
                const newStatus = this.dataset.targetStatus;
                fetch(`${window.EHS_BASE_URL}/api/audit_status.php?id=${auditId}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify({ status: newStatus }),
                })
                    .then(r => r.json().then(body => ({ ok: r.ok, body })))
                    .then(({ ok, body }) => {
                        if (!ok) { showToast(body.error || 'Could not update status.', true); return; }
                        showToast('Marked as ' + STATUS_LABELS[newStatus] + '.');
                        loadDashboard();
                    })
                    .catch(() => showToast('Network error — please try again.', true));
            });
        });
    }

    function renderAvailabilitySummary(summary) {
        const el = document.getElementById('availability-summary');
        const months = [summary.this_month, summary.next_month].filter(Boolean);
        if (months.length === 0) {
            el.innerHTML = '<p class="empty-note">No data.</p>';
            return;
        }

        el.innerHTML = months.map(m => {
            const total = m.working_days || 1;
            const pct = (n) => (n / total * 100).toFixed(0);
            return `
                <div class="avail-month-block">
                    <strong>${escapeHtml(m.label)}</strong>
                    <div class="avail-bar">
                        <div class="avail-bar-seg available" style="width:${pct(m.available)}%"></div>
                        <div class="avail-bar-seg unavailable" style="width:${pct(m.unavailable)}%"></div>
                        <div class="avail-bar-seg tentative" style="width:${pct(m.tentative)}%"></div>
                        <div class="avail-bar-seg not_set" style="width:${pct(m.not_set)}%"></div>
                    </div>
                    <div class="avail-legend-small">
                        <span>🟢 ${m.available} available</span>
                        <span>🔴 ${m.unavailable} unavailable</span>
                        <span>🟡 ${m.tentative} tentative</span>
                        <span>⚪ ${m.not_set} not set</span>
                    </div>
                </div>
            `;
        }).join('');
    }
});
