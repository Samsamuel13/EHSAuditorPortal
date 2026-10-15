<?php
/**
 * crm/board.php
 *
 * CRM lead pipeline board — 5 funnel-stage columns (Enquiry, Lead,
 * Quotation, Negotiation, Awarded), drag-and-drop stage changes via
 * SortableJS. Lost/On Hold are NOT columns here (2026-09-15 scope
 * decision) — they're reached via a per-card action that requires a
 * reason, and are browsable in a separate toggled list view on this same
 * page rather than a second page.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/crm_helpers.php';

$user = ehs_require_role(['super_admin', 'admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CRM Pipeline — EHS Universal</title>
<meta name="csrf-token" content="<?= htmlspecialchars(ehs_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../client-management/assets/css/bootstrap-theme.css" rel="stylesheet">
<link href="assets/css/crm.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-3">
  <div class="container-fluid">
    <span class="navbar-brand">EHS Universal — CRM Pipeline</span>
    <span class="navbar-text text-white-50">Logged in as <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></span>
  </div>
</nav>

<div class="container-fluid px-4 pb-5">

  <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <div class="btn-group" role="group" aria-label="Board view">
      <button type="button" id="view-board-btn" class="btn btn-primary btn-sm">Board</button>
      <button type="button" id="view-lost-on-hold-btn" class="btn btn-outline-secondary btn-sm">Lost &amp; On Hold</button>
    </div>

    <input type="text" id="filter-search" class="form-control form-control-sm" style="max-width:220px" placeholder="Search company/contact...">
    <select id="filter-owner" class="form-select form-select-sm" style="max-width:180px">
      <option value="">All owners</option>
    </select>
    <select id="filter-source" class="form-select form-select-sm" style="max-width:160px">
      <option value="">All sources</option>
      <option value="whatsapp">WhatsApp</option>
      <option value="referral">Referral</option>
      <option value="website">Website</option>
      <option value="cold_call">Cold call</option>
      <option value="exhibition">Exhibition</option>
      <option value="other">Other</option>
    </select>

    <button type="button" id="add-lead-btn" class="btn btn-success btn-sm ms-auto">+ Add Lead</button>
  </div>

  <!-- Board view: 5 funnel columns -->
  <div id="board-view">
    <div class="row g-3 crm-board" id="crm-board-columns">
      <!-- Columns injected by crm_board.js: one per CRM_FUNNEL_STAGES entry -->
    </div>
  </div>

  <!-- Lost & On Hold view: plain list, not draggable -->
  <div id="lost-on-hold-view" class="d-none">
    <table class="table table-sm table-hover bg-white align-middle">
      <thead>
        <tr>
          <th>Company</th>
          <th>Contact</th>
          <th>Stage</th>
          <th>Reason</th>
          <th>Owner</th>
          <th>Days in stage</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="lost-on-hold-tbody"></tbody>
    </table>
  </div>

</div>

<!-- Add Lead modal -->
<div class="modal fade" id="addLeadModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Lead</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="add-lead-form">
          <div class="mb-2">
            <label class="form-label">Company name *</label>
            <input type="text" class="form-control" name="company_name" required maxlength="200">
          </div>
          <div class="row">
            <div class="col-md-6 mb-2">
              <label class="form-label">Contact person</label>
              <input type="text" class="form-control" name="contact_person" maxlength="150">
            </div>
            <div class="col-md-6 mb-2">
              <label class="form-label">Designation</label>
              <input type="text" class="form-control" name="contact_designation" maxlength="100">
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-2">
              <label class="form-label">Phone</label>
              <input type="text" class="form-control" name="phone" maxlength="30">
            </div>
            <div class="col-md-6 mb-2">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="email" maxlength="150">
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-2">
              <label class="form-label">Industry sector</label>
              <input type="text" class="form-control" name="industry_sector" maxlength="100">
            </div>
            <div class="col-md-6 mb-2">
              <label class="form-label">Source</label>
              <select class="form-select" name="source">
                <option value="whatsapp">WhatsApp</option>
                <option value="referral">Referral</option>
                <option value="website">Website</option>
                <option value="cold_call">Cold call</option>
                <option value="exhibition">Exhibition</option>
                <option value="other" selected>Other</option>
              </select>
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label">Owner</label>
            <select class="form-select" name="owner_id" id="add-lead-owner"></select>
          </div>
          <div class="mb-2">
            <label class="form-label">Notes</label>
            <textarea class="form-control" name="notes" rows="2"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="add-lead-submit">Add Lead</button>
      </div>
    </div>
  </div>
</div>

<!-- Move to Lost / On Hold modal (reason required) -->
<div class="modal fade" id="moveReasonModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="moveReasonModalTitle">Move lead</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>A reason is required to move a lead to this stage.</p>
        <textarea class="form-control" id="move-reason-text" rows="3" placeholder="Reason..."></textarea>
        <div class="invalid-feedback d-block d-none" id="move-reason-error">A reason is required.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="move-reason-submit">Confirm move</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script src="assets/js/crm_board.js"></script>
</body>
</html>