# Real Weekly Meal Plan (S-03) — Plan Brief

> Full plan: `context/changes/real-weekly-meal-plan/plan.md`

## What & Why

A user should be able to generate a real 7 days × 3 meals plan from their own recipes in one confirmed click, see it in the app, and regenerate it without limit. Recipes are drawn at random by category with no repeats in the week (FR-4, US-008, US-009). This is the roadmap's north star: the first moment Menu Maker does what it exists for. AI scaling, the shopping list and the PDF all build on it.

## Starting Point

- **Generation is fake.** `GenerateMealPlanJob` sleeps for 2 s and marks the plan `done` without creating any meal.
- **Both entry points are broken.** The Generate button sends a GET to a POST-only route, and the dashboard links to a missing `/meal-plans/create`. Regeneration returns JSON to Inertia, which cannot use it.
- **The plan view is broken and in English.** It reads fields the API never sends.
- **Regeneration is rate-limited** to 5/hour, against the PRD.
- **No queue worker runs** in Sail or in CI E2E.

## Desired End State

From the list or the dashboard, "Wygeneruj jadłospis" opens a dialog prefilled with the nearest upcoming Monday. Confirming leads to the plan view, which polls every 3 s and then shows a grid: rows are the days, columns are Śniadanie / Obiad / Kolacja, and each cell has the recipe name, kcal and servings. No recipe repeats in a column. "Regeneruj" redraws all 21 meals, with no limit. With fewer than 7 recipes in any category, the request is refused up front with a Polish message per category ("…masz 4, potrzeba 7. Dodaj jeszcze 3."). A Playwright test covers the click → grid → regenerate path against Sail with a real queue worker.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Fewer than 7 recipes in a category | Refuse before queuing, Polish message per short category | Keeps "no repeats" strict and keeps a predictable user mistake out of error reporting. |
| What "new set" on regenerate means | Independent random draw (with exactly 7, only the day order changes) | One algorithm for generate and regenerate, testable through invariants alone. |
| Rate limits | Drop the regeneration limit and `force`; keep the "not while processing" guard and the 5/h limit on new plans | Matches US-009 literally, while creating new plans stays protected. |
| Category mapping | `supper` = Obiad, `dinner` = Kolacja (display only, no enum change) | The enum order matches the PRD order śniadanie/obiad/kolacja; S-04's 30/40/30 split follows it. |
| Start date for "one click" | Shared dialog, nearest upcoming Monday, editable | Matches the ui-plan flow (modal → POST → plan view) and still lets the user plan another week. |
| Execution | Queued job + 3 s `usePoll`; a `queue` service in Sail and a worker in CI E2E | The same async path S-04 (AI, up to ~1 min) and F-02 (production worker) need. |
| Week view | 7×3 grid: name (linked) + kcal + servings; no ingredients | Small and testable; ingredients arrive scaled with S-04/S-05. |
| Where the logic lives | New `app/Services/MealPlanGenerator` (approved new folder) | One tested unit the job and requests share, which S-04 to S-06 extend instead of the job. |
| Recipes deleted after the click | The job sets `error` with a Polish message and does not report it | It's user data, not an app fault (F-01 alerts stay meaningful). |

## Scope

**In scope:**
- The selection service and the real job.
- Shortfall validation and Polish messages in both Form Requests.
- Removal of the regeneration limit.
- Inertia redirects from `store`/`update`/`destroy` (JSON kept).
- The generate dialog (list + dashboard) and the week-grid view with polling (plus the slow-pending and empty-plan states).
- Polish labels on the menu, the badges and the recipe form's category options.
- `withTrashed` meals; the job is dropped silently when its plan has been deleted.
- A Sail `queue` service, a CI worker and seeding, the E2E seeder and a Playwright spec.
- The E2E command in `CLAUDE.md`.

**Out of scope:**
- AI or family scaling (S-04), the shopping list (S-05), the PDF (S-06); "Pobierz PDF" stays disabled until a file exists.
- Ingredients in the grid.
- Allowing repeats when recipes are short.
- Guaranteeing that a regeneration differs from the previous version.
- Renaming the enum.
- Removing the new-plan limit.
- Dashboard statistics and the generation-log UI.
- A production worker (F-02).

## Architecture / Approach

```
Dialog / Regeneruj ──POST/PUT──▶ Form Request ──(shortfall? 422 recipes)──▶ Controller
                                   │ uses MealPlanGenerator::missingRecipesPerCategory
Controller ──dispatch──▶ GenerateMealPlanJob ──▶ MealPlanGenerator::generate (tx: delete + 21 meals)
Controller ──redirect──▶ MealPlans/Show ──usePoll 3 s while pending/processing──▶ MealPlanGrid
```

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Selection service and job | `MealPlanGenerator` + the real job, unit-tested | Breaking the F-01 job error contract (first update must stay `processing`) |
| 2. HTTP generate and regenerate | Early shortfall refusal, unlimited regeneration, Inertia redirects | Existing JSON tests and rate-limit tests need careful updating |
| 3. Generate dialog and week view | One-click entry, the 7×3 grid, polling, Polish copy | Poll not stopping/restarting; UTC shift in the default Monday |
| 4. Queue worker and E2E | Sail `queue` service, CI worker and seed, Playwright spec | Flaky E2E from shared fixtures (mitigated: one user per browser, seeder resets) |

**Prerequisites:** F-01 done (it is). Sail up. The user approved the new `app/Services/` folder.
**Estimated effort:** ~2–3 sessions across 4 phases.

## Open Risks & Assumptions

- With exactly 7 recipes per category, a regeneration only reshuffles the days. This is accepted, but users may perceive it as "nothing changed".
- Placeholder-era `done` plans have no meals; they show the empty/regenerate state and are not migrated.
- The <10 s target (US-008) is dominated by worker pick-up and the poll interval. It is checked manually in Phase 4, not asserted in CI.
- `queue:listen` in Sail and a manual `composer dev` would run two workers. This is harmless but worth knowing.

## Success Criteria (Summary)

- A user with 21+ recipes gets a visible 7×3 week with no repeats per category, without refreshing the page, and can regenerate it as often as they like.
- A user with too few recipes is told, in Polish and before waiting, exactly which categories need how many more recipes.
- Pest, Larastan, the build and the new Playwright spec pass locally and in CI.
