# Clairvoyance web app

SvelteKit 2 + Svelte 5 + Tailwind 4, built as a single-page app that talks to the PHP API in
[`../api`](../api/README.md) with a session cookie.

## Run it

```bash
npm install
npm run dev          # http://localhost:5173
```

The API must be running under XAMPP. By default the app calls
`http://localhost/clairvoyance-v3/api`; to change that, copy `.env.example` to `.env` and set
`VITE_API_URL`. The API only accepts cookies from origins listed in `ALLOWED_ORIGINS` in
`api/.env` (it includes `http://localhost:5173`).

```bash
npm run check        # type-check
npm test             # unit tests (period maths, chart helpers, CSV detection, formatting)
npm run build        # static build in build/, served by any web server with an index.html fallback
```

## Where things are

| Path | What |
|---|---|
| `src/routes/(app)/+page.svelte` | Scorecard: one unit, one period, every KPI, and the units × KPIs matrix |
| `src/routes/(app)/kpis/[id]` | One KPI: trend, by-unit breakdown, how it's calculated |
| `src/routes/(app)/enter` | Data entry for one unit and month |
| `src/routes/(app)/import` | CSV import wizard |
| `src/routes/(app)/admin` | Organisation tree, KPI definitions and targets, people, access and invitations |
| `src/routes/(app)/account` | Your name and password |
| `src/routes/(auth)` | Sign in and create account |
| `src/routes/onboarding` | After sign-up: name, structure, starter KPIs (`src/lib/starter-kpis.ts`), invites |
| `src/routes/invite/[token]` | Accept an invitation link |
| `src/lib/api.ts` | Typed API client |
| `src/lib/components` | UI pieces; charts are hand-drawn SVG (`TrendChart`, `Sparkline`, `BreakdownBars`, `StatusMatrix`) |
| `src/app.css` | Design tokens (light and dark) and base component styles |

The unit, period and period length live in the URL (`?unit=&date=&grain=`), so every view can be
bookmarked or shared. All numbers come from the API; the frontend never aggregates.

## Design notes

- Navy (from the logo) appears in one place: the top bar and the "where and when" band under it.
- Status always shows as a shape and a word, never colour alone: a check for on target, a half
  circle for close, a down triangle for behind. No green/amber/red trio is colourblind-safe by
  itself (checked with the dataviz palette validator).
- Charts follow the dataviz rules: 2px lines, solid hairline grids, a crosshair tooltip with
  keyboard support, and a table view as the accessible alternative. Additive KPIs compare
  units with bars from zero; averages and rates use dots on a fitted scale.
