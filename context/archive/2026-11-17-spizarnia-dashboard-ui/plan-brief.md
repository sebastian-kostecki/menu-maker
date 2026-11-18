# Spiżarnia Dashboard UI — Plan Brief

> Full plan: `context/changes/spizarnia-dashboard-ui/plan.md`

## What & Why

Roadmap S-23 (M-4, SP-04) restyles the dashboard to the Spiżarnia direction from artifact screens 05–06. The dashboard is the first screen after login, so it should look like the rest of the restyle. The meal colours carry the week, the hero carries the latest plan, and the stats become full colour tiles. The only new data is the latest plan's meals; everything else is layout.

## Starting Point

The shell, tokens and primitives are already Spiżarnia (F-05, F-06, S-22), and S-25 restyled the meal plans. The dashboard is still the Zielnik layout:
- two small stat cards with an icon on `accent`;
- a „Ostatni jadłospis” card that has only a date and buttons;
- a „Szybkie akcje” card.

`latestMealPlan` reaches the page without its meals.

## Desired End State

- **Header:** greets „Dzień dobry, {imię}.” and has the actions „Wygeneruj jadłospis” and „Dodaj przepis”.
- **Hero:** a `highlight` block with the latest ready plan: date range, „Gotowy”, „21 posiłków…”, „Otwórz jadłospis” and „Pobierz PDF”.
- **Stats:** two tiles beside the hero, „21 przepisów” on honey and „5 jadłospisów” on tomato.
- **Week card:** below, it shows that plan's 7 days × 3 meals as colour blocks. It is titled „Ten tydzień” only when today falls in that week. On a phone the days stack as rows.
- **Brand-new user:** sees the screen-06 tile.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Week card data and title | Latest `done` plan, titled „Ten tydzień” only if today is in it, else „Tydzień {range}” | Uses the roadmap's data source and never labels next week's or an old plan as this week | Plan |
| Greeting | „Dzień dobry, {user.name}.” with the stored name | Personal like the mockup; vocative and status line dropped | Plan |
| „dla N osób” | Dropped; hero counts filled meals only | The plan stores no people count, and today's family can differ from the scaled one | Research |
| Shared components | Additive only: `Button` `inverse`/`inverse-outline`, `PdfDownloadMenu` `variant` prop; `PageHeader`/`EmptyState` untouched | S-24 and S-26 run in parallel on the same shared files and baselines | Plan |
| `PageHeader` title gap | Logged under `## Parked`, fixed once after S-23…S-26 merge | Changing it moves every page's baselines | Plan |
| Empty / linked meals in the week | Mirror the grid compactly: dashed „Bez posiłku”, `tag` badge („Z wczoraj”, „Z kolacji”…), plain names | Same vocabulary as the meal-plan grid; two equal names need the tag | Plan |
| Mobile week | Days stack as rows (label above 3 blocks), 7 columns from `lg` | Readable names without sideways scrolling, no arbitrary grid values | Plan |
| No ready plan yet | Hero says „Nie masz jeszcze gotowego jadłospisu”, no week card | The header already offers generating a plan | Plan |
| Kitchen sink | New last section „Pulpit” with its own baseline | Leaves existing section baselines alone and avoids conflicts with the parallel slices | Research |
| Focus on `highlight` | Inverse variants use a `highlight-foreground` focus outline | `ring` and `highlight` are the same colour, so the default ring would vanish | Research |

## Scope

**In scope:**
- `DashboardController` loads `meals.recipe`, plus three Pest tests
- `Button` `inverse` / `inverse-outline`, `PdfDownloadMenu` `variant`
- `Dashboard/StatCard` (rebuilt), `LatestPlanHero`, `WeekOverview`, `FirstRunTile`, `lib/mealWeek.js`
- `Pages/Dashboard.vue` assembled; „Szybkie akcje” removed
- Kitchen-sink section „Pulpit”, `section-pulpit.png`, `inverse-focus-visible.png`, the four `dashboard-*.png` baselines
- `design-system.md` §5.1/§5.3, a roadmap `## Parked` note

**Out of scope:**
- `PageHeader` / `EmptyState` restyle, people count, recipe links in week blocks
- Ingredients on the dashboard, seeder changes, `MealPlanGrid` refactor
- Generate, PDF or recipe flow changes, dark mode, PDF

## Architecture / Approach

Data first: one `load('meals.recipe')` makes `latestMealPlan.meals` identical to what `MealPlans/Show` receives. Each screen-05/06 piece is then a component in `Components/Dashboard/`, demoed in every state in a new kitchen-sink section. The seeded plan has no empty or linked meals, so the kitchen sink is where those states are seen. The page is assembled last and refreshes only the four dashboard baselines in `visual/app-shell.spec.ts`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Posiłki ostatniego planu w propsach | `latestMealPlan.meals` with recipes, owner-scoped, Pest-guarded | Leaking meals of a non-`done` or another user's plan |
| 2. Klocki pulpitu w kitchen sinku | Inverse buttons, PDF trigger variant, hero, stat tile, week card, first-run tile, „Pulpit” section and 2 new baselines | Invisible focus ring on `highlight`; dynamic colour classes Tailwind never generates; existing kitchen-sink PNGs drifting |
| 3. Strona pulpitu i bramka | Dashboard matching screens 05–06, 4 refreshed baselines, docs and roadmap note | Week title or day grouping off by a day through UTC vs local dates |

**Prerequisites:**
- S-22 in `develop` (done).
- A worktree `.env` with `COMPOSE_PROJECT_NAME=mm-dashboard-ui` and ports 8093/5193/5493/1093/8094.
- `AI_SCALING_DRIVER=fake`, and Sail with `queue` and Mailpit running.
- `E2eMealPlanSeeder` re-run before every E2E or visual run.
- The visual gate run with `VISUAL_DOCKER_NETWORK=mm-dashboard-ui_sail`.

**Estimated effort:** about 2 sessions. Phase 1 is short; phases 2 and 3 carry the visual work.

## Open Risks & Assumptions

- S-24 and S-26 also edit `Pages/Dev/DesignSystem.vue`. Appending „Pulpit” last keeps conflicts small. The kitchen-sink baselines still need one refresh after all four slices merge (§10.6).
- Accepted mockup deviations:
  - hero `h2` at 36 px instead of 40 px;
  - empty-state heading at 24 px instead of 28 px;
  - „Pon. 15.06” instead of „Pon 15.06”;
  - the mobile day label above its blocks;
  - the dashboard `h1` stays 24 px until the parked `PageHeader` fix.
- The greeting shows whatever is stored as the name, so screenshots read „Dzień dobry, E2E Planner.”

## Success Criteria (Summary)

- At 1280 px the dashboard matches screens 05–06, and it stays usable at 1024 and 390 px with a visible focus ring on every control, including on `highlight`.
- Pest (with the new meals tests), Larastan, Pint, `lint:tokens`, the build and the functional E2E all pass.
- The visual gate is green, with exactly two new kitchen-sink baselines and four refreshed dashboard baselines, each reviewed.
