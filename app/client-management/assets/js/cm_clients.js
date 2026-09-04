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

    function entityBadge(entity) {
        const color = entity === 'Axiscert' ? '#7c3aed' : '#2563eb';
        return `<span class="badge" style="background:${color}22; color:${color}; border:1px solid ${color}55;">${escapeHtml(entity || 'EHS')}</span>`;
    }

    let schemeTypes = [];

    // --- Scheme type filter dropdown (+ first-certification dropdown) ---
    function loadSchemeTypes() {
        fetch(API_BASE + '/scheme_types.php')
            .then(r => r.json())
            .then(data => {
                schemeTypes = data.scheme_types || [];
                const sel = document.getElementById('filter-scheme');
                schemeTypes.forEach(st => {
                    const opt = document.createElement('option');
                    opt.value = st.id;
                    opt.textContent = `${st.name} (${st.category})`;
                    sel.appendChild(opt);
                });
                const fcSel = document.getElementById('fc-scheme-type');
                fcSel.innerHTML = schemeTypes.map(st => `<option value="${st.id}">${st.name} (${st.category})</option>`).join('');
            })
            .catch(() => {});
    }

    // --- List loading ---
    function currentFilters() {
        return {
            q: document.getElementById('filter-q').value.trim(),
            industry: document.getElementById('filter-industry').value.trim(),
            status: document.getElementById('filter-status').value,
            entity: document.getElementById('filter-entity').value,
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
                <td>${escapeHtml(c.consultant || '—')}</td>
                <td>${entityBadge(c.entity)}</td>
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

    ['filter-q', 'filter-industry', 'filter-status', 'filter-entity', 'filter-scheme', 'filter-expiring'].forEach(id => {
        const el = document.getElementById(id);
        el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', debounce(() => { page = 1; load(); }, 300));
    });
    document.getElementById('filter-clear').addEventListener('click', () => {
        document.getElementById('filter-q').value = '';
        document.getElementById('filter-industry').value = '';
        document.getElementById('filter-status').value = '';
        document.getElementById('filter-entity').value = '';
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
        document.getElementById('f-consultant').value = client ? (client.consultant || '') : '';
        document.getElementById('f-phone').value = client ? (client.phone || '') : '';
        document.getElementById('f-email').value = client ? (client.email || '') : '';
        document.getElementById('f-website').value = client ? (client.website || '') : '';
        document.getElementById('f-status').value = client ? client.status : 'active';
        document.getElementById('f-entity').value = client ? (client.entity || 'EHS') : 'EHS';
        document.getElementById('f-notes').value = client ? (client.notes || '') : '';

        // First-certification section only makes sense when adding a brand
        // new client — an existing client already manages certifications on
        // its own detail page, so don't show a confusing duplicate control here.
        const fcSection = document.getElementById('first-cert-section');
        fcSection.classList.toggle('hidden', !!editingId);
        document.getElementById('fc-enable').checked = false;
        document.getElementById('fc-fields').classList.add('hidden');
        document.getElementById('fc-accreditation-body').value = '';
        document.getElementById('fc-cert-number').value = '';
        document.getElementById('fc-status').value = 'active';
        document.getElementById('fc-issue-date').value = '';
        document.getElementById('fc-surv1-date').value = '';
        document.getElementById('fc-surv2-date').value = '';
        document.getElementById('fc-expiry-date').value = '';

        modalBackdrop.classList.remove('hidden');
    }

    document.getElementById('fc-enable').addEventListener('change', function () {
        document.getElementById('fc-fields').classList.toggle('hidden', !this.checked);
    });

    document.getElementById('modal-save').addEventListener('click', function () {
        const companyName = document.getElementById('f-company-name').value.trim();
        if (!companyName) { showToast('Company name is required.', true); return; }

        const wantsFirstCert = !editingId && document.getElementById('fc-enable').checked;
        if (wantsFirstCert && !document.getElementById('fc-scheme-type').value) {
            showToast('Choose a scheme type for the certification, or uncheck "Add a certification now".', true);
            return;
        }

        const payload = {
            company_name: companyName,
            uen_registration_no: document.getElementById('f-uen').value.trim(),
            industry_sector: document.getElementById('f-industry').value.trim(),
            address: document.getElementById('f-address').value.trim(),
            contact_person: document.getElementById('f-contact-person').value.trim(),
            contact_designation: document.getElementById('f-contact-designation').value.trim(),
            consultant: document.getElementById('f-consultant').value.trim(),
            phone: document.getElementById('f-phone').value.trim(),
            email: document.getElementById('f-email').value.trim(),
            website: document.getElementById('f-website').value.trim(),
            status: document.getElementById('f-status').value,
            entity: document.getElementById('f-entity').value,
            notes: document.getElementById('f-notes').value.trim(),
        };

        const url = editingId ? `${API_BASE}/clients.php?id=${editingId}` : `${API_BASE}/clients.php`;
        const method = editingId ? 'PUT' : 'POST';

        fetch(url, { method, headers: csrfHeaders(), body: JSON.stringify(payload) })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not save client.', true); return; }

                if (!wantsFirstCert) {
                    showToast(editingId ? 'Client updated.' : 'Client added.');
                    modalBackdrop.classList.add('hidden');
                    load();
                    return;
                }

                // Client created — now create its first certification. The
                // client already exists at this point even if this second
                // call fails, so on failure we still refresh the list and
                // point the user to the detail page to finish manually,
                // rather than leaving them stuck on a half-succeeded modal.
                const newClientId = body.client.id;
                const certPayload = {
                    cm_client_id: newClientId,
                    cm_scheme_type_id: Number(document.getElementById('fc-scheme-type').value),
                    accreditation_body: document.getElementById('fc-accreditation-body').value.trim(),
                    certificate_number: document.getElementById('fc-cert-number').value.trim(),
                    status: document.getElementById('fc-status').value,
                    issue_date: document.getElementById('fc-issue-date').value,
                    surveillance_1_date: document.getElementById('fc-surv1-date').value,
                    surveillance_2_date: document.getElementById('fc-surv2-date').value,
                    expiry_date: document.getElementById('fc-expiry-date').value,
                };

                fetch(`${API_BASE}/certifications.php`, { method: 'POST', headers: csrfHeaders(), body: JSON.stringify(certPayload) })
                    .then(r => r.json().then(certBody => ({ ok: r.ok, certBody })))
                    .then(({ ok: certOk, certBody }) => {
                        modalBackdrop.classList.add('hidden');
                        if (!certOk) {
                            showToast(`Client added, but the certification couldn't be saved: ${certBody.error || 'unknown error'}. Add it from the client's page.`, true);
                        } else {
                            showToast('Client and certification added.');
                        }
                        load();
                    })
                    .catch(() => {
                        modalBackdrop.classList.add('hidden');
                        showToast('Client added, but the certification failed to save (network error). Add it from the client\'s page.', true);
                        load();
                    });
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