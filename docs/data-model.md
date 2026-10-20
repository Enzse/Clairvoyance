# Clairvoyance data model (v3 rebuild)

Status: **steps 1 and 2 implemented**, and the app is multi-tenant (SaaS): anyone can sign up
and create an organisation. Step 3 is future work.

## What the product is

A KPI scorecard for organisations whose parts (branches, stores, departments, teams, regions)
record their figures on a schedule. Each organisation is a separate tenant. The questions it
must answer at a glance:

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
| Organisation | A tenant. Owns its unit tree, KPIs, figures and imports; people join it with a role (admin or member). One person can belong to several. It has its own word for its units (`unit_label`: "branch", "department", "team"), which the interface uses instead of "unit". |
| Unit | A node in the organisation's tree (Company › Region › Branch, or Company › Sales › North team). The root carries the organisation's name. Data is entered only at **leaf** units; parents are roll-ups. Model an HQ's own figures as a leaf such as "HQ Office". |
| KPI | A definition owned by the organisation (admins create them). By default every leaf unit reports it; it can be **scoped** to some units, e.g. revenue for the Sales department only, and optionally to one kind of unit. |
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

### Change from the previous period

When both periods are fully reported, the change compares their values (`basis: "all"`).

When either period is missing reports, comparing totals would mistake a unit that hasn't
reported for a drop. For example, Downtown and Airport made 790,000 in August, but only
Downtown has reported September (452,500), so the totals read as "down 337,500". So the
change is computed **like-for-like** (`basis: "like_for_like"`), the way retailers compare
same-store sales:

1. Find the leaf units that have every native period in *both* periods.
2. Aggregate each period over only those units, with the KPI's usual rules.
3. Compare those two values: "up 42,500 for the 1 of 2 units that reported both months".

If no unit qualifies, the change is null ("not comparable yet"). The headline value itself
stays the total of what has been reported, with its coverage shown beside it.

## Schema (MariaDB 10.4)

Abridged; the source of truth is `api/migrations/`:

- `001_baseline.sql`;
- `002_organizations.sql`, which adds tenancy;
- `003_production.sql`, which adds what running on the internet needs, described after the
  schema;
- `004_unit_label.sql`, which adds `organizations.unit_label`, set from the existing units'
  most common kind.
- `005_setup_skips.sql` (skipped getting-started steps) and `006_unit_icon.sql`
  (`units.icon`, a name from the web app's icon set; NULL shows the default).

Nothing is seeded: an organisation's root unit is created together with the organisation.

```sql
CREATE TABLE users (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  email       VARCHAR(255) NOT NULL UNIQUE,
  name        VARCHAR(255) NULL,
  password    VARCHAR(255) NOT NULL,              -- roles live on organisation membership
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tenants. Every unit, KPI and import belongs to exactly one.
CREATE TABLE organizations (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  name                VARCHAR(255) NOT NULL,
  setup_dismissed_at  TIMESTAMP NULL,              -- hides the getting-started checklist
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE organization_users (
  organization_id  INT NOT NULL,
  user_id          INT NOT NULL,
  role             ENUM('admin','member') NOT NULL,  -- an organisation always keeps one admin
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (organization_id, user_id),
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)         REFERENCES users(id)         ON DELETE CASCADE
);

-- An invitation link. Only the SHA-256 of the token is stored; the link is shown once.
CREATE TABLE invitations (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  organization_id  INT NOT NULL,
  email            VARCHAR(255) NOT NULL,          -- must match the account that accepts
  role             ENUM('admin','member') NOT NULL,
  access           TEXT NULL,                      -- JSON [{unit_id, role}] granted on accept
  token_hash       CHAR(64) NOT NULL UNIQUE,
  invited_by       INT NULL,
  expires_at       DATETIME NOT NULL,              -- 14 days
  accepted_at      DATETIME NULL,                  -- single use
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (invited_by)      REFERENCES users(id)         ON DELETE SET NULL
);

-- Organisation tree, one root per organisation (it carries the organisation's name).
-- Subtrees are read with WITH RECURSIVE (small trees, no path column to maintain).
CREATE TABLE units (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  organization_id  INT NOT NULL,
  parent_id        INT NULL,
  name             VARCHAR(255) NOT NULL,
  code             VARCHAR(64) NULL,               -- optional; matches CSV rows; unique per organisation
  kind             VARCHAR(64) NULL,               -- label: Region, Branch, Department, Team…
  is_active        TINYINT(1) NOT NULL DEFAULT 1,  -- deactivate instead of deleting units with data
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_unit_sibling (parent_id, name),
  UNIQUE KEY uq_unit_code (organization_id, code),
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (parent_id) REFERENCES units(id) ON DELETE RESTRICT
);

CREATE TABLE kpis (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  organization_id   INT NOT NULL,
  code              VARCHAR(64) NOT NULL,         -- stable key for imports, unique per organisation
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
  UNIQUE KEY uq_kpi_code (organization_id, code),
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (weight_kpi_id) REFERENCES kpis(id) ON DELETE RESTRICT,
  CHECK ((unit_aggregation = 'weighted_average') = (weight_kpi_id IS NOT NULL)),
  CHECK (tolerance_pct >= 0)
);

-- "Reported by": a KPI applies to the leaf units under these units (and of applies_to_kind,
-- if set). No rows = the whole organisation. Revenue for Sales only; tickets for Support only.
CREATE TABLE kpi_scope_units (
  kpi_id   INT NOT NULL,
  unit_id  INT NOT NULL,
  PRIMARY KEY (kpi_id, unit_id),
  FOREIGN KEY (kpi_id)  REFERENCES kpis(id)  ON DELETE CASCADE,
  FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
);

CREATE TABLE imports (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  organization_id  INT NOT NULL,
  user_id          INT NULL,
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

`003_production.sql` adds:

| Table / column | Holds |
|---|---|
| `users.email_verified_at` | When the address was confirmed (accounts from before 003 count as confirmed) |
| `users.session_version` | Sessions remember it; a password reset or change bumps it, signing out the other sessions |
| `user_tokens` | One-time links: `verify_email` (48 h) and `reset_password` (1 h). Only SHA-256 hashes are stored; a new link replaces the old one |
| `invitations.emailed_at` | Whether the invitation was emailed; accepting an emailed one confirms the address |
| `rate_limits` | Attempt counters per bucket (`login:<email hash>`, `login-ip:<ip>`, …) in fixed windows |
| `kpi_value_changes` | Every change to a figure: old and new value and note (NULL = absent), who, which import, when |

Single units with children or data are deactivated rather than deleted, so `units.parent_id`
and `kpi_values.unit_id` are `RESTRICT`. Deleting a whole organisation therefore runs in order
(`OrganizationRepository::deleteWithData`):

1. delete its figures;
2. unlink weighted KPIs from their weights;
3. detach units from their parents;
4. delete the organisation, which cascades to the rest.

## Running on the internet

- **Email** (SMTP, configured with `MAIL_*` in `api/.env`):
  - **Confirmation:** a link is sent on sign-up.
  - **Password reset:** "forgot password" sends a reset link. The answer is the same whether or
    not the account exists.
  - **Invitations:** emailed automatically, but only once the inviter's own address is
    confirmed, so nobody can use the app to email strangers from an unconfirmed account.
    Until then, invitation links are copied by hand as before.
  - **Without `MAIL_HOST`:** nothing is emailed, addresses count as confirmed, and a forgotten
    password needs `scripts/reset_password.php`.
- **Throttling:**
  - **Sign-in:** 5 wrong passwords lock that email for 15 minutes, and 30 failures from one
    address lock that address for 15 minutes.
  - **Sign-up:** 10 per hour per address.
  - **Password resets:** 3 per hour per email and 10 per hour per address.
  - **Other:** 3 confirmation resends per hour; 100 invitation emails per organisation per
    day.
- **Sessions:**
  - stored in `api/storage/sessions` and last 7 days;
  - the cookie is HttpOnly, SameSite=Lax, and Secure when `APP_URL` is https.
- **History:** `ValueRepository` writes `kpi_value_changes` for every insert, update or delete
  of a figure, from data entry and imports alike. Writes that change nothing aren't recorded.
- **Export:** returns the leaf figures of a subtree. The web app writes them as a wide CSV
  (unit, period, one column per KPI) that the import wizard reads back unchanged.
- **Operations:** deployment, backups and monitoring are in
  [deployment.md](deployment.md).

## Organisations and onboarding

Signing up creates an account and nothing else. The person is then taken through onboarding:

1. **Name** the organisation (and pick a currency for money KPIs). This creates the
   organisation, its root unit and makes them its admin.
2. **Structure:** one team, several locations or several departments; names typed one per
   line become leaf units under the root.
3. **KPIs** from a starter library (revenue, customer satisfaction, headcount…), each with
   sensible aggregation rules; with several top-level units, each KPI can be limited to the
   ones that report it.
4. **Invite** people by email with a role and unit access. The link is emailed once the
   founder has confirmed their address, and is always shown to copy as well.

Afterwards the scorecard shows a step-by-step getting-started guide (structure, KPIs, targets,
first figures, team). Structure, targets and inviting can be skipped (`organizations.setup_skipped`,
migration 005); KPIs and figures can't. It ends with an "is ready" card, and can be hidden
and brought back from Admin › Settings. Someone with an invitation link signs in or registers with
the invited email and joins that organisation; accepting never demotes an existing member.
A person can belong to several organisations and switch between them; the session remembers
the selected one, and every request re-checks that membership.

## Permissions

| Who | Can |
|---|---|
| Organisation admin | everything in that organisation: units, KPIs, targets, people, invitations, any data |
| Member, contributor on unit U | view U's subtree; enter and import values for leaf units in it |
| Member, viewer on unit U | view U's subtree |

Whole-organisation read access = viewer on the root unit. A member without unit access sees an
empty scorecard. Access follows the organisation tree; there's no per-dashboard sharing. The
last admin can't be demoted, removed or leave. Anyone can leave an organisation or delete their
account. Deleting an account takes along organisations where you're the only person, and it's
refused while you're the only admin of one that has others. Only an admin can delete an
organisation, by typing its name and their password. `php scripts/set_role.php <email> admin
[org-id]` recovers admin access from the command line.

## API

All responses are JSON: `{"success": true, "data": …}` or
`{"success": false, "error": "…", "details": {field: message}}`. Dates are `YYYY-MM-DD`; any
date inside a period selects that period. `grain` is one of the frequencies and must be at least
as coarse as the KPI's frequency.

| Method | Path | Purpose |
|---|---|---|
| POST | `/auth/register`, `/auth/login`, `/auth/logout` | Session-cookie auth. Register needs `accept_terms: true`; the Terms version is recorded |
| GET/PUT | `/auth/me` | Current user (with `email_verified`, `email_delivery`), their organisations, the selected one and unit memberships / change name or password (needs `current_password`) |
| DELETE | `/auth/me` | Delete your account `{password}` |
| POST | `/auth/verify-email`, `/auth/verify-email/resend` | Confirm an address `{token}` (works signed out) / send a new link |
| POST | `/auth/forgot-password`, `/auth/reset-password` | Email a reset link `{email}` / set a new password `{token, password}` and sign in |
| GET | `/health` | `{ok: true}` when PHP and the database work, for uptime monitoring |
| POST | `/organizations` | Create an organisation (you become its admin) and select it |
| POST | `/organizations/select` | Switch to another organisation you belong to |
| PUT | `/organization` | Admin: `{name?, unit_label?}`: rename (also renames the root unit) and/or change the word for units |
| DELETE | `/organization` | Admin: delete it with everything in it `{name, password}` |
| POST | `/organization/leave` | Leave the current organisation (not as its last admin) |
| GET | `/organization/setup` | Admin: the getting-started guide: steps done (worked out from the data), skipped, skippable, `complete`, counts |
| POST | `/organization/setup/dismiss` | Admin: hide the guide `{dismissed?: true}`, or bring it back with `false` |
| POST | `/organization/setup/skip` | Admin: skip a step that can wait (structure, targets, people) `{step, skipped?: true}`, or take it back |
| GET | `/members` | Admin: people in the organisation, with role and unit access |
| PUT/DELETE | `/members/{id}` | Admin: change role / remove from the organisation (not the last admin) |
| PUT | `/members/{id}/memberships` | Admin: replace unit access `[{unit_id, role}]` |
| GET/POST | `/invitations` | Admin: pending invitations / create one (the response holds the link token, once) |
| DELETE | `/invitations/{id}` | Admin: revoke |
| GET | `/invitations/lookup?token=` | Public: organisation name, inviter and email for an invite page |
| POST | `/invitations/accept` | Join with `{token}`; the signed-in email must match |
| GET | `/units` | Units of the selected organisation visible to the current user, flat, with `parent_id`, `is_leaf` and `access` |
| POST/PUT/DELETE | `/units[/{id}]` | Admin; delete only an empty leaf (otherwise deactivate) |
| GET | `/kpis` | Active KPI definitions (`?include_inactive=1` for admins), each with the organisation-wide `target` in force today and `unit_target_count` |
| POST/PUT | `/kpis[/{id}]` | Admin; deactivate with `is_active: false` |
| GET/PUT | `/kpis/{id}/targets` | Read / replace (admin) `[{unit_id \| null, effective_from, value}]` |
| GET | `/values?unit=&date=` | Data-entry form for a leaf unit and the month containing `date`: every period of every KPI that applies (each week or day of weekly and daily KPIs, up to today; otherwise the period the month is in), each with its value, note and the previous period's value |
| GET | `/values/progress?date=` | For each active leaf unit the user can enter data for: how many figures the month needs (counted as on the form) and how many are in |
| PUT | `/values` | Batch upsert `{values: [{kpi_id, unit_id, period, value, note}]}`; `value: null` deletes; all-or-nothing |
| POST | `/imports/preview` | Multipart `file` + `mapping` JSON; per-row results, nothing written |
| POST | `/imports` | Same, committed in one transaction; `skip_invalid=1` writes only the valid values |
| GET | `/imports` | Admin: recent imports |
| GET | `/scorecard?unit=[&date=][&grain=]` | Every KPI: value, target, status, coverage, previous period, change, 12-period sparkline. Without `grain`, each KPI is shown at its own frequency. |
| GET | `/kpis/{id}/series?unit=&grain=[&from=][&to=]` | Value, target, status and coverage per period, and the change into the last period; defaults to the 12 periods ending at the latest data |
| GET | `/kpis/{id}/breakdown?unit=&grain=[&date=]` | The unit's direct children, each with value, target, status, coverage |
| GET | `/kpis/{id}/history?unit=[&date=][&before=]` | `{changes, more}`: changes to the KPI's figures in the unit's subtree, newest first, 20 at a time (`before` = the last id shown), optionally for one period |
| GET | `/exports/values?unit=&from=&to=` | Every figure in the unit's subtree with a period start in the range, plus the units and KPIs to label them |

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

The import wizard in the web app suggests the mapping from the column names and contents,
remembers it for the next file with the same columns, reads Excel (`.xlsx`) files by turning
the first sheet into CSV in the browser, and offers a template: one row per unit the person
reports for, one column per KPI. Not yet supported: notes from a CSV column.

## What replaced the old system

| Old | Now |
|---|---|
| `kpis`, `kpi_entries` | Replaced by `kpis`, `kpi_values`, `kpi_targets` |
| `users` with a global role | Accounts only; `admin` / `member` is per organisation (`organization_users`) |
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
2b. **Multi-tenant — done.** Organisations, sign-up onboarding, invitation links, switching
   between organisations, KPI scope ("Reported by"), account settings.
2c. **Production — done.**
   - email (verification, password reset, invitations);
   - throttling;
   - change history and CSV export;
   - leaving and deleting organisations and accounts;
   - release packaging, CI and deploy, daily backups.

   See [deployment.md](deployment.md).
2d. **Polish — done.** Every screen redesigned around cards, slide-in panels and short
   tutorials: Structure (tree, who has access), KPIs (cards, a four-step "New KPI"), People,
   Settings, Add data (who still has figures to enter; weekly and daily periods; notes),
   spreadsheet upload (Excel, template, remembered matching), a KPI's page, sign-in and
   onboarding. Light theme by default.
3. **Later:**
   - saved dashboards;
   - print-friendly report page;
   - calculated KPIs (ratio of two KPIs);
   - fiscal-year start month;
   - late-submission reminders, which can now be emailed.
