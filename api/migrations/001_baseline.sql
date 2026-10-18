-- Migration: 001_baseline.sql
-- Description: v3 schema (see docs/data-model.md). Replaces legacy migrations 001-013.

CREATE TABLE users (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  email       VARCHAR(255) NOT NULL UNIQUE,
  name        VARCHAR(255) NULL,
  password    VARCHAR(255) NOT NULL,
  role        ENUM('admin','member') NOT NULL DEFAULT 'member',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The organisation tree. Exactly one root (parent_id NULL), seeded below.
CREATE TABLE units (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  parent_id   INT NULL,
  name        VARCHAR(255) NOT NULL,
  code        VARCHAR(64) NULL UNIQUE,
  kind        VARCHAR(64) NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_unit_sibling (parent_id, name),
  CONSTRAINT fk_units_parent FOREIGN KEY (parent_id) REFERENCES units(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kpis (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  code              VARCHAR(64) NOT NULL UNIQUE,
  name              VARCHAR(255) NOT NULL,
  description       TEXT NULL,
  frequency         ENUM('daily','weekly','monthly','quarterly','yearly') NOT NULL,
  time_aggregation  ENUM('sum','average','last') NOT NULL,
  unit_aggregation  ENUM('sum','average','weighted_average') NOT NULL,
  weight_kpi_id     INT NULL,
  direction         ENUM('higher_is_better','lower_is_better','none') NOT NULL,
  tolerance_pct     DECIMAL(6,2) NOT NULL DEFAULT 10.00,
  applies_to_kind   VARCHAR(64) NULL,
  decimals          TINYINT NOT NULL DEFAULT 0,
  prefix            VARCHAR(10) NOT NULL DEFAULT '',
  suffix            VARCHAR(10) NOT NULL DEFAULT '',
  is_active         TINYINT(1) NOT NULL DEFAULT 1,
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_kpis_weight FOREIGN KEY (weight_kpi_id) REFERENCES kpis(id) ON DELETE RESTRICT,
  CONSTRAINT chk_kpis_weight CHECK ((unit_aggregation = 'weighted_average') = (weight_kpi_id IS NOT NULL)),
  CONSTRAINT chk_kpis_tolerance CHECK (tolerance_pct >= 0),
  CONSTRAINT chk_kpis_decimals CHECK (decimals BETWEEN 0 AND 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE imports (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NULL,
  filename        VARCHAR(255) NOT NULL,
  rows_total      INT NOT NULL,
  values_created  INT NOT NULL,
  values_updated  INT NOT NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_imports_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One value per KPI, leaf unit and period. period_start is aligned to the KPI's frequency.
CREATE TABLE kpi_values (
  id            BIGINT AUTO_INCREMENT PRIMARY KEY,
  kpi_id        INT NOT NULL,
  unit_id       INT NOT NULL,
  period_start  DATE NOT NULL,
  value         DECIMAL(20,6) NOT NULL,
  note          VARCHAR(500) NULL,
  import_id     INT NULL,
  updated_by    INT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_value (kpi_id, unit_id, period_start),
  KEY idx_unit_period (unit_id, period_start),
  CONSTRAINT fk_values_kpi FOREIGN KEY (kpi_id) REFERENCES kpis(id) ON DELETE CASCADE,
  CONSTRAINT fk_values_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE RESTRICT,
  CONSTRAINT fk_values_import FOREIGN KEY (import_id) REFERENCES imports(id) ON DELETE SET NULL,
  CONSTRAINT fk_values_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- unit_id NULL = default for every unit. unit_key lets the unique key treat that as one row.
CREATE TABLE kpi_targets (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  kpi_id          INT NOT NULL,
  unit_id         INT NULL,
  unit_key        INT AS (IFNULL(unit_id, 0)) PERSISTENT,
  effective_from  DATE NOT NULL,
  value           DECIMAL(20,6) NOT NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_target (kpi_id, unit_key, effective_from),
  CONSTRAINT fk_targets_kpi FOREIGN KEY (kpi_id) REFERENCES kpis(id) ON DELETE CASCADE,
  CONSTRAINT fk_targets_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Access to a unit covers its whole subtree.
CREATE TABLE unit_memberships (
  user_id     INT NOT NULL,
  unit_id     INT NOT NULL,
  role        ENUM('viewer','contributor') NOT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, unit_id),
  CONSTRAINT fk_memberships_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_memberships_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO units (parent_id, name, kind) VALUES (NULL, 'Organization', 'Organization');
