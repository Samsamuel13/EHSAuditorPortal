document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let users = [];
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
        fetch(window.EHS_BASE_URL + '/api/users.php')
            .then(r => r.json())
            .then(data => { users = data.users || []; render(); })
            .catch(() => showToast('Could not load users.', true));
    }

    function roleLabel(role) {
        return { super_admin: 'Super admin', admin: 'Admin', auditor: 'Auditor' }[role] || role;
    }

    function render() {
        const body = document.getElementById('users-body');
        document.getElementById('empty-state').classList.toggle('hidden', users.length > 0);
        body.innerHTML = users.map(u => `
            <tr>
                <td><span class="color-swatch" style="background:${u.color_hex}"></span></td>
                <td>${escapeHtml(u.name)}</td>
                <td>${escapeHtml(u.username)}</td>
                <td>${escapeHtml(u.email)}</td>
                <td><span class="badge badge-role">${roleLabel(u.role)}</span></td>
                <td><span class="badge ${u.status === 'active' ? 'badge-active' : 'badge-inactive'}">${u.status}</span></td>
                <td class="mgmt-actions">
                    <button data-edit="${u.id}">Edit</button>
                    <button data-delete="${u.id}" class="danger-link">Delete</button>
                </td>
            </tr>
        `).join('');

        body.querySelectorAll('[data-edit]').forEach(btn =>
            btn.addEventListener('click', () => openModal(users.find(u => u.id == btn.dataset.edit))));
        body.querySelectorAll('[data-delete]').forEach(btn =>
            btn.addEventListener('click', () => confirmDelete(btn.dataset.delete)));
    }

    const modalBackdrop = document.getElementById('modal-backdrop');
    document.getElementById('add-btn').addEventListener('click', () => openModal(null));
    document.getElementById('modal-cancel').addEventListener('click', () => modalBackdrop.classList.add('hidden'));

    function openModal(user) {
        editingId = user ? user.id : null;
        document.getElementById('modal-title').textContent = editingId ? 'Edit user' : 'Add user';
        document.getElementById('user-name').value = user ? user.name : '';
        document.getElementById('user-username').value = user ? user.username : '';
        document.getElementById('user-email').value = user ? user.email : '';
        document.getElementById('user-role').value = user ? user.role : 'auditor';
        document.getElementById('user-status').value = user ? user.status : 'active';
        document.getElementById('user-color').value = user ? user.color_hex : '#3788d8';
        document.getElementById('user-phone').value = user ? (user.phone || '') : '';
        document.getElementById('user-password').value = '';
        document.getElementById('password-hint').textContent = editingId ? '(leave blank to keep current password)' : '(required)';
        modalBackdrop.classList.remove('hidden');
    }

    document.getElementById('modal-save').addEventListener('click', function () {
        const payload = {
            name: document.getElementById('user-name').value.trim(),
            username: document.getElementById('user-username').value.trim(),
            email: document.getElementById('user-email').value.trim(),
            role: document.getElementById('user-role').value,
            status: document.getElementById('user-status').value,
            color_hex: document.getElementById('user-color').value,
            phone: document.getElementById('user-phone').value.trim(),
            password: document.getElementById('user-password').value,
        };

        if (!editingId && payload.password.length < 8) {
            showToast('Password must be at least 8 characters for a new user.', true);
            return;
        }

        const url = editingId ? `${window.EHS_BASE_URL}/api/users.php?id=${editingId}` : window.EHS_BASE_URL + '/api/users.php';
        const method = editingId ? 'PUT' : 'POST';

        fetch(url, { method, headers: csrfHeaders(), body: JSON.stringify(payload) })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not save user.', true); return; }
                showToast(editingId ? 'User updated.' : 'User created.');
                modalBackdrop.classList.add('hidden');
                load();
            })
            .catch(() => showToast('Network error — please try again.', true));
    });

    const confirmBackdrop = document.getElementById('confirm-backdrop');
    function confirmDelete(id) {
        deleteId = id;
        const user = users.find(u => u.id == id);
        document.getElementById('confirm-message').textContent =
            `"${user ? user.name : ''}" will be permanently removed. If they have historical records, deletion will be blocked — deactivate instead.`;
        confirmBackdrop.classList.remove('hidden');
    }
    document.getElementById('confirm-cancel').addEventListener('click', () => confirmBackdrop.classList.add('hidden'));
    document.getElementById('confirm-ok').addEventListener('click', function () {
        confirmBackdrop.classList.add('hidden');
        fetch(`${window.EHS_BASE_URL}/api/users.php?id=${deleteId}`, { method: 'DELETE', headers: csrfHeaders() })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not delete user.', true); return; }
                showToast('User deleted.');
                load();
            })
            .catch(() => showToast('Network error — please try again.', true));
    });

    load();
});
