document.addEventListener('DOMContentLoaded', function () {
    const API_BASE = window.EHS_BASE_URL + '/client-management/api';

    let activeMonth = 'this'; // 'last' | 'this' | 'next'

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

    function loadResponsibleFilter() {
        fetch(API_BASE + '/users_lookup.php')
            .then(r => r.json())
            .then(data => {
                const sel = document.getElementById('filter-responsible');
                (data.users || []).forEach(u => {
                    const opt = document.createElement('option');
                    opt.value = u.id;
                    opt.textContent = u.name;
                    sel.appendChild(opt);
                });
            })
            .catch(() => {});
    }

    function currentFilters() {
        return {
            q: document.getElementById('filter-client-name').value.trim(),
            stage: document.getElementById('filter-stage').value,
            scheme_category: document.getElementById('filter-scheme-category').value,
            industry: document.getElementById('filter-industry').value.trim(),
            responsible_person_id: document.getElementById('filter-responsible').value,
        };
    }

    function load() {
        const params = new URLSearchParams(currentFilters());
        params.set('month', activeMonth);

        fetch(API_BASE + '/audit_extract.php?' + params.toString())
            .then(r => r.json())
            .then(render)
            .catch(() => showToast('Could not load the audit extract.', true));
    }

    function render(data) {
        document.getElementById('tab-last-title').textContent = 'Last Month';
        document.getElementById('tab-this-title').textContent = 'This Month';
        document.getElementById('tab-next-title').textContent = 'Next Month';
        document.getElementById('count-last').textContent = data.counts.last;
        document.getElementById('count-this').textContent = data.counts.this;
        document.getElementById('count-next').textContent = data.counts.next;

        ['tab-last', 'tab-this', 'tab-next'].forEach(id => document.getElementById(id).style.outline = '');
        document.getElementById('tab-' + activeMonth).style.outline = '2px solid var(--brand)';

        document.getElementById('list-title').textContent = data.range.label;

        const body = document.getElementById('results-body');
        const certs = data.certifications || [];
        document.getElementById('results-empty-state').classList.toggle('hidden', certs.length > 0);

        body.innerHTML = certs.map(c => `
            <tr>
                <td><a href="${window.EHS_BASE_URL}/client-management/client_detail.php?id=${c.client_id}">${escapeHtml(c.company_name)}</a></td>
                <td>${escapeHtml(c.scheme_name)} (${escapeHtml(c.scheme_category)})</td>
                <td>${escapeHtml(c.certificate_number || '—')}</td>
                <td>${escapeHtml(c.milestone_label)}</td>
                <td>${escapeHtml(c.milestone_date)}</td>
                <td>${escapeHtml(c.consultant || '—')}</td>
                <td>${escapeHtml(c.responsible_person || '—')}</td>
            </tr>
        `).join('');
    }

    document.getElementById('tab-last').addEventListener('click', () => { activeMonth = 'last'; load(); });
    document.getElementById('tab-this').addEventListener('click', () => { activeMonth = 'this'; load(); });
    document.getElementById('tab-next').addEventListener('click', () => { activeMonth = 'next'; load(); });

    function debounce(fn, ms) {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
    }
    ['filter-client-name', 'filter-stage', 'filter-scheme-category', 'filter-industry', 'filter-responsible'].forEach(id => {
        const el = document.getElementById(id);
        el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', debounce(load, 300));
    });
    document.getElementById('filter-clear').addEventListener('click', () => {
        document.getElementById('filter-client-name').value = '';
        document.getElementById('filter-stage').value = '';
        document.getElementById('filter-scheme-category').value = '';
        document.getElementById('filter-industry').value = '';
        document.getElementById('filter-responsible').value = '';
        load();
    });

    document.getElementById('btn-export').addEventListener('click', () => {
        const params = new URLSearchParams(currentFilters());
        params.set('month', activeMonth);
        window.location.href = API_BASE + '/export_audit_extract.php?' + params.toString();
    });

    loadResponsibleFilter();
    load();
});