# invoice-system/ — Invoice System module (EHS Universal admin portal)

Current stage: **3B: quotations issued in this module and converted into invoices** (on top of Stage 3: invoices, stored PDF, Services, environment check). Payments, credit notes, void and ageing come in Stage 4.

> **Which quotation feature should staff use?** For client quotations that will be invoiced, use **Invoice System → Quotations** (this module). The CRM module's own quotation feature (`crm/`) is unchanged and was not touched; it is not linked to invoices. A link between CRM leads and these quotations is out of scope for now (owner decision 12).

| Stage | Contents | Status |
|---|---|---|
| 1 | `db/inv_schema.sql` (tables + seeds), this README, `docs/STAGE1_CHECKLISTS.md` | **Delivered** |
| 2 | Skeleton, nav entry, Settings page (TBC editing + number confirmation), party lookup, `docs/STAGE2_CHECKLISTS.md` | **Delivered** |
| 3 | `db/inv_migration_003.sql`, invoice screens, Services screen, PDF generator, #26301 fidelity report, `docs/STAGE3_CHECKLISTS.md` | **Delivered** |
| 3B | `db/inv_migration_004.sql`, quotation screens, quotation PDF (quote-sample layout), acceptance record, conversion to invoices, quote-sample fidelity report, `docs/STAGE3B_CHECKLISTS.md` | **Delivered** (awaiting fidelity sign-off) |
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
2. Run `invoice-system/db/inv_migration_003.sql`. In phpMyAdmin, **first click the portal database in the left panel**, then use that database's **Import** tab (utf-8). Do not import from the server-level Import page or while `information_schema` is selected.
   - A guard at the top checks this. With the wrong database (or none) selected, it stops immediately with `#1054 Unknown column 'STOP - wrong database. Select the portal DB, then import again'`, and nothing is changed.
   - It names **no collation and no character set**. `inv_invoices.status` is re-declared with `MODIFY`, so it keeps the table's own collation (`utf8mb4_general_ci` on your server).
   - It is safe to run twice: the second run prints "status already includes credited: no change."
   - It adds `credited` to the status list, adds the options `pdf_page_size` (A4), `storage_path` (blank) and `schema_version` (3), and fixes the "Organization&" spacing.
   - **Verify** in the portal database's SQL tab (run these as their own query, not as part of the import):
     ```sql
     SHOW COLUMNS FROM `inv_invoices` LIKE 'status';   -- Type ends with 'void','credited')
     SELECT `cfg_key`, `cfg_value` FROM `inv_config`
     WHERE `cfg_key` IN ('pdf_page_size','storage_path','schema_version');   -- A4, (blank), 3
     ```
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

## 1C. Install (Stage 3B: quotations)

1. **Back up the database.**
2. Import `invoice-system/db/inv_migration_004.sql` exactly like migration 003: **click the portal database in the left panel first**, then its **Import** tab (character set utf-8).
   - Guards stop the import with a readable `#1054` message and change nothing when: the wrong database is selected (`STOP - wrong database…`), required inv_ tables are missing (`STOP - required inv_ tables are missing in this DB`), or migration 003 was not run (`STOP - run inv_migration_003.sql first`).
   - It names **no collation**. The three new tables read the character set and collation of your existing `inv_invoices` table at run time (`utf8mb4_general_ci` on your server) and are created with it. Added columns inherit their table's collation. The only charset statement is `SET NAMES utf8mb4` (connection only), so the `«TBC»` markers arrive intact.
   - No `ADD COLUMN IF NOT EXISTS`, no CTEs, no window or JSON functions, no MySQL-8-only syntax. Safe to run twice (tested twice on MySQL 8.0.46 and MariaDB 10.11.14, including on a half-applied database).
   - It adds: `inv_quotations`, `inv_quotation_lines`, `inv_quotation_line_entities`; `inv_invoices.quotation_id` (+ index + FK); `'quotation'` in `inv_number_series.doc_type`; LOCKED 2026 quotation series rows for EHS and Axiscert; quotation settings on `inv_settings`; `schema_version` = 4.
3. **Verify** (portal database → SQL tab, as separate queries, not part of the import):
   ```sql
   SELECT `cfg_value` FROM `inv_config` WHERE `cfg_key` = 'schema_version';            -- 4
   SHOW COLUMNS FROM `inv_number_series` LIKE 'doc_type';                               -- Type lists 'quotation'
   SHOW COLUMNS FROM `inv_invoices` LIKE 'quotation_id';                                -- one row
   SELECT `TABLE_NAME`, `TABLE_COLLATION` FROM information_schema.TABLES
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` LIKE 'inv\_quot%';              -- 3 rows, utf8mb4_general_ci
   SELECT `entity_code`, `quote_prefix`, `quote_prefix_confirmed`, `quote_valid_days` FROM `inv_settings`;
                                                         -- Axiscert Q/SH 0 45 · EHS «TBC» 0 45
   SELECT `entity_code`, `doc_type`, `period_year`, `next_value`, `is_confirmed`
     FROM `inv_number_series` WHERE `doc_type` = 'quotation';                           -- 2 rows, NULL, 0
   ```
4. Copy the Stage 3B files over the module folder. **Only files inside `invoice-system/` change.** Make sure `invoice-system/private/.htaccess` arrived (deny-all; it protects the stamp images).
5. **Stamps (when you have them):** upload the image(s) by cPanel File Manager or FTP into `invoice-system/private/`, named `stamp_<something>.png` or `.jpg` (lower case, e.g. `stamp_axiscert.png`, max 2 MB). Then choose the file in Settings → entity → Quotations → *Company stamp*. Until then the stamp area prints blank. Check that `…/invoice-system/private/stamp_axiscert.png` returns **403** in a browser.
6. Settings → entity → **Quotations**: replace every «TBC» you can (signatory name, EHS texts), then confirm the quotation number series (this also confirms the prefix, which is seeded UNCONFIRMED as `Q/SH` for Axiscert). Settings → Environment check should show *Migration 004 applied* and *Stamp folder protected*.

Stage 3B files (all inside `invoice-system/`):

| Path | Purpose |
|---|---|
| `db/inv_migration_004.sql` | Quotation tables, settings, series, invoice link |
| `quotations.php`, `quotation_edit.php`, `quotation_view.php` | List + metrics, draft editor (preview / finalise and send), sent view (accept, revise, reject, cancel, convert) |
| `api/quotations.php`, `api/quotation_action.php`, `api/quotation_accept.php`, `api/quotation_convert.php`, `api/quotation_pdf.php` | Draft CRUD; finalise / revise / reject / cancel; acceptance with optional signed upload; conversion; PDF and signed-copy download |
| `includes/inv_quote.php`, `includes/inv_quote_pdf.php` | Quotation rules and transactions; quotation PDF layout |
| `assets/js/inv_quotations.js`, `inv_quotation_edit.js`, `inv_quotation_view.js` | Front-end |
| `private/.htaccess` | Deny-all for stamp images |
| `docs/STAGE3B_CHECKLISTS.md` | Zero-regression, MySQL/MariaDB notes, manual QA, security. The quote-sample fidelity report is delivered separately (`stage3b-fidelity-evidence.zip`), not deployed. |
| Updated: `includes/inv_helpers.php`, `inv_storage.php`, `inv_page.php`, `api/settings.php`, `api/number_series.php`, `api/env_check.php`, `index.php`, `settings.php`, `invoice_edit.php`, `invoice_view.php`, `assets/js/inv_settings.js`, `inv_settings_env.js`, `README.md` | Quotation settings, stamps, storage paths, Quotations tab, Overview tiles, "From quotation" links, environment rows |

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

### 2.6 Stage 3B decisions (quotations)

- **Where quotations live:** this module (owner decision, option c). CRM is untouched; see the note at the top.
- **Lifecycle (stored):** `draft → sent → accepted | rejected | cancelled | superseded`.
  - **"Finalise and send"** = numbered, PDF stored (with stamp), locked. You then send the PDF yourself; emailing from the system can come later (Stage 5) without changing this meaning. Every finalise is logged (`send_quotation`) with the user's name, the PDF SHA-256 and whether the stamp was printed.
  - **Expired is computed, never stored:** `status = 'sent' AND valid_until < today`. An **accepted** quotation never expires and stays convertible, so year-2 and year-3 lines can be invoiced in later years. A late acceptance of an expired quotation is allowed and logged as late.
  - **Revise** (sent only, one revision draft at a time): copies the quotation into a draft. Finalising it numbers it `<base>-R1`, `-R2`… (no new sequence number) and marks the previous version `superseded` in the same transaction.
  - **Reject** (sent) and **Cancel** (sent or accepted, reason ≥ 5 characters, impossible once any line is invoiced).
- **Numbering:** prefix + YY + 3-digit sequence per entity from the `quotation` series, locked until confirmed in Settings (same screen and rules as invoices). Axiscert prefix seeded `Q/SH`, UNCONFIRMED (the meaning of "SH" is unknown and not guessed); confirming the series confirms the prefix. EHS prefix «TBC». A prefix cannot change once a quotation has been sent.
- **Numbers contain "/" and are never used in a path or URL:**
  - stored PDF: `storage/quotations/<ENTITY>/<YYYY>/q<ID>.pdf`;
  - signed copy: `storage/acceptances/<ENTITY>/<YYYY>/q<ID>-signed-<12 random hex>.<pdf|jpg|png>`;
  - URLs use `?id=<ID>` only;
  - download names replace "/" with "-": `Q-SH26001-R1 XXX.pdf`, `… - signed.pdf`.
  - The storage path pattern accepts only these shapes and every read is `realpath()`-checked.
- **Finalise transaction:** lock quotation → (revised quotation) → entity settings → quotation series (same fixed order as invoices; no deadlock cycle). Re-check readiness and content, assign the number, snapshot entity details (with logo and stamp SHA-256), render, **write the PDF to its final path before COMMIT**, mark sent (the DB CHECK requires the PDF path once sent), supersede the parent, log, COMMIT. Any failure rolls back and deletes the file; no number is consumed.
- **Stamp:** stored only in `invoice-system/private/` (deny-all), chosen by file name in Settings, never shown or served by URL, read server-side and embedded as base64. Printed **only** on finalised quotations; draft previews never carry it (watermark "DRAFT, NOT SENT"). Changing or removing the stamp later never changes a stored PDF.
- **Acceptance record:** acceptance date (not before the quotation date, not in the future), who accepted (client signatory), optional note, optional signed copy. Upload validation: PHP upload error codes, ≤ 10 MB, extension whitelist (pdf/jpg/jpeg/png), MIME sniffed from the content with `finfo` and required to match the extension, PDF must start with `%PDF-`, images must decode with `getimagesize`. Stored under the deny-all storage folder with a random name; served only through the authenticated endpoint after a SHA-256 check, always as an attachment with `nosniff` (images also with a sandboxing CSP). The original file name is kept for display only, path removed.
- **Price table:** item rows (Rate, optional Special Rate, optional qty, optional audited entities, optional "Year" for conversion grouping) and two kinds of sum row chosen per quotation: **automatic** (adds the item rows since the previous sum row) or **manual** (typed price). Special Rate blank prints the Rate. Sum rows are printed right-aligned like the sample, are never totalled and never invoiced. No grand total is printed (as on the sample); totals are shown on screen only. A Qty column appears only when some item has qty ≠ 1 (amounts then show "n × S$unit").
- **GST:** not registered → "(No GST)" under Rate (as on the sample). Registered and confirmed → "(before GST)" plus the line "All rates are before GST. GST at n% will be added to the invoice."
- **Texts:** per entity defaults (Axiscert seeded from the sample; EHS «TBC»), copied into each new quotation and editable there. `{valid_days}` in the terms is replaced by the validity; `**text**` prints bold; in payment terms "2nd", "3rd" print as superscript ordinals. A quotation cannot be finalised while any text contains «TBC».
- **Validity:** 45 days per entity (editable per entity and per quotation, 1–365).
- **Conversion:** accepted quotations only; tick item lines; each line once; one new **draft** invoice per conversion at Special Rate (or Rate), with audited entities, party and addressee copied and `quotation_id` set. The invoice is then edited and issued as usual. Deleting that draft invoice frees its lines (FK `ON DELETE SET NULL`). The "each line once" rule is enforced in PHP under row locks (`UPDATE … WHERE invoiced_invoice_id IS NULL` with a row-count check); a DB CHECK was not possible because both engines refuse a CHECK on a column used by an `ON DELETE SET NULL` FK.
- **Paper:** quotations are always A4 (the sample is A4); the paper option applies to invoices only.
- **Metrics (overview and list):** open quotations (count, special value), expiring in 7 days, acceptance rate over 90 days (accepted ÷ accepted + rejected + expired), accepted-not-yet-invoiced value (lines not invoiced, or only on a draft invoice), average days to accept. Superseded and cancelled quotations are excluded.

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

**Quotations (Stage 3B)** have their own, shorter check (`inv_quote_readiness()`): letterhead fields, the five quotation texts and the signatory name must not be TBC or empty; the quotation prefix must be decided and confirmed; GST status confirmed; the quotation series for the quotation-date year confirmed; logo valid; the stamp, **if one is chosen**, must be a valid file (no stamp = blank area, not a blocker). Bank details are not needed for quotations.

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
| 30 | Nav | Optional ticket: add "Invoices", "Quotations" and "Services" links to the shared sidebar (would change the `$navInvPages` line in `includes/sidebar_nav.php`) | not done | No | Separate approval |
| 31 | Axiscert | Quotation prefix `Q/SH` (from the sample; meaning of "SH" unknown) | `Q/SH`, UNCONFIRMED | Yes (quotations) | Settings → Quotations / series |
| 32 | Axiscert | Approved signatory name (sample shows "XXXX") | `«TBC»` | Yes (quotations) | Settings → Quotations |
| 33 | Axiscert | Company stamp image | none (prints blank) | No | Upload to `private/`, choose in Settings |
| 34 | Axiscert | Next quotation number for 2026 | locked | Yes (quotations) | Settings → Numbering |
| 35 | EHS | Quotation prefix, "Ref:" text, introduction, terms, payment terms, closing, signatory | `«TBC»` ×7 | Yes (EHS quotations) | Settings → Quotations |
| 36 | EHS | Company stamp image and next quotation number | none / locked | Stamp: no · Number: yes | Settings |
| 37 | Axiscert | Closing paragraph phone **8312 3164** differs from the letterhead 91445253 (both as printed on the sample; kept as printed) | as printed | No | Confirm or edit text |
| 38 | Both | Standard quotation line items / rates (none invented; the sample shows placeholders) | none | No | Services or per quotation |

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
SET FOREIGN_KEY_CHECKS = 0;   -- quotations and invoices reference each other (Stage 3B)
DROP TABLE IF EXISTS inv_quotation_line_entities, inv_quotation_lines, inv_quotations;
DROP TABLE IF EXISTS inv_reminders_log, inv_credit_note_lines, inv_credit_notes, inv_payments,
  inv_line_audited_entities, inv_invoice_lines, inv_invoices, inv_parties, inv_services,
  inv_categories, inv_number_series, inv_config, inv_activity_log, inv_settings;
SET FOREIGN_KEY_CHECKS = 1;
```
