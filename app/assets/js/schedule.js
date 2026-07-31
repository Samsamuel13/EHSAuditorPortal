document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    // -------------------------------------------------------------------
    // State
    // -------------------------------------------------------------------
    let auditorsCache = [];      // base list, no date/session context
    let schemesCache = [];
    let auditsCache = [];        // current filtered range
    let auditsById = {};
    let holidaysByDate = {};
    let teamAvailability = {};   // date -> auditor_id -> [ { session, status }, ... ]
    let currentView = 'calendar';
    let rangeStart = '';
    let rangeEnd = '';
    let editingAuditId = null;
    let editingAuditUpdatedAt = null;   // for optimistic locking
    let originalAuditorIds = new Set(); // to detect an accidental auditor-list change before saving
    let selectedAuditorIds = new Set();
    let selectedSchemeIds = new Set();
    let selectedClient = { id: null, name: '' };
    // Collapsed ('locked') mode shows just the one auditor implied by which
    // grid cell was clicked, instead of the full 15-name checklist — expand
    // via the "change auditors" link if more than one needs to be assigned.
    let auditorPickerExpanded = true;

    const filterAuditorEl = document.getElementById('filter-auditor');
    const filterSchemeEl = document.getElementById('filter-scheme');
    const filterClientEl = document.getElementById('filter-client');
    const filterStatusEl = document.getElementById('filter-status');

    // -------------------------------------------------------------------
    // Toast + confirm dialog
    // -------------------------------------------------------------------
    const toastEl = document.getElementById('toast');
    let toastTimer = null;
    function showToast(message, isError = false) {
        clearTimeout(toastTimer);
        toastEl.textContent = message;
        toastEl.classList.toggle('error', isError);
        toastEl.classList.remove('hidden');
        toastTimer = setTimeout(() => toastEl.classList.add('hidden'), 3500);
    }

    const confirmBackdrop = document.getElementById('confirm-backdrop');
    let confirmCallback = null;
    function showConfirm(title, message, onConfirm, okLabel = 'Delete', okClass = 'btn-danger') {
        document.getElementById('confirm-title').textContent = title;
        document.getElementById('confirm-message').textContent = message;
        const okBtn = document.getElementById('confirm-ok');
        okBtn.textContent = okLabel;
        okBtn.className = 'btn ' + okClass;
        confirmCallback = onConfirm;
        confirmBackdrop.classList.remove('hidden');
    }
    document.getElementById('confirm-cancel').addEventListener('click', () => confirmBackdrop.classList.add('hidden'));
    document.getElementById('confirm-ok').addEventListener('click', () => {
        confirmBackdrop.classList.add('hidden');
        if (confirmCallback) confirmCallback();
    });

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : str;
        return div.innerHTML;
    }

    function csrfHeaders() {
        return { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken };
    }

    // -------------------------------------------------------------------
    // Reference data: schemes + auditors + legend + filter dropdowns
    // -------------------------------------------------------------------
    function loadReferenceData() {
        return Promise.all([
            fetch(window.EHS_BASE_URL + '/api/schemes.php').then(r => r.json()),
            fetch(window.EHS_BASE_URL + '/api/auditors.php').then(r => r.json()),
        ]).then(([schemeData, auditorData]) => {
            schemesCache = schemeData.schemes || [];
            auditorsCache = auditorData.auditors || [];

            filterSchemeEl.innerHTML = '<option value="">All schemes</option>' +
                schemesCache.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');

            filterAuditorEl.innerHTML = '<option value="">All auditors</option>' +
                auditorsCache.map(a => `<option value="${a.id}">${escapeHtml(a.name)}</option>`).join('');

            const legendEl = document.getElementById('auditor-legend');
            legendEl.innerHTML = auditorsCache.map(a =>
                `<span class="legend-item"><i class="dot" style="background:${a.color_hex}"></i> ${escapeHtml(a.name)}</span>`
            ).join('');

            renderSchemeCheckboxes();
        });
    }

    function renderSchemeCheckboxes() {
        const container = document.getElementById('scheme-checkboxes');
        container.innerHTML = schemesCache.map(s => `
            <label class="chip-checkbox" data-scheme-id="${s.id}">
                <input type="checkbox" value="${s.id}" style="display:none">
                ${escapeHtml(s.name)}
            </label>
        `).join('');

        container.querySelectorAll('.chip-checkbox').forEach(chip => {
            chip.addEventListener('click', function (e) {
                e.preventDefault();
                const id = parseInt(this.dataset.schemeId, 10);
                if (selectedSchemeIds.has(id)) {
                    selectedSchemeIds.delete(id);
                    this.classList.remove('active-chip');
                    this.style.background = '';
                } else {
                    selectedSchemeIds.add(id);
                    this.style.background = '#dbeafe';
                }
                renderAuditorCheckboxes(); // refresh "approved" hints
            });
        });
    }

    // -------------------------------------------------------------------
    // Calendar view
    // -------------------------------------------------------------------
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 'auto',
        firstDay: 1,
        headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
        dayMaxEvents: 3,

        dateClick: function (info) {
            openDayAgenda(info.dateStr.slice(0, 10));
        },

        eventClick: function (info) {
            const auditId = parseInt(info.event.id.replace('audit-', ''), 10);
            const audit = auditsById[auditId];
            if (audit) openModal(audit);
        },

        dayCellDidMount: function (arg) {
            const dow = arg.date.getDay();
            if (dow === 0 || dow === 6) arg.el.classList.add('fc-day-weekend-custom');
        },

        datesSet: function (arg) {
            // FullCalendar can emit full ISO datetimes with a timezone offset
            // (e.g. '2026-07-27T00:00:00+05:30') depending on locale/timeZone
            // settings, but our API expects plain 'YYYY-MM-DD'. Take just the
            // date portion regardless of which shape we're given.
            rangeStart = arg.startStr.slice(0, 10);
            rangeEnd = arg.endStr.slice(0, 10);
            loadHolidays().then(loadAudits);
            loadTeamAvailability();

            const gridLabel = document.getElementById('grid-month-label');
            if (gridLabel) gridLabel.textContent = arg.view.title;
        },
    });
    calendar.render();

    function renderCalendarEvents() {
        calendar.getEvents().forEach(e => e.remove());
        auditsCache.forEach(audit => {
            const primary = audit.auditors[0];
            const initials = audit.auditors.map(a => a.name.split(' ').map(w => w[0]).join('')).join('/');
            const statusIcon = { scheduled: '', confirmed: '\u2713 ', completed: '\u2713\u2713 ', cancelled: '\u2715 ' }[audit.status] || '';
            const overdueTag = audit.is_overdue ? ' \u26a0' : '';

            const classNames = [];
            if (audit.status === 'cancelled') classNames.push('audit-cancelled');
            if (audit.is_overdue) classNames.push('audit-overdue');

            calendar.addEvent({
                id: 'audit-' + audit.id,
                title: `${statusIcon}${audit.client_name} (${initials})${overdueTag}`,
                start: audit.audit_date,
                allDay: true,
                backgroundColor: primary ? primary.color_hex : '#94a3b8',
                borderColor: audit.is_overdue ? '#b91c1c' : (primary ? primary.color_hex : '#94a3b8'),
                classNames: classNames,
            });
        });
        applyDayHighlighting(calendarEl);
    }

    function applyDayHighlighting(root) {
        root.querySelectorAll('.fc-day[data-date]').forEach(cell => {
            const dateStr = cell.getAttribute('data-date');
            const holiday = holidaysByDate[dateStr];
            cell.classList.toggle('fc-day-holiday', !!holiday);
            if (holiday) cell.setAttribute('title', holiday.name);
        });
    }

    // -------------------------------------------------------------------
    // Grid / sheet view
    // -------------------------------------------------------------------
    function renderGridView() {
        const table = document.getElementById('grid-table');
        const activeAuditors = auditorsCache; // all active auditors as columns

        const thead = table.querySelector('thead tr');
        thead.innerHTML = '<th>Date</th>' + activeAuditors.map(a =>
            `<th style="color:${a.color_hex}">${escapeHtml(a.name)}</th>`
        ).join('');

        const tbody = table.querySelector('tbody');
        const days = [];
        let cursor = new Date(rangeStart + 'T00:00:00');
        const end = new Date(rangeEnd + 'T00:00:00');
        while (cursor < end) {
            days.push(formatDate(cursor));
            cursor.setDate(cursor.getDate() + 1);
        }

        // Group this range's audits by date+auditor for quick lookup.
        const auditsByDateAuditor = {};
        auditsCache.forEach(audit => {
            audit.auditors.forEach(a => {
                const key = audit.audit_date + '|' + a.id;
                (auditsByDateAuditor[key] = auditsByDateAuditor[key] || []).push(audit);
            });
        });

        tbody.innerHTML = days.map(dateStr => {
            const d = new Date(dateStr + 'T00:00:00');
            const dow = d.getDay();
            const holiday = holidaysByDate[dateStr];
            const rowClass = holiday ? 'holiday-cell' : (dow === 0 || dow === 6 ? 'weekend-cell' : '');
            const label = d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });

            const cells = activeAuditors.map(a => {
                const key = dateStr + '|' + a.id;
                const dayAudits = auditsByDateAuditor[key] || [];
                const chips = dayAudits.map(audit => {
                    const sessionLabel = audit.session === 'FULL_DAY' ? 'Full day' : audit.session;
                    const statusIcon = { scheduled: '', confirmed: '\u2713 ', completed: '\u2713\u2713 ', cancelled: '\u2715 ' }[audit.status] || '';
                    const overdueTag = audit.is_overdue ? ' \u26a0' : '';
                    let cls = 'grid-chip';
                    if (audit.status === 'cancelled') cls += ' chip-cancelled';
                    if (audit.is_overdue) cls += ' chip-overdue';
                    const overdueTitle = audit.is_overdue ? ` — OVERDUE: ${audit.overdue_reason}` : '';
                    return `<span class="${cls}" style="background:${a.color_hex}" data-audit-id="${audit.id}" title="${escapeHtml(audit.client_name)} — ${sessionLabel}${overdueTitle}"><b>${sessionLabel}:</b> ${statusIcon}${escapeHtml(audit.client_name)}${overdueTag}</span>`;
                }).join('');

                // Tint the cell to show availability: top half = AM, bottom
                // half = PM (a FULL_DAY entry colors both the same). This
                // sits on the inner div, not the <td> itself, so it layers
                // on top of the weekend/holiday tint rather than replacing it.
                const { amColor, pmColor } = availabilityHalvesFor(dateStr, a.id);
                let cellStyle = '';
                if (amColor || pmColor) {
                    cellStyle = `style="background: linear-gradient(to bottom, ${amColor || 'transparent'} 0 50%, ${pmColor || 'transparent'} 50% 100%);"`;
                }

                // The '+ add' affordance is ALWAYS present and always clickable,
                // even when the cell already has one or more audits — this is
                // what lets an auditor be given a separate AM audit and a
                // separate PM audit on the same day. Previously the whole
                // cell button bailed out once any chip existed, silently
                // blocking a second assignment.
                return `<td class="${rowClass}">
                            <div class="grid-cell" ${cellStyle}>
                                ${chips}
                                <button class="grid-add-btn" data-date="${dateStr}" data-auditor-id="${a.id}" title="Assign another audit">+ add</button>
                            </div>
                        </td>`;
            }).join('');

            return `<tr class="${rowClass}"><td class="${rowClass}">${escapeHtml(label)}${holiday ? ' 🎌' : ''}</td>${cells}</tr>`;
        }).join('');

        // Clicking an existing chip opens that audit for editing.
        tbody.querySelectorAll('.grid-chip').forEach(chip => {
            chip.addEventListener('click', function (e) {
                e.stopPropagation();
                const audit = auditsById[parseInt(this.dataset.auditId, 10)];
                if (audit) openModal(audit);
            });
        });
        // Clicking '+ add' always starts a NEW audit for that auditor/date,
        // regardless of how many audits are already assigned there.
        tbody.querySelectorAll('.grid-add-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                openModal({
                    audit_date: this.dataset.date,
                    auditor_ids_preset: [parseInt(this.dataset.auditorId, 10)],
                });
            });
        });
    }

    function formatDate(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    // -------------------------------------------------------------------
    // View toggle
    // -------------------------------------------------------------------
    document.getElementById('view-calendar-btn').addEventListener('click', () => switchView('calendar'));
    document.getElementById('view-grid-btn').addEventListener('click', () => switchView('grid'));

    function switchView(view) {
        currentView = view;
        document.getElementById('view-calendar-btn').classList.toggle('active', view === 'calendar');
        document.getElementById('view-grid-btn').classList.toggle('active', view === 'grid');
        document.getElementById('calendar-view').classList.toggle('hidden', view !== 'calendar');
        document.getElementById('grid-view').classList.toggle('hidden', view !== 'grid');
        if (view === 'grid') renderGridView();
    }

    // Grid view's own month navigation — drives the SAME underlying
    // FullCalendar instance (even though it's hidden while grid view is
    // active), so both views always agree on which month is showing and
    // reuse all the existing datesSet-triggered data loading.
    document.getElementById('grid-prev-btn').addEventListener('click', () => calendar.prev());
    document.getElementById('grid-today-btn').addEventListener('click', () => calendar.today());
    document.getElementById('grid-next-btn').addEventListener('click', () => calendar.next());

    // -------------------------------------------------------------------
    // Filters
    // -------------------------------------------------------------------
    [filterAuditorEl, filterSchemeEl, filterStatusEl].forEach(el => el.addEventListener('change', loadAudits));
    let clientFilterTimer = null;
    filterClientEl.addEventListener('input', function () {
        clearTimeout(clientFilterTimer);
        clientFilterTimer = setTimeout(loadAudits, 350);
    });
    document.getElementById('filter-clear-btn').addEventListener('click', function () {
        filterAuditorEl.value = '';
        filterSchemeEl.value = '';
        filterStatusEl.value = '';
        filterClientEl.value = '';
        loadAudits();
    });

    // -------------------------------------------------------------------
    // Data loading
    // -------------------------------------------------------------------
    function loadHolidays() {
        return fetch(`${window.EHS_BASE_URL}/api/holidays.php?start=${rangeStart}&end=${rangeEnd}`)
            .then(r => r.json())
            .then(data => {
                holidaysByDate = {};
                (data.holidays || []).forEach(h => { holidaysByDate[h.date] = h; });
            });
    }

    function loadTeamAvailability() {
        return fetch(`${window.EHS_BASE_URL}/api/team_availability.php?start=${rangeStart}&end=${rangeEnd}`)
            .then(r => r.json())
            .then(data => {
                teamAvailability = {};
                (data.availability || []).forEach(row => {
                    teamAvailability[row.date] = teamAvailability[row.date] || {};
                    (teamAvailability[row.date][row.auditor_id] = teamAvailability[row.date][row.auditor_id] || []).push(row);
                });
                if (currentView === 'grid') renderGridView();
            })
            .catch(() => showToast('Could not load team availability.', true));
    }

    /**
     * Returns { amColor, pmColor } CSS colors (or null for "nothing set") for
     * one auditor on one date, used to tint that grid cell. A FULL_DAY entry
     * colors both halves the same; otherwise AM and PM are independent, so a
     * cell can show "available AM, unavailable PM" as two different colors.
     */
    function availabilityHalvesFor(dateStr, auditorId) {
        const entries = (teamAvailability[dateStr] || {})[auditorId] || [];
        const colorFor = status => ({
            available: 'var(--available-bg)',
            unavailable: 'var(--unavailable-bg)',
            tentative: 'var(--tentative-bg)',
        }[status] || null);

        let am = null, pm = null;
        entries.forEach(e => {
            if (e.session === 'FULL_DAY') { am = colorFor(e.status); pm = colorFor(e.status); }
            else if (e.session === 'AM') { am = colorFor(e.status); }
            else if (e.session === 'PM') { pm = colorFor(e.status); }
        });
        return { amColor: am, pmColor: pm };
    }

    function loadAudits() {
        const params = new URLSearchParams({ start: rangeStart, end: rangeEnd });
        if (filterAuditorEl.value) params.set('auditor_id', filterAuditorEl.value);
        if (filterSchemeEl.value) params.set('scheme_id', filterSchemeEl.value);
        if (filterStatusEl.value) params.set('status', filterStatusEl.value);
        if (filterClientEl.value.trim()) params.set('client', filterClientEl.value.trim());

        return fetch(window.EHS_BASE_URL + '/api/audits.php?' + params.toString())
            .then(r => r.json())
            .then(data => {
                auditsCache = data.audits || [];
                auditsById = {};
                auditsCache.forEach(a => { auditsById[a.id] = a; });

                if (currentView === 'calendar') {
                    renderCalendarEvents();
                } else {
                    renderGridView();
                }
            })
            .catch(() => showToast('Could not load audits.', true));
    }

    // -------------------------------------------------------------------
    // Day agenda — clicking a date in Calendar view shows this first,
    // listing that day's audits (if any) with an "+ Add New Audit" option,
    // rather than jumping straight into the New Audit form.
    // -------------------------------------------------------------------
    const dayAgendaBackdrop = document.getElementById('day-agenda-backdrop');
    let dayAgendaDate = null;

    function openDayAgenda(dateStr) {
        dayAgendaDate = dateStr;
        const dayLabel = new Date(dateStr + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
        document.getElementById('day-agenda-title').textContent = `Audits on ${dayLabel}`;

        const dayAudits = auditsCache.filter(a => a.audit_date === dateStr);
        const listEl = document.getElementById('day-agenda-list');

        if (dayAudits.length === 0) {
            listEl.innerHTML = '<p class="empty-note">No audits scheduled for this day yet.</p>';
        } else {
            listEl.innerHTML = dayAudits.map(a => {
                const sessionLabel = a.session === 'FULL_DAY' ? 'Full day' : a.session;
                const names = a.auditors.map(x => x.name).join(', ');
                const overdueTag = a.is_overdue ? '<span class="overdue-badge">OVERDUE</span>' : '';
                return `
                    <div class="day-agenda-item" data-audit-id="${a.id}">
                        <div>
                            <div class="list-item-main">${escapeHtml(a.client_name)}</div>
                            <div class="list-item-sub">${escapeHtml(names || 'Unassigned')} &middot; ${sessionLabel}</div>
                        </div>
                        <div><span class="badge badge-role">${a.status}</span> ${overdueTag}</div>
                    </div>`;
            }).join('');

            listEl.querySelectorAll('.day-agenda-item').forEach(item => {
                item.addEventListener('click', function () {
                    const audit = auditsById[parseInt(this.dataset.auditId, 10)];
                    dayAgendaBackdrop.classList.add('hidden');
                    if (audit) openModal(audit);
                });
            });
        }

        dayAgendaBackdrop.classList.remove('hidden');
    }

    document.getElementById('day-agenda-close').addEventListener('click', () => dayAgendaBackdrop.classList.add('hidden'));
    document.getElementById('day-agenda-add-btn').addEventListener('click', function () {
        dayAgendaBackdrop.classList.add('hidden');
        openModal({ audit_date: dayAgendaDate });
    });

    // -------------------------------------------------------------------
    // Per-audit timeline
    // -------------------------------------------------------------------
    const timelineBackdrop = document.getElementById('timeline-backdrop');

    function openTimeline(auditId) {
        fetch(`${window.EHS_BASE_URL}/api/audit_timeline.php?audit_id=${auditId}`)
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not load timeline.', true); return; }
                document.getElementById('timeline-subtitle').textContent =
                    `${body.audit.client_name} — ${body.audit.audit_date} (${body.audit.session === 'FULL_DAY' ? 'Full day' : body.audit.session})`;

                const listEl = document.getElementById('timeline-list');
                if (body.events.length === 0) {
                    listEl.innerHTML = '<p class="empty-note">No recorded history yet.</p>';
                } else {
                    listEl.innerHTML = body.events.map(e => `
                        <div class="timeline-item">
                            <div class="timeline-action">${escapeHtml(e.action.replace(/_/g, ' '))}</div>
                            <div class="timeline-meta">${escapeHtml(e.created_at)} &middot; ${escapeHtml(e.user_name || 'System')}</div>
                            ${e.details ? `<div class="timeline-meta">${escapeHtml(e.details)}</div>` : ''}
                        </div>
                    `).join('');
                }
                timelineBackdrop.classList.remove('hidden');
            })
            .catch(() => showToast('Network error — please try again.', true));
    }

    document.getElementById('timeline-close').addEventListener('click', () => timelineBackdrop.classList.add('hidden'));
    document.getElementById('modal-timeline').addEventListener('click', function () {
        if (editingAuditId) openTimeline(editingAuditId);
    });

    // -------------------------------------------------------------------
    // Assignment modal
    // -------------------------------------------------------------------
    const modalBackdrop = document.getElementById('modal-backdrop');
    const clientInput = document.getElementById('audit-client');
    const clientIdInput = document.getElementById('audit-client-id');
    const clientSuggestions = document.getElementById('client-suggestions');

    document.getElementById('new-audit-btn').addEventListener('click', () => openModal({}));
    document.getElementById('modal-cancel').addEventListener('click', closeModal);

    function closeModal() {
        modalBackdrop.classList.add('hidden');
    }

    function openModal(audit) {
        editingAuditId = audit.id || null;
        editingAuditUpdatedAt = audit.updated_at || null;
        document.getElementById('modal-title').textContent = editingAuditId ? 'Edit audit' : 'New audit';
        document.getElementById('modal-delete').classList.toggle('hidden', !editingAuditId);
        document.getElementById('modal-timeline').classList.toggle('hidden', !editingAuditId);

        selectedClient = audit.client_id ? { id: audit.client_id, name: audit.client_name } : { id: null, name: '' };
        clientInput.value = selectedClient.name;
        clientIdInput.value = selectedClient.id || '';
        clientSuggestions.classList.add('hidden');

        selectedSchemeIds = new Set((audit.schemes || []).map(s => s.id));
        renderSchemeCheckboxes();
        schemesCache.forEach(s => {
            const chip = document.querySelector(`#scheme-checkboxes .chip-checkbox[data-scheme-id="${s.id}"]`);
            if (chip && selectedSchemeIds.has(s.id)) chip.style.background = '#dbeafe';
        });

        document.getElementById('audit-date').value = audit.audit_date || '';
        document.querySelector(`input[name="audit-session"][value="${audit.session || 'FULL_DAY'}"]`).checked = true;
        document.getElementById('audit-location').value = audit.location || '';
        document.getElementById('audit-notes').value = audit.notes || '';
        document.getElementById('audit-status').value = audit.status || 'scheduled';

        selectedAuditorIds = new Set(
            (audit.auditors || []).map(a => a.id).concat(audit.auditor_ids_preset || [])
        );
        // Snapshot of who was assigned when the modal opened, purely for
        // editing an existing audit — used to detect (and confirm) an
        // accidental auditor-list change before saving.
        originalAuditorIds = editingAuditId ? new Set((audit.auditors || []).map(a => a.id)) : new Set();

        // Grid-cell clicks already imply exactly one auditor (the column
        // clicked) — no need to re-show all 15 names for the admin to
        // re-confirm. Calendar-view clicks and editing an existing audit
        // still show the full picker, since there's no single implied auditor.
        auditorPickerExpanded = !(audit.auditor_ids_preset && audit.auditor_ids_preset.length > 0 && !editingAuditId);

        refreshAuditorAvailability();
        modalBackdrop.classList.remove('hidden');
    }

    document.getElementById('modal-delete').addEventListener('click', function () {
        if (!editingAuditId) return;
        showConfirm('Delete this audit?', 'This cannot be undone.', function () {
            fetch(window.EHS_BASE_URL + '/api/audits.php?id=' + editingAuditId, { method: 'DELETE', headers: csrfHeaders() })
                .then(r => r.json().then(body => ({ ok: r.ok, body })))
                .then(({ ok, body }) => {
                    if (!ok) { showToast(body.error || 'Could not delete audit.', true); return; }
                    showToast('Audit deleted.');
                    closeModal();
                    loadAudits();
                })
                .catch(() => showToast('Network error — please try again.', true));
        });
    });

    // --- client autocomplete ---
    let clientSearchTimer = null;
    clientInput.addEventListener('input', function () {
        clientIdInput.value = '';
        selectedClient = { id: null, name: clientInput.value };
        clearTimeout(clientSearchTimer);
        const q = clientInput.value.trim();
        clientSearchTimer = setTimeout(() => searchClients(q), 250);
    });

    function searchClients(q) {
        fetch(window.EHS_BASE_URL + '/api/clients.php?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                const clients = data.clients || [];
                let html = clients.map(c =>
                    `<div class="autocomplete-item" data-id="${c.id}" data-name="${escapeHtml(c.name)}">${escapeHtml(c.name)}</div>`
                ).join('');
                const exactMatch = clients.some(c => c.name.toLowerCase() === q.toLowerCase());
                if (q && !exactMatch) {
                    html += `<div class="autocomplete-item add-new" data-id="" data-name="${escapeHtml(q)}">+ Add new client "${escapeHtml(q)}"</div>`;
                }
                clientSuggestions.innerHTML = html;
                clientSuggestions.classList.toggle('hidden', html === '');
            });
    }

    clientSuggestions.addEventListener('click', function (e) {
        const item = e.target.closest('.autocomplete-item');
        if (!item) return;
        clientInput.value = item.dataset.name;
        clientIdInput.value = item.dataset.id;
        selectedClient = { id: item.dataset.id || null, name: item.dataset.name };
        clientSuggestions.classList.add('hidden');
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.autocomplete')) clientSuggestions.classList.add('hidden');
    });

    // --- date/session change refreshes conflict info ---
    document.getElementById('audit-date').addEventListener('change', refreshAuditorAvailability);
    document.querySelectorAll('input[name="audit-session"]').forEach(r => r.addEventListener('change', refreshAuditorAvailability));

    function refreshAuditorAvailability() {
        const date = document.getElementById('audit-date').value;
        const session = document.querySelector('input[name="audit-session"]:checked').value;
        if (!date) { renderAuditorCheckboxes(); return; }

        const params = new URLSearchParams({ date, session });
        if (editingAuditId) params.set('exclude_audit_id', editingAuditId);

        fetch(window.EHS_BASE_URL + '/api/auditors.php?' + params.toString())
            .then(r => r.json())
            .then(data => {
                auditorsCache = data.auditors || []; // now carries availability_status + conflict
                renderAuditorCheckboxes();
            });
    }

    function renderAuditorCheckboxes() {
        const container = document.getElementById('auditor-checkboxes');
        const warnings = [];

        function classify(a) {
            const isUnavailable = a.availability_status === 'unavailable';
            const isTentative = a.availability_status === 'tentative';
            const isNotSet = a.availability_status === 'not_set';
            const hasConflict = a.conflict;
            const approvedForSelectedScheme = selectedSchemeIds.size === 0 ||
                a.scheme_codes.some(code => schemesCache.find(s => selectedSchemeIds.has(s.id) && s.code === code));
            return { isUnavailable, isTentative, isNotSet, hasConflict, approvedForSelectedScheme };
        }

        // Collect warnings for anyone currently selected, regardless of
        // which render mode we're in (used by both).
        auditorsCache.forEach(a => {
            if (!selectedAuditorIds.has(a.id)) return;
            const { isUnavailable, isTentative, isNotSet, hasConflict, approvedForSelectedScheme } = classify(a);
            if (isUnavailable || hasConflict) {
                warnings.push(`${a.name} is ${hasConflict ? 'already assigned elsewhere' : 'marked unavailable'} that day/session.`);
            } else if (isTentative) {
                warnings.push(`${a.name} marked only tentative that day/session.`);
            } else if (isNotSet) {
                warnings.push(`${a.name} hasn\u2019t confirmed availability for that day/session \u2014 not necessarily free, just unconfirmed.`);
            }
            if (!approvedForSelectedScheme) {
                warnings.push(`${a.name} is not recorded as approved for the selected scheme(s).`);
            }
        });

        if (!auditorPickerExpanded) {
            // --- Collapsed: compact summary of the one implied auditor ---
            const chosen = auditorsCache.filter(a => selectedAuditorIds.has(a.id));
            container.innerHTML = chosen.map(a => {
                const { isUnavailable, hasConflict, isTentative, isNotSet } = classify(a);
                const badge = (isUnavailable || hasConflict)
                    ? `<span class="conflict-badge">${hasConflict ? 'Conflict' : 'Unavailable'}</span>`
                    : (isTentative ? '<span class="conflict-badge tentative-badge">Tentative</span>'
                        : (isNotSet ? '<span class="conflict-badge caution-badge">Not confirmed</span>' : ''));
                return `
                    <div class="locked-auditor-summary">
                        <i class="chip-dot" style="background:${a.color_hex}"></i>
                        <span>${escapeHtml(a.name)}</span>
                        ${badge}
                        <button type="button" class="expand-auditors-btn">Change auditors</button>
                    </div>`;
            }).join('') || '<p class="field-hint">No auditor selected yet.</p>';

            container.querySelectorAll('.expand-auditors-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    auditorPickerExpanded = true;
                    renderAuditorCheckboxes();
                });
            });
        } else {
            // --- Expanded: full checklist, with a clear selected state ---
            container.innerHTML = `<div class="checkbox-grid checkbox-grid-scroll">` +
                auditorsCache.map(a => {
                    const selected = selectedAuditorIds.has(a.id);
                    const { isUnavailable, isTentative, isNotSet, hasConflict, approvedForSelectedScheme } = classify(a);

                    let cls = 'chip-checkbox';
                    if (isUnavailable || hasConflict) cls += ' blocked';
                    else if (isTentative) cls += ' warn';
                    else if (isNotSet) cls += ' caution';
                    if (!approvedForSelectedScheme) cls += ' not-approved';
                    if (selected) cls += ' selected';

                    const badge = (isUnavailable || hasConflict)
                        ? `<span class="conflict-badge">${hasConflict ? 'Conflict' : 'Unavailable'}</span>`
                        : (isTentative ? '<span class="conflict-badge tentative-badge">Tentative</span>'
                            : (isNotSet ? '<span class="conflict-badge caution-badge">Not confirmed</span>' : ''));

                    return `
                        <label class="${cls}" style="${selected ? `background:${a.color_hex};` : ''}"
                               title="${a.scheme_codes.join(', ') || 'No schemes recorded'}">
                            <input type="checkbox" value="${a.id}" ${selected ? 'checked' : ''} style="display:none">
                            ${selected ? '<span class="check-mark">✓</span>' : `<i class="chip-dot" style="background:${a.color_hex}"></i>`}
                            ${escapeHtml(a.name)}
                            ${badge}
                        </label>`;
                }).join('') +
                `</div>`;

            container.querySelectorAll('.chip-checkbox').forEach(chip => {
                chip.addEventListener('click', function (e) {
                    e.preventDefault();
                    const id = parseInt(this.querySelector('input').value, 10);
                    if (selectedAuditorIds.has(id)) selectedAuditorIds.delete(id);
                    else selectedAuditorIds.add(id);
                    renderAuditorCheckboxes();
                });
            });
        }

        const warningsEl = document.getElementById('modal-warnings');
        if (warnings.length) {
            warningsEl.innerHTML = '⚠ ' + warnings.join('<br>⚠ ');
            warningsEl.classList.remove('hidden');
        } else {
            warningsEl.classList.add('hidden');
        }
    }

    // --- save ---
    document.getElementById('modal-save').addEventListener('click', function () {
        const date = document.getElementById('audit-date').value;
        const session = document.querySelector('input[name="audit-session"]:checked').value;
        const status = document.getElementById('audit-status').value;
        const location = document.getElementById('audit-location').value.trim();
        const notes = document.getElementById('audit-notes').value.trim();

        if (!date) { showToast('Please choose a date.', true); return; }
        if (!clientInput.value.trim()) { showToast('Please choose or enter a client.', true); return; }
        if (selectedSchemeIds.size === 0) { showToast('Please select at least one scheme.', true); return; }
        if (selectedAuditorIds.size === 0) { showToast('Please assign at least one auditor.', true); return; }

        const payload = {
            client_id: clientIdInput.value || null,
            client_name: clientIdInput.value ? null : clientInput.value.trim(),
            scheme_ids: Array.from(selectedSchemeIds),
            audit_date: date,
            session: session,
            auditor_ids: Array.from(selectedAuditorIds),
            location: location,
            notes: notes,
            status: status,
        };
        if (editingAuditId && editingAuditUpdatedAt) {
            payload.expected_updated_at = editingAuditUpdatedAt;
        }

        // Guard against an accidental auditor-list change on an existing
        // audit — e.g. a mis-click that swaps the assigned auditor. Only
        // fires when editing (not on a brand-new audit) and only when the
        // auditor list actually differs from what it was when the modal
        // opened; unrelated edits (date, notes, status, etc.) save normally
        // with no extra prompt.
        if (editingAuditId) {
            const added = [...selectedAuditorIds].filter(id => !originalAuditorIds.has(id));
            const removed = [...originalAuditorIds].filter(id => !selectedAuditorIds.has(id));
            if (added.length > 0 || removed.length > 0) {
                const nameOf = id => (auditorsCache.find(a => a.id === id) || {}).name || `#${id}`;
                const parts = [];
                if (removed.length) parts.push(`removing ${removed.map(nameOf).join(', ')}`);
                if (added.length) parts.push(`adding ${added.map(nameOf).join(', ')}`);
                showConfirm(
                    'Change assigned auditor(s)?',
                    `You're ${parts.join(' and ')} on this audit. Continue?`,
                    () => performSave(payload),
                    'Continue',
                    'btn-primary'
                );
                return;
            }
        }

        performSave(payload);
    });

    function performSave(payload) {
        const url = editingAuditId ? `${window.EHS_BASE_URL}/api/audits.php?id=${editingAuditId}` : window.EHS_BASE_URL + '/api/audits.php';
        const method = editingAuditId ? 'PUT' : 'POST';

        fetch(url, { method, headers: csrfHeaders(), body: JSON.stringify(payload) })
            .then(r => r.json().then(body => ({ ok: r.ok, status: r.status, body })))
            .then(({ ok, status, body }) => {
                if (!ok) {
                    if (status === 409) {
                        showToast(body.error || 'This audit was changed by someone else. Please close this window and reopen it.', true);
                    } else {
                        showToast(body.error || 'Could not save audit.', true);
                    }
                    return;
                }
                showToast(editingAuditId ? 'Audit updated.' : 'Audit created.');
                closeModal();
                loadAudits();
            })
            .catch(() => showToast('Network error — please try again.', true));
    }

    // -------------------------------------------------------------------
    // Boot
    // -------------------------------------------------------------------
    loadReferenceData().then(() => {
        // calendar's initial datesSet fires calendar.render() above, which
        // triggers loadHolidays -> loadAudits with the initial visible range.
        renderAuditorCheckboxes();
    });
});
