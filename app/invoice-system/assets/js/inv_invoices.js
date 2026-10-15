// invoice-system/assets/js/inv_invoices.js — invoice list.
'use strict';

const INV_STATUS_BADGE = {
    draft: ['Draft', 'text-bg-secondary'], issued: ['Issued', 'text-bg-primary'],
    partially_paid: ['Partially paid', 'text-bg-info'], paid: ['Paid', 'text-bg-success'],
    credited: ['Credited', 'text-bg-light border'], void: ['Void', 'text-bg-dark'],
};

function invStatusBadge(status, overdue) {
    const b = INV_STATUS_BADGE[status] || [status, 'text-bg-light'];
    return '<span class="badge ' + b[1] + '">' + invEscape(b[0]) + '</span>'
        + (overdue ? ' <span class="badge text-bg-danger">Overdue</span>' : '');
}

function invMoney(dec) {
    const n = Number(dec || 0);
    return n.toLocaleString('en-SG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

(function () {
    const form = document.getElementById('filters');
    if (!form) return;
    const body = document.getElementById('inv-body');
    const empty = document.getElementById('inv-empty');
    const countEl = document.getElementById('inv-count');
    const summary = document.getElementById('summary');
    const prev = document.getElementById('pg-prev');
    const next = document.getElementById('pg-next');
    let page = 1;
    let timer = null;

    async function load() {
        const params = new URLSearchParams();
        new FormData(form).forEach((v, k) => { if (String(v).trim() !== '') params.set(k, String(v).trim()); });
        params.set('page', String(page));
        try {
            const d = await invFetch(INV_API + '/invoices.php?' + params.toString());
            render(d);
        } catch (e) {
            if (e.message !== 'session_expired') invToast(e.message, true);
        }
    }

    function render(d) {
        const base = window.EHS_BASE_URL + '/invoice-system/';
        body.innerHTML = d.invoices.map(r => {
            const href = base + (r.status === 'draft' ? 'invoice_edit.php?id=' : 'invoice_view.php?id=') + encodeURIComponent(r.id);
            const num = r.invoice_no ? '<span class="font-monospace">' + invEscape(r.invoice_no) + '</span>' : '<span class="text-muted">Draft #' + invEscape(r.id) + '</span>';
            return '<tr>'
                + '<td><a href="' + invEscape(href) + '">' + num + '</a></td>'
                + '<td class="text-nowrap">' + invEscape(invFormatDateTime(r.invoice_date)) + '</td>'
                + '<td>' + invEscape(r.entity_code) + '</td>'
                + '<td>' + invEscape(r.party_name) + (r.customer_ref ? '<div class="small text-muted">Ref ' + invEscape(r.customer_ref) + '</div>' : '') + '</td>'
                + '<td class="small">' + invEscape(r.category_name || '') + '</td>'
                + '<td class="text-end">' + invMoney(r.total) + '</td>'
                + '<td class="text-end">' + (r.status === 'draft' || r.status === 'void' ? '<span class="text-muted">—</span>' : invMoney(r.balance)) + '</td>'
                + '<td class="text-nowrap small">' + invEscape(invFormatDateTime(r.due_date)) + '</td>'
                + '<td>' + invStatusBadge(r.status, r.overdue) + '</td>'
                + '</tr>';
        }).join('');
        empty.classList.toggle('d-none', d.invoices.length > 0);
        const from = d.invoices.length ? (d.page - 1) * d.page_size + 1 : 0;
        countEl.textContent = d.invoices.length ? `Showing ${from}–${from + d.invoices.length - 1} of ${d.total}` : '';
        prev.disabled = d.page <= 1;
        next.disabled = d.page * d.page_size >= d.total;

        const s = d.summary;
        const st = s.by_status;
        const tile = (label, value, cls) => '<div class="col-6 col-md-3"><div class="card shadow-sm"><div class="card-body py-2">'
            + '<div class="small text-muted">' + invEscape(label) + '</div><div class="fw-semibold ' + (cls || '') + '">' + value + '</div></div></div></div>';
        summary.innerHTML =
            tile('Drafts', (st.draft ? st.draft.count : 0) + ' · S$ ' + invMoney(st.draft ? st.draft.total : 0), 'text-secondary')
            + tile('Outstanding (issued + partially paid)', 'S$ ' + invMoney(s.outstanding), 'text-primary')
            + tile('Overdue', s.overdue_count + ' · S$ ' + invMoney(s.overdue_amount), s.overdue_count ? 'text-danger' : '')
            + tile('Paid / Credited / Void', (st.paid ? st.paid.count : 0) + ' / ' + (st.credited ? st.credited.count : 0) + ' / ' + (st.void ? st.void.count : 0), '');
    }

    form.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => { page = 1; load(); }, 300); });
    form.addEventListener('change', () => { page = 1; load(); });
    prev.addEventListener('click', () => { if (page > 1) { page--; load(); } });
    next.addEventListener('click', () => { page++; load(); });
    load();
})();
