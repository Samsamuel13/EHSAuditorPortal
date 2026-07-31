// File: assets/js/admin_availability.js
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const auditorSelect = document.getElementById('auditor-select');
    const editorArea = document.getElementById('editor-area');
    const noSelectionNote = document.getElementById('no-selection-note');
    const selectionBar = document.getElementById('selection-bar');
    const selectionCount = document.getElementById('selection-count');
    const modalBackdrop = document.getElementById('modal-backdrop');
    const toastEl = document.getElementById('toast');

    let selectedAuditorId = null;
    let selectedAuditorName = '';
    let selectedDates = new Set();
    let holidaysByDate = {};
    let availabilityByDate = {};
    let calendar = null;

    // -------------------------------------------------------------------
    // Toast
    // -------------------------------------------------------------------
    let toastTimer = null;
    function showToast(message, isError = false) {
        clearTimeout(toastTimer);
        toastEl.textContent = message;
        toastEl.classList.toggle('error', isError);
        toastEl.classList.remove('hidden');
        toastTimer = setTimeout(() => toastEl.classList.add('hidden'), 3500);
    }

    // -------------------------------------------------------------------
    // Auditor picker
    // -------------------------------------------------------------------
    fetch(`${window.EHS_BASE_URL}/api/auditor_profile.php`)
        .then(r => r.json())
        .then(data => {
            (data.auditors || []).forEach(a => {
                const opt = document.createElement('option');
                opt.value = a.id;
                opt.textContent = a.name;
                auditorSelect.appendChild(opt);
            });
        })
        .catch(() => showToast('Could not load auditor list.', true));

    auditorSelect.addEventListener('change', function () {
        selectedAuditorId = this.value ? parseInt(this.value, 10) : null;
        selectedAuditorName = this.options[this.selectedIndex].textContent;
        clearSelectionState();

        if (!selectedAuditorId) {
            editorArea.classList.add('hidden');
            noSelectionNote.classList.remove('hidden');
            return;
        }

        editorArea.classList.remove('hidden');
        noSelectionNote.classList.add('hidden');

        if (!calendar) {
            initCalendar();
        } else {
            // Re-load data for the newly selected auditor within the
            // calendar's current visible range.
            loadRangeData(formatDate(calendar.view.activeStart), formatDate(calendar.view.activeEnd));
        }
    });

    // -------------------------------------------------------------------
    // Selection state (same pattern as the auditor's own availability.js)
    // -------------------------------------------------------------------
    function dayCellFor(dateStr) {
        return document.getElementById('calendar').querySelector('.fc-day[data-date="' + dateStr + '"]');
    }

    function updateSelectionUI() {
        selectionCount.textContent = selectedDates.size + ' date' + (selectedDates.size === 1 ? '' : 's') + ' selected';
        selectionBar.classList.toggle('hidden', selectedDates.size === 0);
    }

    function toggleDateSelection(dateStr, forceState) {
        const isSelected = selectedDates.has(dateStr);
        const shouldSelect = forceState !== undefined ? forceState : !isSelected;
        if (shouldSelect) selectedDates.add(dateStr); else selectedDates.delete(dateStr);
        const cell = dayCellFor(dateStr);
        if (cell) cell.classList.toggle('fc-day-selected', shouldSelect);
        updateSelectionUI();
    }

    function clearSelection() {
        selectedDates.forEach(dateStr => {
            const cell = dayCellFor(dateStr);
            if (cell) cell.classList.remove('fc-day-selected');
        });
        selectedDates.clear();
        updateSelectionUI();
    }

    function clearSelectionState() {
        selectedDates.clear();
        updateSelectionUI();
    }

    document.getElementById('clear-selection-btn').addEventListener('click', clearSelection);

    // -------------------------------------------------------------------
    // FullCalendar (created once, on first auditor selection)
    // -------------------------------------------------------------------
    function initCalendar() {
        const calendarEl = document.getElementById('calendar');
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            height: 'auto',
            selectable: true,
            firstDay: 1,
            headerToolbar: { left: 'prev,next today', center: 'title', right: '' },

            select: function (info) {
                const additive = info.jsEvent && (info.jsEvent.ctrlKey || info.jsEvent.metaKey);
                const daysInRange = [];
                let cursor = new Date(info.start);
                while (cursor < info.end) {
                    daysInRange.push(formatDate(cursor));
                    cursor.setDate(cursor.getDate() + 1);
                }
                if (additive) {
                    daysInRange.forEach(d => toggleDateSelection(d));
                } else {
                    clearSelection();
                    daysInRange.forEach(d => toggleDateSelection(d, true));
                }
                calendar.unselect();
            },

            eventClick: function (info) {
                const dateStr = info.event.startStr.slice(0, 10);
                const additive = info.jsEvent && (info.jsEvent.ctrlKey || info.jsEvent.metaKey);
                if (additive) toggleDateSelection(dateStr);
                else { clearSelection(); toggleDateSelection(dateStr, true); }
            },

            dayCellDidMount: function (arg) {
                const dow = arg.date.getDay();
                if (dow === 0 || dow === 6) arg.el.classList.add('fc-day-weekend-custom');
            },

            datesSet: function (arg) {
                loadRangeData(arg.startStr.slice(0, 10), arg.endStr.slice(0, 10));
            },

            eventContent: function (arg) {
                return { html: '<div class="fc-event-main-frame">' + escapeHtml(arg.event.title) + '</div>' };
            },
        });
        calendar.render();
    }

    // -------------------------------------------------------------------
    // Data loading
    // -------------------------------------------------------------------
    function formatDate(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function loadRangeData(start, end) {
        if (!selectedAuditorId) return;
        Promise.all([
            fetch(`${window.EHS_BASE_URL}/api/admin_availability.php?auditor_id=${selectedAuditorId}&start=${start}&end=${end}`).then(r => r.json()),
            fetch(`${window.EHS_BASE_URL}/api/holidays.php?start=${start}&end=${end}`).then(r => r.json()),
        ]).then(([availData, holidayData]) => {
            holidaysByDate = {};
            (holidayData.holidays || []).forEach(h => { holidaysByDate[h.date] = h; });

            calendar.getEvents().forEach(e => e.remove());
            availabilityByDate = {};
            (availData.availability || []).forEach(row => {
                const label = row.session === 'FULL_DAY' ? 'Full day' : row.session;
                calendar.addEvent({
                    id: 'avail-' + row.id,
                    title: label + (row.note ? ' — ' + row.note : ''),
                    start: row.date,
                    allDay: true,
                    classNames: ['avail-' + row.status],
                });
                (availabilityByDate[row.date] = availabilityByDate[row.date] || []).push(row);
            });

            applyDayHighlighting();
        }).catch(() => showToast('Could not load calendar data.', true));
    }

    function applyDayHighlighting() {
        document.getElementById('calendar').querySelectorAll('.fc-day[data-date]').forEach(cell => {
            const dateStr = cell.getAttribute('data-date');
            const dow = new Date(dateStr + 'T00:00:00').getDay();
            cell.classList.toggle('fc-day-weekend-custom', dow === 0 || dow === 6);
            const holiday = holidaysByDate[dateStr];
            cell.classList.toggle('fc-day-holiday', !!holiday);
            if (holiday) cell.setAttribute('title', holiday.name); else cell.removeAttribute('title');
        });
    }

    // -------------------------------------------------------------------
    // Bulk update modal
    // -------------------------------------------------------------------
    document.getElementById('apply-btn').addEventListener('click', function () {
        if (selectedDates.size === 0) return;
        const sorted = Array.from(selectedDates).sort();
        const summary = sorted.length <= 3
            ? sorted.join(', ')
            : sorted[0] + ' … ' + sorted[sorted.length - 1] + ' (' + sorted.length + ' dates)';
        document.getElementById('modal-date-summary').textContent = summary;
        document.getElementById('modal-auditor-name').textContent = selectedAuditorName;

        const existing = sorted.length === 1 ? (availabilityByDate[sorted[0]] || []) : [];
        if (existing.length === 1) {
            document.querySelector(`input[name="session"][value="${existing[0].session}"]`).checked = true;
            document.querySelector(`input[name="status"][value="${existing[0].status}"]`).checked = true;
            document.getElementById('note').value = existing[0].note || '';
        } else {
            document.querySelector('input[name="session"][value="FULL_DAY"]').checked = true;
            document.querySelector('input[name="status"][value="available"]').checked = true;
            document.getElementById('note').value = '';
        }

        modalBackdrop.classList.remove('hidden');
    });

    document.getElementById('modal-cancel').addEventListener('click', function () {
        modalBackdrop.classList.add('hidden');
    });

    document.getElementById('modal-save').addEventListener('click', function () {
        const session = document.querySelector('input[name="session"]:checked').value;
        const status = document.querySelector('input[name="status"]:checked').value;
        const note = document.getElementById('note').value.trim();

        fetch(`${window.EHS_BASE_URL}/api/admin_availability.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({
                auditor_id: selectedAuditorId,
                dates: Array.from(selectedDates),
                session: session,
                status: status,
                note: note,
            }),
        })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not save availability.', true); return; }
                showToast(`Availability saved for ${selectedAuditorName} (${body.updated} date(s)).`);
                modalBackdrop.classList.add('hidden');
                clearSelection();
                loadRangeData(formatDate(calendar.view.activeStart), formatDate(calendar.view.activeEnd));
            })
            .catch(() => showToast('Network error — please try again.', true));
    });
});
