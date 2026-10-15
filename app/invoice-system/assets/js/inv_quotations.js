// invoice-system/assets/js/inv_quotations.js — quotation list + overview metrics (Stage 3B).
'use strict';

const INV_Q_BADGE = {
    draft: ['Draft', 'text-bg-secondary'], sent: ['Sent', 'text-bg-primary'], expired: ['Expired', 'text-bg-warning'],
    accepted: ['Accepted', 'text-bg-success'], rejected: ['Rejected', 'text-bg-danger'],
    cancelled: ['Cancelled', 'text-bg-dark'], superseded: ['Superseded', 'text-bg-light border'],
};

function invQuoteBadge(status) {
    const b = INV_Q_BADGE[status] || [status, 'text-bg-light'];
    return '<span class="badge ' + b[1] + '">' + invEscape(b[0]) + '</span>';
}

function invQMoney(dec) {
    return Number(dec || 0).toLocaleString('en-SG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

(function () {
    const form = document.getElementById('filters');
    if (!form) return;
    const body = document.getElementById('q-body');
    const empty = document.getElementById('q-empty');
    const countEl = document.getElementById('q-count');
    const metrics = document.getElementById('metrics');
    const prev = document.getElementById('pg-prev');
    const next = document.getElementById('pg-next');
    let page = 1;
    let timer = null;

    async function load() {
        const params = new URLSearchParams();
        new FormData(form).forEach((v, k) => { if (String(v).trim() !== '') params.set(k, String(v).trim()); });
        params.set('page', String(page));
        try {
            render(await invFetch(INV_API + '/quotations.php?' + params.toString()));
        } catch (e) {
            if (e.message !== 'session_expired') invToast(e.message, true);
        }
    }

    function render(d) {
        const base = window.EHS_BASE_URL + '/invoice-system/';
        body.innerHTML = d.quotations.map(r => {
            const href = base + (r.status === 'draft' ? 'quotation_edit.php?id=' : 'quotation_view.php?id=') + encodeURIComponent(r.id);
            const num = r.quote_no ? '<span class="font-monospace">' + invEscape(r.quote_no) + '</span>'
                : '<span class="text-muted">Draft #' + invEscape(r.id) + (Number(r.revision_no) > 0 ? ' (revision ' + invEscape(r.revision_no) + ')' : '') + '</span>';
            const inv = Number(r.item_n) > 0 && Number(r.invoiced_n) > 0 ? invEscape(r.invoiced_n) + ' / ' + invEscape(r.item_n) + ' lines' : '<span class="text-muted">—</span>';
            return '<tr>'
                + '<td><a href="' + invEscape(href) + '">' + num + '</a></td>'
                + '<td class="text-nowrap">' + invEscape(invFormatDateTime(r.quote_date)) + '</td>'
                + '<td>' + invEscape(r.entity_code) + '</td>'
                + '<td>' + invEscape(r.party_name) + '</td>'
                + '<td class="small text-truncate" style="max-width:18rem">' + invEscape(r.subject || '') + '</td>'
                + '<td class="text-end">' + invQMoney(r.total_special) + '</td>'
                + '<td class="text-nowrap small">' + (r.status === 'sent' ? invEscape(invFormatDateTime(r.valid_until)) : '<span class="text-muted">—</span>') + '</td>'
                + '<td class="small">' + inv + '</td>'
                + '<td>' + invQuoteBadge(r.display_status) + '</td>'
                + '</tr>';
        }).join('');
        empty.classList.toggle('d-none', d.quotations.length > 0);
        const from = d.quotations.length ? (d.page - 1) * d.page_size + 1 : 0;
        countEl.textContent = d.quotations.length ? `Showing ${from}–${from + d.quotations.length - 1} of ${d.total}` : '';
        prev.disabled = d.page <= 1;
        next.disabled = d.page * d.page_size >= d.total;

        const m = d.metrics;
        const tile = (label, value, cls, hint) => '<div class="col-6 col-md-4 col-xl-2"><div class="card shadow-sm h-100"><div class="card-body py-2">'
            + '<div class="small text-muted">' + invEscape(label) + '</div><div class="fw-semibold ' + (cls || '') + '">' + value + '</div>'
            + (hint ? '<div class="small text-muted">' + invEscape(hint) + '</div>' : '') + '</div></div></div>';
        metrics.innerHTML =
            tile('Open quotations', invEscape(m.open_count) + ' · S$ ' + invQMoney(m.open_value), 'text-primary', 'sent, still valid')
            + tile('Expiring in 7 days', invEscape(m.expiring_7d), m.expiring_7d ? 'text-warning' : '')
            + tile('Acceptance rate (90 days)', m.acceptance_rate_90d === null ? '—' : invEscape(m.acceptance_rate_90d) + '%', '', 'accepted / decided')
            + tile('Accepted, not yet invoiced', 'S$ ' + invQMoney(m.accepted_not_invoiced), m.accepted_not_invoiced > 0 ? 'text-success' : '')
            + tile('Avg. days to accept', m.avg_days_to_accept === null ? '—' : invEscape(m.avg_days_to_accept), '', 'last 90 days');
    }

    form.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => { page = 1; load(); }, 300); });
    form.addEventListener('change', () => { page = 1; load(); });
    prev.addEventListener('click', () => { if (page > 1) { page--; load(); } });
    next.addEventListener('click', () => { page++; load(); });
    load();
})();
