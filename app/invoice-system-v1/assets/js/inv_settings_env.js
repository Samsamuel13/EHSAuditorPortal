// invoice-system/assets/js/inv_settings_env.js — Settings page: module options + read-only environment check.
'use strict';

(function () {
    const body = document.getElementById('env-body');
    const paper = document.getElementById('opt-paper');
    const form = document.getElementById('options-form');

    if (paper && paper.dataset.current) paper.value = paper.dataset.current;

    function badge(ok, yes, no) {
        return ok ? '<span class="badge text-bg-success">' + invEscape(yes) + '</span>'
                  : '<span class="badge text-bg-danger">' + invEscape(no) + '</span>';
    }

    async function load() {
        body.innerHTML = '<span class="text-muted">Checking…</span>';
        try {
            const d = await invFetch(INV_API + '/env_check.php');
            const rows = [
                ['Database server', invEscape(d.server.type + ' ' + d.server.version)],
                ['CHECK constraints', badge(d.server.check_constraints_enforced, 'enforced', 'not enforced (PHP still enforces every rule)')],
                ['Strict SQL mode', badge(d.server.strict, 'on', 'off')],
                ['Time zones', 'PHP ' + invEscape(d.server.php_time_zone) + ' · DB session ' + invEscape(d.server.db_time_zone)],
                ['inv_ collation', '<code>' + invEscape(d.collation.module || '—') + '</code> ' + badge(d.collation.odd.length === 0, 'consistent', d.collation.odd.length + ' differ')],
                ['Other modules', d.collation.reference.map(r => invEscape(r.TABLE_NAME) + ' <code>' + invEscape(r.TABLE_COLLATION) + '</code>').join(' · ') || '—'],
                ['Migration 003', badge(d.migration_003, 'applied', 'not applied')],
                ['PDF', 'Dompdf ' + invEscape(d.pdf.dompdf || 'missing') + ' · GD ' + (d.pdf.gd ? 'yes' : 'no') + ' · paper ' + invEscape(d.pdf.page_size || '—')],
                ['PDF storage', badge(d.storage.ok, 'ready', 'problem') + ' <code class="small">' + invEscape(d.storage.path) + '</code>'
                    + (d.storage.in_web_root ? ' <span class="text-muted">(inside web root, deny-all .htaccess)</span>' : ' <span class="text-muted">(outside web root)</span>')],
            ];
            let html = '<table class="table table-sm mb-2"><tbody>'
                + rows.map(r => '<tr><th class="fw-normal text-muted" style="width:32%">' + r[0] + '</th><td>' + r[1] + '</td></tr>').join('')
                + '</tbody></table>';
            html += '<details class="mb-2"><summary>inv_ tables (' + d.collation.tables.length + ')</summary><table class="table table-sm small mt-1"><thead><tr><th>Table</th><th>Engine</th><th>Collation</th></tr></thead><tbody>'
                + d.collation.tables.map(t => '<tr><td>' + invEscape(t.TABLE_NAME) + '</td><td>' + invEscape(t.ENGINE) + '</td><td><code>' + invEscape(t.TABLE_COLLATION) + '</code></td></tr>').join('')
                + '</tbody></table></details>';
            (d.warnings || []).forEach(w => { html += '<div class="alert alert-warning py-1 px-2 mb-1"><i class="bi bi-exclamation-triangle"></i> ' + invEscape(w) + '</div>'; });
            (d.info || []).forEach(w => { html += '<div class="alert alert-light border py-1 px-2 mb-1"><i class="bi bi-info-circle"></i> ' + invEscape(w) + '</div>'; });
            if (!(d.warnings || []).length) html += '<div class="text-success"><i class="bi bi-check-circle"></i> No problems found.</div>';
            body.innerHTML = html;
        } catch (e) {
            if (e.message !== 'session_expired') body.innerHTML = '<div class="alert alert-danger py-1">' + invEscape(e.message) + '</div>';
        }
    }

    document.getElementById('env-refresh').addEventListener('click', load);

    form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        invClearFieldErrors(form);
        try {
            const r = await invFetch(INV_API + '/config.php', { method: 'PUT', body: { pdf_page_size: paper.value } });
            paper.dataset.current = r.pdf_page_size;
            invToast('Paper size saved: ' + r.pdf_page_size + '.');
            load();
        } catch (e) {
            if (e.message === 'session_expired') return;
            if (e.data && e.data.fields) invShowFieldErrors(form, e.data.fields);
            invToast(e.message, true);
        }
    });

    load();
})();
