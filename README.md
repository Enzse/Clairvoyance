# Clairvoyance

A KPI scorecard for organisations with many units (branches, stores, schools, departments)
that report the same KPIs on a schedule. It answers: are we on target, is it getting better
or worse, and which units are behind or haven't reported?

- **Design:** [docs/data-model.md](docs/data-model.md): concepts, aggregation rules, schema, API
- **Backend:** [api/](api/README.md): PHP 8.2 + MariaDB on XAMPP
- **Frontend:** [web/](web/README.md): SvelteKit + Tailwind

## Getting started

1. Start Apache and MySQL in XAMPP.
2. `cd api`, then `php scripts/migrate.php`.
3. `cd web`, then `npm install` and `npm run dev`, and open http://localhost:5173.
4. Create an account: the first one becomes the administrator.
5. Optional sample data: `php api/scripts/seed_demo.php <your-email>`.
