// File: assets/js/session_guard.js
/**
 * Wraps window.fetch once, globally, so that if any API call comes back with
 * our 401 "session_expired" shape, the user is bounced to the login page
 * instead of every page's JS having to special-case it individually.
 */
(function () {
    const originalFetch = window.fetch;
    let redirecting = false;

    window.fetch = function (...args) {
        return originalFetch.apply(this, args).then(response => {
            if (response.status === 401 && !redirecting) {
                const clone = response.clone();
                clone.json().then(body => {
                    if (body && body.session_expired && !redirecting) {
                        redirecting = true;
                        window.location.href = '/login.php';
                    }
                }).catch(() => { /* not JSON, not our session-expiry shape — ignore */ });
            }
            return response;
        });
    };
})();
