document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const calendarEl = document.getElementById('calendar');
    const selectionBar = document.getElementById('selection-bar');
    const selectionCount = document.getElementById('selection-count');
    const modalBackdrop = document.getElementById('modal-backdrop');
    const toastEl = document.getElementById('toast');

    let selectedDates = new Set(); // 'YYYY-MM-DD' strings
    let holidaysByDate = {};       // date -> { name, type }
    let availabilityByDate = {};   // date -> [ { session, status, note }, ... ]

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
    // Selection state
    // -------------------------------------------------------------------
    function dayCellFor(dateStr) {
        return calendarEl.querySelector('.fc-day[data-date="' + dateStr + '"]');
    }

    function updateSelectionUI() {
        selectionCount.textContent = selectedDates.size + ' date' + (selectedDates.size === 1 ? '' : 's') + ' selected';
        selectionBar.classList.toggle('hidden', selectedDates.size === 0);
    }

    function toggleDateSelection(dateStr, forceState) {
        const isSelected = selectedDates.has(dateStr);
        const shouldSelect = forceState !== undefined ? forceState : !isSelected;

        if (shouldSelect) {
            selectedDates.add(dateStr);
        } else {
            selectedDates.delete(dateStr);
        }

        const cell = dayCellFor(dateStr);
        if (cell) {
            cell.classList.toggle('fc-day-selected', shouldSelect);
        }
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

    document.getElementById('clear-selection-btn').addEventListener('click', clearSelection);

    // -------------------------------------------------------------------
    // FullCalendar
    // -------------------------------------------------------------------
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 'auto',
        selectable: true,
        firstDay: 1,
        headerToolbar: { left: 'prev,next today', center: 'title', right: '' },

        // Handles BOTH click-drag ranges and plain single-day clicks — with
        // selectable:true, FullCalendar treats a plain click as a 1-day
        // selection and fires this same callback, so a separate dateClick
        // handler isn't needed (and previously caused a bug: dateClick and
        // select both fired for a single click, and their logic fought each
        // other, netting out to the date being selected then immediately
        // deselected).
        select: function (info) {
            const additive = info.jsEvent && (info.jsEvent.ctrlKey || info.jsEvent.metaKey);
            const daysInRange = [];
            let cursor = new Date(info.start);
            while (cursor < info.end) {
                daysInRange.push(formatDate(cursor));
                cursor.setDate(cursor.getDate() + 1);
            }

            if (additive) {
                // Ctrl/Cmd+click or drag: toggle each day in the range
                // in/out of the existing selection (lets a single
                // already-selected date be ctrl+clicked to deselect it).
                daysInRange.forEach(dateStr => toggleDateSelection(dateStr));
            } else {
                // Plain click or drag: start fresh with just this range.
                clearSelection();
                daysInRange.forEach(dateStr => toggleDateSelection(dateStr, true));
            }
            calendar.unselect();
        },

        // Clicking an EXISTING availability entry (the colored pill) was
        // previously a dead click — FullCalendar routes clicks on events
        // separately from clicks on the day cell, so 'select' never fired
        // and the date couldn't be re-selected to update it. This makes
        // clicking an existing entry behave exactly like clicking the day.
        eventClick: function (info) {
            const dateStr = info.event.startStr.slice(0, 10);
            const additive = info.jsEvent && (info.jsEvent.ctrlKey || info.jsEvent.metaKey);
            if (additive) {
                toggleDateSelection(dateStr);
            } else {
                clearSelection();
                toggleDateSelection(dateStr, true);
            }
        },

        dayCellDidMount: function (arg) {
            const dow = arg.date.getDay();
            if (dow === 0 || dow === 6) {
                arg.el.classList.add('fc-day-weekend-custom');
            }
            const dateStr = formatDate(arg.date);
            if (holidaysByDate[dateStr]) {
                arg.el.classList.add('fc-day-holiday');
                arg.el.setAttribute('title', holidaysByDate[dateStr].name);
            }
        },

        datesSet: function (arg) {
            // See schedule.js for why we slice to just the date portion —
            // FullCalendar can return full ISO datetimes with a timezone
            // offset depending on locale/timeZone settings.
            loadRangeData(arg.startStr.slice(0, 10), arg.endStr.slice(0, 10));
        },

        eventContent: function (arg) {
            const label = arg.event.title;
            return { html: '<div class="fc-event-main-frame">' + escapeHtml(label) + '</div>' };
        },
    });

    calendar.render();

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
        Promise.all([
            fetch(`${window.EHS_BASE_URL}/api/availability.php?start=${start}&end=${end}`).then(r => r.json()),
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

            // Re-apply weekend/holiday classes now that holidaysByDate is fresh
            // (avoids a full calendar.render(), which could re-trigger datesSet).
            applyDayHighlighting();
        }).catch(() => showToast('Could not load calendar data.', true));
    }

    function applyDayHighlighting() {
        calendarEl.querySelectorAll('.fc-day[data-date]').forEach(cell => {
            const dateStr = cell.getAttribute('data-date');
            const dow = new Date(dateStr + 'T00:00:00').getDay();
            cell.classList.toggle('fc-day-weekend-custom', dow === 0 || dow === 6);

            const holiday = holidaysByDate[dateStr];
            cell.classList.toggle('fc-day-holiday', !!holiday);
            if (holiday) {
                cell.setAttribute('title', holiday.name);
            } else {
                cell.removeAttribute('title');
            }
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

        // If exactly one date is selected and it already has a single
        // existing entry, pre-fill the form with its current values so
        // editing feels like "update this" rather than "start from scratch".
        // (A date with both an AM and a PM entry set differently can't be
        // represented in one form, so we fall back to defaults for that case.)
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

        fetch(window.EHS_BASE_URL + '/api/availability.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify({
                dates: Array.from(selectedDates),
                session: session,
                status: status,
                note: note,
            }),
        })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) {
                    showToast(body.error || 'Could not save availability.', true);
                    return;
                }
                showToast('Availability saved for ' + body.updated + ' date(s).');
                modalBackdrop.classList.add('hidden');
                clearSelection();
                loadRangeData(
                    formatDate(calendar.view.activeStart),
                    formatDate(calendar.view.activeEnd)
                );
            })
            .catch(() => showToast('Network error — please try again.', true));
    });
});
