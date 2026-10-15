# Stage 3 — Zero-Regression, Database Compatibility, Manual QA and Security

**Scope:**
- migration 003;
- invoice drafts, preview, issue and the stored PDF;
- the invoice list and the issued-invoice view;
- the Services screen;
- the read-only environment check;
- the paper-size option.

Payments, credit notes, void and ageing are Stage 4.

Run **B** on the local dev site first. On production run only **B1, B2, B13 and B17**, plus one real issue once the EHS number has been confirmed.

---

## A. Zero-regression checklist

### A1. Existing files touched

**None.** Stage 3 changes no file outside `invoice-system/`. In particular, `includes/sidebar_nav.php` is unchanged since Stage 2. As instructed, no existing nav lines were edited.

The new pages are reached through the module's own tab bar. On those pages the shared sidebar highlights "Overview" (README TBC #30).

`crm/`, `client-management/`, `admin_index.php` and all scheduler code are untouched.

### A2. Database changes (migration 003 only)

| Object | Change |
|---|---|
| `inv_invoices.status` | `'credited'` appended to the ENUM. Same table collation; existing values keep their positions. |
| `inv_config` | 3 rows inserted if absent: `pdf_page_size` = A4, `storage_path` = '', `schema_version` = 3. |
| `inv_settings.accreditation_line` | "Organization&" → "Organization &", only where the space is missing. |
| Any other table | **No change.** No table is converted; no collation or character set is named. |

### A3. Must still work after deploying (smoke test)

- [ ] Login and logout for super_admin, admin and auditor.
- [ ] **super_admin top bar:** Auditor Portal, Client Management, CRM, 🧾 Invoices.
- [ ] **admin:** no Invoices switcher; `…/invoice-system/invoices.php` returns 403.
- [ ] **Auditor Portal pages** (Master Schedule, Day Schedule, Auditors, Clients, Schemes, Holidays, Export, Activity Log, User Accounts) load.
- [ ] **Client Management:**
  - Directory, client detail, Renewal Dashboard, Audit Extract with Excel export, and Bulk Import load.
  - The client directory's data is unchanged (the Invoice System only reads it).
- [ ] **CRM:** Board, List and lead detail load; a quotation PDF downloads.
- [ ] **Invoice System Stage 2 screens:**
  - Overview, Billed Parties and Settings load.
  - TBC editing and number confirmation still work.

**Verified automatically** on both MySQL 8.0.46 and MariaDB 10.11.14:
- the Stage 2 suite (100 checks) passes on the Stage 3 code;
- 13 existing and module pages return 200 with no PHP notices for super_admin, plus 3 for admin.

---

## B. MySQL 8 vs MariaDB: what the Stage 3 features rely on

Tested on **MySQL 8.0.46** (portal tables `utf8mb4_0900_ai_ci`, inv_ tables `utf8mb4_general_ci`) and **MariaDB 10.11.14** (portal tables `utf8mb4_unicode_ci`, inv_ tables `utf8mb4_general_ci`). The same automated suite ran on both, with the same results.

| Behaviour | MySQL 8 | MariaDB | Stage 3 features relying on it | Also enforced in PHP? |
|---|---|---|---|---|
| **CHECK constraints** | Enforced from 8.0.16; parsed but ignored before. | Enforced from 10.2.1. | DB safety net for: line amount = ROUND(qty×rate,2); total = subtotal+GST; GST rounding; issued rows complete with total > 0; draft has no number; due ≥ date; SGD only. | **Yes, every one.** Amounts are recomputed in integer cents on every save and issue; issue refuses total ≤ 0, missing number, snapshot or address; drafts never get a number. The Environment check says whether your server enforces CHECKs. |
| **`SELECT … FOR UPDATE`** (InnoDB row locks) | Yes | Yes (same semantics for this use) | Issue: invoice → settings → series locked in a fixed order. Number confirmation locks the series row. Draft save, delete and edit-conflict checks lock their row. | The UNIQUE key `(entity_code, invoice_no)` plus a pre-insert duplicate check stand behind the lock. `FOR SHARE`, `NOWAIT` and `SKIP LOCKED` are **not used** (MariaDB differences). |
| **JSON** (`entity_snapshot`) | Native JSON type. | Alias of LONGTEXT + `json_valid()` CHECK; forced `utf8mb4_bin` collation. | Entity snapshot frozen at issue. | Written with `json_encode` and read with `json_decode`; **no JSON SQL functions are used**. The Environment check reports MariaDB's binary JSON collation as expected, not as a mismatch. |
| **ENUM `MODIFY`** (migration 003) | Appending a value is metadata-only. | Same. | `credited` status. | Status values are whitelisted in PHP (`INV_STATUSES`). |
| **`ROUND()` on DECIMAL** | Half away from zero. | Same. | Line and GST rounding. | PHP integer-cents rounding reproduces it exactly (spot-checked on both servers). |
| **Strict `sql_mode`** | Default on. | Default on in 10.2.4+ (`STRICT_TRANS_TABLES`). | Rejects truncated or invalid values. | All input is validated, length-capped and whitelisted in PHP. The Environment check warns if strict mode is off. |
| **`NOW()` / time zone** | Session time zone. | Same. | `issued_at` and other audit timestamps (same as the other modules' `created_at` defaults). | "Today", "future date" and "overdue" use the **PHP app time zone**, passed as a parameter. The Environment check shows any DB vs PHP clock offset. |
| **Collation** (`utf8mb4_general_ci` vs `0900_ai_ci` / `unicode_ci`) | `0900_ai_ci` exists. | `0900_ai_ci` only as an alias from 11.4.5. | None: Stage 3 creates no table or column. | See B1. |

### B1. Collation confirmation

- `inv_migration_003.sql` names **no collation and no character set** (`grep 0900` = 0). The re-declared `status` column inherits the table default (verified: `utf8mb4_general_ci` on both servers).
- **No Stage 3 query compares or joins `inv_` text with `users`, `cm_*` or `crm_*`.** A static scan of every SQL statement in the module finds only:
  - the approved single-table `cm_clients` lookup (no join; the search string is a bound parameter);
  - `information_schema` reads in the environment check and the lookup guard.

  Every join in Stage 3 (invoice list totals and similar) is between `inv_` tables only, all on the same collation. Any future cross-module text comparison must carry an explicit `COLLATE`.
- The Settings page **Environment check** (read-only, GET only, verified to change nothing) shows:
  - server type and version;
  - every inv_ table's collation;
  - a warning when any inv_ table or text column differs from the rest (verified by temporarily setting one column to `utf8mb4_bin`);
  - users / cm_clients collations for reference.

---

## C. Manual QA checklist — Stage 3

### B1. Migration

| # | Step | Expected |
|---|---|---|
| 1 | Run `inv_migration_003.sql` | Pre-flight row, then `status_column_after` shows `…'void','credited')`, then 3 config rows |
| 2 | Run it again | "status already includes credited: no change."; no errors; config values unchanged |
| 3 | `SELECT accreditation_line FROM inv_settings WHERE entity_code='EHS';` | Contains "Organization & ISO" |

### B2. Environment check (Settings page, bottom right)

| # | Step | Expected |
|---|---|---|
| 1 | Open Settings | Panel shows server type and version, CHECK "enforced", strict "on", inv_ collation `utf8mb4_general_ci` "consistent", Migration 003 "applied", Dompdf version, GD yes, storage "ready" |
| 2 | Click **Re-check** several times, then compare `CHECKSUM TABLE inv_invoices, inv_settings, inv_config;` before and after | Identical (read-only) |
| 3 | MariaDB only | One info line: the JSON `entity_snapshot` columns use a binary collation (expected) |

### B3. Services and categories

| # | Step | Expected |
|---|---|---|
| 1 | Services → **New service** with code `bad code!` and an empty title | Field errors on code and title |
| 2 | Create `SURV-1` "Surveillance Audit Year 1", rate `800` | Listed. The code is upper-cased. |
| 3 | Create another service with code `SURV-1` | "This code is already used." |
| 4 | Edit `SURV-1`, change the rate. Activity log shows `update_service` with "rate 800.00 -> …" | Logged |
| 5 | Edit the same service in two tabs and save both | Second tab: "changed by someone else" |
| 6 | Deactivate `SURV-1` | Greyed out, no longer offered on new lines; existing invoices unchanged |
| 7 | Categories: add "rm audit" | Refused (already exists, case-insensitive) |
| 8 | Rename a category | Saved. Already-issued invoices keep the category name printed at issue. |

### B4. Draft invoice

| # | Step | Expected |
|---|---|---|
| 1 | Invoices → **New invoice**. Type 2+ letters of a party, pick it | Bill-to name, address, Attn and email fill in; terms = party terms or entity default; due date = date + terms |
| 2 | Category = RM Audit. Choose service RM-BIZSAFE → **Add line** | Title, wording and rate 550.00 filled in |
| 3 | **Paste list** with `1) DING ALLOYS…` … `6) HS INTERNATIONAL…` → Add these | 6 entities, numbering stripped; qty = 6 and read-only; amount and total $3,300.00 |
| 4 | Add one more entity from the client directory, then remove it | Directory badge shown; back to 6 |
| 5 | **Save draft** | "Draft saved"; URL gets `?id=`; green panel "Ready to issue: next number …" (if the series is confirmed); words "SG DOLLARS: THREE THOUSAND THREE HUNDRED ONLY" |
| 6 | Set a future invoice date and save | "Future invoice dates are not allowed." |
| 7 | Set qty 0 on a free-text line | Error on that line's Qty |
| 8 | Backdate the invoice date and save | Info: "Backdated: the invoice date is before today." |
| 9 | Change something without saving, then try to leave the page | Browser asks to confirm leaving |
| 10 | Open the same draft in two tabs, save in both | Second: "changed by someone else… Reload" |

### B5. Draft preview (watermark)

| # | Step | Expected |
|---|---|---|
| 1 | **Preview PDF** | New tab: A4 PDF, title "INVOICE: DRAFT", diagonal "DRAFT, NOT FOR ISSUE" across the page centre |
| 2 | A draft with more than ~35 audited entities | 2+ pages; column headers repeated; watermark on **every** page; "Draft invoice · Page n of m" |
| 3 | A draft for **Axiscert** (not ready) | Preview works; «TBC» values printed; watermark present |
| 4 | Activity log | `preview_invoice_pdf` row per preview |

### B6. Issue

| # | Step | Expected |
|---|---|---|
| 1 | **Issue…** | Modal shows entity, expected number, total, due date, and "cannot be edited" |
| 2 | **Issue now** | Redirect to the view page: "Invoice 26xxx was issued and its PDF stored" |
| 3 | **Download PDF** | File name like `26302 World Health SafetyTec - RM Audit.pdf`; no watermark; Due Date and Payment terms printed; Your Ref only if filled in |
| 4 | Settings → number series | Next number advanced by 1 |
| 5 | Open `invoice_edit.php?id=<issued id>` | Redirects to the view page (no editing) |
| 6 | Change the bank details in Settings, then download the issued PDF again | **Unchanged**: stored file, old bank details |
| 7 | Activity log | `issue_invoice` with number, total and PDF SHA-256 |

### B7. Issue blocked (nothing consumed)

For each case: HTTP 409 with the list of reasons, the invoice stays a draft, the counter does not move, no file is created, and an `issue_blocked` row is logged.

| # | Case | Expected reason |
|---|---|---|
| 1 | Axiscert draft (TBC settings) | Every TBC field, GST status, series locked |
| 2 | Party with no billing address | "Billing address is required before issuing." |
| 3 | Total 0.00 (rate 0) | "Total must be more than 0.00." |
| 4 | Line title containing `«TBC»` | "The invoice text still contains a «TBC» placeholder." |
| 5 | Invoice dated last year with no confirmed series for that year | "Invoice number series for 2025 does not exist yet." |
| 6 | Storage folder problem (e.g. set `storage_path` to a missing folder) | Storage reason; restore afterwards |

### B8. Duplicate numbering under two simultaneous issues

| # | Step | Expected |
|---|---|---|
| 1 | Prepare two ready drafts. Open each in its own browser window. | — |
| 2 | Open the Issue modal in both, then click **Issue now** in both as close together as possible | Both succeed with **consecutive, different** numbers; two PDFs in storage |
| 3 | Run `SELECT invoice_no, COUNT(*) FROM inv_invoices WHERE invoice_no IS NOT NULL GROUP BY entity_code, invoice_no HAVING COUNT(*) > 1;` | 0 rows |

Automated evidence: two threads issuing at the same moment received 26304 and 26305 on both servers.

### B9. Failure after the PDF is written (LOCAL ONLY)

The rollback path is exercised automatically by replacing the year folder with a symlink, which storage refuses. To repeat it by hand on local dev:

```bash
cd invoice-system/storage/invoices/EHS
mv 2026 2026_real && ln -s 2026_real 2026     # break storage on purpose
```

Issue a draft → error "Issuing failed and was fully rolled back (no number used, no file kept)". The draft is still a draft and the counter is unchanged. Then restore it:

```bash
rm 2026 && mv 2026_real 2026
```

### B10. Orphan file (crash between write and commit)

| # | Step (LOCAL ONLY) | Expected |
|---|---|---|
| 1 | Create an empty file `storage/invoices/EHS/2026/<next number>.pdf` | — |
| 2 | Issue a draft | Succeeds with that number; the stray file is renamed `<number>.orphan-<timestamp>.pdf` (kept, never served); the `issue_invoice` log mentions it |

### B11. Stored-file integrity

| # | Step (LOCAL ONLY) | Expected |
|---|---|---|
| 1 | Append a byte to an issued invoice's PDF in storage, then download it | Refused (500 "integrity check"); `pdf_integrity_failure` logged |
| 2 | Restore the file | Download works; file matches the stored SHA-256 |

### B12. Duplicate and delete

| # | Step | Expected |
|---|---|---|
| 1 | View an issued invoice → **Duplicate as new draft** | New draft: same lines and entities, today's date, Your Ref cleared |
| 2 | Draft → **Delete draft** → confirm | Gone; its lines and entities are gone; `delete_invoice_draft` logged |
| 3 | Try to delete an issued invoice via the API | 409 "Only drafts can be deleted" |

### B13. List, filters, overdue

| # | Step | Expected |
|---|---|---|
| 1 | Invoices list | Drafts first, then newest first. Tiles: Drafts, Outstanding, Overdue, Paid/Credited/Void. |
| 2 | Issue an invoice dated more than 30 days ago (30-day terms) | Red "Overdue" badge; counted in the Overdue tile and filter |
| 3 | Search by number, by party, by Your Ref | Matches |
| 4 | Filter: status Open; entity; date range | Totals follow the filter |

### B14. Paper size

| # | Step | Expected |
|---|---|---|
| 1 | Settings → Module options → US Letter → Save | Saved and logged; new previews are 8.5 × 11 in |
| 2 | Download an invoice issued on A4 | Still A4 (stored file) |
| 3 | Set back to A4 | — |

### B15. Edge cases from the mandate

| Case | Where tested |
|---|---|
| Duplicate numbering, two simultaneous issues | B8 |
| Partial payment / overpayment / credited | Status rules unit-tested on both servers (10 checks: partial → partially_paid, overpayment rolled back, mixed → paid, fully credited → credited, CN void → back). UI arrives in Stage 4. |
| Void | Stage 4 |
| Credit note | Stage 4 (the `credited` status and its rules are ready) |
| NULL-address party | B7 #2: draft saves with a warning, issue blocked |
| Entity switch | Change the entity on a draft: the service list re-filters, a service of the other entity is refused, the GST rule and readiness follow the new entity, and the number comes from that entity's series |

### B16. Security spot checks

| # | Step | Expected |
|---|---|---|
| 1 | Logged out: open `…/invoice-system/api/invoice_pdf.php?id=1` | 401 JSON, no PDF |
| 2 | As admin: open the same URL | 403 |
| 3 | Party name `<img src=x onerror=alert(1)>`, line title `<script>…</script>` | Shown as text in the editor, list and PDF; nothing runs |
| 4 | Open `…/invoice-system/storage/invoices/EHS/2026/<n>.pdf` directly | 403 (deny-all). Check this on cPanel: PHP's built-in server ignores `.htaccess`. |

### B17. Reconciliation (read-only, production-safe)

```sql
-- Issued invoices: subtotal = sum of lines, total = subtotal + GST
SELECT i.invoice_no, i.subtotal, SUM(l.amount) AS lines_sum, i.gst_amount, i.total
FROM inv_invoices i JOIN inv_invoice_lines l ON l.invoice_id = i.id
WHERE i.status <> 'draft'
GROUP BY i.id, i.invoice_no, i.subtotal, i.gst_amount, i.total
HAVING i.subtotal <> SUM(l.amount) OR i.total <> i.subtotal + i.gst_amount;      -- expect 0 rows

-- Every issued invoice has a stored PDF and a hash
SELECT invoice_no FROM inv_invoices
WHERE status <> 'draft' AND (pdf_file IS NULL OR pdf_sha256 IS NULL);            -- expect 0 rows

-- No duplicate numbers per entity
SELECT entity_code, invoice_no, COUNT(*) FROM inv_invoices
WHERE invoice_no IS NOT NULL GROUP BY entity_code, invoice_no HAVING COUNT(*) > 1; -- expect 0 rows

-- Status drift (Stage 1 §B7 query, now including 'credited')
SELECT i.invoice_no, i.status,
  CASE WHEN i.status IN ('draft','void') THEN i.status
       WHEN i.total - COALESCE(p.paid,0) - COALESCE(c.cr,0) = 0
            THEN IF(COALESCE(p.paid,0) = 0 AND COALESCE(c.cr,0) = i.total, 'credited', 'paid')
       WHEN i.total - COALESCE(p.paid,0) - COALESCE(c.cr,0) < i.total THEN 'partially_paid'
       ELSE 'issued' END AS ledger_status
FROM inv_invoices i
LEFT JOIN (SELECT invoice_id, SUM(amount) paid FROM inv_payments WHERE reversed_at IS NULL GROUP BY invoice_id) p ON p.invoice_id = i.id
LEFT JOIN (SELECT invoice_id, SUM(total) cr FROM inv_credit_notes WHERE status = 'issued' GROUP BY invoice_id) c ON c.invoice_id = i.id
HAVING i.status <> ledger_status;                                                  -- expect 0 rows
```

All joins above are between `inv_` tables only (one collation).

---

## D. Security notes (Stage 3)

- **Authorisation:** all 4 new pages and 6 new APIs start with `ehs_require_role(INV_ROLES)` (super_admin only); APIs return a 401/403 JSON response. Verified for anonymous users and admin.
- **CSRF:** every POST, PUT and DELETE calls `ehs_verify_csrf()` (tested).
- **Issue integrity:**
  - row-locked, all-or-nothing;
  - the number is consumed only on COMMIT;
  - the file is deleted on any failure;
  - an orphan is moved aside, never served;
  - stored files are hash-verified on every download.
- **Immutable history:**
  - issued invoices cannot be edited or deleted (PHP checks plus the DB CHECK and FK);
  - downloads never re-render;
  - snapshots keep the bank details, address and category as they were at issue.
- **PDF rendering:**
  - Dompdf runs with remote fetching, PHP and JavaScript **off**, `chroot` set to the logo folder;
  - every value is HTML-escaped;
  - the logo is embedded from the module folder as a data URI (no URLs, no user paths).
- **Storage:**
  - deny-all `.htaccess`, checked before every issue;
  - absolute paths only, symlinks refused;
  - `realpath()` containment on every read and write;
  - files are 0640 and folders 0750.
  - A folder outside `public_html` is recommended (TBC #29).
- **Downloads:** `Content-Disposition` file names are sanitised; `Cache-Control: private, no-store`; `nosniff`; `X-Frame-Options: SAMEORIGIN`.
- **SQL:**
  - prepared statements only; no named placeholder is repeated (scanned);
  - LIMIT/OFFSET are server integers; LIKE wildcards are escaped;
  - table names in the services API come from a fixed pair.
- **Output escaping:** server values are escaped in PHP (`htmlspecialchars`) and in JS (`invEscape`, which escapes quotes for attributes). An XSS payload in party and line text did not execute in the browser test.
- **Fraud-sensitive actions logged:** issue (with SHA-256), blocked issues with reasons, PDF downloads and previews, integrity failures, service rate changes (before → after), paper-size changes.
- **Hygiene list:** still with the owner, not built around.

---

## E. Test evidence (this delivery)

| Suite | MySQL 8.0.46 | MariaDB 10.11.14 |
|---|---|---|
| Stage 3 end-to-end (access, CSRF, services, drafts, preview, issue, blocked issues, rollback, orphan, concurrency, list, duplicate/delete, paper, environment check, DB CHECK, regression pages) | 120/120 | 120/120 |
| Status recompute (issued / partially_paid / paid / credited / overpayment rollback / CN void) | 10/10 | 10/10 |
| Stage 2 suite on Stage 3 code | 100/100 | — |
| Browser UI (party pick, paste list, directory entity, save, preview, issue modal, download name, services, environment panel, XSS, phone width) | 24/24, no JS errors | — |
| Migration 003 run twice; accreditation fix; no collation named | OK | OK |
| Storage safeguards (blank, outside root, relative, missing, symlink, no .htaccess) | 9/9 | — |
| PHP error log during all runs | 0 warnings / notices | 0 |
