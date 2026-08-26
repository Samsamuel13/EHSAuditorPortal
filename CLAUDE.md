# EHS Universal — Auditor Portal

Plain PHP/PDO + MySQL system for EHS Universal (`ehscertification.sg`),
deployed on cPanel shared hosting in production and MAMPP locally. No
framework, no build step, no Composer autoload magic beyond PhpSpreadsheet
and Dompdf.

## Three isolated modules

1. **Scheduler** (`admin/`, `auditor/`, root `api/`, root `includes/`) —
   the original system. Auditor availability, scheduling/calendar
   (FullCalendar.js), holidays, activity log.
   Tables: `users`, `schemes`, `clients`, `availability`, `holidays`,
   `audits`, `audit_schemes`, `audit_auditors`, `auditor_schemes`,
   `personal_schedule_items`, `activity_log`.

2. **Client & Certification Management** (`client-management/`) —
   `cm_`-prefixed schema. Client directory, certification cycle tracking
   (1st Cert → Surveillance 1 → Surveillance 2 → Recertification), renewal
   dashboard, Audit Extract (last/this/next calendar month), bulk
   import/export, consultant tracking.
   Tables: `cm_clients`, `cm_scheme_types`, `cm_certifications`,
   `cm_certification_documents`, `cm_renewal_alerts`, `cm_settings`,
   `cm_activity_log`.

3. **CRM / Lead Pipeline** (`crm/`) — `crm_`-prefixed schema. Kanban board
   (Enquiry → Lead → Quotation → Negotiation → Awarded, with Lost/On Hold
   as side-exits), follow-ups, versioned quotations with PDF export,
   dashboard widgets.
   Tables: `crm_leads`, `crm_lead_stage_history`, `crm_followups`,
   `crm_quotations`, `crm_quotation_items`, `crm_activity_log`.

## Isolation rules — non-negotiable

- Each module's tables stay in their own prefix (`cm_`, `crm_`). **No
  foreign keys** point from one module's schema into another's, or into
  the scheduler's tables.
- The only permitted cross-module link is **one-way, application-level,
  no FK**: CRM lead → `cm_clients` row on Award. Store the resulting
  `cm_clients.id` as a plain `INT UNSIGNED` column
  (`crm_leads.converted_client_id`) with no constraint. Never add a
  reverse dependency — `client-management/` and the scheduler must never
  query or reference `crm_*` tables.
- Each module has its **own** helpers file (`cm_helpers.php`,
  `crm_helpers.php`) — these are deliberate copies of the same
  JSON-response/logging pattern, not shared includes. Do not make one
  module require another's helpers file.
- Each module has its **own** small utility endpoints where needed (e.g.
  `users_lookup.php` exists separately in both `client-management/api/`
  and `crm/api/`) rather than sharing one.
- **Shared, and only this**: `includes/auth.php` → `includes/db.php`
  (session/login/role system) and the `users` table. Every new page and
  API endpoint in any module must use this — no parallel auth path.
- If a new feature would blur this isolation (e.g. a query joining
  `crm_*` to scheduler tables, or a shared helper file), **stop and flag
  it before building** rather than working around it silently.

## The recurring bug to never reintroduce

**Never reuse a named placeholder (`:name`) twice in one SQL query.**
MySQL native prepared statements (`PDO::ATTR_EMULATE_PREPARES => false`,
set in `includes/db.php`) reject a repeated named placeholder outright —
this throws a `PDOException` and surfaces as a 500. It already caused a
real production incident in the Audit Extract feature (an OR-chain that
repeated `:range_start`/`:range_end` once per milestone).

This is a **recurring risk anywhere a query has repeated OR-conditions**
— filtering the same date range against multiple columns, checking the
same value against multiple tables, building per-item conditions in a
loop, etc. When that pattern comes up:
- Give each occurrence its own uniquely-suffixed placeholder
  (`:range_start_initial`, `:range_start_surveillance_1`, ...), even
  though the bound value is identical.
- Before finalizing any new query with more than one OR-condition, check
  it for duplicate placeholder names.

## Conventions

- Plain PHP with PDO, prepared statements only. Never concatenate user
  input into SQL.
- New UI is **Bootstrap 5, CDN only** (no build tools, no npm bundling for
  frontend assets) — this is the agreed direction for all new/refreshed
  UI across the portal.
- **Free/open-source only.** No paid libraries or services. Currently in
  use: PhpSpreadsheet and Dompdf (Composer), SortableJS and Chart.js
  (CDN, MIT).
- Every response involving code must update/create actual files on disk
  — never just describe changes.
- Flag security issues proactively as they come up: SQL injection,
  missing auth checks, unguarded file/export routes. Every page and API
  endpoint must call `ehs_require_role([...], $isApi)` — no exceptions
  for "temporary" or diagnostic files (`_diag.php`-style files are a
  known past mistake, not a pattern to repeat).
- No hard deletes on core records (clients, certifications) — status
  changes only, matching existing convention.
- New nav links: this codebase has **no shared header partial** — every
  page hardcodes its own topbar. Adding a nav link across the portal
  means editing each page's topbar individually (see file map below for
  which files currently have topbars).

## File map

```
admin/            Scheduler pages (super_admin/admin), own topbar per file
auditor/          Auditor-role pages — no CRM/Client Management access, skip nav links here
api/              Scheduler's shared API endpoints + includes/api.php helpers
includes/         auth.php, db.php — shared by every module
config.php        DB credentials, URL_BASE, APP_TIMEZONE — differs between MAMPP and cPanel
db/
  schema_production.sql       Scheduler schema
  cm_schema.sql                Client Management schema
  crm_schema.sql               CRM schema
vendor/           Composer: PhpSpreadsheet, Dompdf

client-management/
  index.php, client_detail.php, renewal_dashboard.php,
  audit_extract.php, import.php    Pages (own topbar each)
  includes/cm_helpers.php
  api/*.php                         clients, certifications, documents, renewal_dashboard,
                                     audit_extract, export_xlsx, export_client_pdf, import, users_lookup
  assets/css/cm.css, assets/js/*.js
  cron/send_renewal_reminders.php
  storage/certification_docs/       .htaccess-blocked upload storage

crm/
  index.php (Kanban board), list.php, lead_detail.php   Pages (own topbar each)
  includes/crm_helpers.php
  api/*.php                         leads, followups, quotations, quotation_pdf, dashboard, users_lookup
  assets/css/crm.css, assets/js/*.js
  cron/send_followup_reminders.php
```

## Known open items / context

- **`cm_clients.consultant` column is NULL for most rows** — the original
  bulk import left consultant names as free text inside `notes` (e.g.
  `"Consultant: ZACK"`, sometimes `"Consultant: RUTHU/ZACK"` or with
  `| Source Client Status: ...` junk appended). ~58% of clients affected.
  Needs a one-time backfill script (dry-run report first, then write) —
  not yet built. This affects any feature that displays `consultant`
  (Audit Extract, Renewal Dashboard).
- Local dev is MAMPP (`http://localhost:8888/auditor_portal/...`);
  production is cPanel shared hosting at `ehscertification.sg`. Watch for
  environment-specific gotchas already hit in production: missing
  `ZipArchive`, `.htaccess` quirks, stale JS caching, file permissions.
- `admin/clients.php` (old scheduler "Clients" page — bare name+notes
  lookup used to tag audits) is intentionally separate from
  `client-management/` and not linked to it. Don't merge these or assume
  they share data.