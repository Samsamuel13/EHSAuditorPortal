-- =============================================================================
-- invoice-system/db/inv_migration_003.sql
-- Invoice System, Stage 3 migration. Run AFTER the Stage 1 schema
-- (inv_schema.sql) as already imported on your server.
--
-- What it does:
--   1. Adds 'credited' to inv_invoices.status (appended at the END of the
--      ENUM list so existing stored values keep their positions).
--   2. Adds module options to inv_config: PDF paper size (default A4),
--      optional PDF storage path (blank = invoice-system/storage), and a
--      schema_version marker.
--   3. Fixes the accreditation-line spacing "Organization&" ->
--      "Organization &" wherever it is still missing (TBC #18).
--
-- Collation: this file names NO collation and NO character set on any
-- column. The status column is re-declared with MODIFY and therefore takes
-- the DEFAULT collation of the inv_invoices table, i.e. whatever your
-- existing inv_ tables already use. No table or column is converted.
--
-- Safe to run twice: every step checks information_schema (or uses
-- INSERT ... WHERE NOT EXISTS / a guarded UPDATE) first. No ADD COLUMN IF
-- NOT EXISTS, no CTEs, no window functions, no JSON functions, no
-- MySQL-8-only or MariaDB-only syntax. Tested on MySQL 8.0 and MariaDB
-- (see the Stage 3 delivery notes for versions).
-- =============================================================================

-- Pre-flight: show what this runs against.
SELECT VERSION() AS server_version, DATABASE() AS database_name;

-- Abort marker: if inv_invoices does not exist, step 1 prints a message
-- and changes nothing (run inv_schema.sql first).

-- -----------------------------------------------------------------------------
-- 1. inv_invoices.status: add 'credited'
-- -----------------------------------------------------------------------------
SET @inv_status_type = (
  SELECT COLUMN_TYPE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_invoices' AND COLUMN_NAME = 'status'
);
SET @inv_sql = IF(@inv_status_type IS NULL,
  'SELECT ''inv_invoices.status not found: run inv_schema.sql first. Nothing changed.'' AS migration_003_step_1',
  IF(LOCATE('''credited''', @inv_status_type) > 0,
    'SELECT ''status already includes credited: no change.'' AS migration_003_step_1',
    'ALTER TABLE `inv_invoices` MODIFY COLUMN `status` ENUM(''draft'',''issued'',''partially_paid'',''paid'',''void'',''credited'') NOT NULL DEFAULT ''draft'''
  )
);
PREPARE inv_stmt FROM @inv_sql;
EXECUTE inv_stmt;
DEALLOCATE PREPARE inv_stmt;

-- -----------------------------------------------------------------------------
-- 2. Module options (insert-if-absent; never overwrites an edited value)
-- -----------------------------------------------------------------------------
INSERT INTO `inv_config` (`cfg_key`, `cfg_value`, `description`)
SELECT k.`cfg_key`, k.`cfg_value`, k.`description`
FROM (
  SELECT 'pdf_page_size' AS `cfg_key`, 'A4' AS `cfg_value`,
         'Paper size for invoice PDFs: A4 or letter (sample #26301 was letter).' AS `description`
  UNION ALL SELECT 'storage_path', '',
         'Absolute folder for stored PDFs. Blank = invoice-system/storage (deny-all .htaccess). Set via SQL only.'
  UNION ALL SELECT 'schema_version', '3',
         'Last invoice-system migration applied.'
) AS k
WHERE NOT EXISTS (SELECT 1 FROM `inv_config` x WHERE x.`cfg_key` = k.`cfg_key`);

UPDATE `inv_config` SET `cfg_value` = '3'
WHERE `cfg_key` = 'schema_version' AND `cfg_value` IN ('', '1', '2');

-- -----------------------------------------------------------------------------
-- 3. Accreditation line spacing (only rows still missing the space)
-- -----------------------------------------------------------------------------
UPDATE `inv_settings`
SET `accreditation_line` = REPLACE(`accreditation_line`, 'Organization&', 'Organization &')
WHERE `accreditation_line` LIKE '%Organization&%';

-- -----------------------------------------------------------------------------
-- Post-check (read-only)
-- -----------------------------------------------------------------------------
SELECT COLUMN_TYPE AS status_column_after
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_invoices' AND COLUMN_NAME = 'status';

SELECT `cfg_key`, `cfg_value` FROM `inv_config`
WHERE `cfg_key` IN ('pdf_page_size', 'storage_path', 'schema_version');

-- =============================================================================
-- End of inv_migration_003.sql
-- =============================================================================
