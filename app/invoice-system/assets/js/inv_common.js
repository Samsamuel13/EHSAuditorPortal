// invoice-system/assets/js/inv_common.js — shared by every Invoice System page.
// Same pattern as crm/assets/js/crm_common.js (module-local copy, not a dependency).
'use strict';

const INV_API = window.EHS_BASE_URL + '/invoice-system/api';

/**
 * Escape text for safe insertion into HTML text AND quoted attribute values.
 * Use for EVERY server value rendered via innerHTML. (textContent->innerHTML
 * alone does not escape quotes, which would break out of attributes.)
 */
function invEscape(str) {
    return String(str == null ? '' : str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function invToast(message, isError = false) {
    const el = document.getElementById('inv-toast');
    if (!el) { alert(message); return; }
    document.getElementById('inv-toast-body').textContent = message;
    el.classList.toggle('bg-danger', isError);
    el.classList.toggle('bg-dark', !isError);
    bootstrap.Toast.getOrCreateInstance(el).show();
}

/**
 * fetch() wrapper: JSON body, CSRF header on state-changing verbs, 401 handling.
 * Rejects with an Error carrying .status and .data (the parsed JSON body) so
 * callers can show field errors (422) or duplicate warnings (409).
 */
async function invFetch(url, options = {}) {
    const opts = Object.assign({ headers: {}, credentials: 'same-origin' }, options);
    opts.headers = Object.assign({ 'Content-Type': 'application/json', 'Accept': 'application/json' }, opts.headers);
    if (opts.method && opts.method !== 'GET') {
        opts.headers['X-CSRF-Token'] = window.INV_CSRF_TOKEN;
    }
    if (opts.body && typeof opts.body !== 'string') {
        opts.body = JSON.stringify(opts.body);
    }

    const res = await fetch(url, opts);
    if (res.status === 401) {
        invToast('Your session has expired. Please log in again.', true);
        setTimeout(() => { window.location.href = window.EHS_BASE_URL + '/login.php'; }, 1500);
        throw new Error('session_expired');
    }
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const err = new Error(data.error || (res.status === 403 ? 'Not allowed (session or security token expired; reload the page).' : 'Request failed (' + res.status + ')'));
        err.status = res.status;
        err.data = data;
        throw err;
    }
    return data;
}

/** Clear Bootstrap validation state inside a form. */
function invClearFieldErrors(form) {
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback[data-for]').forEach(el => { el.textContent = ''; });
}

/** Show {field: message} errors next to inputs named field (Bootstrap .is-invalid). */
function invShowFieldErrors(form, fields) {
    Object.entries(fields || {}).forEach(([name, msg]) => {
        const input = form.querySelector('[name="' + CSS.escape(name) + '"]');
        if (input) input.classList.add('is-invalid');
        const fb = form.querySelector('.invalid-feedback[data-for="' + CSS.escape(name) + '"]');
        if (fb) fb.textContent = msg;
    });
}

/** True when a value still holds a placeholder (same rule as the server: contains "TBC"). */
function invIsTbc(v) {
    return v != null && String(v).toUpperCase().indexOf('TBC') !== -1;
}

/** Format 'YYYY-MM-DD hh:mm:ss' as dd/mm/yyyy hh:mm (portal print format). */
function invFormatDateTime(s) {
    if (!s) return '';
    const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
    if (!m) return String(s);
    return m[3] + '/' + m[2] + '/' + m[1] + (m[4] ? ' ' + m[4] + ':' + m[5] : '');
}
