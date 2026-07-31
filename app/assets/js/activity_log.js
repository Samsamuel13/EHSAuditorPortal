// File: assets/js/activity_log.js
document.addEventListener('DOMContentLoaded', function () {
    let page = 1;
    let totalPages = 1;
    let usersCache = [];

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : str;
        return div.innerHTML;
    }

    const filterUser = document.getElementById('filter-user');
    const filterAction = document.getElementById('filter-action');
    const filterEntity = document.getElementById('filter-entity');
    const filterStart = document.getElementById('filter-start');
    const filterEnd = document.getElementById('filter-end');

    // Populate the user dropdown once, from the users API (already restricted
    // to super admins, same as this page).
    fetch(window.EHS_BASE_URL + '/api/users.php')
        .then(r => r.json())
        .then(data => {
            usersCache = data.users || [];
            filterUser.innerHTML = '<option value="">All users</option>' +
                usersCache.map(u => `<option value="${u.id}">${escapeHtml(u.name)}</option>`).join('');
        });

    [filterUser, filterAction, filterEntity, filterStart, filterEnd].forEach(el =>
        el.addEventListener('change', () => { page = 1; load(); }));

    document.getElementById('filter-clear-btn').addEventListener('click', function () {
        filterUser.value = '';
        filterAction.value = '';
        filterEntity.value = '';
        filterStart.value = '';
        filterEnd.value = '';
        page = 1;
        load();
    });

    document.getElementById('prev-page-btn').addEventListener('click', () => {
        if (page > 1) { page--; load(); }
    });
    document.getElementById('next-page-btn').addEventListener('click', () => {
        if (page < totalPages) { page++; load(); }
    });

    function load() {
        const params = new URLSearchParams({ page: page, per_page: 50 });
        if (filterUser.value) params.set('user_id', filterUser.value);
        if (filterAction.value) params.set('action', filterAction.value);
        if (filterEntity.value) params.set('entity_type', filterEntity.value);
        if (filterStart.value) params.set('start', filterStart.value);
        if (filterEnd.value) params.set('end', filterEnd.value);

        fetch(window.EHS_BASE_URL + '/api/activity_log.php?' + params.toString())
            .then(r => r.json())
            .then(data => {
                render(data.entries || []);
                totalPages = Math.max(1, Math.ceil(data.total / data.per_page));
                document.getElementById('page-info').textContent = `Page ${data.page} of ${totalPages} (${data.total} total)`;
                document.getElementById('prev-page-btn').disabled = data.page <= 1;
                document.getElementById('next-page-btn').disabled = data.page >= totalPages;

                // Populate action/entity filter options once we know what exists.
                if (filterAction.children.length <= 1) {
                    filterAction.innerHTML = '<option value="">All actions</option>' +
                        (data.actions || []).map(a => `<option value="${escapeHtml(a)}">${escapeHtml(a)}</option>`).join('');
                }
                if (filterEntity.children.length <= 1) {
                    filterEntity.innerHTML = '<option value="">All entity types</option>' +
                        (data.entity_types || []).map(e => `<option value="${escapeHtml(e)}">${escapeHtml(e)}</option>`).join('');
                }
            });
    }

    function render(entries) {
        const body = document.getElementById('log-body');
        document.getElementById('empty-state').classList.toggle('hidden', entries.length > 0);
        body.innerHTML = entries.map(e => `
            <tr>
                <td>${escapeHtml(e.created_at)}</td>
                <td>${escapeHtml(e.user_name || 'Unknown')}</td>
                <td>${escapeHtml(e.action)}</td>
                <td>${escapeHtml(e.entity_type)}${e.entity_id ? ' #' + e.entity_id : ''}</td>
                <td>${escapeHtml(e.details)}</td>
            </tr>
        `).join('');
    }

    load();
});
