// invoice-system/assets/js/inv_settings.js — Settings page (issuing entities, readiness, number series).
'use strict';

(function () {
    const tabs = document.getElementById('entity-tabs');
    const panes = document.getElementById('entity-panes');
    const lookupStatus = document.getElementById('lookup-status');

    const seriesModal = new bootstrap.Modal(document.getElementById('series-modal'));
    const seriesForm = document.getElementById('series-form');

    // Field layout. type: text | textarea | email. col: Bootstrap column class.
    const GROUPS = [
        { title: 'Letterhead', fields: [
            ['display_name', 'text', 'col-md-4', 100],
            ['legal_name', 'text', 'col-md-8', 200],
            ['co_reg_no', 'text', 'col-md-4', 50],
            ['address', 'text', 'col-md-8', 255],
            ['email', 'email', 'col-md-6', 150],
            ['phone', 'text', 'col-md-6', 50],
            ['accreditation_line', 'text', 'col-12', 255, 'Leave blank to print no accreditation line.'],
        ] },
        { title: 'Payment details (printed in the invoice "Note" block)', fields: [
            ['cheque_payee', 'text', 'col-md-6', 200],
            ['paynow_uen', 'text', 'col-md-6', 50],
            ['bank_name', 'text', 'col-md-4', 100],
            ['bank_code', 'text', 'col-md-2', 20],
            ['bank_branch', 'text', 'col-md-6', 100],
            ['bank_account_label', 'text', 'col-md-4', 100],
            ['bank_account_no', 'text', 'col-md-4', 50],
            ['bank_swift', 'text', 'col-md-4', 20],
            ['payment_note', 'text', 'col-12', 255],
        ] },
        { title: 'Terms and footer', fields: [
            ['tnc_text', 'textarea', 'col-12', 4000, 'One bullet per line.'],
            ['footer_text', 'text', 'col-12', 255],
        ] },
        { title: 'Overdue reminders (Stage 5)', fields: [
            ['reminder_from_email', 'email', 'col-md-6', 150],
            ['reminder_from_name', 'text', 'col-md-6', 150],
        ] },
    ];

    let state = null;

    const TYPE_LABELS = { invoice: 'Invoice', credit_note: 'Credit note', quotation: 'Quotation' };
    function seriesPrefix(s, t) {
        return t === 'invoice' ? s.invoice_prefix : (t === 'credit_note' ? s.cn_prefix : s.quote_prefix);
    }

    /** Quotation settings (migration 004). The stamp is chosen by file name only: it is never shown or served by URL. */
    function quoteFieldset(x) {
        const s = x.settings;
        const id = (n) => 'f-' + invEscape(s.entity_code) + '-' + n;
        const pv = s.quote_prefix || '';
        const tbc = invIsTbc(pv);
        const unconf = !tbc && String(s.quote_prefix_confirmed) !== '1';
        const stamps = state.stamps || [];
        const stampOpts = '<option value="">None: the stamp area prints blank</option>'
            + stamps.map(st => '<option value="' + invEscape(st.file) + '"' + (st.file === s.stamp_filename ? ' selected' : '') + (st.valid ? '' : ' disabled') + '>'
                + invEscape(st.file) + (st.valid ? ' (' + invEscape(st.width) + '×' + invEscape(st.height) + ' px)' : ' (invalid image)') + '</option>').join('')
            + (s.stamp_filename && !stamps.some(st => st.file === s.stamp_filename) ? '<option value="' + invEscape(s.stamp_filename) + '" selected>' + invEscape(s.stamp_filename) + ' (missing)</option>' : '');
        return '<fieldset class="border rounded p-2 mb-3"><legend class="float-none w-auto px-2 fs-6 mb-0">Quotations</legend><div class="row g-2">'
            + '<div class="col-6 col-md-3"><label class="form-label small mb-0" for="' + id('quote_prefix') + '">' + invEscape(label('quote_prefix'))
            + (tbc ? ' <span class="badge text-bg-warning">TBC</span>' : '') + (unconf ? ' <span class="badge text-bg-warning">UNCONFIRMED</span>' : '') + '</label>'
            + '<input class="form-control form-control-sm' + (tbc || unconf ? ' border-warning bg-warning-subtle' : '') + '" id="' + id('quote_prefix') + '" name="quote_prefix" maxlength="20" value="' + invEscape(pv) + '"'
            + (x.quote_format_frozen ? ' disabled' : '') + '>'
            + '<div class="invalid-feedback" data-for="quote_prefix"></div><div class="form-text">'
            + (x.quote_format_frozen ? '<i class="bi bi-lock"></i> Frozen: quotations have been sent.' : 'Confirmed together with the quotation number series below.') + '</div></div>'
            + '<div class="col-6 col-md-2"><label class="form-label small mb-0" for="' + id('quote_valid_days') + '">Validity (days)</label>'
            + '<input type="number" min="1" max="365" step="1" class="form-control form-control-sm" id="' + id('quote_valid_days') + '" name="quote_valid_days" value="' + invEscape(s.quote_valid_days) + '">'
            + '<div class="invalid-feedback" data-for="quote_valid_days"></div></div>'
            + '<div class="col-12 col-md-4"><label class="form-label small mb-0" for="' + id('stamp_filename') + '">Company stamp (sent quotations only)</label>'
            + '<select class="form-select form-select-sm" id="' + id('stamp_filename') + '" name="stamp_filename">' + stampOpts + '</select>'
            + '<div class="invalid-feedback" data-for="stamp_filename"></div>'
            + '<div class="form-text">Upload stamp_*.png / .jpg to invoice-system/private/ (deny-all) by FTP or cPanel. Printed only on finalised quotations, never on drafts.</div></div>'
            + fieldHtml(s, ['quote_ref_prefix', 'text', 'col-12', 255, 'Fixed text after "Ref:"; the bold subject is typed on each quotation.'])
            + fieldHtml(s, ['quote_intro', 'textarea', 'col-md-6', 2000])
            + fieldHtml(s, ['quote_terms', 'textarea', 'col-md-6', 2000, '{valid_days} is replaced by the validity in days.'])
            + fieldHtml(s, ['quote_payment_terms', 'textarea', 'col-md-6', 2000, 'One bullet per line. 2nd / 3rd print as superscript ordinals.'])
            + fieldHtml(s, ['quote_closing', 'textarea', 'col-md-6', 2000, 'Wrap text in **double asterisks** for bold.'])
            + '</div><div class="form-text">Defaults for new quotations; each quotation can still edit its own copy. '
            + 'No signatory is printed: the bottom box shows the entity name and the Footer text (Terms and footer) — computer generated, no signature required.</div></fieldset>';
    }

    async function load(activeCode) {
        try {
            state = await invFetch(INV_API + '/settings.php');
            render(activeCode || (location.hash || '').replace('#entity-', '') || null);
        } catch (e) {
            if (e.message !== 'session_expired') {
                panes.innerHTML = '<div class="alert alert-danger small">' + invEscape(e.message) + '</div>';
            }
        }
    }

    function label(f) { return (state.labels && state.labels[f]) || f; }

    function render(activeCode) {
        const lk = state.client_lookup || {};
        lookupStatus.innerHTML = lk.available
            ? '<i class="bi bi-check-circle text-success"></i> Client directory lookup available'
              + (lk.has_entity ? ' (entity column detected)' : ' <span class="text-warning">(no entity column: entity not suggested)</span>')
            : '<i class="bi bi-exclamation-triangle text-warning"></i> Client directory lookup unavailable';

        const codes = state.entities.map(x => x.settings.entity_code);
        const active = codes.includes(activeCode) ? activeCode : codes[0];

        tabs.innerHTML = state.entities.map(x => {
            const s = x.settings;
            const ready = x.readiness.invoice.length === 0;
            const id = 'tab-' + s.entity_code;
            return '<li class="nav-item" role="presentation"><button class="nav-link' + (s.entity_code === active ? ' active' : '')
                + '" id="' + invEscape(id) + '" data-bs-toggle="tab" data-bs-target="#entity-' + invEscape(s.entity_code)
                + '" type="button" role="tab" data-code="' + invEscape(s.entity_code) + '">'
                + invEscape(s.display_name) + ' '
                + (ready ? '<span class="badge text-bg-success">Ready</span>' : '<span class="badge text-bg-warning">' + x.readiness.invoice.length + ' to fix</span>')
                + '</button></li>';
        }).join('');

        panes.innerHTML = state.entities.map(x => paneHtml(x, x.settings.entity_code === active)).join('');

        tabs.querySelectorAll('[data-code]').forEach(btn => {
            btn.addEventListener('shown.bs.tab', () => { history.replaceState(null, '', '#entity-' + btn.dataset.code); });
        });
        panes.querySelectorAll('form[data-entity]').forEach(f => {
            f.addEventListener('submit', onSaveEntity);
            f.querySelectorAll('input, textarea').forEach(inp => inp.addEventListener('input', () => markTbc(inp)));
            const logoSel = f.querySelector('[name="logo_filename"]');
            if (logoSel) logoSel.addEventListener('change', () => {
                f.querySelector('.logo-preview').src = logoUrl(logoSel.value);
            });
        });
        panes.querySelectorAll('[data-series]').forEach(btn => btn.addEventListener('click', () => openSeries(JSON.parse(btn.dataset.series))));
    }

    function logoUrl(file) {
        return window.EHS_BASE_URL + '/invoice-system/assets/img/' + encodeURIComponent(file);
    }

    function markTbc(inp) {
        const tbc = invIsTbc(inp.value);
        inp.classList.toggle('border-warning', tbc);
        inp.classList.toggle('bg-warning-subtle', tbc);
    }

    function fieldHtml(s, def) {
        const [name, type, col, max, help] = def;
        const v = s[name] == null ? '' : s[name];
        const tbc = invIsTbc(v);
        const cls = 'form-control form-control-sm' + (tbc ? ' border-warning bg-warning-subtle' : '');
        const id = 'f-' + s.entity_code + '-' + name;
        const input = type === 'textarea'
            ? '<textarea class="' + cls + '" id="' + invEscape(id) + '" name="' + name + '" rows="4" maxlength="' + max + '">' + invEscape(v) + '</textarea>'
            : '<input type="' + (type === 'email' ? 'text' : 'text') + '" class="' + cls + '" id="' + invEscape(id) + '" name="' + name
              + '" maxlength="' + max + '" value="' + invEscape(v) + '"' + (type === 'email' ? ' inputmode="email"' : '') + '>';
        return '<div class="col-12 ' + col + '"><label class="form-label small mb-0" for="' + invEscape(id) + '">' + invEscape(label(name))
            + (tbc ? ' <span class="badge text-bg-warning">TBC</span>' : '') + '</label>' + input
            + '<div class="invalid-feedback" data-for="' + name + '"></div>'
            + (help ? '<div class="form-text">' + invEscape(help) + '</div>' : '') + '</div>';
    }

    function readinessHtml(x) {
        const inv = x.readiness.invoice;
        const cnOnly = x.readiness.credit_note.filter(p => !inv.some(q => q.message === p.message));
        let html = '';
        if (inv.length === 0) {
            html += '<div class="alert alert-success py-2 small mb-2"><i class="bi bi-check-circle"></i> Ready to issue invoices dated ' + state.year + '.</div>';
        } else {
            html += '<div class="alert alert-warning py-2 small mb-2"><strong><i class="bi bi-lock"></i> Invoice issuing blocked: '
                + inv.length + ' item(s)</strong><ul class="mb-0">' + inv.map(p => '<li>' + invEscape(p.message) + '</li>').join('') + '</ul></div>';
        }
        if (cnOnly.length) {
            html += '<div class="alert alert-light border py-2 small mb-2"><strong>Credit notes additionally need:</strong><ul class="mb-0">'
                + cnOnly.map(p => '<li>' + invEscape(p.message) + '</li>').join('') + '</ul></div>';
        }
        if (x.has_quote_settings) {
            const q = x.readiness.quotation || [];
            html += q.length === 0
                ? '<div class="alert alert-success py-2 small mb-2"><i class="bi bi-check-circle"></i> Ready to finalise and send quotations dated ' + state.year + '.</div>'
                : '<div class="alert alert-light border py-2 small mb-2"><strong><i class="bi bi-lock"></i> Quotations: ' + q.length
                  + ' item(s) before finalising</strong><ul class="mb-0">' + q.map(p => '<li>' + invEscape(p.message) + '</li>').join('') + '</ul></div>';
        }
        (x.warnings || []).forEach(w => {
            html += '<div class="alert alert-info py-2 small mb-2"><i class="bi bi-info-circle"></i> ' + invEscape(w) + '</div>';
        });
        return html;
    }

    function seriesHtml(x) {
        const s = x.settings;
        const rows = x.series.map(r => {
            const prefix = seriesPrefix(s, r.doc_type);
            const confirmed = String(r.is_confirmed) === '1';
            const payload = { entity_code: s.entity_code, doc_type: r.doc_type, period_year: Number(r.period_year),
                next_value: r.next_value, max_issued_seq: r.max_issued_seq, prefix: prefix, digits: Number(s.seq_digits),
                prefix_confirmed: String(s.quote_prefix_confirmed) === '1' };
            return '<tr><td>' + invEscape(r.period_year) + '</td>'
                + '<td>' + invEscape(TYPE_LABELS[r.doc_type] || r.doc_type) + '</td>'
                + '<td class="font-monospace">' + (r.preview ? invEscape(r.preview) : '<span class="text-muted">—</span>') + '</td>'
                + '<td>' + (confirmed
                    ? '<span class="badge text-bg-success">Confirmed</span><div class="small text-muted">' + invEscape(r.confirmed_by_name || '') + ' ' + invEscape(invFormatDateTime(r.confirmed_at)) + '</div>'
                    : '<span class="badge text-bg-warning"><i class="bi bi-lock"></i> Locked</span>') + '</td>'
                + '<td class="small">' + (Number(r.max_issued_seq) > 0 ? invEscape(r.max_issued_seq) : '<span class="text-muted">none</span>') + '</td>'
                + '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" data-series="' + invEscape(JSON.stringify(payload)) + '">'
                + (confirmed ? 'Change' : 'Confirm next number') + '</button></td></tr>';
        }).join('');
        const nextYear = state.year + 1;
        const types = x.has_quote_settings ? ['invoice', 'credit_note', 'quotation'] : ['invoice', 'credit_note'];
        const addBtns = types.filter(t => !x.series.some(r => Number(r.period_year) === nextYear && r.doc_type === t)).map(t => {
            const prefix = seriesPrefix(s, t);
            const payload = { entity_code: s.entity_code, doc_type: t, period_year: nextYear, next_value: null, max_issued_seq: 0, prefix: prefix, digits: Number(s.seq_digits),
                prefix_confirmed: String(s.quote_prefix_confirmed) === '1' };
            return '<button type="button" class="btn btn-sm btn-outline-secondary me-2" data-series="' + invEscape(JSON.stringify(payload)) + '">'
                + '<i class="bi bi-plus"></i> ' + nextYear + ' ' + invEscape((TYPE_LABELS[t] || t).toLowerCase()) + ' series</button>';
        }).join('');
        return '<h6 class="mt-4">Number series</h6>'
            + '<div class="table-responsive"><table class="table table-sm align-middle small"><thead class="table-light"><tr>'
            + '<th>Year</th><th>Type</th><th>Next number</th><th>Status</th><th>Highest issued</th><th></th></tr></thead><tbody>'
            + rows + '</tbody></table></div>' + addBtns;
    }

    function paneHtml(x, isActive) {
        const s = x.settings;
        const frozen = x.format_frozen;
        const gst = x.gst_status;
        const logos = state.logos.map(l => '<option value="' + invEscape(l) + '"' + (l === s.logo_filename ? ' selected' : '') + '>' + invEscape(l) + '</option>').join('');

        const groups = GROUPS.map(g => '<fieldset class="border rounded p-2 mb-3"><legend class="float-none w-auto px-2 fs-6 mb-0">'
            + invEscape(g.title) + '</legend><div class="row g-2">' + g.fields.map(f => fieldHtml(s, f)).join('') + '</div></fieldset>').join('');

        const prefixInput = (name) => {
            const v = s[name] || '';
            const tbc = invIsTbc(v);
            return '<div class="col-6 col-md-3"><label class="form-label small mb-0" for="f-' + invEscape(s.entity_code) + '-' + name + '">'
                + invEscape(label(name)) + (tbc ? ' <span class="badge text-bg-warning">TBC</span>' : '') + '</label>'
                + '<input class="form-control form-control-sm' + (tbc ? ' border-warning bg-warning-subtle' : '') + '" id="f-' + invEscape(s.entity_code) + '-' + name
                + '" name="' + name + '" maxlength="10" value="' + invEscape(v) + '"' + (frozen ? ' disabled' : '') + '>'
                + '<div class="invalid-feedback" data-for="' + name + '"></div><div class="form-text">Blank = no prefix.</div></div>';
        };

        return '<div class="tab-pane fade' + (isActive ? ' show active' : '') + '" id="entity-' + invEscape(s.entity_code) + '" role="tabpanel">'
            + readinessHtml(x)
            + '<form data-entity="' + invEscape(s.entity_code) + '" novalidate autocomplete="off">'
            + '<input type="hidden" name="row_hash" value="' + invEscape(x.row_hash) + '">'
            + '<div class="row g-3"><div class="col-12 col-xl-9">' + groups + '</div>'
            + '<div class="col-12 col-xl-3"><fieldset class="border rounded p-2 mb-3"><legend class="float-none w-auto px-2 fs-6 mb-0">Logo</legend>'
            + '<img class="logo-preview img-fluid border rounded bg-white p-1 mb-2" style="max-height:110px" alt="Logo preview" src="' + invEscape(logoUrl(s.logo_filename)) + '">'
            + '<select class="form-select form-select-sm" name="logo_filename" aria-label="Logo file">' + logos + '</select>'
            + '<div class="invalid-feedback" data-for="logo_filename"></div>'
            + '<div class="form-text">Files in invoice-system/assets/img/ named logo_*.png. Embedded into PDFs; never linked.</div></fieldset></div></div>'

            + '<fieldset class="border rounded p-2 mb-3"><legend class="float-none w-auto px-2 fs-6 mb-0">GST</legend><div class="row g-2">'
            + '<div class="col-12 col-md-4"><label class="form-label small mb-0" for="f-' + invEscape(s.entity_code) + '-gst_status">GST status'
            + (gst === 'unconfirmed' ? ' <span class="badge text-bg-warning">TBC</span>' : '') + '</label>'
            + '<select class="form-select form-select-sm' + (gst === 'unconfirmed' ? ' border-warning bg-warning-subtle' : '') + '" id="f-' + invEscape(s.entity_code) + '-gst_status" name="gst_status">'
            + '<option value="unconfirmed"' + (gst === 'unconfirmed' ? ' selected' : '') + '>Not confirmed yet (blocks issuing)</option>'
            + '<option value="not_registered"' + (gst === 'not_registered' ? ' selected' : '') + '>Not GST-registered (prints "NO GST")</option>'
            + '<option value="registered"' + (gst === 'registered' ? ' selected' : '') + '>GST-registered (charges GST)</option></select>'
            + '<div class="invalid-feedback" data-for="gst_status"></div></div>'
            + '<div class="col-6 col-md-2"><label class="form-label small mb-0" for="f-' + invEscape(s.entity_code) + '-gst_rate">GST rate %</label>'
            + '<input class="form-control form-control-sm" id="f-' + invEscape(s.entity_code) + '-gst_rate" name="gst_rate" inputmode="decimal" maxlength="6" value="' + invEscape(s.gst_rate) + '">'
            + '<div class="invalid-feedback" data-for="gst_rate"></div></div>'
            + fieldHtml(s, ['gst_reg_no', 'text', 'col-md-3', 50, 'Required when GST-registered.'])
            + fieldHtml(s, ['no_gst_label', 'text', 'col-md-3', 30])
            + '</div></fieldset>'

            + '<fieldset class="border rounded p-2 mb-3"><legend class="float-none w-auto px-2 fs-6 mb-0">Defaults and number format</legend><div class="row g-2">'
            + '<div class="col-6 col-md-3"><label class="form-label small mb-0" for="f-' + invEscape(s.entity_code) + '-default_terms_days">Default payment terms (days)</label>'
            + '<input type="number" min="0" max="365" step="1" class="form-control form-control-sm" id="f-' + invEscape(s.entity_code) + '-default_terms_days" name="default_terms_days" value="' + invEscape(s.default_terms_days) + '">'
            + '<div class="invalid-feedback" data-for="default_terms_days"></div></div>'
            + prefixInput('invoice_prefix') + prefixInput('cn_prefix')
            + '<div class="col-6 col-md-3"><label class="form-label small mb-0" for="f-' + invEscape(s.entity_code) + '-seq_digits">Sequence digits</label>'
            + '<select class="form-select form-select-sm" id="f-' + invEscape(s.entity_code) + '-seq_digits" name="seq_digits"' + (frozen ? ' disabled' : '') + '>'
            + [3, 4, 5, 6].map(n => '<option' + (Number(s.seq_digits) === n ? ' selected' : '') + '>' + n + '</option>').join('') + '</select>'
            + '<div class="invalid-feedback" data-for="seq_digits"></div></div>'
            + (frozen ? '<div class="col-12 form-text"><i class="bi bi-lock"></i> Number format is frozen because documents have been issued for this entity.</div>' : '')
            + '</div></fieldset>'

            + (x.has_quote_settings ? quoteFieldset(x) : '')
            + '<div class="d-flex flex-wrap align-items-center gap-2">'
            + '<button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Save ' + invEscape(s.display_name) + ' settings</button>'
            + '<span class="small text-muted">Last saved ' + invEscape(invFormatDateTime(s.updated_at))
            + (s.updated_by_name ? ' by ' + invEscape(s.updated_by_name) : '') + '. Bank and payee changes are recorded in the activity log.</span>'
            + '</div></form>'
            + seriesHtml(x)
            + '</div>';
    }

    async function onSaveEntity(ev) {
        ev.preventDefault();
        const f = ev.currentTarget;
        invClearFieldErrors(f);
        const code = f.dataset.entity;
        const payload = { entity_code: code };
        f.querySelectorAll('input[name], textarea[name], select[name]').forEach(el => {
            if (!el.disabled) payload[el.name] = el.value;
        });
        const btn = f.querySelector('button[type="submit"]');
        btn.disabled = true;
        try {
            state = await invFetch(INV_API + '/settings.php', { method: 'PUT', body: payload });
            const n = (state.changed || []).length;
            invToast(n ? 'Saved ' + n + ' change(s).' : 'No changes to save.');
            render(code);
        } catch (e) {
            if (e.message === 'session_expired') return;
            if (e.status === 422 && e.data && e.data.fields) {
                invShowFieldErrors(f, e.data.fields);
                invToast('Please correct the highlighted fields.', true);
            } else {
                invToast(e.message, true);
            }
        } finally {
            btn.disabled = false;
        }
    }

    // ------------------------------------------------------------ series modal
    let seriesCtx = null;

    function updatePreview() {
        const n = parseInt(seriesForm.elements.next_value.value, 10);
        const out = document.getElementById('series-preview');
        if (!seriesCtx || !n || n < 1) { out.textContent = '—'; return; }
        const yy = String(seriesCtx.period_year % 100).padStart(2, '0');
        const prefix = invIsTbc(seriesCtx.prefix) ? '«TBC»' : (seriesCtx.prefix || '');
        out.textContent = prefix + yy + String(n).padStart(seriesCtx.digits, '0');
    }

    function openSeries(ctx) {
        seriesCtx = ctx;
        seriesForm.reset();
        invClearFieldErrors(seriesForm);
        seriesForm.elements.entity_code.value = ctx.entity_code;
        seriesForm.elements.doc_type.value = ctx.doc_type;
        seriesForm.elements.period_year.value = ctx.period_year;
        seriesForm.elements.next_value.value = ctx.next_value || '';
        seriesForm.elements.next_value.max = String(Math.pow(10, ctx.digits) - 1);
        document.getElementById('series-digits').textContent = ctx.digits;
        const typeLabel = TYPE_LABELS[ctx.doc_type] || ctx.doc_type;
        document.getElementById('series-context').innerHTML = '<strong>' + invEscape(ctx.entity_code) + '</strong> · '
            + typeLabel + ' numbers dated <strong>' + invEscape(ctx.period_year) + '</strong> · prefix '
            + (ctx.prefix ? '<code>' + invEscape(ctx.prefix) + '</code>' : '<em>none</em>')
            + (invIsTbc(ctx.prefix) ? ' <span class="badge text-bg-warning">set the prefix first</span>' : '')
            + (ctx.doc_type === 'quotation' && !ctx.prefix_confirmed && !invIsTbc(ctx.prefix)
                ? '<div class="mt-1 text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> The quotation prefix is UNCONFIRMED. Confirming this series also confirms the prefix <code>' + invEscape(ctx.prefix) + '</code>.</div>' : '');
        document.getElementById('series-max').textContent = Number(ctx.max_issued_seq) > 0
            ? 'Highest sequence already issued in this series: ' + ctx.max_issued_seq + '. The next number must be greater.'
            : 'No documents issued in this series yet.';
        updatePreview();
        seriesModal.show();
    }

    seriesForm.elements.next_value.addEventListener('input', updatePreview);

    seriesForm.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        invClearFieldErrors(seriesForm);
        if (!seriesForm.elements.acknowledge.checked) {
            invShowFieldErrors(seriesForm, { acknowledge: 'Required.' });
            return;
        }
        const btn = document.getElementById('btn-series-save');
        btn.disabled = true;
        try {
            const res = await invFetch(INV_API + '/number_series.php', {
                method: 'POST',
                body: {
                    entity_code: seriesForm.elements.entity_code.value,
                    doc_type: seriesForm.elements.doc_type.value,
                    period_year: Number(seriesForm.elements.period_year.value),
                    next_value: Number(seriesForm.elements.next_value.value),
                    acknowledge: true,
                },
            });
            seriesModal.hide();
            invToast('Confirmed. Next number: ' + res.preview);
            load(seriesForm.elements.entity_code.value);
        } catch (e) {
            if (e.message === 'session_expired') return;
            if (e.data && e.data.fields) invShowFieldErrors(seriesForm, e.data.fields);
            invToast(e.message, true);
        } finally {
            btn.disabled = false;
        }
    });

    load(null);
})();