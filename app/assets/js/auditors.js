document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let auditors = [];
    let schemes = [];
    let editingId = null;
    let selectedSchemeIds = new Set();

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
        Promise.all([
            fetch(window.EHS_BASE_URL + '/api/auditor_profile.php').then(r => r.json()),
            fetch(window.EHS_BASE_URL + '/api/schemes.php').then(r => r.json()),
        ]).then(([auditorData, schemeData]) => {
            auditors = auditorData.auditors || [];
            schemes = schemeData.schemes || [];
            render();
        }).catch(() => showToast('Could not load auditors.', true));
    }

    function schemeNames(schemeIds) {
        return schemeIds
            .map(id => schemes.find(s => s.id === id))
            .filter(Boolean)
            .map(s => s.code)
            .join(', ') || '—';
    }

    function render() {
        const body = document.getElementById('auditors-body');
        document.getElementById('empty-state').classList.toggle('hidden', auditors.length > 0);
        body.innerHTML = auditors.map(a => `
            <tr>
                <td><span class="color-swatch" style="background:${a.color_hex}"></span></td>
                <td>${escapeHtml(a.name)}</td>
                <td>${escapeHtml(a.email)}</td>
                <td>${escapeHtml(a.phone || '—')}</td>
                <td>${escapeHtml(schemeNames(a.scheme_ids))}</td>
                <td><span class="badge ${a.status === 'active' ? 'badge-active' : 'badge-inactive'}">${a.status}</span></td>
                <td class="mgmt-actions"><button data-edit="${a.id}">Edit</button></td>
            </tr>
        `).join('');

        body.querySelectorAll('[data-edit]').forEach(btn =>
            btn.addEventListener('click', () => openModal(auditors.find(a => a.id == btn.dataset.edit))));
    }

    const modalBackdrop = document.getElementById('modal-backdrop');
    document.getElementById('modal-cancel').addEventListener('click', () => modalBackdrop.classList.add('hidden'));

    function renderSchemeCheckboxes() {
        const container = document.getElementById('scheme-checkboxes');
        container.innerHTML = schemes.map(s => `
            <label class="chip-checkbox" data-scheme-id="${s.id}" style="${selectedSchemeIds.has(s.id) ? 'background:#dbeafe;' : ''}">
                <input type="checkbox" style="display:none">
                ${escapeHtml(s.name)}
            </label>
        `).join('');

        container.querySelectorAll('.chip-checkbox').forEach(chip => {
            chip.addEventListener('click', function (e) {
                e.preventDefault();
                const id = parseInt(this.dataset.schemeId, 10);
                if (selectedSchemeIds.has(id)) {
                    selectedSchemeIds.delete(id);
                    this.style.background = '';
                } else {
                    selectedSchemeIds.add(id);
                    this.style.background = '#dbeafe';
                }
            });
        });
    }

    function openModal(auditor) {
        editingId = auditor.id;
        document.getElementById('modal-name').textContent = auditor.name;
        document.getElementById('auditor-color').value = auditor.color_hex;
        document.getElementById('auditor-phone').value = auditor.phone || '';
        document.getElementById('auditor-status').value = auditor.status;
        selectedSchemeIds = new Set(auditor.scheme_ids);
        renderSchemeCheckboxes();
        modalBackdrop.classList.remove('hidden');
    }

    document.getElementById('modal-save').addEventListener('click', function () {
        const payload = {
            color_hex: document.getElementById('auditor-color').value,
            phone: document.getElementById('auditor-phone').value.trim(),
            status: document.getElementById('auditor-status').value,
            scheme_ids: Array.from(selectedSchemeIds),
        };

        fetch(`${window.EHS_BASE_URL}/api/auditor_profile.php?id=${editingId}`, { method: 'PUT', headers: csrfHeaders(), body: JSON.stringify(payload) })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not save profile.', true); return; }
                showToast('Auditor profile updated.');
                modalBackdrop.classList.add('hidden');
                load();
            })
            .catch(() => showToast('Network error — please try again.', true));
    });

    load();
});
