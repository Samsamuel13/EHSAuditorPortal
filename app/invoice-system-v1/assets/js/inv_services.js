// invoice-system/assets/js/inv_services.js — services catalogue and categories.
'use strict';

(function () {
    const svcBody = document.getElementById('svc-body');
    const catBody = document.getElementById('cat-body');
    const modal = new bootstrap.Modal(document.getElementById('svc-modal'));
    const form = document.getElementById('svc-form');
    const toggle = document.getElementById('svc-toggle');
    const catForm = document.getElementById('cat-form');
    let data = null;
    let current = null;

    function money(dec) {
        return Number(dec || 0).toLocaleString('en-SG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    async function load() {
        try { data = await invFetch(INV_API + '/services.php'); render(); }
        catch (e) { if (e.message !== 'session_expired') invToast(e.message, true); }
    }

    function render() {
        svcBody.innerHTML = data.services.map(s => '<tr' + (String(s.is_active) === '1' ? '' : ' class="text-muted"') + '>'
            + '<td class="font-monospace small">' + invEscape(s.code) + '</td>'
            + '<td><strong>' + invEscape(s.title) + '</strong>' + (String(s.is_active) === '1' ? '' : ' <span class="badge text-bg-secondary">Inactive</span>')
            + (s.standard_wording ? '<div class="small text-muted text-truncate" style="max-width:420px">' + invEscape(s.standard_wording) + '</div>' : '') + '</td>'
            + '<td class="small">' + invEscape(s.category_name || '') + '</td>'
            + '<td class="small">' + invEscape(s.entity_code || 'Both') + '</td>'
            + '<td class="text-end">' + money(s.default_rate) + '</td>'
            + '<td class="small">' + (String(s.qty_from_entities) === '1' ? '= entities' : 'manual') + '</td>'
            + '<td class="small">' + invEscape(s.used_lines) + ' line(s)</td>'
            + '<td class="text-end"><button class="btn btn-sm btn-outline-primary" data-edit="' + invEscape(s.id) + '"><i class="bi bi-pencil"></i> Edit</button></td>'
            + '</tr>').join('') || '<tr><td colspan="8" class="text-muted">No services yet.</td></tr>';

        catBody.innerHTML = data.categories.map(c => '<tr data-id="' + invEscape(c.id) + '"' + (String(c.is_active) === '1' ? '' : ' class="text-muted"') + '>'
            + '<td><input class="form-control form-control-sm cat-name" maxlength="100" value="' + invEscape(c.name) + '" aria-label="Category name"></td>'
            + '<td style="width:6rem"><input type="number" class="form-control form-control-sm text-end cat-sort" min="0" max="100000" value="' + invEscape(c.sort_order) + '" aria-label="Sort order"></td>'
            + '<td class="small">' + invEscape(c.used_invoices) + '</td>'
            + '<td class="text-end text-nowrap"><button class="btn btn-sm btn-outline-primary cat-save">Save</button> '
            + '<button class="btn btn-sm ' + (String(c.is_active) === '1' ? 'btn-outline-danger cat-off">Deactivate' : 'btn-outline-success cat-on">Reactivate') + '</button></td>'
            + '</tr>').join('');
    }

    function openModal(s) {
        current = s;
        form.reset();
        invClearFieldErrors(form);
        document.getElementById('sv-cat').innerHTML = '<option value="">None</option>' + data.categories
            .filter(c => String(c.is_active) === '1' || (s && String(c.id) === String(s.category_id)))
            .map(c => '<option value="' + invEscape(c.id) + '">' + invEscape(c.name) + '</option>').join('');
        document.getElementById('sv-ent').innerHTML = '<option value="">Both entities</option>' + data.entities
            .map(e => '<option value="' + invEscape(e.code) + '">' + invEscape(e.name) + '</option>').join('');
        form.elements.id.value = s ? s.id : '';
        form.elements.row_hash.value = s ? s.row_hash : '';
        form.elements.code.value = s ? s.code : '';
        form.elements.title.value = s ? s.title : '';
        form.elements.standard_wording.value = s ? (s.standard_wording || '') : '';
        form.elements.default_rate.value = s ? s.default_rate : '0.00';
        form.elements.category_id.value = s && s.category_id ? s.category_id : '';
        form.elements.entity_code.value = s && s.entity_code ? s.entity_code : '';
        form.elements.sort_order.value = s ? s.sort_order : 0;
        form.elements.qty_from_entities.checked = !!(s && String(s.qty_from_entities) === '1');
        document.getElementById('svc-title').textContent = s ? 'Edit service ' + s.code : 'New service';
        if (s) {
            const on = String(s.is_active) === '1';
            toggle.textContent = on ? 'Deactivate' : 'Reactivate';
            toggle.className = 'btn btn-sm me-auto ' + (on ? 'btn-outline-danger' : 'btn-outline-success');
        } else {
            toggle.className = 'btn btn-sm me-auto d-none';
        }
        modal.show();
    }

    document.getElementById('btn-new-service').addEventListener('click', () => { if (data) openModal(null); });
    svcBody.addEventListener('click', e => {
        const b = e.target.closest('[data-edit]');
        if (b) openModal(data.services.find(s => String(s.id) === b.dataset.edit));
    });

    form.addEventListener('submit', async ev => {
        ev.preventDefault();
        invClearFieldErrors(form);
        const body = {
            kind: 'service', code: form.elements.code.value, title: form.elements.title.value,
            standard_wording: form.elements.standard_wording.value, default_rate: form.elements.default_rate.value,
            category_id: form.elements.category_id.value || null, entity_code: form.elements.entity_code.value || null,
            sort_order: form.elements.sort_order.value, qty_from_entities: form.elements.qty_from_entities.checked,
        };
        try {
            if (current) {
                Object.assign(body, { id: Number(current.id), row_hash: current.row_hash, action: 'update' });
                data = await invFetch(INV_API + '/services.php', { method: 'PUT', body });
            } else {
                data = await invFetch(INV_API + '/services.php', { method: 'POST', body });
            }
            modal.hide(); render(); invToast('Service saved.');
        } catch (e) {
            if (e.message === 'session_expired') return;
            if (e.data && e.data.fields) invShowFieldErrors(form, e.data.fields);
            invToast(e.message, true);
        }
    });

    toggle.addEventListener('click', async () => {
        if (!current) return;
        const on = String(current.is_active) === '1';
        try {
            data = await invFetch(INV_API + '/services.php', { method: 'PUT', body: { kind: 'service', id: Number(current.id), action: on ? 'deactivate' : 'reactivate' } });
            modal.hide(); render(); invToast(on ? 'Service deactivated.' : 'Service reactivated.');
        } catch (e) { if (e.message !== 'session_expired') invToast(e.message, true); }
    });

    catForm.addEventListener('submit', async ev => {
        ev.preventDefault();
        try {
            data = await invFetch(INV_API + '/services.php', { method: 'POST', body: { kind: 'category', name: catForm.elements.name.value } });
            catForm.reset(); render(); invToast('Category added.');
        } catch (e) { if (e.message !== 'session_expired') invToast(e.message, true); }
    });

    catBody.addEventListener('click', async e => {
        const tr = e.target.closest('tr[data-id]');
        if (!tr) return;
        const c = data.categories.find(x => String(x.id) === tr.dataset.id);
        let body = null;
        if (e.target.closest('.cat-save')) {
            body = { kind: 'category', id: Number(c.id), row_hash: c.row_hash, action: 'update',
                name: tr.querySelector('.cat-name').value, sort_order: tr.querySelector('.cat-sort').value };
        } else if (e.target.closest('.cat-off') || e.target.closest('.cat-on')) {
            body = { kind: 'category', id: Number(c.id), action: e.target.closest('.cat-off') ? 'deactivate' : 'reactivate' };
        }
        if (!body) return;
        try {
            data = await invFetch(INV_API + '/services.php', { method: 'PUT', body });
            render(); invToast('Category updated.');
        } catch (err) { if (err.message !== 'session_expired') invToast(err.message, true); }
    });

    load();
})();
