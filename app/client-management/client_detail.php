<?php
// File: client-management/client_detail.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/cm_helpers.php';

$user = ehs_require_role(['super_admin', 'admin']);

$clientId = (int) ($_GET['id'] ?? 0);
if ($clientId <= 0) {
    header('Location: ' . ehs_url('client-management/index.php'));
    exit;
}

$stmt = get_db()->prepare('SELECT * FROM cm_clients WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $clientId]);
$client = $stmt->fetch();

if (!$client) {
    header('Location: ' . ehs_url('client-management/index.php'));
    exit;
}

// Recent activity for this client only (cm_activity_log, scoped to this module).
$logStmt = get_db()->prepare(
    "SELECT l.action, l.details, l.created_at, u.name AS user_name
     FROM cm_activity_log l
     JOIN users u ON u.id = l.user_id
     WHERE l.entity_type = 'cm_client' AND l.entity_id = :id
     ORDER BY l.created_at DESC LIMIT 20"
);
$logStmt->execute(['id' => $clientId]);
$activity = $logStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars(ehs_csrf_token()) ?>">
<meta name="cm-client-id" content="<?= (int) $client['id'] ?>">
<title><?= htmlspecialchars($client['company_name']) ?> — Client Management</title>
<link rel="stylesheet" href="<?= ehs_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/calendar.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('assets/css/management.css') ?>">
<link rel="stylesheet" href="<?= ehs_url('client-management/assets/css/cm.css') ?>">
</head>
<body>
    <header class="topbar">
        <span class="topbar-brand">
            <img src="<?= ehs_url('assets/img/logo.png') ?>" alt="EHS Universal" class="topbar-logo">
            <span class="topbar-subtitle">Client Management</span>
        </span>
        <span class="topbar-user">
            <?= htmlspecialchars($user['name']) ?>
            &middot; <a href="<?= ehs_url('client-management/index.php') ?>">Client Directory</a>
            &middot; <a href="<?= ehs_url('client-management/renewal_dashboard.php') ?>">Renewal Dashboard</a>
            &middot; <a href="<?= ehs_url('client-management/audit_extract.php') ?>">Audit Extract</a>
            &middot; <a href="<?= ehs_url('client-management/import.php') ?>">Bulk Import</a>
            &middot; <a href="<?= ehs_url('crm/index.php') ?>">CRM</a>
            &middot; <a href="<?= ehs_url('admin/index.php') ?>">Scheduler Dashboard</a>
            &middot; <a href="<?= ehs_url('logout.php') ?>">Log out</a>
        </span>
    </header>

    <div class="back-nav">
        <button onclick="location.href='<?= ehs_url('client-management/index.php') ?>'" class="btn btn-ghost-light btn-small back-btn">&larr; Back to directory</button>
    </div>

    <main class="page">
        <h1><?= htmlspecialchars($client['company_name']) ?>
            <span class="badge cm-badge-<?= htmlspecialchars($client['status']) ?>" style="vertical-align:middle; margin-left:8px;">
                <?= htmlspecialchars(ucfirst($client['status'])) ?>
            </span>
        </h1>

        <div class="mgmt-toolbar">
            <span></span>
            <span>
                <a href="<?= ehs_url('client-management/api/export_client_pdf.php?id=' . (int) $client['id']) ?>" class="btn btn-ghost-light btn-small" style="width:auto; margin-right:8px; display:inline-block;">⬇ Export certification history (PDF)</a>
                <button id="edit-btn" class="btn btn-primary btn-small">Edit client info</button>
            </span>
        </div>

        <dl class="cm-detail-grid">
            <div><dt>UEN / Registration No.</dt><dd><?= htmlspecialchars($client['uen_registration_no'] ?? '—') ?></dd></div>
            <div><dt>Industry sector</dt><dd><?= htmlspecialchars($client['industry_sector'] ?? '—') ?></dd></div>
            <div><dt>Contact person</dt><dd><?= htmlspecialchars($client['contact_person'] ?? '—') ?></dd></div>
            <div><dt>Designation</dt><dd><?= htmlspecialchars($client['contact_designation'] ?? '—') ?></dd></div>
            <div><dt>Consultant</dt><dd><?= htmlspecialchars($client['consultant'] ?? '—') ?></dd></div>
            <div><dt>Phone</dt><dd><?= htmlspecialchars($client['phone'] ?? '—') ?></dd></div>
            <div><dt>Email</dt><dd><?= htmlspecialchars($client['email'] ?? '—') ?></dd></div>
            <div><dt>Website</dt><dd><?= htmlspecialchars($client['website'] ?? '—') ?></dd></div>
            <div><dt>Address</dt><dd><?= htmlspecialchars($client['address'] ?? '—') ?></dd></div>
            <div style="grid-column: 1 / -1;"><dt>Notes</dt><dd><?= nl2br(htmlspecialchars($client['notes'] ?? '—')) ?></dd></div>
        </dl>

        <div class="mgmt-toolbar" style="margin-top:24px;">
            <h2 style="font-size:1.05rem; margin:0;">Certifications</h2>
            <button id="add-cert-btn" class="btn btn-primary btn-small">+ Add certification</button>
        </div>

        <div class="mgmt-table-wrap">
            <table class="mgmt-table">
                <thead><tr>
                    <th>Scheme</th>
                    <th>Cert #</th>
                    <th>Accreditation Body</th>
                    <th>1st Cert</th>
                    <th>Surv. 1</th>
                    <th>Surv. 2</th>
                    <th>Recert.</th>
                    <th>Next Due</th>
                    <th>Status</th>
                    <th></th>
                </tr></thead>
                <tbody id="certs-body"></tbody>
            </table>
            <div id="certs-empty-state" class="mgmt-empty hidden">No certifications on file for this client yet.</div>
        </div>

        <h2 style="font-size:1.05rem; margin-top:24px;">Recent activity</h2>
        <div class="mgmt-table-wrap cm-activity-list">
            <?php if (!$activity): ?>
                <div class="mgmt-empty">No activity recorded yet.</div>
            <?php else: ?>
                <?php foreach ($activity as $entry): ?>
                    <div class="list-item">
                        <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $entry['action']))) ?></strong>
                        <span class="cm-activity-meta">
                            <?= htmlspecialchars($entry['user_name']) ?> &middot; <?= htmlspecialchars($entry['created_at']) ?>
                        </span>
                        <?php if ($entry['details']): ?><span><?= htmlspecialchars($entry['details']) ?></span><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <div id="modal-backdrop" class="modal-backdrop hidden">
        <div class="modal modal-wide">
            <h2>Edit client</h2>

            <div class="modal-row">
                <div>
                    <label for="f-company-name">Company name *</label>
                    <input type="text" id="f-company-name" maxlength="200" value="<?= htmlspecialchars($client['company_name']) ?>">
                </div>
                <div>
                    <label for="f-uen">UEN / Registration No.</label>
                    <input type="text" id="f-uen" maxlength="50" value="<?= htmlspecialchars($client['uen_registration_no'] ?? '') ?>">
                </div>
            </div>

            <div class="modal-row">
                <div>
                    <label for="f-industry">Industry sector</label>
                    <input type="text" id="f-industry" maxlength="100" value="<?= htmlspecialchars($client['industry_sector'] ?? '') ?>">
                </div>
                <div>
                    <label for="f-status">Status</label>
                    <select id="f-status">
                        <?php foreach (['active', 'suspended', 'withdrawn', 'blacklisted'] as $s): ?>
                            <option value="<?= $s ?>" <?= $client['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <label for="f-address">Address</label>
            <input type="text" id="f-address" maxlength="255" value="<?= htmlspecialchars($client['address'] ?? '') ?>">

            <div class="modal-row">
                <div>
                    <label for="f-contact-person">Contact person</label>
                    <input type="text" id="f-contact-person" maxlength="150" value="<?= htmlspecialchars($client['contact_person'] ?? '') ?>">
                </div>
                <div>
                    <label for="f-contact-designation">Designation</label>
                    <input type="text" id="f-contact-designation" maxlength="100" value="<?= htmlspecialchars($client['contact_designation'] ?? '') ?>">
                </div>
            </div>

            <div class="modal-row">
                <div>
                    <label for="f-consultant">Consultant</label>
                    <input type="text" id="f-consultant" maxlength="150" value="<?= htmlspecialchars($client['consultant'] ?? '') ?>">
                </div>
            </div>

            <div class="modal-row">
                <div>
                    <label for="f-phone">Phone</label>
                    <input type="text" id="f-phone" maxlength="30" value="<?= htmlspecialchars($client['phone'] ?? '') ?>">
                </div>
                <div>
                    <label for="f-email">Email</label>
                    <input type="email" id="f-email" maxlength="150" value="<?= htmlspecialchars($client['email'] ?? '') ?>">
                </div>
            </div>

            <label for="f-website">Website</label>
            <input type="text" id="f-website" maxlength="255" value="<?= htmlspecialchars($client['website'] ?? '') ?>">

            <label for="f-notes">Notes</label>
            <textarea id="f-notes" rows="3"><?= htmlspecialchars($client['notes'] ?? '') ?></textarea>

            <div class="modal-actions">
                <button id="modal-cancel" class="btn btn-ghost">Cancel</button>
                <button id="modal-save" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>

    <div id="cert-modal-backdrop" class="modal-backdrop hidden">
        <div class="modal modal-wide">
            <h2 id="cert-modal-title">Add certification</h2>

            <div class="modal-row">
                <div>
                    <label for="c-scheme-type">Scheme type *</label>
                    <select id="c-scheme-type"></select>
                </div>
                <div>
                    <label for="c-accreditation-body">Accreditation body</label>
                    <input type="text" id="c-accreditation-body" maxlength="100" placeholder="e.g. JAS-ANZ, SAC-SINGLAS">
                </div>
            </div>

            <div class="modal-row">
                <div>
                    <label for="c-cert-number">Certificate number</label>
                    <input type="text" id="c-cert-number" maxlength="100">
                </div>
                <div>
                    <label for="c-cycle-stage">Cycle stage</label>
                    <select id="c-cycle-stage">
                        <option value="initial">Initial</option>
                        <option value="surveillance_1">Surveillance 1</option>
                        <option value="surveillance_2">Surveillance 2</option>
                        <option value="recertification">Recertification</option>
                    </select>
                </div>
            </div>

            <div class="modal-row">
                <div>
                    <label for="c-issue-date">1st Certification</label>
                    <input type="date" id="c-issue-date">
                </div>
                <div>
                    <label for="c-surv1-date">Surveillance 1</label>
                    <input type="date" id="c-surv1-date">
                </div>
            </div>

            <div class="modal-row">
                <div>
                    <label for="c-surv2-date">Surveillance 2</label>
                    <input type="date" id="c-surv2-date">
                </div>
                <div>
                    <label for="c-expiry-date">Recertification</label>
                    <input type="date" id="c-expiry-date">
                </div>
            </div>

            <div class="modal-row">
                <div>
                    <label for="c-status">Status</label>
                    <select id="c-status">
                        <option value="pending">Pending</option>
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                        <option value="suspended">Suspended</option>
                        <option value="withdrawn">Withdrawn</option>
                    </select>
                </div>
                <div>
                    <label for="c-responsible-person">Responsible person</label>
                    <select id="c-responsible-person"><option value="">— None —</option></select>
                </div>
            </div>

            <label for="c-notes">Notes</label>
            <textarea id="c-notes" rows="2"></textarea>

            <div class="modal-actions">
                <button id="cert-modal-cancel" class="btn btn-ghost">Cancel</button>
                <button id="cert-modal-save" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>

    <div id="docs-modal-backdrop" class="modal-backdrop hidden">
        <div class="modal modal-wide">
            <h2>Documents</h2>

            <div class="mgmt-table-wrap" style="margin-bottom:16px;">
                <table class="mgmt-table">
                    <thead><tr><th>File</th><th>Type</th><th>Uploaded</th><th></th></tr></thead>
                    <tbody id="docs-body"></tbody>
                </table>
                <div id="docs-empty-state" class="mgmt-empty hidden">No documents uploaded yet.</div>
            </div>

            <label for="doc-type">Document type</label>
            <select id="doc-type">
                <option value="certificate">Certificate</option>
                <option value="audit_report">Audit report</option>
                <option value="application_form">Application form</option>
                <option value="other">Other</option>
            </select>
            <label for="doc-file">File (PDF, JPG, PNG, DOC, DOCX — 10 MB max)</label>
            <input type="file" id="doc-file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">

            <div class="modal-actions">
                <button id="docs-modal-close" class="btn btn-ghost">Close</button>
                <button id="doc-upload-btn" class="btn btn-primary">Upload</button>
            </div>
        </div>
    </div>

    <div id="confirm-backdrop" class="modal-backdrop hidden">
        <div class="modal modal-narrow">
            <h2 id="confirm-title">Confirm</h2>
            <p id="confirm-message"></p>
            <div class="modal-actions">
                <button id="confirm-cancel" class="btn btn-ghost">Cancel</button>
                <button id="confirm-ok" class="btn btn-danger">Remove</button>
            </div>
        </div>
    </div>

    <div id="toast" class="toast hidden"></div>
    <script>window.EHS_BASE_URL = "<?= addslashes(ehs_url()) ?>";</script>
    <script src="<?= ehs_url('assets/js/session_guard.js') ?>"></script>
    <script src="<?= ehs_url('client-management/assets/js/cm_client_detail.js') ?>"></script>
    <script src="<?= ehs_url('client-management/assets/js/cm_certifications.js') ?>"></script>
</body>
</html>