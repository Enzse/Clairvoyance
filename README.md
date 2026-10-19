# Clairvoyance

A KPI scorecard for organisations whose branches, departments or teams record their figures
on a schedule. It answers: are we on target, is it getting better or worse, and which units
are behind or haven't reported? Anyone can sign up and set up their own organisation.

- **Design:** [docs/data-model.md](docs/data-model.md): concepts, aggregation rules, schema, API
- **Backend:** [api/](api/README.md): PHP 8.2 + MariaDB on XAMPP
- **Frontend:** [web/](web/README.md): SvelteKit + Tailwind

## Getting started

1. Start Apache and MySQL in XAMPP.
2. `cd api`, then `php scripts/migrate.php`.
3. `cd web`, then `npm install` and `npm run dev`, and open http://localhost:5173.
4. Create an account. Onboarding walks you through naming your organisation, its branches
   or departments, starter KPIs and invitations; you're its admin.
5. Optional sample data: `php api/scripts/seed_demo.php <your-email>` loads it into the
   organisation you administer.
