# Clairvoyance API

Vanilla PHP 8.2 + MariaDB 10.4 (XAMPP). The data model, aggregation rules and endpoint list
are in [docs/data-model.md](../docs/data-model.md).

## Setup

```bash
cd C:\xampp\htdocs\clairvoyance-v3\api
copy .env.example .env               # then edit if your MySQL isn't root with no password
php scripts/migrate.php              # creates the database and tables
```

Open the web app and register: **the first account becomes the admin.** To load the DepEd
sample (6 KPIs, 14 units, a year of monthly school reports):

```bash
php scripts/seed_demo.php you@example.com
```

## Scripts

| Command | What it does |
|---|---|
| `php scripts/migrate.php` | Run pending migrations (creates the database if needed) |
| `php scripts/migrate.php --fresh` | Drop every table first. **Deletes all data.** |
| `php scripts/seed_demo.php <admin-email>` | Load the DepEd sample; safe to re-run |
| `php scripts/set_role.php <email> <admin\|member>` | Change a role from the command line (e.g. lost admin) |

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
| `migrations/` | `001_baseline.sql` is the whole schema |
| `demo/` | Sample data |

`.htaccess` routes every request to `index.php`, so no other file in this folder is served or
executed by Apache.

## Conventions

- Controllers return arrays (sent as `{"success": true, "data": …}`) or a `Response`.
- Throw `Core\HttpException` for errors the client should see; anything else is logged and
  returned as a generic 500.
- Database errors propagate; repositories never swallow them.
