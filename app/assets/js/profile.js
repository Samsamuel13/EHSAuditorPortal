// File: assets/js/profile.js
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const toastEl = document.getElementById('toast');
    const alertBox = document.getElementById('alert-box');

    let toastTimer = null;
    function showToast(message, isError = false) {
        clearTimeout(toastTimer);
        toastEl.textContent = message;
        toastEl.classList.toggle('error', isError);
        toastEl.classList.remove('hidden');
        toastTimer = setTimeout(() => toastEl.classList.add('hidden'), 3500);
    }

    function showAlert(message) {
        alertBox.textContent = message;
        alertBox.classList.remove('hidden');
    }
    function clearAlert() {
        alertBox.classList.add('hidden');
    }

    document.getElementById('save-btn').addEventListener('click', function () {
        clearAlert();
        const currentPassword = document.getElementById('current-password').value;
        const newPassword = document.getElementById('new-password').value;
        const confirmPassword = document.getElementById('confirm-password').value;

        if (!currentPassword) {
            showAlert('Please enter your current password.');
            return;
        }
        if (newPassword.length < 8) {
            showAlert('New password must be at least 8 characters.');
            return;
        }
        if (newPassword !== confirmPassword) {
            showAlert('New password and confirmation do not match.');
            return;
        }

        fetch(`${window.EHS_BASE_URL}/api/change_password.php`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ current_password: currentPassword, new_password: newPassword }),
        })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) {
                    showAlert(body.error || 'Could not update password.');
                    return;
                }
                showToast('Password updated.');
                document.getElementById('current-password').value = '';
                document.getElementById('new-password').value = '';
                document.getElementById('confirm-password').value = '';
            })
            .catch(() => showAlert('Network error — please try again.'));
    });
});
