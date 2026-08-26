<?php
// File: crm/index.php — Kanban board (primary view)
require_once __DIR__ . '/../includes/auth.php';

$user = ehs_require_role(['super_admin', 'admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars(ehs_csrf_token()) ?>">
<title>CRM — Lead Pipeline</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= ehs_url('crm/assets/css/crm.css') ?>">
</head>
<body class="bg-light">
    <header class="crm-topbar">
        <span>
            <img src="<?= ehs_url('assets/img/logo.png') ?>" alt="EHS Universal" class="crm-topbar-logo">
            <strong>CRM — Lead Pipeline</strong>
        </span>
        <span>
            <?= htmlspecialchars($user['name']) ?>
            <a href="<?= ehs_url('crm/index.php') ?>"><i class="bi bi-kanban"></i> Board</a>
            <a href="<?= ehs_url('crm/list.php') ?>"><i class="bi bi-list-ul"></i> List</a>
            <a href="<?= ehs_url('admin/index.php') ?>"><i class="bi bi-calendar3"></i> Scheduler</a>
            <a href="<?= ehs_url('client-management/index.php') ?>"><i class="bi bi-building"></i> Client Mgmt</a>
            <a href="<?= ehs_url('logout.php') ?>"><i class="bi bi-box-arrow-right"></i> Log out</a>
        </span>
    </header>

    <main class="container-fluid py-3">
        <!-- Dashboard widgets -->
        <div class="row g-3 mb-3" id="dashboard-widgets">
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small">New Enquiries This Week</div>
                        <div class="fs-3 fw-bold" id="w-new-enquiries">—</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small">Overdue Follow-ups</div>
                        <div class="fs-3 fw-bold text-danger" id="w-overdue-followups">—</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small">Quotations Awaiting Response</div>
                        <div class="fs-3 fw-bold" id="w-quotations-awaiting">—</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small">Win Rate This Month</div>
                        <div class="fs-3 fw-bold" id="w-win-rate">—</div>
                        <div class="text-muted small" id="w-win-rate-detail"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">Pipeline Board</h5>
            <button class="btn btn-primary btn-sm" id="btn-new-lead" data-bs-toggle="modal" data-bs-target="#modal-new-lead">
                <i class="bi bi-plus-lg"></i> New Lead
            </button>
        </div>

        <div class="crm-board" id="board"></div>
    </main>

    <!-- New Lead modal -->
    <div class="modal fade" id="modal-new-lead" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">New Lead</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div id="dup-warning" class="alert alert-warning d-none"></div>
            <div class="mb-2"><label class="form-label">Company Name *</label><input type="text" class="form-control" id="nl-company"></div>
            <div class="mb-2"><label class="form-label">Contact Person</label><input type="text" class="form-control" id="nl-contact"></div>
            <div class="row">
              <div class="col mb-2"><label class="form-label">Phone</label><input type="text" class="form-control" id="nl-phone"></div>
              <div class="col mb-2"><label class="form-label">Email</label><input type="email" class="form-control" id="nl-email"></div>
            </div>
            <div class="row">
              <div class="col mb-2"><label class="form-label">Source</label>
                <select class="form-select" id="nl-source">
                  <option value="whatsapp">WhatsApp</option>
                  <option value="referral">Referral</option>
                  <option value="website">Website</option>
                  <option value="cold_call">Cold Call</option>
                  <option value="exhibition">Exhibition</option>
                  <option value="other" selected>Other</option>
                </select>
              </div>
              <div class="col mb-2"><label class="form-label">Owner</label><select class="form-select" id="nl-owner"><option value="">Unassigned</option></select></div>
            </div>
            <div class="mb-2"><label class="form-label">Industry Sector</label><input type="text" class="form-control" id="nl-industry"></div>
            <div class="mb-2"><label class="form-label">Notes</label><textarea class="form-control" id="nl-notes" rows="2"></textarea></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="btn-save-lead">Create Lead</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Stage-change reason modal (Lost / On Hold) -->
    <div class="modal fade" id="modal-stage-reason" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="reason-modal-title">Reason required</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <label class="form-label" id="reason-modal-label">Reason</label>
            <textarea class="form-control" id="reason-text" rows="3"></textarea>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="btn-reason-cancel">Cancel</button>
            <button type="button" class="btn btn-primary" id="btn-reason-confirm">Confirm</button>
          </div>
        </div>
      </div>
    </div>

    <div id="crm-toast" class="toast align-items-center text-white bg-dark border-0 position-fixed bottom-0 end-0 m-3" role="alert" style="z-index:1080;">
        <div class="d-flex">
            <div class="toast-body" id="crm-toast-body"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>

    <script>
        window.EHS_BASE_URL = "<?= addslashes(ehs_url()) ?>";
        window.CRM_CSRF_TOKEN = "<?= addslashes(ehs_csrf_token()) ?>";
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script src="<?= ehs_url('crm/assets/js/crm_common.js') ?>"></script>
    <script src="<?= ehs_url('crm/assets/js/crm_kanban.js') ?>"></script>
</body>
</html>
