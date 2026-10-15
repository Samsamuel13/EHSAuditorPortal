-- =============================================================================
-- invoice-system/db/inv_migration_004.sql
-- Invoice System, Stage 3B: quotations converted into invoices.
-- Run AFTER inv_migration_003.sql, with the PORTAL database selected
-- (phpMyAdmin: click the database in the left panel, then its Import tab).
--
-- What it does:
--   1. Creates inv_quotations, inv_quotation_lines, inv_quotation_line_entities.
--   2. Adds inv_invoices.quotation_id (link from an invoice to its quotation).
--   3. Adds 'quotation' to inv_number_series.doc_type and seeds LOCKED 2026
--      quotation series for EHS and Axiscert (next number NULL, unconfirmed).
--   4. Adds per-entity quotation settings to inv_settings (prefix, validity,
--      texts, signatory, stamp file) and seeds them:
--        Axiscert: texts from the owner's quote sample; prefix 'Q/SH' marked
--                  UNCONFIRMED; signatory «TBC».
--        EHS:      everything «TBC».
--
-- Collation: this file names NO collation. New tables are created with the
-- collation (and character set) READ AT RUN TIME from the existing
-- inv_invoices table, so they always match your inv_ tables
-- (your live baseline) even when the database default
-- differs. Added columns carry no collation and inherit their table default.
--
-- Safe to run twice: every step checks information_schema first or uses
-- INSERT ... WHERE NOT EXISTS / UPDATE ... WHERE value IS NULL (never
-- overwrites edited values). No ADD COLUMN IF NOT EXISTS, no procedures,
-- no CTEs, no window functions, no JSON functions, no MySQL-8-only syntax.
-- =============================================================================

-- Client character set only (no collation is named): the seeds below contain
-- the «TBC» marker, which must reach the server as UTF-8 even when the import
-- tool's connection default is latin1.
SET NAMES utf8mb4;

SELECT VERSION() AS server_version, DATABASE() AS database_name;

-- -----------------------------------------------------------------------------
-- 0. Guards: portal database selected, and migration 003 already applied.
--    Each failure stops the import at once with a readable #1054 message.
-- -----------------------------------------------------------------------------
SET @inv_guard = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('inv_invoices', 'inv_config', 'inv_settings', 'inv_parties', 'inv_services', 'inv_number_series')
);
SET @inv_any = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'inv\_%'
);
SET @inv_m3 = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_invoices' AND COLUMN_NAME = 'status'
    AND COLUMN_TYPE LIKE '%''credited''%'
);
SET @inv_sql = IF(@inv_any = 0,
  'SELECT `STOP - wrong database. Select the portal DB, then import again`',
  IF(@inv_guard < 6,
    'SELECT `STOP - required inv_ tables are missing in this DB`',
    IF(@inv_m3 = 0,
      'SELECT `STOP - run inv_migration_003.sql first`',
      'SELECT ''OK: portal database, migration 003 present'' AS migration_004_guard')));
PREPARE inv_stmt FROM @inv_sql;
EXECUTE inv_stmt;
DEALLOCATE PREPARE inv_stmt;

-- Character set + collation of the existing inv_ tables (read, not named).
SET @inv_coll = (
  SELECT TABLE_COLLATION FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_invoices'
);
SET @inv_cs = (
  SELECT CHARACTER_SET_NAME FROM information_schema.COLLATIONS WHERE COLLATION_NAME = @inv_coll
);
SET @inv_tail = CONCAT(' ENGINE=InnoDB DEFAULT CHARSET=', @inv_cs, ' COLLATE=', @inv_coll);

-- -----------------------------------------------------------------------------
-- 1. inv_settings: quotation settings (each column added only if missing)
-- -----------------------------------------------------------------------------
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_settings' AND COLUMN_NAME = 'quote_prefix');
SET @s = IF(@c = 0, 'ALTER TABLE `inv_settings` ADD COLUMN `quote_prefix` VARCHAR(20) NULL', 'DO 0');
PREPARE inv_stmt FROM @s; EXECUTE inv_stmt; DEALLOCATE PREPARE inv_stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_settings' AND COLUMN_NAME = 'quote_prefix_confirmed');
SET @s = IF(@c = 0, 'ALTER TABLE `inv_settings` ADD COLUMN `quote_prefix_confirmed` TINYINT(1) NOT NULL DEFAULT 0', 'DO 0');
PREPARE inv_stmt FROM @s; EXECUTE inv_stmt; DEALLOCATE PREPARE inv_stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_settings' AND COLUMN_NAME = 'quote_valid_days');
SET @s = IF(@c = 0, 'ALTER TABLE `inv_settings` ADD COLUMN `quote_valid_days` SMALLINT UNSIGNED NOT NULL DEFAULT 45', 'DO 0');
PREPARE inv_stmt FROM @s; EXECUTE inv_stmt; DEALLOCATE PREPARE inv_stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_settings' AND COLUMN_NAME = 'quote_ref_prefix');
SET @s = IF(@c = 0, 'ALTER TABLE `inv_settings` ADD COLUMN `quote_ref_prefix` VARCHAR(255) NULL', 'DO 0');
PREPARE inv_stmt FROM @s; EXECUTE inv_stmt; DEALLOCATE PREPARE inv_stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_settings' AND COLUMN_NAME = 'quote_intro');
SET @s = IF(@c = 0, 'ALTER TABLE `inv_settings` ADD COLUMN `quote_intro` TEXT NULL', 'DO 0');
PREPARE inv_stmt FROM @s; EXECUTE inv_stmt; DEALLOCATE PREPARE inv_stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_settings' AND COLUMN_NAME = 'quote_terms');
SET @s = IF(@c = 0, 'ALTER TABLE `inv_settings` ADD COLUMN `quote_terms` TEXT NULL', 'DO 0');
PREPARE inv_stmt FROM @s; EXECUTE inv_stmt; DEALLOCATE PREPARE inv_stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_settings' AND COLUMN_NAME = 'quote_payment_terms');
SET @s = IF(@c = 0, 'ALTER TABLE `inv_settings` ADD COLUMN `quote_payment_terms` TEXT NULL', 'DO 0');
PREPARE inv_stmt FROM @s; EXECUTE inv_stmt; DEALLOCATE PREPARE inv_stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_settings' AND COLUMN_NAME = 'quote_closing');
SET @s = IF(@c = 0, 'ALTER TABLE `inv_settings` ADD COLUMN `quote_closing` TEXT NULL', 'DO 0');
PREPARE inv_stmt FROM @s; EXECUTE inv_stmt; DEALLOCATE PREPARE inv_stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_settings' AND COLUMN_NAME = 'signatory_name');
SET @s = IF(@c = 0, 'ALTER TABLE `inv_settings` ADD COLUMN `signatory_name` VARCHAR(150) NULL', 'DO 0');
PREPARE inv_stmt FROM @s; EXECUTE inv_stmt; DEALLOCATE PREPARE inv_stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_settings' AND COLUMN_NAME = 'stamp_filename');
SET @s = IF(@c = 0, 'ALTER TABLE `inv_settings` ADD COLUMN `stamp_filename` VARCHAR(100) NULL', 'DO 0');
PREPARE inv_stmt FROM @s; EXECUTE inv_stmt; DEALLOCATE PREPARE inv_stmt;

-- Seeds (only where still NULL: edited values are never overwritten).
-- Axiscert: from the owner's quote sample. "{valid_days}" is replaced by the
-- quotation's validity; **text** prints bold.
UPDATE `inv_settings` SET `quote_prefix` = 'Q/SH', `quote_prefix_confirmed` = 0
  WHERE `entity_code` = 'Axiscert' AND `quote_prefix` IS NULL;
UPDATE `inv_settings` SET `quote_ref_prefix` = 'Quotation For Certification of Management System'
  WHERE `entity_code` = 'Axiscert' AND `quote_ref_prefix` IS NULL;
UPDATE `inv_settings` SET `quote_intro` = 'Thank you for your keen interest in our certification program, we are pleased to enclose the proposal for your esteemed organization.'
  WHERE `entity_code` = 'Axiscert' AND `quote_intro` IS NULL;
UPDATE `inv_settings` SET `quote_terms` = 'The annual fee for the surveillance visits must be paid before the surveillance audits. This quotation, inclusive of travel expenses, is valid for {valid_days} days. Since this is a promotional rate, your organization is required to maintain the certifications for a full three-year certification cycle.'
  WHERE `entity_code` = 'Axiscert' AND `quote_terms` IS NULL;
UPDATE `inv_settings` SET `quote_payment_terms` = CONCAT('Certification Audit: 100% due on the day of the audit.', CHAR(10 USING utf8mb4), '2nd and 3rd Year: Surveillance Audits: 100% due on the day of the audit.')
  WHERE `entity_code` = 'Axiscert' AND `quote_payment_terms` IS NULL;
UPDATE `inv_settings` SET `quote_closing` = 'If the terms are acceptable to you, please sign the attached contract agreement return to AXISCERT PTE. LTD. On receipt of your signed contract, we will contact you to discuss a provisional date for the audits. Should you have any questions or require further information, please contact **HP 8312 3164** or email: contact@axiscert.com.sg'
  WHERE `entity_code` = 'Axiscert' AND `quote_closing` IS NULL;
-- EHS (and any other entity): placeholders, all blocking until filled in.
UPDATE `inv_settings` SET `quote_prefix` = '«TBC»', `quote_prefix_confirmed` = 0 WHERE `quote_prefix` IS NULL;
UPDATE `inv_settings` SET `quote_ref_prefix` = '«TBC»' WHERE `quote_ref_prefix` IS NULL;
UPDATE `inv_settings` SET `quote_intro` = '«TBC»' WHERE `quote_intro` IS NULL;
UPDATE `inv_settings` SET `quote_terms` = '«TBC»' WHERE `quote_terms` IS NULL;
UPDATE `inv_settings` SET `quote_payment_terms` = '«TBC»' WHERE `quote_payment_terms` IS NULL;
UPDATE `inv_settings` SET `quote_closing` = '«TBC»' WHERE `quote_closing` IS NULL;
UPDATE `inv_settings` SET `signatory_name` = '«TBC»' WHERE `signatory_name` IS NULL;

-- -----------------------------------------------------------------------------
-- 2. inv_quotations (created with the inv_ tables' collation, read above)
-- -----------------------------------------------------------------------------
SET @s = CONCAT('CREATE TABLE IF NOT EXISTS `inv_quotations` (
  `id`                     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `entity_code`            VARCHAR(20)   NOT NULL,
  `quote_no`               VARCHAR(40)   DEFAULT NULL,
  `base_no`                VARCHAR(40)   DEFAULT NULL,
  `revision_no`            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `revision_of_id`         INT UNSIGNED  DEFAULT NULL,
  `status`                 ENUM(''draft'',''sent'',''accepted'',''rejected'',''cancelled'',''superseded'') NOT NULL DEFAULT ''draft'',
  `party_id`               INT UNSIGNED  NOT NULL,
  `quote_date`             DATE          NOT NULL,
  `valid_days`             SMALLINT UNSIGNED NOT NULL DEFAULT 45,
  `valid_until`            DATE          NOT NULL,
  `ref_prefix`             VARCHAR(255)  DEFAULT NULL,
  `subject`                VARCHAR(500)  DEFAULT NULL,
  `scope`                  TEXT          DEFAULT NULL,
  `intro`                  TEXT          DEFAULT NULL,
  `terms_text`             TEXT          DEFAULT NULL,
  `payment_terms`          TEXT          DEFAULT NULL,
  `closing_text`           TEXT          DEFAULT NULL,
  `signatory_name`         VARCHAR(150)  DEFAULT NULL,
  `bill_to_name`           VARCHAR(200)  DEFAULT NULL,
  `bill_to_address`        TEXT          DEFAULT NULL,
  `bill_to_attention`      VARCHAR(150)  DEFAULT NULL,
  `bill_to_email`          VARCHAR(150)  DEFAULT NULL,
  `total_list`             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_special`          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `entity_snapshot`        JSON          DEFAULT NULL,
  `pdf_file`               VARCHAR(255)  DEFAULT NULL,
  `pdf_sha256`             CHAR(64)      DEFAULT NULL,
  `internal_notes`         TEXT          DEFAULT NULL,
  `row_version`            INT UNSIGNED  NOT NULL DEFAULT 1,
  `sent_at`                TIMESTAMP     NULL DEFAULT NULL,
  `sent_by`                INT UNSIGNED  DEFAULT NULL,
  `sent_by_name`           VARCHAR(150)  DEFAULT NULL,
  `accepted_on`            DATE          DEFAULT NULL,
  `accepted_by_name`       VARCHAR(150)  DEFAULT NULL,
  `acceptance_note`        VARCHAR(500)  DEFAULT NULL,
  `accepted_recorded_at`   TIMESTAMP     NULL DEFAULT NULL,
  `accepted_recorded_by`   INT UNSIGNED  DEFAULT NULL,
  `accepted_recorded_by_name` VARCHAR(150) DEFAULT NULL,
  `signed_file`            VARCHAR(255)  DEFAULT NULL,
  `signed_sha256`          CHAR(64)      DEFAULT NULL,
  `signed_mime`            VARCHAR(50)   DEFAULT NULL,
  `signed_original_name`   VARCHAR(255)  DEFAULT NULL,
  `closed_reason`          VARCHAR(500)  DEFAULT NULL,
  `closed_at`              TIMESTAMP     NULL DEFAULT NULL,
  `closed_by_name`         VARCHAR(150)  DEFAULT NULL,
  `created_by`             INT UNSIGNED  DEFAULT NULL,
  `created_by_name`        VARCHAR(150)  DEFAULT NULL,
  `created_at`             TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_quotations_number` (`entity_code`, `quote_no`),
  KEY `idx_inv_quotations_status_valid` (`status`, `valid_until`),
  KEY `idx_inv_quotations_party` (`party_id`),
  KEY `idx_inv_quotations_revision_of` (`revision_of_id`),
  KEY `idx_inv_quotations_base` (`entity_code`, `base_no`),
  CONSTRAINT `fk_inv_quotations_entity`   FOREIGN KEY (`entity_code`) REFERENCES `inv_settings` (`entity_code`),
  CONSTRAINT `fk_inv_quotations_party`    FOREIGN KEY (`party_id`) REFERENCES `inv_parties` (`id`),
  CONSTRAINT `fk_inv_quotations_revision` FOREIGN KEY (`revision_of_id`) REFERENCES `inv_quotations` (`id`),
  CONSTRAINT `chk_inv_quotations_valid`     CHECK (`valid_until` >= `quote_date`),
  CONSTRAINT `chk_inv_quotations_draft_no`  CHECK (`status` <> ''draft'' OR (`quote_no` IS NULL AND `sent_at` IS NULL)),
  CONSTRAINT `chk_inv_quotations_sent_req`  CHECK (`status` = ''draft'' OR (`quote_no` IS NOT NULL AND `sent_at` IS NOT NULL AND `entity_snapshot` IS NOT NULL AND `pdf_file` IS NOT NULL)),
  CONSTRAINT `chk_inv_quotations_accepted`  CHECK (`status` <> ''accepted'' OR (`accepted_on` IS NOT NULL AND `accepted_by_name` IS NOT NULL)),
  CONSTRAINT `chk_inv_quotations_totals`    CHECK (`total_list` >= 0 AND `total_special` >= 0)
)', @inv_tail);
PREPARE inv_stmt FROM @s; EXECUTE inv_stmt; DEALLOCATE PREPARE inv_stmt;

-- -----------------------------------------------------------------------------
-- 3. inv_quotation_lines
--    line_type: item (billable), auto_sum (sum of the item lines above it,
--    since the previous sum row), manual_sum (a right-aligned row priced by
--    hand). Only item lines can be converted to an invoice.
--    invoiced_invoice_id: set on conversion; ON DELETE SET NULL frees the
--    line again when the draft invoice is deleted.
-- -----------------------------------------------------------------------------
SET @s = CONCAT('CREATE TABLE IF NOT EXISTS `inv_quotation_lines` (
  `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `quotation_id`        INT UNSIGNED  NOT NULL,
  `line_no`             SMALLINT UNSIGNED NOT NULL,
  `line_type`           ENUM(''item'',''auto_sum'',''manual_sum'') NOT NULL DEFAULT ''item'',
  `service_id`          INT UNSIGNED  DEFAULT NULL,
  `title`               VARCHAR(255)  NOT NULL,
  `description`         TEXT          DEFAULT NULL,
  `qty`                 DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `rate`                DECIMAL(12,2) DEFAULT NULL,
  `special_rate`        DECIMAL(12,2) DEFAULT NULL,
  `amount`              DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `special_amount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cycle_year`          TINYINT UNSIGNED DEFAULT NULL,
  `invoiced_invoice_id` INT UNSIGNED  DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_qlines_line` (`quotation_id`, `line_no`),
  KEY `idx_inv_qlines_service` (`service_id`),
  KEY `idx_inv_qlines_invoice` (`invoiced_invoice_id`),
  CONSTRAINT `fk_inv_qlines_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `inv_quotations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inv_qlines_service`   FOREIGN KEY (`service_id`) REFERENCES `inv_services` (`id`),
  CONSTRAINT `fk_inv_qlines_invoice`   FOREIGN KEY (`invoiced_invoice_id`) REFERENCES `inv_invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_inv_qlines_qty`      CHECK (`qty` > 0),
  CONSTRAINT `chk_inv_qlines_amounts`  CHECK (`amount` >= 0 AND `special_amount` >= 0),
  CONSTRAINT `chk_inv_qlines_item_rate` CHECK (`line_type` = ''auto_sum'' OR `rate` IS NOT NULL)
)', @inv_tail);
-- Note: "only item lines can be invoiced" is enforced in PHP. MySQL (3823) and
-- MariaDB (1901) both refuse a CHECK on a column used by an ON DELETE SET NULL
-- foreign key, so it cannot be a database CHECK here.
PREPARE inv_stmt FROM @s; EXECUTE inv_stmt; DEALLOCATE PREPARE inv_stmt;

SET @s = CONCAT('CREATE TABLE IF NOT EXISTS `inv_quotation_line_entities` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `line_id`          INT UNSIGNED  NOT NULL,
  `seq`              SMALLINT UNSIGNED NOT NULL,
  `company_name`     VARCHAR(200)  NOT NULL,
  `uen`              VARCHAR(50)   DEFAULT NULL,
  `source_client_id` INT UNSIGNED  DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_qents_line_seq` (`line_id`, `seq`),
  CONSTRAINT `fk_inv_qents_line` FOREIGN KEY (`line_id`) REFERENCES `inv_quotation_lines` (`id`) ON DELETE CASCADE
)', @inv_tail);
PREPARE inv_stmt FROM @s; EXECUTE inv_stmt; DEALLOCATE PREPARE inv_stmt;

-- -----------------------------------------------------------------------------
-- 4. inv_invoices.quotation_id (+ index + FK), only if missing
-- -----------------------------------------------------------------------------
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_invoices' AND COLUMN_NAME = 'quotation_id');
SET @s = IF(@c = 0, 'ALTER TABLE `inv_invoices` ADD COLUMN `quotation_id` INT UNSIGNED NULL, ADD KEY `idx_inv_invoices_quotation` (`quotation_id`)', 'DO 0');
PREPARE inv_stmt FROM @s; EXECUTE inv_stmt; DEALLOCATE PREPARE inv_stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_invoices' AND CONSTRAINT_NAME = 'fk_inv_invoices_quotation');
SET @s = IF(@c = 0, 'ALTER TABLE `inv_invoices` ADD CONSTRAINT `fk_inv_invoices_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `inv_quotations` (`id`)', 'DO 0');
PREPARE inv_stmt FROM @s; EXECUTE inv_stmt; DEALLOCATE PREPARE inv_stmt;

-- -----------------------------------------------------------------------------
-- 5. Number series: 'quotation' document type + locked 2026 rows
-- -----------------------------------------------------------------------------
SET @t = (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_number_series' AND COLUMN_NAME = 'doc_type');
SET @s = IF(LOCATE('''quotation''', @t) > 0, 'DO 0',
  'ALTER TABLE `inv_number_series` MODIFY COLUMN `doc_type` ENUM(''invoice'',''credit_note'',''quotation'') NOT NULL');
PREPARE inv_stmt FROM @s; EXECUTE inv_stmt; DEALLOCATE PREPARE inv_stmt;

INSERT INTO `inv_number_series` (`entity_code`, `doc_type`, `period_year`, `next_value`, `is_confirmed`)
SELECT s.`entity_code`, 'quotation', 2026, NULL, 0
FROM `inv_settings` s
WHERE NOT EXISTS (
  SELECT 1 FROM `inv_number_series` n
  WHERE n.`entity_code` = s.`entity_code` AND n.`doc_type` = 'quotation' AND n.`period_year` = 2026
);

UPDATE `inv_config` SET `cfg_value` = '4' WHERE `cfg_key` = 'schema_version' AND `cfg_value` IN ('', '1', '2', '3');

-- -----------------------------------------------------------------------------
-- Done. Verify with the queries in README section 1C, in the portal
-- database's SQL tab.
-- -----------------------------------------------------------------------------
SELECT 'Migration 004 finished. Verify with the README 1C queries.' AS migration_004_done;

-- =============================================================================
-- End of inv_migration_004.sql
-- =============================================================================
