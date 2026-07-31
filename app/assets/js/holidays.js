document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let holidays = [];
    let editingId = null;
    let deleteId = null;

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

    // --- year selector ---
    const yearSelect = document.getElementById('year-select');
    const currentYear = new Date().getFullYear();
    for (let y = currentYear - 1; y <= currentYear + 2; y++) {
        const opt = document.createElement('option');
        opt.value = y;
        opt.textContent = y;
        if (y === currentYear) opt.selected = true;
        yearSelect.appendChild(opt);
    }
    yearSelect.addEventListener('change', load);

    function load() {
        const year = yearSelect.value;
        fetch(`${window.EHS_BASE_URL}/api/holidays.php?start=${year}-01-01&end=${parseInt(year) + 1}-01-01`)
            .then(r => r.json())
            .then(data => { holidays = data.holidays || []; render(); })
            .catch(() => showToast('Could not load holidays.', true));
    }

    function render() {
        const body = document.getElementById('holidays-body');
        document.getElementById('empty-state').classList.toggle('hidden', holidays.length > 0);
        body.innerHTML = holidays.map(h => `
            <tr>
                <td>${escapeHtml(h.date)}</td>
                <td>${escapeHtml(h.name)}</td>
                <td>${h.type === 'company_holiday' ? 'Company' : 'Public'}</td>
                <td class="mgmt-actions">
                    <button data-edit="${h.id}">Edit</button>
                    <button data-delete="${h.id}" class="danger-link">Delete</button>
                </td>
            </tr>
        `).join('');

        body.querySelectorAll('[data-edit]').forEach(btn =>
            btn.addEventListener('click', () => openModal(holidays.find(h => h.id == btn.dataset.edit))));
        body.querySelectorAll('[data-delete]').forEach(btn =>
            btn.addEventListener('click', () => confirmDelete(btn.dataset.delete)));
    }

    // --- single add/edit modal ---
    const modalBackdrop = document.getElementById('modal-backdrop');
    document.getElementById('add-btn').addEventListener('click', () => openModal(null));
    document.getElementById('modal-cancel').addEventListener('click', () => modalBackdrop.classList.add('hidden'));

    function openModal(holiday) {
        editingId = holiday ? holiday.id : null;
        document.getElementById('modal-title').textContent = editingId ? 'Edit holiday' : 'Add holiday';
        document.getElementById('holiday-date').value = holiday ? holiday.date : '';
        document.getElementById('holiday-name').value = holiday ? holiday.name : '';
        document.getElementById('holiday-type').value = holiday ? holiday.type : 'public_holiday';
        modalBackdrop.classList.remove('hidden');
    }

    document.getElementById('modal-save').addEventListener('click', function () {
        const date = document.getElementById('holiday-date').value;
        const name = document.getElementById('holiday-name').value.trim();
        const type = document.getElementById('holiday-type').value;
        if (!date || !name) { showToast('Date and name are both required.', true); return; }

        const url = editingId ? `${window.EHS_BASE_URL}/api/holidays.php?id=${editingId}` : window.EHS_BASE_URL + '/api/holidays.php';
        const method = editingId ? 'PUT' : 'POST';

        fetch(url, { method, headers: csrfHeaders(), body: JSON.stringify({ date, name, type }) })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not save holiday.', true); return; }
                showToast(editingId ? 'Holiday updated.' : 'Holiday added.');
                modalBackdrop.classList.add('hidden');
                load();
            })
            .catch(() => showToast('Network error — please try again.', true));
    });

    // --- bulk import modal ---
    const bulkBackdrop = document.getElementById('bulk-modal-backdrop');
    document.getElementById('bulk-btn').addEventListener('click', () => {
        document.getElementById('bulk-text').value = '';
        bulkBackdrop.classList.remove('hidden');
    });
    document.getElementById('bulk-cancel').addEventListener('click', () => bulkBackdrop.classList.add('hidden'));

    document.getElementById('bulk-save').addEventListener('click', function () {
        const lines = document.getElementById('bulk-text').value.split('\n').map(l => l.trim()).filter(Boolean);
        const items = lines.map(line => {
            const parts = line.split(',').map(p => p.trim());
            const [date, name, typeWord] = parts;
            return {
                date: date,
                name: name || '',
                type: (typeWord || '').toLowerCase() === 'company' ? 'company_holiday' : 'public_holiday',
            };
        });

        if (items.length === 0) { showToast('Nothing to import.', true); return; }

        fetch(window.EHS_BASE_URL + '/api/holidays.php', { method: 'POST', headers: csrfHeaders(), body: JSON.stringify({ holidays: items }) })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not import holidays.', true); return; }
                let msg = `Imported ${body.imported} holiday(s).`;
                if (body.skipped && body.skipped.length) msg += ` Skipped ${body.skipped.length}: ${body.skipped.join('; ')}`;
                showToast(msg, body.skipped && body.skipped.length > 0);
                bulkBackdrop.classList.add('hidden');
                load();
            })
            .catch(() => showToast('Network error — please try again.', true));
    });

    // --- delete confirm ---
    const confirmBackdrop = document.getElementById('confirm-backdrop');
    function confirmDelete(id) {
        deleteId = id;
        const holiday = holidays.find(h => h.id == id);
        document.getElementById('confirm-message').textContent =
            `"${holiday ? holiday.name : ''}" will be permanently removed.`;
        confirmBackdrop.classList.remove('hidden');
    }
    document.getElementById('confirm-cancel').addEventListener('click', () => confirmBackdrop.classList.add('hidden'));
    document.getElementById('confirm-ok').addEventListener('click', function () {
        confirmBackdrop.classList.add('hidden');
        fetch(`${window.EHS_BASE_URL}/api/holidays.php?id=${deleteId}`, { method: 'DELETE', headers: csrfHeaders() })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not delete holiday.', true); return; }
                showToast('Holiday deleted.');
                load();
            })
            .catch(() => showToast('Network error — please try again.', true));
    });

    load();
});
