# invoice-system/ — Invoice System module (EHS Universal admin portal)

Current stage: **3 of 5: invoices (drafts, issue, stored PDF), Services catalogue, environment check.** Payments, credit notes, void and ageing come in Stage 4.

| Stage | Contents | Status |
|---|---|---|
| 1 | `db/inv_schema.sql` (tables + seeds), this README, `docs/STAGE1_CHECKLISTS.md` | **Delivered** |
| 2 | Skeleton, nav entry, Settings page (TBC editing + number confirmation), party lookup, `docs/STAGE2_CHECKLISTS.md` | **Delivered** |
| 3 | `db/inv_migration_003.sql`, invoice screens, Services screen, PDF generator, #26301 fidelity report, `docs/STAGE3_CHECKLISTS.md` | **Delivered** (awaiting fidelity sign-off) |
| 4 | Payments, credit notes, void, ageing dashboard | Pending |
| 5 | Overdue reminder cron, Excel exports | Pending |

---

## 1. Install (Stage 1)

1. **Back up the database first** (phpMyAdmin → Export, or `mysqldump`).
2. Run the pre-flight check. It is the first `SELECT` in the file and reports `VERSION()`.
   - Continue only on **MySQL 8.0.16 or later**: CHECK constraints are enforced from 8.0.16, and the `utf8mb4_0900_ai_ci` collation is MySQL-only.
   - **Stop** if the server reports MariaDB, and tell me before going further.
3. Import `invoice-system/db/inv_schema.sql` into the portal database (`ehsuser`).
   - The file starts with `SET NAMES utf8mb4`, so the `«TBC»` markers are stored correctly.
   - phpMyAdmin: keep the import "Character set of the file" at **utf-8**.
4. Re-running the file is safe:
   - Tables are created only if absent, and the "already exists" messages are warnings, not errors.
   - Seed rows are inserted only if absent; values edited later are **never** overwritten.

Creates 14 tables, all `inv_*`, InnoDB. The file names `utf8mb4_0900_ai_ci`; **your live server was imported with `utf8mb4_general_ci` instead (owner decision), and that live database is the baseline.** Do not re-run the Stage 1 file as-is on a server that lacks `utf8mb4_0900_ai_ci`.

`inv_settings`, `inv_config`, `inv_number_series`, `inv_categories`, `inv_services`, `inv_parties`, `inv_invoices`, `inv_invoice_lines`, `inv_line_audited_entities`, `inv_payments`, `inv_credit_notes`, `inv_credit_note_lines`, `inv_reminders_log`, `inv_activity_log`.

**Isolation:**
- Foreign keys exist only between `inv_` tables.
- `users` and `cm_clients` ids are stored as plain `INT UNSIGNED`, with no FK.
- No existing table, file or nav entry is changed in this stage.

---

## 1A. Install (Stage 2)

Stage 2 needs the Stage 1 schema in place. No database change in this stage.

1. Copy the `invoice-system/` folder into the app root, next to `crm/` and `client-management/`.
2. Replace `includes/sidebar_nav.php` with the delivered version (diff in `docs/STAGE2_CHECKLISTS.md` §A1).
3. **Do not leave the delivery zip inside the web root.** Extract it elsewhere, copy the files over, then delete the zip.
4. Confirm the deny rules work. Each URL below must return **403**:
   - `…/invoice-system/includes/inv_helpers.php`
   - `…/invoice-system/db/inv_schema.sql`
   - `…/invoice-system/docs/STAGE2_CHECKLISTS.md`
   - `…/invoice-system/storage/`
5. Log in as a super_admin. The top bar now shows **🧾 Invoices**; admins and auditors do not see it.

Files in Stage 2 (all new except the nav):

| Path | Purpose |
|---|---|
| `invoice-system/index.php` | Overview: per-entity readiness, what still blocks issuing |
| `invoice-system/parties.php` | Billed parties list, create/edit, copy from client directory |
| `invoice-system/settings.php` | Entity settings (TBC editing), readiness, number series |
| `invoice-system/api/parties.php` | Party list / create / update / deactivate (no delete) |
| `invoice-system/api/client_lookup.php` | **Read-only** fixed-column `cm_clients` lookup (Option A) |
| `invoice-system/api/settings.php` | Read and update entity settings (whitelisted fields) |
| `invoice-system/api/number_series.php` | Confirm / set the next number under `FOR UPDATE` |
| `invoice-system/includes/inv_helpers.php` | Module helpers: validation, logging, readiness, lookup |
| `invoice-system/includes/inv_page.php` | Page shell (Bootstrap 5.3.3 CDN, nav, toast, scripts) |
| `invoice-system/assets/js/inv_common.js`, `inv_parties.js`, `inv_settings.js` | Front-end |
| `invoice-system/assets/img/logo_ehs.png`, `logo_axiscert.png` | Issuing-entity logos (owner-supplied files) |
| `invoice-system/{includes,db,docs,storage}/.htaccess` | `Require all denied` |
| `includes/sidebar_nav.php` | **Existing file, approved change:** Invoices switcher + sidebar section (super_admin only) |

`admin_index.php` was **not** changed, and no change to it is planned.

---

## 1B. Install (Stage 3)

1. **Back up the database.**
2. Run `invoice-system/db/inv_migration_003.sql` (phpMyAdmin → Import, utf-8).
   - It names **no collation and no character set**. `inv_invoices.status` is re-declared with `MODIFY`, so it keeps the table's own collation (`utf8mb4_general_ci` on your server).
   - It is safe to run twice: the second run prints "status already includes credited: no change."
   - It adds `credited` to the status list, adds the options `pdf_page_size` (A4), `storage_path` (blank) and `schema_version` (3), and fixes the "Organization&" spacing.
3. Copy the Stage 3 files over the Stage 2 `invoice-system/` folder (all are module files; **no file outside `invoice-system/` changes in Stage 3**).
4. Open Settings → **Environment check**. Expect:
   - server version shown;
   - CHECK constraints "enforced";
   - inv_ collation `utf8mb4_general_ci` "consistent";
   - Migration 003 "applied";
   - PDF storage "ready".

   On MariaDB the two `entity_snapshot` JSON columns show as binary collation. This is expected and is reported as information, not as a problem.
5. **Number series:** confirm the real next EHS number in Settings before the first live issue (still locked).

Stage 3 files (all inside `invoice-system/`):

| Path | Purpose |
|---|---|
| `db/inv_migration_003.sql` | `credited` status, module options, accreditation spacing |
| `invoices.php`, `invoice_edit.php`, `invoice_view.php`, `services.php` | Invoice list, draft editor (preview / issue), issued view, services and categories |
| `api/invoices.php`, `api/invoice_issue.php`, `api/invoice_pdf.php` | Draft CRUD + duplicate, issue, PDF (preview / stored download) |
| `api/services.php`, `api/config.php`, `api/env_check.php` | Services and categories, paper size, read-only environment check |
| `includes/inv_invoice.php`, `inv_money.php`, `inv_pdf.php`, `inv_storage.php` | Invoice rules, cents and amount in words, PDF layout, safe storage |
| `assets/js/inv_invoices.js`, `inv_invoice_edit.js`, `inv_services.js`, `inv_settings_env.js` | Front-end |
| `docs/STAGE3_CHECKLISTS.md` | Zero-regression, MySQL/MariaDB notes, manual QA, security. The #26301 fidelity report and images are delivered separately (`stage3-fidelity-evidence.zip`), not deployed. |
| `index.php`, `settings.php`, `parties.php`, `includes/inv_page.php`, `includes/inv_helpers.php` | Updated module files (tab bar, Overview tiles, Settings panels, error-path rollback) |

---

## 2. Design decisions taken in Stage 1

### 2.1 Invoice status: stored, recomputed in the same transaction

**Choice.** `inv_invoices.status` is **stored**, but it is never set by the UI and never incremented. A single PHP function, `inv_recompute_status()` (Stage 3/4), recalculates it from the ledger. That function runs **inside the same transaction**, after `SELECT … FOR UPDATE` on the invoice row, for every:
- issue,
- payment,
- payment reversal,
- credit-note issue,
- credit-note void,
- invoice void.

If any step fails, the whole transaction rolls back, so status and ledger cannot diverge.

**Why stored rather than purely derived:**
- `draft` and `void` are workflow states, not ledger facts, so they must be stored anyway.
- Lists, filters, ageing and the reminder cron need an indexed `status` column (`idx_inv_invoices_status_due`) rather than a ledger aggregation on every row.
- Recompute (not increment) means a status can always be rebuilt from the ledger.
- The QA drift query (checklist §B7) proves stored status = ledger status at any time.
- No triggers: shared cPanel hosting often lacks the TRIGGER privilege, and hidden DB logic is hard to audit.

**Rule applied by `inv_recompute_status()`:**

| Condition | Status |
|---|---|
| `draft` or `void` | unchanged |
| balance = total − Σ active payments − Σ issued credit notes; balance < 0 | **ROLLBACK** (overpayment / over-credit rejected) |
| balance = 0, issued credit notes = total, no active payments | `credited` (Stage 3 migration; owner decision #21) |
| balance = 0 otherwise | `paid` (cash received, possibly with a partial credit) |
| 0 < balance < total | `partially_paid` |
| balance = total | `issued` |

`overdue` is never stored. It is `status IN ('issued','partially_paid') AND due_date < today`, where *today* is the PHP (app time zone) date passed as a parameter, never the database clock.

Credited invoices are not outstanding: they leave ageing and outstanding totals, and are excluded from days-to-pay and % paid on time (owner decision, used by Stage 4 reports).

### 2.2 Rules the database enforces (verified on MySQL 8.0.46)

- **Line amounts:** each must equal `ROUND(qty × rate, 2)` (round once per line).
- **Header totals:**
  - `total = subtotal + gst_amount`;
  - `gst_amount = ROUND(subtotal × gst_rate / 100, 2)`;
  - currency must be SGD.
- **Drafts:** a draft can have no number. A non-draft must have:
  - a number, `issued_at` and `due_date`;
  - bill-to and entity snapshots;
  - amount in words;
  - `total > 0`.
- **Void:** requires `voided_at` and a reason of at least 5 characters.
- **Payments:**
  - amount must be > 0;
  - method must be one of cheque / bank_transfer / paynow;
  - a reversal needs a reason;
  - a payment blocks deletion of its invoice.
- **Credit notes:** a credit note can only reference an invoice of the **same issuing entity** (composite FK).
- **Duplicate numbers:** `UNIQUE (entity_code, invoice_no)` backs up the `FOR UPDATE` counter.
- **Number series:** a series cannot be marked confirmed without a `next_value`.

**Application-only rules:** subtotal = Σ lines, the credit-note cap, and the no-overpayment rule span several rows, so MySQL CHECK cannot enforce them. The application enforces them inside the row lock, and the QA reconciliation queries (checklist §B7) verify them.

### 2.3 Number series (ASSUMPTION, locked)

- **Assumed format:** `[prefix] + YY + 3-digit sequence`, reset each calendar year, separate per entity.
  - Example: EHS #26301 = `""` + `26` + `301`.
- **Year used:** `YEAR(invoice_date)`, so the printed YY always matches the printed date, including a backdated invoice issued in the next year.
- **All four 2026 series rows** (EHS / Axiscert × invoice / credit note) are seeded with `next_value = NULL` and `is_confirmed = 0`.
- **Nothing can be issued** until a super_admin enters and confirms the next number on the Settings page (Stage 2).
  - The system does not assume 26302.
  - A new year (e.g. 2027) also needs a confirmed row before the first issue.
- **Overflow:** with 3 digits, the sequence stops at 999. Issuing number 1000 is refused, and the owner must widen `seq_digits`.

### 2.4 Stage 2 decisions

- **Access:** every page uses `ehs_require_role(['super_admin'])` and every API uses `ehs_require_role(['super_admin'], true)`, via the `INV_ROLES` constant. Every POST/PUT calls `ehs_verify_csrf()` (`X-CSRF-Token` header, as in CRM).
- **Edit conflicts:** edits to settings and parties are checked with a **SHA-256 hash of the row as loaded**, compared under `SELECT … FOR UPDATE`. `updated_at` was rejected after testing showed two saves within the same second slipped past it.
- **Client lookup (Option A):**
  - It reads `information_schema` once per request. It selects `entity` only if the column exists, and returns "unavailable", never a 500, if `cm_clients` or a required column is missing.
  - Searches cap at 20 rows and match LIKE wildcards literally.
  - Copied phone numbers have newlines joined with " / ".
  - It never writes to `cm_*`; a checksum test confirms this.
- **Duplicate warning:** creating a party warns (409) on the same client-directory record, or the same name after ignoring punctuation and Pte/Ltd suffixes. The user can then "Save anyway".
- **Number series confirmation:**
  - The prefix must be decided first (not TBC), and the acknowledgement box must be ticked.
  - The next number must be greater than the highest number already issued. It may only move backwards onto numbers that were never issued.
  - Last year, this year and next year rows can be created.
  - It runs under the same row lock the Stage 3 issue endpoint will take.
- **Number format freeze:** prefixes and sequence digits cannot change once the entity has issued any document.
- **Audit trail:** bank, payee, Co Reg No and legal-name changes are logged with **before and after** values, because changed bank details are a classic invoice-fraud vector. Other fields are logged by name.
- **Logos:** only files matching `logo_*.png|jpg` in `invoice-system/assets/img/` can be selected. Readiness checks the file exists, is ≤ 2 MB and is a real PNG/JPEG. Logo upload is not built; replace the file via cPanel.

### 2.5 Stage 3 decisions

- **Shared nav untouched in Stage 3:** no existing nav lines were edited (owner instruction).
  - The Invoices and Services pages are reached through the module's own tab bar (Overview · Invoices · Billed Parties · Services · Settings) and from the Overview page.
  - The shared sidebar keeps its three Stage 2 links; on the new pages it highlights "Overview".
  - Adding sidebar links for Invoices and Services would need a separate approved nav ticket.
- **Issue transaction:**
  1. Lock in a fixed order: invoice → entity settings → number series. The settings and number-series endpoints only ever lock one of these, so there is no deadlock cycle.
  2. Re-check readiness, module problems and content.
  3. Assign the number and snapshot the entity and bill-to details.
  4. Render the PDF and **write it to its final path before COMMIT**, then record path and SHA-256.
  5. Recompute the status, log, and COMMIT.
  - **Any failure** rolls back and deletes the new file, so no number is consumed.
  - **Crash between write and commit:** a crash after the file write but before COMMIT leaves an orphan file. The next issue for that number moves it aside to `*.orphan-<timestamp>.pdf` (never deleted, never served) and logs it.
- **Stored PDFs are final:** downloads serve the stored bytes after a SHA-256 check and never re-render. A tampered file is refused and logged.
- **Draft previews:**
  - Rendered on the fly with "DRAFT, NOT FOR ISSUE" on every page and the title "INVOICE: DRAFT".
  - Not stored; logged.
  - Entities that are not ready still preview, with their «TBC» values printed.
- **Blocked issue** returns 409 with every reason, and logs `issue_blocked`. Nothing changes: no number is used and no file is written.
  - Invoice content is blocked only by the exact «TBC» marker, because plain "TBC" can be part of a real name.
  - Entity settings are blocked by any "TBC" (Stage 2 rule).
- **Totals:** a total of 0.00 or less cannot be issued (PHP check and DB CHECK `total > 0`).
- **Future invoice dates are blocked.** Backdating is allowed with a warning. The year in the number is the invoice date's year: a 2025-dated invoice needs a confirmed 2025 series.
- **Amount in words:**
  - Format: "SG DOLLARS: … ONLY", all capitals, no "AND" inside the dollars, no hyphens.
  - Cents are written as "AND CENTS …" (e.g. 1,250.05 → "ONE THOUSAND TWO HUNDRED FIFTY AND CENTS FIVE").
  - Computed in integer cents and frozen at issue.
- **Money:** integer cents end to end. Line, GST and total rounding reproduce MySQL/MariaDB `ROUND()` exactly (verified on both).
- **Qty:** for services marked "Qty = number of audited entities" the server sets the quantity itself and ignores the client value.
- **Paper:** A4 by default; Letter can be set in Settings → Module options. An issued PDF keeps the paper it was issued on.
- **Logo:** the owner's file is unchanged. At render time GD trims its transparent (or white) margins in memory so the visible logo fills the same 101.5 × 63.5 pt box as on #26301.
- **Storage path:**
  - Blank `inv_config.storage_path` means `invoice-system/storage/`, which has a deny-all `.htaccess`.
  - An absolute folder **outside** `public_html` is safer. Set it by SQL: `UPDATE inv_config SET cfg_value='/home/<account>/inv_storage' WHERE cfg_key='storage_path';`
  - Issuing is blocked when the folder:
    - is missing, relative or a symlink;
    - is not writable;
    - sits inside the web root without a deny-all `.htaccess`.
  - Stored paths must match `invoices/<ENTITY>/<YYYY>/<NUMBER>.pdf` and are re-checked with `realpath()` on every read.
- **Services and categories** can be deactivated, never deleted (invoices reference them). Editing a service never changes existing invoices.

---

## 3. "Entity ready to issue" check (implemented in Stage 2, enforced server-side in Stage 3)

**Issuing is blocked for an entity while any condition below fails.** Drafts and PDF previews stay allowed. Previews of drafts, and of any entity that is not ready, carry the watermark **"DRAFT, NOT FOR ISSUE"**. The Settings page and the invoice editor list every failing field.

1. No required field contains the substring `TBC`. The fields checked are:
   - `legal_name`, `co_reg_no`, `address`, `email`, `phone`, `accreditation_line`;
   - `cheque_payee`, `bank_name`, `bank_code`, `bank_branch`, `bank_account_label`, `bank_account_no`, `bank_swift`, `paynow_uen`, `payment_note`;
   - `tnc_text`, `footer_text`, `invoice_prefix`;
   - plus `cn_prefix` for credit notes.

   The match uses the ASCII substring so that a mis-encoded import still blocks. `accreditation_line` may be set to empty, meaning "print no line".
2. `gst_status_confirmed = 1`.
3. The number series for `YEAR(invoice_date)` and the document type exists and has `is_confirmed = 1`.
4. The logo file named in `logo_filename` exists in `invoice-system/assets/img/` and is a valid PNG or JPEG.
5. Module-wide (Stage 3), checked by `inv_module_problems()`:
   - PDF storage passes its safeguards;
   - Dompdf is installed;
   - PHP GD is loaded;
   - migration 003 has been run.

Seeded state:
- **EHS:** not ready only because its numbering is unconfirmed.
- **Axiscert:** not ready on 12 fields (13 for credit notes) plus GST status plus numbering.

---

## 4. OPEN TBCs — owner to supply (none may be forgotten)

| # | Entity | Item | Seeded value | Blocks issuing? | Where to fix |
|---|---|---|---|---|---|
| 1 | Axiscert | Cheque payee | `«TBC»` | Yes | Settings |
| 2 | Axiscert | Bank name, bank code, branch, account label, account no., SWIFT | `«TBC»` ×6 | Yes | Settings |
| 3 | Axiscert | PayNow UEN (quote shows Co Reg/UEN 202310893C; confirm it is the PayNow target) | `«TBC»` | Yes | Settings |
| 4 | Axiscert | GST status (quote says "No GST"; confirm for invoices) | not registered, unconfirmed | Yes | Settings |
| 5 | Axiscert | Invoice Terms & Conditions | `«TBC»` | Yes | Settings |
| 6 | Axiscert | Accreditation line on invoices (none in quote header; empty = none) | `«TBC»` | Yes | Settings |
| 7 | Axiscert | Contact number: 91445253 (quote header, same as EHS) or 8312 3164 (quote body) | `«TBC»` | Yes | Settings |
| 8 | Axiscert | Invoice number prefix (e.g. `AX` or none) | `«TBC»` | Yes | Settings |
| 9 | Axiscert | Credit-note prefix | `«TBC»` | Yes (credit notes) | Settings |
| 10 | Axiscert | Next invoice number for 2026 (confirm) | locked | Yes | Settings → Numbering |
| 11 | Axiscert | Next credit-note number for 2026 (confirm) | locked | Yes (credit notes) | Settings → Numbering |
| 12 | Axiscert | Transparent, higher-resolution logo (current: 225×225, white background) | `logo_axiscert.png` (current file) | No | Replace file |
| 13 | Axiscert | Is `contact@axiscert.com.sg` the right sender/reply address for reminders? | as printed | No (Stage 5) | Settings |
| 14 | EHS | Confirm format YY + 3-digit running number with yearly reset (26301 = 26 + 301) | assumption | Yes | Settings → Numbering |
| 15 | EHS | Next invoice number for 2026 (not auto-assumed) | locked | Yes | Settings → Numbering |
| 16 | EHS | Credit-note prefix `CN` and next number (assumption) | `CN`, locked | Yes (credit notes) | Settings → Numbering |
| 17 | EHS | Default rate for "Risk Management Audit for bizSAFE recognition" (550.00 taken from #26301; confirm it is the standard rate) | 550.00 | No (editable per line) | Services |
| 18 | EHS | ~~Accreditation line spacing~~ **Resolved:** "Organization & ISO"; migration 003 fixes any row still missing the space | done | — | — |
| 19 | Both | ~~Default terms and due-date position~~ **Resolved:** 30 days; "Due Date" and "Payment terms" printed under Category | done | — | — |
| 20 | Both | Standard wording for the other categories (Certification, Surveillance, Recertification, Training) | none seeded | No | Services |
| 21 | Both | ~~Separate `credited` status~~ **Resolved:** yes, added by migration 003, set in the credit-note transaction | done | — | — |
| 22 | Live DB | Confirm `cm_clients.entity` exists (current dump says yes; Stage 2 checks `information_schema` anyway) | n/a | No | Owner confirms |
| 23 | Live DB | ~~MySQL 8 only~~ **Superseded:** the live inv_ tables use `utf8mb4_general_ci` (owner). Stage 3 is tested on MySQL 8.0.46 and MariaDB 10.11.14; see Settings → Environment check for your server | n/a | — | Environment check |
| 24 | Existing code (archive) | `crm/api/leads.php` uses `CRM_FUNNEL_STAGES` / `CRM_SIDE_STAGES`, which nothing in the archive defines, so the request returns 500 in the archived copy. Pre-existing; not touched (crm/ is out of bounds). Check whether the live copy has the same problem. | n/a | No | Owner / CRM fix |
| 25 | Existing code | At phone width the existing top bar already overflows by ~257 px: its CSS hides `span.label` on mobile, but the existing switcher buttons have no such span. The new Invoices button uses the span, so it adds only ~47 px. Fixing the others means editing existing nav lines; needs approval. | n/a | No | Approve a nav tweak |
| 26 | Existing code | The root `admin_index.php` looks like an orphaned copy of `admin/index.php`. Owner is checking the live copy; no change planned by this module. | n/a | No | Owner |
| 27 | Stage 4 | An invoice partly credited and **unpaid** currently shows as `partially_paid` (balance < total). Keep that label, or show e.g. "Partially credited"? | `partially_paid` | No | Decide before Stage 4 |
| 28 | Both | Wording and rates for certification, surveillance, recertification and training services (none invented) | none | No | Services screen |
| 29 | Server | PDF storage outside `public_html` recommended: send the absolute path, or set `inv_config.storage_path` yourself | module storage | No | SQL |
| 30 | Nav | Optional ticket: add "Invoices" and "Services" links to the shared sidebar (would change the `$navInvPages` line in `includes/sidebar_nav.php`) | not done | No | Separate approval |

---

## 5. Security notes (Stage 1)

- **Stage 1 had no new attack surface:** schema only. No PHP, no web-reachable file, no credentials, no config read. Stage 2 security notes are in `docs/STAGE2_CHECKLISTS.md` §C.
- **Defence in depth:** money, state, numbering and entity-matching rules are enforced in the database as well as the application, so a future buggy endpoint cannot write an inconsistent invoice.
- **History is preserved:** `ON DELETE` actions are `RESTRICT`, except draft-only child rows. Issued invoices, payments and credit notes cannot be deleted while referenced.
- **Logos:** `logo_filename` is a bare filename. Stage 3 will whitelist it with `basename()` plus an extension check and resolve it only inside `invoice-system/assets/img/`, so it cannot be used for path traversal.
- **Placeholder injection:** settings text is printed into PDFs. Stage 3 escapes every field with `htmlspecialchars` before Dompdf renders it.
- **Hygiene list:** the separate list (unauthenticated `_diag.php`, web-root zips and `* copy.php` files, config files, the shared password hash) is tracked by the owner and is not built around here.

---

## 6. Dev-only reset (LOCAL ONLY, never on production)

Drops the module's tables in FK-safe order. Use only on `localhost:8888`.

```sql
DROP TABLE IF EXISTS inv_reminders_log, inv_credit_note_lines, inv_credit_notes, inv_payments,
  inv_line_audited_entities, inv_invoice_lines, inv_invoices, inv_parties, inv_services,
  inv_categories, inv_number_series, inv_config, inv_activity_log, inv_settings;
```
