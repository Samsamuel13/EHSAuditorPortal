document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const clientId = document.querySelector('meta[name="cm-client-id"]').content;
    const API_BASE = window.EHS_BASE_URL + '/client-management/api';

    const toastEl = document.getElementById('toast');
    let toastTimer = null;
    function showToast(message, isError = false) {
        clearTimeout(toastTimer);
        toastEl.textContent = message;
        toastEl.classList.toggle('error', isError);
        toastEl.classList.remove('hidden');
        toastTimer = setTimeout(() => toastEl.classList.add('hidden'), 3500);
    }

    function csrfHeaders() {
        return { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken };
    }

    const modalBackdrop = document.getElementById('modal-backdrop');
    document.getElementById('edit-btn').addEventListener('click', () => modalBackdrop.classList.remove('hidden'));
    document.getElementById('modal-cancel').addEventListener('click', () => modalBackdrop.classList.add('hidden'));

    document.getElementById('modal-save').addEventListener('click', function () {
        const companyName = document.getElementById('f-company-name').value.trim();
        if (!companyName) { showToast('Company name is required.', true); return; }

        const payload = {
            company_name: companyName,
            uen_registration_no: document.getElementById('f-uen').value.trim(),
            industry_sector: document.getElementById('f-industry').value.trim(),
            address: document.getElementById('f-address').value.trim(),
            contact_person: document.getElementById('f-contact-person').value.trim(),
            contact_designation: document.getElementById('f-contact-designation').value.trim(),
            phone: document.getElementById('f-phone').value.trim(),
            email: document.getElementById('f-email').value.trim(),
            website: document.getElementById('f-website').value.trim(),
            status: document.getElementById('f-status').value,
            notes: document.getElementById('f-notes').value.trim(),
        };

        fetch(`${API_BASE}/clients.php?id=${clientId}`, { method: 'PUT', headers: csrfHeaders(), body: JSON.stringify(payload) })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) { showToast(body.error || 'Could not save client.', true); return; }
                showToast('Client updated.');
                setTimeout(() => location.reload(), 600);
            })
            .catch(() => showToast('Network error — please try again.', true));
    });
});
