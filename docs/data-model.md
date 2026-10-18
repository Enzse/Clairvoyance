# Clairvoyance data model (v3 rebuild)

Status: **steps 1 and 2 implemented**: the backend and the web app. Step 3 is future work.

## What the product is

A scorecard for organisations with many units — branches, stores, schools, clinics,
departments — that report the same KPIs on a schedule. The questions it must answer at a
glance:

1. Are we on target this period? (overall, and for any unit)
2. Is it getting better or worse than last period?
3. Which units are behind, and which haven't reported yet?

Everything is keyed on **KPI × unit × period**.

## Principles

- **The KPI definition owns its meaning.** How a KPI combines over time and across units is
  set once, on the KPI. Widgets and reports never choose an aggregation, so the same KPI
  shows the same number everywhere.
- **One value per KPI, unit and period.** Re-entering or re-importing replaces the value
  (upsert); it never duplicates it.
- **The server computes; the frontend draws.** All aggregation, targets and status live in
  one PHP module with unit tests. Charts, the scorecard and exports call the same endpoints.
- **Roll-ups are computed from the leaf units**, never as averages of averages.
- **Missing data is visible.** Every aggregate says how many units reported out of how many
  were expected.

## Concepts

| Concept | Meaning |
|---|---|
| Unit | A node in the organisation tree (Org › Region › Division › School). Data is entered only at **leaf** units; parents are roll-ups. Model an HQ's own figures as a leaf such as "HQ Office". |
| KPI | An organisation-wide definition (admins create them, not individual users). |
| Frequency | How often the KPI is reported: daily, weekly (ISO, Monday start), monthly, quarterly, yearly. |
| Period | Identified by its start date, aligned to the KPI's frequency (`2025-06-01` for June). |
| Grain | The period length you view at (≥ frequency): a monthly KPI can be viewed monthly, quarterly or yearly. |
| Target | A value per native period, either a default for every unit or set for a specific unit, effective from a date. |

## Aggregation

Each KPI has two rules.

**Over time** (`time_aggregation`), within one unit:

| Rule | Use for | Example |
|---|---|---|
| `sum` | flows | sales, absences, units sold |
| `average` | rates measured each period | attendance %, satisfaction score |
| `last` | stocks / snapshots | headcount, inventory, enrolment |

**Across units** (`unit_aggregation`), combining leaf units:

| Rule | Use for |
|---|---|
| `sum` | totals: students, revenue |
| `average` | simple mean across units (each unit counts equally) |
| `weighted_average` | rates where bigger units should count more; weighted by another KPI (e.g. attendance % weighted by enrolment) |

**Order:** first over time within each leaf unit, then across leaf units. Units with no
values in the period are excluded from the value and counted as missing in `coverage`.

### Worked example — NCR region, Q2 2025 (from `api/deped.csv`)

Two schools, monthly reports for April–June.

| | Doña Carmen | Manila Science |
|---|---|---|
| Total students (Apr / May / Jun) | 1810 / 1812 / 1815 | 1247 / 1250 / 1252 |
| Attendance % (Apr / May / Jun) | 95.9 / 95.2 / 94.9 | 97.5 / 97.0 / 96.6 |
| Teacher absences (Apr / May / Jun) | 2 / 3 / 3 | 0 / 0 / 1 |

- **Total students** — `last`, `sum`: 1815 + 1252 = **3,067**.
  The current app shows either 9,186 (summing all six rows) or ~1,531 (averaging them).
- **Teacher absences** — `sum`, `sum`: (2+3+3) + (0+0+1) = **9**.
- **Attendance %** — `average`, `weighted_average` by Total students:
  each school's Q2 average (95.33, 97.03), weighted by its Q2 enrolment (1815, 1252)
  = **96.03%**. A plain average of the two schools would say 96.18%.

These figures become acceptance tests for the aggregator.

## Targets and status

**Resolving a target** for a leaf unit and native period: the unit's own target with the
latest `effective_from` on or before the period; otherwise the default (no unit) target the
same way.

**Aggregated targets** use the same two rules as the values, over the same leaf units and
periods — so a monthly enrolment target of 1,800 per school becomes a region target of
1,800 × number of schools. If a parent unit has its own explicit target, that wins for that
unit's view.

**Status** compares value `x` with target `t` using the KPI's `tolerance_pct` (default 10):

| Direction | Green | Amber | Red |
|---|---|---|---|
| higher_is_better | x ≥ t | x ≥ t − \|t\|·tol | otherwise |
| lower_is_better | x ≤ t | x ≤ t + \|t\|·tol | otherwise |
| none | no status (tracked only) | | |

No target → no status. Coverage below 100% is shown alongside the status ("4 of 5 reported"),
never hidden.

This replaces `rag_red` / `rag_amber`, whose meaning the frontend and backend disagreed on.

## Schema (MariaDB 10.4)

Abridged; the source of truth is `api/migrations/001_baseline.sql`, which also seeds the root
unit ("Organization").

```sql
CREATE TABLE users (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  email       VARCHAR(255) NOT NULL UNIQUE,
  name        VARCHAR(255) NULL,
  password    VARCHAR(255) NOT NULL,
  role        ENUM('admin','member') NOT NULL DEFAULT 'member',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Organisation tree. Subtrees are read with WITH RECURSIVE (small trees, no path column to maintain).
CREATE TABLE units (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  parent_id   INT NULL,
  name        VARCHAR(255) NOT NULL,
  code        VARCHAR(64) NULL UNIQUE,          -- optional; used to match CSV rows
  kind        VARCHAR(64) NULL,                 -- label: Region, Division, School, Branch…
  is_active   TINYINT(1) NOT NULL DEFAULT 1,    -- deactivate instead of deleting units with data
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_unit_sibling (parent_id, name),
  FOREIGN KEY (parent_id) REFERENCES units(id) ON DELETE RESTRICT
);

CREATE TABLE kpis (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  code              VARCHAR(64) NOT NULL UNIQUE,  -- stable key for imports, e.g. total_students
  name              VARCHAR(255) NOT NULL,
  description       TEXT NULL,
  frequency         ENUM('daily','weekly','monthly','quarterly','yearly') NOT NULL,
  time_aggregation  ENUM('sum','average','last') NOT NULL,
  unit_aggregation  ENUM('sum','average','weighted_average') NOT NULL,
  weight_kpi_id     INT NULL,                     -- required iff weighted_average; same frequency
  direction         ENUM('higher_is_better','lower_is_better','none') NOT NULL,
  tolerance_pct     DECIMAL(6,2) NOT NULL DEFAULT 10.00,
  applies_to_kind   VARCHAR(64) NULL,             -- NULL = every leaf unit; else only units of this kind
  decimals          TINYINT NOT NULL DEFAULT 0,
  prefix            VARCHAR(10) NOT NULL DEFAULT '',
  suffix            VARCHAR(10) NOT NULL DEFAULT '',
  is_active         TINYINT(1) NOT NULL DEFAULT 1,
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (weight_kpi_id) REFERENCES kpis(id) ON DELETE RESTRICT,
  CHECK ((unit_aggregation = 'weighted_average') = (weight_kpi_id IS NOT NULL)),
  CHECK (tolerance_pct >= 0)
);

CREATE TABLE imports (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NULL,
  filename      VARCHAR(255) NOT NULL,
  rows_total    INT NOT NULL,
  values_created INT NOT NULL,
  values_updated INT NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE kpi_values (
  id            BIGINT AUTO_INCREMENT PRIMARY KEY,
  kpi_id        INT NOT NULL,
  unit_id       INT NOT NULL,                     -- must be a leaf unit (enforced in app)
  period_start  DATE NOT NULL,                    -- aligned to the KPI's frequency (enforced in app)
  value         DECIMAL(20,6) NOT NULL,           -- negatives allowed
  note          VARCHAR(500) NULL,
  import_id     INT NULL,
  updated_by    INT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_value (kpi_id, unit_id, period_start),
  KEY idx_unit_period (unit_id, period_start),
  FOREIGN KEY (kpi_id)     REFERENCES kpis(id)    ON DELETE CASCADE,
  FOREIGN KEY (unit_id)    REFERENCES units(id)   ON DELETE RESTRICT,
  FOREIGN KEY (import_id)  REFERENCES imports(id) ON DELETE SET NULL,
  FOREIGN KEY (updated_by) REFERENCES users(id)   ON DELETE SET NULL
);

CREATE TABLE kpi_targets (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  kpi_id          INT NOT NULL,
  unit_id         INT NULL,                       -- NULL = default for every unit
  unit_key        INT AS (IFNULL(unit_id, 0)) PERSISTENT,  -- lets UNIQUE treat the default as one row
  effective_from  DATE NOT NULL,
  value           DECIMAL(20,6) NOT NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_target (kpi_id, unit_key, effective_from),
  FOREIGN KEY (kpi_id)  REFERENCES kpis(id)  ON DELETE CASCADE,
  FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
);

-- Access is granted on a unit and covers its whole subtree.
CREATE TABLE unit_memberships (
  user_id     INT NOT NULL,
  unit_id     INT NOT NULL,
  role        ENUM('viewer','contributor') NOT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, unit_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
);
```

## Permissions

| Who | Can |
|---|---|
| admin | everything: units, KPIs, targets, users, memberships, any data |
| contributor on unit U | view U's subtree; enter and import values for leaf units in it |
| viewer on unit U | view U's subtree |

Whole-organisation read access = viewer on the root unit. Per-dashboard sharing and share
tokens go away; access follows the organisation tree.

The first account registered on a fresh install becomes the admin. Everyone who registers
after that is a member and sees nothing until an admin grants them access to units.
`php scripts/set_role.php <email> admin` recovers admin access from the command line.

## API

All responses are JSON: `{"success": true, "data": …}` or
`{"success": false, "error": "…", "details": {field: message}}`. Dates are `YYYY-MM-DD`; any
date inside a period selects that period. `grain` is one of the frequencies and must be at least
as coarse as the KPI's frequency.

| Method | Path | Purpose |
|---|---|---|
| POST | `/auth/register`, `/auth/login`, `/auth/logout` | Session-cookie auth |
| GET | `/auth/me` | Current user with memberships |
| GET/POST | `/users` | Admin: list / create users |
| PUT/DELETE | `/users/{id}` | Admin: change name, role or password / delete (not yourself, not the last admin) |
| GET/PUT | `/users/{id}/memberships` | Admin: read / replace `[{unit_id, role}]` |
| GET | `/units` | Units visible to the current user, flat, with `parent_id`, `is_leaf` and `access` |
| POST/PUT/DELETE | `/units[/{id}]` | Admin; delete only an empty leaf (otherwise deactivate) |
| GET | `/kpis` | Active KPI definitions (`?include_inactive=1` for admins) |
| POST/PUT | `/kpis[/{id}]` | Admin; deactivate with `is_active: false` |
| GET/PUT | `/kpis/{id}/targets` | Read / replace (admin) `[{unit_id \| null, effective_from, value}]` |
| GET | `/values?unit=&date=` | Data-entry form: every KPI that applies to a leaf unit, at the period containing `date` |
| PUT | `/values` | Batch upsert `{values: [{kpi_id, unit_id, period, value, note}]}`; `value: null` deletes; all-or-nothing |
| POST | `/imports/preview` | Multipart `file` + `mapping` JSON; per-row results, nothing written |
| POST | `/imports` | Same, committed in one transaction; `skip_invalid=1` writes only the valid values |
| GET | `/imports` | Admin: recent imports |
| GET | `/scorecard?unit=[&date=][&grain=]` | Every KPI: value, target, status, coverage, previous period, change, 12-period sparkline. Without `grain`, each KPI is shown at its own frequency. |
| GET | `/kpis/{id}/series?unit=&grain=[&from=][&to=]` | Value, target, status and coverage per period; defaults to the 12 periods ending at the latest data |
| GET | `/kpis/{id}/breakdown?unit=&grain=[&date=]` | The unit's direct children, each with value, target, status, coverage |

On the report endpoints a missing `date` (or `to`) means the latest period with data for that
unit, so a view never opens on an empty period.

## CSV import

One wide file can fill many KPIs and units at once (as `deped.csv` does):

1. **Unit:** several columns forming a path (`Region`, `Division`, `School_Name`), or one
   column matched by unit name (a leaf anywhere under the parent) or by unit code.
   Admins can let the import create missing units, with a kind per path level.
2. **Date:** one column plus an explicit format (`YYYY-MM-DD`, `DD/MM/YYYY`, `MM/DD/YYYY`,
   `YYYY-MM`). Impossible dates such as `31/02/2025` are rejected, never rolled over. Dates
   snap to each KPI's period start (`2025-06-30` → June 2025), and the preview shows the period.
3. **KPIs:** map columns to KPI codes; unmapped columns are ignored. Numbers may use thousands
   separators (`1,250`) and a trailing `%`. Empty cells are skipped and counted.
4. **Preview** lists every rejected cell with its row, column and reason (wrong date, unknown
   unit, not a number, non-leaf unit, KPI not applicable, duplicate of an earlier row), plus
   how many values would be new, changed or unchanged, and which units would be created.
5. **Commit** refuses a file with errors unless `skip_invalid` is set, then upserts in one
   transaction. Re-importing the same file reports every value as unchanged.

Not yet supported: notes from a CSV column, and auto-suggesting the mapping (that belongs in
the frontend wizard).

## What replaced the old system

| Old | Now |
|---|---|
| `kpis`, `kpi_entries` | Replaced by `kpis`, `kpi_values`, `kpi_targets` |
| `users` | Kept; roles become `admin` / `member` |
| `dashboards`, `dashboard_access`, `dashboard_share_tokens` | Dropped. The scorecard becomes the home page. Custom dashboards return later as saved views on `/series` (KPI + unit + grain + relative range like "last 12 months"). |
| Migrations 000–013 | Replaced by one baseline migration; the old files stay in git history. Fixes fresh installs (013 currently fails). |
| Widget-side aggregation, pie charts, value-bucket charts, screenshot PDF | Removed. Charts become thin renderers of `/series` data. |
| Frontend deps | Keep Chart.js + date adapter; drop svelte-grid, jspdf, html2canvas, zoom/datalabels/annotation plugins until needed. |

## Build order

1. **Core (backend) — done.** Baseline migration; `Domain\Period` and `Domain\Aggregator`
   as plain PHP classes unit-tested without a database; units/KPIs/targets/values, import
   and scorecard/series/breakdown endpoints. `tests/Integration/DepedImportTest.php` imports
   `deped.csv` through the API and reproduces the worked-example numbers.
2. **Frontend — done.** Scorecard home with unit picker and period/grain selector; KPI detail
   (series chart + breakdown); data-entry form (one unit and month, all its KPIs); import
   wizard; admin screens for units, KPIs, targets, people and access. See `web/README.md`.
3. **Later:** saved dashboards, print-friendly report page, value change history,
   calculated KPIs (ratio of two KPIs), fiscal-year start month, late-submission reminders.
