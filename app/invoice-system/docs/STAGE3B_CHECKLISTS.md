# Stage 3B — Zero-Regression, Database Compatibility, Manual QA and Security

**Scope:** quotations issued in the Invoice System and converted into invoices.
- migration 004;
- quotation drafts, preview, "Finalise and send", stored PDF with stamp;
- revisions (-R1, -R2…), reject, cancel, computed expiry;
- acceptance record with optional signed-copy upload;
- conversion of accepted lines into draft invoices;
- quotation settings, stamps, quotation number series, metrics.

Run **C** on the local dev site first. On production run only **C1, C2, C12 and C16**, then one real quotation once the Axiscert texts, signatory and quotation number are confirmed.

---

## A. Zero-regression checklist

### A1. Existing files touched

**None outside `invoice-system/`.**
- `includes/sidebar_nav.php`: unchanged since Stage 2. No existing nav line was edited; Quotations is reached through the module's own tab bar (Overview · **Quotations** · Invoices · Billed Parties · Services · Settings) and from the Overview page.
- `crm/` (including `crm_quotations`, `crm_quotation_items`, `crm/api/quotation_pdf.php` and `crm/api/leads.php`), `client-management/`, `admin_index.php` and all scheduler code: **untouched**. CRM quotations keep working exactly as before; they are simply not linked to invoices.
- No new Composer dependency (Dompdf, already in `vendor/`, renders the quotation PDF).

Module files updated (inside `invoice-system/`): `includes/inv_helpers.php`, `includes/inv_storage.php`, `includes/inv_page.php`, `api/settings.php`, `api/number_series.php`, `api/env_check.php`, `index.php`, `settings.php`, `invoice_edit.php`, `invoice_view.php`, `assets/js/inv_settings.js`, `assets/js/inv_settings_env.js`, `README.md`. Each change is additive and guarded so the module still works if migration 004 has not been imported yet (Quotations then shows "not installed", nothing else changes).

### A2. Database changes (migration 004 only)

| Object | Change |
|---|---|
| `inv_quotations`, `inv_quotation_lines`, `inv_quotation_line_entities` | **New.** Created with the charset and collation read from `inv_invoices` at run time (no collation named in the file). |
| `inv_invoices` | `quotation_id INT UNSIGNED NULL` + index + FK to `inv_quotations`. Existing rows: NULL. |
| `inv_number_series.doc_type` | `'quotation'` appended to the ENUM (existing values keep their positions). 2 rows inserted: 2026 quotation series for EHS and Axiscert, **locked** (`next_value` NULL, unconfirmed). |
| `inv_settings` | 10 columns added: `quote_prefix`, `quote_prefix_confirmed`, `quote_valid_days`, `quote_ref_prefix`, `quote_intro`, `quote_terms`, `quote_payment_terms`, `quote_closing`, `signatory_name`, `stamp_filename`. Filled only where NULL (Axiscert from the sample; everything else «TBC»; stamp NULL). |
| `inv_config` | `schema_version` → 4. |
| Any other table | **No change.** No table converted; no `cm_*`, `crm_*`, `users` or scheduler object touched. |

### A3. Must still work after deploying (smoke test)

- [ ] Login and logout for super_admin, admin and auditor.
- [ ] super_admin top bar unchanged: Auditor Portal, Client Management, CRM, 🧾 Invoices.
- [ ] admin / auditor: no Invoices switcher; `…/invoice-system/quotations.php` returns **403**.
- [ ] Scheduler: admin dashboard and auditor dashboard open; one audit opens.
- [ ] Client Management list and one client open.
- [ ] CRM board opens; **one CRM quotation PDF still downloads** (CRM quotation feature unchanged).
- [ ] Invoices: list, one draft, one issued invoice and its stored PDF still open; issuing a new invoice still works (Stage 3 regression suite re-run: 120/120 on both engines).
- [ ] Settings: invoice readiness and invoice/credit-note series unchanged; new Quotations fieldset and quotation series rows appear.
- [ ] Environment check: no new warnings; *Migration 004 applied*; *Stamp folder protected*.

---

## B. MySQL 8 vs MariaDB: what Stage 3B relies on

Tested on **MySQL 8.0.46** and **MariaDB 10.11.14** (PHP 8.3.6, Dompdf 2.0.8). Both engines: migration 004 run twice on a fresh database, and re-run on a half-applied database; API suite 143/143; browser UI suite 24/24.

| Feature | MySQL 8.0 | MariaDB 10.11 | Note |
|---|---|---|---|
| Dynamic `CREATE TABLE` via `PREPARE` with collation read from `information_schema` | yes | yes | New tables match the inv_ tables on both (verified: general_ci even when the DB default is unicode_ci). |
| CHECK constraints on quotations (valid dates, draft has no number, sent needs number + snapshot + PDF, accepted needs date + name, totals ≥ 0, line qty/amounts) | enforced (≥ 8.0.16) | enforced (≥ 10.2.1) | PHP enforces the same rules first. |
| CHECK on a column used by an `ON DELETE SET NULL` FK | refused (3823) | refused (1901) | So "invoiced line must be an item line" is enforced in PHP only. |
| `JSON` column `entity_snapshot` | native JSON | LONGTEXT + `json_valid`, `utf8mb4_bin` | Env check reports the `_bin` collation as expected information. |
| `SELECT … FOR UPDATE` row locks, fixed lock order | yes | yes | Two simultaneous finalises get consecutive numbers on both. |
| `SET NAMES utf8mb4` at the top of the migration | connection only | connection only | Needed so «TBC» is not double-encoded by a latin1 client (found in testing). Names no collation. |

**Could not be tested here (please check on the live server):**
- the exact live MariaDB/MySQL version and its `sql_mode` (the environment check shows both);
- phpMyAdmin's own import behaviour (tested with the `mysql`/`mariadb` command-line clients; migration 003's phpMyAdmin issues are already handled by the same guard design);
- Apache `.htaccess` handling on cPanel (`private/` and `storage/` must return 403; the local PHP built-in server ignores `.htaccess`);
- cPanel PHP upload limits (`upload_max_filesize`, `post_max_size`); the environment check reports them if below 10 MB, and oversized uploads get a clear "larger than the server allows" message;
- the owner's real stamp image (a stamp extracted from the sample PDF was used for testing only and is **not** shipped).

---

## C. Manual QA checklist — Stage 3B

### C1. Migration
- [ ] Import `db/inv_migration_004.sql` with the portal DB selected → last message "Migration 004 finished…".
- [ ] Import it again → no error; nothing changes (seeded texts you edited are not overwritten).
- [ ] With `information_schema` selected → stops with `STOP - wrong database…`; nothing changed.
- [ ] Run the README §1C verify queries: schema_version 4; doc_type lists quotation; quotation_id exists; 3 quotation tables in `utf8mb4_general_ci`; Axiscert `Q/SH` unconfirmed; 2 locked quotation series rows.

### C2. Settings and readiness
- [ ] Axiscert → Quotations: prefix `Q/SH` with **UNCONFIRMED** badge; texts as on the sample; signatory «TBC».
- [ ] Readiness lists: signatory TBC, prefix UNCONFIRMED, quotation series locked (and phone, GST if still TBC).
- [ ] Prefix `Q SH<x>` → rejected. Stamp file name typed via dev tools as `../../config.php` → rejected.
- [ ] Confirm the 2026 **Quotation** series with next number 1 → preview `Q/SH26001`; the modal warns that the prefix is confirmed too; afterwards the prefix badge disappears.
- [ ] Change the prefix before any send → it becomes UNCONFIRMED again. After the first send the prefix field is disabled (frozen).
- [ ] Stamp select lists only `stamp_*.png/jpg` files in `private/`; no image preview is shown; `…/invoice-system/private/<file>` returns 403 on Apache.

### C3. Draft quotation
- [ ] New quotation → Axiscert texts and 45 days prefilled; "Valid until" = date + 45.
- [ ] Pick a party (address prefilled); type the bold subject and scope.
- [ ] Add 2 item rows (year 1), one **Sum row (auto)**, 2 item rows (years 2, 3). Sum row shows the sum of the two rows above it (Rate / Special). Totals show item rows only.
- [ ] Leave a Special Rate blank → it shows "= Rate" and previews/prints the Rate.
- [ ] Replace the auto sum row with a **Sum row (manual)** and type a price → that price is printed.
- [ ] Rate `-5`, more than 50 rows, future date → refused with messages.
- [ ] Save → URL gets `?id=`; the green panel shows the next number.
- [ ] NULL-address party → panel lists "Address is required before finalising".

### C4. Draft preview
- [ ] Preview PDF → "DRAFT, NOT SENT" watermark on every page, "Our Ref: DRAFT", **no stamp** even when one is configured.
- [ ] Terms show "valid for 45 days"; "2nd / 3rd" in payment terms print as superscripts; "HP 8312 3164" bold.

### C5. Finalise and send
- [ ] Finalise and send → modal shows number, special total, valid until and the lock warning → confirm.
- [ ] View page: number `Q/SH26001`, status Sent, "Stamp: Printed" (when configured).
- [ ] Download → file name `Q-SH26001 <party>.pdf` (slash replaced). The PDF shows the stamp and no watermark.
- [ ] Server: the file is at `storage/quotations/Axiscert/2026/q<ID>.pdf` (ID, not the number).
- [ ] Activity log: `send_quotation` with your name, PDF SHA-256 and "stamp printed".
- [ ] Editing the sent quotation by URL `quotation_edit.php?id=<ID>` redirects to the view.

### C6. Finalise blocked (nothing consumed)
- [ ] EHS draft (texts «TBC», series locked) → Finalise → 409 with every reason; no number, no file; `send_quotation_blocked` logged.

### C7. Duplicate numbering under two simultaneous finalises
- [ ] Two drafts, finalise both at the same moment from two browsers → numbers 002 and 003, never the same.

### C8. Revise / supersede
- [ ] On a sent quotation → Revise → editor shows "Revision Q/SH26001-R1"; entity is fixed.
- [ ] Revise again on the original → you are taken to the existing revision draft (only one at a time).
- [ ] Finalise the revision → `Q/SH26001-R1`; the original becomes **Superseded**; the quotation counter does **not** advance; both versions listed under "Versions".
- [ ] A superseded quotation cannot be revised, accepted or converted.

### C9. Expiry
- [ ] (LOCAL ONLY) `UPDATE inv_quotations SET quote_date = CURDATE() - INTERVAL 60 DAY, valid_until = CURDATE() - INTERVAL 15 DAY WHERE id = <sent id>;`
- [ ] It shows **Expired** in the list and view (stored status is still `sent`); the "Expired" filter lists it; Open excludes it.
- [ ] Accepting it still works and is logged "after its validity date". Once accepted it never shows Expired.

### C10. Acceptance record
- [ ] Mark accepted… with a date, signatory name, note and a signed **PDF** → Acceptance card shows all, "Download signed copy" works (attachment).
- [ ] Try: a `.txt` renamed `.pdf`; a PNG renamed `.pdf`; an `.exe`; a broken PNG; a 12 MB PDF; a future date; a date before the quotation date; an empty name → each refused with a clear message; nothing stored.
- [ ] Server: the file is `storage/acceptances/Axiscert/2026/q<ID>-signed-<random>.pdf`.
- [ ] Accept twice → refused.

### C11. Convert to invoice
- [ ] Sent (not accepted) quotation → no Convert button; API refuses (409).
- [ ] Accepted → Convert… → lines grouped by Year 1 / 2 / 3; sum rows not offered.
- [ ] Tick the two Year-1 lines, choose a category → a **draft invoice** opens with "Created from quotation Q/SH26001-R1", rates = Special Rate (or Rate when blank), audited entities copied.
- [ ] The quotation view marks those lines "Draft #N"; Convert offers only the remaining lines; Cancel is no longer offered.
- [ ] Delete that draft invoice → the lines become "Not yet" again and can be converted again.
- [ ] Next year: convert the Year-2 line → second draft invoice; issue it as usual; its view shows "From quotation".

### C12. List and overview
- [ ] Quotations list: filters (status incl. Expired / Sent awaiting reply, entity, search, dates), "Invoiced n / m lines" column.
- [ ] Metric tiles: Open (count, value), Expiring in 7 days, Acceptance rate 90 days, Accepted not yet invoiced, Average days to accept.
- [ ] Overview page shows four quotation tiles linking to the filtered list.

### C13. Reject / cancel
- [ ] Rejected… on a sent quotation (reason optional) → Rejected; no further actions except Duplicate.
- [ ] Cancel… with a 3-character reason → refused; with a proper reason → Cancelled.

### C14. Stored-file integrity
- [ ] (LOCAL ONLY) append a byte to a stored quotation PDF → download refused (500) and `pdf_integrity_failure` logged.
- [ ] Remove the stamp in Settings → the stored PDF of an earlier quotation is unchanged (same SHA-256); a new quotation finalised now has a blank stamp area and the log says "stamp not configured".

### C15. Edge cases from the mandate
- [ ] NULL-address party (C3), entity switch on a new draft (texts offered from the new entity), duplicate numbering (C7), partial conversion (C11), revision after expiry, Axiscert ↔ EHS series kept separate (EHS series untouched by Axiscert sends).

### C16. Security spot checks
- [ ] Logged out: every `invoice-system/api/quotation*.php` → 401; pages → login.
- [ ] admin user: pages and APIs → 403.
- [ ] POST without the CSRF header → 403 (also the multipart acceptance upload).
- [ ] Subject `<script>alert(1)</script>` → shown as text on screen and printed literally in the PDF.
- [ ] `…/invoice-system/private/`, `…/invoice-system/storage/quotations/…`, `…/storage/acceptances/…` → 403 on Apache.

---

## D. Security notes (Stage 3B)

- **Access and CSRF:** every new page starts with `ehs_require_role(INV_ROLES)` (super_admin) and every API with `ehs_require_role(INV_ROLES, true)`. Every state change (create, save, delete, finalise, revise, reject, cancel, accept upload, convert) calls `ehs_verify_csrf()`; the multipart upload sends the same `X-CSRF-Token` header.
- **No number in any path or URL:** quote numbers contain "/". Files are named by numeric ID (plus a random suffix for signed copies); the storage pattern rejects anything else; every read is `realpath()`-contained and SHA-256-verified; download names replace "/" and strip other unsafe characters (RFC 5987 `filename*` for the display name).
- **Uploads:** server-side size cap (10 MB) and PHP upload error handling, extension whitelist, content MIME via `finfo` that must match the extension, structural check (`%PDF-` / `getimagesize`), random stored name, deny-all folder, served only via the authenticated endpoint as an attachment with `nosniff` (and `Content-Security-Policy: sandbox` for images). The browser's claimed MIME type and file name are never trusted; the original name is kept only for display, path removed.
- **Stamp:** not web-reachable (`private/` deny-all), selectable only from files matching `stamp_[a-z0-9_]+.(png|jpg|jpeg)`, size and type checked, embedded as base64 server-side, printed only on finalised quotations, its SHA-256 frozen into the quotation snapshot. Draft previews never include it, so an unfinalised document never carries the company stamp.
- **Immutability:** sent quotations are locked (PUT/DELETE refused server-side; editor redirects); corrections go through revisions. Stored PDFs are final; settings, logo or stamp changes never alter them.
- **Concurrency:** finalise, revise, accept, reject/cancel and convert all lock the quotation row (`FOR UPDATE`) and compare the row hash loaded by the user. Conversion marks lines with `WHERE invoiced_invoice_id IS NULL` and checks the row count, so a line can never be invoiced twice even under concurrent clicks.
- **SQL:** prepared statements only; **no named placeholder is used twice in any query** (automated scan of every `prepare()` in the module: 0 findings). All quotation SQL touches `inv_` tables only; no cross-module join, so no collation mixing.
- **Output escaping:** all values escaped with `htmlspecialchars` (PHP) or `invEscape` (JS, quotes included). The PDF escapes every value before applying the two formatting rules (`**bold**`, ordinals), so user text cannot inject HTML into Dompdf; Dompdf runs with remote fetching, PHP and JavaScript disabled.
- **Activity log:** create/update/delete draft, duplicate, finalise (with user, PDF and stamp hashes), blocked finalise, revise, supersede, accept (late flagged, signed-copy hash), reject, cancel, convert, every PDF preview/download and signed-copy download, and integrity failures.
- **Deployment hygiene:** the delivery zip contains no `config.php`, no test configuration, no test data, no stamp image and no credentials. Extract it outside the web root and delete it after copying. The separate hygiene list (`_diag.php`, web-root zips, copy files, config files, shared password hash) remains with the owner.

---

## E. Test evidence (this delivery)

| Suite | MySQL 8.0.46 | MariaDB 10.11.14 |
|---|---|---|
| Migration 004 fresh ×2, re-run on half-applied DB, collation inherited | pass | pass |
| Stage 3B API suite (access, CSRF, settings, drafts, preview, finalise, blocked, concurrency, revise, expiry, upload validation, accept, convert, reject/cancel, integrity, stamp immutability, XSS, pages, placeholder scan) | 143/143 | 143/143 |
| Stage 3B browser suite (settings → editor → preview → finalise → download → accept with upload → convert → list → overview → mobile) | 24/24 | 24/24 |
| Stage 3 regression suite (invoices) | 120/120 | 120/120 |
| Stage 3 status recompute | 10/10 | 10/10 |
| Stage 2 regression suite | 100/100 | — (MySQL-only suite) |
| Quote-sample fidelity (A4) | 47/47 text lines matched; 45 within 1.0 pt; max 2.3 pt (baseline); all 8 vertical rules within 0.5 pt; horizontal rules within 1.5 pt | (renderer is PHP-only; engine-independent) |

The fidelity report (side-by-side, overlay, line table, rule table and sample PDFs) is in `stage3b-fidelity-evidence.zip`, delivered separately and **not** deployed.
