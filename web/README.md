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
npm test             # unit tests (periods, charts, CSV and templates, KPI rules, data entry, formatting)
npm run build        # static build in build/; for a production release use ../scripts/release.sh
```

## Where things are

| Path | What |
|---|---|
| `src/routes/(app)/+page.svelte` | Scorecard: one unit, one period, every KPI, and the units × KPIs matrix; the getting-started guide for admins |
| `src/routes/(app)/kpis/[id]` | One KPI: the figure with target, change and reports in; trend chart or table; by-unit breakdown; how it's calculated; recent changes with notes |
| `src/routes/(app)/enter` | "Add data": who still has the month's figures to enter, then one unit's form (every week or day of weekly and daily KPIs, notes, "Next: …") |
| `src/routes/(app)/import` | Uploading a CSV or Excel file: choose (or download a template), match columns (remembered per set of columns), check, import |
| `src/routes/(app)/admin/units` | Structure: the editable tree, a slide-in panel per unit (including who has access) and a tutorial |
| `src/routes/(app)/admin/kpis` | KPIs as cards, a four-step "New KPI" guide, a slide-in panel to change one, a tutorial |
| `src/routes/(app)/admin/users` | People: roles and access in a slide-in panel, inviting, pending invitations, a tutorial |
| `src/routes/(app)/admin/settings` | The organisation's name and word for units, the getting-started guide, deleting the organisation |
| `src/routes/(app)/account` | Your name and password, leaving organisations, deleting your account |
| `src/routes/(auth)` | Sign in, create account, forgot password |
| `src/routes/reset-password/[token]`, `verify-email/[token]` | Links from emails |
| `src/routes/onboarding` | After sign-up: name, structure, starter KPIs (`src/lib/starter-kpis.ts`), invites |
| `src/routes/invite/[token]` | Accept an invitation link |
| `src/lib/api.ts` | Typed API client |
| `src/lib/components` | Shared pieces (`Tour`, `Tooltip`, `InlineEdit`, `OptionCards`, `ConfirmDialog`, charts drawn in SVG), with one folder per screen: `structure/`, `kpis/`, `people/`, `entry/`, `import/`, `kpi-detail/`, `onboarding/`, `auth/`, `account/`, `settings/` |
| `src/app.css` | Design tokens (light, and dark when chosen) and base component styles |

The unit, period and period length live in the URL (`?unit=&date=&grain=`), so every view can be
bookmarked or shared. All numbers come from the API; the frontend never aggregates.

The interface never says "unit": it uses the organisation's own word (`term` in
`src/lib/terms.svelte.ts`, e.g. "2 of 3 branches"), or a list's shared kind ("Regions in …") via
`wordFor()`. Page bands and bodies share one centred width (`PageBand.svelte`).

## Design notes

- Light theme by default; the moon button in the top bar switches to dark and remembers it.
- Screens follow one pattern: cards with an icon heading, details in a panel that slides in from
  the right, changes saved from a bar that appears only when something changed, and questions
  asked in the app's own dialogs, never the browser's. Motion is short and switched off for
  people who ask their system for reduced motion.
- Each Manage tab opens a short tutorial on the first visit; the **?** button brings it back
  (`Tour.svelte`, remembered in the browser).
- Excel files are read with `read-excel-file`, loaded only when someone chooses one.

- Navy (from the logo) appears in one place: the top bar and the "where and when" band under it.
- Status always shows as a shape and a word, never colour alone: a check for on target, a half
  circle for close, a down triangle for behind. No green/amber/red trio is colourblind-safe by
  itself (checked with the dataviz palette validator).
- Charts follow the dataviz rules: 2px lines, solid hairline grids, a crosshair tooltip with
  keyboard support, and a table view as the accessible alternative. Additive KPIs compare
  units with bars from zero; averages and rates use dots on a fitted scale.
