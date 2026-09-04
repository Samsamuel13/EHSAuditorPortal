/**
 * client-management/assets/js/cm_milestone_badges.js
 *
 * Overlays a "✅ Done" badge next to Surveillance 1 / Surveillance 2 /
 * Recertification dates in the Certifications table on client_detail.php
 * — WITHOUT modifying cm_certifications.js (that file renders the table;
 * this script only adds to what's already on the page after the fact).
 *
 * Activity logging itself stays on the toolbar "Log Activity" button
 * (with its Certification picker dropdown) — this script only adds the
 * visual done-status badges, nothing interactive.
 *
 * HOW IT WORKS: fetches this client's certifications (same endpoint the
 * Log Activity picker uses), matches each one to its <tr> in #certs-body
 * by certificate number (falling back to scheme name if cert # is blank),
 * then appends a checkmark span into specific <td> cells BY COLUMN
 * POSITION — this assumes the column order is:
 *   0 Scheme | 1 Cert # | 2 Accreditation Body | 3 1st Cert
 *   | 4 Surv 1 | 5 Surv 2 | 6 Recert. | 7 Next Due | 8 Status
 * (matching the table as shown in your screenshot). If cm_certifications.js
 * ever changes the column order, update COLUMN_INDEX below to match —
 * that's the one thing this script can't detect on its own.
 *
 * THIS IS A WORKAROUND, NOT THE PROPER FIX. Text-matching rows and
 * hardcoding column positions is inherently fragile — it'll silently stop
 * working (badges just won't appear, nothing will break) if the table
 * markup changes. The correct long-term fix is adding this directly inside
 * cm_certifications.js's own row-rendering code, which needs that file.
 */
(function () {
    const COLUMN_INDEX = { surveillance_1: 4, surveillance_2: 5, recertification: 6 };
    const API_BASE = window.EHS_BASE_URL + '/client-management/api';

    function badge() {
        const span = document.createElement('span');
        span.textContent = ' ✅';
        span.title = 'Marked complete';
        span.style.color = '#16a34a';
        span.style.fontWeight = '600';
        span.className = 'cm-milestone-done-badge';
        return span;
    }

    function normalize(s) {
        return (s || '').trim().toLowerCase();
    }

    function applyBadges() {
        const clientId = window.CM_CLIENT_ID;
        if (!clientId) return;

        fetch(`${API_BASE}/certifications.php?client_id=${clientId}`)
            .then(r => r.json())
            .then(data => {
                const certs = data.certifications || [];
                const rows = document.querySelectorAll('#certs-body tr');

                rows.forEach(row => {
                    // Remove any badge from a previous run before re-checking,
                    // so this stays correct if the table re-renders.
                    row.querySelectorAll('.cm-milestone-done-badge').forEach(el => el.remove());

                    const cells = row.querySelectorAll('td');
                    if (!cells.length) return; // "No certifications on file" row, etc.

                    const rowCertNumber = normalize(cells[1] && cells[1].textContent);
                    const rowScheme = normalize(cells[0] && cells[0].textContent);

                    const match = certs.find(c => {
                        const certNum = normalize(c.certificate_number);
                        if (certNum && certNum !== '—') return certNum === rowCertNumber;
                        return normalize(c.scheme_name) && rowScheme.includes(normalize(c.scheme_name));
                    });
                    if (!match) return;

                    if (match.surveillance_1_completed_at && cells[COLUMN_INDEX.surveillance_1]) {
                        cells[COLUMN_INDEX.surveillance_1].appendChild(badge());
                    }
                    if (match.surveillance_2_completed_at && cells[COLUMN_INDEX.surveillance_2]) {
                        cells[COLUMN_INDEX.surveillance_2].appendChild(badge());
                    }
                    if (match.recertification_completed_at && cells[COLUMN_INDEX.recertification]) {
                        cells[COLUMN_INDEX.recertification].appendChild(badge());
                    }
                });
            })
            .catch(() => { /* silent — badges are a nice-to-have overlay, not core functionality */ });
    }

    // The certifications table is rendered asynchronously by
    // cm_certifications.js after its own fetch completes, so badge
    // placement has to run AFTER that, and again whenever the table
    // changes (e.g. after Log Activity triggers a page reload, this whole
    // script re-runs from scratch anyway; MutationObserver covers any
    // in-place re-render that doesn't reload the page).
    document.addEventListener('DOMContentLoaded', function () {
        const target = document.getElementById('certs-body');
        if (!target) return;

        setTimeout(applyBadges, 400); // give the first fetch+render a moment to finish
        new MutationObserver(() => {
            clearTimeout(applyBadges._t);
            applyBadges._t = setTimeout(applyBadges, 150);
        }).observe(target, { childList: true, subtree: true });
    });
})();