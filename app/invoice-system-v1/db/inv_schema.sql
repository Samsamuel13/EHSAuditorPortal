-- =============================================================================
-- invoice-system/db/inv_schema.sql
-- EHS Universal admin portal: Invoice System module, Stage 1 (schema + seeds)
--
-- Target  : MySQL 8.0.16+ (CHECK constraints are enforced from 8.0.16; the
--           project dump header shows 8.0.40). Collation utf8mb4_general_ci,
--           matching `users` and cm_* tables.
--           NOT for MariaDB: utf8mb4_general_ci does not exist on most MariaDB
--           versions. Run the pre-flight SELECT below first and stop if
--           VERSION() does not report MySQL 8.0.16 or later.
--
-- Idempotent: safe to run more than once.
--   * Tables use CREATE TABLE IF NOT EXISTS (a re-run emits "already exists"
--     warnings, not errors).
--   * Seed rows use INSERT ... SELECT ... WHERE NOT EXISTS, so a re-run NEVER
--     overwrites values a super_admin has since edited on the Settings page.
--   * No `ADD COLUMN IF NOT EXISTS` (MariaDB-only syntax, #1064 on MySQL).
--   * No triggers, procedures or events (shared cPanel hosting often lacks
--     the privileges, and hidden DB logic is hard to audit).
--   Future column additions MUST use an information_schema.columns pre-flight
--   with PREPARE/EXECUTE, never ADD COLUMN IF NOT EXISTS.
--
-- Isolation:
--   * Every table is inv_-prefixed. Foreign keys exist ONLY between inv_ tables.
--   * No FK to `users`: user ids are plain INT UNSIGNED plus a name snapshot
--     (same pattern as crm_*).
--   * No FK to cm_clients: inv_parties.source_client_id and
--     inv_line_audited_entities.source_client_id are plain INT UNSIGNED.
--   * Nothing here alters, reads or references scheduler, cm_* or crm_* tables.
--   * crm_* tables use utf8mb4_general_ci. Never JOIN inv_ to crm_ without an
--     explicit COLLATE. The module has no such join today.
--
-- Money: DECIMAL(12,2) everywhere. Line amount = ROUND(qty * rate, 2) is
--   enforced by CHECK; header totals = subtotal + GST is enforced by CHECK.
--   Subtotal = SUM(line amounts) spans rows, so it is enforced by the
--   application inside the issue transaction and verified by the QA
--   reconciliation queries.
--
-- Placeholders: the literal marker «TBC» means "owner has not supplied this
--   value". The Stage 2/3 "entity ready to issue" check blocks issuing while
--   ANY printed or required field contains the ASCII substring "TBC". The
--   ASCII match is deliberate: if this file is imported over a non-utf8
--   connection and the guillemets get mangled, issuing still stays blocked.
--
-- ASSUMPTION (unconfirmed by the owner, see README "Open TBCs"):
--   Invoice number = [prefix] + YY + zero-padded sequence (seq_digits = 3),
--   reset each calendar year, separate per issuing entity. Derived from
--   sample #26301 (EHS, dated 29/09/2026) = "" + "26" + "301".
--   Every series row is seeded LOCKED (is_confirmed = 0, next_value NULL).
--   A super_admin must enter and confirm the next number before ANY issue.
--   Nothing auto-assumes 26302.
-- =============================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_general_ci;

-- Pre-flight: confirm server type/version and charset before continuing.
SELECT VERSION() AS server_version,
       @@character_set_server AS server_charset,
       @@collation_server AS server_collation;

-- -----------------------------------------------------------------------------
-- 1. inv_settings: one row per ISSUING entity (letterhead, bank, GST, T&C,
--    number format). Edited by super_admin on the Settings page (Stage 2).
--    entity_code values deliberately match cm_clients.entity ENUM values.
--    A row here (FK-referenced) replaces an ENUM so that a third entity can be
--    added with an INSERT rather than an ALTER on six tables.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inv_settings` (
  `entity_code`          VARCHAR(20)   NOT NULL,
  `display_name`         VARCHAR(100)  NOT NULL,
  `legal_name`           VARCHAR(200)  NOT NULL,
  `co_reg_no`            VARCHAR(50)   NOT NULL,
  `address`              VARCHAR(255)  NOT NULL,
  `email`                VARCHAR(150)  NOT NULL,
  `phone`                VARCHAR(50)   NOT NULL,
  -- NULL or '' = line not printed. «TBC» = owner must decide (blocks issue).
  `accreditation_line`   VARCHAR(255)  DEFAULT NULL,
  -- Filename only, resolved inside invoice-system/assets/img/ and embedded as
  -- base64 by the PDF renderer. Never a URL, never a user-supplied path.
  `logo_filename`        VARCHAR(100)  NOT NULL,
  -- Payment block printed in the "Note:" section of the invoice.
  `cheque_payee`         VARCHAR(200)  NOT NULL,
  `bank_name`            VARCHAR(100)  NOT NULL,
  `bank_code`            VARCHAR(20)   NOT NULL,
  `bank_branch`          VARCHAR(100)  NOT NULL,
  `bank_account_label`   VARCHAR(100)  NOT NULL,
  `bank_account_no`      VARCHAR(50)   NOT NULL,
  `bank_swift`           VARCHAR(20)   NOT NULL,
  `paynow_uen`           VARCHAR(50)   NOT NULL,
  `payment_note`         VARCHAR(255)  NOT NULL,
  -- GST: gst_status_confirmed = 0 blocks issuing until the owner confirms.
  `gst_registered`       TINYINT(1)    NOT NULL DEFAULT 0,
  `gst_status_confirmed` TINYINT(1)    NOT NULL DEFAULT 0,
  `gst_reg_no`           VARCHAR(50)   DEFAULT NULL,
  `gst_rate`             DECIMAL(5,2)  NOT NULL DEFAULT 9.00,
  `no_gst_label`         VARCHAR(30)   NOT NULL DEFAULT 'NO GST',
  -- One T&C bullet per line (newline-separated); rendered as a bullet list.
  `tnc_text`             TEXT          NOT NULL,
  `footer_text`          VARCHAR(255)  NOT NULL,
  `default_terms_days`   SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  -- Number FORMAT for this entity (the counters live in inv_number_series).
  `invoice_prefix`       VARCHAR(10)   NOT NULL DEFAULT '',
  `cn_prefix`            VARCHAR(10)   NOT NULL DEFAULT '',
  `seq_digits`           TINYINT UNSIGNED NOT NULL DEFAULT 3,
  -- Reminder sender (Stage 5). Defaults to the entity email.
  `reminder_from_email`  VARCHAR(150)  NOT NULL,
  `reminder_from_name`   VARCHAR(150)  NOT NULL,
  `is_active`            TINYINT(1)    NOT NULL DEFAULT 1,
  `updated_by`           INT UNSIGNED  DEFAULT NULL,
  `updated_by_name`      VARCHAR(150)  DEFAULT NULL,
  `created_at`           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`entity_code`),
  CONSTRAINT `chk_inv_settings_gst_rate`   CHECK (`gst_rate` >= 0 AND `gst_rate` <= 100),
  CONSTRAINT `chk_inv_settings_seq_digits` CHECK (`seq_digits` BETWEEN 3 AND 6),
  CONSTRAINT `chk_inv_settings_terms`      CHECK (`default_terms_days` <= 365),
  CONSTRAINT `chk_inv_settings_gst_flags`  CHECK (`gst_registered` IN (0,1) AND `gst_status_confirmed` IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 2. inv_config: global module settings (key/value).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inv_config` (
  `cfg_key`         VARCHAR(64)   NOT NULL,
  `cfg_value`       TEXT          NOT NULL,
  `description`     VARCHAR(255)  DEFAULT NULL,
  `updated_by`      INT UNSIGNED  DEFAULT NULL,
  `updated_by_name` VARCHAR(150)  DEFAULT NULL,
  `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`cfg_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 3. inv_number_series: one counter row per (entity, document type, year).
--    Issue flow (Stage 3), all inside ONE transaction:
--      SELECT ... FROM inv_number_series WHERE entity_code=? AND doc_type=?
--        AND period_year=? FOR UPDATE;
--      refuse if row missing, is_confirmed = 0 or next_value would overflow
--        seq_digits; else format the number, UPDATE next_value = next_value + 1,
--        write the invoice, COMMIT.
--    period_year = YEAR(invoice_date), so the printed YY always matches the
--    printed date (also when a backdated invoice crosses a year boundary).
--    A missing year row (e.g. 2027) blocks issuing until a super_admin
--    creates and confirms it. ASSUMPTION: yearly reset (see file header).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inv_number_series` (
  `entity_code`       VARCHAR(20)   NOT NULL,
  `doc_type`          ENUM('invoice','credit_note') NOT NULL,
  `period_year`       SMALLINT UNSIGNED NOT NULL,
  `next_value`        INT UNSIGNED  DEFAULT NULL,
  `is_confirmed`      TINYINT(1)    NOT NULL DEFAULT 0,
  `confirmed_by`      INT UNSIGNED  DEFAULT NULL,
  `confirmed_by_name` VARCHAR(150)  DEFAULT NULL,
  `confirmed_at`      TIMESTAMP     NULL DEFAULT NULL,
  `updated_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`entity_code`, `doc_type`, `period_year`),
  CONSTRAINT `fk_inv_series_entity` FOREIGN KEY (`entity_code`) REFERENCES `inv_settings` (`entity_code`),
  CONSTRAINT `chk_inv_series_year`      CHECK (`period_year` BETWEEN 2020 AND 2099),
  CONSTRAINT `chk_inv_series_next`      CHECK (`next_value` IS NULL OR `next_value` >= 1),
  CONSTRAINT `chk_inv_series_confirmed` CHECK (`is_confirmed` = 0 OR (`next_value` IS NOT NULL AND `confirmed_at` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 4. inv_categories: printed as "Category:" on the invoice.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inv_categories` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100)  NOT NULL,
  `is_active`  TINYINT(1)    NOT NULL DEFAULT 1,
  `sort_order` INT UNSIGNED  NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 5. inv_services: catalog with default rate and standard wording.
--    entity_code NULL = usable by both issuing entities.
--    qty_from_entities = 1: line qty is set to the number of audited entities.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inv_services` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `code`              VARCHAR(30)   NOT NULL,
  `title`             VARCHAR(255)  NOT NULL,
  `standard_wording`  TEXT          DEFAULT NULL,
  `default_rate`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `category_id`       INT UNSIGNED  DEFAULT NULL,
  `entity_code`       VARCHAR(20)   DEFAULT NULL,
  `qty_from_entities` TINYINT(1)    NOT NULL DEFAULT 0,
  `is_active`         TINYINT(1)    NOT NULL DEFAULT 1,
  `sort_order`        INT UNSIGNED  NOT NULL DEFAULT 0,
  `created_by`        INT UNSIGNED  DEFAULT NULL,
  `created_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_services_code` (`code`),
  KEY `idx_inv_services_category` (`category_id`),
  KEY `idx_inv_services_entity` (`entity_code`),
  CONSTRAINT `fk_inv_services_category` FOREIGN KEY (`category_id`) REFERENCES `inv_categories` (`id`),
  CONSTRAINT `fk_inv_services_entity`   FOREIGN KEY (`entity_code`) REFERENCES `inv_settings` (`entity_code`),
  CONSTRAINT `chk_inv_services_rate`    CHECK (`default_rate` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 6. inv_parties: billed customers / consultancies.
--    Created manually, or COPIED from cm_clients via the read-only lookup
--    (Option A). source_client_id is informational only: no FK, never used to
--    write back. All billing fields are editable here; NULL address allowed.
--    Parties are never hard-deleted (is_active = 0) because invoices FK them.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inv_parties` (
  `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `display_name`        VARCHAR(200)  NOT NULL,
  `legal_name`          VARCHAR(200)  DEFAULT NULL,
  `uen`                 VARCHAR(50)   DEFAULT NULL,
  `billing_address`     TEXT          DEFAULT NULL,
  `attention`           VARCHAR(150)  DEFAULT NULL,
  `accounts_email`      VARCHAR(150)  DEFAULT NULL,
  `cc_emails`           VARCHAR(500)  DEFAULT NULL,
  -- Free text: cm_clients.phone may contain newlines and several numbers.
  `phone`               VARCHAR(100)  DEFAULT NULL,
  -- NULL = use inv_settings.default_terms_days of the issuing entity.
  `payment_terms_days`  SMALLINT UNSIGNED DEFAULT NULL,
  `default_entity_code` VARCHAR(20)   DEFAULT NULL,
  `source`              ENUM('manual','cm_lookup') NOT NULL DEFAULT 'manual',
  `source_client_id`    INT UNSIGNED  DEFAULT NULL,
  `source_copied_at`    TIMESTAMP     NULL DEFAULT NULL,
  `notes`               TEXT          DEFAULT NULL,
  `is_active`           TINYINT(1)    NOT NULL DEFAULT 1,
  `created_by`          INT UNSIGNED  DEFAULT NULL,
  `created_by_name`     VARCHAR(150)  DEFAULT NULL,
  `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inv_parties_name` (`display_name`),
  KEY `idx_inv_parties_source_client` (`source_client_id`),
  KEY `idx_inv_parties_active` (`is_active`),
  CONSTRAINT `fk_inv_parties_entity` FOREIGN KEY (`default_entity_code`) REFERENCES `inv_settings` (`entity_code`),
  CONSTRAINT `chk_inv_parties_terms`  CHECK (`payment_terms_days` IS NULL OR `payment_terms_days` <= 365),
  CONSTRAINT `chk_inv_parties_source` CHECK (`source` = 'manual' OR `source_client_id` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 7. inv_invoices
--    status is STORED and RECOMPUTED (never incremented) by one PHP function,
--    inv_recompute_status(), inside the SAME transaction, after
--    SELECT ... FOR UPDATE on this row, for every: issue, payment, payment
--    reversal, credit-note issue, credit-note void and invoice void.
--    The UI never sets status directly. Rule:
--      draft/void   -> unchanged (explicit workflow states)
--      balance = total - SUM(active payments) - SUM(issued credit notes)
--      balance < 0  -> ROLLBACK (overpayment / over-credit rejected)
--      balance = 0  -> 'paid'           (settled; reports split cash vs credit)
--      balance < total -> 'partially_paid'
--      balance = total -> 'issued'
--    overdue is NEVER stored: status IN ('issued','partially_paid')
--    AND due_date < CURDATE().
--    Bill-to and entity details are SNAPSHOTTED at issue; later edits to
--    inv_parties / inv_settings never change an issued document.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inv_invoices` (
  `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `entity_code`        VARCHAR(20)   NOT NULL,
  `invoice_no`         VARCHAR(30)   DEFAULT NULL,
  `status`             ENUM('draft','issued','partially_paid','paid','void') NOT NULL DEFAULT 'draft',
  `party_id`           INT UNSIGNED  NOT NULL,
  `category_id`        INT UNSIGNED  DEFAULT NULL,
  `invoice_date`       DATE          NOT NULL,
  `payment_terms_days` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  `due_date`           DATE          DEFAULT NULL,
  `currency`           CHAR(3)       NOT NULL DEFAULT 'SGD',
  `customer_ref`       VARCHAR(100)  DEFAULT NULL,
  `subtotal`           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `gst_rate`           DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `gst_amount`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total`              DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  -- Snapshots (filled at issue; drafts render live values).
  `amount_in_words`    VARCHAR(255)  DEFAULT NULL,
  `category_name`      VARCHAR(100)  DEFAULT NULL,
  `bill_to_name`       VARCHAR(200)  DEFAULT NULL,
  `bill_to_address`    TEXT          DEFAULT NULL,
  `bill_to_attention`  VARCHAR(150)  DEFAULT NULL,
  `bill_to_email`      VARCHAR(150)  DEFAULT NULL,
  `entity_snapshot`    JSON          DEFAULT NULL,
  -- Stored PDF (written once at issue under invoice-system/storage/, deny-all).
  `pdf_file`           VARCHAR(255)  DEFAULT NULL,
  `pdf_sha256`         CHAR(64)      DEFAULT NULL,
  `internal_notes`     TEXT          DEFAULT NULL,
  -- Optimistic lock for concurrent draft edits.
  `row_version`        INT UNSIGNED  NOT NULL DEFAULT 1,
  `issued_at`          TIMESTAMP     NULL DEFAULT NULL,
  `issued_by`          INT UNSIGNED  DEFAULT NULL,
  `issued_by_name`     VARCHAR(150)  DEFAULT NULL,
  `void_reason`        TEXT          DEFAULT NULL,
  `voided_at`          TIMESTAMP     NULL DEFAULT NULL,
  `voided_by`          INT UNSIGNED  DEFAULT NULL,
  `voided_by_name`     VARCHAR(150)  DEFAULT NULL,
  `created_by`         INT UNSIGNED  DEFAULT NULL,
  `created_by_name`    VARCHAR(150)  DEFAULT NULL,
  `created_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  -- Duplicate-number safety net behind the FOR UPDATE counter (NULLs allowed for drafts).
  UNIQUE KEY `uq_inv_invoices_number` (`entity_code`, `invoice_no`),
  -- Target of the composite FK from inv_credit_notes (same entity enforced in DB).
  UNIQUE KEY `uq_inv_invoices_id_entity` (`id`, `entity_code`),
  KEY `idx_inv_invoices_status_due` (`status`, `due_date`),
  KEY `idx_inv_invoices_party_status` (`party_id`, `status`),
  KEY `idx_inv_invoices_entity_date` (`entity_code`, `invoice_date`),
  KEY `idx_inv_invoices_category` (`category_id`),
  CONSTRAINT `fk_inv_invoices_entity`   FOREIGN KEY (`entity_code`) REFERENCES `inv_settings` (`entity_code`),
  CONSTRAINT `fk_inv_invoices_party`    FOREIGN KEY (`party_id`) REFERENCES `inv_parties` (`id`),
  CONSTRAINT `fk_inv_invoices_category` FOREIGN KEY (`category_id`) REFERENCES `inv_categories` (`id`),
  CONSTRAINT `chk_inv_invoices_amounts`     CHECK (`subtotal` >= 0 AND `gst_amount` >= 0 AND `total` = `subtotal` + `gst_amount`),
  CONSTRAINT `chk_inv_invoices_gst_calc`    CHECK (`gst_amount` = ROUND(`subtotal` * `gst_rate` / 100, 2)),
  CONSTRAINT `chk_inv_invoices_gst_rate`    CHECK (`gst_rate` >= 0 AND `gst_rate` <= 100),
  CONSTRAINT `chk_inv_invoices_terms`       CHECK (`payment_terms_days` <= 365),
  CONSTRAINT `chk_inv_invoices_currency`    CHECK (`currency` = 'SGD'),
  CONSTRAINT `chk_inv_invoices_due`         CHECK (`due_date` IS NULL OR `due_date` >= `invoice_date`),
  CONSTRAINT `chk_inv_invoices_draft_no`    CHECK (`status` <> 'draft' OR (`invoice_no` IS NULL AND `issued_at` IS NULL)),
  CONSTRAINT `chk_inv_invoices_issued_req`  CHECK (`status` = 'draft' OR (`invoice_no` IS NOT NULL AND `issued_at` IS NOT NULL
                                                   AND `total` > 0
                                                   AND `due_date` IS NOT NULL AND `bill_to_name` IS NOT NULL
                                                   AND `entity_snapshot` IS NOT NULL AND `amount_in_words` IS NOT NULL)),
  CONSTRAINT `chk_inv_invoices_void_req`    CHECK (`status` <> 'void' OR (`voided_at` IS NOT NULL AND `void_reason` IS NOT NULL
                                                   AND CHAR_LENGTH(TRIM(`void_reason`)) >= 5)),
  CONSTRAINT `chk_inv_invoices_void_only`   CHECK (`status` = 'void' OR (`voided_at` IS NULL AND `void_reason` IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 8. inv_invoice_lines. Editable only while the parent is a draft
--    (application rule). CASCADE applies only to deleting a draft invoice;
--    issued invoices are never deleted.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inv_invoice_lines` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `invoice_id`  INT UNSIGNED  NOT NULL,
  `line_no`     SMALLINT UNSIGNED NOT NULL,
  `service_id`  INT UNSIGNED  DEFAULT NULL,
  `title`       VARCHAR(255)  NOT NULL,
  `description` TEXT          DEFAULT NULL,
  `qty`         DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `rate`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `amount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_lines_invoice_line` (`invoice_id`, `line_no`),
  KEY `idx_inv_lines_service` (`service_id`),
  CONSTRAINT `fk_inv_lines_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `inv_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inv_lines_service` FOREIGN KEY (`service_id`) REFERENCES `inv_services` (`id`),
  CONSTRAINT `chk_inv_lines_qty`    CHECK (`qty` > 0),
  CONSTRAINT `chk_inv_lines_rate`   CHECK (`rate` >= 0),
  -- Round once per line: DB rejects any amount not equal to ROUND(qty * rate, 2).
  CONSTRAINT `chk_inv_lines_amount` CHECK (`amount` = ROUND(`qty` * `rate`, 2))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 9. inv_line_audited_entities: structured "For: 1) ... 2) ..." list.
--    Named "audited" to avoid confusion with the ISSUING entity.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inv_line_audited_entities` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `line_id`          INT UNSIGNED  NOT NULL,
  `seq`              SMALLINT UNSIGNED NOT NULL,
  `company_name`     VARCHAR(200)  NOT NULL,
  `uen`              VARCHAR(50)   DEFAULT NULL,
  `source_client_id` INT UNSIGNED  DEFAULT NULL,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_audited_line_seq` (`line_id`, `seq`),
  KEY `idx_inv_audited_source_client` (`source_client_id`),
  CONSTRAINT `fk_inv_audited_line` FOREIGN KEY (`line_id`) REFERENCES `inv_invoice_lines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_inv_audited_name` CHECK (CHAR_LENGTH(TRIM(`company_name`)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 10. inv_payments: ledger. Rows are never deleted or edited. A mistake is
--     fixed by a reversal (reversed_at + reason), and the invoice status is
--     recomputed in the same transaction.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inv_payments` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `invoice_id`       INT UNSIGNED  NOT NULL,
  `payment_date`     DATE          NOT NULL,
  `amount`           DECIMAL(12,2) NOT NULL,
  `method`           ENUM('cheque','bank_transfer','paynow') NOT NULL,
  `reference`        VARCHAR(100)  DEFAULT NULL,
  `notes`            VARCHAR(500)  DEFAULT NULL,
  `recorded_by`      INT UNSIGNED  DEFAULT NULL,
  `recorded_by_name` VARCHAR(150)  DEFAULT NULL,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reversed_at`      TIMESTAMP     NULL DEFAULT NULL,
  `reversed_by`      INT UNSIGNED  DEFAULT NULL,
  `reversed_by_name` VARCHAR(150)  DEFAULT NULL,
  `reversal_reason`  VARCHAR(500)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_inv_payments_invoice` (`invoice_id`, `reversed_at`),
  KEY `idx_inv_payments_date` (`payment_date`),
  CONSTRAINT `fk_inv_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `inv_invoices` (`id`),
  CONSTRAINT `chk_inv_payments_amount`   CHECK (`amount` > 0),
  CONSTRAINT `chk_inv_payments_reversal` CHECK ((`reversed_at` IS NULL AND `reversal_reason` IS NULL)
                                             OR (`reversed_at` IS NOT NULL AND `reversal_reason` IS NOT NULL
                                                 AND CHAR_LENGTH(TRIM(`reversal_reason`)) >= 5))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 11. inv_credit_notes: always against exactly one invoice of the SAME
--     issuing entity (composite FK). Total is capped at the invoice's
--     outstanding balance by the application, inside the invoice row lock.
--     Own number series (doc_type = 'credit_note').
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inv_credit_notes` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `entity_code`     VARCHAR(20)   NOT NULL,
  `cn_no`           VARCHAR(30)   DEFAULT NULL,
  `invoice_id`      INT UNSIGNED  NOT NULL,
  `status`          ENUM('draft','issued','void') NOT NULL DEFAULT 'draft',
  `cn_date`         DATE          NOT NULL,
  `reason`          TEXT          NOT NULL,
  `subtotal`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `gst_rate`        DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `gst_amount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total`           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `amount_in_words` VARCHAR(255)  DEFAULT NULL,
  `bill_to_name`    VARCHAR(200)  DEFAULT NULL,
  `bill_to_address` TEXT          DEFAULT NULL,
  `entity_snapshot` JSON          DEFAULT NULL,
  `pdf_file`        VARCHAR(255)  DEFAULT NULL,
  `pdf_sha256`      CHAR(64)      DEFAULT NULL,
  `issued_at`       TIMESTAMP     NULL DEFAULT NULL,
  `issued_by`       INT UNSIGNED  DEFAULT NULL,
  `issued_by_name`  VARCHAR(150)  DEFAULT NULL,
  `void_reason`     TEXT          DEFAULT NULL,
  `voided_at`       TIMESTAMP     NULL DEFAULT NULL,
  `voided_by`       INT UNSIGNED  DEFAULT NULL,
  `voided_by_name`  VARCHAR(150)  DEFAULT NULL,
  `created_by`      INT UNSIGNED  DEFAULT NULL,
  `created_by_name` VARCHAR(150)  DEFAULT NULL,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_cn_number` (`entity_code`, `cn_no`),
  KEY `idx_inv_cn_invoice` (`invoice_id`, `status`),
  KEY `idx_inv_cn_date` (`cn_date`),
  CONSTRAINT `fk_inv_cn_entity`  FOREIGN KEY (`entity_code`) REFERENCES `inv_settings` (`entity_code`),
  CONSTRAINT `fk_inv_cn_invoice` FOREIGN KEY (`invoice_id`, `entity_code`) REFERENCES `inv_invoices` (`id`, `entity_code`),
  CONSTRAINT `chk_inv_cn_amounts`    CHECK (`subtotal` >= 0 AND `gst_amount` >= 0 AND `total` = `subtotal` + `gst_amount`),
  CONSTRAINT `chk_inv_cn_gst_calc`   CHECK (`gst_amount` = ROUND(`subtotal` * `gst_rate` / 100, 2)),
  CONSTRAINT `chk_inv_cn_reason`     CHECK (CHAR_LENGTH(TRIM(`reason`)) >= 5),
  CONSTRAINT `chk_inv_cn_draft_no`   CHECK (`status` <> 'draft' OR (`cn_no` IS NULL AND `issued_at` IS NULL)),
  CONSTRAINT `chk_inv_cn_issued_req` CHECK (`status` = 'draft' OR (`cn_no` IS NOT NULL AND `issued_at` IS NOT NULL
                                            AND `total` > 0 AND `entity_snapshot` IS NOT NULL)),
  CONSTRAINT `chk_inv_cn_void_req`   CHECK (`status` <> 'void' OR (`voided_at` IS NOT NULL AND `void_reason` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 12. inv_credit_note_lines
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inv_credit_note_lines` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `credit_note_id`  INT UNSIGNED  NOT NULL,
  `line_no`         SMALLINT UNSIGNED NOT NULL,
  `invoice_line_id` INT UNSIGNED  DEFAULT NULL,
  `description`     TEXT          NOT NULL,
  `qty`             DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `rate`            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `amount`          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_cn_lines_line` (`credit_note_id`, `line_no`),
  KEY `idx_inv_cn_lines_inv_line` (`invoice_line_id`),
  CONSTRAINT `fk_inv_cn_lines_cn`       FOREIGN KEY (`credit_note_id`) REFERENCES `inv_credit_notes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inv_cn_lines_inv_line` FOREIGN KEY (`invoice_line_id`) REFERENCES `inv_invoice_lines` (`id`),
  CONSTRAINT `chk_inv_cn_lines_qty`     CHECK (`qty` > 0),
  CONSTRAINT `chk_inv_cn_lines_rate`    CHECK (`rate` >= 0),
  CONSTRAINT `chk_inv_cn_lines_amount`  CHECK (`amount` = ROUND(`qty` * `rate`, 2))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 13. inv_reminders_log: one row per (invoice, stage). The UNIQUE key stops
--     the cron double-sending a stage; a failed send is retried by updating
--     the same row.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inv_reminders_log` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `invoice_id` INT UNSIGNED  NOT NULL,
  `stage`      VARCHAR(20)   NOT NULL,
  `sent_to`    VARCHAR(500)  DEFAULT NULL,
  `result`     ENUM('sent','failed','skipped') NOT NULL,
  `error`      TEXT          DEFAULT NULL,
  `attempts`   TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_reminders_invoice_stage` (`invoice_id`, `stage`),
  CONSTRAINT `fk_inv_reminders_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `inv_invoices` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 14. inv_activity_log: same columns as cm_activity_log / crm_activity_log.
--     Module-local; never shares rows with other modules' logs.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inv_activity_log` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED  NOT NULL,
  `action`      VARCHAR(100)  NOT NULL,
  `entity_type` VARCHAR(50)   NOT NULL,
  `entity_id`   INT UNSIGNED  DEFAULT NULL,
  `details`     TEXT          DEFAULT NULL,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inv_activity_entity` (`entity_type`, `entity_id`),
  KEY `idx_inv_activity_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =============================================================================
-- SEED DATA (insert-if-absent; never overwrites edited values)
-- =============================================================================

-- EHS Universal: every value below is as printed on invoice #26301.
-- Note: the sample prints "Organization&" (no space); the spec text with a
-- space is used here. Editable on the Settings page.
INSERT INTO `inv_settings`
  (`entity_code`, `display_name`, `legal_name`, `co_reg_no`, `address`, `email`, `phone`,
   `accreditation_line`, `logo_filename`,
   `cheque_payee`, `bank_name`, `bank_code`, `bank_branch`, `bank_account_label`, `bank_account_no`,
   `bank_swift`, `paynow_uen`, `payment_note`,
   `gst_registered`, `gst_status_confirmed`, `gst_reg_no`, `gst_rate`, `no_gst_label`,
   `tnc_text`, `footer_text`, `default_terms_days`,
   `invoice_prefix`, `cn_prefix`, `seq_digits`,
   `reminder_from_email`, `reminder_from_name`, `is_active`)
SELECT
  'EHS', 'EHS Universal', 'EHS Universal Pte Ltd', '201207714D',
  '2 Kallang Avenue #08-16 CT Hub Singapore 339407', 'admin@ehscertification.sg', '91445253',
  '(SAC and MOM Approved Auditing Organization & ISO Certification Body for ISO 9001, 45001 and 14001)',
  'logo_ehs.png',
  'EHS UNIVERSAL PTE LTD', 'DBS Bank', '7171', 'Choa Chu Kang (066)', 'DBS Current', '066-902165-8',
  'DBSSSGSG', '201207714D',
  'Please indicate our reference number when making payments (Cheques & Online Payment)',
  0, 1, NULL, 9.00, 'NO GST',
  CONCAT(
    'Upon receipt of the invoice, if the client finds any discrepancy in the invoice, EHS Universal must be informed in writing via admin@ehscertification.sg within 10 days from the date of invoice of any discrepancy therein, failing which the invoice issued is deemed to be in order and the client is liable to effect payment accordingly.', CHAR(10 USING utf8mb4),
    'Invoices are NOT subject to the prevailing GST.', CHAR(10 USING utf8mb4),
    'Invoices not paid within the stipulated payment terms on the invoice will be charged interests at the rate of 5% p. a. from the due date up to payment date.'
  ),
  'This is computer generated and NO SIGNATURE required', 30,
  '', 'CN', 3,
  'admin@ehscertification.sg', 'EHS Universal Pte Ltd', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `inv_settings` WHERE `entity_code` = 'EHS');

-- Axiscert: name, Co Reg No, address and email as printed on the quote
-- sample. Everything else is «TBC» and blocks issuing until a super_admin
-- fills it in.
-- phone: header shows 91445253 (same as EHS); body shows HP 8312 3164.
-- accreditation_line: set to '' (empty) on the Settings page if Axiscert
-- invoices should print no accreditation line.
INSERT INTO `inv_settings`
  (`entity_code`, `display_name`, `legal_name`, `co_reg_no`, `address`, `email`, `phone`,
   `accreditation_line`, `logo_filename`,
   `cheque_payee`, `bank_name`, `bank_code`, `bank_branch`, `bank_account_label`, `bank_account_no`,
   `bank_swift`, `paynow_uen`, `payment_note`,
   `gst_registered`, `gst_status_confirmed`, `gst_reg_no`, `gst_rate`, `no_gst_label`,
   `tnc_text`, `footer_text`, `default_terms_days`,
   `invoice_prefix`, `cn_prefix`, `seq_digits`,
   `reminder_from_email`, `reminder_from_name`, `is_active`)
SELECT
  'Axiscert', 'Axiscert', 'AXISCERT PTE. LTD.', '202310893C',
  '190 Middle Rd, Fortune Centre #15-01 Singapore 188979', 'contact@axiscert.com.sg', '«TBC»',
  '«TBC»',
  'logo_axiscert.png',
  '«TBC»', '«TBC»', '«TBC»', '«TBC»', '«TBC»', '«TBC»',
  '«TBC»', '«TBC»',
  'Please indicate our reference number when making payments (Cheques & Online Payment)',
  0, 0, NULL, 9.00, 'NO GST',
  '«TBC»',
  'This is computer generated and NO SIGNATURE required', 30,
  '«TBC»', '«TBC»', 3,
  'contact@axiscert.com.sg', 'AXISCERT PTE. LTD.', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `inv_settings` WHERE `entity_code` = 'Axiscert');

-- Number series for 2026: ALL LOCKED. next_value stays NULL until a
-- super_admin enters and confirms it (Settings page, Stage 2).
INSERT INTO `inv_number_series` (`entity_code`, `doc_type`, `period_year`, `next_value`, `is_confirmed`)
SELECT s.`entity_code`, s.`doc_type`, s.`period_year`, NULL, 0
FROM (
  SELECT 'EHS'      AS `entity_code`, 'invoice'     AS `doc_type`, 2026 AS `period_year`
  UNION ALL SELECT 'EHS',      'credit_note', 2026
  UNION ALL SELECT 'Axiscert', 'invoice',     2026
  UNION ALL SELECT 'Axiscert', 'credit_note', 2026
) AS s
WHERE NOT EXISTS (
  SELECT 1 FROM `inv_number_series` n
  WHERE n.`entity_code` = s.`entity_code`
    AND n.`doc_type`    = s.`doc_type`
    AND n.`period_year` = s.`period_year`
);

-- Categories (recommended defaults; editable).
INSERT INTO `inv_categories` (`name`, `sort_order`)
SELECT c.`name`, c.`sort_order`
FROM (
  SELECT 'RM Audit' AS `name`, 10 AS `sort_order`
  UNION ALL SELECT 'Certification Audit',   20
  UNION ALL SELECT 'Surveillance Audit',    30
  UNION ALL SELECT 'Recertification Audit', 40
  UNION ALL SELECT 'Training',              50
  UNION ALL SELECT 'Other',                 90
) AS c
WHERE NOT EXISTS (SELECT 1 FROM `inv_categories` x WHERE x.`name` = c.`name`);

-- One service, taken verbatim from invoice #26301 (EHS). The 550.00 rate is
-- the rate on that one invoice, seeded as a DEFAULT only; editable per line.
-- Other services are added on the Services page once their wording is known.
INSERT INTO `inv_services`
  (`code`, `title`, `standard_wording`, `default_rate`, `category_id`, `entity_code`, `qty_from_entities`, `sort_order`)
SELECT
  'RM-BIZSAFE',
  'Risk Management Audit for bizSAFE recognition',
  'Carry out WSH Risk Management audit in line with MOM / WSH Council requirements, WSH Risk management Regulations, Code of Practice on RM and Guides by WSH Council',
  550.00,
  (SELECT `id` FROM `inv_categories` WHERE `name` = 'RM Audit'),
  'EHS', 1, 10
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `inv_services` WHERE `code` = 'RM-BIZSAFE');

-- Global config (decisions confirmed at kickoff).
INSERT INTO `inv_config` (`cfg_key`, `cfg_value`, `description`)
SELECT k.`cfg_key`, k.`cfg_value`, k.`description`
FROM (
  SELECT 'currency' AS `cfg_key`, 'SGD' AS `cfg_value`, 'Only SGD is supported (CHECK on inv_invoices.currency).' AS `description`
  UNION ALL SELECT 'date_format',          'd/m/Y',   'Display/print format (dd/mm/yyyy, as on #26301).'
  UNION ALL SELECT 'overpayment_rule',     'reject',  'Payments that would take the balance below zero are rejected.'
  UNION ALL SELECT 'credit_note_cap',      'outstanding_balance', 'Credit note total may not exceed the invoice outstanding balance.'
  UNION ALL SELECT 'backdating',           'allow_with_warning',  'Invoice dates in the past are allowed; the UI shows a warning.'
  UNION ALL SELECT 'reminders_enabled',    '0',       'Overdue reminder cron is off until Stage 5 is installed and SMTP is tested.'
  UNION ALL SELECT 'reminder_stages_days', '7,30,60', 'Days past due_date at which one reminder per stage is sent.'
  UNION ALL SELECT 'reminder_attach_pdf',  '1',       'Attach the stored invoice PDF to reminders.'
  UNION ALL SELECT 'draft_watermark',      'DRAFT, NOT FOR ISSUE', 'Watermark for draft previews and not-ready entities.'
) AS k
WHERE NOT EXISTS (SELECT 1 FROM `inv_config` x WHERE x.`cfg_key` = k.`cfg_key`);

-- =============================================================================
-- End of inv_schema.sql
-- =============================================================================
