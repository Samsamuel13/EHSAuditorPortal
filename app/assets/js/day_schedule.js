// File: assets/js/day_schedule.js
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const dayPicker = document.getElementById('day-picker');

    const toastEl = document.getElementById('toast');
    let toastTimer = null;
    function showToast(message, isError = false) {
        clearTimeout(toastTimer);
        toastEl.textContent = message;
        toastEl.classList.toggle('error', isError);
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

    function formatDate(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    // --- date navigation ---
    dayPicker.value = formatDate(new Date());

    document.getElementById('today-btn').addEventListener('click', function () {
        dayPicker.value = formatDate(new Date());
        load();
    });
    document.getElementById('prev-day-btn').addEventListener('click', function () {
        const d = new Date(dayPicker.value + 'T00:00:00');
        d.setDate(d.getDate() - 1);
        dayPicker.value = formatDate(d);
        load();
    });
    document.getElementById('next-day-btn').addEventListener('click', function () {
        const d = new Date(dayPicker.value + 'T00:00:00');
        d.setDate(d.getDate() + 1);
        dayPicker.value = formatDate(d);
        load();
    });
    dayPicker.addEventListener('change', load);

    // --- load + render ---
    function load() {
        const date = dayPicker.value;
        // The API takes a range; for a single day, end = date + 1 day.
        const end = new Date(date + 'T00:00:00');
        end.setDate(end.getDate() + 1);

        fetch(`${window.EHS_BASE_URL}/api/personal_schedule.php?start=${date}&end=${formatDate(end)}`)
            .then(r => r.json())
            .then(data => render(data.items || []))
            .catch(() => showToast('Could not load your schedule.', true));
    }

    function render(items) {
        const listEl = document.getElementById('items-list');
        document.getElementById('empty-state').classList.toggle('hidden', items.length > 0);

        listEl.innerHTML = items.map(item => `
            <div class="list-item">
                <div>
                    ${item.time_label ? `<strong>${escapeHtml(item.time_label)}</strong> — ` : ''}${escapeHtml(item.title)}
                </div>
                <button class="danger-link" data-id="${item.id}" style="background:none; border:none; cursor:pointer;">Delete</button>
            </div>
        `).join('');

        listEl.querySelectorAll('[data-id]').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.dataset.id;
                fetch(`${window.EHS_BASE_URL}/api/personal_schedule.php?id=${id}`, { method: 'DELETE', headers: csrfHeaders() })
                    .then(r => r.json().then(body => ({ ok: r.ok, body })))
                    .then(({ ok, body }) => {
                        if (!ok) { showToast(body.error || 'Could not delete item.', true); return; }
                        load();
                    })
                    .catch(() => showToast('Network error — please try again.', true));
            });
        });
    }

    // --- add item ---
    document.getElementById('add-item-btn').addEventListener('click', function () {
        const title = document.getElementById('new-title').value.trim();
        const timeLabel = document.getElementById('new-time-label').value.trim();

        if (!title) {
            showToast('Please enter what this item is.', true);
            return;
        }

        fetch(`${window.EHS_BASE_URL}/api/personal_schedule.php`, {
            method: 'POST',
            headers: csrfHeaders(),
            body: JSON.stringify({ date: dayPicker.value, time_label: timeLabel, title: title }),
        })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not add item.', true); return; }
                document.getElementById('new-title').value = '';
                document.getElementById('new-time-label').value = '';
                showToast('Added.');
                load();
            })
            .catch(() => showToast('Network error — please try again.', true));
    });

    load();
});
