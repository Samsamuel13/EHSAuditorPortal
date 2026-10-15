// invoice-system/assets/js/inv_quotation_edit.js — draft quotation editor (Stage 3B).
// The server recomputes every amount and sum row; client figures are a live preview only.
'use strict';

(function () {
    const boot = JSON.parse(document.getElementById('inv-boot').textContent);
    const form = document.getElementById('q-form');
    const linesEl = document.getElementById('lines');
    const tpl = document.getElementById('tpl-item');
    const checkPanel = document.getElementById('check-panel');
    const dirtyBadge = document.getElementById('dirty-badge');
    const finModal = new bootstrap.Modal(document.getElementById('fin-modal'));
    const TEXTS = ['ref_prefix', 'intro', 'terms_text', 'payment_terms', 'closing_text'];

    const f = {
        entity: document.getElementById('f-entity'), partyId: document.getElementById('f-party-id'),
        partyQ: document.getElementById('f-party-q'), partyResults: document.getElementById('party-results'),
        date: document.getElementById('f-date'), days: document.getElementById('f-days'), until: document.getElementById('f-until'),
        subject: document.getElementById('f-subject'), scope: document.getElementById('f-scope'), refShow: document.getElementById('ref-prefix-show'),
        bn: document.getElementById('f-bn'), ba: document.getElementById('f-ba'), bat: document.getElementById('f-bat'),
        be: document.getElementById('f-be'), notes: document.getElementById('f-notes'), addService: document.getElementById('add-service'),
    };
    const t = {};
    TEXTS.forEach(k => { t[k] = document.getElementById('t-' + k); });

    const state = { id: boot.id, rowHash: null, lines: [], party: null, dirty: false, check: null, warnings: [] };
    const servicesById = {};
    boot.services.forEach(s => { servicesById[s.id] = s; });

    // ------------------------------------------------------------ money (cents)
    function toCents(v) {
        const s = String(v == null ? '' : v).replace(/[,\s$]/g, '');
        if (!/^\d{1,10}(\.\d{1,2})?$/.test(s)) return null;
        const [a, b = ''] = s.split('.');
        return Number(a) * 100 + Number((b + '00').slice(0, 2));
    }
    function money(c) {
        const d = Math.floor(c / 100), r = c % 100;
        return '$' + d.toLocaleString('en-SG') + '.' + String(r).padStart(2, '0');
    }
    function entityById(code) { return boot.entities.find(e => e.code === code) || null; }
    function setDirty(on) { state.dirty = on; dirtyBadge.classList.toggle('d-none', !on); }
    function addDays(ymd, n) {
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(ymd || '');
        if (!m || isNaN(n)) return '';
        const d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3] + n));
        return String(d.getUTCDate()).padStart(2, '0') + '/' + String(d.getUTCMonth() + 1).padStart(2, '0') + '/' + d.getUTCFullYear();
    }
    function updateUntil() { f.until.value = addDays(f.date.value, parseInt(f.days.value, 10)); }
    function option(value, label, selected) {
        return '<option value="' + invEscape(value) + '"' + (selected ? ' selected' : '') + '>' + invEscape(label) + '</option>';
    }

    // ------------------------------------------------------------ selects / texts
    function fillEntitySelect(current) {
        f.entity.innerHTML = boot.entities.filter(e => e.active || e.code === current)
            .map(e => option(e.code, e.name + (e.blocking ? ' (sending blocked: ' + e.blocking + ' to fix)' : ''), e.code === current)).join('');
        if (!current && f.entity.options.length) f.entity.selectedIndex = 0;
    }
    function serviceOptions(current) {
        const list = boot.services.filter(s => (String(s.is_active) === '1' || String(s.id) === String(current))
            && (s.entity_code === null || s.entity_code === f.entity.value));
        return option('', '— No service (free text) —', !current)
            + list.map(s => option(s.id, s.code + ' · ' + s.title, String(s.id) === String(current))).join('');
    }
    function fillAddService() { f.addService.innerHTML = serviceOptions(null); }
    function textsFromEntity() {
        const e = entityById(f.entity.value);
        if (!e) return;
        TEXTS.forEach(k => { t[k].value = e.texts[k] || ''; });
        f.days.value = e.valid_days;
        updateUntil(); showRefPrefix(); updateTotals();
    }
    function showRefPrefix() { f.refShow.textContent = 'Ref: ' + (t.ref_prefix.value || ''); f.refShow.title = f.refShow.textContent; }

    // ------------------------------------------------------------ lines
    function qtyIsAuto(l) {
        const s = l.service_id ? servicesById[l.service_id] : null;
        return l.line_type === 'item' && !!(s && String(s.qty_from_entities) === '1' && l.entities.length > 0);
    }
    /** Live preview of every row: [amount, special] in cents, or null when unparsable. Mirrors inv_build_quote(). */
    function computeRows() {
        let runL = 0, runS = 0;
        return state.lines.map(l => {
            if (l.line_type === 'auto_sum') { const r = [runL, runS]; runL = 0; runS = 0; return r; }
            const rate = toCents(l.rate);
            const sp = String(l.special_rate || '').trim() === '' ? null : toCents(l.special_rate);
            if (rate === null || (String(l.special_rate || '').trim() !== '' && sp === null)) return null;
            if (l.line_type === 'manual_sum') { runL = 0; runS = 0; return [rate, sp === null ? rate : sp]; }
            const qty = qtyIsAuto(l) ? l.entities.length * 100 : toCents(l.qty === '' || l.qty == null ? '1' : l.qty);
            if (qty === null) return null;
            const a = Math.floor((qty * rate + 50) / 100), b = Math.floor((qty * (sp === null ? rate : sp) + 50) / 100);
            runL += a; runS += b;
            return [a, b];
        });
    }

    function renderLines() {
        linesEl.innerHTML = '';
        if (!state.lines.length) {
            linesEl.innerHTML = '<div class="text-muted small">No rows yet. Add item rows, then (optionally) a sum row for the certification cycle.</div>';
        }
        state.lines.forEach((l, i) => linesEl.appendChild(buildLine(l, i)));
        updateTotals();
    }

    function renderEntities(box, l) {
        box.querySelector('.ln-ents').innerHTML = l.entities.map((e, k) =>
            '<div class="input-group input-group-sm mb-1" data-k="' + k + '">'
            + '<span class="input-group-text" style="min-width:2.6rem">' + (k + 1) + ')</span>'
            + '<input class="form-control ln-ent-name" maxlength="200" value="' + invEscape(e.company_name) + '" aria-label="Audited entity ' + (k + 1) + '">'
            + '<button type="button" class="btn btn-outline-danger ln-ent-del" aria-label="Remove entity ' + (k + 1) + '"><i class="bi bi-x"></i></button>'
            + '</div>').join('');
    }

    function buildLine(l, i) {
        const box = tpl.content.firstElementChild.cloneNode(true);
        box.dataset.i = String(i);
        box.querySelector('.ln-no').textContent = String(i + 1);
        const isItem = l.line_type === 'item';
        box.querySelector('.ln-kind').textContent = isItem ? 'Item' : (l.line_type === 'auto_sum' ? 'Sum row (automatic)' : 'Sum row (manual price)');
        if (!isItem) {
            box.classList.add('bg-light');
            box.querySelectorAll('.ln-item-only').forEach(el => el.classList.add('d-none'));
            box.querySelector('.ln-title-col').className = 'col-12 ln-title-col';
        }
        if (l.line_type === 'auto_sum') box.querySelectorAll('.ln-price').forEach(el => el.classList.add('d-none'));
        const svc = box.querySelector('.ln-service');
        svc.innerHTML = serviceOptions(l.service_id);
        box.querySelector('.ln-title').value = l.title || '';
        box.querySelector('.ln-desc').value = l.description || '';
        box.querySelector('.ln-qty').value = l.qty == null ? '1' : String(l.qty).replace(/\.00$/, '');
        box.querySelector('.ln-rate').value = l.rate == null ? '' : l.rate;
        box.querySelector('.ln-special').value = l.special_rate == null ? '' : l.special_rate;
        box.querySelector('.ln-year').value = l.cycle_year == null ? '' : String(l.cycle_year);
        box.querySelector('.ln-up').disabled = i === 0;
        box.querySelector('.ln-down').disabled = i === state.lines.length - 1;
        if (isItem) renderEntities(box, l);
        const q = box.querySelector('.ln-qty');
        const syncQty = () => {
            const auto = qtyIsAuto(l);
            q.readOnly = auto;
            if (auto) q.value = String(l.entities.length);
            box.querySelector('.ln-qty-auto').classList.toggle('d-none', !auto);
        };
        syncQty();

        const changed = () => { setDirty(true); syncQty(); updateTotals(); };
        svc.addEventListener('change', () => {
            const s = servicesById[svc.value];
            l.service_id = s ? Number(s.id) : null;
            if (s) {
                l.title = s.title; l.rate = s.default_rate;
                box.querySelector('.ln-title').value = l.title;
                box.querySelector('.ln-rate').value = l.rate;
            }
            changed();
        });
        box.querySelector('.ln-title').addEventListener('input', e => { l.title = e.target.value; setDirty(true); });
        box.querySelector('.ln-desc').addEventListener('input', e => { l.description = e.target.value; setDirty(true); });
        q.addEventListener('input', e => { l.qty = e.target.value; changed(); });
        box.querySelector('.ln-rate').addEventListener('input', e => { l.rate = e.target.value; changed(); });
        box.querySelector('.ln-special').addEventListener('input', e => { l.special_rate = e.target.value; changed(); });
        box.querySelector('.ln-year').addEventListener('change', e => { l.cycle_year = e.target.value === '' ? null : Number(e.target.value); setDirty(true); });
        box.querySelector('.ln-up').addEventListener('click', () => move(i, -1));
        box.querySelector('.ln-down').addEventListener('click', () => move(i, 1));
        box.querySelector('.ln-remove').addEventListener('click', () => {
            if (!confirm('Remove row ' + (i + 1) + '?')) return;
            state.lines.splice(i, 1); setDirty(true); renderLines();
        });

        if (isItem) {
            const ents = box.querySelector('.ln-ents');
            ents.addEventListener('input', e => {
                const row = e.target.closest('[data-k]');
                if (row && e.target.classList.contains('ln-ent-name')) { l.entities[+row.dataset.k].company_name = e.target.value; setDirty(true); }
            });
            ents.addEventListener('click', e => {
                const btn = e.target.closest('.ln-ent-del');
                if (!btn) return;
                l.entities.splice(+btn.closest('[data-k]').dataset.k, 1);
                renderEntities(box, l); changed();
            });
            const eq = box.querySelector('.ln-ent-q');
            const results = box.querySelector('.ln-ent-results');
            const addTyped = () => {
                const name = eq.value.trim();
                if (!name) return;
                l.entities.push({ company_name: name, uen: null, source_client_id: null });
                eq.value = ''; results.innerHTML = '';
                renderEntities(box, l); changed();
            };
            eq.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); addTyped(); } });
            box.querySelector('.ln-ent-add').addEventListener('click', addTyped);
            let tm = null;
            eq.addEventListener('input', () => {
                clearTimeout(tm);
                const v = eq.value.trim();
                if (!boot.lookup || v.length < 2) { results.innerHTML = ''; return; }
                tm = setTimeout(async () => {
                    try {
                        const d = await invFetch(INV_API + '/client_lookup.php?q=' + encodeURIComponent(v));
                        results.innerHTML = (d.results || []).slice(0, 8).map(c =>
                            '<button type="button" class="list-group-item list-group-item-action py-1" data-c="' + invEscape(JSON.stringify(c)) + '">'
                            + invEscape(c.company_name) + '</button>').join('');
                    } catch (e) { results.innerHTML = ''; }
                }, 300);
            });
            results.addEventListener('click', e => {
                const b = e.target.closest('[data-c]');
                if (!b) return;
                const c = JSON.parse(b.dataset.c);
                l.entities.push({ company_name: c.company_name, uen: c.uen, source_client_id: c.source_client_id });
                eq.value = ''; results.innerHTML = '';
                renderEntities(box, l); changed();
            });
        }
        return box;
    }

    function move(i, d) {
        const j = i + d;
        if (j < 0 || j >= state.lines.length) return;
        [state.lines[i], state.lines[j]] = [state.lines[j], state.lines[i]];
        setDirty(true); renderLines();
    }
    function addRow(type) {
        if (state.lines.length >= boot.max_lines) { invToast('At most ' + boot.max_lines + ' rows per quotation.', true); return; }
        const s = type === 'item' ? servicesById[f.addService.value] : null;
        state.lines.push({
            line_type: type, service_id: s ? Number(s.id) : null,
            title: s ? s.title : (type === 'item' ? '' : 'Certification Cycle'), description: '',
            qty: '1', rate: s ? s.default_rate : (type === 'auto_sum' ? null : '0.00'), special_rate: '', cycle_year: null, entities: [],
        });
        setDirty(true); renderLines();
        const last = linesEl.querySelector('.q-line:last-child .ln-title');
        if (last) last.focus();
    }
    document.getElementById('btn-add-line').addEventListener('click', () => addRow('item'));
    document.getElementById('btn-add-auto').addEventListener('click', () => addRow('auto_sum'));
    document.getElementById('btn-add-manual').addEventListener('click', () => addRow('manual_sum'));

    function updateTotals() {
        const rows = computeRows();
        let tl = 0, ts = 0, ok = true;
        rows.forEach((r, i) => {
            const box = linesEl.querySelector('.q-line[data-i="' + i + '"]');
            if (box) box.querySelector('.ln-amount').textContent = r === null ? '—' : money(r[0]) + ' / ' + money(r[1]);
            if (state.lines[i].line_type !== 'item') return;
            if (r === null) ok = false; else { tl += r[0]; ts += r[1]; }
        });
        document.getElementById('t-list').textContent = ok ? money(tl) : '—';
        document.getElementById('t-special').textContent = ok ? money(ts) : '—';
        const e = entityById(f.entity.value);
        document.getElementById('t-gst').textContent = e && e.gst_registered
            ? 'GST-registered: the table header prints "(before GST)" and a GST note is added.' : 'Printed with "(No GST)" under Rate.';
    }

    // ------------------------------------------------------------ party picker
    let pt = null;
    f.partyQ.addEventListener('input', () => {
        clearTimeout(pt);
        const v = f.partyQ.value.trim();
        if (v.length < 2) { f.partyResults.innerHTML = ''; return; }
        pt = setTimeout(async () => {
            try {
                const d = await invFetch(INV_API + '/parties.php?status=active&q=' + encodeURIComponent(v));
                f.partyResults.innerHTML = (d.parties || []).slice(0, 10).map(p =>
                    '<button type="button" class="list-group-item list-group-item-action py-1" data-id="' + invEscape(p.id) + '">'
                    + '<strong>' + invEscape(p.display_name) + '</strong>'
                    + (p.billing_address ? '<div class="text-muted text-truncate">' + invEscape(p.billing_address) + '</div>' : '<div class="text-warning">No address</div>')
                    + '</button>').join('') || '<div class="list-group-item text-muted">No active party found.</div>';
            } catch (e) { f.partyResults.innerHTML = ''; }
        }, 300);
    });
    f.partyResults.addEventListener('click', async e => {
        const b = e.target.closest('[data-id]');
        if (!b) return;
        f.partyResults.innerHTML = '';
        try {
            const d = await invFetch(INV_API + '/parties.php?id=' + encodeURIComponent(b.dataset.id));
            pickParty(d.party, true);
        } catch (err) { invToast(err.message, true); }
    });
    function pickParty(p, fill) {
        state.party = p;
        f.partyId.value = p.id;
        f.partyQ.value = p.display_name;
        if (fill) { fillAddressee(p); setDirty(true); }
    }
    function fillAddressee(p) {
        f.bn.value = p.display_name || '';
        f.ba.value = p.billing_address || '';
        f.bat.value = p.attention || '';
        f.be.value = p.accounts_email || '';
    }
    document.getElementById('btn-refill').addEventListener('click', () => {
        if (!state.party) { invToast('Choose a party first.', true); return; }
        fillAddressee(state.party); setDirty(true);
    });
    document.getElementById('btn-texts-reset').addEventListener('click', () => {
        if (!confirm('Replace the printed texts and validity with the defaults of the selected entity?')) return;
        textsFromEntity(); setDirty(true);
    });

    // ------------------------------------------------------------ load / collect / save
    function applyServerPayload(d) {
        const q = d.quotation;
        state.id = Number(q.id);
        state.rowHash = d.row_hash;
        fillEntitySelect(q.entity_code);
        fillAddService();
        f.date.value = q.quote_date;
        f.days.value = q.valid_days;
        f.subject.value = q.subject || '';
        f.scope.value = q.scope || '';
        f.bn.value = q.bill_to_name || '';
        f.ba.value = q.bill_to_address || '';
        f.bat.value = q.bill_to_attention || '';
        f.be.value = q.bill_to_email || '';
        f.notes.value = q.internal_notes || '';
        f.partyId.value = q.party_id;
        TEXTS.forEach(k => { t[k].value = q[k] || ''; });
        state.lines = d.lines.map(l => ({
            line_type: l.line_type, service_id: l.service_id, title: l.title, description: l.description || '',
            qty: l.qty, rate: l.rate, special_rate: l.special_rate, cycle_year: l.cycle_year,
            entities: l.entities.map(e => Object.assign({}, e)),
        }));
        renderLines();
        updateUntil(); showRefPrefix();
        state.check = d.finalise_check || null;
        state.warnings = d.warnings || [];
        renderCheckPanel();
        document.getElementById('page-title').firstChild.textContent = 'Draft quotation #' + state.id + ' ';
        document.getElementById('btn-delete').classList.remove('d-none');
        document.getElementById('btn-duplicate').classList.remove('d-none');
        if (!location.search.includes('id=')) history.replaceState(null, '', 'quotation_edit.php?id=' + state.id);
        setDirty(false);
    }

    function renderCheckPanel(extra) {
        const c = state.check;
        const probs = extra || (c ? c.problems : []);
        let html = '';
        if (state.id && probs.length) {
            html += '<div class="alert alert-warning small mb-2"><strong><i class="bi bi-lock"></i> Cannot be finalised yet (' + probs.length + '):</strong><ul class="mb-0">'
                + probs.map(p => '<li>' + invEscape(p.message)
                    + (!/^(lines|bill_to_|party_id|content|quote_date|valid_days|subject|ref_prefix|intro|terms_text|payment_terms|closing_text|entity_code)/.test(p.field || '')
                        ? ' <a href="' + invEscape(window.EHS_BASE_URL + '/invoice-system/settings.php') + '" target="_blank" rel="noopener">Settings</a>' : '')
                    + '</li>').join('')
                + '</ul><div class="mt-1">You can keep editing and preview the draft (watermarked "DRAFT, NOT SENT", no stamp).</div></div>';
        } else if (state.id && c) {
            html += '<div class="alert alert-success small mb-2"><i class="bi bi-check-circle"></i> Ready to finalise'
                + (c.next_number ? ': number <strong class="font-monospace">' + invEscape(c.next_number) + '</strong> (assigned when finalised).' : '.') + '</div>';
        }
        (state.warnings || []).forEach(w => { html += '<div class="alert alert-light border small py-1 mb-1"><i class="bi bi-info-circle"></i> ' + invEscape(w) + '</div>'; });
        checkPanel.innerHTML = html;
    }

    function collect() {
        const out = {
            entity_code: f.entity.value, party_id: f.partyId.value ? Number(f.partyId.value) : null,
            quote_date: f.date.value, valid_days: f.days.value, subject: f.subject.value, scope: f.scope.value,
            bill_to_name: f.bn.value, bill_to_address: f.ba.value, bill_to_attention: f.bat.value, bill_to_email: f.be.value,
            internal_notes: f.notes.value,
            lines: state.lines.map(l => ({
                line_type: l.line_type, service_id: l.line_type === 'item' ? l.service_id : null, title: l.title, description: l.description,
                qty: qtyIsAuto(l) ? String(l.entities.length) : (l.qty === '' || l.qty == null ? '1' : l.qty),
                rate: l.line_type === 'auto_sum' ? null : l.rate, special_rate: l.line_type === 'auto_sum' ? '' : (l.special_rate == null ? '' : l.special_rate),
                cycle_year: l.line_type === 'item' ? l.cycle_year : null,
                entities: l.line_type === 'item' ? l.entities.filter(e => String(e.company_name || '').trim() !== '') : [],
            })),
        };
        TEXTS.forEach(k => { out[k] = t[k].value; });
        return out;
    }

    function showErrors(fields) {
        invShowFieldErrors(form, fields);
        const generic = [];
        Object.entries(fields || {}).forEach(([k, msg]) => {
            const m = /^lines\.(\d+)\.(\w+)$/.exec(k);
            if (m) {
                const box = linesEl.querySelector('.q-line[data-i="' + m[1] + '"]');
                const cls = { service_id: 'service', special_rate: 'special' }[m[2]] || m[2];
                const input = box && box.querySelector('.ln-' + cls);
                const fb = box && box.querySelector('.ln-err-' + m[2]);
                if (input) input.classList.add('is-invalid');
                if (fb) fb.textContent = msg; else generic.push(msg);
            } else if (!form.querySelector('[data-for="' + CSS.escape(k) + '"]')) {
                generic.push(msg);
            }
        });
        if (generic.length) invToast(generic.join(' '), true);
    }

    async function save() {
        invClearFieldErrors(form);
        linesEl.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        const payload = collect();
        const btn = document.getElementById('btn-save');
        btn.disabled = true;
        try {
            let d;
            if (state.id) {
                payload.id = state.id; payload.row_hash = state.rowHash;
                d = await invFetch(INV_API + '/quotations.php', { method: 'PUT', body: payload });
            } else {
                payload.action = 'create';
                d = await invFetch(INV_API + '/quotations.php', { method: 'POST', body: payload });
            }
            applyServerPayload(d);
            invToast('Draft saved.');
            return true;
        } catch (e) {
            if (e.message === 'session_expired') return false;
            if (e.status === 422 && e.data && e.data.fields) { showErrors(e.data.fields); invToast('Please correct the highlighted fields.', true); }
            else invToast(e.message, true);
            return false;
        } finally {
            btn.disabled = false;
        }
    }
    document.getElementById('btn-save').addEventListener('click', save);

    document.getElementById('btn-preview').addEventListener('click', async () => {
        if (!state.id || state.dirty) { if (!(await save())) return; }
        window.open(INV_API + '/quotation_pdf.php?inline=1&id=' + encodeURIComponent(state.id), '_blank', 'noopener');
    });

    document.getElementById('btn-finalise').addEventListener('click', async () => {
        if (!state.id || state.dirty) { if (!(await save())) return; }
        const c = state.check;
        if (!c || c.problems.length) {
            invToast('This draft cannot be finalised yet: see the list above.', true);
            checkPanel.scrollIntoView({ behavior: 'smooth' });
            return;
        }
        const ent = entityById(f.entity.value);
        document.getElementById('fin-summary').innerHTML =
            '<p>Finalise this quotation for <strong>' + invEscape(f.bn.value) + '</strong>?</p>'
            + '<table class="table table-sm mb-2"><tr><td>Issuing entity</td><td>' + invEscape(ent ? ent.name : f.entity.value) + '</td></tr>'
            + '<tr><td>Number</td><td class="font-monospace">' + invEscape(c.next_number || '—') + '</td></tr>'
            + '<tr><td>Special Rate total</td><td>' + invEscape(document.getElementById('t-special').textContent) + '</td></tr>'
            + '<tr><td>Valid until</td><td>' + invEscape(f.until.value) + '</td></tr></table>'
            + '<div class="alert alert-warning py-1 mb-0"><i class="bi bi-exclamation-triangle"></i> Finalising numbers the quotation, stores its PDF (with the stamp, if one is set) and <strong>locks it</strong>. '
            + 'You then send that PDF to the client yourself. Changes after this need a revision (-R1, -R2…).</div>';
        finModal.show();
    });

    document.getElementById('btn-fin-confirm').addEventListener('click', async () => {
        const btn = document.getElementById('btn-fin-confirm');
        btn.disabled = true;
        try {
            const r = await invFetch(INV_API + '/quotation_action.php', { method: 'POST', body: { id: state.id, row_hash: state.rowHash, action: 'finalise', confirm: true } });
            finModal.hide();
            invToast('Finalised as ' + r.quotation.quote_no + '.');
            setDirty(false);
            window.location.href = window.EHS_BASE_URL + '/invoice-system/quotation_view.php?id=' + encodeURIComponent(state.id) + '&sent=1';
        } catch (e) {
            finModal.hide();
            if (e.message === 'session_expired') return;
            if (e.data && e.data.problems && e.data.problems.length) renderCheckPanel(e.data.problems);
            invToast(e.message, true);
            reload();
        } finally {
            btn.disabled = false;
        }
    });

    document.getElementById('btn-delete').addEventListener('click', async () => {
        if (!state.id || !confirm('Delete this draft permanently? Drafts have no number, so nothing is skipped.')) return;
        try {
            await invFetch(INV_API + '/quotations.php', { method: 'DELETE', body: { id: state.id, row_hash: state.rowHash } });
            setDirty(false);
            window.location.href = window.EHS_BASE_URL + '/invoice-system/quotations.php';
        } catch (e) { if (e.message !== 'session_expired') invToast(e.message, true); }
    });

    document.getElementById('btn-duplicate').addEventListener('click', async () => {
        if (state.dirty && !confirm('Duplicate the last SAVED version? Unsaved changes are not copied.')) return;
        try {
            const d = await invFetch(INV_API + '/quotations.php', { method: 'POST', body: { action: 'duplicate', id: state.id } });
            setDirty(false);
            window.location.href = window.EHS_BASE_URL + '/invoice-system/quotation_edit.php?id=' + encodeURIComponent(d.quotation.id);
        } catch (e) { if (e.message !== 'session_expired') invToast(e.message, true); }
    });

    // ------------------------------------------------------------ wiring
    [f.date, f.days].forEach(el => el.addEventListener('input', () => { updateUntil(); setDirty(true); }));
    [f.subject, f.scope, f.bn, f.ba, f.bat, f.be, f.notes].forEach(el => el.addEventListener('input', () => setDirty(true)));
    TEXTS.forEach(k => t[k].addEventListener('input', () => { setDirty(true); if (k === 'ref_prefix') showRefPrefix(); }));
    f.entity.addEventListener('change', () => {
        fillAddService(); renderLines(); setDirty(true);
        if (confirm('Use the printed texts and validity of the newly selected entity?')) textsFromEntity();
    });
    f.date.max = boot.today;
    window.addEventListener('beforeunload', e => { if (state.dirty) { e.preventDefault(); e.returnValue = ''; } });

    async function reload() {
        try {
            const d = await invFetch(INV_API + '/quotations.php?id=' + encodeURIComponent(state.id));
            if (d.quotation.status !== 'draft') {
                window.location.href = window.EHS_BASE_URL + '/invoice-system/quotation_view.php?id=' + encodeURIComponent(state.id);
                return;
            }
            applyServerPayload(d);
            if (d.quotation.party_id) {
                const p = await invFetch(INV_API + '/parties.php?id=' + encodeURIComponent(d.quotation.party_id));
                pickParty(p.party, false);
            }
        } catch (e) { if (e.message !== 'session_expired') invToast(e.message, true); }
    }

    if (state.id) {
        reload();
    } else {
        fillEntitySelect(null);
        fillAddService();
        f.date.value = boot.today;
        textsFromEntity();
        renderLines();
        renderCheckPanel();
    }
})();