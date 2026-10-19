-- Migration: 002_organizations.sql
-- Description: Multi-tenant SaaS. Every unit, KPI and import belongs to an organisation; users
-- join organisations with a per-organisation role; invitations; KPIs can be scoped to units.
-- Existing single-organisation data moves into one organisation named after its root unit.

CREATE TABLE organizations (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  name                VARCHAR(255) NOT NULL,
  setup_dismissed_at  TIMESTAMP NULL,
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE organization_users (
  organization_id  INT NOT NULL,
  user_id          INT NOT NULL,
  role             ENUM('admin','member') NOT NULL DEFAULT 'member',
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (organization_id, user_id),
  KEY idx_org_users_user (user_id),
  CONSTRAINT fk_org_users_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_org_users_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Only a hash of the token is stored; the link holds the token itself.
CREATE TABLE invitations (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  organization_id  INT NOT NULL,
  email            VARCHAR(255) NOT NULL,
  role             ENUM('admin','member') NOT NULL DEFAULT 'member',
  access           TEXT NULL COMMENT 'JSON list of {unit_id, role} granted on acceptance',
  token_hash       CHAR(64) NOT NULL UNIQUE,
  invited_by       INT NULL,
  expires_at       DATETIME NOT NULL,
  accepted_at      DATETIME NULL,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_invitations_org (organization_id),
  CONSTRAINT fk_invitations_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_invitations_user FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A KPI applies to the leaf units under these units; no rows = the whole organisation.
CREATE TABLE kpi_scope_units (
  kpi_id   INT NOT NULL,
  unit_id  INT NOT NULL,
  PRIMARY KEY (kpi_id, unit_id),
  CONSTRAINT fk_scope_kpi FOREIGN KEY (kpi_id) REFERENCES kpis(id) ON DELETE CASCADE,
  CONSTRAINT fk_scope_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing data (an installation that already has users) becomes one organisation.
INSERT INTO organizations (name)
SELECT u.name FROM units u WHERE u.parent_id IS NULL AND EXISTS (SELECT 1 FROM users) LIMIT 1;
SET @legacy_org = IF(ROW_COUNT() > 0, LAST_INSERT_ID(), NULL);

-- A fresh installation only has the old baseline's seeded root unit, which belongs to nobody.
DELETE FROM units WHERE @legacy_org IS NULL;

INSERT INTO organization_users (organization_id, user_id, role)
SELECT @legacy_org, id, role FROM users WHERE @legacy_org IS NOT NULL;

ALTER TABLE users DROP COLUMN role;

ALTER TABLE units ADD COLUMN organization_id INT NULL AFTER id;
UPDATE units SET organization_id = @legacy_org;
ALTER TABLE units
  MODIFY organization_id INT NOT NULL,
  DROP INDEX code,
  ADD UNIQUE KEY uq_unit_code (organization_id, code),
  ADD CONSTRAINT fk_units_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE;

ALTER TABLE kpis ADD COLUMN organization_id INT NULL AFTER id;
UPDATE kpis SET organization_id = @legacy_org;
ALTER TABLE kpis
  MODIFY organization_id INT NOT NULL,
  DROP INDEX code,
  ADD UNIQUE KEY uq_kpi_code (organization_id, code),
  ADD CONSTRAINT fk_kpis_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE;

ALTER TABLE imports ADD COLUMN organization_id INT NULL AFTER id;
UPDATE imports SET organization_id = @legacy_org;
ALTER TABLE imports
  MODIFY organization_id INT NOT NULL,
  ADD CONSTRAINT fk_imports_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE;
