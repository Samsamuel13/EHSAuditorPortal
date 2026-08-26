-- crm_schema.sql
-- CRM / Lead Pipeline module for EHS Universal — run against the existing
-- `ehsuser` database, after schema_production.sql (needed only for the
-- shared `users` table, which is referenced by plain integer ID — no FK).
--
-- Isolation: every table here is crm_-prefixed. No FOREIGN KEY points into
-- the scheduler's tables (users, clients, audits, ...) or into
-- client-management's cm_* tables. Internal FKs (crm_* -> crm_leads) are
-- fine since they stay inside this module's own schema.
--
-- The one permitted cross-module link is `crm_leads.converted_client_id`,
-- a plain nullable INT UNSIGNED holding a cm_clients.id value with NO FK
-- constraint — set once, by application code, when a lead is Awarded.

-- --------------------------------------------------------------------------
CREATE TABLE `crm_leads` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_name` varchar(200) NOT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `contact_designation` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  -- normalized_* columns exist only to make the duplicate-lead check a fast
  -- indexed lookup instead of a fuzzy scan of every row on every keystroke.
  `normalized_phone` varchar(30) DEFAULT NULL,
  `normalized_email` varchar(150) DEFAULT NULL,
  `normalized_company` varchar(200) DEFAULT NULL,
  `industry_sector` varchar(100) DEFAULT NULL,
  `source` enum('whatsapp','referral','website','cold_call','exhibition','other') NOT NULL DEFAULT 'other',
  `stage` enum('enquiry','lead','quotation','negotiation','awarded','lost','on_hold') NOT NULL DEFAULT 'enquiry',
  `owner_id` int(10) UNSIGNED DEFAULT NULL,
  `owner_name` varchar(150) DEFAULT NULL,
  `lost_reason` text DEFAULT NULL,
  `on_hold_reason` text DEFAULT NULL,
  -- One-way conversion link. No FK by design (see file header). NULL until
  -- the lead is Awarded and converted; once set, never re-converted.
  `converted_client_id` int(10) UNSIGNED DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_crm_leads_stage` (`stage`),
  KEY `idx_crm_leads_owner` (`owner_id`),
  KEY `idx_crm_leads_norm_email` (`normalized_email`),
  KEY `idx_crm_leads_norm_phone` (`normalized_phone`),
  KEY `idx_crm_leads_norm_company` (`normalized_company`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------------------------
CREATE TABLE `crm_lead_stage_history` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `crm_lead_id` int(10) UNSIGNED NOT NULL,
  `from_stage` varchar(20) DEFAULT NULL,
  `to_stage` varchar(20) NOT NULL,
  `reason` text DEFAULT NULL,
  `changed_by` int(10) UNSIGNED DEFAULT NULL,
  `changed_by_name` varchar(150) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_stage_history_lead` (`crm_lead_id`),
  CONSTRAINT `fk_stage_history_lead` FOREIGN KEY (`crm_lead_id`) REFERENCES `crm_leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------------------------
CREATE TABLE `crm_followups` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `crm_lead_id` int(10) UNSIGNED NOT NULL,
  `due_date` date NOT NULL,
  `type` enum('call','email','meeting','whatsapp','other') NOT NULL DEFAULT 'call',
  `owner_id` int(10) UNSIGNED DEFAULT NULL,
  `owner_name` varchar(150) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `done` tinyint(1) NOT NULL DEFAULT 0,
  `done_at` timestamp NULL DEFAULT NULL,
  `reminder_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_followups_lead` (`crm_lead_id`),
  KEY `idx_followups_due` (`due_date`, `done`),
  CONSTRAINT `fk_followups_lead` FOREIGN KEY (`crm_lead_id`) REFERENCES `crm_leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------------------------
-- One row per quotation VERSION. A re-negotiated quote inserts a new row
-- with version+1 rather than updating the previous one, so the full
-- negotiation history for a lead is always intact.
CREATE TABLE `crm_quotations` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `crm_lead_id` int(10) UNSIGNED NOT NULL,
  `version` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `quote_number` varchar(50) NOT NULL,
  `status` enum('draft','sent','accepted','rejected','expired') NOT NULL DEFAULT 'draft',
  `valid_until` date DEFAULT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'SGD',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_by_name` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_lead_version` (`crm_lead_id`, `version`),
  KEY `idx_quotations_lead` (`crm_lead_id`),
  CONSTRAINT `fk_quotations_lead` FOREIGN KEY (`crm_lead_id`) REFERENCES `crm_leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------------------------
CREATE TABLE `crm_quotation_items` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `crm_quotation_id` int(10) UNSIGNED NOT NULL,
  `description` varchar(255) NOT NULL,
  `qty` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_items_quotation` (`crm_quotation_id`),
  CONSTRAINT `fk_items_quotation` FOREIGN KEY (`crm_quotation_id`) REFERENCES `crm_quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------------------------
-- Module-local activity log — mirrors cm_activity_log's pattern. Never
-- shares rows with the scheduler's `activity_log` or client-management's
-- `cm_activity_log`.
CREATE TABLE `crm_activity_log` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(10) UNSIGNED DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_crm_activity_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
