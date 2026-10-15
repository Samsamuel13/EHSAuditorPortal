// invoice-system/assets/js/inv_invoice_edit.js — draft invoice editor.
// The server recomputes every amount; client totals are a live preview only.
'use strict';

(function () {
    const boot = JSON.parse(document.getElementById('inv-boot').textContent);
    const form = document.getElementById('inv-form');
    const linesEl = document.getElementById('lines');
    const tpl = document.getElementById('tpl-line');
    const issuePanel = document.getElementById('issue-panel');
    const dirtyBadge = document.getElementById('dirty-badge');
    const issueModal = new bootstrap.Modal(document.getElementById('issue-modal'));

    const f = {
        entity: document.getElementById('f-entity'), partyId: document.getElementById('f-party-id'),
        partyQ: document.getElementById('f-party-q'), partyResults: document.getElementById('party-results'),
        category: document.getElementById('f-category'), date: document.getElementById('f-date'),
        terms: document.getElementById('f-terms'), due: document.getElementById('f-due'), ref: document.getElementById('f-ref'),
        bn: document.getElementById('f-bn'), ba: document.getElementById('f-ba'), bat: document.getElementById('f-bat'),
        be: document.getElementById('f-be'), notes: document.getElementById('f-notes'), addService: document.getElementById('add-service'),
    };

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

    // ------------------------------------------------------------ helpers
    function setDirty(on) {
        state.dirty = on;
        dirtyBadge.classList.toggle('d-none', !on);
    }
    function addDays(ymd, n) {
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(ymd || '');
        if (!m || isNaN(n)) return '';
        const d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3] + n));
        return String(d.getUTCDate()).padStart(2, '0') + '/' + String(d.getUTCMonth() + 1).padStart(2, '0') + '/' + d.getUTCFullYear();
    }
    function updateDue() {
        f.due.value = addDays(f.date.value, parseInt(f.terms.value, 10));
    }
    function option(value, label, selected, disabled) {
        return '<option value="' + invEscape(value) + '"' + (selected ? ' selected' : '') + (disabled ? ' disabled' : '') + '>' + invEscape(label) + '</option>';
    }

    // ------------------------------------------------------------ selects
    function fillEntitySelect(current) {
        f.entity.innerHTML = boot.entities.filter(e => e.active || e.code === current)
            .map(e => option(e.code, e.name + (e.blocking ? ' (issuing blocked: ' + e.blocking + ' to fix)' : ''), e.code === current)).join('');
        if (!current && f.entity.options.length) f.entity.selectedIndex = 0;
    }
    function fillCategorySelect(current) {
        f.category.innerHTML = option('', 'Choose…', !current)
            + boot.categories.filter(c => String(c.is_active) === '1' || String(c.id) === String(current))
                .map(c => option(c.id, c.name, String(c.id) === String(current))).join('');
    }
    function servicesFor(entity, current) {
        return boot.services.filter(s => (String(s.is_active) === '1' || String(s.id) === String(current))
            && (s.entity_code === null || s.entity_code === entity));
    }
    function serviceOptions(current) {
        const list = servicesFor(f.entity.value, current);
        return option('', '— No service (free text) —', !current)
            + list.map(s => option(s.id, s.code + ' · ' + s.title + (String(s.is_active) === '1' ? '' : ' (inactive)'), String(s.id) === String(current))).join('');
    }
    function fillAddService() {
        f.addService.innerHTML = serviceOptions(null);
    }

    // ------------------------------------------------------------ lines
    function qtyIsAuto(l) {
        const s = l.service_id ? servicesById[l.service_id] : null;
        return !!(s && String(s.qty_from_entities) === '1' && l.entities.length > 0);
    }
    function lineAmount(l) {
        const rate = toCents(l.rate);
        const qty = qtyIsAuto(l) ? l.entities.length * 100 : toCents(l.qty);
        if (rate === null || qty === null) return null;
        return Math.floor((qty * rate + 50) / 100);
    }

    function renderLines() {
        linesEl.innerHTML = '';
        if (!state.lines.length) {
            linesEl.innerHTML = '<div class="text-muted small">No lines yet. Choose a service and click <strong>Add line</strong>.</div>';
        }
        state.lines.forEach((l, i) => linesEl.appendChild(buildLine(l, i)));
        updateTotals();
    }

    function renderEntities(box, l) {
        const list = box.querySelector('.ln-ents');
        list.innerHTML = l.entities.map((e, k) =>
            '<div class="input-group input-group-sm mb-1" data-k="' + k + '">'
            + '<span class="input-group-text" style="min-width:2.6rem">' + (k + 1) + ')</span>'
            + '<input class="form-control ln-ent-name" maxlength="200" value="' + invEscape(e.company_name) + '" aria-label="Audited entity ' + (k + 1) + '">'
            + (e.source_client_id ? '<span class="input-group-text" title="From the client directory (#' + invEscape(e.source_client_id) + ')"><i class="bi bi-journal-check"></i></span>' : '')
            + '<button type="button" class="btn btn-outline-danger ln-ent-del" aria-label="Remove entity ' + (k + 1) + '"><i class="bi bi-x"></i></button>'
            + '</div>').join('');
    }

    function refreshLineQty(box, l) {
        const auto = qtyIsAuto(l);
        const q = box.querySelector('.ln-qty');
        q.readOnly = auto;
        if (auto) q.value = String(l.entities.length);
        box.querySelector('.ln-qty-auto').classList.toggle('d-none', !auto);
        const a = lineAmount(l);
        box.querySelector('.ln-amount').textContent = a === null ? '—' : money(a);
    }

    function buildLine(l, i) {
        const box = tpl.content.firstElementChild.cloneNode(true);
        box.dataset.i = String(i);
        box.querySelector('.ln-no').textContent = String(i + 1);
        const svc = box.querySelector('.ln-service');
        svc.innerHTML = serviceOptions(l.service_id);
        box.querySelector('.ln-title').value = l.title || '';
        box.querySelector('.ln-desc').value = l.description || '';
        box.querySelector('.ln-qty').value = l.qty == null ? '' : String(l.qty).replace(/\.00$/, '');
        box.querySelector('.ln-rate').value = l.rate == null ? '' : l.rate;
        box.querySelector('.ln-up').disabled = i === 0;
        box.querySelector('.ln-down').disabled = i === state.lines.length - 1;
        renderEntities(box, l);
        refreshLineQty(box, l);

        const changed = () => { setDirty(true); refreshLineQty(box, l); updateTotals(); };
        svc.addEventListener('change', () => {
            const s = servicesById[svc.value];
            l.service_id = s ? Number(s.id) : null;
            if (s) {
                l.title = s.title; l.description = s.standard_wording || ''; l.rate = s.default_rate;
                box.querySelector('.ln-title').value = l.title;
                box.querySelector('.ln-desc').value = l.description;
                box.querySelector('.ln-rate').value = l.rate;
            }
            changed();
        });
        box.querySelector('.ln-title').addEventListener('input', e => { l.title = e.target.value; setDirty(true); });
        box.querySelector('.ln-desc').addEventListener('input', e => { l.description = e.target.value; setDirty(true); });
        box.querySelector('.ln-qty').addEventListener('input', e => { l.qty = e.target.value; changed(); });
        box.querySelector('.ln-rate').addEventListener('input', e => { l.rate = e.target.value; changed(); });
        box.querySelector('.ln-up').addEventListener('click', () => { move(i, -1); });
        box.querySelector('.ln-down').addEventListener('click', () => { move(i, 1); });
        box.querySelector('.ln-remove').addEventListener('click', () => {
            if (!confirm('Remove line ' + (i + 1) + '?')) return;
            state.lines.splice(i, 1); setDirty(true); renderLines();
        });

        // audited entities
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
        const q = box.querySelector('.ln-ent-q');
        const results = box.querySelector('.ln-ent-results');
        const addTyped = () => {
            const name = q.value.trim();
            if (!name) return;
            l.entities.push({ company_name: name, uen: null, source_client_id: null });
            q.value = ''; results.innerHTML = '';
            renderEntities(box, l); changed();
        };
        q.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); addTyped(); } });
        box.querySelector('.ln-ent-add').addEventListener('click', addTyped);
        let t = null;
        q.addEventListener('input', () => {
            clearTimeout(t);
            const v = q.value.trim();
            if (!boot.lookup || v.length < 2) { results.innerHTML = ''; return; }
            t = setTimeout(async () => {
                try {
                    const d = await invFetch(INV_API + '/client_lookup.php?q=' + encodeURIComponent(v));
                    results.innerHTML = (d.results || []).slice(0, 8).map(c =>
                        '<button type="button" class="list-group-item list-group-item-action py-1" data-c="' + invEscape(JSON.stringify(c)) + '">'
                        + invEscape(c.company_name) + (c.uen ? ' <span class="text-muted">' + invEscape(c.uen) + '</span>' : '') + '</button>').join('');
                } catch (e) { results.innerHTML = ''; }
            }, 300);
        });
        results.addEventListener('click', e => {
            const b = e.target.closest('[data-c]');
            if (!b) return;
            const c = JSON.parse(b.dataset.c);
            l.entities.push({ company_name: c.company_name, uen: c.uen, source_client_id: c.source_client_id });
            q.value = ''; results.innerHTML = '';
            renderEntities(box, l); changed();
        });
        box.querySelector('.ln-ent-paste-toggle').addEventListener('click', () => box.querySelector('.ln-paste').classList.toggle('d-none'));
        box.querySelector('.ln-paste-apply').addEventListener('click', () => {
            const ta = box.querySelector('.ln-paste-text');
            ta.value.split(/\r?\n/).map(s => s.replace(/^\s*\(?\d{1,3}\s*[).:-]\s*/, '').trim()).filter(Boolean)
                .forEach(name => l.entities.push({ company_name: name.slice(0, 200), uen: null, source_client_id: null }));
            ta.value = ''; box.querySelector('.ln-paste').classList.add('d-none');
            renderEntities(box, l); changed();
        });
        return box;
    }

    function move(i, d) {
        const j = i + d;
        if (j < 0 || j >= state.lines.length) return;
        [state.lines[i], state.lines[j]] = [state.lines[j], state.lines[i]];
        setDirty(true); renderLines();
    }

    document.getElementById('btn-add-line').addEventListener('click', () => {
        const s = servicesById[f.addService.value];
        state.lines.push({
            service_id: s ? Number(s.id) : null, title: s ? s.title : '', description: s ? (s.standard_wording || '') : '',
            qty: '1', rate: s ? s.default_rate : '0.00', entities: [],
        });
        setDirty(true); renderLines();
        const last = linesEl.querySelector('.inv-line:last-child .ln-title');
        if (last) last.focus();
    });

    // ------------------------------------------------------------ totals
    function updateTotals() {
        let sub = 0, ok = true;
        state.lines.forEach(l => { const a = lineAmount(l); if (a === null) ok = false; else sub += a; });
        const ent = entityById(f.entity.value);
        const rate = ent ? ent.gst_rate_h : 0;
        const gst = Math.floor((sub * rate + 5000) / 10000);
        document.getElementById('t-sub').textContent = ok ? money(sub) : '—';
        document.getElementById('t-gst-label').textContent = rate ? 'GST ' + (rate / 100) + '%' : (ent ? ent.no_gst_label : 'NO GST');
        document.getElementById('t-gst').textContent = rate ? money(gst) : '-';
        document.getElementById('t-total').textContent = ok ? money(sub + gst) : '—';
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
                    + (p.billing_address ? '<div class="text-muted text-truncate">' + invEscape(p.billing_address) + '</div>' : '<div class="text-warning">No billing address</div>')
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
        if (fill) {
            fillBillTo(p);
            if (p.default_entity_code && !state.lines.length && entityById(p.default_entity_code)) {
                f.entity.value = p.default_entity_code;
                fillAddService();
            }
            const ent = entityById(f.entity.value);
            f.terms.value = p.payment_terms_days !== null && p.payment_terms_days !== undefined ? p.payment_terms_days : (ent ? ent.default_terms : 30);
            updateDue(); updateTotals(); setDirty(true);
        }
    }
    function fillBillTo(p) {
        f.bn.value = p.display_name || '';
        f.ba.value = p.billing_address || '';
        f.bat.value = p.attention || '';
        f.be.value = p.accounts_email || '';
    }
    document.getElementById('btn-refill').addEventListener('click', () => {
        if (!state.party) { invToast('Choose a party first.', true); return; }
        fillBillTo(state.party); setDirty(true);
    });

    // ------------------------------------------------------------ load / collect / save
    function applyServerPayload(d) {
        const inv = d.invoice;
        state.id = Number(inv.id);
        state.rowHash = d.row_hash;
        fillEntitySelect(inv.entity_code);
        fillCategorySelect(inv.category_id);
        fillAddService();
        f.date.value = inv.invoice_date;
        f.terms.value = inv.payment_terms_days;
        f.ref.value = inv.customer_ref || '';
        f.bn.value = inv.bill_to_name || '';
        f.ba.value = inv.bill_to_address || '';
        f.bat.value = inv.bill_to_attention || '';
        f.be.value = inv.bill_to_email || '';
        f.notes.value = inv.internal_notes || '';
        f.partyId.value = inv.party_id;
        state.lines = d.lines.map(l => ({
            service_id: l.service_id, title: l.title, description: l.description || '',
            qty: l.qty, rate: l.rate, entities: l.entities.map(e => Object.assign({}, e)),
        }));
        renderLines();
        updateDue();
        document.getElementById('t-words').textContent = d.amount_in_words || '';
        state.check = d.issue_check || null;
        state.warnings = d.warnings || [];
        renderIssuePanel();
        document.getElementById('page-title').firstChild.textContent = 'Draft invoice #' + state.id + ' ';
        document.getElementById('btn-delete').classList.remove('d-none');
        document.getElementById('btn-duplicate').classList.remove('d-none');
        if (!location.search.includes('id=')) history.replaceState(null, '', 'invoice_edit.php?id=' + state.id);
        setDirty(false);
    }

    function renderIssuePanel(extraProblems) {
        const c = state.check;
        const probs = extraProblems || (c ? c.problems : []);
        let html = '';
        if (state.id && probs.length) {
            html += '<div class="alert alert-warning small mb-2"><strong><i class="bi bi-lock"></i> Cannot be issued yet (' + probs.length + '):</strong><ul class="mb-0">'
                + probs.map(p => '<li>' + invEscape(p.message)
                    // Problems that are not about this invoice's own content are fixed in Settings.
                    + (!/^(lines|total|category_id|bill_to_|party_id|content|invoice_date|payment_terms|customer_ref|entity_code)/.test(p.field || '')
                        ? ' <a href="' + invEscape(window.EHS_BASE_URL + '/invoice-system/settings.php') + '" target="_blank" rel="noopener">Settings</a>' : '')
                    + '</li>').join('')
                + '</ul><div class="mt-1">You can keep editing and preview the draft (watermarked "DRAFT, NOT FOR ISSUE").</div></div>';
        } else if (state.id && c) {
            html += '<div class="alert alert-success small mb-2"><i class="bi bi-check-circle"></i> Ready to issue'
                + (c.next_number ? ': next number <strong class="font-monospace">' + invEscape(c.next_number) + '</strong> (final number is assigned at issue).' : '.') + '</div>';
        }
        (state.warnings || []).forEach(w => { html += '<div class="alert alert-light border small py-1 mb-1"><i class="bi bi-info-circle"></i> ' + invEscape(w) + '</div>'; });
        issuePanel.innerHTML = html;
    }

    function collect() {
        return {
            entity_code: f.entity.value, party_id: f.partyId.value ? Number(f.partyId.value) : null,
            category_id: f.category.value ? Number(f.category.value) : null,
            invoice_date: f.date.value, payment_terms_days: f.terms.value === '' ? null : f.terms.value,
            customer_ref: f.ref.value, bill_to_name: f.bn.value, bill_to_address: f.ba.value,
            bill_to_attention: f.bat.value, bill_to_email: f.be.value, internal_notes: f.notes.value,
            lines: state.lines.map(l => ({
                service_id: l.service_id, title: l.title, description: l.description,
                qty: qtyIsAuto(l) ? String(l.entities.length) : l.qty, rate: l.rate,
                entities: l.entities.filter(e => String(e.company_name || '').trim() !== ''),
            })),
        };
    }

    function showErrors(fields) {
        invShowFieldErrors(form, fields);
        const generic = [];
        Object.entries(fields || {}).forEach(([k, msg]) => {
            const m = /^lines\.(\d+)\.(\w+)$/.exec(k);
            if (m) {
                const box = linesEl.querySelector('.inv-line[data-i="' + m[1] + '"]');
                const input = box && box.querySelector('.ln-' + (m[2] === 'service_id' ? 'service' : m[2]));
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
                d = await invFetch(INV_API + '/invoices.php', { method: 'PUT', body: payload });
            } else {
                payload.action = 'create';
                d = await invFetch(INV_API + '/invoices.php', { method: 'POST', body: payload });
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
        window.open(INV_API + '/invoice_pdf.php?inline=1&id=' + encodeURIComponent(state.id), '_blank', 'noopener');
    });

    document.getElementById('btn-issue').addEventListener('click', async () => {
        if (!state.id || state.dirty) { if (!(await save())) return; }
        const c = state.check;
        if (!c || c.problems.length) {
            invToast('This draft cannot be issued yet: see the list above.', true);
            issuePanel.scrollIntoView({ behavior: 'smooth' });
            return;
        }
        const ent = entityById(f.entity.value);
        document.getElementById('issue-summary').innerHTML =
            '<p>Issue this invoice for <strong>' + invEscape(f.bn.value) + '</strong>?</p>'
            + '<table class="table table-sm mb-2"><tr><td>Issuing entity</td><td>' + invEscape(ent ? ent.name : f.entity.value) + '</td></tr>'
            + '<tr><td>Number (expected)</td><td class="font-monospace">' + invEscape(c.next_number || '—') + '</td></tr>'
            + '<tr><td>Total</td><td>' + invEscape(document.getElementById('t-total').textContent) + '</td></tr>'
            + '<tr><td>Due date</td><td>' + invEscape(f.due.value) + '</td></tr></table>'
            + '<div class="alert alert-warning py-1 mb-0"><i class="bi bi-exclamation-triangle"></i> After issuing, the invoice <strong>cannot be edited</strong>. Corrections need a credit note or a void (Stage 4).</div>';
        issueModal.show();
    });

    document.getElementById('btn-issue-confirm').addEventListener('click', async () => {
        const btn = document.getElementById('btn-issue-confirm');
        btn.disabled = true;
        try {
            const r = await invFetch(INV_API + '/invoice_issue.php', { method: 'POST', body: { id: state.id, row_hash: state.rowHash, confirm: true } });
            issueModal.hide();
            invToast('Issued as ' + r.invoice.invoice_no + '.');
            setDirty(false);
            window.location.href = window.EHS_BASE_URL + '/invoice-system/invoice_view.php?id=' + encodeURIComponent(state.id) + '&issued=1';
        } catch (e) {
            issueModal.hide();
            if (e.message === 'session_expired') return;
            if (e.data && e.data.problems && e.data.problems.length) renderIssuePanel(e.data.problems);
            invToast(e.message, true);
            reload();
        } finally {
            btn.disabled = false;
        }
    });

    document.getElementById('btn-delete').addEventListener('click', async () => {
        if (!state.id || !confirm('Delete this draft permanently? Drafts have no number, so nothing is skipped.')) return;
        try {
            await invFetch(INV_API + '/invoices.php', { method: 'DELETE', body: { id: state.id, row_hash: state.rowHash } });
            setDirty(false);
            window.location.href = window.EHS_BASE_URL + '/invoice-system/invoices.php';
        } catch (e) { if (e.message !== 'session_expired') invToast(e.message, true); }
    });

    document.getElementById('btn-duplicate').addEventListener('click', async () => {
        if (state.dirty && !confirm('Duplicate the last SAVED version? Unsaved changes are not copied.')) return;
        try {
            const d = await invFetch(INV_API + '/invoices.php', { method: 'POST', body: { action: 'duplicate', id: state.id } });
            setDirty(false);
            window.location.href = window.EHS_BASE_URL + '/invoice-system/invoice_edit.php?id=' + encodeURIComponent(d.invoice.id);
        } catch (e) { if (e.message !== 'session_expired') invToast(e.message, true); }
    });

    // ------------------------------------------------------------ wiring
    [f.date, f.terms].forEach(el => el.addEventListener('input', () => { updateDue(); setDirty(true); }));
    [f.ref, f.bn, f.ba, f.bat, f.be, f.notes, f.category].forEach(el => el.addEventListener('input', () => setDirty(true)));
    f.category.addEventListener('change', () => setDirty(true));
    f.entity.addEventListener('change', () => {
        fillAddService();
        renderLines();
        setDirty(true);
    });
    f.date.max = boot.today;
    window.addEventListener('beforeunload', e => { if (state.dirty) { e.preventDefault(); e.returnValue = ''; } });

    async function reload() {
        try {
            const d = await invFetch(INV_API + '/invoices.php?id=' + encodeURIComponent(state.id));
            if (d.invoice.status !== 'draft') {
                window.location.href = window.EHS_BASE_URL + '/invoice-system/invoice_view.php?id=' + encodeURIComponent(state.id);
                return;
            }
            applyServerPayload(d);
            if (d.invoice.party_id) {
                const p = await invFetch(INV_API + '/parties.php?id=' + encodeURIComponent(d.invoice.party_id));
                pickParty(p.party, false);
            }
        } catch (e) { if (e.message !== 'session_expired') invToast(e.message, true); }
    }

    if (state.id) {
        reload();
    } else {
        fillEntitySelect(null);
        fillCategorySelect(null);
        fillAddService();
        f.date.value = boot.today;
        const ent = entityById(f.entity.value);
        f.terms.value = ent ? ent.default_terms : 30;
        updateDue();
        renderLines();
        renderIssuePanel();
    }
})();
