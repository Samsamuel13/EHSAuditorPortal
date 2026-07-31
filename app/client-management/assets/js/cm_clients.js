document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const API_BASE = window.EHS_BASE_URL + '/client-management/api';

    let clients = [];
    let total = 0;
    let page = 1;
    const perPage = 25;
    let editingId = null;

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

    function statusBadge(status) {
        const labels = { active: 'Active', suspended: 'Suspended', withdrawn: 'Withdrawn', blacklisted: 'Blacklisted' };
        return `<span class="badge cm-badge-${status}">${labels[status] || status}</span>`;
    }

    // --- Scheme type filter dropdown ---
    function loadSchemeTypes() {
        fetch(API_BASE + '/scheme_types.php')
            .then(r => r.json())
            .then(data => {
                const sel = document.getElementById('filter-scheme');
                (data.scheme_types || []).forEach(st => {
                    const opt = document.createElement('option');
                    opt.value = st.id;
                    opt.textContent = `${st.name} (${st.category})`;
                    sel.appendChild(opt);
                });
            })
            .catch(() => {});
    }

    // --- List loading ---
    function currentFilters() {
        return {
            q: document.getElementById('filter-q').value.trim(),
            industry: document.getElementById('filter-industry').value.trim(),
            status: document.getElementById('filter-status').value,
            scheme_type_id: document.getElementById('filter-scheme').value,
            expiring_within_days: document.getElementById('filter-expiring').value,
        };
    }

    function load() {
        const filters = currentFilters();
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([k, v]) => { if (v) params.set(k, v); });
        params.set('page', page);
        params.set('per_page', perPage);

        fetch(API_BASE + '/clients.php?' + params.toString())
            .then(r => r.json())
            .then(data => {
                clients = data.clients || [];
                total = data.total || 0;
                render();
            })
            .catch(() => showToast('Could not load clients.', true));
    }

    function render() {
        const body = document.getElementById('clients-body');
        document.getElementById('empty-state').classList.toggle('hidden', clients.length > 0);
        document.getElementById('results-count').textContent = total + (total === 1 ? ' client' : ' clients');

        body.innerHTML = clients.map(c => `
            <tr>
                <td><a href="${window.EHS_BASE_URL}/client-management/client_detail.php?id=${c.id}">${escapeHtml(c.company_name)}</a></td>
                <td>${escapeHtml(c.uen_registration_no || '—')}</td>
                <td>${escapeHtml(c.industry_sector || '—')}</td>
                <td>${escapeHtml(c.contact_person || '—')}</td>
                <td>${statusBadge(c.status)}</td>
                <td class="mgmt-actions">
                    <button data-edit="${c.id}">Edit</button>
                </td>
            </tr>
        `).join('');

        body.querySelectorAll('[data-edit]').forEach(btn =>
            btn.addEventListener('click', () => openModal(clients.find(c => c.id == btn.dataset.edit))));

        renderPagination();
    }

    function renderPagination() {
        const bar = document.getElementById('pagination-bar');
        const totalPages = Math.max(1, Math.ceil(total / perPage));
        if (totalPages <= 1) { bar.innerHTML = ''; return; }
        bar.innerHTML = `
            <button class="btn btn-ghost-light btn-small" style="width:auto;" ${page <= 1 ? 'disabled' : ''} id="pg-prev">&larr; Prev</button>
            <span>Page ${page} of ${totalPages}</span>
            <button class="btn btn-ghost-light btn-small" style="width:auto;" ${page >= totalPages ? 'disabled' : ''} id="pg-next">Next &rarr;</button>
        `;
        const prev = document.getElementById('pg-prev');
        const next = document.getElementById('pg-next');
        if (prev) prev.addEventListener('click', () => { page--; load(); });
        if (next) next.addEventListener('click', () => { page++; load(); });
    }

    ['filter-q', 'filter-industry', 'filter-status', 'filter-scheme', 'filter-expiring'].forEach(id => {
        const el = document.getElementById(id);
        el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', debounce(() => { page = 1; load(); }, 300));
    });
    document.getElementById('filter-clear').addEventListener('click', () => {
        document.getElementById('filter-q').value = '';
        document.getElementById('filter-industry').value = '';
        document.getElementById('filter-status').value = '';
        document.getElementById('filter-scheme').value = '';
        document.getElementById('filter-expiring').value = '';
        page = 1;
        load();
    });

    function debounce(fn, ms) {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
    }

    // --- Add/Edit modal ---
    const modalBackdrop = document.getElementById('modal-backdrop');
    document.getElementById('add-btn').addEventListener('click', () => openModal(null));
    document.getElementById('modal-cancel').addEventListener('click', () => modalBackdrop.classList.add('hidden'));

    const fieldIds = ['company-name', 'uen', 'industry', 'address', 'contact-person', 'contact-designation', 'phone', 'email', 'website', 'notes'];

    function openModal(client) {
        editingId = client ? client.id : null;
        document.getElementById('modal-title').textContent = editingId ? 'Edit client' : 'Add client';

        document.getElementById('f-company-name').value = client ? client.company_name : '';
        document.getElementById('f-uen').value = client ? (client.uen_registration_no || '') : '';
        document.getElementById('f-industry').value = client ? (client.industry_sector || '') : '';
        document.getElementById('f-address').value = client ? (client.address || '') : '';
        document.getElementById('f-contact-person').value = client ? (client.contact_person || '') : '';
        document.getElementById('f-contact-designation').value = client ? (client.contact_designation || '') : '';
        document.getElementById('f-phone').value = client ? (client.phone || '') : '';
        document.getElementById('f-email').value = client ? (client.email || '') : '';
        document.getElementById('f-website').value = client ? (client.website || '') : '';
        document.getElementById('f-status').value = client ? client.status : 'active';
        document.getElementById('f-notes').value = client ? (client.notes || '') : '';

        modalBackdrop.classList.remove('hidden');
    }

    document.getElementById('modal-save').addEventListener('click', function () {
        const companyName = document.getElementById('f-company-name').value.trim();
        if (!companyName) { showToast('Company name is required.', true); return; }

        const payload = {
            company_name: companyName,
            uen_registration_no: document.getElementById('f-uen').value.trim(),
            industry_sector: document.getElementById('f-industry').value.trim(),
            address: document.getElementById('f-address').value.trim(),
            contact_person: document.getElementById('f-contact-person').value.trim(),
            contact_designation: document.getElementById('f-contact-designation').value.trim(),
            phone: document.getElementById('f-phone').value.trim(),
            email: document.getElementById('f-email').value.trim(),
            website: document.getElementById('f-website').value.trim(),
            status: document.getElementById('f-status').value,
            notes: document.getElementById('f-notes').value.trim(),
        };

        const url = editingId ? `${API_BASE}/clients.php?id=${editingId}` : `${API_BASE}/clients.php`;
        const method = editingId ? 'PUT' : 'POST';

        fetch(url, { method, headers: csrfHeaders(), body: JSON.stringify(payload) })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not save client.', true); return; }
                showToast(editingId ? 'Client updated.' : 'Client added.');
                modalBackdrop.classList.add('hidden');
                load();
            })
            .catch(() => showToast('Network error — please try again.', true));
    });

    document.getElementById('export-btn').addEventListener('click', () => {
        const params = new URLSearchParams();
        Object.entries(currentFilters()).forEach(([k, v]) => { if (v) params.set(k, v); });
        window.location.href = `${API_BASE}/export_xlsx.php?${params.toString()}`;
    });

    loadSchemeTypes();
    load();
});
