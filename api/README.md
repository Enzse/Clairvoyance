# Clairvoyance API

Vanilla PHP 8.2 + MariaDB 10.4 (XAMPP). The data model, aggregation rules and endpoint list
are in [docs/data-model.md](../docs/data-model.md).

## Setup

```bash
cd C:\xampp\htdocs\clairvoyance-v3\api
copy .env.example .env               # then edit if your MySQL isn't root with no password
php scripts/migrate.php              # creates the database and tables
```

Open the web app and sign up; onboarding creates your organisation with you as its admin.
Others join through invitation links. To give your account a separate "DepEd sample"
organisation (6 KPIs, 14 schools, a year of monthly reports):

```bash
php scripts/seed_demo.php you@example.com
```

## Scripts

| Command | What it does |
|---|---|
| `php scripts/migrate.php` | Run pending migrations (creates the database if needed) |
| `php scripts/migrate.php --fresh` | Drop every table first. **Deletes all data.** |
| `php scripts/seed_demo.php <email>` | Give an account a "DepEd sample" organisation (schools, KPIs, a year of figures); safe to re-run |
| `php scripts/set_role.php <email> <admin\|member> [organization-id]` | Change someone's role in an organisation (e.g. lost admin); the id is needed only if they belong to several |
| `php scripts/reset_password.php <email>` | Set and print a new random password (for when email isn't set up) |
| `php scripts/daily.php` | Daily cron job: database backup to `storage/backups` (keeps 14) and cleanup of expired links and counters |

Runtime files (sessions, `logs/api.log`, backups) live in `storage/`, which is created on first
use and never committed.

## Tests

```bash
php tests/run.php                # everything
php tests/run.php Unit           # domain logic only, no database
php tests/run.php DepedImport    # classes whose name contains the filter
```

Integration tests run the API in-process against `clairvoyance_v3_test`, which they create
and rebuild themselves. They refuse to run against a database whose name doesn't end in
`_test`; override the settings in `api/.env.test` if needed. PHP warnings fail a test.

## Layout

| Folder | Contents |
|---|---|
| `core/` | Framework: routing, requests/responses, auth session, validation, DI container, migrations |
| `domain/` | Pure logic with no database: periods, the unit tree, aggregation, targets, status |
| `repositories/` | SQL, one class per table |
| `services/` | Business rules and access control |
| `controllers/` | HTTP: read the request, check the role, call a service |
| `routes.php` | Every endpoint |
| `migrations/` | `001_baseline.sql` is the schema; `002_organizations.sql` makes it multi-tenant; `003_production.sql` adds email links, throttling and change history |
| `demo/` | Sample data |

`.htaccess` routes every request to `index.php`, so no other file in this folder is served or
executed by Apache.

## Conventions

- Controllers return arrays (sent as `{"success": true, "data": …}`) or a `Response`.
- Throw `Core\HttpException` for errors the client should see; anything else is logged and
  returned as a generic 500.
- Database errors propagate; repositories never swallow them.
- Every repository method that reads tenant data takes the organisation id explicitly;
  services get it from `Auth::organizationId()`, which re-checks membership on each request.
