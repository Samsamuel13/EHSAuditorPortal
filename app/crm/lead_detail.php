<?php
// File: crm/lead_detail.php?id=X
require_once __DIR__ . '/../includes/auth.php';

$user = ehs_require_role(['super_admin', 'admin']);
$leadId = (int) ($_GET['id'] ?? 0);
if ($leadId <= 0) { http_response_code(422); die('A valid lead id is required.'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars(ehs_csrf_token()) ?>">
<title>Lead Detail — CRM</title>
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
            <a href="<?= ehs_url('logout.php') ?>"><i class="bi bi-box-arrow-right"></i> Log out</a>
        </span>
    </header>

    <main class="container-fluid py-3">
        <a href="<?= ehs_url('crm/list.php') ?>" class="small"><i class="bi bi-arrow-left"></i> Back to list</a>

        <div class="row g-3 mt-1">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h5 class="card-title mb-1" id="ld-company">—</h5>
                        <div id="ld-stage-badge" class="mb-2"></div>
                        <div id="ld-converted-banner" class="alert alert-success py-1 px-2 small d-none"></div>
                        <dl class="row small mb-0">
                            <dt class="col-5">Contact</dt><dd class="col-7" id="ld-contact">—</dd>
                            <dt class="col-5">Phone</dt><dd class="col-7" id="ld-phone">—</dd>
                            <dt class="col-5">Email</dt><dd class="col-7" id="ld-email">—</dd>
                            <dt class="col-5">Industry</dt><dd class="col-7" id="ld-industry">—</dd>
                            <dt class="col-5">Source</dt><dd class="col-7" id="ld-source">—</dd>
                            <dt class="col-5">Owner</dt><dd class="col-7" id="ld-owner">—</dd>
                        </dl>
                        <button class="btn btn-outline-secondary btn-sm mt-2" id="btn-edit-lead" data-bs-toggle="modal" data-bs-target="#modal-edit-lead">Edit</button>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Change Stage</span>
                    </div>
                    <div class="card-body">
                        <select class="form-select form-select-sm" id="ld-stage-select">
                            <option value="enquiry">Enquiry</option>
                            <option value="lead">Lead</option>
                            <option value="quotation">Quotation</option>
                            <option value="negotiation">Negotiation</option>
                            <option value="awarded">Awarded</option>
                            <option value="lost">Lost</option>
                            <option value="on_hold">On Hold</option>
                        </select>
                        <button class="btn btn-primary btn-sm mt-2 w-100" id="btn-change-stage">Apply</button>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <ul class="nav nav-tabs" id="ld-tabs">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-timeline">Timeline</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-followups">Follow-ups</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-quotations">Quotations</button></li>
                </ul>
                <div class="tab-content bg-white p-3 border border-top-0">
                    <div class="tab-pane fade show active" id="tab-timeline">
                        <div id="timeline-list"></div>
                    </div>

                    <div class="tab-pane fade" id="tab-followups">
                        <div class="d-flex justify-content-end mb-2">
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modal-new-followup">
                                <i class="bi bi-plus-lg"></i> Add Follow-up
                            </button>
                        </div>
                        <table class="table table-sm">
                            <thead><tr><th>Due</th><th>Type</th><th>Owner</th><th>Note</th><th>Status</th><th></th></tr></thead>
                            <tbody id="followups-body"></tbody>
                        </table>
                    </div>

                    <div class="tab-pane fade" id="tab-quotations">
                        <div class="d-flex justify-content-end mb-2">
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modal-new-quotation">
                                <i class="bi bi-plus-lg"></i> New Quotation Version
                            </button>
                        </div>
                        <div id="quotations-list"></div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Edit lead modal -->
    <div class="modal fade" id="modal-edit-lead" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header"><h5 class="modal-title">Edit Lead</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <div class="mb-2"><label class="form-label">Company Name</label><input type="text" class="form-control" id="ed-company"></div>
            <div class="mb-2"><label class="form-label">Contact Person</label><input type="text" class="form-control" id="ed-contact"></div>
            <div class="row">
              <div class="col mb-2"><label class="form-label">Phone</label><input type="text" class="form-control" id="ed-phone"></div>
              <div class="col mb-2"><label class="form-label">Email</label><input type="email" class="form-control" id="ed-email"></div>
            </div>
            <div class="row">
              <div class="col mb-2"><label class="form-label">Source</label>
                <select class="form-select" id="ed-source">
                  <option value="whatsapp">WhatsApp</option><option value="referral">Referral</option>
                  <option value="website">Website</option><option value="cold_call">Cold Call</option>
                  <option value="exhibition">Exhibition</option><option value="other">Other</option>
                </select>
              </div>
              <div class="col mb-2"><label class="form-label">Owner</label><select class="form-select" id="ed-owner"><option value="">Unassigned</option></select></div>
            </div>
            <div class="mb-2"><label class="form-label">Industry Sector</label><input type="text" class="form-control" id="ed-industry"></div>
            <div class="mb-2"><label class="form-label">Notes</label><textarea class="form-control" id="ed-notes" rows="3"></textarea></div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" id="btn-save-edit">Save</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Reason modal -->
    <div class="modal fade" id="modal-stage-reason" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header"><h5 class="modal-title" id="reason-modal-title">Reason required</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body"><label class="form-label" id="reason-modal-label">Reason</label><textarea class="form-control" id="reason-text" rows="3"></textarea></div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" id="btn-reason-confirm">Confirm</button>
          </div>
        </div>
      </div>
    </div>

    <!-- New follow-up modal -->
    <div class="modal fade" id="modal-new-followup" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header"><h5 class="modal-title">Add Follow-up</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <div class="mb-2"><label class="form-label">Due Date</label><input type="date" class="form-control" id="fu-due"></div>
            <div class="mb-2"><label class="form-label">Type</label>
              <select class="form-select" id="fu-type">
                <option value="call">Call</option><option value="email">Email</option>
                <option value="meeting">Meeting</option><option value="whatsapp">WhatsApp</option><option value="other">Other</option>
              </select>
            </div>
            <div class="mb-2"><label class="form-label">Owner</label><select class="form-select" id="fu-owner"></select></div>
            <div class="mb-2"><label class="form-label">Note</label><textarea class="form-control" id="fu-note" rows="2"></textarea></div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" id="btn-save-followup">Add</button>
          </div>
        </div>
      </div>
    </div>

    <!-- New quotation modal -->
    <div class="modal fade" id="modal-new-quotation" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header"><h5 class="modal-title">New Quotation Version</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <div class="row">
              <div class="col mb-2"><label class="form-label">Valid Until</label><input type="date" class="form-control" id="qt-valid-until"></div>
              <div class="col mb-2"><label class="form-label">Tax %</label><input type="number" step="0.01" class="form-control" id="qt-tax" value="9"></div>
            </div>
            <table class="table table-sm" id="qt-items-table">
              <thead><tr><th>Description</th><th style="width:90px;">Qty</th><th style="width:120px;">Unit Price</th><th style="width:40px;"></th></tr></thead>
              <tbody id="qt-items-body"></tbody>
            </table>
            <button class="btn btn-sm btn-outline-secondary" id="btn-add-item">+ Add Line</button>
            <div class="mb-2 mt-3"><label class="form-label">Notes</label><textarea class="form-control" id="qt-notes" rows="2"></textarea></div>
            <div class="text-end fw-bold" id="qt-total-preview">Total: SGD 0.00</div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" id="btn-save-quotation">Save Version</button>
          </div>
        </div>
      </div>
    </div>

    <div id="crm-toast" class="toast align-items-center text-white bg-dark border-0 position-fixed bottom-0 end-0 m-3" role="alert" style="z-index:1080;">
        <div class="d-flex"><div class="toast-body" id="crm-toast-body"></div><button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>
    </div>

    <script>
        window.EHS_BASE_URL = "<?= addslashes(ehs_url()) ?>";
        window.CRM_CSRF_TOKEN = "<?= addslashes(ehs_csrf_token()) ?>";
        window.CRM_LEAD_ID = <?= (int) $leadId ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ehs_url('crm/assets/js/crm_common.js') ?>"></script>
    <script src="<?= ehs_url('crm/assets/js/crm_lead_detail.js') ?>"></script>
</body>
</html>
