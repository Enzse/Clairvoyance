# Demo scenarios

Two realistic scenarios for presenting Clairvoyance, each with a user story, a use case, a
step-by-step user flow and spreadsheets to load. They show different sides of the app:

| | Scenario 1: Kapehan Coffee Co. | Scenario 2: Bayanihan Logistics |
|---|---|---|
| Organisation | A café chain, 5 branches in 2 regions | A courier, 5 depots in 3 regions |
| Figures | Monthly | Weekly |
| Shows | Onboarding, building the structure, a new KPI, targets, inviting a branch manager, typing in figures with a note, the scorecard's roll-ups and like-for-like change | Importing a spreadsheet that builds the structure, weighted averages, different targets per depot, a KPI only some depots report, a view-only regional manager, a repeat upload matched automatically |
| Time | about 8 minutes | about 6 minutes |

The companies and figures are made up. The spreadsheets are in this folder.

## Before you present

1. **Date the spreadsheets for the day.** The files are dated around the day they were made, so
   "this month" and "last week" match the calendar. On the day (or the day before), run:

   ```bash
   php docs/demo/make_datasets.php 2026-11-05     # your presentation date
   ```

2. **Use one email inbox for every role.** Gmail ignores anything after a `+`, so
   `you+kapehan@gmail.com`, `you+cubao@gmail.com` and so on all arrive in your inbox while the app
   treats them as different people.
3. **Use two browser windows**: a normal one for the administrator and a private one for the
   person who is invited. Signing in as two people in one window signs the first one out.
4. **Do the "Prepare" steps beforehand.** They're slow to watch; the "Live" steps are the demo.

---

## Scenario 1: Kapehan Coffee Co.

### User story

> As **Maria, the operations manager** of a five-branch café chain, I want every branch's monthly
> figures in one scorecard that adds them up the right way, so that I can see which branch is
> slipping before the month-end meeting, instead of copying five spreadsheets into one.

> As **Ben, the Cubao branch manager**, I want to type in my branch's figures in a couple of
> minutes and explain anything unusual, so that head office doesn't call me about numbers they
> misread.

### Use case: Monthly branch reporting

| | |
|---|---|
| **Primary actor** | Operations manager (administrator) |
| **Supporting actor** | Branch manager (member who enters data for one branch) |
| **Goal** | See the chain's month against target, find the branch that is behind, and why |
| **Preconditions** | Maria has an account. Last 11 months of figures exist in a spreadsheet. |
| **Trigger** | Month end: branches report, and Maria prepares the management meeting |

**Main success scenario**

1. Maria creates the organisation, its five branches and its KPIs.
2. She groups the branches into North and South regions.
3. She adds a KPI of her own, Food waste, and sets a target for each KPI.
4. She uploads last year's spreadsheet; the app checks it and saves 11 months of figures.
5. She invites Ben to enter data for Cubao.
6. Ben joins, sees only Cubao, and types in October's figures with a note on food waste.
7. Maria opens the scorecard: every branch has reported and the chain is on target for revenue.
   South is close on complaints and food waste; opening it shows Alabang behind on revenue,
   complaints and food waste.
8. She opens Revenue for the trend and the branch breakdown, and Food waste for Ben's note.

**Alternative flows**

- **4a. A row in the spreadsheet is wrong** (a misspelt branch, a word in a number column): the
  check lists the row and column; Maria fixes the file or imports the rest and skips it.
- **6a. Ben hasn't reported yet**: the scorecard says some figures are still missing, the
  "Reports" column shows 4/5, and Add data lists Cubao under "Still to do".
- **7a. A month is incomplete**: the change from last month compares only the branches that
  reported both months ("2 of 3 branches"), so a missing branch doesn't look like a drop.

**Postconditions:** October is complete for all five branches; Ben's note is kept with the
figure and in its history.

### Prepare (before the panel)

1. Sign up as Maria (`you+kapehan@gmail.com`). In the setup steps:
   - **Name:** Kapehan Coffee Co., money in **₱**.
   - **Structure:** *Several locations*, names `Katipunan`, `Cubao`, `Makati`, `BGC`, `Alabang`,
     each one is a **Branch**.
   - **KPIs:** tick **Revenue, Orders, Customer satisfaction, Complaints** (untick Operating
     expenses).
   - **Team:** skip for now.

### Live user flow

| # | Who | Where | What to do | What the panel should notice |
|---|---|---|---|---|
| 1 | Maria | Manage › Structure | **New group** → `North`, move Katipunan and Cubao in. Again for `South` with Makati, BGC, Alabang. | The tree updates in place; the scorecard will add branches up into regions automatically. |
| 2 | Maria | Manage › KPIs | **New KPI** → name `Food waste`; *added up* over time and across branches; **Lower** is better, target **120**, written as ` kg` after the number (with a space); everyone reports it. | The worked example shows how the rule adds up before saving. |
| 3 | Maria | Manage › KPIs | Open each KPI and set its target: Revenue **₱1,100,000**, Customer satisfaction **90%**, Complaints **6**. Leave Orders without a target. | Targets are per branch per month; regions and the chain get theirs added up the same way as the figures. Orders has no target on purpose: it only says how busy a branch is. |
| 4 | Maria | Add data › Upload a spreadsheet | Upload `kapehan-history.csv`. Every column is matched by itself; **Check the file** → **Import**. | 51 rows, 255 figures, no problems; BGC simply starts later (it opened in the spring). |
| 5 | Maria | Upload again | Upload `kapehan-this-month.csv`: "Matched the same way as the last file". Import. | The matching is remembered for files with the same columns. |
| 6 | Maria | Scorecard | Read the top line: figures still missing. **Reports** shows 4/5. | Who hasn't reported is visible at once. |
| 7 | Maria | Manage › Structure | Open **Cubao** → Who has access → **Invite** → `you+cubao@gmail.com`, *Enters data*. Copy the link. | Access is given to a branch, not to the whole company. |
| 8 | Ben (private window) | Invitation link | **Create your account to join** → name Ben Cruz → **Join**. | Ben lands on Cubao's scorecard; there's no Manage menu for him. |
| 9 | Ben | Add data | Type: Revenue `1064500`, Orders `7120`, Customer satisfaction `92`, Complaints `4`, Food waste `131`. Under Food waste, **Add a note**: `Freezer broke down on the 14th; two days of pastries thrown out.` **Save**. | Last month's figure sits next to each field; Food waste shows as *close*, not behind, because of the 15% tolerance. |
| 10 | Maria | Scorecard (refresh) | "Everyone has reported." Look at the **Regions** grid, then click **South**. | The chain and South look fine on revenue (Makati carries them); South is only *close* on complaints and food waste. Inside South, **Alabang** is behind on revenue, complaints and food waste, and new BGC is close on revenue. |
| 11 | Maria | Click **Revenue** | Chart and table, then **By branch**; click Alabang. | Alabang has slid from about ₱1.04M in the spring to ₱0.9M; the chain is still on target because Makati and BGC grow. |
| 12 | Maria | Click **Food waste**, then Cubao | **Recent changes** shows Ben's figure and his note. | The note travels with the figure; nobody has to ask. |
| 13 | Maria | Scorecard → **Quarter** | Switch Month → Quarter → Year. | Monthly figures add up into quarters and years by each KPI's own rule (satisfaction is averaged, revenue added). |

**Talking points:** the aggregation rules (added up vs averaged, per KPI), targets that add up
with the figures, like-for-like change, access per branch, notes and change history.

---

## Scenario 2: Bayanihan Logistics

### User story

> As **Carmela, the operations director** of a courier with depots across Luzon, Visayas and
> Mindanao, I want each depot's weekly figures loaded straight from the spreadsheets they already
> send, and judged against targets that fit each depot, so that the Monday meeting starts from the
> few depots that need attention.

> As **Joel, the Visayas regional manager**, I want to see only my depots, so that I can follow up
> on Iloilo's late deliveries without wading through the whole company.

### Use case: Weekly depot performance review

| | |
|---|---|
| **Primary actor** | Operations director (administrator) |
| **Supporting actor** | Regional manager (member who can view one region) |
| **Goal** | Load the weekly depot report, see which depots miss their targets, share one region with its manager |
| **Preconditions** | Carmela has an account and the KPIs are set up. The depots send a weekly spreadsheet. |
| **Trigger** | Monday: last week's depot report arrives |

**Main success scenario**

1. Carmela uploads 15 weeks of depot reports; the app builds the regions and depots from the
   file and saves the figures.
2. She sets targets that differ per depot (Manila Hub is twice the size of most depots; Iloilo
   is smaller).
3. She limits Cold-chain breaches to the two depots with cold storage.
4. She uploads last week's file; it is matched like the first one.
5. The scorecard shows Davao behind on fuel cost and Visayas close on on-time delivery;
   drilling into Visayas shows Iloilo is the depot behind.
6. She gives Joel view-only access to Visayas; Joel sees Cebu and Iloilo only.

**Alternative flows**

- **1a. The file names a depot that doesn't exist** and "Add depots that don't exist yet" is not
  ticked: the check lists those rows instead of guessing.
- **5a. Carmela looks at the whole company**: on-time delivery is a weighted average, so a large
  depot counts more than a small one, and the company figure isn't dragged down unfairly by
  Iloilo's 900 parcels.

**Postconditions:** 16 weeks of figures for 5 depots; Joel can view Visayas and nothing else.

### Prepare (before the panel)

1. Sign up as Carmela (`you+bayanihan@gmail.com`). Setup: name **Bayanihan Logistics**, money in
   **₱**; Structure: **Skip for now**; KPIs: **Skip for now**; Team: skip.
2. **Manage › Settings** → Your word for units: type `depot`.
3. **Manage › KPIs**, create five KPIs with **New KPI**, all reported **Weekly**:

   | Name | How it adds up | What's good | Target | Written as |
   |---|---|---|---|---|
   | Parcels delivered | Added up / Added up | Higher | 1,200 | — |
   | On-time delivery | Averaged / **Weighted** by Parcels delivered | Higher, close within **3%** | 95 | `%` after, 1 decimal |
   | Fuel cost | Added up / Added up | Lower, close within **10%** | 120,000 | ₱ before |
   | Damaged parcels | Added up / Added up | Lower, close within **25%** | 12 | — |
   | Cold-chain breaches | Added up / Added up | Lower, close within **100%** | 1 | — |

   Create Parcels delivered before On-time delivery, so it can be chosen as the weight.

### Live user flow

| # | Who | Where | What to do | What the panel should notice |
|---|---|---|---|---|
| 1 | Carmela | Manage › Structure | Show the tree is empty. | Nothing is set up by hand. |
| 2 | Carmela | Add data › Upload a spreadsheet | Upload `bayanihan-history.csv`. Region and Depot are ticked as a path, Week as the date, each KPI column matched. Tick **Add depots that don't exist yet**. **Check the file**: 8 new units (3 regions, 5 depots), 75 rows. **Import**. | The structure comes from the file: Luzon › Manila Hub, Clark; Visayas › Cebu, Iloilo; Mindanao › Davao. |
| 3 | Carmela | Manage › Structure | Show the tree the import built. | Regions add up their depots. |
| 4 | Carmela | Manage › KPIs → Parcels delivered | **More targets** → Manila Hub, leave the date as it is, **2,300**; another for Iloilo, **900**. Fuel cost: Manila Hub **₱180,000**, Cebu **₱135,000**. Save. | One KPI, different targets where depots differ in size. |
| 5 | Carmela | Manage › KPIs → Cold-chain breaches | Who reports it → **Only some** → tick Manila Hub and Cebu. Save. | The other depots no longer count as "missing" for a KPI that doesn't apply to them. |
| 6 | Carmela | Add data › Upload | Upload `bayanihan-last-week.csv`: "Matched the same way as the last file". Import. | The Monday routine is one upload. |
| 7 | Carmela | Scorecard | Read the top line and the **Regions** grid. The month isn't over, so "2 of 4" weeks are in: say so. | Mindanao is behind on fuel cost. Visayas is only *close* on on-time delivery: the weighted average lets busy Cebu outweigh small Iloilo. |
| 8 | Carmela | Click **Visayas**, then **On-time delivery** | Chart, then **By depot**. | Drilling in finds the problem: Iloilo fell to about 90% in the last four weeks (behind), Cebu is fine. |
| 9 | Carmela | Manage › Structure → open Visayas | Who has access → **Invite** `you+joel@gmail.com`, **Views**. Copy the link. | Access to a region covers every depot in it. |
| 10 | Joel (private window) | Invitation link | Create the account, **Join**. | Joel's scorecard is Visayas: Cebu and Iloilo only, no Add data, no Manage. |
| 11 | Carmela | Scorecard → **Quarter** | Switch grain. | Weekly figures add up into months and quarters. |

**Talking points:** importing builds the structure, weighted averages (big depots count more),
per-depot targets, KPIs that only some depots report, view-only access, and a weekly routine of
one upload.

---

## The spreadsheets

| File | Rows | Contents |
|---|---|---|
| `kapehan-history.csv` | 51 | 11 months × 5 branches (BGC from the month it opened): Revenue, Orders, Customer satisfaction, Complaints, Food waste |
| `kapehan-this-month.csv` | 4 | This month for every branch except Cubao, which Ben types in |
| `bayanihan-history.csv` | 75 | 15 weeks × 5 depots: Parcels delivered, On-time delivery, Fuel cost, Damaged parcels, Cold-chain breaches (blank where a depot has no cold storage) |
| `bayanihan-last-week.csv` | 5 | Last week, same columns |

They open in Excel or Google Sheets. Saving them as `.xlsx` works too; the app reads both.
