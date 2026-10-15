# Stage 1 — Zero-Regression and Manual QA Checklists

Scope: `invoice-system/db/inv_schema.sql` only.

**Run sections B5–B7 on the LOCAL dev database** (`localhost:8888`), never on production. They insert test rows and, in B6, commit them. On production, run only A, B1–B4 and B7.

---

## A. Zero-regression checklist

### A1. Existing files touched

**None.** Stage 1 adds new files only:

| File | Change |
|---|---|
| `invoice-system/db/inv_schema.sql` | new |
| `invoice-system/README.md` | new |
| `invoice-system/docs/STAGE1_CHECKLISTS.md` | new |

There is no change to `includes/`, `admin/`, `auditor/`, `api/`, `client-management/`, `crm/`, `includes/sidebar_nav.php` or `admin_index.php`. The nav change comes in Stage 2 and will ship with its own diff.

### A2. Database objects touched

- [ ] **Only `CREATE TABLE IF NOT EXISTS inv_*` statements and INSERTs into `inv_*` tables.** The script contains no `ALTER`, `DROP`, `UPDATE` or `DELETE` on any existing table.
  - Check: `grep -inE "alter|drop|update |delete" invoice-system/db/inv_schema.sql` only matches comments and `ON UPDATE CURRENT_TIMESTAMP`.
- [ ] **No foreign key points from `inv_*` to `users`, `cm_*`, `crm_*` or scheduler tables.** Run:
  ```sql
  SELECT TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'inv\_%' AND REFERENCED_TABLE_NAME IS NOT NULL
    AND REFERENCED_TABLE_NAME NOT LIKE 'inv\_%';
  ```
  Expected: **0 rows.**
- [ ] **Table count rose by exactly 14, all named `inv_`.** Compare `SHOW TABLES` before and after.

### A3. Existing functionality to smoke-test after import

Log in as super_admin and check each item:

- [ ] Login and logout. The `activity_log` login row is still written.
- [ ] **Auditor Portal:** Master Schedule loads, a day schedule opens, the Auditors/Clients/Schemes/Holidays pages load, Export runs, and Activity Log loads.
- [ ] **Auditor login:** an auditor account still sees only the Auditor Portal.
- [ ] **Client Management:**
  - Client Directory loads and filters by entity.
  - A client detail page opens.
  - The Renewal Dashboard, Audit Extract (including Excel export) and Bulk Import pages load.
- [ ] **CRM:**
  - Board and List load, a lead detail page opens, a quotation can be created, and **the quotation PDF downloads**.
- [ ] **Crons** (`client-management/cron/*`, `crm/cron/*`) run without new errors. Nothing in them changed.

---

## B. Manual QA checklist — Stage 1

### B1. Pre-flight (production and local)

- [ ] **`SELECT VERSION();` reports MySQL 8.0.16 or later.** Record the version: ________. If it reports MariaDB, STOP and report it.
- [ ] **`SELECT @@sql_mode;` includes `STRICT_TRANS_TABLES`.** Record it: ________.
  - Without strict mode, an invalid ENUM value is stored as `''` with only a warning. The application whitelists every ENUM anyway, but record the mode.
- [ ] **A database backup exists and is stored outside the web root.**

### B2. Install and idempotency

- [ ] **Import `inv_schema.sql`.** Expected: the pre-flight row is returned and there are no errors.
- [ ] **Import it a second time.** Expected: no errors (only "already exists" warnings), and seed counts are unchanged:
  ```sql
  SELECT 'settings', COUNT(*) FROM inv_settings        -- 2
  UNION ALL SELECT 'series',   COUNT(*) FROM inv_number_series -- 4
  UNION ALL SELECT 'categories', COUNT(*) FROM inv_categories  -- 6
  UNION ALL SELECT 'services', COUNT(*) FROM inv_services      -- 1
  UNION ALL SELECT 'config',   COUNT(*) FROM inv_config;       -- 9
  ```
- [ ] **A re-import never overwrites edits.**
  1. `UPDATE inv_settings SET bank_branch = 'TEST-EDIT' WHERE entity_code = 'Axiscert';`
  2. Re-import the file.
  3. Confirm the value is still `TEST-EDIT`.
  4. Set it back: `UPDATE inv_settings SET bank_branch = '«TBC»' WHERE entity_code = 'Axiscert';`

### B3. Structure and collation

- [ ] **All 14 tables use InnoDB and `utf8mb4_0900_ai_ci`.** Expected: 14 rows, all identical.
  ```sql
  SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'inv\_%';
  ```
- [ ] **Every money column is DECIMAL(12,2).** Expected: **0 rows.**
  ```sql
  SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'inv\_%'
    AND COLUMN_NAME IN ('subtotal','gst_amount','total','amount','rate','default_rate')
    AND COLUMN_TYPE <> 'decimal(12,2)';
  ```

### B4. Seed values

- [ ] **EHS row matches invoice #26301:**
  - Co Reg No `201207714D`;
  - address `2 Kallang Avenue #08-16 CT Hub Singapore 339407`;
  - DBS `066-902165-8`, SWIFT `DBSSSGSG`;
  - PayNow `201207714D`;
  - `gst_registered = 0`, `gst_status_confirmed = 1`;
  - 3 T&C lines.
- [ ] **Axiscert row:**
  - legal name `AXISCERT PTE. LTD.`, Co Reg No `202310893C`;
  - address `190 Middle Rd, Fortune Centre #15-01 Singapore 188979`;
  - email `contact@axiscert.com.sg`;
  - the 12 invoice fields show `«TBC»` (the guillemets display correctly, not as `Â«`);
  - `gst_status_confirmed = 0`.
- [ ] **All 4 number-series rows have `next_value IS NULL` and `is_confirmed = 0`.**
- [ ] **TBC count per entity.** Expected: Axiscert 12, EHS 0.
  ```sql
  SELECT entity_code,
    (phone LIKE '%TBC%')+(accreditation_line LIKE '%TBC%')+(cheque_payee LIKE '%TBC%')+
    (bank_name LIKE '%TBC%')+(bank_code LIKE '%TBC%')+(bank_branch LIKE '%TBC%')+
    (bank_account_label LIKE '%TBC%')+(bank_account_no LIKE '%TBC%')+(bank_swift LIKE '%TBC%')+
    (paynow_uen LIKE '%TBC%')+(tnc_text LIKE '%TBC%')+(invoice_prefix LIKE '%TBC%') AS tbc_fields
  FROM inv_settings;
  ```

### B5. Constraint tests (LOCAL ONLY)

**Setup (valid rows; each statement must succeed).** The party has a NULL billing address, which is the NULL-address edge case:

```sql
INSERT INTO inv_parties (display_name, billing_address) VALUES ('QA Party Pte Ltd', NULL);
SET @p = LAST_INSERT_ID();
INSERT INTO inv_invoices (entity_code, party_id, invoice_date) VALUES ('EHS', @p, '2026-09-29');
SET @i = LAST_INSERT_ID();
INSERT INTO inv_invoice_lines (invoice_id, line_no, title, qty, rate, amount) VALUES (@i, 1, 'RM', 6, 550.00, 3300.00);
```

Run each statement below on its own. **Every one must be REJECTED** with the error shown.

| # | Statement | Expected error |
|---|---|---|
| 1 | `INSERT INTO inv_invoice_lines (invoice_id,line_no,title,qty,rate,amount) VALUES (@i,2,'x',3,33.33,100.00);` | `chk_inv_lines_amount` |
| 2 | `INSERT INTO inv_invoice_lines (invoice_id,line_no,title,qty,rate,amount) VALUES (@i,3,'x',0,10,0);` | `chk_inv_lines_qty` |
| 3 | `UPDATE inv_invoices SET invoice_no='26999' WHERE id=@i;` (draft with number) | `chk_inv_invoices_draft_no` |
| 4 | `UPDATE inv_invoices SET status='issued', invoice_no='26999', issued_at=NOW(), due_date='2026-10-29' WHERE id=@i;` (no snapshots / zero total) | `chk_inv_invoices_issued_req` |
| 5 | `UPDATE inv_invoices SET subtotal=3300, gst_amount=0, total=3000 WHERE id=@i;` | `chk_inv_invoices_amounts` |
| 6 | `UPDATE inv_invoices SET subtotal=3300, gst_rate=9, gst_amount=200, total=3500 WHERE id=@i;` | `chk_inv_invoices_gst_calc` |
| 7 | `UPDATE inv_invoices SET due_date='2026-01-01' WHERE id=@i;` | `chk_inv_invoices_due` |
| 8 | `UPDATE inv_invoices SET currency='USD' WHERE id=@i;` | `chk_inv_invoices_currency` |
| 9 | `INSERT INTO inv_invoices (entity_code,party_id,invoice_date) VALUES ('ACME',@p,'2026-10-01');` | FK `fk_inv_invoices_entity` |
| 10 | `UPDATE inv_number_series SET is_confirmed=1, confirmed_at=NOW() WHERE entity_code='EHS' AND doc_type='invoice';` | `chk_inv_series_confirmed` |
| 11 | `INSERT INTO inv_parties (display_name, source) VALUES ('X','cm_lookup');` | `chk_inv_parties_source` |

**Then issue the test invoice legitimately.** This must succeed:

```sql
UPDATE inv_invoices SET subtotal=3300, total=3300, status='issued', invoice_no='26999', issued_at=NOW(),
  due_date='2026-10-29', bill_to_name='QA Party Pte Ltd',
  amount_in_words='SG DOLLARS: THREE THOUSAND THREE HUNDRED ONLY',
  entity_snapshot=JSON_OBJECT('legal_name','EHS Universal Pte Ltd') WHERE id=@i;
```

**Each of these must now be REJECTED:**

| # | Statement | Expected error |
|---|---|---|
| 12 | `UPDATE inv_invoices SET status='void', voided_at=NOW() WHERE id=@i;` (no reason) | `chk_inv_invoices_void_req` |
| 13 | `INSERT INTO inv_payments (invoice_id,payment_date,amount,method) VALUES (@i,'2026-10-05',0,'paynow');` | `chk_inv_payments_amount` |
| 14 | `INSERT INTO inv_payments (invoice_id,payment_date,amount,method) VALUES (@i,'2026-10-05',10,'cash');` | Data truncated (strict mode) |
| 15 | `INSERT INTO inv_payments (invoice_id,payment_date,amount,method,reversed_at) VALUES (@i,'2026-10-05',10,'paynow',NOW());` | `chk_inv_payments_reversal` |
| 16 | `INSERT INTO inv_credit_notes (entity_code,invoice_id,cn_date,reason) VALUES ('Axiscert',@i,'2026-10-05','wrong rate');` (**entity switch**) | FK `fk_inv_cn_invoice` |
| 17 | `INSERT INTO inv_invoices (entity_code,party_id,invoice_date,status,invoice_no,issued_at,due_date,bill_to_name,amount_in_words,entity_snapshot,subtotal,total) VALUES ('EHS',@p,'2026-10-01','issued','26999',NOW(),'2026-10-31','x','y',JSON_OBJECT(),10,10);` (**duplicate number**) | Duplicate entry `uq_inv_invoices_number` |
| 18 | `INSERT INTO inv_payments (invoice_id,payment_date,amount,method) VALUES (@i,'2026-10-05',1000,'paynow');` must succeed, then `DELETE FROM inv_invoices WHERE id=@i;` | FK `fk_inv_payments_invoice` |

**Positive check:** the same number `26999` under entity `Axiscert` is allowed, because numbering is per entity:

```sql
INSERT INTO inv_invoices (entity_code,party_id,invoice_date,status,invoice_no,issued_at,due_date,bill_to_name,amount_in_words,entity_snapshot,subtotal,total)
VALUES ('Axiscert',@p,'2026-10-01','issued','26999',NOW(),'2026-10-31','x','y',JSON_OBJECT(),10,10);
```

### B6. Duplicate numbering under two simultaneous issues (LOCAL ONLY)

Use two terminal windows: `mysql -u… ehsuser`. In phpMyAdmin, two tabs work only if each runs the whole block in one go.

1. **Unlock the EHS invoice series for the test:**
   ```sql
   UPDATE inv_number_series SET next_value=900, is_confirmed=1, confirmed_at=NOW()
   WHERE entity_code='EHS' AND doc_type='invoice' AND period_year=2026;
   ```
2. **Window A:** run the lock statements and **do not commit yet**.
   ```sql
   START TRANSACTION;
   SELECT next_value INTO @n FROM inv_number_series
     WHERE entity_code='EHS' AND doc_type='invoice' AND period_year=2026 AND is_confirmed=1 FOR UPDATE;
   SELECT @n;  -- shows 900
   ```
3. **Window B:** run the same three statements. **Expected: B hangs, waiting on A's lock.**
4. **Window A:** finish and commit.
   ```sql
   UPDATE inv_number_series SET next_value=next_value+1
     WHERE entity_code='EHS' AND doc_type='invoice' AND period_year=2026;
   INSERT INTO inv_invoices (entity_code,party_id,invoice_date,status,invoice_no,issued_at,due_date,bill_to_name,amount_in_words,entity_snapshot,subtotal,total)
   VALUES ('EHS',@p,'2026-10-05','issued',CONCAT('26',LPAD(@n,3,'0')),NOW(),'2026-11-04','x','y',JSON_OBJECT(),10,10);
   COMMIT;
   ```
   In window A, `@p` is unset. First run `SET @p = (SELECT id FROM inv_parties WHERE display_name='QA Party Pte Ltd');` in **each** window.
5. **Expected:** B unblocks immediately and `SELECT @n` in B shows **901** (not 900). Finish B with the same statements and COMMIT.
6. **Verify:** `SELECT invoice_no FROM inv_invoices WHERE invoice_no IN ('26900','26901');` returns 2 distinct rows, and `next_value` is 902.

> This was executed on MySQL 8.0.46 during development. Session B started 0.5 s after A, waited for A's commit, and received the next number. No duplicate was produced.

### B7. Reconciliation queries (production-safe, read-only; run after every later stage)

**Status drift.** Expected: **0 rows** once Stage 3/4 code writes the data. (In B5 you insert a payment by hand without recomputing, so test invoice `26999` will correctly appear here. That proves the query works.)

```sql
SELECT i.id, i.entity_code, i.invoice_no, i.status AS stored_status,
  CASE WHEN i.status IN ('draft','void') THEN i.status
       WHEN i.total - COALESCE(p.paid,0) - COALESCE(c.credited,0) = 0 THEN 'paid'
       WHEN i.total - COALESCE(p.paid,0) - COALESCE(c.credited,0) < i.total THEN 'partially_paid'
       ELSE 'issued' END AS ledger_status,
  i.total - COALESCE(p.paid,0) - COALESCE(c.credited,0) AS balance
FROM inv_invoices i
LEFT JOIN (SELECT invoice_id, SUM(amount) AS paid FROM inv_payments
           WHERE reversed_at IS NULL GROUP BY invoice_id) p ON p.invoice_id = i.id
LEFT JOIN (SELECT invoice_id, SUM(total) AS credited FROM inv_credit_notes
           WHERE status = 'issued' GROUP BY invoice_id) c ON c.invoice_id = i.id
HAVING stored_status <> ledger_status OR balance < 0;
```

**Subtotal equals the sum of lines.** Expected: **0 rows.**

```sql
SELECT i.id, i.invoice_no, i.subtotal, COALESCE(SUM(l.amount),0) AS lines_sum
FROM inv_invoices i LEFT JOIN inv_invoice_lines l ON l.invoice_id = i.id
GROUP BY i.id, i.invoice_no, i.subtotal
HAVING i.subtotal <> lines_sum;
```

**Gaps or duplicates in a series**, for example EHS 2026. Review any gap: it should only come from a deliberately confirmed restart.

```sql
SELECT invoice_no FROM inv_invoices WHERE entity_code='EHS' AND invoice_no LIKE '26%' ORDER BY invoice_no;
```

### B8. Mandate edge cases: where each is covered

| Edge case | Stage 1 (database) | Full test in |
|---|---|---|
| Duplicate numbering, two simultaneous issues | B6 (lock) and B5 #17 (UNIQUE) | Stage 3 (two browsers) |
| Partial payment | B7 drift query computes `partially_paid` | Stage 4 |
| Overpayment attempt | B7 flags `balance < 0`; app rejects inside the lock | Stage 4 |
| Void | B5 #12 (reason required); B5 #18 (paid invoice cannot be deleted) | Stage 4 |
| Credit note | B5 #16 (same entity only) | Stage 4 |
| NULL-address party | B5 setup (party with NULL address accepted) | Stage 3 (PDF renders blank lines cleanly) |
| Entity switch | B5 #16; B5 positive check (same number, other entity) | Stage 3 (switching entity on a draft re-reads settings and readiness) |

### B9. Cleanup (LOCAL)

Use the dev-only reset in `README.md` §6, then re-import `inv_schema.sql` to return to the seeded state.
