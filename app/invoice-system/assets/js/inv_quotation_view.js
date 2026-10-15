// invoice-system/assets/js/inv_quotation_view.js — actions on a finalised quotation (Stage 3B).
'use strict';

(function () {
    const boot = JSON.parse(document.getElementById('inv-boot').textContent);
    const base = window.EHS_BASE_URL + '/invoice-system/';
    const go = (path) => { window.location.href = base + path; };
    const fail = (e) => { if (e.message !== 'session_expired') invToast(e.message, true); };

    // ---------------------------------------------------------------- revise / duplicate
    const rev = document.getElementById('btn-revise');
    if (rev) rev.addEventListener('click', async () => {
        if (!confirm('Create a revision draft? ' + boot.quote_no + ' stays valid until the revision is finalised; it is then marked superseded.')) return;
        try {
            const r = await invFetch(INV_API + '/quotation_action.php', { method: 'POST', body: { id: boot.id, row_hash: boot.row_hash, action: 'revise' } });
            go('quotation_edit.php?id=' + encodeURIComponent(r.id));
        } catch (e) {
            if (e.data && e.data.id) { go('quotation_edit.php?id=' + encodeURIComponent(e.data.id)); return; }
            fail(e);
        }
    });
    document.getElementById('btn-duplicate').addEventListener('click', async () => {
        try {
            const d = await invFetch(INV_API + '/quotations.php', { method: 'POST', body: { action: 'duplicate', id: boot.id } });
            go('quotation_edit.php?id=' + encodeURIComponent(d.quotation.id));
        } catch (e) { fail(e); }
    });

    // ---------------------------------------------------------------- accept (multipart)
    const af = document.getElementById('accept-form');
    if (af) {
        const on = document.getElementById('a-on');
        on.value = boot.today; on.max = boot.today; on.min = boot.quote_date;
        af.addEventListener('submit', async () => {
            invClearFieldErrors(af);
            const file = document.getElementById('a-file').files[0];
            if (file && file.size > boot.max_mb * 1048576) {
                invShowFieldErrors(af, { signed_copy: 'The file is larger than ' + boot.max_mb + ' MB.' });
                return;
            }
            const fd = new FormData(af);
            fd.append('id', String(boot.id));
            fd.append('row_hash', boot.row_hash);
            if (!file) fd.delete('signed_copy');
            const btn = document.getElementById('btn-accept');
            btn.disabled = true;
            try {
                // invFetch sends JSON; multipart is posted directly with the same CSRF header.
                const res = await fetch(INV_API + '/quotation_accept.php', {
                    method: 'POST', body: fd, credentials: 'same-origin',
                    headers: { 'X-CSRF-Token': window.INV_CSRF_TOKEN, 'Accept': 'application/json' },
                });
                if (res.status === 401) { go('../login.php'); return; }
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    if (data.fields) invShowFieldErrors(af, data.fields);
                    invToast(data.error || (res.status === 403 ? 'Not allowed (session or security token expired; reload the page).' : 'Request failed (' + res.status + ')'), true);
                    return;
                }
                window.location.reload();
            } catch (e) { fail(e); } finally { btn.disabled = false; }
        });
    }

    // ---------------------------------------------------------------- reject / cancel
    const cm = document.getElementById('close-modal');
    const closeModal = new bootstrap.Modal(cm);
    let closeAction = null;
    document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => {
        closeAction = b.dataset.close;
        document.getElementById('close-title').textContent = (closeAction === 'reject' ? 'Mark rejected: ' : 'Cancel: ') + boot.quote_no;
        document.getElementById('close-help').textContent = closeAction === 'reject'
            ? 'The client declined this quotation. A reason is optional.'
            : 'Cancel this quotation (for example issued by mistake or withdrawn). A reason of at least 5 characters is required. Not possible once any line has been invoiced.';
        document.getElementById('c-reason').value = '';
        invClearFieldErrors(document.getElementById('close-form'));
        closeModal.show();
    }));
    document.getElementById('close-form').addEventListener('submit', async () => {
        const reason = document.getElementById('c-reason').value;
        try {
            await invFetch(INV_API + '/quotation_action.php', { method: 'POST', body: { id: boot.id, row_hash: boot.row_hash, action: closeAction, reason } });
            window.location.reload();
        } catch (e) {
            if (e.data && e.data.fields) invShowFieldErrors(document.getElementById('close-form'), e.data.fields);
            fail(e);
        }
    });

    // ---------------------------------------------------------------- convert
    const cf = document.getElementById('convert-form');
    if (cf) cf.addEventListener('submit', async () => {
        const ids = Array.from(cf.querySelectorAll('.cv-line:checked')).map(c => Number(c.value));
        if (!ids.length) { invToast('Tick at least one line.', true); return; }
        const cat = document.getElementById('cv-cat').value;
        const btn = document.getElementById('btn-convert');
        btn.disabled = true;
        try {
            const r = await invFetch(INV_API + '/quotation_convert.php', { method: 'POST',
                body: { id: boot.id, line_ids: ids, category_id: cat === '' ? null : Number(cat), confirm: true } });
            go('invoice_edit.php?id=' + encodeURIComponent(r.invoice_id));
        } catch (e) { fail(e); } finally { btn.disabled = false; }
    });
})();
