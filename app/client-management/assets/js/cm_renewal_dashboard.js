document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const isSuperAdmin = document.querySelector('meta[name="cm-is-super-admin"]').content === '1';
    const API_BASE = window.EHS_BASE_URL + '/client-management/api';

    let activeBucket = ''; // '' | 'near' | 'far' | 'overdue'
    let thresholds = [30, 60, 90];

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
            scheme_category: document.getElementById('filter-scheme-category').value,
            industry: document.getElementById('filter-industry').value.trim(),
            responsible_person_id: document.getElementById('filter-responsible').value,
        };
    }

    function load() {
        const params = new URLSearchParams(currentFilters());
        if (activeBucket) params.set('bucket', activeBucket);

        fetch(API_BASE + '/renewal_dashboard.php?' + params.toString())
            .then(r => r.json())
            .then(data => {
                thresholds = data.thresholds || thresholds;
                render(data);
            })
            .catch(() => showToast('Could not load the renewal dashboard.', true));
    }

    function render(data) {
        const [t1, t2, t3] = thresholds;
        document.getElementById('widget-near-title').textContent = `Expiring in ${t1} days`;
        document.getElementById('widget-far-title').textContent = `Expiring in ${t2}-${t3} days`;
        document.getElementById('count-near').textContent = data.counts.near;
        document.getElementById('count-far').textContent = data.counts.far;
        document.getElementById('count-overdue').textContent = data.counts.overdue;

        ['widget-near', 'widget-far', 'widget-overdue'].forEach(id => document.getElementById(id).style.outline = '');
        const activeMap = { near: 'widget-near', far: 'widget-far', overdue: 'widget-overdue' };
        if (activeBucket && activeMap[activeBucket]) {
            document.getElementById(activeMap[activeBucket]).style.outline = '2px solid var(--brand)';
        }

        const titles = { '': 'All certifications with an expiry date', near: `Expiring within ${t1} days`, far: `Expiring in ${t2}-${t3} days`, overdue: 'Overdue / Expired' };
        document.getElementById('list-title').textContent = titles[activeBucket] ?? titles[''];

        const body = document.getElementById('results-body');
        const certs = data.certifications || [];
        document.getElementById('results-empty-state').classList.toggle('hidden', certs.length > 0);

        body.innerHTML = certs.map(c => `
            <tr>
                <td><a href="${window.EHS_BASE_URL}/client-management/client_detail.php?id=${c.client_id}">${escapeHtml(c.company_name)}</a></td>
                <td>${escapeHtml(c.scheme_name)} (${escapeHtml(c.scheme_category)})</td>
                <td>${escapeHtml(c.certificate_number || '—')}</td>
                <td>${escapeHtml(c.expiry_date || '—')} <span class="badge ${c.expiry_badge.class}">${escapeHtml(c.expiry_badge.label)}</span></td>
                <td>${escapeHtml(c.status)}</td>
                <td>${escapeHtml(c.responsible_person || '—')}</td>
            </tr>
        `).join('');
    }

    document.getElementById('widget-near').addEventListener('click', () => { activeBucket = activeBucket === 'near' ? '' : 'near'; load(); });
    document.getElementById('widget-far').addEventListener('click', () => { activeBucket = activeBucket === 'far' ? '' : 'far'; load(); });
    document.getElementById('widget-overdue').addEventListener('click', () => { activeBucket = activeBucket === 'overdue' ? '' : 'overdue'; load(); });

    function debounce(fn, ms) {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
    }
    ['filter-scheme-category', 'filter-industry', 'filter-responsible'].forEach(id => {
        const el = document.getElementById(id);
        el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', debounce(load, 300));
    });
    document.getElementById('filter-clear').addEventListener('click', () => {
        document.getElementById('filter-scheme-category').value = '';
        document.getElementById('filter-industry').value = '';
        document.getElementById('filter-responsible').value = '';
        activeBucket = '';
        load();
    });

    if (isSuperAdmin) {
        document.getElementById('save-thresholds').addEventListener('click', function () {
            const t1 = parseInt(document.getElementById('t1').value, 10);
            const t2 = parseInt(document.getElementById('t2').value, 10);
            const t3 = parseInt(document.getElementById('t3').value, 10);
            if (!(t1 > 0 && t2 > t1 && t3 > t2)) {
                showToast('Thresholds must be positive and strictly increasing (e.g. 30, 60, 90).', true);
                return;
            }
            fetch(API_BASE + '/renewal_dashboard.php', { method: 'PUT', headers: csrfHeaders(), body: JSON.stringify({ thresholds: [t1, t2, t3] }) })
                .then(r => r.json().then(body => ({ ok: r.ok, body })))
                .then(({ ok, body }) => {
                    if (!ok) { showToast(body.error || 'Could not save thresholds.', true); return; }
                    showToast('Thresholds updated.');
                    thresholds = body.thresholds;
                    load();
                })
                .catch(() => showToast('Network error — please try again.', true));
        });
    }

    function initThresholdInputs() {
        if (!isSuperAdmin) return;
        document.getElementById('t1').value = thresholds[0];
        document.getElementById('t2').value = thresholds[1];
        document.getElementById('t3').value = thresholds[2];
    }

    loadResponsibleFilter();
    fetch(API_BASE + '/renewal_dashboard.php')
        .then(r => r.json())
        .then(data => { thresholds = data.thresholds || thresholds; initThresholdInputs(); render(data); })
        .catch(() => showToast('Could not load the renewal dashboard.', true));
});
