# Clairvoyance

A KPI scorecard for organisations with many units (branches, stores, schools, departments)
that report the same KPIs on a schedule. It answers: are we on target, is it getting better
or worse, and which units are behind or haven't reported?

- **Design:** [docs/data-model.md](docs/data-model.md): concepts, aggregation rules, schema, API
- **Backend:** [api/](api/README.md): PHP 8.2 + MariaDB on XAMPP
- **Frontend:** [web/](web/): SvelteKit + Tailwind

## Status

The backend has been rebuilt around the new data model and is covered by tests. The
frontend still targets the old API and is the next thing to rebuild, so the web app does not
work yet.
