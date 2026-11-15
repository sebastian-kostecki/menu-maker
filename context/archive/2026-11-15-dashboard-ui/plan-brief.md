# Dashboard with Real Data — Plan Brief

> Full plan: `context/changes/dashboard-ui/plan.md`

## What & Why

The dashboard is the first screen after every login, yet today it shows fake numbers (`12`, `3`, "2 dni temu") and an "Ostatni PDF" card. That card has no data behind it, because PDFs are generated on demand. Roadmap S-16 (DS-11) makes it truthful and puts it on the design system. It shows the user's own recipe and plan counts and the newest ready plan with its PDF. It warns when a week cannot be generated yet and shows a first step instead of zeros to a new user.

## Starting Point

`/dashboard` is a route closure rendering `Dashboard.vue` without props: raw `div` cards, off-system icons, an emoji welcome box and a hard-coded `/recipes/create` URL. The backend pieces already exist:
- owner scopes `Recipe::forUser` and `MealPlan::ownedBy`;
- `MealPlanGenerator::missingRecipesPerCategory()` and `shortfallMessages()`, the same text the generate dialog rejects with;
- `MealPlanResource` with `links.pdf` only for `done` plans.

## Desired End State

An invokable `DashboardController` passes `stats`, `latestMealPlan` and `recipeShortfall`. The page shows one of three states:
- **First run (no recipes and no plans):** a single `EmptyState` with "Dodaj pierwszy przepis".
- **Otherwise:**
  - a `warning` Alert, only when a category has fewer than 7 recipes;
  - "Przepisy" and "Jadłospisy" stat cards;
  - a "Ostatni jadłospis" card with dates, "Otwórz jadłospis" and "Pobierz PDF";
  - quick actions "Dodaj przepis" and "Wygeneruj jadłospis" (the existing dialog, with an unchanged request).

The full and first-run states are pixel-gated.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| "Ostatni jadłospis" | Newest `done` plan by `start_date desc, id desc`; `processing`/`error` never shown | It is the week the family cooks next, and the unique `(user, start_date)` index makes the order unambiguous | Plan |
| Plan count | All owned plans, any status | Equals the `/meal-plans` total, so the numbers never disagree | Plan |
| Generation readiness | `warning` Alert with the existing shortfall sentences, only when short | The dashboard and the dialog say exactly the same thing; nothing extra when ready | Plan |
| Empty state trigger | Only when recipes = 0 **and** plans = 0 | A returning user who deleted recipes keeps access to their last PDF | Plan |
| Test coverage | Pest props + 3 updated dashboard PNGs + 1 new empty-state PNG for a dedicated seeded user | Both page branches are pixel-gated, and the dedicated user is not touched by the E2E specs | Plan |
| Controller shape | Invokable, no policy; owner scoping in queries, proven by tests | The dashboard is not a resource; follows `ClientErrorController` | Plan |
| `StatCard` | Feature component in `Components/Dashboard/`, no kitchen-sink demo | Avoids `DesignSystem.vue` changes and the known overlay-PNG churn | Plan |
| URL and header | `/dashboard` and `PageHeader` in the `#header` slot stay | About ten specs and the login redirects depend on the URL; DS §5.2 already places Dashboard there | Roadmap / DS |

## Scope

**In scope:**
- `DashboardController` and the route swap
- `DashboardControllerTest`
- `Components/Dashboard/StatCard.vue` and the `Dashboard.vue` rewrite
- `lint-tokens.paths`
- the empty-dashboard seeded user and its seeder test
- the visual spec and baselines
- the `ui-plan.md` §2.1 and `CLAUDE.md` updates

**Out of scope:**
- the `/` redirect (S-15)
- a per-category breakdown when ready
- "last generated" ordering
- a `done`-only count
- a shared `StatCard` or kitchen-sink demo
- a shortfall PNG
- a new functional E2E spec
- toasts, polling, deferred props
- any change to `GenerateMealPlanDialog`

## Architecture / Approach

The controller runs owner-scoped queries:
- `Recipe::forUser()->count()`
- `MealPlan::ownedBy()->count()`
- the newest `done` plan, passed through `MealPlanResource`
- `MealPlanGenerator`'s shortfall

The result is the Inertia props of `Dashboard`. The Vue page branches on those props; it derives nothing relative to "today", so the pixel-exact baselines stay stable under the pinned browser clock. The order is contract first, then pixels: Phase 1 pins the props in tests, Phase 2 builds the view, and Phase 3 records the baselines.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Backend contract | Controller, route, feature test for counts / latest done / shortfall / scoping | Leaking another user's data. Covered by explicit cross-user cases |
| 2. Dashboard view | `StatCard` and `Dashboard.vue` in three states, `lint:tokens` green | Substring locator collisions ("Przepisy", "Dodaj przepis"). Stat cards are not links, and the names are distinct |
| 3. Fixtures, visual gate and docs | Empty-dashboard user, 3 M + 1 new PNG, ui-plan/CLAUDE.md | Unexpected PNG changes elsewhere; any delta beyond the four is a regression |

**Prerequisites:** S-07 and S-08 are done. The worktree needs its own `.env`, ports and Sail stack (see the plan's Environment section).
**Estimated effort:** about one session across 3 small phases.

## Open Risks & Assumptions

- The visual run fails on the three dashboard PNGs between Phase 2 and Phase 3. This is expected.
- Soft-deleted recipes are excluded from the count, consistent with the generator.
- If `develop` moves (other slices touching `app-shell` PNGs), merge it before recording baselines.

## Success Criteria (Summary)

- The visual user sees 21 recipes, 4 plans and the 15–21 June 2026 plan with a working PDF, and no alert.
- A new user sees only "Dodaj pierwszy przepis". A user short on recipes sees exactly the sentences the generate dialog would show.
- CI is green: Pest, Larastan, `lint:tokens`, E2E, and a visual gate with exactly 3 changed and 1 new PNG.
