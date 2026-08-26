# CRM / Lead Pipeline Module — EHS Universal

A new, isolated module for the existing Auditor Portal. Tracks internal
leads/quotations from first enquiry through to Awarded (or Lost/On Hold),
completely independently of the scheduler and of the Client & Certification
Management module.

## Install (fresh install of this module)

1. Run `db/crm_schema.sql` against your existing `ehsuser` database (after
   `schema_production.sql` and `cm_schema.sql`, since the conversion feature
   reads/writes `cm_clients` — see "What's isolated vs. shared" below).
2. Copy the `crm/` folder into your project root, as a sibling of `admin/`,
   `api/`, `auditor/`, `client-management/`.
3. `admin/index.php` has one new line added — a "CRM" link in the topbar,
   right after "Client Management" (see "Existing files touched" below).
4. No file storage folder is needed for this module (quotations are
   generated as PDFs on the fly via Dompdf, not stored on disk).
5. Set up the follow-up reminder cron (optional but recommended):
   ```
   0 8 * * * php /path/to/auditor_portal/crm/cron/send_followup_reminders.php >> /path/to/logs/crm_followup_cron.log 2>&1
   ```

## What's isolated vs. shared

**New tables only**, `crm_`-prefixed: `crm_leads`, `crm_lead_stage_history`,
`crm_followups`, `crm_quotations`, `crm_quotation_items`, `crm_activity_log`.
No foreign keys point into the scheduler's tables or into
client-management's `cm_*` tables.

**The one permitted cross-module interaction**: when a lead's stage is set
to `awarded`, `crm_convert_lead_to_client()` runs a plain PHP `INSERT` into
`cm_clients` and stores the resulting ID in `crm_leads.converted_client_id`
— a plain `INT UNSIGNED` column with **no FK constraint**, the same
non-FK cross-schema pattern already used elsewhere in this codebase. This
is one-way only: `client-management/` and the scheduler never query or
reference anything in `crm_*` tables. The duplicate-lead check also reads
(never writes) `cm_clients` by email/phone/company — the only other
permitted touch on that schema.

**Shared**: only the session/login system (`includes/auth.php` ->
`includes/db.php`) and the existing `users` table, exactly as
client-management does it. This module has its own JSON-response/logging
helpers (`crm/includes/crm_helpers.php`) rather than reusing
`cm_helpers.php` or the scheduler's `includes/api.php`, and its own
`users_lookup.php` rather than reusing either existing copy.

**Third-party libraries** (Dompdf, PhpSpreadsheet) are reused via the
existing Composer install — a shared open-source dependency, not shared
application code. New CDN-only dependencies specific to this module:
Bootstrap 5, Bootstrap Icons, SortableJS (Kanban drag-and-drop), Chart.js
is available for future dashboard charts but the current widgets are plain
counters (no chart needed yet).

## Existing files touched

- `admin/index.php` — one new `<a>` link ("CRM") added to the topbar,
  immediately after the existing "Client Management" link. No other file
  was modified.

## Module file map

```
crm/
  index.php                        Kanban board (primary view) + dashboard widgets
  list.php                         Sortable/filterable list view
  lead_detail.php                  Timeline (stage history + follow-ups + quotations) + inline actions
  includes/crm_helpers.php         Module-local JSON/logging/duplicate-check/conversion helpers
  api/
    leads.php                     Lead CRUD, stage change (+ required reason for Lost/On Hold,
                                   + auto-conversion to cm_clients on Awarded), list filters
    followups.php                 Follow-up CRUD, overdue list, mark-done
    quotations.php                Versioned quotation CRUD (new version, never an overwrite) + items
    quotation_pdf.php              One quotation version as PDF (Dompdf)
    dashboard.php                  Widget counts (new enquiries, overdue follow-ups, quotations
                                   awaiting response, win rate this month, stage counts)
    users_lookup.php               id+name lookup (owner dropdowns)
  assets/css/crm.css                Additive styles on top of Bootstrap 5 (CDN)
  assets/js/
    crm_common.js                 Shared fetch wrapper (CSRF header, 401 handling), toast, formatters
    crm_kanban.js                 Board rendering, SortableJS drag/drop, New Lead modal + dup check
    crm_list.js                   Filtering + client-side sort for the list view
    crm_lead_detail.js            Timeline merge, stage change, follow-up/quotation inline actions
  cron/send_followup_reminders.php  Daily reminder email for due/overdue follow-ups

db/
  crm_schema.sql                   Full module schema (fresh installs)
```

## Permissions

Matches the existing brief: `super_admin` and `admin` have full access to
every page and endpoint in this module (`ehs_require_role(['super_admin',
'admin'], ...)` on every single one, no exceptions for diagnostic/temporary
files). `auditor` gets no access — this is an internal sales/ops tool, not
part of the auditor-facing workflow.

## Notable design decisions

- **Duplicate-lead check is advisory, not a block.** `crm_find_possible_duplicates()`
  matches on normalized email/phone/company across both `crm_leads` and
  `cm_clients`, and the New Lead form surfaces any matches with a
  "Create Anyway" confirmation step — it never silently prevents a new lead.
- **Award conversion is guarded against double-conversion** with a
  `SELECT ... FOR UPDATE` inside a transaction before the `cm_clients`
  insert, so two near-simultaneous stage-change requests can't create two
  client rows for the same lead.
- **Quotations are append-only by version.** There is no "edit" endpoint for
  an existing quotation's line items — only a status transition (draft ->
  sent -> accepted/rejected/expired). A re-negotiated price is always a new
  version, so the full negotiation history stays intact and auditable.
- **Every multi-condition query was written with the Audit Extract bug in
  mind**: no named placeholder (`:name`) is ever reused twice in the same
  query. Where the same value is needed in more than one place (e.g. the
  cron's `reminder_sent_at` dedupe check), each occurrence gets its own
  uniquely-suffixed placeholder.
