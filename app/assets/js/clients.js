document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let clients = [];
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
        fetch(window.EHS_BASE_URL + '/api/clients.php')
            .then(r => r.json())
            .then(data => {
                clients = data.clients || [];
                render();
            })
            .catch(() => showToast('Could not load clients.', true));
    }

    function render() {
        const body = document.getElementById('clients-body');
        document.getElementById('empty-state').classList.toggle('hidden', clients.length > 0);
        body.innerHTML = clients.map(c => `
            <tr>
                <td>${escapeHtml(c.name)}</td>
                <td>${escapeHtml(c.notes || '—')}</td>
                <td class="mgmt-actions">
                    <button data-edit="${c.id}">Edit</button>
                    <button data-delete="${c.id}" class="danger-link">Delete</button>
                </td>
            </tr>
        `).join('');

        body.querySelectorAll('[data-edit]').forEach(btn =>
            btn.addEventListener('click', () => openModal(clients.find(c => c.id == btn.dataset.edit))));
        body.querySelectorAll('[data-delete]').forEach(btn =>
            btn.addEventListener('click', () => confirmDelete(btn.dataset.delete)));
    }

    const modalBackdrop = document.getElementById('modal-backdrop');
    document.getElementById('add-btn').addEventListener('click', () => openModal(null));
    document.getElementById('modal-cancel').addEventListener('click', () => modalBackdrop.classList.add('hidden'));

    function openModal(client) {
        editingId = client ? client.id : null;
        document.getElementById('modal-title').textContent = editingId ? 'Edit client' : 'Add client';
        document.getElementById('client-name').value = client ? client.name : '';
        document.getElementById('client-notes').value = client ? (client.notes || '') : '';
        modalBackdrop.classList.remove('hidden');
    }

    document.getElementById('modal-save').addEventListener('click', function () {
        const name = document.getElementById('client-name').value.trim();
        const notes = document.getElementById('client-notes').value.trim();
        if (!name) { showToast('Name is required.', true); return; }

        const url = editingId ? `${window.EHS_BASE_URL}/api/clients.php?id=${editingId}` : window.EHS_BASE_URL + '/api/clients.php';
        const method = editingId ? 'PUT' : 'POST';

        fetch(url, { method, headers: csrfHeaders(), body: JSON.stringify({ name, notes }) })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not save client.', true); return; }
                showToast(editingId ? 'Client updated.' : 'Client added.');
                modalBackdrop.classList.add('hidden');
                load();
            })
            .catch(() => showToast('Network error — please try again.', true));
    });

    const confirmBackdrop = document.getElementById('confirm-backdrop');
    function confirmDelete(id) {
        deleteId = id;
        const client = clients.find(c => c.id == id);
        document.getElementById('confirm-message').textContent =
            `"${client ? client.name : ''}" will be permanently removed. This cannot be undone.`;
        confirmBackdrop.classList.remove('hidden');
    }
    document.getElementById('confirm-cancel').addEventListener('click', () => confirmBackdrop.classList.add('hidden'));
    document.getElementById('confirm-ok').addEventListener('click', function () {
        confirmBackdrop.classList.add('hidden');
        fetch(`${window.EHS_BASE_URL}/api/clients.php?id=${deleteId}`, { method: 'DELETE', headers: csrfHeaders() })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not delete client.', true); return; }
                showToast('Client deleted.');
                load();
            })
            .catch(() => showToast('Network error — please try again.', true));
    });

    load();
});
