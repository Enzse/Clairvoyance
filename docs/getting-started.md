# Run Clairvoyance on your own computer

This gets Clairvoyance running on a Windows computer with XAMPP, with your own account, your
own organisation, and a sample organisation full of school data to explore. It takes about 20
minutes.

## What you need

- **XAMPP** with PHP 8.2 (it includes Apache and MariaDB): https://www.apachefriends.org
- **Node.js** 20 or newer: https://nodejs.org
- **Git**: https://git-scm.com

## 1. Get the code

Open a terminal (Git Bash, or PowerShell) and run:

```bash
cd C:\xampp\htdocs
git clone https://github.com/Enzse/Clairvoyance.git clairvoyance-v3
cd clairvoyance-v3
git checkout v3-rebuild
```

Keep the folder name `clairvoyance-v3`: the web app expects the API at
`http://localhost/clairvoyance-v3/api`. To use another folder name, copy `web/.env.example`
to `web/.env` and change `VITE_API_URL`.

If you want your own copy on GitHub (for a thesis, for example), **fork** the repository
first and clone your fork instead.

## 2. Start Apache and MySQL

Open the XAMPP Control Panel and start **Apache** and **MySQL**.

## 3. Set up the API and the database

```bash
cd api
copy .env.example .env
C:\xampp\php\php.exe scripts\migrate.php
```

- In Git Bash, use `cp` instead of `copy`.
- The defaults in `.env` suit a fresh XAMPP: user `root`, no password, a database called
  `clairvoyance_v3`. `migrate.php` creates the database and all its tables.

Check it works: open http://localhost/clairvoyance-v3/api/health. It should say `"ok":true`.

## 4. Start the web app

```bash
cd ..\web
npm install
npm run dev
```

Open http://localhost:5173.

## 5. Make your account

1. Click **Create account**. Nothing is emailed locally, so there's no confirmation step.
2. Follow the short setup: name your organisation, add its branches or departments, pick a
   few KPIs, and skip inviting people for now.

## 6. Load the sample data

In a second terminal, from the `clairvoyance-v3` folder, using the email you signed up with:

```bash
C:\xampp\php\php.exe api\scripts\seed_demo.php you@example.com
```

This adds an organisation called **DepEd sample** with:

- 14 schools grouped into regions and divisions;
- six KPIs with targets (attendance rate, average grade, teacher absences…);
- a year of monthly figures from `api/demo/deped.csv`.

Switch to it from the organisation name in the top-left of the app. It's separate from your
own organisation, and running the script again only refreshes the figures.

## 7. Things to try

- **Scorecard:** every KPI against its target, the trend and the status, and which schools
  are behind. Switch between Month, Quarter and Year to see figures add up over time.
- **A KPI's page:** click any KPI for its chart, the breakdown by region or school, and the
  history of every change.
- **Add data:** type in a month's figures, or upload a spreadsheet (try editing
  `api/demo/deped.csv`).
- **Manage › Structure:** the tree of regions, divisions and schools. The **?** button
  explains it.
- **Manage › KPIs:** how each KPI adds up. A rate like attendance is *weighted by the number
  of students*, not simply averaged, so the totals come out right.
- **Manage › People:** invite a second account (use another email). It gets a link; open it
  in a private window to see what a member sees.

## 8. Run the tests

```bash
C:\xampp\php\php.exe api\tests\run.php
cd web
npm test
npm run check
```

The API tests use their own database (`clairvoyance_v3_test`) and never touch your data.

## 9. Where to read the code

| Start with | For |
|---|---|
| [docs/data-model.md](data-model.md) | The concepts, how figures add up (time × units), targets and status, every API endpoint |
| [api/README.md](../api/README.md) | The PHP back end: folders, scripts, tests |
| [web/README.md](../web/README.md) | The SvelteKit front end: pages and components |
| `api/domain/Aggregator.php` | The heart of it: combining figures over time and across units |
| `api/services/ReportService.php` | The scorecard, charts and like-for-like comparisons |

## When something goes wrong

| Problem | Fix |
|---|---|
| "Could not reach the server" in the app | Apache isn't running, or the folder isn't `C:\xampp\htdocs\clairvoyance-v3` |
| `/api/health` shows an error | MySQL isn't running, or `api/.env` has the wrong database user or password |
| Port 5173 is in use | Another `npm run dev` is running; close it, or use the address it prints |
| Forgot your password | `C:\xampp\php\php.exe api\scripts\reset_password.php you@example.com` prints a new one |
| Locked out after wrong passwords | Wait 15 minutes; five wrong tries lock an email for that long |
