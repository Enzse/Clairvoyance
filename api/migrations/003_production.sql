-- Migration: 003_production.sql
-- Description: What running it on the internet needs: email verification and password reset
-- tokens, signing out other sessions, throttling, emailed invitations, and a history of
-- every change to a figure.

ALTER TABLE users
  ADD COLUMN email_verified_at DATETIME NULL AFTER password,
  ADD COLUMN session_version INT NOT NULL DEFAULT 0 AFTER email_verified_at;

-- Accounts created before verification existed are trusted as they are
UPDATE users SET email_verified_at = created_at;

-- One-time links sent by email. Only a hash of the token is stored.
CREATE TABLE user_tokens (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  purpose     ENUM('verify_email','reset_password') NOT NULL,
  token_hash  CHAR(64) NOT NULL UNIQUE,
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_tokens_user (user_id, purpose),
  CONSTRAINT fk_user_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE invitations ADD COLUMN emailed_at DATETIME NULL AFTER invited_by;

-- Attempts per bucket (e.g. failed logins for one email) in a fixed window
CREATE TABLE rate_limits (
  bucket        VARCHAR(191) PRIMARY KEY,
  window_start  INT NOT NULL,
  hits          INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every change to a figure: old and new value (NULL = absent), who, and which import
CREATE TABLE kpi_value_changes (
  id            BIGINT AUTO_INCREMENT PRIMARY KEY,
  kpi_id        INT NOT NULL,
  unit_id       INT NOT NULL,
  period_start  DATE NOT NULL,
  old_value     DECIMAL(20,6) NULL,
  new_value     DECIMAL(20,6) NULL,
  old_note      VARCHAR(500) NULL,
  new_note      VARCHAR(500) NULL,
  import_id     INT NULL,
  changed_by    INT NULL,
  changed_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_changes_cell (kpi_id, unit_id, period_start),
  KEY idx_changes_unit (unit_id),
  CONSTRAINT fk_changes_kpi FOREIGN KEY (kpi_id) REFERENCES kpis(id) ON DELETE CASCADE,
  CONSTRAINT fk_changes_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
  CONSTRAINT fk_changes_import FOREIGN KEY (import_id) REFERENCES imports(id) ON DELETE SET NULL,
  CONSTRAINT fk_changes_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
