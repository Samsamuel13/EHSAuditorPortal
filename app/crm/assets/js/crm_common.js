// crm/assets/js/crm_common.js — shared across all CRM pages.
const CRM_API = window.EHS_BASE_URL + '/crm/api';

function crmEscapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : str;
    return div.innerHTML;
}

function crmToast(message, isError = false) {
    const toastEl = document.getElementById('crm-toast');
    if (!toastEl) { alert(message); return; }
    document.getElementById('crm-toast-body').textContent = message;
    toastEl.classList.toggle('bg-danger', isError);
    toastEl.classList.toggle('bg-dark', !isError);
    (bootstrap.Toast.getOrCreateInstance(toastEl)).show();
}

// fetch() wrapper: JSON body, CSRF header on state-changing verbs, 401 handling.
async function crmFetch(url, options = {}) {
    const opts = Object.assign({ headers: {} }, options);
    opts.headers = Object.assign({ 'Content-Type': 'application/json' }, opts.headers);
    if (options.method && options.method !== 'GET') {
        opts.headers['X-CSRF-Token'] = window.CRM_CSRF_TOKEN;
    }
    if (opts.body && typeof opts.body !== 'string') {
        opts.body = JSON.stringify(opts.body);
    }

    const res = await fetch(url, opts);
    if (res.status === 401) {
        crmToast('Your session has expired. Please log in again.', true);
        setTimeout(() => window.location.href = window.EHS_BASE_URL + '/login.php', 1500);
        throw new Error('session_expired');
    }
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        throw new Error(data.error || ('Request failed (' + res.status + ')'));
    }
    return data;
}

const CRM_STAGE_LABELS = {
    enquiry: 'Enquiry', lead: 'Lead', quotation: 'Quotation',
    negotiation: 'Negotiation', awarded: 'Awarded', lost: 'Lost', on_hold: 'On Hold',
};
const CRM_SOURCE_LABELS = {
    whatsapp: 'WhatsApp', referral: 'Referral', website: 'Website',
    cold_call: 'Cold Call', exhibition: 'Exhibition', other: 'Other',
};

function crmLoadOwnersInto(selectEl) {
    return crmFetch(CRM_API + '/users_lookup.php').then(data => {
        (data.users || []).forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = u.name;
            selectEl.appendChild(opt);
        });
    }).catch(() => {});
}
