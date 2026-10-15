# Stage 2 — Zero-Regression, Manual QA and Security Checklists

Scope: module skeleton, navigation, Settings (TBC editing, readiness, number series) and Billed Parties with the read-only client-directory lookup. **No database schema change** in this stage; the Stage 1 schema must already be installed.

Run **B** on the local dev site first (`localhost:8888`), then the production-safe subset (**B1–B3, B10**) after deploying.

---

## A. Zero-regression checklist

### A1. Existing files touched

**Exactly one existing file changed:** `includes/sidebar_nav.php`, the approved nav change. `admin_index.php` is **not** changed (see README §1A).

The full diff is below. It is additive only: no existing line was edited or removed.

```diff
--- includes/sidebar_nav.php (original)
+++ includes/sidebar_nav.php (Stage 2)
@@ -35,6 +35,7 @@
  *   Client Management: cm_index, cm_client_detail, renewal_dashboard,
  *              audit_extract, cm_import
  *   CRM: crm_board, crm_list, crm_lead_detail
+ *   Invoices: inv_home, inv_parties, inv_settings  (super_admin only)
  */
 
 $role = $user['role'] ?? 'auditor';
@@ -43,7 +44,12 @@
 
 $cmPages = ['cm_index', 'cm_client_detail', 'renewal_dashboard', 'audit_extract', 'cm_import'];
 $crmPages = ['crm_board', 'crm_list', 'crm_lead_detail'];
+$navInvPages = ['inv_home', 'inv_parties', 'inv_settings'];
+$navInvVisible = $role === 'super_admin';
 $currentApp = in_array($activePage, $cmPages, true) ? 'cm' : (in_array($activePage, $crmPages, true) ? 'crm' : 'scheduler');
+if ($navInvVisible && in_array($activePage, $navInvPages, true)) {
+    $currentApp = 'inv';
+}
 
 $schedulerHome = $isAdmin ? ehs_url('admin/index.php') : ehs_url('auditor/index.php');
 ?>
@@ -115,6 +121,9 @@
         <a class="app-switcher-btn <?= $currentApp === 'cm' ? 'active' : '' ?>" href="<?= ehs_url('client-management/index.php') ?>">📋 Client Management</a>
         <a class="app-switcher-btn <?= $currentApp === 'crm' ? 'active' : '' ?>" href="<?= ehs_url('crm/index.php') ?>">🎯 CRM</a>
         <?php endif; ?>
+        <?php if ($navInvVisible): ?>
+        <a class="app-switcher-btn <?= $currentApp === 'inv' ? 'active' : '' ?>" href="<?= ehs_url('invoice-system/index.php') ?>" title="Invoices">🧾 <span class="label">Invoices</span></a>
+        <?php endif; ?>
     </nav>
     <button class="app-mobile-toggle" id="app-sidebar-toggle" aria-label="Toggle navigation">☰</button>
     <div class="app-topbar-user">
@@ -154,6 +163,12 @@
             <div class="app-sidebar-title">CRM</div>
             <a href="<?= ehs_url('crm/index.php') ?>" class="<?= $activePage === 'crm_board' ? 'active' : '' ?>">Board</a>
             <a href="<?= ehs_url('crm/list.php') ?>" class="<?= $activePage === 'crm_list' ? 'active' : '' ?>">List</a>
+
+        <?php elseif ($currentApp === 'inv'): ?>
+            <div class="app-sidebar-title">Invoices</div>
+            <a href="<?= ehs_url('invoice-system/index.php') ?>" class="<?= $activePage === 'inv_home' ? 'active' : '' ?>">Overview</a>
+            <a href="<?= ehs_url('invoice-system/parties.php') ?>" class="<?= $activePage === 'inv_parties' ? 'active' : '' ?>">Billed Parties</a>
+            <a href="<?= ehs_url('invoice-system/settings.php') ?>" class="<?= $activePage === 'inv_settings' ? 'active' : '' ?>">Settings</a>
         <?php endif; ?>
     </aside>
 
```

**What the change does:**
- Adds a fourth top-bar switcher, **🧾 Invoices**, shown only when `role === 'super_admin'`.
- Adds an "Invoices" sidebar section when the page sets `$activePage` to `inv_home`, `inv_parties` or `inv_settings`.
- Uses new variable names (`$navInvPages`, `$navInvVisible`) that no existing page uses. `client-management/renewal_dashboard.php` already defines `$isSuperAdmin`, so that name was deliberately avoided.
- Wraps the button label in `span.label`, so the existing mobile rule hides the word and shows only the icon.

### A2. Everything else

- **New folder:** everything else is under `invoice-system/`.
- **Database:** no table altered. The module reads `cm_clients` (fixed columns, read-only), `information_schema.COLUMNS` and `inv_*` only, and writes only `inv_*`.
- **No new Composer dependency and no new CDN host.** The pages use the same Bootstrap 5.3.3 and Bootstrap Icons 1.11.3 jsdelivr URLs as CRM.

### A3. Must still work (smoke test after deploying)

Log in as each role in turn.

- [ ] **Login and logout** for super_admin, admin and auditor.
- [ ] **super_admin:**
  - [ ] the top bar shows Auditor Portal, Client Management, CRM, **Invoices**;
  - [ ] each switcher opens its app, and the active one is highlighted.
- [ ] **admin:** the top bar shows Auditor Portal, Client Management and CRM, with **no Invoices**.
- [ ] **admin:** opening `…/invoice-system/index.php` directly gives **403**.
- [ ] **auditor:** the auditor home, day schedule and availability pages load, with no Invoices link.
- [ ] **Auditor Portal (super_admin):** Master Schedule, My Day Schedule, Auditor Availability, Auditors, Clients, Schemes, Holidays, Export, Activity Log, User Accounts and My Account all load, with the sidebar unchanged.
- [ ] **Client Management:**
  - [ ] Client Directory (entity filter), client detail, Renewal Dashboard, Audit Extract with Excel export, and Bulk Import all load;
  - [ ] the Renewal Dashboard's super-admin-only controls still show for super_admin and stay hidden for admin.
- [ ] **CRM:**
  - [ ] Board, List and lead detail load;
  - [ ] creating a quotation works, and **the quotation PDF downloads**.
- [ ] **Crons** (`client-management/cron/*`, `crm/cron/*`) are unchanged and run as before.

**Verified on the archived code** (PHP 8.3, MySQL 8.0.46), with the new nav, as super_admin, admin and auditor:
- 18 existing pages returned 200 with no PHP warnings.
- `renewal_dashboard.php`'s super-admin flag was unchanged for both roles.

**Pre-existing, unrelated:** in the archive, `crm/api/leads.php` returns 500 (undefined `CRM_FUNNEL_STAGES`). It does not include the nav file and was not touched (README TBC #24).

---

## B. Manual QA checklist — Stage 2

### B1. Deploy and deny rules

| # | Step | Expected |
|---|---|---|
| 1 | Open `…/invoice-system/includes/inv_helpers.php` in a browser (logged in or not) | 403 (Apache `.htaccess`; PHP's built-in server ignores it, so check this on cPanel) |
| 2 | Open `…/invoice-system/db/inv_schema.sql` | 403 |
| 3 | Open `…/invoice-system/docs/STAGE2_CHECKLISTS.md` | 403 |
| 4 | Open `…/invoice-system/storage/` | 403 |
| 5 | Open `…/invoice-system/assets/img/logo_ehs.png` | 200 (brand logo, public by design) |

### B2. Access control

| # | Step | Expected |
|---|---|---|
| 1 | Log out, then open `…/invoice-system/settings.php` | Redirect to the login page |
| 2 | Logged out, open `…/invoice-system/api/parties.php` | 401 JSON: `session_expired: true` |
| 3 | Log in as **admin**, open `…/invoice-system/index.php`, `parties.php`, `settings.php` | 403 each |
| 4 | As admin, open `…/invoice-system/api/settings.php` | 403 JSON |
| 5 | Log in as **auditor** and repeat 3 | 403 each |
| 6 | Log in as **super_admin** and click **🧾 Invoices** | Overview opens; the sidebar shows Overview / Billed Parties / Settings |

### B3. Overview and readiness (seeded state)

| # | Step | Expected |
|---|---|---|
| 1 | Open the Overview | EHS: **Issuing blocked**, 1 item (invoice series 2026 locked) |
| 2 | Same page | Axiscert: **Issuing blocked**, 12 TBC items + GST status + locked series; credit notes also need the CN prefix |
| 3 | Settings → tab badges | EHS "1 to fix", Axiscert "14 to fix" |

### B4. Settings: TBC editing (Axiscert tab)

| # | Step | Expected |
|---|---|---|
| 1 | Open the Axiscert tab | TBC fields are highlighted amber with a **TBC** badge |
| 2 | Clear **Cheque payee** and Save | Error under the field: "required (enter «TBC» if not known yet)"; nothing saved |
| 3 | Set **Email** to `abc` and Save | Field error: not a valid email |
| 4 | Set **Invoice number prefix** to `A X!` and Save | Field error: letters, digits, `-`, `/` only |
| 5 | Enter a real **Cheque payee** only and Save | Toast "Saved 1 change(s)"; Axiscert count drops by 1; others stay TBC |
| 6 | Activity log check: run `SELECT details FROM inv_activity_log WHERE action='update_entity_settings' ORDER BY id DESC LIMIT 1;` | Shows `cheque_payee: '«TBC»' -> '…'` |
| 7 | Type `TBC-pending` into **Bank code** and Save | Saves, but the field stays highlighted and still blocks issuing |
| 8 | Set **GST status** = GST-registered and Save, leaving GST Reg No empty | Readiness shows "GST-registered but GST Reg No is missing". (Doing the same on the **EHS** tab also shows a blue warning that its T&C still say invoices are NOT subject to GST; revert afterwards.) |
| 9 | Set GST status back to "Not confirmed yet" and Save | Back to the previous state |
| 10 | Set **Accreditation line** to blank and Save | Accepted (blank = no line printed); that TBC item disappears |
| 11 | **Same-second edit conflict:** open Settings in two tabs. Save a change in tab 1, then a different change in tab 2 **without reloading** | Tab 2: "changed by someone else… Reload"; tab 1's change is kept |
| 12 | Choose another logo in the Logo dropdown | Preview updates; only `logo_*.png/jpg` files are offered |

### B5. Number series

| # | Step | Expected |
|---|---|---|
| 1 | EHS tab → Number series → **Confirm next number** (2026 invoice) | Modal explains the assumed format; preview is blank |
| 2 | Enter your real next number (e.g. `302` **only if** 26301 was the last issued) | Preview shows `26302` |
| 3 | Click **Confirm number** without ticking the box | "Required" under the checkbox; nothing saved |
| 4 | Enter `1000` with the box ticked | Error: does not fit in 3 digits |
| 5 | Enter the correct number, tick the box, Confirm | Toast "Confirmed. Next number: 26302"; row shows Confirmed with your name and time; **EHS tab shows "Ready"** |
| 6 | Axiscert → Confirm a credit-note series while the CN prefix is TBC | Error: prefix still TBC; set it first (blank allowed) |
| 7 | Click **+ 2027 invoice series** | Modal for 2027; confirming creates the row (preview `27…`) |
| 8 | Activity log | `confirm_number_series` rows show before → after |

> **Do not confirm real EHS/Axiscert numbers on production until the owner has confirmed them (README TBC #10, #11, #14–16).** On local dev, any value can be used for testing.

### B6. Billed parties: manual

| # | Step | Expected |
|---|---|---|
| 1 | **New party**: name only (**NULL address**), Save | Created; the list shows an amber "No address" badge |
| 2 | **New party**: `World Health SafetyTec Pte. Ltd.`, address `67 Ubi Road 1 #07-07, Oxley Bizhub Singapore 408730`, terms `30` | Created |
| 3 | **New party**: `World Health SafetyTec Pte Ltd` (no dots) | Amber "Possible duplicate" box lists the existing party; **Save anyway** appears |
| 4 | Click **Save anyway** | Created |
| 5 | Accounts email `bad`, CC `a@b.co, nope`, terms `400` | 3 field errors; nothing saved |
| 6 | Name `<b>Test</b> "Q" & Co` | Saved; the list shows the text literally (no bold, no script) |
| 7 | Edit a party, change Attention, Save | "Party updated"; the row updates |
| 8 | Open the same party in two tabs; save in tab 1, then save in tab 2 | Tab 2: "changed by someone else… Reload" |
| 9 | Edit → **Deactivate** → confirm | Hidden from Active; visible under Inactive / All with an "Inactive" badge |
| 10 | Inactive filter → Edit → **Reactivate** | Back under Active |
| 11 | Search `%` | Literal match only (does not list everything) |
| 12 | More than 50 parties (if available) | Prev / Next paging works and the count text is correct |

### B7. Billed parties: copy from client directory (Option A)

| # | Step | Expected |
|---|---|---|
| 1 | **New party** → type one letter in "Copy from client directory" | Nothing happens (2 characters minimum) |
| 2 | Type 2+ letters of a known client | Up to 20 matches with address/email and an **entity badge** |
| 3 | Pick a client **with** an address | The form fills (name, UEN if any, address, contact as Attention, phone, email, usual entity from `cm_clients.entity`); grey note "Copied from client directory record #N" |
| 4 | Pick a client **without** an address | Note says the client has no address on file; address stays blank |
| 5 | Pick a client whose phone has a line break | Phone shows the numbers joined with " / " |
| 6 | Edit any copied field and Save | Saved with source = Directory; **Client Management shows the client unchanged** |
| 7 | Create another party from the **same** client record | Duplicate warning "Same client-directory record" |
| 8 | Edit a copied party | Note "Originally copied from … #N"; the lookup box is hidden (source is fixed) |

### B8. Entity switch (set-up level)

| # | Step | Expected |
|---|---|---|
| 1 | Set a party's **Usual issuing entity** to Axiscert and Save | Saved (used as the default on new invoices in Stage 3) |
| 2 | Overview → each entity card | Readiness is per entity: EHS can be Ready while Axiscert stays blocked |

### B9. Session and CSRF

| # | Step | Expected |
|---|---|---|
| 1 | Open Settings, log out in another tab, then click Save | Toast "Your session has expired"; redirect to login |
| 2 | Open Settings; in the browser dev-tools console run `window.INV_CSRF_TOKEN = 'x'`, then click Save | Error toast "Not allowed (session or security token expired; reload the page)"; nothing saved (server returns 403) |

### B10. Read-only reconciliation (production-safe)

```sql
-- Client Management untouched by this module: every cm_ table's checksum is unchanged before vs after Stage 2 QA.
CHECKSUM TABLE cm_clients;

-- Every party copied from the directory points at an existing client (informational; no FK by design).
SELECT p.id, p.display_name, p.source_client_id
FROM inv_parties p
LEFT JOIN cm_clients c ON c.id = p.source_client_id
WHERE p.source = 'cm_lookup' AND c.id IS NULL;   -- expected 0 rows (unless a client was later deleted)

-- Activity log for this stage
SELECT action, COUNT(*) FROM inv_activity_log GROUP BY action;
```

**Corrected in Stage 3:** this join compares INTEGER ids only (`p.source_client_id = c.id`), so collation does not apply. Your live `inv_` tables use `utf8mb4_general_ci` while `cm_*` / `users` may differ: never compare or join TEXT columns across `inv_` and `users` / `cm_*` / `crm_*` without an explicit COLLATE.

---

## C. Security notes (Stage 2)

- **Authorisation:**
  - Every page and API starts with `ehs_require_role(INV_ROLES)` (super_admin only); APIs pass `true` so they return a 401/403 JSON response.
  - The Invoices switcher is hidden from other roles, but the **server checks are the control**: they were verified for anonymous users, admin and auditor on all 3 pages and 4 APIs.
- **CSRF:** every POST/PUT calls `ehs_verify_csrf()`. Requests without a token, or with a wrong one, return 403 and write nothing (tested).
- **SQL:**
  - Prepared statements throughout. **No named placeholder is used twice in any query.** Lists use positional `?`, and named queries use distinct prefixes (`:c_name`/`:c_email`, `:e_inv`/`:e_cn`, `:w_id`…).
  - Tested against the portal's native prepares (`ATTR_EMULATE_PREPARES=false`).
  - LIMIT/OFFSET are server-computed integers.
  - Settings UPDATE column names come from a fixed whitelist, never from input.
  - LIKE wildcards in search terms are escaped.
- **Input validation:**
  - Whitelisted fields only; unknown keys are ignored (tested).
  - Lengths are capped to the column sizes; control characters are stripped.
  - Emails are validated; integers are range-checked.
  - The GST rate format is enforced; entity codes are checked against `inv_settings`.
  - Logo names must match `logo_*.png|jpg` and exist in the module folder, so no path traversal (`../../config.php` is rejected, tested).
  - The JSON body is capped at 256 KB.
- **Output escaping:**
  - PHP output uses `htmlspecialchars`.
  - In JS, every server value goes through `invEscape()`, which escapes `& < > " '`. It is safe in attributes too: a bug in the first draft, which did not escape quotes, was fixed before testing.
  - XSS payloads in party name, address and attention rendered as text in a real browser, and the payload flag never fired.
- **Response headers:** pages send `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff` and `Referrer-Policy: same-origin`. JSON responses send `Cache-Control: no-store`.
- **Cross-module boundary:**
  - The `cm_clients` lookup is one fixed-column SELECT, read-only, guarded by the role check.
  - It never 500s when the column or table is missing (tested by dropping the column and renaming the table).
  - `cm_clients` checksum was unchanged after all tests.
- **Fraud-sensitive settings:**
  - Bank, payee, PayNow, Co Reg No and legal-name changes are logged with before → after values.
  - The page shows who last saved.
  - **Recommendation:** after any bank-detail change, confirm it with the owner by phone before issuing.
- **Concurrency:** settings and party edits are checked by content hash under a row lock. Number confirmation runs under the series row lock that issuing will use.
- **Folders:** `includes/`, `db/`, `docs/` and `storage/` deny all web access. Logos are intentionally public.
- **Deployment:** do not leave the delivery zip or any `* copy.php` inside the web root. This adds to the hygiene list you are already handling.
- **Not yet built:** logo upload (deliberately; replace files via cPanel); invoices, PDFs and exports (Stage 3+).
