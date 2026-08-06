document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const clientId = document.querySelector('meta[name="cm-client-id"]').content;
    const API_BASE = window.EHS_BASE_URL + '/client-management/api';

    let certs = [];
    let schemeTypes = [];
    let users = [];
    let editingCertId = null;
    let activeCertIdForDocs = null;
    let docs = [];
    let pendingDeleteDocId = null;

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
    function csrfHeaders(json = true) {
        return json ? { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken } : { 'X-CSRF-Token': csrfToken };
    }
    const cycleLabels = { initial: 'Initial', surveillance_1: 'Surveillance 1', surveillance_2: 'Surveillance 2', recertification: 'Recertification' };
    const statusLabels = { pending: 'Pending', active: 'Active', expired: 'Expired', suspended: 'Suspended', withdrawn: 'Withdrawn' };

    function nextDueCell(nextDue) {
        if (!nextDue || !nextDue.date) return '—';
        const cls = nextDue.overdue ? 'cm-badge-red' : 'cm-badge-amber';
        return `${escapeHtml(nextDue.label)}: ${escapeHtml(nextDue.date)} <span class="badge ${cls}">${nextDue.overdue ? 'Overdue' : 'Upcoming'}</span>`;
    }

    // --- Lookups ---
    function loadLookups() {
        return Promise.all([
            fetch(API_BASE + '/scheme_types.php').then(r => r.json()).then(d => { schemeTypes = d.scheme_types || []; }),
            fetch(API_BASE + '/users_lookup.php').then(r => r.json()).then(d => { users = d.users || []; }),
        ]);
    }

    function populateCertDropdowns() {
        const schemeSel = document.getElementById('c-scheme-type');
        schemeSel.innerHTML = schemeTypes.map(st => `<option value="${st.id}">${escapeHtml(st.name)} (${escapeHtml(st.category)})</option>`).join('');

        const respSel = document.getElementById('c-responsible-person');
        respSel.innerHTML = '<option value="">— None —</option>' +
            users.map(u => `<option value="${u.id}">${escapeHtml(u.name)}</option>`).join('');
    }

    // --- Certifications list ---
    function loadCerts() {
        fetch(`${API_BASE}/certifications.php?client_id=${clientId}`)
            .then(r => r.json())
            .then(data => { certs = data.certifications || []; renderCerts(); })
            .catch(() => showToast('Could not load certifications.', true));
    }

    function renderCerts() {
        const body = document.getElementById('certs-body');
        document.getElementById('certs-empty-state').classList.toggle('hidden', certs.length > 0);

        body.innerHTML = certs.map(c => `
            <tr>
                <td>${escapeHtml(c.scheme_name)}</td>
                <td>${escapeHtml(c.certificate_number || '—')}</td>
                <td>${escapeHtml(c.accreditation_body || '—')}</td>
                <td>${escapeHtml(c.issue_date || '—')}</td>
                <td>${escapeHtml(c.surveillance_1_date || '—')}</td>
                <td>${escapeHtml(c.surveillance_2_date || '—')}</td>
                <td>${escapeHtml(c.expiry_date || '—')}</td>
                <td>${nextDueCell(c.next_due)}</td>
                <td><span class="badge cm-badge-${c.status === 'active' ? 'active' : (c.status === 'expired' ? 'blacklisted' : (c.status === 'pending' ? 'suspended' : c.status))}">${statusLabels[c.status] || c.status}</span></td>
                <td class="mgmt-actions">
                    <button data-edit="${c.id}">Edit</button>
                    <button data-docs="${c.id}">Documents</button>
                </td>
            </tr>
        `).join('');

        body.querySelectorAll('[data-edit]').forEach(btn =>
            btn.addEventListener('click', () => openCertModal(certs.find(c => c.id == btn.dataset.edit))));
        body.querySelectorAll('[data-docs]').forEach(btn =>
            btn.addEventListener('click', () => openDocsModal(btn.dataset.docs)));
    }

    // --- Add/Edit certification modal ---
    const certModalBackdrop = document.getElementById('cert-modal-backdrop');
    document.getElementById('add-cert-btn').addEventListener('click', () => openCertModal(null));
    document.getElementById('cert-modal-cancel').addEventListener('click', () => certModalBackdrop.classList.add('hidden'));

    function openCertModal(cert) {
        editingCertId = cert ? cert.id : null;
        document.getElementById('cert-modal-title').textContent = editingCertId ? 'Edit certification' : 'Add certification';

        document.getElementById('c-scheme-type').value = cert ? cert.cm_scheme_type_id : (schemeTypes[0] ? schemeTypes[0].id : '');
        document.getElementById('c-accreditation-body').value = cert ? (cert.accreditation_body || '') : '';
        document.getElementById('c-cert-number').value = cert ? (cert.certificate_number || '') : '';
        document.getElementById('c-cycle-stage').value = cert ? cert.cycle_stage : 'initial';
        document.getElementById('c-issue-date').value = cert ? (cert.issue_date || '') : '';
        document.getElementById('c-surv1-date').value = cert ? (cert.surveillance_1_date || '') : '';
        document.getElementById('c-surv2-date').value = cert ? (cert.surveillance_2_date || '') : '';
        document.getElementById('c-expiry-date').value = cert ? (cert.expiry_date || '') : '';
        document.getElementById('c-status').value = cert ? cert.status : 'pending';
        document.getElementById('c-responsible-person').value = cert ? (cert.responsible_person_id || '') : '';
        document.getElementById('c-notes').value = cert ? (cert.notes || '') : '';

        certModalBackdrop.classList.remove('hidden');
    }

    document.getElementById('cert-modal-save').addEventListener('click', function () {
        const payload = {
            cm_client_id: Number(clientId),
            cm_scheme_type_id: Number(document.getElementById('c-scheme-type').value),
            accreditation_body: document.getElementById('c-accreditation-body').value.trim(),
            certificate_number: document.getElementById('c-cert-number').value.trim(),
            cycle_stage: document.getElementById('c-cycle-stage').value,
            issue_date: document.getElementById('c-issue-date').value,
            surveillance_1_date: document.getElementById('c-surv1-date').value,
            surveillance_2_date: document.getElementById('c-surv2-date').value,
            expiry_date: document.getElementById('c-expiry-date').value,
            status: document.getElementById('c-status').value,
            responsible_person_id: document.getElementById('c-responsible-person').value ? Number(document.getElementById('c-responsible-person').value) : null,
            notes: document.getElementById('c-notes').value.trim(),
        };
        if (!payload.cm_scheme_type_id) { showToast('Scheme type is required.', true); return; }

        const url = editingCertId ? `${API_BASE}/certifications.php?id=${editingCertId}` : `${API_BASE}/certifications.php`;
        const method = editingCertId ? 'PUT' : 'POST';

        fetch(url, { method, headers: csrfHeaders(), body: JSON.stringify(payload) })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not save certification.', true); return; }
                showToast(editingCertId ? 'Certification updated.' : 'Certification added.');
                certModalBackdrop.classList.add('hidden');
                loadCerts();
            })
            .catch(() => showToast('Network error — please try again.', true));
    });

    // --- Documents modal ---
    const docsModalBackdrop = document.getElementById('docs-modal-backdrop');
    document.getElementById('docs-modal-close').addEventListener('click', () => docsModalBackdrop.classList.add('hidden'));

    function openDocsModal(certId) {
        activeCertIdForDocs = certId;
        loadDocs();
        docsModalBackdrop.classList.remove('hidden');
    }

    function loadDocs() {
        fetch(`${API_BASE}/certification_documents.php?certification_id=${activeCertIdForDocs}`)
            .then(r => r.json())
            .then(data => { docs = data.documents || []; renderDocs(); })
            .catch(() => showToast('Could not load documents.', true));
    }

    function renderDocs() {
        const body = document.getElementById('docs-body');
        document.getElementById('docs-empty-state').classList.toggle('hidden', docs.length > 0);
        body.innerHTML = docs.map(d => `
            <tr>
                <td><a href="${API_BASE}/certification_document_download.php?id=${d.id}" target="_blank">${escapeHtml(d.original_filename)}</a></td>
                <td>${escapeHtml(d.doc_type)}</td>
                <td>${escapeHtml(d.uploaded_at)} by ${escapeHtml(d.uploaded_by_name)}</td>
                <td class="mgmt-actions"><button data-remove-doc="${d.id}" class="danger-link">Remove</button></td>
            </tr>
        `).join('');
        body.querySelectorAll('[data-remove-doc]').forEach(btn =>
            btn.addEventListener('click', () => confirmDeleteDoc(btn.dataset.removeDoc)));
    }

    document.getElementById('doc-upload-btn').addEventListener('click', function () {
        const fileInput = document.getElementById('doc-file');
        if (!fileInput.files.length) { showToast('Choose a file first.', true); return; }

        const formData = new FormData();
        formData.append('certification_id', activeCertIdForDocs);
        formData.append('doc_type', document.getElementById('doc-type').value);
        formData.append('file', fileInput.files[0]);

        fetch(`${API_BASE}/certification_documents.php`, { method: 'POST', headers: csrfHeaders(false), body: formData })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Upload failed.', true); return; }
                showToast('Document uploaded.');
                fileInput.value = '';
                loadDocs();
            })
            .catch(() => showToast('Network error — please try again.', true));
    });

    // --- Delete document confirmation (shared confirm modal) ---
    const confirmBackdrop = document.getElementById('confirm-backdrop');
    function confirmDeleteDoc(id) {
        pendingDeleteDocId = id;
        const doc = docs.find(d => d.id == id);
        document.getElementById('confirm-title').textContent = 'Remove this document?';
        document.getElementById('confirm-message').textContent =
            `"${doc ? doc.original_filename : ''}" will be permanently removed. This cannot be undone.`;
        confirmBackdrop.classList.remove('hidden');
    }
    document.getElementById('confirm-cancel').addEventListener('click', () => confirmBackdrop.classList.add('hidden'));
    document.getElementById('confirm-ok').addEventListener('click', function () {
        confirmBackdrop.classList.add('hidden');
        fetch(`${API_BASE}/certification_documents.php?id=${pendingDeleteDocId}`, { method: 'DELETE', headers: csrfHeaders() })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not remove document.', true); return; }
                showToast('Document removed.');
                loadDocs();
            })
            .catch(() => showToast('Network error — please try again.', true));
    });

    loadLookups().then(() => { populateCertDropdowns(); loadCerts(); });
});