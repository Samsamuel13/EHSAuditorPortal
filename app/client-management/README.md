# Client & Certification Management Module — EHS Universal

A new, isolated module for the existing Auditor Scheduling System's admin portal.
Tracks client companies and their certifications (ISO, BizSafe, JAS-ANZ, etc.) —
renewals, documents, status — completely independently of the scheduling system.

## Install (fresh install of this module)

1. Run `db/cm_schema.sql` against your existing `ehsuser` database (after
   `schema_production.sql`, since it references the shared `users` table).
2. Run `db/cm_seed_scheme_types.sql` to seed ISO 9001/14001/45001/27001 and
   BizSafe Star/Level 3/Level 4.
3. Copy the `client-management/` folder into your project root, as a sibling
   of `admin/`, `api/`, `auditor/`.
4. `admin/index.php` has one new line added — a "Client Management" link in
   the topbar (see "Existing files touched" below). If you've since modified
   your own copy of that file, re-apply just that one `<a>` line by hand
   instead of overwriting the whole file.
5. Confirm `client-management/storage/` is writable by PHP (it's created
   automatically on first document upload, with a `.htaccess` blocking direct
   web access to anything inside it).

## Already had `cm_schema.sql` installed from an earlier step?

Run `db/cm_migration_002_settings.sql` instead of re-running the full schema —
it only adds the one new table (`cm_settings`) needed for the renewal
dashboard's configurable thresholds.

## What's isolated vs. shared

**New tables only**, `cm_`-prefixed: `cm_clients`, `cm_scheme_types`,
`cm_certifications`, `cm_certification_documents`, `cm_renewal_alerts`,
`cm_settings`, `cm_activity_log`. No foreign keys point into or out of the
scheduling system's tables (`clients`, `schemes`, `audits`, `audit_schemes`,
`audit_auditors`, `auditor_schemes`, `availability`, `holidays`,
`activity_log`, `personal_schedule_items`).

**Shared**: only the session/login system (`includes/auth.php` →
`includes/db.php`) and the existing `users` table, exactly as scoped in the
original brief. This module has its own JSON-response/activity-log helpers
(`client-management/includes/cm_helpers.php`) rather than reusing the
scheduling system's `includes/api.php`, and its own tiny `users_lookup.php`
rather than reusing `api/users.php`.

**Third-party libraries** (PhpSpreadsheet, Dompdf) are reused via Composer —
that's a shared open-source dependency, not shared application code, so it
doesn't conflict with the isolation requirement. The scheduling system's own
export features already depend on both.

## Existing files touched

- `admin/index.php` — one new `<a>` link ("Client Management") added to the
  topbar. This codebase has no shared header partial (every `admin/*.php`
  page hardcodes its own topbar), so the link only appears on the main admin
  dashboard, not on every scheduling page. Add the same line to other
  `admin/*.php` topbars by hand if you want it everywhere.

No other existing file was modified. The old `admin/clients.php` ("Clients")
page — a bare name+notes lookup used only to tag audits — is untouched and
still works exactly as before. It's deliberately named differently
("Client Management") from this new module in the nav so the two aren't
mistaken for each other, even though nothing links them underneath.

## Module file map

```
client-management/
  index.php                        Client Directory (search/filter/CRUD)
  client_detail.php                Client info + certifications + documents + activity
  renewal_dashboard.php            30/60/90-day + overdue widgets, filters, threshold editor
  import.php                       Bulk CSV/XLSX import (preview -> commit)
  includes/cm_helpers.php          Module-local JSON/logging/validation helpers
  api/
    clients.php                   Client CRUD (no hard delete — status-only)
    certifications.php            Certification CRUD (no hard delete — status-only)
    certification_documents.php   Document upload/list/delete
    certification_document_download.php   The only route that serves a file's bytes
    scheme_types.php               Scheme type list (for dropdowns)
    users_lookup.php               id+name lookup (responsible-person dropdown)
    renewal_dashboard.php          Bucket counts + drill-down list + threshold PUT
    import.php                     Preview/commit for bulk import
    import_template.php            Downloadable .xlsx import template
    export_xlsx.php                Filtered Excel export (clients + certifications)
    export_client_pdf.php          One client's certification history as PDF
  assets/css/cm.css                Additive styles only (badges, detail grid)
  assets/js/*.js                   One file per page's interactivity
  storage/certification_docs/      Uploaded files (blocked by .htaccess)

db/
  cm_schema.sql                    Full module schema (fresh installs)
  cm_seed_scheme_types.sql         Default scheme types
  cm_migration_002_settings.sql    Additive: adds cm_settings only
```

## Permissions

Matches the brief's table: `super_admin` and `admin` have full CRUD on
clients/certifications; only `super_admin` can edit scheme types (read API
built, a dedicated manage-scheme-types screen is the natural next add if
needed) and renewal alert thresholds. `auditor` gets no access to any page
or API in this module — `ehs_require_role(['super_admin','admin'])` on every
entry point, consistent with the scheduling system's own convention.
