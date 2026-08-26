// crm/assets/js/crm_list.js
document.addEventListener('DOMContentLoaded', function () {
    let leadsCache = [];
    let sortKey = 'updated_at';
    let sortDir = -1; // desc by default

    crmLoadOwnersInto(document.getElementById('f-owner'));

    function currentFilters() {
        return {
            q: document.getElementById('f-q').value.trim(),
            stage: document.getElementById('f-stage').value,
            source: document.getElementById('f-source').value,
            owner_id: document.getElementById('f-owner').value,
            date_from: document.getElementById('f-date-from').value,
            date_to: document.getElementById('f-date-to').value,
        };
    }

    function load() {
        const params = new URLSearchParams(currentFilters());
        crmFetch(CRM_API + '/leads.php?' + params.toString()).then(data => {
            leadsCache = data.leads || [];
            render();
        }).catch(err => crmToast(err.message, true));
    }

    function render() {
        const sorted = [...leadsCache].sort((a, b) => {
            const av = a[sortKey] || '';
            const bv = b[sortKey] || '';
            return av > bv ? sortDir : av < bv ? -sortDir : 0;
        });
        const body = document.getElementById('leads-body');
        document.getElementById('leads-empty').classList.toggle('d-none', sorted.length > 0);
        body.innerHTML = sorted.map(l => `
            <tr style="cursor:pointer;" onclick="window.location.href='${window.EHS_BASE_URL}/crm/lead_detail.php?id=${l.id}'">
                <td>${crmEscapeHtml(l.company_name)}</td>
                <td>${crmEscapeHtml(l.contact_person || '—')}</td>
                <td><span class="badge bg-secondary">${CRM_STAGE_LABELS[l.stage] || l.stage}</span></td>
                <td>${CRM_SOURCE_LABELS[l.source] || l.source}</td>
                <td>${crmEscapeHtml(l.owner_display_name || '—')}</td>
                <td>${crmEscapeHtml((l.created_at || '').substring(0, 10))}</td>
                <td>${crmEscapeHtml((l.updated_at || '').substring(0, 10))}</td>
            </tr>
        `).join('');
    }

    document.querySelectorAll('th[data-sort]').forEach(th => {
        th.addEventListener('click', () => {
            const key = th.dataset.sort;
            sortDir = (sortKey === key) ? -sortDir : -1;
            sortKey = key;
            render();
        });
    });

    function debounce(fn, ms) {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
    }
    ['f-q', 'f-stage', 'f-source', 'f-owner', 'f-date-from', 'f-date-to'].forEach(id => {
        const el = document.getElementById(id);
        el.addEventListener(el.tagName === 'SELECT' || el.type === 'date' ? 'change' : 'input', debounce(load, 300));
    });
    document.getElementById('f-clear').addEventListener('click', () => {
        ['f-q', 'f-stage', 'f-source', 'f-owner', 'f-date-from', 'f-date-to'].forEach(id => document.getElementById(id).value = '');
        load();
    });

    load();
});
