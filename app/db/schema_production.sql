-- File: db/schema_production.sql
--
-- Structure-only schema for an EXISTING, already-in-use database
-- (e.g. your 'ehsuser' database on ehscertification.sg) — deliberately
-- has NO "CREATE DATABASE" / "USE" statements and NO seed/demo data.
-- Just the 11 tables this app needs.
--
-- ⚠️  BEFORE RUNNING: this script does `DROP TABLE IF EXISTS` for each of
-- the table names below (users, schemes, clients, audits, holidays, etc.)
-- as a safe way to re-run this script if needed. Since 'ehsuser' already
-- has OTHER tables in it, please run this first to be certain none of
-- YOUR existing tables share these names:
--
--   SHOW TABLES LIKE 'users';
--   SHOW TABLES LIKE 'clients';
--   SHOW TABLES LIKE 'schemes';
--   SHOW TABLES LIKE 'availability';
--   SHOW TABLES LIKE 'holidays';
--   SHOW TABLES LIKE 'audits';
--   SHOW TABLES LIKE 'audit_schemes';
--   SHOW TABLES LIKE 'audit_auditors';
--   SHOW TABLES LIKE 'auditor_schemes';
--   SHOW TABLES LIKE 'activity_log';
--   SHOW TABLES LIKE 'personal_schedule_items';
--
-- If any of those return a row, STOP and tell me — that name collides
-- with something you already have, and we'll rename this app's table
-- instead of risking your existing data.
--
-- HOW TO RUN: in phpMyAdmin, select the 'ehsuser' database FIRST (so you're
-- not on a different DB), then Import (or paste into the SQL tab) this file.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS audit_auditors;
DROP TABLE IF EXISTS audit_schemes;
DROP TABLE IF EXISTS audits;
DROP TABLE IF EXISTS holidays;
DROP TABLE IF EXISTS availability;
DROP TABLE IF EXISTS auditor_schemes;
DROP TABLE IF EXISTS clients;
DROP TABLE IF EXISTS schemes;
DROP TABLE IF EXISTS personal_schedule_items;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100)        NOT NULL,
    email           VARCHAR(150)        NOT NULL,
    username        VARCHAR(50)         NOT NULL,
    password_hash   VARCHAR(255)        NOT NULL,
    role            ENUM('super_admin','admin','auditor') NOT NULL,
    color_hex       CHAR(7)             NOT NULL DEFAULT '#3788d8',
    phone           VARCHAR(30)         DEFAULT NULL,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_username (username),
    INDEX idx_users_role (role),
    INDEX idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- schemes: certification schemes (ISO 9001, 45001, ConSASS, etc.)
-- ----------------------------------------------------------------------------
CREATE TABLE schemes (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name    VARCHAR(100) NOT NULL,
    code    VARCHAR(20)  NOT NULL,
    UNIQUE KEY uq_schemes_name (name),
    UNIQUE KEY uq_schemes_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- auditor_schemes: which schemes each auditor is approved/certified for
-- ----------------------------------------------------------------------------
CREATE TABLE auditor_schemes (
    auditor_id  INT UNSIGNED NOT NULL,
    scheme_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (auditor_id, scheme_id),
    CONSTRAINT fk_as_auditor FOREIGN KEY (auditor_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_as_scheme  FOREIGN KEY (scheme_id)  REFERENCES schemes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- clients: audited organizations (names repeat month to month in the old sheet)
-- ----------------------------------------------------------------------------
CREATE TABLE clients (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name    VARCHAR(150) NOT NULL,
    notes   TEXT,
    UNIQUE KEY uq_clients_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- availability: auditor self-reported availability, one row per auditor+date+session
-- ----------------------------------------------------------------------------
CREATE TABLE availability (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    auditor_id  INT UNSIGNED NOT NULL,
    date        DATE NOT NULL,
    session     ENUM('AM','PM','FULL_DAY') NOT NULL,
    status      ENUM('available','unavailable','tentative') NOT NULL DEFAULT 'available',
    note        VARCHAR(255) DEFAULT NULL,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_avail_auditor FOREIGN KEY (auditor_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_avail_auditor_date_session (auditor_id, date, session),
    INDEX idx_avail_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- holidays: public/company holidays, used to auto-highlight the calendar
-- ----------------------------------------------------------------------------
CREATE TABLE holidays (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date    DATE NOT NULL,
    name    VARCHAR(150) NOT NULL,
    type    ENUM('public_holiday','company_holiday') NOT NULL DEFAULT 'public_holiday',
    UNIQUE KEY uq_holidays_date (date),
    INDEX idx_holidays_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- audits: the scheduled audit itself (one row per client/date/session engagement)
-- ----------------------------------------------------------------------------
CREATE TABLE audits (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id   INT UNSIGNED NOT NULL,
    audit_date  DATE NOT NULL,
    session     ENUM('AM','PM','FULL_DAY') NOT NULL,
    status      ENUM('scheduled','confirmed','completed','cancelled') NOT NULL DEFAULT 'scheduled',
    location    VARCHAR(255) DEFAULT NULL,
    notes       TEXT,
    created_by  INT UNSIGNED NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_audits_client  FOREIGN KEY (client_id)  REFERENCES clients(id),
    CONSTRAINT fk_audits_creator FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_audits_date (audit_date),
    INDEX idx_audits_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- audit_schemes: an audit can cover more than one scheme (e.g. IMS 9001+45001)
-- ----------------------------------------------------------------------------
CREATE TABLE audit_schemes (
    audit_id    INT UNSIGNED NOT NULL,
    scheme_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (audit_id, scheme_id),
    CONSTRAINT fk_asch_audit  FOREIGN KEY (audit_id)  REFERENCES audits(id) ON DELETE CASCADE,
    CONSTRAINT fk_asch_scheme FOREIGN KEY (scheme_id) REFERENCES schemes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- audit_auditors: one or more auditors can be assigned to the same audit
-- ----------------------------------------------------------------------------
CREATE TABLE audit_auditors (
    audit_id    INT UNSIGNED NOT NULL,
    auditor_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (audit_id, auditor_id),
    CONSTRAINT fk_aa_audit   FOREIGN KEY (audit_id)   REFERENCES audits(id) ON DELETE CASCADE,
    CONSTRAINT fk_aa_auditor FOREIGN KEY (auditor_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_aa_auditor (auditor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- activity_log: audit trail — who changed what, when
-- ----------------------------------------------------------------------------
CREATE TABLE activity_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    action      VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50)  NOT NULL,
    entity_id   INT UNSIGNED DEFAULT NULL,
    details     TEXT,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_log_created (created_at),
    INDEX idx_log_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- personal_schedule_items: freeform personal tasks/meetings for one user on
-- one date — independent of formal audits (no client/scheme/assignment).
-- Added post-launch; see db/migration_002_personal_schedule.sql for how this
-- was added to an already-running database without a full re-import.
-- ----------------------------------------------------------------------------
CREATE TABLE personal_schedule_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    date        DATE NOT NULL,
    time_label  VARCHAR(50)  DEFAULT NULL,
    title       VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_psi_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_psi_user_date (user_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

