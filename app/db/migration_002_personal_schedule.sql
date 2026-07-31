-- File: db/migration_002_personal_schedule.sql
-- Run this against your EXISTING ehs_auditor_planner database — it only adds
-- one new table, so it's safe to run without touching anything you've
-- already got (users, audits, availability, etc. are untouched).
--
-- Adds a lightweight personal day-schedule: freeform tasks/meetings that
-- belong to one user on one date, independent of formal audits. This is for
-- things like "Pandian: 11am NUS meeting" or "IAS Meeting 6-6:30pm" — the
-- kind of personal notes the original Excel sheet mixed into the audit
-- cells, but which don't belong in the `audits` table (no client, no scheme,
-- not something auditors get assigned to).

USE ehs_auditor_planner;

CREATE TABLE IF NOT EXISTS personal_schedule_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    date        DATE NOT NULL,
    time_label  VARCHAR(50)  DEFAULT NULL,   -- free text, e.g. "11:00 AM" or "AM" — deliberately not a strict TIME column since real entries are things like "6 to 6:30pm"
    title       VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_psi_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_psi_user_date (user_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
