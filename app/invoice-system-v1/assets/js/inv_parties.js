// invoice-system/assets/js/inv_parties.js — Billed Parties page.
'use strict';

(function () {
    const body = document.getElementById('parties-body');
    const empty = document.getElementById('parties-empty');
    const countEl = document.getElementById('parties-count');
    const fQ = document.getElementById('f-q');
    const fStatus = document.getElementById('f-status');
    const pgPrev = document.getElementById('pg-prev');
    const pgNext = document.getElementById('pg-next');

    const modalEl = document.getElementById('party-modal');
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('party-form');
    const title = document.getElementById('party-modal-title');
    const lookupBlock = document.getElementById('lookup-block');
    const lookupQ = document.getElementById('lookup-q');
    const lookupResults = document.getElementById('lookup-results');
    const sourceNote = document.getElementById('source-note');
    const dupWarning = document.getElementById('dup-warning');
    const btnSaveAnyway = document.getElementById('btn-save-anyway');
    const btnSave = document.getElementById('btn-save-party');
    const btnToggle = document.getElementById('btn-toggle-active');

    const FIELDS = ['display_name', 'legal_name', 'uen', 'billing_address', 'attention', 'phone',
        'accounts_email', 'cc_emails', 'payment_terms_days', 'default_entity_code', 'notes'];

    let page = 1;
    let currentParty = null;
    let searchTimer = null;
    let lookupTimer = null;

    // ------------------------------------------------------------------ list
    async function loadList() {
        const params = new URLSearchParams({ status: fStatus.value, page: String(page) });
        if (fQ.value.trim()) params.set('q', fQ.value.trim());
        try {
            const data = await invFetch(INV_API + '/parties.php?' + params.toString());
            renderList(data);
        } catch (e) {
            if (e.message !== 'session_expired') invToast(e.message, true);
        }
    }

    function renderList(data) {
        const rows = data.parties || [];
        body.innerHTML = rows.map(p => {
            const terms = p.payment_terms_days === null ? '<span class="text-muted">default</span>' : invEscape(p.payment_terms_days) + 'd';
            const addr = p.billing_address
                ? invEscape(p.billing_address).replace(/\n/g, '<br>')
                : '<span class="badge text-bg-warning">No address</span>';
            const source = p.source === 'cm_lookup'
                ? '<span class="badge text-bg-light border" title="Copied from client directory #' + invEscape(p.source_client_id) + '">Directory</span>'
                : '<span class="badge text-bg-light border">Manual</span>';
            const inactive = String(p.is_active) === '1' ? '' : ' <span class="badge text-bg-secondary">Inactive</span>';
            return '<tr>'
                + '<td><strong>' + invEscape(p.display_name) + '</strong>' + inactive
                + (p.uen ? '<div class="small text-muted">UEN ' + invEscape(p.uen) + '</div>' : '') + '</td>'
                + '<td class="small">' + addr + '</td>'
                + '<td class="small">' + invEscape(p.attention || '') + '</td>'
                + '<td class="small">' + invEscape(p.accounts_email || '') + '</td>'
                + '<td class="text-end small">' + terms + '</td>'
                + '<td>' + source + '</td>'
                + '<td class="text-end text-nowrap"><button class="btn btn-sm btn-outline-primary" data-edit="' + invEscape(p.id) + '"><i class="bi bi-pencil"></i> Edit</button></td>'
                + '</tr>';
        }).join('');
        empty.classList.toggle('d-none', rows.length > 0);
        const from = rows.length ? (data.page - 1) * data.page_size + 1 : 0;
        countEl.textContent = rows.length ? `Showing ${from}–${from + rows.length - 1} of ${data.total}` : '';
        pgPrev.disabled = data.page <= 1;
        pgNext.disabled = data.page * data.page_size >= data.total;
    }

    fQ.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { page = 1; loadList(); }, 300);
    });
    fStatus.addEventListener('change', () => { page = 1; loadList(); });
    pgPrev.addEventListener('click', () => { if (page > 1) { page--; loadList(); } });
    pgNext.addEventListener('click', () => { page++; loadList(); });

    body.addEventListener('click', async (ev) => {
        const btn = ev.target.closest('[data-edit]');
        if (!btn) return;
        try {
            const data = await invFetch(INV_API + '/parties.php?id=' + encodeURIComponent(btn.dataset.edit));
            openModal(data.party);
        } catch (e) {
            if (e.message !== 'session_expired') invToast(e.message, true);
        }
    });

    // ----------------------------------------------------------------- modal
    function resetModalState() {
        invClearFieldErrors(form);
        dupWarning.classList.add('d-none');
        dupWarning.innerHTML = '';
        btnSaveAnyway.classList.add('d-none');
        sourceNote.classList.add('d-none');
        if (lookupQ) { lookupQ.value = ''; lookupResults.innerHTML = ''; }
    }

    function openModal(party) {
        currentParty = party || null;
        form.reset();
        resetModalState();
        form.elements.id.value = party ? party.id : '';
        form.elements.row_hash.value = party ? party.row_hash : '';
        form.elements.source.value = party ? party.source : 'manual';
        form.elements.source_client_id.value = party && party.source_client_id ? party.source_client_id : '';
        FIELDS.forEach(f => { form.elements[f].value = party && party[f] != null ? party[f] : ''; });

        title.textContent = party ? 'Edit party' : 'New party';
        lookupBlock.classList.toggle('d-none', !!party);
        if (party && party.source === 'cm_lookup') {
            sourceNote.textContent = 'Originally copied from client directory record #' + party.source_client_id
                + '. Edits here do not change Client Management.';
            sourceNote.classList.remove('d-none');
        }
        if (party) {
            const active = String(party.is_active) === '1';
            btnToggle.textContent = active ? 'Deactivate' : 'Reactivate';
            btnToggle.className = 'btn btn-sm me-auto ' + (active ? 'btn-outline-danger' : 'btn-outline-success');
        } else {
            btnToggle.className = 'btn btn-sm me-auto d-none';
        }
        modal.show();
    }

    document.getElementById('btn-new-party').addEventListener('click', () => openModal(null));

    // ------------------------------------------------- client directory lookup
    if (lookupQ) {
        lookupQ.addEventListener('input', () => {
            clearTimeout(lookupTimer);
            const q = lookupQ.value.trim();
            if (q.length < 2) { lookupResults.innerHTML = ''; return; }
            lookupTimer = setTimeout(async () => {
                try {
                    const data = await invFetch(INV_API + '/client_lookup.php?q=' + encodeURIComponent(q));
                    renderLookup(data.results || []);
                } catch (e) {
                    if (e.message !== 'session_expired') invToast(e.message, true);
                }
            }, 300);
        });

        lookupResults.addEventListener('click', (ev) => {
            const item = ev.target.closest('[data-client]');
            if (!item) return;
            const c = JSON.parse(item.dataset.client);
            applySuggestion(c);
        });
    }

    function renderLookup(results) {
        if (!results.length) {
            lookupResults.innerHTML = '<div class="list-group-item text-muted">No match in the client directory. Enter the party manually.</div>';
            return;
        }
        lookupResults.innerHTML = results.map(c =>
            '<button type="button" class="list-group-item list-group-item-action py-1" data-client="' + invEscape(JSON.stringify(c)) + '">'
            + '<strong>' + invEscape(c.company_name) + '</strong>'
            + (c.entity ? ' <span class="badge text-bg-light border">' + invEscape(c.entity) + '</span>' : '')
            + '<div class="text-muted">' + (c.address ? invEscape(c.address) : '<em>no address on file</em>')
            + (c.email ? ' · ' + invEscape(c.email) : '') + '</div></button>'
        ).join('');
    }

    function applySuggestion(c) {
        form.elements.source.value = 'cm_lookup';
        form.elements.source_client_id.value = c.source_client_id;
        form.elements.display_name.value = c.company_name || '';
        form.elements.uen.value = c.uen || '';
        form.elements.billing_address.value = c.address || '';
        form.elements.attention.value = c.contact_person || '';
        form.elements.phone.value = c.phone || '';
        form.elements.accounts_email.value = c.email || '';
        if (c.entity) form.elements.default_entity_code.value = c.entity;
        lookupResults.innerHTML = '';
        lookupQ.value = '';
        sourceNote.textContent = 'Copied from client directory record #' + c.source_client_id
            + '. Check the billing address and accounts email before saving'
            + (c.address ? '.' : ': this client has no address on file.');
        sourceNote.classList.remove('d-none');
    }

    // ------------------------------------------------------------------ save
    function collect() {
        const out = {};
        FIELDS.forEach(f => { out[f] = form.elements[f].value; });
        return out;
    }

    async function save(confirmDuplicate) {
        invClearFieldErrors(form);
        const payload = collect();
        const isEdit = !!form.elements.id.value;
        if (!payload.display_name.trim()) {
            invShowFieldErrors(form, { display_name: 'Party name is required.' });
            return;
        }
        btnSave.disabled = true;
        try {
            if (isEdit) {
                payload.id = Number(form.elements.id.value);
                payload.row_hash = form.elements.row_hash.value;
                payload.action = 'update';
                await invFetch(INV_API + '/parties.php', { method: 'PUT', body: payload });
            } else {
                payload.source = form.elements.source.value;
                payload.source_client_id = form.elements.source_client_id.value || null;
                payload.confirm_duplicate = !!confirmDuplicate;
                await invFetch(INV_API + '/parties.php', { method: 'POST', body: payload });
            }
            modal.hide();
            invToast(isEdit ? 'Party updated.' : 'Party created.');
            loadList();
        } catch (e) {
            if (e.message === 'session_expired') return;
            if (e.status === 422 && e.data && e.data.fields) {
                invShowFieldErrors(form, e.data.fields);
                const unknown = Object.keys(e.data.fields).filter(k => !form.querySelector('[data-for="' + CSS.escape(k) + '"]'));
                if (unknown.length) invToast(unknown.map(k => e.data.fields[k]).join(' '), true);
            } else if (e.status === 409 && e.data && e.data.duplicates) {
                dupWarning.innerHTML = '<strong>Possible duplicate.</strong> Existing: <ul class="mb-0">'
                    + e.data.duplicates.map(d => '<li>' + invEscape(d.display_name)
                        + (String(d.is_active) === '1' ? '' : ' (inactive)') + ' — ' + invEscape(d.reason) + '</li>').join('')
                    + '</ul>Use the existing party, or click <em>Save anyway</em>.';
                dupWarning.classList.remove('d-none');
                btnSaveAnyway.classList.remove('d-none');
            } else {
                invToast(e.message, true);
            }
        } finally {
            btnSave.disabled = false;
        }
    }

    form.addEventListener('submit', (ev) => { ev.preventDefault(); save(false); });
    btnSaveAnyway.addEventListener('click', () => save(true));

    btnToggle.addEventListener('click', async () => {
        if (!currentParty) return;
        const active = String(currentParty.is_active) === '1';
        if (active && !confirm('Deactivate "' + currentParty.display_name + '"? It will no longer be offered for new invoices.')) return;
        try {
            await invFetch(INV_API + '/parties.php', {
                method: 'PUT',
                body: { id: Number(currentParty.id), action: active ? 'deactivate' : 'reactivate' },
            });
            modal.hide();
            invToast(active ? 'Party deactivated.' : 'Party reactivated.');
            loadList();
        } catch (e) {
            if (e.message !== 'session_expired') invToast(e.message, true);
        }
    });

    loadList();
})();
