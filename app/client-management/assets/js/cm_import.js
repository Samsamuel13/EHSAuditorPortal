document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const API_BASE = window.EHS_BASE_URL + '/client-management/api';

    let currentToken = null;

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

    const statusBadgeClass = { valid: 'cm-badge-active', info: 'cm-badge-suspended', error: 'cm-badge-blacklisted' };
    const statusLabel = { valid: 'Valid', info: 'Info', error: 'Error' };

    document.getElementById('preview-btn').addEventListener('click', function () {
        const fileInput = document.getElementById('import-file');
        if (!fileInput.files.length) { showToast('Choose a file first.', true); return; }

        const formData = new FormData();
        formData.append('file', fileInput.files[0]);

        this.disabled = true;
        fetch(`${API_BASE}/import.php?action=preview`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            body: formData,
        })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                this.disabled = false;
                if (!ok) { showToast(body.error || 'Could not preview the file.', true); return; }
                currentToken = body.token;
                renderPreview(body);
            })
            .catch(() => { this.disabled = false; showToast('Network error — please try again.', true); });
    });

    function renderPreview(data) {
        const { rows, summary } = data;

        const summaryBox = document.getElementById('summary-box');
        summaryBox.classList.remove('hidden');
        summaryBox.innerHTML = `
            <strong>${summary.total}</strong> rows read —
            <span style="color:#1e7e34;">${summary.valid} valid</span>,
            <span style="color:#b45309;">${summary.info} valid with notes</span>,
            <span style="color:#b91c1c;">${summary.error} errors (will be skipped)</span>.
        `;

        document.getElementById('preview-table-wrap').classList.remove('hidden');
        const body = document.getElementById('preview-body');
        body.innerHTML = rows.map(r => `
            <tr>
                <td>${r.row_num}</td>
                <td><span class="badge ${statusBadgeClass[r.status]}">${statusLabel[r.status]}</span></td>
                <td>${escapeHtml(r.data.company_name || '—')}</td>
                <td>${escapeHtml(r.data.scheme_type_name || '—')}</td>
                <td>${escapeHtml(r.data.certificate_number || '—')}</td>
                <td style="font-size:0.8rem; color:var(--muted);">${r.messages.map(escapeHtml).join('<br>') || '—'}</td>
            </tr>
        `).join('');

        const importable = summary.valid + summary.info;
        document.getElementById('commit-toolbar').classList.remove('hidden');
        document.getElementById('commit-hint').textContent =
            importable > 0
                ? `${importable} row(s) will be imported; ${summary.error} error row(s) will be skipped.`
                : 'No importable rows — fix the errors above and re-upload.';
        document.getElementById('commit-btn').disabled = importable === 0;

        document.getElementById('commit-result-box').classList.add('hidden');
    }

    document.getElementById('commit-btn').addEventListener('click', function () {
        if (!currentToken) return;
        if (!confirm('Import the valid rows now? This will create new client and certification records.')) return;

        this.disabled = true;
        fetch(`${API_BASE}/import.php?action=commit`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ token: currentToken }),
        })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                this.disabled = false;
                if (!ok) { showToast(body.error || 'Commit failed.', true); return; }
                showToast('Import complete.');
                const box = document.getElementById('commit-result-box');
                box.classList.remove('hidden');
                box.innerHTML = `
                    <strong>Import complete:</strong>
                    ${body.created_clients} new client(s) created,
                    ${body.matched_existing_clients} matched to existing clients,
                    ${body.created_certifications} certification(s) created,
                    ${body.skipped} row(s) skipped.
                    <br><a href="${window.EHS_BASE_URL}/client-management/index.php">Go to Client Directory →</a>
                `;
                currentToken = null;
                document.getElementById('commit-toolbar').classList.add('hidden');
            })
            .catch(() => { this.disabled = false; showToast('Network error — please try again.', true); });
    });
});
