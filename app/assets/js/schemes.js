document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let schemes = [];
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

    function load() {
        fetch(window.EHS_BASE_URL + '/api/schemes.php')
            .then(r => r.json())
            .then(data => { schemes = data.schemes || []; render(); })
            .catch(() => showToast('Could not load schemes.', true));
    }

    function render() {
        const body = document.getElementById('schemes-body');
        document.getElementById('empty-state').classList.toggle('hidden', schemes.length > 0);
        body.innerHTML = schemes.map(s => `
            <tr>
                <td>${escapeHtml(s.name)}</td>
                <td>${escapeHtml(s.code)}</td>
                <td class="mgmt-actions">
                    <button data-edit="${s.id}">Edit</button>
                    <button data-delete="${s.id}" class="danger-link">Delete</button>
                </td>
            </tr>
        `).join('');

        body.querySelectorAll('[data-edit]').forEach(btn =>
            btn.addEventListener('click', () => openModal(schemes.find(s => s.id == btn.dataset.edit))));
        body.querySelectorAll('[data-delete]').forEach(btn =>
            btn.addEventListener('click', () => confirmDelete(btn.dataset.delete)));
    }

    const modalBackdrop = document.getElementById('modal-backdrop');
    document.getElementById('add-btn').addEventListener('click', () => openModal(null));
    document.getElementById('modal-cancel').addEventListener('click', () => modalBackdrop.classList.add('hidden'));

    function openModal(scheme) {
        editingId = scheme ? scheme.id : null;
        document.getElementById('modal-title').textContent = editingId ? 'Edit scheme' : 'Add scheme';
        document.getElementById('scheme-name').value = scheme ? scheme.name : '';
        document.getElementById('scheme-code').value = scheme ? scheme.code : '';
        modalBackdrop.classList.remove('hidden');
    }

    document.getElementById('modal-save').addEventListener('click', function () {
        const name = document.getElementById('scheme-name').value.trim();
        const code = document.getElementById('scheme-code').value.trim();
        if (!name || !code) { showToast('Name and code are both required.', true); return; }

        const url = editingId ? `${window.EHS_BASE_URL}/api/schemes.php?id=${editingId}` : window.EHS_BASE_URL + '/api/schemes.php';
        const method = editingId ? 'PUT' : 'POST';

        fetch(url, { method, headers: csrfHeaders(), body: JSON.stringify({ name, code }) })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not save scheme.', true); return; }
                showToast(editingId ? 'Scheme updated.' : 'Scheme added.');
                modalBackdrop.classList.add('hidden');
                load();
            })
            .catch(() => showToast('Network error — please try again.', true));
    });

    const confirmBackdrop = document.getElementById('confirm-backdrop');
    function confirmDelete(id) {
        deleteId = id;
        const scheme = schemes.find(s => s.id == id);
        document.getElementById('confirm-message').textContent =
            `"${scheme ? scheme.name : ''}" will be permanently removed. This cannot be undone.`;
        confirmBackdrop.classList.remove('hidden');
    }
    document.getElementById('confirm-cancel').addEventListener('click', () => confirmBackdrop.classList.add('hidden'));
    document.getElementById('confirm-ok').addEventListener('click', function () {
        confirmBackdrop.classList.add('hidden');
        fetch(`${window.EHS_BASE_URL}/api/schemes.php?id=${deleteId}`, { method: 'DELETE', headers: csrfHeaders() })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not delete scheme.', true); return; }
                showToast('Scheme deleted.');
                load();
            })
            .catch(() => showToast('Network error — please try again.', true));
    });

    load();
});
